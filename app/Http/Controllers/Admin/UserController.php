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
 * Accounts, roles, self-registrations waiting for approval, password reset links and pending invitations.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->whereNotNull('approved_at')->withCount(['collectionLots', 'armyLists', 'games'])->orderBy('name')->get(),
            'pendingUsers' => User::query()->pending()->oldest()->get(),
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
     * Approve a self-registered account: it can log in from now on.
     */
    public function approve(User $user): RedirectResponse
    {
        abort_if($user->isApproved(), 404);

        $user->forceFill(['approved_at' => now()])->save();

        return back()->with('status', "Inscription de {$user->name} validée : le compte peut se connecter.");
    }

    /**
     * Refuse a self-registration: the pending account is deleted. Approved accounts cannot be removed this way.
     */
    public function reject(User $user): RedirectResponse
    {
        abort_if($user->isApproved(), 404);

        $user->delete();

        return back()->with('status', "Inscription de {$user->name} refusée.");
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
