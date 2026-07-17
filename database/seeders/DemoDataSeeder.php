<?php

namespace Database\Seeders;

use App\Models\Drink;
use App\Models\DrinkCatalog;
use App\Models\DrinkLog;
use App\Models\Event;
use App\Models\EventPhotoGame;
use App\Models\FoodSpecial;
use App\Models\Group;
use App\Models\Guest;
use App\Models\InvitationToken;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Models\PhotoGameAssignment;
use App\Models\PhotoGameTaskCatalog;
use App\Models\User;
use App\Services\DrinkScoreService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates a plausible fake wedding "Anna & Ben 2027" so screenshots for
 * README + external demos can be produced without exposing real data.
 *
 * Idempotent: re-running deletes the previous demo user (cascades) before seeding.
 * Cleanup: `User::where('email', 'demo@eveplan.app')->delete()` from tinker.
 *
 * Run: docker exec laravel-app php artisan db:seed --class=DemoDataSeeder
 *
 * Credentials printed at the end. Real event data on the same DB is left untouched.
 */
class DemoDataSeeder extends Seeder
{
    private const EMAIL = 'demo@eveplan.app';

    private const PASSWORD = 'demo-1234';

    public function run(): void
    {
        // Idempotent: wipe any prior demo user and cascade.
        User::where('email', self::EMAIL)->delete();

        // DrinkCatalog + FoodSpecial + PhotoGameTaskCatalog(global) must exist —
        // DrinkCatalogSeeder covers the drinks; the other two are seeded by
        // their own migrations. Verify so the seeder fails loudly if not.
        if (DrinkCatalog::count() === 0 || FoodSpecial::count() === 0 || PhotoGameTaskCatalog::whereNull('event_id')->where('is_base', true)->count() === 0) {
            throw new \RuntimeException('Prerequisite seed data missing. Run: php artisan migrate --seed');
        }

        DB::transaction(function () {
            $user = User::create([
                'name' => 'Demo Veranstalter',
                'email' => self::EMAIL,
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'privacy_accepted_at' => now(),
                'role' => 'user',
                'is_approved' => true,
            ]);

            $event = Event::create([
                'user_id' => $user->id,
                'name' => 'Hochzeit Anna & Ben',
                'date' => '2027-06-19',
                'rsvp_deadline' => '2027-05-15',
                'venue_name' => 'Weingut zum Alten Turm',
                'venue_street' => 'Weinstraße',
                'venue_house_number' => '12',
                'venue_postal_code' => '55232',
                'venue_city' => 'Alzey',
                'venue_country' => 'Deutschland',
                'venue_display_mode' => 'both',
                'venue_lat' => 49.7473,
                'venue_lng' => 8.1156,
                'dresscode' => 'Sommerlich elegant, Damen bitte in Pastell',
                'schedule' => "15:00 – Freie Trauung im Rosengarten\n16:00 – Sekt-Empfang\n18:00 – Dinner\n21:00 – Party bis in die Puppen",
                'cover_image_url' => 'https://picsum.photos/seed/anna-ben-cover/1600/900',
                'color_primary' => '#2d4a3e',   // deep forest green
                'color_secondary' => '#f5efe4', // cream
                'color_tertiary' => '#c9a86b',  // muted gold
                'color_home_text' => '#ffffff',
                'color_home_shadow' => '#000000',
                'home_shadow_opacity' => 0.4,
                'role_screen_bg' => 'secondary',
                'role_card_bg' => 'tertiary',
                'role_card_text' => 'primary',
                'role_card_button' => 'primary',
                'role_card_button_text' => 'secondary',
                'role_tab_tint' => 'primary',
                'role_border' => 'tertiary',
                'role_fab' => 'primary',
                'role_fab_icon' => 'secondary',
                'font_heading' => 'Cormorant Garamond',
                'drink_game_enabled' => true,
                'drink_game_end_time' => '2027-06-20 03:00:00',
                'photo_game_enabled' => true,
                'projector_token' => Str::random(32),
                'projector_name_mode' => 'first',
            ]);

            // Pivot the user into the event so `accessible_events` picks it up
            // even when the session's active event switches.
            $event->users()->syncWithoutDetaching([$user->id]);

            $galleryAlbum = PhotoAlbum::create(['event_id' => $event->id, 'slug' => 'app_gallery', 'name' => 'App-Galerie', 'sort_order' => 0]);
            $presentationAlbum = PhotoAlbum::create(['event_id' => $event->id, 'slug' => 'presentation', 'name' => 'Präsentation', 'sort_order' => 1]);
            $gameAlbum = PhotoAlbum::create(['event_id' => $event->id, 'slug' => 'photo_game', 'name' => 'Fotospiel', 'sort_order' => 2]);

            $event->update(['projector_album_id' => $galleryAlbum->id]);

            $groupBlueprint = [
                ['name' => 'Familie Braut', 'members' => [['Anna', 'Weber', 'accepted'], ['Michael', 'Weber', 'accepted'], ['Susanne', 'Weber', 'accepted']]],
                ['name' => 'Familie Bräutigam', 'members' => [['Ben', 'Fischer', 'accepted'], ['Klaus', 'Fischer', 'accepted'], ['Ingrid', 'Fischer', 'accepted']]],
                ['name' => 'Trauzeugen', 'members' => [['Laura', 'Schmidt', 'accepted'], ['Tobias', 'Meyer', 'accepted']]],
                ['name' => 'Freunde Uni Anna', 'members' => [['Katharina', 'Bauer', 'accepted'], ['Jonas', 'Wagner', 'accepted'], ['Marie', 'Hoffmann', 'accepted_pending'], ['Simon', 'Zimmermann', 'accepted']]],
                ['name' => 'Freunde Uni Ben', 'members' => [['Lukas', 'Schulz', 'accepted'], ['Julia', 'Becker', 'accepted'], ['Felix', 'Wolf', 'declined'], ['Sophie', 'Neumann', 'accepted']]],
                ['name' => 'Arbeitskollegen Anna', 'members' => [['Nina', 'Schwarz', 'accepted'], ['Christian', 'Krüger', 'accepted_pending']]],
                ['name' => 'Arbeitskollegen Ben', 'members' => [['Sebastian', 'König', 'accepted'], ['Ann-Kathrin', 'Braun', 'accepted']]],
                ['name' => 'Familie Onkel Peter', 'members' => [['Peter', 'Weber', 'accepted'], ['Anja', 'Weber', 'accepted'], ['Tim', 'Weber', 'accepted']]],
                ['name' => 'Familie Tante Renate', 'members' => [['Renate', 'Weber', 'declined'], ['Hans', 'Weber', 'declined']]],
                ['name' => 'Nachbarn', 'members' => [['Bettina', 'Lehmann', 'accepted'], ['Andreas', 'Lehmann', 'accepted']]],
                ['name' => 'Kindergartenfreundinnen', 'members' => [['Sarah', 'Vogel', 'accepted'], ['Lena', 'Richter', 'accepted']]],
                ['name' => 'Sportverein', 'members' => [['Markus', 'Werner', 'accepted_pending'], ['Verena', 'Klein', 'accepted']]],
                ['name' => 'Chor', 'members' => [['Bernd', 'Groß', 'accepted'], ['Silke', 'Groß', 'accepted']]],
                ['name' => 'Studienfreunde Ben Ausland', 'members' => [['Emily', 'Turner', 'accepted_pending'], ['Marco', 'Rossi', 'accepted']]],
                ['name' => 'Solo — DJ Team', 'members' => [['Max', 'Berger', 'accepted']]],
            ];

            $allGuests = collect();
            foreach ($groupBlueprint as $blueprint) {
                $group = Group::create(['event_id' => $event->id, 'name' => $blueprint['name']]);

                foreach ($blueprint['members'] as [$firstname, $lastname, $rsvp]) {
                    $guest = Guest::create([
                        'event_id' => $event->id,
                        'group_id' => $group->id,
                        'firstname' => $firstname,
                        'lastname' => $lastname,
                        'rsvp_status' => $rsvp,
                        'rsvp_set_at' => in_array($rsvp, ['accepted', 'declined'], true) ? now()->subDays(rand(3, 45)) : null,
                        'rsvp_set_by_user_id' => in_array($rsvp, ['accepted', 'declined'], true) ? $user->id : null,
                        'app_access' => rand(0, 1) === 1,
                        'drinks_access' => rand(0, 1) === 1,
                    ]);
                    $allGuests->push($guest);
                }

                // One invitation token per group so the QR-code page has content.
                InvitationToken::create(['group_id' => $group->id, 'guest_id' => null, 'token' => Str::random(32)]);
            }

            // Food specials on a handful of guests — pick from the global
            // templates (event_id = null), which are valid for every event.
            $foodIds = FoodSpecial::whereNull('event_id')->inRandomOrder()->limit(5)->pluck('id');
            $allGuests->random(8)->each(function (Guest $g) use ($foodIds) {
                $g->foodSpecials()->syncWithoutDetaching($foodIds->random(rand(1, 2))->all());
            });

            // Drinks catalog — pick a plausible bar (~6 items).
            $drinkPicks = [
                DrinkCatalog::where('type', 'pils')->first(),
                DrinkCatalog::where('type', 'weizen')->first(),
                DrinkCatalog::where('category', 'wine')->inRandomOrder()->first(),
                DrinkCatalog::where('category', 'spirit')->inRandomOrder()->first(),
                DrinkCatalog::where('category', 'water')->inRandomOrder()->first(),
                DrinkCatalog::where('category', 'softdrink')->inRandomOrder()->first(),
            ];

            $drinks = collect();
            foreach (array_filter($drinkPicks) as $catalog) {
                $defaultSize = $catalog->sizes()->where('is_default', true)->first() ?? $catalog->sizes()->first();
                if (! $defaultSize) {
                    continue;
                }
                $drinks->push(Drink::create([
                    'event_id' => $event->id,
                    'drink_catalog_id' => $catalog->id,
                    'size_id' => $defaultSize->id,
                ]));
            }

            // Drink logs for the leaderboard. Give a few guests noticeably more.
            $topDrinkers = $allGuests->where('rsvp_status', 'accepted')->random(6);
            foreach ($topDrinkers as $i => $guest) {
                $entriesForGuest = rand(3, 12);
                for ($n = 0; $n < $entriesForGuest; $n++) {
                    $drink = $drinks->random();
                    $catalog = $drink->catalog;
                    $size = $drink->size;
                    $base = DrinkScoreService::basePoints($catalog, (float) $size->amount_liter);

                    DrinkLog::create([
                        'guest_id' => $guest->id,
                        'drink_id' => $drink->id,
                        'size_id' => $size->id,
                        'amount_liter' => $size->amount_liter,
                        'base_points' => $base,
                        'final_points' => $base,
                        'created_at' => now()->subMinutes(rand(5, 60 * 8)),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Photos — three albums.
            $acceptedGuests = $allGuests->where('rsvp_status', 'accepted');

            // App gallery.
            for ($i = 1; $i <= 15; $i++) {
                $guest = $acceptedGuests->random();
                Photo::create([
                    'event_id' => $event->id,
                    'album_id' => $galleryAlbum->id,
                    'guest_id' => $guest->id,
                    'uploaded_by' => $guest->firstname.' '.$guest->lastname,
                    'url' => 'https://picsum.photos/seed/anna-ben-gallery-'.$i.'/800/600',
                    'r2_key' => null,
                ]);
            }

            // Presentation album with descriptions (shown in the projector overlay).
            $presentationDescriptions = [
                'Anna & Ben — Verlobung, Frühjahr 2026',
                'Der Sommerurlaub in Amalfi, wo alles anfing',
                'Erster gemeinsamer Kater — Findus, 2024',
                'Junggesellinnenabschied in Lissabon',
                'Junggesellenabschied in Berlin',
                'Standesamt, 12. Juni 2027',
            ];
            foreach ($presentationDescriptions as $i => $desc) {
                Photo::create([
                    'event_id' => $event->id,
                    'album_id' => $presentationAlbum->id,
                    'guest_id' => null,
                    'uploaded_by' => 'Demo Veranstalter',
                    'uploader_user_id' => $user->id,
                    'url' => 'https://picsum.photos/seed/anna-ben-slideshow-'.($i + 1).'/1600/900',
                    'r2_key' => null,
                    'description' => $desc,
                ]);
            }

            // Photo game: pick the Hochzeit type-catalog + spin up assignments with photos.
            $hochzeitCatalog = PhotoGameTaskCatalog::whereNull('event_id')->where('event_type', 'hochzeit')->first();
            $game = EventPhotoGame::create([
                'event_id' => $event->id,
                'status' => 'active',
                'catalog_id' => $hochzeitCatalog?->id,
            ]);

            $baseCatalog = PhotoGameTaskCatalog::whereNull('event_id')->where('is_base', true)->first();
            $taskPool = $baseCatalog->tasks()->where('is_active', true)->get();
            if ($hochzeitCatalog) {
                $taskPool = $taskPool->concat($hochzeitCatalog->tasks()->where('is_active', true)->get());
            }
            $sampleCount = min(6, $taskPool->count(), $acceptedGuests->count());
            $tasksForAssignments = $taskPool->random($sampleCount);
            $guestsForAssignments = $acceptedGuests->shuffle()->take($sampleCount)->values();

            foreach ($tasksForAssignments as $i => $task) {
                $guest = $guestsForAssignments[$i];
                $photo = Photo::create([
                    'event_id' => $event->id,
                    'album_id' => $gameAlbum->id,
                    'guest_id' => $guest->id,
                    'uploaded_by' => $guest->firstname.' '.$guest->lastname,
                    'url' => 'https://picsum.photos/seed/anna-ben-game-'.($i + 1).'/800/600',
                    'r2_key' => null,
                ]);
                PhotoGameAssignment::create([
                    'game_id' => $game->id,
                    'guest_id' => $guest->id,
                    'task_id' => $task->id,
                    'override_id' => null,
                    'photo_id' => $photo->id,
                    'submitted_at' => now()->subMinutes(rand(10, 90)),
                ]);
            }
        });

        $this->command?->line('');
        $this->command?->info('Demo-Daten seed abgeschlossen.');
        $this->command?->line('  Login:    '.self::EMAIL);
        $this->command?->line('  Passwort: '.self::PASSWORD);
        $this->command?->line('  Cleanup:  User::where(\'email\', \''.self::EMAIL.'\')->delete()');
    }
}
