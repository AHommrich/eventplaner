<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected static function booted(): void
    {
        // Sanctum's tokenable morph has no FK, so account deletion must clean
        // bearer tokens explicitly instead of leaving orphaned credentials.
        static::deleting(fn (User $user) => $user->revokeApiTokens());
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_approved',
        'privacy_accepted_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_approved' => 'boolean',
            'privacy_accepted_at' => 'datetime',
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
        return $this->belongsToMany(Event::class)->withPivot('role');
    }

    public function devicePairings()
    {
        return $this->hasMany(DevicePairing::class);
    }

    public function pushTokens()
    {
        return $this->hasMany(PushToken::class);
    }

    /** User tokens are management credentials; revoke all in one place. */
    public function revokeApiTokens(): void
    {
        $this->tokens()->delete();
    }

    /** All events the user has access to (own + shared) */
    public function accessibleEvents()
    {
        if ($this->isAdmin()) {
            return Event::query();
        }

        return Event::where('user_id', $this->id)
            ->orWhereHas('users', fn ($q) => $q->where('users.id', $this->id));
    }

    /**
     * Resolve the actor's effective tier on a given event.
     *
     * Precedence: superadmin → primary/pivot owner → pivot event_admin/event_manager.
     * Returns null when the user has no relationship to the event.
     *
     * @return 'owner'|'event_admin'|'event_manager'|'superadmin'|null
     */
    public function roleOn(Event $event): ?string
    {
        if ($this->isAdmin()) {
            return 'superadmin';
        }

        if ($event->user_id === $this->id) {
            return 'owner';
        }

        $pivotRole = $event->users()
            ->where('users.id', $this->id)
            ->value('event_user.role');

        if ($pivotRole === null) {
            return null;
        }

        // 'owner' | 'event_admin' | 'event_manager' — passed through verbatim.
        return $pivotRole;
    }

    /** May perform manager-level actions (manager ∪ event_admin ∪ owner ∪ superadmin). */
    public function canManage(Event $event): bool
    {
        return in_array($this->roleOn($event), ['event_manager', 'event_admin', 'owner', 'superadmin'], true);
    }

    /** May perform administer-level actions (event_admin ∪ owner ∪ superadmin). */
    public function canAdminister(Event $event): bool
    {
        return in_array($this->roleOn($event), ['event_admin', 'owner', 'superadmin'], true);
    }
}
