<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotoGameAssignment extends Model
{
    protected $fillable = ['game_id', 'guest_id', 'task_id', 'override_id', 'photo_id', 'submitted_at'];

    protected $casts = ['submitted_at' => 'datetime'];

    public function game()
    {
        return $this->belongsTo(EventPhotoGame::class, 'game_id');
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function task()
    {
        return $this->belongsTo(PhotoGameTask::class);
    }

    public function photo()
    {
        return $this->belongsTo(Photo::class);
    }

    public function override()
    {
        return $this->belongsTo(EventTaskOverride::class);
    }
}
