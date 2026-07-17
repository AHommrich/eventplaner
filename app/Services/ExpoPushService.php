<?php

namespace App\Services;

use App\Models\Note;
use App\Models\PushTicket;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    private const ASSIGNMENT_TITLE = 'Neue Aufgabe vom Veranstalter';

    private const ASSIGNMENT_BODY = 'Öffne eveplan, um die Details zu sehen.';

    /** Send privacy-minimized assignment pushes and persist successful Expo ticket IDs. */
    public function sendAssignedNote(User $assignee, Note $note): int
    {
        $sent = 0;

        $assignee->pushTokens()
            ->whereHas('accessToken', fn ($query) => $query->where(
                fn ($expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now())
            ))
            ->orderBy('id')
            ->chunkById(100, function ($tokens) use ($note, &$sent) {
                $messages = $tokens->map(fn (PushToken $token) => [
                    'to' => $token->expo_token,
                    'sound' => 'default',
                    'channelId' => 'organizer-tasks',
                    'title' => self::ASSIGNMENT_TITLE,
                    'body' => self::ASSIGNMENT_BODY,
                    'data' => [
                        'type' => 'assigned_note',
                        'event_id' => $note->event_id,
                        'note_id' => $note->id,
                    ],
                ])->values()->all();

                $response = $this->request()
                    ->post(config('services.expo.send_url'), $messages)
                    ->throw();

                $tickets = $response->json('data', []);
                foreach ($tokens->values() as $index => $token) {
                    $ticket = $tickets[$index] ?? null;
                    if (! is_array($ticket)) {
                        continue;
                    }

                    if (($ticket['status'] ?? null) === 'ok' && filled($ticket['id'] ?? null)) {
                        PushTicket::firstOrCreate(
                            ['expo_ticket_id' => $ticket['id']],
                            ['push_token_id' => $token->id],
                        );
                        $sent++;

                        continue;
                    }

                    $error = data_get($ticket, 'details.error');
                    if ($error === 'DeviceNotRegistered') {
                        $token->delete();
                    }

                    Log::warning('Expo rejected a push ticket.', ['error_code' => $error]);
                }
            });

        return $sent;
    }

    /** Fetch due Expo receipts, prune invalid tokens, and expire old ticket state. */
    public function fetchPendingReceipts(): int
    {
        $now = now();
        $maxAgeHours = (int) config('services.expo.receipt_max_age_hours', 24);
        $delayMinutes = (int) config('services.expo.receipt_delay_minutes', 15);
        $processed = 0;

        PushTicket::query()
            ->whereNull('checked_at')
            ->where('created_at', '<=', $now->copy()->subHours($maxAgeHours))
            ->update([
                'receipt_status' => 'expired',
                'error_code' => 'ReceiptExpired',
                'error_message' => 'Expo receipt was not available within its retention window.',
                'checked_at' => $now,
            ]);

        PushTicket::query()
            ->whereNull('checked_at')
            ->where('created_at', '<=', $now->copy()->subMinutes($delayMinutes))
            ->orderBy('id')
            ->chunkById(1000, function ($tickets) use (&$processed) {
                $response = $this->request()
                    ->post(config('services.expo.receipts_url'), [
                        'ids' => $tickets->pluck('expo_ticket_id')->values()->all(),
                    ])
                    ->throw();

                $receipts = $response->json('data', []);
                foreach ($tickets as $ticket) {
                    $receipt = $receipts[$ticket->expo_ticket_id] ?? null;
                    if (! is_array($receipt)) {
                        continue;
                    }

                    $error = data_get($receipt, 'details.error');
                    $ticket->update([
                        'receipt_status' => $receipt['status'] ?? 'error',
                        'error_code' => $error,
                        'error_message' => $receipt['message'] ?? null,
                        'checked_at' => now(),
                    ]);

                    if ($error === 'DeviceNotRegistered') {
                        $ticket->pushToken?->delete();
                    }

                    $processed++;
                }
            });

        PushTicket::query()
            ->whereNotNull('checked_at')
            ->where('checked_at', '<=', now()->subDays((int) config('retention.push_tickets_days', 7)))
            ->delete();

        return $processed;
    }

    private function request(): PendingRequest
    {
        $request = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('services.expo.timeout_seconds', 10));

        if (filled(config('services.expo.access_token'))) {
            $request->withToken(config('services.expo.access_token'));
        }

        return $request;
    }
}
