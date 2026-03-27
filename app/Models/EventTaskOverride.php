<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventTaskOverride extends Model
{
    protected $fillable = ['event_id', 'task_id', 'action', 'custom_text'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function task()
    {
        return $this->belongsTo(PhotoGameTask::class);
    }
}
