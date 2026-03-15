<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvitationToken extends Model
{
    protected $fillable = ['token', 'family_id', 'guest_id'];

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    /** Alle Gäste die zu diesem Token gehören (Familie oder Einzelperson) */
    public function guests()
    {
        if ($this->family_id) {
            return $this->family->guests;
        }

        return collect([$this->guest])->filter();
    }
}
