<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'name'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function guests()
    {
        return $this->hasMany(Guest::class);
    }

    /**
     * Schedule stations this group must NOT see. Empty = sees every station.
     */
    public function hiddenScheduleItems()
    {
        return $this->belongsToMany(ScheduleItem::class, 'group_schedule_item_hidden');
    }

    public function invitationToken()
    {
        return $this->hasOne(InvitationToken::class);
    }
}
