{{--
    Liste d'unités filtrable : recherche par nom (FR ou EN, sans accents), Légendes masquées par défaut,
    regroupement par faction. L'unité déjà choisie reste toujours affichée.
    Attend $name, $id, $label, $options (UnitSelectOptions), $selected, et facultativement $required et $emptyLabel.
--}}
<div x-data="filterableSelect(@js($options), @js((string) $selected))" class="flex flex-col gap-1">
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <div class="flex flex-wrap items-center gap-2">
        <input type="search" x-model="query" placeholder="Rechercher…" aria-label="Rechercher une unité" class="form-input mt-0 min-w-0 flex-1">
        <label class="flex items-center gap-1.5 text-xs whitespace-nowrap">
            <input type="checkbox" x-model="showLegends" class="rounded border-zinc-300 dark:border-zinc-700">
            Légendes
        </label>
    </div>
    <select id="{{ $id }}" name="{{ $name }}" x-model="selected" @required($required ?? false) class="form-input mt-0">
        <option value="">{{ $emptyLabel ?? '—' }}</option>
        <template x-for="group in groups" :key="group.name">
            <optgroup :label="group.name">
                <template x-for="option in group.options" :key="option.value">
                    <option :value="option.value" :selected="String(option.value) === selected" x-text="option.label"></option>
                </template>
            </optgroup>
        </template>
    </select>
    <p class="form-help" x-text="visibleCount === 0 ? 'Aucune unité ne correspond.' : visibleCount + ' unité(s)'"></p>
</div>
