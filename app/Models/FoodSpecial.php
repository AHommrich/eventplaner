<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodSpecial extends Model
{
    protected $fillable = ['event_id', 'name'];

    /**
     * Owning event. Null = global, read-only seeded template visible to every
     * event; non-null = event-local custom entry. See the food_specials
     * migration and docs/EVENT_MANAGER_ROLE_PLAN.md §11 P0.1.
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function guests()
    {
        return $this->belongsToMany(Guest::class, 'guest_food_special');
    }
}
