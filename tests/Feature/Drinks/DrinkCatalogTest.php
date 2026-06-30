<?php

use App\Models\Drink;
use App\Models\DrinkCatalog;
use App\Models\DrinkCatalogSize;
use App\Models\Event;

it('shows the drink catalog index page', function () {
    actingAsOwner();

    $this->get(route('drinks.index'))->assertOk();
});

it('batch-adds drink sizes for the active event', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $catalog = DrinkCatalog::factory()->create();
    $size1 = DrinkCatalogSize::create(['catalog_id' => $catalog->id, 'amount_liter' => 0.3, 'is_default' => false, 'sort_order' => 0]);
    $size2 = DrinkCatalogSize::create(['catalog_id' => $catalog->id, 'amount_liter' => 0.5, 'is_default' => true, 'sort_order' => 1]);

    $this->post(route('drinks.batch'), ['add' => [$size1->id, $size2->id]])
        ->assertRedirect();

    expect(Drink::where('event_id', $event->id)->count())->toBe(2);
});

it('batch-removes drink sizes from the active event', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $catalog = DrinkCatalog::factory()->create();
    $size = DrinkCatalogSize::create(['catalog_id' => $catalog->id, 'amount_liter' => 0.5, 'is_default' => true, 'sort_order' => 0]);
    $drink = Drink::create(['event_id' => $event->id, 'drink_catalog_id' => $catalog->id, 'size_id' => $size->id]);

    $this->post(route('drinks.batch'), ['remove' => [$drink->id]])
        ->assertRedirect();

    expect(Drink::find($drink->id))->toBeNull();
});

it('removes a single drink', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $catalog = DrinkCatalog::factory()->create();
    $size = DrinkCatalogSize::create(['catalog_id' => $catalog->id, 'amount_liter' => 0.5, 'is_default' => true, 'sort_order' => 0]);
    $drink = Drink::create(['event_id' => $event->id, 'drink_catalog_id' => $catalog->id, 'size_id' => $size->id]);

    $this->delete(route('drinks.destroy', $drink))->assertRedirect();

    expect(Drink::find($drink->id))->toBeNull();
});

it('only shows drinks for the active event', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $otherEvent = Event::factory()->create();

    $catalog = DrinkCatalog::factory()->create();
    $size = DrinkCatalogSize::create(['catalog_id' => $catalog->id, 'amount_liter' => 0.5, 'is_default' => true, 'sort_order' => 0]);
    Drink::create(['event_id' => $event->id, 'drink_catalog_id' => $catalog->id, 'size_id' => $size->id]);
    Drink::create(['event_id' => $otherEvent->id, 'drink_catalog_id' => $catalog->id, 'size_id' => $size->id]);

    $response = $this->get(route('drinks.index'))->assertOk();
    // event_drinks Prop nur mit einer Zeile (dem aktiven Event)
    expect($response->getOriginalContent()->getData()['page']['props']['event_drinks'])->toHaveCount(1);
});
