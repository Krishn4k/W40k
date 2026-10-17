<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Invitation links created by administrators. The link is shown only once, right after its creation.
 */
class InvitationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        [, $token] = Invitation::issue($request->user());

        return redirect()->route('admin.users.index')
            ->with('status', 'Invitation créée : transmets ce lien, il ne sera plus affiché.')
            ->with('generated_link', route('invitations.show', $token));
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        abort_unless($invitation->isPending(), 404);

        $invitation->delete();

        return redirect()->route('admin.users.index')->with('status', 'Invitation révoquée.');
    }
}
