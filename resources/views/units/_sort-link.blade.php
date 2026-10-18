{{--
    En-tête triable d'un tableau d'unités (tri côté serveur, conserve les autres filtres).
    Attend $label, $param (valeur de « tri » : nom, role, points), $column (colonne interne), $sort et $direction.
--}}
@php
    $isActive = ($sort ?? 'name') === $column;
    $nextDirection = $isActive && ($direction ?? 'asc') === 'asc' ? 'desc' : 'asc';
@endphp
<a href="{{ request()->fullUrlWithQuery(['tri' => $param, 'ordre' => $nextDirection, 'page' => null]) }}"
   @class(['inline-flex items-center gap-1 hover:text-accent-700 dark:hover:text-accent-400', 'text-accent-700 dark:text-accent-400' => $isActive])
   aria-label="Trier par {{ mb_strtolower($label) }}">
    {{ $label }}
    <span aria-hidden="true">{{ $isActive ? (($direction ?? 'asc') === 'asc' ? '▲' : '▼') : '↕' }}</span>
</a>
