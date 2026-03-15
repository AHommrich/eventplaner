<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Photo extends Model
{
    protected $fillable = ['guest_id', 'uploaded_by', 'url'];

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }
}
