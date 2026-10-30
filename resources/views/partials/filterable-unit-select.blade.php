{{--
    Champ unique de choix d'une unité : recherche par nom (FR ou EN, sans accents), mots-clés, rôle ou faction,
    résultats groupés par faction, Légendes masquées par défaut.
    Attend $name, $id, $label, $selected et soit $options (UnitSelectOptions), soit $optionsExpression
    (expression Alpine donnant le même format) ; facultativement $required et $emptyLabel.
--}}
<div x-data="unitCombobox({!! $optionsExpression ?? Js::from($options) !!}, @js((string) $selected), @js($required ?? false))"
     @click.outside="close()" class="relative flex flex-col gap-1">
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <div class="flex items-center gap-2">
        <div class="relative min-w-0 flex-1">
            <input id="{{ $id }}" x-ref="input" type="text" autocomplete="off" x-model="query"
                   placeholder="{{ $emptyLabel ?? 'Rechercher une unité…' }}"
                   role="combobox" aria-autocomplete="list" :aria-expanded="open.toString()" aria-controls="{{ $id }}-list"
                   :aria-activedescendant="open && results.length ? '{{ $id }}-option-' + activeIndex : null"
                   @focus="$el.select(); openList()" @input="openList()" @blur="close()"
                   @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)"
                   @keydown.enter="if (open && results[activeIndex]) { $event.preventDefault(); choose(results[activeIndex]) }"
                   @keydown.escape="close()"
                   class="form-input mt-0 pr-8">
            <button type="button" x-show="selected !== ''" @mousedown.prevent @click="clear()" aria-label="Vider le champ"
                    class="absolute inset-y-0 right-0 px-2 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200">×</button>
        </div>
        <label class="flex items-center gap-1.5 text-xs whitespace-nowrap">
            <input type="checkbox" x-model="showLegends" class="rounded border-zinc-300 dark:border-zinc-700">
            Légendes
        </label>
    </div>
    <input type="hidden" name="{{ $name }}" :value="selected">

    <ul id="{{ $id }}-list" role="listbox" x-show="open" x-cloak
        class="absolute top-full right-0 left-0 z-20 mt-1 max-h-72 overflow-y-auto rounded-md border border-zinc-200 bg-white py-1 text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
        <template x-for="(option, index) in results" :key="option.value">
            <li>
                <p x-show="index === 0 || results[index - 1].group !== option.group" x-text="option.group"
                   class="px-3 pt-2 pb-1 text-xs font-semibold tracking-wide text-zinc-500 uppercase"></p>
                <div :id="'{{ $id }}-option-' + index" :data-index="index" role="option" :aria-selected="(index === activeIndex).toString()"
                     @mousedown.prevent @click="choose(option)" @mouseenter="activeIndex = index"
                     :class="index === activeIndex ? 'bg-accent-50 text-accent-800 dark:bg-accent-900/40 dark:text-accent-200' : ''"
                     class="flex cursor-pointer items-center justify-between gap-2 px-3 py-1.5">
                    <span x-text="option.label"></span>
                    <span x-show="String(option.value) === selected" class="text-accent-600">✓</span>
                </div>
            </li>
        </template>
        <li x-show="results.length === 0" class="px-3 py-2 text-zinc-500">Aucune unité ne correspond.</li>
        <li x-show="matches.length > results.length" class="px-3 py-2 text-xs text-zinc-500"
            x-text="`${matches.length} unités : affiner la recherche…`"></li>
    </ul>
</div>
