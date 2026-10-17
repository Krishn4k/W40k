@extends('layouts.app')

@section('title', 'Utilisateurs')

@section('content')
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
                            @if ($user->is_admin)
                                <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">Admin</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $user->email }}</td>
                        <td class="hidden px-4 py-3 text-right md:table-cell">{{ $user->collection_lots_count }} / {{ $user->army_lists_count }} / {{ $user->games_count }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('admin.users.toggle-admin', $user) }}" class="inline">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="link">{{ $user->is_admin ? 'Retirer admin' : 'Nommer admin' }}</button>
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
