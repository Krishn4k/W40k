{{-- Sous-navigation de l'administration. --}}
<nav class="mb-6 flex gap-4 border-b border-zinc-200 text-sm dark:border-zinc-800">
    @foreach ([['Utilisateurs', 'admin.users.index'], ['Maintenance', 'admin.maintenance.index']] as [$label, $routeName])
        <a href="{{ route($routeName) }}"
           @class([
               '-mb-px border-b-2 px-1 pb-2',
               'border-accent-600 font-semibold text-accent-700 dark:text-accent-400' => request()->routeIs($routeName),
               'border-transparent text-zinc-600 hover:text-accent-700 dark:text-zinc-400' => ! request()->routeIs($routeName),
           ])>{{ $label }}</a>
    @endforeach
</nav>
