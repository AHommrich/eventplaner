<?php

use App\Models\Event;
use App\Models\Note;
use App\Models\PushTicket;
use App\Models\PushToken;
use App\Models\User;
use App\Services\ExpoPushService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function pushAssignmentFixture(): array
{
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create(['name' => 'Secret wedding']);
    $event->users()->attach($manager, ['role' => 'event_manager']);
    $note = Note::factory()->todo()->create([
        'event_id' => $event->id,
        'author_user_id' => $owner->id,
        'author_name' => 'Secret sender',
        'assignee_user_id' => $manager->id,
        'title' => 'Secret task title',
        'body' => 'Secret task body',
    ]);
    $accessToken = $manager->createToken('manager phone', ['management:*'])->accessToken;
    $token = PushToken::create([
        'user_id' => $manager->id,
        'personal_access_token_id' => $accessToken->id,
        'expo_token' => 'ExponentPushToken[manager-device]',
        'platform' => 'ios',
        'last_used_at' => now(),
    ]);

    return [$owner, $manager, $event, $note, $token];
}

it('sends generic assignment copy and persists Expo ticket ids', function () {
    [$owner, $manager, $event, $note, $token] = pushAssignmentFixture();
    Http::fake([
        config('services.expo.send_url') => Http::response([
            'data' => [['status' => 'ok', 'id' => 'ticket-123']],
        ]),
    ]);

    expect(app(ExpoPushService::class)->sendAssignedNote($manager, $note))->toBe(1);

    Http::assertSent(function (Request $request) use ($owner, $event, $note) {
        $encoded = json_encode($request->data());

        expect($request->url())->toBe(config('services.expo.send_url'));
        expect($encoded)
            ->toContain('Neue Aufgabe vom Veranstalter')
            ->toContain('organizer-tasks')
            ->toContain('assigned_note')
            ->not->toContain($note->title)
            ->not->toContain($note->body)
            ->not->toContain($event->name)
            ->not->toContain($owner->name);

        return true;
    });

    $this->assertDatabaseHas('push_tickets', [
        'push_token_id' => $token->id,
        'expo_ticket_id' => 'ticket-123',
        'checked_at' => null,
    ]);
});

it('deletes a token rejected immediately as DeviceNotRegistered', function () {
    [, $manager, , $note, $token] = pushAssignmentFixture();
    Http::fake([
        config('services.expo.send_url') => Http::response([
            'data' => [[
                'status' => 'error',
                'message' => 'Not registered',
                'details' => ['error' => 'DeviceNotRegistered'],
            ]],
        ]),
    ]);

    expect(app(ExpoPushService::class)->sendAssignedNote($manager, $note))->toBe(0);
    $this->assertDatabaseMissing('push_tokens', ['id' => $token->id]);
});

it('does not send to a device whose management bearer has expired', function () {
    [, $manager, , $note, $token] = pushAssignmentFixture();
    $token->accessToken->forceFill(['expires_at' => now()->subMinute()])->save();
    Http::fake();

    expect(app(ExpoPushService::class)->sendAssignedNote($manager, $note))->toBe(0);

    Http::assertNothingSent();
});

it('fetches receipts later and removes DeviceNotRegistered tokens', function () {
    [, , , , $token] = pushAssignmentFixture();
    $ticket = PushTicket::create([
        'push_token_id' => $token->id,
        'expo_ticket_id' => 'receipt-ticket',
    ]);
    $ticket->forceFill(['created_at' => now()->subMinutes(16)])->saveQuietly();
    Http::fake([
        config('services.expo.receipts_url') => Http::response([
            'data' => [
                'receipt-ticket' => [
                    'status' => 'error',
                    'message' => 'The device is no longer registered.',
                    'details' => ['error' => 'DeviceNotRegistered'],
                ],
            ],
        ]),
    ]);

    expect(app(ExpoPushService::class)->fetchPendingReceipts())->toBe(1);

    $this->assertDatabaseMissing('push_tokens', ['id' => $token->id]);
    $this->assertDatabaseHas('push_tickets', [
        'id' => $ticket->id,
        'push_token_id' => null,
        'receipt_status' => 'error',
        'error_code' => 'DeviceNotRegistered',
    ]);
});

it('expires unavailable receipts and prunes resolved diagnostics', function () {
    [, , , , $token] = pushAssignmentFixture();
    $expired = PushTicket::create([
        'push_token_id' => $token->id,
        'expo_ticket_id' => 'expired-ticket',
    ]);
    $expired->forceFill(['created_at' => now()->subHours(25)])->saveQuietly();
    $old = PushTicket::create([
        'push_token_id' => $token->id,
        'expo_ticket_id' => 'old-ticket',
        'receipt_status' => 'ok',
        'checked_at' => now()->subDays(8),
    ]);

    Http::fake();
    expect(app(ExpoPushService::class)->fetchPendingReceipts())->toBe(0);

    $this->assertDatabaseHas('push_tickets', [
        'id' => $expired->id,
        'receipt_status' => 'expired',
        'error_code' => 'ReceiptExpired',
    ]);
    $this->assertDatabaseMissing('push_tickets', ['id' => $old->id]);
    Http::assertNothingSent();
});
