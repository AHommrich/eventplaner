<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single station on an event's schedule (registry office, lunch, party, …),
 * with an optional structured location. Ordering is explicit via sort_order;
 * starts_at is the time-of-day shown to guests and the basis for per-group
 * visibility (see Group::schedule_visible_from).
 */
class ScheduleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'starts_at',
        'ends_at',
        'sort_order',
        'location_name',
        'location_street',
        'location_house_number',
        'location_postal_code',
        'location_city',
        'location_state',
        'location_country',
        'location_lat',
        'location_lng',
    ];

    protected $casts = [
        'location_lat' => 'float',
        'location_lng' => 'float',
        'sort_order' => 'integer',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
