<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

class SocialLoginController extends Controller
{
    public function redirect()
    {
        // Wenn du Proxies o. ä. hast, nimm ->stateless()
        // return Socialite::driver('google')->stateless()->redirect();
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        // $googleUser = Socialite::driver('google')->stateless()->user();
        $googleUser = Socialite::driver('google')->user();

        $googleId = (string) $googleUser->getId();
        $email    = $googleUser->getEmail();   // Google liefert i. d. R. verifizierte E-Mail
        $name     = $googleUser->getName() ?: 'Google User';

        // Falls du später mehrere Provider verknüpfen willst, bau dir eine social_accounts-Tabelle.
        // Für den Start reicht "per E-Mail matchen oder neu anlegen":

        DB::beginTransaction();

        $user = $email ? User::where('email', $email)->first() : null;

        if (!$user) {
            $user = User::create([
                'name'              => $name,
                'email'             => $email ?? "google-".Str::uuid()."@example.local",
                'password'          => bcrypt(Str::random(40)),
                'email_verified_at' => $email ? now() : null,
            ]);
        } elseif (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // (Optional) hier könntest du googleId/Avatar/Token speichern, wenn du ein SocialAccount-Modell nutzt.

        DB::commit();

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}
