{{--
    Formulaire d'ajout ou de modification d'une unité de la liste.
    Attend $action, $entryOptions, et facultativement $entry (modification).
--}}
@php
    $entry ??= null;
    $formId = (string) ($entry?->id ?? 'new');
    // Anciennes valeurs et erreurs ne concernent que le formulaire qui a été soumis.
    $isSubmittedForm = old('_form') === $formId;
    $value = fn (string $field, mixed $default) => $isSubmittedForm ? old($field, $default) : $default;
@endphp
<form method="POST" action="{{ $action }}"
      x-data="armyListEntryForm(@js($entryOptions), @js([
          'unitId' => $value('unit_id', $entry?->unit_id),
          'modelCount' => $value('model_count', $entry?->model_count),
          'enhancementId' => $value('enhancement_id', $entry?->enhancement_id),
          'lotId' => $value('collection_lot_id', $entry?->collection_lot_id),
          'entryId' => $entry?->id,
          'attachedToEntryId' => $value('attached_to_entry_id', $entry?->attached_to_entry_id),
          'wargear' => (object) $value('wargear', $entry?->wargear->pluck('quantity', 'name')->all() ?? []),
          'isWarlord' => (bool) $value('is_warlord', $entry?->is_warlord),
      ]))"
      class="flex flex-col gap-4">
    @csrf
    <input type="hidden" name="_form" value="{{ $formId }}">
    @if ($entry)
        @method('PUT')
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="lg:col-span-2">
            <label class="form-label" for="unit_id_{{ $formId }}">Unité</label>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <input type="search" x-model="unitQuery" placeholder="Rechercher…" aria-label="Rechercher une unité" class="form-input mt-0 min-w-0 flex-1">
                <label class="flex items-center gap-1.5 text-xs whitespace-nowrap">
                    <input type="checkbox" x-model="showLegends" class="rounded border-zinc-300 dark:border-zinc-700">
                    Légendes
                </label>
            </div>
            <select id="unit_id_{{ $formId }}" name="unit_id" required x-model="unitId" @change="unitChanged()" class="form-input mt-1">
                <option value="">—</option>
                <template x-for="group in unitGroups" :key="group.name">
                    <optgroup :label="group.name">
                        <template x-for="unit in group.options" :key="unit.id">
                            <option :value="unit.id" :selected="String(unit.id) === String(unitId)" x-text="unit.name"></option>
                        </template>
                    </optgroup>
                </template>
            </select>
        </div>

        <div>
            <label class="form-label" for="model_count_{{ $formId }}">Taille d'escouade</label>
            <template x-if="squadSizes.length > 0">
                <select id="model_count_{{ $formId }}" name="model_count" x-model.number="modelCount" class="form-input">
                    <template x-for="size in squadSizes" :key="size.modelCount">
                        <option :value="size.modelCount" :selected="size.modelCount === Number(modelCount)" x-text="`${size.modelCount} fig. — ${size.points} pts` + (size.laterPoints ? ` (exemplaires suivants : ${size.laterPoints} pts)` : '')"></option>
                    </template>
                </select>
            </template>
            <template x-if="squadSizes.length === 0">
                <input type="number" name="model_count" min="1" max="100" x-model.number="modelCount" class="form-input">
            </template>
        </div>

        <div>
            <label class="form-label" for="lot_{{ $formId }}">Lot de la collection</label>
            <select id="lot_{{ $formId }}" name="collection_lot_id" x-model="lotId" class="form-input">
                <option value="">Aucun</option>
                <template x-for="lot in lots" :key="lot.id">
                    <option :value="lot.id" :selected="lot.id === Number(lotId)" x-text="`${lot.name} (${lot.ready}/${lot.quantity} prêtes)`"></option>
                </template>
            </select>
        </div>
    </div>

    <div class="flex flex-wrap items-end gap-4">
        <div x-show="enhancements.length > 0" class="min-w-64">
            <label class="form-label" for="enhancement_{{ $formId }}">Optimisation</label>
            <select id="enhancement_{{ $formId }}" name="enhancement_id" x-model="enhancementId" class="form-input">
                <option value="">Aucune</option>
                <template x-for="enhancement in enhancements" :key="enhancement.id">
                    <option :value="enhancement.id" :selected="enhancement.id === Number(enhancementId)"
                            x-text="`${enhancement.name}${enhancement.isUpgrade ? ' [Upgrade]' : ''} (+${enhancement.points} pts) — ${enhancement.detachment}`"></option>
                </template>
            </select>
        </div>

        <div x-show="bodyguards.length > 0" class="min-w-64">
            <label class="form-label" for="attached_{{ $formId }}">Rattachée à</label>
            <select id="attached_{{ $formId }}" name="attached_to_entry_id" x-model="attachedToEntryId" class="form-input">
                <option value="" x-text="unit?.isSupport ? 'À rattacher (obligatoire pour une unité Support)' : 'Aucune (unité indépendante)'"></option>
                <template x-for="bodyguard in bodyguards" :key="bodyguard.id">
                    <option :value="bodyguard.id" :selected="bodyguard.id === Number(attachedToEntryId)" x-text="bodyguard.name"></option>
                </template>
            </select>
        </div>

        <template x-for="item in wargearOptions" :key="item.name">
            <div class="w-40">
                <label class="form-label" :for="`wargear_{{ $formId }}_${item.name}`" x-text="`${item.name} (+${item.points} pts)`"></label>
                <input type="number" min="0" max="20" :id="`wargear_{{ $formId }}_${item.name}`" :name="`wargear[${item.name}]`"
                       x-model.number="wargear[item.name]" placeholder="0" class="form-input">
            </div>
        </template>

        <input type="hidden" name="is_warlord" value="0">
        <label class="flex items-center gap-2 pb-2 text-sm">
            <input type="checkbox" name="is_warlord" value="1" x-model="isWarlord" class="rounded border-zinc-300 dark:border-zinc-700">
            Seigneur de guerre
        </label>

        <button type="submit" class="btn-primary">{{ $entry ? 'Enregistrer' : 'Ajouter à la liste' }}</button>
    </div>

    @if ($isSubmittedForm && $errors->hasAny(['unit_id', 'model_count', 'enhancement_id', 'attached_to_entry_id', 'wargear', 'collection_lot_id', 'is_warlord']))
        <div class="form-error">
            @foreach (['unit_id', 'model_count', 'enhancement_id', 'attached_to_entry_id', 'wargear', 'collection_lot_id', 'is_warlord'] as $field)
                @foreach ($errors->get($field) as $message)
                    <p>{{ $message }}</p>
                @endforeach
            @endforeach
        </div>
    @endif
</form>
