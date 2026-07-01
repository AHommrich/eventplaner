<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvitationToken extends Model
{
    use HasFactory;

    protected $fillable = ['token', 'group_id', 'guest_id'];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    /** All guests belonging to this token (group or single person) */
    public function guests()
    {
        if ($this->group_id) {
            return $this->group->guests;
        }

        return collect([$this->guest])->filter();
    }
}
