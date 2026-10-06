<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'min:7', 'max:40'],
            'document_number' => ['nullable', 'string', 'min:8', 'max:40'],
            'password' => ['required', 'confirmed', Rules\Password::min(10)->letters()->numbers()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'document_number' => $request->document_number,
            'password' => Hash::make($request->password),
        ]);

        Role::firstOrCreate(['code' => 'customer'], ['name' => 'Cliente / pasajero', 'description' => 'Compra pasajes y consulta únicamente sus propios boletos y códigos QR.'])->users()->attach($user->id);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('travels.index', absolute: false));
    }
}
