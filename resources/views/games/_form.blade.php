@csrf

<div class="grid gap-4 sm:grid-cols-3">
    <div>
        <label for="played_on" class="form-label">Date</label>
        <input id="played_on" name="played_on" type="date" required max="{{ today()->toDateString() }}" value="{{ old('played_on', $game->played_on?->toDateString()) }}" class="form-input">
        @error('played_on')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="result" class="form-label">Résultat</label>
        <select id="result" name="result" required class="form-input">
            @foreach (App\Enums\GameResult::cases() as $result)
                <option value="{{ $result->value }}" @selected(old('result', $game->result?->value) === $result->value)>{{ $result->label() }}</option>
            @endforeach
        </select>
        @error('result')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
    <div class="grid grid-cols-2 gap-2">
        <div>
            <label for="score_for" class="form-label">Mon score</label>
            <input id="score_for" name="score_for" type="number" min="0" max="999" value="{{ old('score_for', $game->score_for) }}" class="form-input">
        </div>
        <div>
            <label for="score_against" class="form-label">Adversaire</label>
            <input id="score_against" name="score_against" type="number" min="0" max="999" value="{{ old('score_against', $game->score_against) }}" class="form-input">
        </div>
        @foreach (['score_for', 'score_against'] as $field)
            @error($field)
                <p class="form-error col-span-2">{{ $message }}</p>
            @enderror
        @endforeach
    </div>
</div>

<div x-data="gameDispositions({
         armyListId: @js((string) old('army_list_id', $game->army_list_id)),
         forceDisposition: @js((string) old('force_disposition', $game->force_disposition?->value)),
         opponentForceDisposition: @js((string) old('opponent_force_disposition', $game->opponent_force_disposition?->value)),
         mission: @js((string) old('mission', $game->mission)),
         listDispositions: @js($listDispositions),
         primaryMissions: @js($primaryMissions),
     })"
     class="flex flex-col gap-4">
<div class="grid gap-4 sm:grid-cols-3">
    <div>
        <label for="army_list_id" class="form-label">Liste jouée</label>
        <select id="army_list_id" name="army_list_id" x-model="armyListId" @change="listChanged()" class="form-input">
            <option value="">Aucune</option>
            @foreach ($armyLists as $armyList)
                <option value="{{ $armyList->id }}" @selected((string) old('army_list_id', $game->army_list_id) === (string) $armyList->id)>{{ $armyList->name }} ({{ $armyList->faction->fullName() }})</option>
            @endforeach
        </select>
        <p class="form-help">Une copie de la liste (unités, points) est gardée avec la partie, même si la liste change ensuite.</p>
        @if ($game->exists && $game->army_list_id)
            <input type="hidden" name="refresh_snapshot" value="0">
            <label class="mt-2 flex items-center gap-2 text-sm">
                <input type="checkbox" name="refresh_snapshot" value="1" class="rounded border-zinc-300 dark:border-zinc-700">
                Remplacer la copie par la version actuelle de la liste
            </label>
        @endif
        @error('army_list_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
    <div x-show="armyListId === ''">
        <label for="faction_id" class="form-label">Ma faction</label>
        <select id="faction_id" name="faction_id" class="form-input">
            <option value="">—</option>
            @foreach ($factionOptions as $id => $label)
                <option value="{{ $id }}" @selected((string) old('faction_id', $game->faction_id) === (string) $id)>{{ $label }}</option>
            @endforeach
        </select>
        @error('faction_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="force_disposition" class="form-label">Ma disposition de force</label>
        <select id="force_disposition" name="force_disposition" x-model="forceDisposition" @change="dispositionsChanged()" class="form-input">
            <option value="">—</option>
            @foreach (App\Enums\ForceDisposition::cases() as $disposition)
                <option value="{{ $disposition->value }}" @selected(old('force_disposition', $game->force_disposition?->value) === $disposition->value)>{{ $disposition->label() }} ({{ $disposition->englishName() }})</option>
            @endforeach
        </select>
        @error('force_disposition')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div>
        <label for="opponent_name" class="form-label">Adversaire</label>
        <input id="opponent_name" name="opponent_name" type="text" maxlength="255" value="{{ old('opponent_name', $game->opponent_name) }}" class="form-input">
        @error('opponent_name')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="opponent_faction_id" class="form-label">Faction adverse</label>
        <select id="opponent_faction_id" name="opponent_faction_id" class="form-input">
            <option value="">—</option>
            @foreach ($factionOptions as $id => $label)
                <option value="{{ $id }}" @selected((string) old('opponent_faction_id', $game->opponent_faction_id) === (string) $id)>{{ $label }}</option>
            @endforeach
        </select>
        @error('opponent_faction_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="opponent_force_disposition" class="form-label">Disposition adverse</label>
        <select id="opponent_force_disposition" name="opponent_force_disposition" x-model="opponentForceDisposition" @change="dispositionsChanged()" class="form-input">
            <option value="">—</option>
            @foreach (App\Enums\ForceDisposition::cases() as $disposition)
                <option value="{{ $disposition->value }}" @selected(old('opponent_force_disposition', $game->opponent_force_disposition?->value) === $disposition->value)>{{ $disposition->label() }} ({{ $disposition->englishName() }})</option>
            @endforeach
        </select>
        @error('opponent_force_disposition')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="mission" class="form-label">Mission</label>
        <input id="mission" name="mission" type="text" maxlength="255" x-model="mission" class="form-input">
        <p class="form-help" x-show="deducedMission">Mission primaire déduite des deux dispositions (modifiable).</p>
        @error('mission')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
</div>
</div>

<div>
    <label for="notes" class="form-label">Notes</label>
    <textarea id="notes" name="notes" rows="4" maxlength="5000" class="form-input">{{ old('notes', $game->notes) }}</textarea>
    @error('notes')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
