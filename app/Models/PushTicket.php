<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushTicket extends Model
{
    protected $fillable = [
        'push_token_id',
        'expo_ticket_id',
        'receipt_status',
        'error_code',
        'error_message',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    public function pushToken()
    {
        return $this->belongsTo(PushToken::class);
    }
}
