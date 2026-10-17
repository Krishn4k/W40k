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
                class="hidden w-full flex-col gap-1 sm:flex sm:w-auto sm:flex-row sm:gap-6">
                @foreach (['Tableau de bord', 'Collection', "Listes d'armée"] as $label)
                    <li>
                        <span aria-disabled="true"
                              class="block cursor-not-allowed py-1 text-zinc-400 dark:text-zinc-500"
                              title="Bientôt disponible">{{ $label }}</span>
                    </li>
                @endforeach
            </ul>
        </nav>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8">
        @yield('content')
    </main>
</body>
</html>
