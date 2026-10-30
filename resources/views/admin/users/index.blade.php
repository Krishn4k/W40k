@extends('layouts.app')

@section('title', 'Utilisateurs')

@section('content')
    @include('admin._nav')
    @include('partials.errors', ['keys' => ['user']])

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Utilisateurs</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Les administrateurs gèrent le référentiel et les comptes.</p>
        </div>
        <form method="POST" action="{{ route('admin.invitations.store') }}">
            @csrf
            <button type="submit" class="btn-primary">Créer une invitation</button>
        </form>
    </div>

    <section class="card mb-6 flex flex-col gap-2" x-data="{ copied: false }">
        <h2 class="text-lg font-semibold">Lien d'inscription</h2>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">À partager : chacun crée son compte, qui reste en attente jusqu'à ta validation ci-dessous.</p>
        <div class="flex flex-wrap items-center gap-2">
            <input type="text" readonly value="{{ route('register') }}" aria-label="Lien d'inscription" class="form-input mt-0 min-w-0 flex-1 font-mono text-xs" @focus="$event.target.select()">
            <button type="button" class="btn-secondary" @click="navigator.clipboard.writeText(@js(route('register'))); copied = true; setTimeout(() => copied = false, 2000)"
                    x-text="copied ? 'Copié !' : 'Copier'">Copier</button>
        </div>
    </section>

    <section class="mb-8">
        <h2 class="mb-3 text-lg font-semibold">Inscriptions en attente ({{ $pendingUsers->count() }})</h2>
        @if ($pendingUsers->isEmpty())
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Aucune inscription en attente.</p>
        @else
            <ul class="card flex flex-col divide-y divide-zinc-200 p-0 dark:divide-zinc-800">
                @foreach ($pendingUsers as $pendingUser)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div>
                            <span class="font-medium">{{ $pendingUser->name }}</span>
                            <span class="text-zinc-500">— {{ $pendingUser->email }}</span>
                            <span class="block text-xs text-zinc-500">Inscrit le {{ $pendingUser->created_at->format('d/m/Y à H:i') }}</span>
                        </div>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('admin.users.approve', $pendingUser) }}">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="btn-primary">Valider</button>
                            </form>
                            <form method="POST" action="{{ route('admin.users.reject', $pendingUser) }}" onsubmit="return confirm('Refuser et supprimer cette inscription ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger">Refuser</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <h2 class="mb-3 text-lg font-semibold">Comptes</h2>
    <div class="card mb-8 overflow-x-auto p-0">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-200 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                <tr>
                    <th class="px-4 py-3">Nom</th>
                    <th class="px-4 py-3">E-mail</th>
                    <th class="hidden px-4 py-3 text-right md:table-cell">Lots / listes / parties</th>
                    <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @foreach ($users as $user)
                    <tr>
                        <td class="px-4 py-3 font-medium">
                            {{ $user->name }}
                            @unless ($user->role === App\Enums\UserRole::Player)
                                <span class="ml-1 rounded bg-accent-100 px-1.5 py-0.5 text-xs text-accent-800 dark:bg-accent-950 dark:text-accent-300">{{ $user->role->label() }}</span>
                            @endunless
                        </td>
                        <td class="px-4 py-3">{{ $user->email }}</td>
                        <td class="hidden px-4 py-3 text-right md:table-cell">{{ $user->collection_lots_count }} / {{ $user->army_lists_count }} / {{ $user->games_count }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('admin.users.role', $user) }}" class="inline-flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <label for="role_{{ $user->id }}" class="sr-only">Rôle de {{ $user->name }}</label>
                                <select id="role_{{ $user->id }}" name="role" class="form-input mt-0 w-auto py-1">
                                    @foreach (App\Enums\UserRole::cases() as $role)
                                        <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="link">Enregistrer</button>
                            </form>
                            <form method="POST" action="{{ route('admin.users.reset-link', $user) }}" class="ml-3 inline">
                                @csrf
                                <button type="submit" class="link">Lien de mot de passe</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="mb-3 text-lg font-semibold">Invitations en attente</h2>
    @if ($invitations->isEmpty())
        <p class="text-sm text-zinc-600 dark:text-zinc-400">Aucune invitation en attente.</p>
    @else
        <ul class="card flex flex-col divide-y divide-zinc-200 p-0 dark:divide-zinc-800">
            @foreach ($invitations as $invitation)
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                    <span>
                        Créée le {{ $invitation->created_at->format('d/m/Y') }} par {{ $invitation->creator->name }},
                        expire le {{ $invitation->expires_at->format('d/m/Y à H:i') }}
                    </span>
                    <form method="POST" action="{{ route('admin.invitations.destroy', $invitation) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="link">Révoquer</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
