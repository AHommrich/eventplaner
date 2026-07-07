<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Group;
use App\Models\Guest;
use App\Models\InvitationToken;
use App\Models\PhotoAlbum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Minimal deterministic fixtures for the Playwright e2e suite.
 *
 * Layout:
 *  - Owner: e2e@eveplan.test / e2e-test-1234
 *  - Event "E2E Testfeier" with fixed projector token
 *  - Anna Solo + solo InvitationToken
 *  - Family group "Müller" with Ben and Clara + family InvitationToken
 *  - Three default albums (app_gallery, presentation, photo_game)
 *
 * Idempotent — re-running deletes the previous owner (cascades) and rebuilds.
 * Runs offline. Never touches user data outside the e2e owner.
 *
 * Run: php artisan db:seed --class=E2eSetupSeeder
 */
class E2eSetupSeeder extends Seeder
{
    public const OWNER_EMAIL = 'e2e@eveplan.test';

    public const OWNER_PASSWORD = 'e2e-test-1234';

    public const SOLO_TOKEN = 'e2esolotoken00000000000000000000';

    public const FAMILY_TOKEN = 'e2efamilytoken000000000000000000';

    public const PROJECTOR_TOKEN = 'e2eprojector0000000000000000000a';

    public function run(): void
    {
        // Idempotent wipe: cascading FK deletes are inconsistent across
        // guest / group / token relations, so we scrub the deterministic
        // tokens explicitly before dropping the owner.
        InvitationToken::whereIn('token', [self::SOLO_TOKEN, self::FAMILY_TOKEN])->delete();
        User::where('email', self::OWNER_EMAIL)->delete();

        DB::transaction(function () {
            $user = User::create([
                'name' => 'E2E Owner',
                'email' => self::OWNER_EMAIL,
                'password' => Hash::make(self::OWNER_PASSWORD),
                'role' => 'user',
                'is_approved' => true,
                'privacy_accepted_at' => now(),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $event = Event::create([
                'user_id' => $user->id,
                'name' => 'E2E Testfeier',
                'date' => '2027-09-01',
                'color_primary' => '#123456',
                'color_secondary' => '#abcdef',
                'color_tertiary' => '#fedcba',
                'projector_token' => self::PROJECTOR_TOKEN,
                'projector_name_mode' => 'first',
            ]);

            $albums = [];
            foreach ([
                ['slug' => PhotoAlbum::APP_GALLERY, 'name' => 'App-Galerie', 'sort_order' => 1],
                ['slug' => PhotoAlbum::PRESENTATION, 'name' => 'Präsentation', 'sort_order' => 2],
                ['slug' => PhotoAlbum::PHOTO_GAME, 'name' => 'Fotospiel', 'sort_order' => 3],
            ] as $album) {
                $albums[$album['slug']] = PhotoAlbum::create(['event_id' => $event->id] + $album);
            }
            $event->update(['projector_album_id' => $albums[PhotoAlbum::APP_GALLERY]->id]);

            $anna = Guest::create([
                'event_id' => $event->id,
                'firstname' => 'Anna',
                'lastname' => 'Solo',
                'app_access' => true,
                'drinks_access' => true,
            ]);
            InvitationToken::create(['guest_id' => $anna->id, 'token' => self::SOLO_TOKEN]);

            $group = Group::create(['event_id' => $event->id, 'name' => 'Müller']);
            foreach (['Ben', 'Clara'] as $firstname) {
                Guest::create([
                    'event_id' => $event->id,
                    'group_id' => $group->id,
                    'firstname' => $firstname,
                    'lastname' => 'Müller',
                    'app_access' => true,
                    'drinks_access' => true,
                ]);
            }
            InvitationToken::create(['group_id' => $group->id, 'token' => self::FAMILY_TOKEN]);
        });
    }
}
