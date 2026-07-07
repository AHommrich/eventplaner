<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialLoginController extends Controller
{
    public function redirect()
    {
        // Stateful (session-based) is correct here. Only switch to ->stateless()
        // if the login flow ever crosses instances that do not share session
        // storage (multi-node deployment, external SPA on a different origin).
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')->user();

        $googleId = (string) $googleUser->getId();
        $email = $googleUser->getEmail();   // Google usually returns a verified email
        $name = $googleUser->getName() ?: 'Google User';

        // If you want to link multiple providers later, build a social_accounts table.
        // For the start, "match by email or create new" is enough:

        DB::beginTransaction();

        $user = $email ? User::where('email', $email)->first() : null;

        if (! $user) {
            $user = new User([
                'name' => $name,
                'email' => $email ?? 'google-'.Str::uuid().'@example.local',
                'password' => bcrypt(Str::random(40)),
                // Choosing "Sign in with Google" counts as accepting the privacy
                // policy presented next to that button — record the timestamp so
                // the consent paper trail is consistent with the email-signup path.
                'privacy_accepted_at' => now(),
            ]);
            // email_verified_at is not in $fillable — set it directly so the
            // Google-verified status is not lost during mass assignment.
            $user->email_verified_at = $email ? now() : null;
            $user->save();
        } elseif (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // (Optional) you could store googleId/avatar/token here if you use a SocialAccount model.

        DB::commit();

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}
