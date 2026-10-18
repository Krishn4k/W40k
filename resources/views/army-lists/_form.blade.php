@csrf

<div>
    <label for="name" class="form-label">Nom de la liste</label>
    <input id="name" name="name" type="text" required maxlength="255" value="{{ old('name', $armyList->name) }}" class="form-input">
    @error('name')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<div x-data="detachmentPicker({
         factionId: @js((string) old('faction_id', $armyList->faction_id)),
         gameFormatId: @js((string) old('game_format_id', $armyList->game_format_id)),
         selectedIds: @js($selectedDetachmentIds),
         detachmentsByFaction: @js($detachmentsByFaction),
         pointLimits: @js($detachmentPointLimits),
     })"
     class="flex flex-col gap-4">
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="faction_id" class="form-label">Faction</label>
            <select id="faction_id" name="faction_id" required x-model="factionId" class="form-input">
                <option value="">—</option>
                @foreach ($factionOptions as $id => $label)
                    <option value="{{ $id }}" @selected((string) old('faction_id', $armyList->faction_id) === (string) $id)>{{ $label }}</option>
                @endforeach
            </select>
            @error('faction_id')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="game_format_id" class="form-label">Format</label>
            <select id="game_format_id" name="game_format_id" required x-model="gameFormatId" class="form-input">
                @foreach ($gameFormats as $gameFormat)
                    <option value="{{ $gameFormat->id }}" @selected((string) old('game_format_id', $armyList->game_format_id) === (string) $gameFormat->id)>
                        {{ $gameFormat->name }} ({{ $gameFormat->points_limit }} pts)
                    </option>
                @endforeach
            </select>
            @error('game_format_id')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <fieldset>
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <legend class="form-label">Détachements</legend>
            <p class="text-sm" :class="pointsExceeded ? 'text-red-700 dark:text-red-400 font-medium' : 'text-zinc-600 dark:text-zinc-400'"
               x-text="limit ? `${spentPoints} / ${limit.max} DP` + (limit.single_max ? ` (ou un seul détachement jusqu'à ${limit.single_max} DP)` : '') : `${spentPoints} DP`"></p>
        </div>
        <input type="search" x-model="query" placeholder="Rechercher un détachement…" aria-label="Rechercher un détachement" class="form-input mt-1">
        <p x-show="! factionId" class="form-help">Choisis d'abord la faction.</p>
        <ul x-show="factionId" class="mt-2 grid max-h-72 gap-1 overflow-y-auto rounded-md border border-zinc-200 p-2 sm:grid-cols-2 dark:border-zinc-800">
            <template x-for="detachment in visibleDetachments" :key="detachment.id">
                <li>
                    <label class="flex items-start gap-2 rounded px-1.5 py-1 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-900">
                        <input type="checkbox" name="detachment_ids[]" :value="detachment.id" x-model.number="selectedIds" class="mt-0.5 rounded border-zinc-300 dark:border-zinc-700">
                        <span>
                            <span x-text="detachment.name"></span>
                            <span class="text-zinc-500" x-text="detachment.points !== null ? ` · ${detachment.points} DP` : ''"></span>
                            <template x-if="detachment.uniqueTag">
                                <span class="ml-1 rounded bg-zinc-100 px-1 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300" x-text="`Unique : ${detachment.uniqueTag}`"></span>
                            </template>
                        </span>
                    </label>
                </li>
            </template>
        </ul>
        <template x-for="conflict in tagConflicts" :key="conflict">
            <p class="form-error" x-text="`Tag « Unique » en double : ${conflict}`"></p>
        </template>
        <p class="form-help">Retirer un détachement enlève ses optimisations des unités.</p>
        @error('detachment_ids')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </fieldset>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="point_set_id" class="form-label">Version des points</label>
        <select id="point_set_id" name="point_set_id" class="form-input">
            <option value="">Version en vigueur{{ $currentPointSet ? ' ('.$currentPointSet->name.')' : '' }}</option>
            @foreach ($pointSets as $pointSet)
                <option value="{{ $pointSet->id }}" @selected((string) old('point_set_id', $armyList->point_set_id) === (string) $pointSet->id)>Figer sur : {{ $pointSet->name }}</option>
            @endforeach
        </select>
        <p class="form-help">Figer une version garde les points d'une liste jouée à un tournoi passé.</p>
        @error('point_set_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
</div>

<div>
    <label for="notes" class="form-label">Notes</label>
    <textarea id="notes" name="notes" rows="3" maxlength="5000" class="form-input">{{ old('notes', $armyList->notes) }}</textarea>
    @error('notes')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
