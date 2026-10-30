<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Accounts, roles, password reset links and pending invitations.
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
     * Change an account's role; the last administrator cannot lose the role.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $role = UserRole::from($request->validate(['role' => ['required', Rule::enum(UserRole::class)]])['role']);

        if ($user->isAdmin() && $role !== UserRole::Admin && User::query()->where('role', UserRole::Admin)->count() === 1) {
            return back()->withErrors(['user' => 'Il doit rester au moins un administrateur.']);
        }

        $user->forceFill(['role' => $role])->save();

        return back()->with('status', "{$user->name} : rôle « {$role->label()} ».");
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
