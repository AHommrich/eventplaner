<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Family extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function guests()
    {
        return $this->hasMany(Guest::class);
    }

    public function invitationToken()
    {
        return $this->hasOne(InvitationToken::class);
    }
}
