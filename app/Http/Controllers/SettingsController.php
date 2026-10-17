<?php

namespace App\Http\Controllers;

use App\Enums\PaintStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Personal settings: display name, playable threshold and password.
 */
class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'playable_min_status' => ['required', Rule::enum(PaintStatus::class)],
        ]);

        $request->user()->update($validated);

        return redirect()->route('settings.edit')->with('status', 'Paramètres enregistrés.');
    }

    /**
     * Change the password, after checking the current one.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $validated['password']]);

        return redirect()->route('settings.edit')->with('status', 'Mot de passe modifié.');
    }
}
