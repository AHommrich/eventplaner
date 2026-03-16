<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvitationToken extends Model
{
    protected $fillable = ['token', 'group_id', 'guest_id'];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    /** Alle Gäste die zu diesem Token gehören (Gruppe oder Einzelperson) */
    public function guests()
    {
        if ($this->group_id) {
            return $this->group->guests;
        }

        return collect([$this->guest])->filter();
    }
}
