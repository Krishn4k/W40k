<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Account creation from an invitation link.
 */
class InvitationAcceptanceController extends Controller
{
    public function show(string $token): View
    {
        abort_if(Invitation::findPending($token) === null, 404, 'Cette invitation est invalide, expirée ou déjà utilisée.');

        return view('auth.invitation', ['token' => $token]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::findPending($token);
        abort_if($invitation === null, 404, 'Cette invitation est invalide, expirée ou déjà utilisée.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = DB::transaction(function () use ($invitation, $validated): User {
            $user = User::create($validated);
            $invitation->update(['accepted_at' => now(), 'user_id' => $user->id]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('status', "Bienvenue {$user->name} !");
    }
}
