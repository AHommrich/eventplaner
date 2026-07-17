<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushToken extends Model
{
    protected $fillable = [
        'user_id',
        'personal_access_token_id',
        'expo_token',
        'platform',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accessToken()
    {
        return $this->belongsTo(\Laravel\Sanctum\PersonalAccessToken::class, 'personal_access_token_id');
    }

    public function tickets()
    {
        return $this->hasMany(PushTicket::class);
    }
}
