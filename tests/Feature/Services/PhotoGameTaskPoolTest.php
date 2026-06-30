<?php

use App\Models\Event;
use App\Models\EventTaskOverride;
use App\Models\PhotoGameTask;
use App\Models\PhotoGameTaskCatalog;
use App\Services\PhotoGameTaskPool;

function makeBaseCatalog(array $taskDescriptions): PhotoGameTaskCatalog
{
    $catalog = PhotoGameTaskCatalog::create([
        'event_id' => null,
        'name' => 'Base',
        'is_base' => true,
        'is_active' => true,
    ]);

    foreach ($taskDescriptions as $desc) {
        PhotoGameTask::create([
            'catalog_id' => $catalog->id,
            'description' => $desc,
            'is_active' => true,
        ]);
    }

    return $catalog;
}

function makeTypeCatalog(string $type, array $taskDescriptions): PhotoGameTaskCatalog
{
    $catalog = PhotoGameTaskCatalog::create([
        'event_id' => null,
        'name' => ucfirst($type),
        'event_type' => $type,
        'is_base' => false,
        'is_active' => true,
    ]);

    foreach ($taskDescriptions as $desc) {
        PhotoGameTask::create([
            'catalog_id' => $catalog->id,
            'description' => $desc,
            'is_active' => true,
        ]);
    }

    return $catalog;
}

it('contains base tasks when no type catalog is selected', function () {
    makeBaseCatalog(['Base A', 'Base B']);
    $event = Event::factory()->create();

    $pool = (new PhotoGameTaskPool)->build($event->id, null);

    expect($pool)->toHaveCount(2);
    expect($pool->pluck('description')->all())->toEqual(['Base A', 'Base B']);
});

it('contains base + type tasks when catalog_id is set', function () {
    makeBaseCatalog(['Base A', 'Base B']);
    $type = makeTypeCatalog('hochzeit', ['Wedding 1', 'Wedding 2']);
    $event = Event::factory()->create();

    $pool = (new PhotoGameTaskPool)->build($event->id, $type->id);

    expect($pool)->toHaveCount(4);
    expect($pool->pluck('description')->all())->toEqual(['Base A', 'Base B', 'Wedding 1', 'Wedding 2']);
});

it('removes a task when hidden override exists', function () {
    $base = makeBaseCatalog(['Base A', 'Base B']);
    $taskB = $base->tasks()->where('description', 'Base B')->first();
    $event = Event::factory()->create();

    EventTaskOverride::create([
        'event_id' => $event->id,
        'task_id' => $taskB->id,
        'action' => 'hidden',
    ]);

    $pool = (new PhotoGameTaskPool)->build($event->id, null);

    expect($pool)->toHaveCount(1);
    expect($pool->first()['description'])->toBe('Base A');
});

it('replaces description when modified override exists', function () {
    $base = makeBaseCatalog(['Base A', 'Base B']);
    $taskA = $base->tasks()->where('description', 'Base A')->first();
    $event = Event::factory()->create();

    EventTaskOverride::create([
        'event_id' => $event->id,
        'task_id' => $taskA->id,
        'action' => 'modified',
        'custom_text' => 'Base A — angepasst',
    ]);

    $pool = (new PhotoGameTaskPool)->build($event->id, null);

    $modified = $pool->firstWhere('task_id', $taskA->id);
    expect($modified['description'])->toBe('Base A — angepasst');
    expect($modified['description_en'])->toBeNull(); // modified löscht EN-Text bewusst
});

it('adds custom tasks for added overrides', function () {
    makeBaseCatalog(['Base A']);
    $event = Event::factory()->create();

    EventTaskOverride::create([
        'event_id' => $event->id,
        'task_id' => null,
        'action' => 'added',
        'custom_text' => 'Event-eigene Aufgabe',
    ]);

    $pool = (new PhotoGameTaskPool)->build($event->id, null);

    expect($pool)->toHaveCount(2);
    $added = $pool->firstWhere('override_id', '!=', null);
    expect($added['description'])->toBe('Event-eigene Aufgabe');
    expect($added['task_id'])->toBeNull();
});

it('combines all override types correctly', function () {
    $base = makeBaseCatalog(['A', 'B', 'C']);
    $taskA = $base->tasks()->where('description', 'A')->first();
    $taskB = $base->tasks()->where('description', 'B')->first();
    $event = Event::factory()->create();

    EventTaskOverride::create(['event_id' => $event->id, 'task_id' => $taskA->id, 'action' => 'hidden']);
    EventTaskOverride::create(['event_id' => $event->id, 'task_id' => $taskB->id, 'action' => 'modified', 'custom_text' => 'B-mod']);
    EventTaskOverride::create(['event_id' => $event->id, 'task_id' => null, 'action' => 'added', 'custom_text' => 'D-added']);

    $pool = (new PhotoGameTaskPool)->build($event->id, null);

    // A entfernt, B umbenannt, C unverändert, D neu → 3 Items
    expect($pool)->toHaveCount(3);
    expect($pool->pluck('description')->sort()->values()->all())
        ->toEqual(['B-mod', 'C', 'D-added']);
});

it('ignores overrides from other events', function () {
    makeBaseCatalog(['A']);
    $event = Event::factory()->create();
    $otherEvent = Event::factory()->create();

    EventTaskOverride::create([
        'event_id' => $otherEvent->id,
        'task_id' => null,
        'action' => 'added',
        'custom_text' => 'Foreign override',
    ]);

    $pool = (new PhotoGameTaskPool)->build($event->id, null);

    expect($pool)->toHaveCount(1);
    expect($pool->first()['description'])->toBe('A');
});
