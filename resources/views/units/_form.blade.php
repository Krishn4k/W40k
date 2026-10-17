@csrf

@include('partials.translated-name-fields', ['model' => $unit])

<div class="grid gap-4 sm:grid-cols-3">
    <div>
        <label for="faction_id" class="form-label">Faction</label>
        <select id="faction_id" name="faction_id" required class="form-input">
            <option value="">—</option>
            @foreach ($factionOptions as $id => $label)
                <option value="{{ $id }}" @selected((string) old('faction_id', $unit->faction_id) === (string) $id)>{{ $label }}</option>
            @endforeach
        </select>
        @error('faction_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="role" class="form-label">Rôle</label>
        <select id="role" name="role" required class="form-input">
            @foreach (App\Enums\UnitRole::cases() as $role)
                <option value="{{ $role->value }}" @selected(old('role', $unit->role?->value) === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </select>
        @error('role')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
    <div class="flex items-end pb-2">
        <input type="hidden" name="is_epic_hero" value="0">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_epic_hero" value="1" @checked(old('is_epic_hero', $unit->is_epic_hero)) class="rounded border-zinc-300 dark:border-zinc-700">
            Héros épique (un seul exemplaire par armée)
        </label>
    </div>
</div>

<div>
    <label for="keywords" class="form-label">Mots-clés (en anglais, séparés par des virgules)</label>
    <textarea id="keywords" name="keywords" rows="2" maxlength="2000" placeholder="Infantry, Grenades, Imperium" class="form-input">{{ old('keywords', $unit->exists ? $unit->keywords->map(fn ($keyword) => $keyword->name->get('en'))->sort()->join(', ') : '') }}</textarea>
    <p class="form-help">Un mot-clé inconnu est créé ; sa traduction française pourra être ajoutée plus tard.</p>
    @error('keywords')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<fieldset x-data="{ costs: @js(array_values(old('costs', $costs)) ?: [['model_count' => '', 'points' => '']]) }">
    <legend class="form-label">
        Points par taille d'escouade
        @if ($pointSet)
            <span class="font-normal text-zinc-500">({{ $pointSet->name }})</span>
        @endif
    </legend>

    @if (! $pointSet)
        <p class="form-help">Aucune version de points en vigueur : les coûts ne peuvent pas être saisis.</p>
    @else
        <div class="mt-2 flex flex-col gap-2">
            <template x-for="(cost, index) in costs" :key="index">
                <div class="flex flex-wrap items-center gap-2">
                    <input type="number" min="1" max="100" :name="`costs[${index}][model_count]`" x-model="cost.model_count" placeholder="Figurines" aria-label="Nombre de figurines" class="form-input mt-0 w-32">
                    <span class="text-sm text-zinc-500">figurines →</span>
                    <input type="number" min="0" max="5000" :name="`costs[${index}][points]`" x-model="cost.points" placeholder="Points" aria-label="Points" class="form-input mt-0 w-32">
                    <span class="text-sm text-zinc-500">pts</span>
                    <button type="button" class="link text-sm" @click="costs.splice(index, 1)" x-show="costs.length > 1">Retirer</button>
                </div>
            </template>
        </div>
        <button type="button" class="btn-secondary mt-2" @click="costs.push({ model_count: '', points: '' })">Ajouter un palier</button>
        @foreach ($errors->get('costs.*') as $messages)
            @foreach ($messages as $message)
                <p class="form-error">{{ $message }}</p>
            @endforeach
        @endforeach
    @endif
</fieldset>
