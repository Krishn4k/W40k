<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <header x-data="{ open: false }" class="border-b-2 border-accent-600 bg-white dark:bg-zinc-900">
        <nav class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-y-2 px-4 py-3">
            <a href="{{ route('home') }}" class="font-display text-lg font-semibold tracking-wider uppercase">{{ config('app.name') }}</a>

            @auth
                {{-- Bouton menu mobile --}}
                <button type="button"
                        class="rounded-md px-2 py-1 text-xl hover:bg-zinc-100 sm:hidden dark:hover:bg-zinc-800"
                        @click="open = ! open"
                        :aria-expanded="open.toString()"
                        aria-controls="main-menu"
                        aria-label="Menu">☰</button>

                {{-- Liens : visibles en desktop, dépliés par Alpine en mobile --}}
                <ul id="main-menu"
                    :class="{ 'hidden': ! open, 'flex': open }"
                    class="hidden w-full flex-col gap-1 sm:flex sm:w-auto sm:flex-row sm:items-center sm:gap-6">
                    @foreach ([
                        ['Tableau de bord', 'home', ['home']],
                        ['Collection', 'collection.index', ['collection.*']],
                        ["Listes d'armée", 'army-lists.index', ['army-lists.*']],
                        ['Parties', 'games.index', ['games.*']],
                        ['Référentiel', 'factions.index', ['factions.*', 'units.*', 'detachments.*', 'point-sets.*']],
                    ] as [$label, $routeName, $activePatterns])
                        <li>
                            <a href="{{ route($routeName) }}"
                               @class([
                                   'block py-1 hover:text-accent-700 dark:hover:text-accent-400',
                                   'font-semibold text-accent-700 dark:text-accent-400' => request()->routeIs(...$activePatterns),
                               ])>{{ $label }}</a>
                        </li>
                    @endforeach
                    @can('admin')
                        <li>
                            <a href="{{ route('translations.overview') }}"
                               @class([
                                   'block py-1 hover:text-accent-700 dark:hover:text-accent-400',
                                   'font-semibold text-accent-700 dark:text-accent-400' => request()->routeIs('translations.*'),
                               ])>Traductions</a>
                        </li>
                        <li>
                            <a href="{{ route('admin.users.index') }}"
                               @class([
                                   'block py-1 hover:text-accent-700 dark:hover:text-accent-400',
                                   'font-semibold text-accent-700 dark:text-accent-400' => request()->routeIs('admin.*'),
                               ])>Administration</a>
                        </li>
                    @endcan
                    <li class="flex items-center gap-3 border-t border-zinc-200 pt-2 sm:border-0 sm:pt-0 dark:border-zinc-800">
                        <a href="{{ route('settings.edit') }}" title="Paramètres" class="text-sm text-zinc-600 hover:text-accent-700 dark:text-zinc-400 dark:hover:text-accent-400">{{ auth()->user()->name }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-sm text-zinc-600 hover:text-accent-700 dark:text-zinc-400 dark:hover:text-accent-400">Déconnexion</button>
                        </form>
                    </li>
                </ul>
            @endauth
        </nav>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8">
        @if (session('status'))
            <div role="status" class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        {{-- Lien à usage unique (invitation, réinitialisation) affiché une seule fois à l'administrateur. --}}
        @if (session('generated_link'))
            <div x-data="{ copied: false }" class="mb-6 flex flex-wrap items-center gap-2">
                <input type="text" readonly value="{{ session('generated_link') }}" aria-label="Lien généré" class="form-input mt-0 min-w-0 flex-1 font-mono text-xs" @focus="$el.select()">
                <button type="button" class="btn-secondary" @click="navigator.clipboard.writeText(@js(session('generated_link'))); copied = true" x-text="copied ? 'Copié' : 'Copier'">Copier</button>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
