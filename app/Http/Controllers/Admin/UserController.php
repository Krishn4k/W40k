<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Accounts, administrator rights, password reset links and pending invitations.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->withCount(['collectionLots', 'armyLists', 'games'])->orderBy('name')->get(),
            'invitations' => Invitation::query()->pending()->with('creator')->latest()->get(),
        ]);
    }

    /**
     * Grant or remove administrator rights; the last administrator cannot lose them.
     */
    public function toggleAdmin(User $user): RedirectResponse
    {
        if ($user->is_admin && User::query()->where('is_admin', true)->count() === 1) {
            return back()->withErrors(['user' => 'Il doit rester au moins un administrateur.']);
        }

        $user->forceFill(['is_admin' => ! $user->is_admin])->save();

        return back()->with('status', $user->is_admin
            ? "{$user->name} est maintenant administrateur."
            : "{$user->name} n'est plus administrateur.");
    }

    /**
     * Generate a password reset link to hand over to the user (no e-mail is sent).
     */
    public function resetLink(User $user): RedirectResponse
    {
        $token = Password::broker()->createToken($user);
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return back()
            ->with('status', "Lien de réinitialisation pour {$user->name}, valable {$minutes} minutes : transmets-le, il ne sera plus affiché.")
            ->with('generated_link', route('password.reset', ['token' => $token, 'email' => $user->email]));
    }
}
