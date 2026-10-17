@csrf

<div>
    <label for="name" class="form-label">Nom de la liste</label>
    <input id="name" name="name" type="text" required maxlength="255" value="{{ old('name', $armyList->name) }}" class="form-input">
    @error('name')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<div x-data="{ factionId: @js((string) old('faction_id', $armyList->faction_id)), detachmentsByFaction: @js($detachmentsByFaction) }"
     class="grid gap-4 sm:grid-cols-2">
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
        <label for="detachment_id" class="form-label">Détachement</label>
        <select id="detachment_id" name="detachment_id" class="form-input">
            <option value="">À choisir plus tard</option>
            <template x-for="detachment in (detachmentsByFaction[factionId] ?? [])" :key="detachment.id">
                <option :value="detachment.id" :selected="detachment.id === {{ (int) old('detachment_id', $armyList->detachment_id) }}" x-text="detachment.name"></option>
            </template>
        </select>
        <p class="form-help">Changer de détachement retire les optimisations de l'ancien.</p>
        @error('detachment_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="game_format_id" class="form-label">Format</label>
        <select id="game_format_id" name="game_format_id" required class="form-input">
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
