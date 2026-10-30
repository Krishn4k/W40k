<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistrationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Self-registration from the shared link: the account waits for an administrator's approval before it can log in.
 */
class RegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegistrationRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return redirect()->route('login')->with('status', 'Inscription enregistrée : un administrateur va valider ton compte, tu pourras ensuite te connecter.');
    }
}
