@csrf

@include('partials.translated-name-fields', ['model' => $faction])

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="slug" class="form-label">Identifiant (slug)</label>
        <input id="slug" name="slug" type="text" maxlength="255" value="{{ old('slug', $faction->slug) }}" class="form-input">
        <p class="form-help">Généré depuis le nom anglais si vide.</p>
        @error('slug')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="parent_id" class="form-label">Faction mère</label>
        <select id="parent_id" name="parent_id" class="form-input">
            <option value="">Aucune (faction principale)</option>
            @foreach ($parentOptions as $id => $label)
                <option value="{{ $id }}" @selected((string) old('parent_id', $faction->parent_id) === (string) $id)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="form-help">Une sous-faction hérite des unités et détachements de sa faction mère.</p>
        @error('parent_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
</div>
