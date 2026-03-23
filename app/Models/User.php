<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_approved',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_approved'       => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isApproved(): bool
    {
        return $this->isAdmin() || (bool) $this->is_approved;
    }

    public function ownedEvents()
    {
        return $this->hasMany(Event::class);
    }

    public function sharedEvents()
    {
        return $this->belongsToMany(Event::class);
    }

    /** Alle Events auf die der User Zugriff hat (eigene + geteilte) */
    public function accessibleEvents()
    {
        if ($this->isAdmin()) {
            return Event::query();
        }

        return Event::where('user_id', $this->id)
            ->orWhereHas('users', fn($q) => $q->where('users.id', $this->id));
    }
}
