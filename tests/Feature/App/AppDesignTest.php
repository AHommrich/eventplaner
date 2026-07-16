<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('shows the app design page', function () {
    actingAsOwner();

    $this->get(route('app.design'))->assertOk();
});

it('updates all color palette and role fields', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();

    $this->post(route('app.design.update'), [
        'color_primary' => '#112233',
        'color_secondary' => '#445566',
        'color_tertiary' => '#778899',
        'role_screen_bg' => 'secondary',
        'role_card_bg' => 'tertiary',
        'role_card_text' => 'primary',
    ])->assertRedirect(route('app.design'));

    $fresh = $event->fresh();
    expect($fresh->color_primary)->toBe('#112233');
    expect($fresh->role_screen_bg)->toBe('secondary');
    expect($fresh->role_card_text)->toBe('primary');
});

it('rejects invalid hex colors', function () {
    actingAsOwner();

    $this->post(route('app.design.update'), [
        'color_primary' => 'not-a-hex',
    ])->assertSessionHasErrors(['color_primary']);
});

it('updates the design preset', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();

    $this->post(route('app.design.update'), [
        'design_preset' => 'soft-luxury',
    ])->assertRedirect(route('app.design'));

    expect($event->fresh()->design_preset)->toBe('soft-luxury');
});

it('rejects an unknown design preset', function () {
    actingAsOwner();

    $this->post(route('app.design.update'), [
        'design_preset' => 'bogus',
    ])->assertSessionHasErrors(['design_preset']);
});

it('uploads a cover image to object storage', function () {
    Storage::fake('s3');
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();

    $this->post(route('app.design.update'), [
        'cover' => UploadedFile::fake()->image('cover.jpg', 800, 600),
    ])->assertRedirect();

    $fresh = $event->fresh();
    expect($fresh->cover_image_r2_key)->toStartWith('covers/');
    Storage::disk('s3')->assertExists($fresh->cover_image_r2_key);
});

it('removes a cover only when the design form is saved', function () {
    Storage::fake('s3');
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $key = 'covers/current-cover.jpg';
    Storage::disk('s3')->put($key, 'cover');
    $event->update([
        'cover_image_url' => Storage::disk('s3')->url($key),
        'cover_image_r2_key' => $key,
    ]);

    $this->post(route('app.design.update'), [
        'remove_cover' => true,
    ])->assertRedirect(route('app.design'));

    expect($event->fresh()->cover_image_url)->toBeNull();
    expect($event->fresh()->cover_image_r2_key)->toBeNull();
    Storage::disk('s3')->assertMissing($key);
});

it('keeps the current cover when remove_cover is not submitted', function () {
    Storage::fake('s3');
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $key = 'covers/current-cover.jpg';
    Storage::disk('s3')->put($key, 'cover');
    $event->update([
        'cover_image_url' => Storage::disk('s3')->url($key),
        'cover_image_r2_key' => $key,
    ]);

    $this->post(route('app.design.update'), [
        'color_primary' => '#112233',
    ])->assertRedirect(route('app.design'));

    expect($event->fresh()->cover_image_r2_key)->toBe($key);
    Storage::disk('s3')->assertExists($key);
});
