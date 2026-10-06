<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthenticatedSessionController extends Controller
{
    public function redirect(): RedirectResponse
    {
        abort_unless(config('services.google.enabled'), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        abort_unless(config('services.google.enabled'), 404);

        $googleUser = Socialite::driver('google')->user();
        abort_unless($googleUser->getEmail(), 422, 'Google no proporcionó una dirección de correo electrónico.');
        abort_unless((bool) data_get($googleUser->user, 'email_verified'), 422, 'Google debe confirmar el correo electrónico de la cuenta.');

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (! $user) {
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Viajero NavegaYA',
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'password' => Str::password(40),
            ]);
        } elseif (! $user->google_id) {
            $user->update(['google_id' => $googleUser->getId()]);
        }

        Role::firstOrCreate(['code' => 'customer'], ['name' => 'Cliente / pasajero', 'description' => 'Compra pasajes y consulta únicamente sus propios boletos y códigos QR.'])
            ->users()->syncWithoutDetaching([$user->id]);

        $user->claimGuestPurchases();

        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->intended(route('travels.index', absolute: false));
    }
}
