<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <header x-data="{ open: false }" class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <nav class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-y-2 px-4 py-3">
            <a href="{{ route('home') }}" class="font-semibold tracking-wide">{{ config('app.name') }}</a>

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
                        'Tableau de bord' => 'home',
                        'Collection' => 'collection.index',
                        "Listes d'armée" => 'army-lists.index',
                        'Référentiel' => 'factions.index',
                    ] as $label => $routeName)
                        <li>
                            @if (Route::has($routeName))
                                <a href="{{ route($routeName) }}"
                                   @class([
                                       'block py-1 hover:text-amber-700 dark:hover:text-amber-400',
                                       'font-semibold text-amber-700 dark:text-amber-400' => request()->routeIs(Str::before($routeName, '.').'*'),
                                   ])>{{ $label }}</a>
                            @else
                                <span aria-disabled="true"
                                      class="block cursor-not-allowed py-1 text-zinc-400 dark:text-zinc-500"
                                      title="Bientôt disponible">{{ $label }}</span>
                            @endif
                        </li>
                    @endforeach
                    <li class="flex items-center gap-3 border-t border-zinc-200 pt-2 sm:border-0 sm:pt-0 dark:border-zinc-800">
                        @if (Route::has('settings.edit'))
                            <a href="{{ route('settings.edit') }}" class="text-sm text-zinc-600 hover:text-amber-700 dark:text-zinc-400 dark:hover:text-amber-400">{{ auth()->user()->name }}</a>
                        @else
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ auth()->user()->name }}</span>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-sm text-zinc-600 hover:text-amber-700 dark:text-zinc-400 dark:hover:text-amber-400">Déconnexion</button>
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

        @yield('content')
    </main>
</body>
</html>
