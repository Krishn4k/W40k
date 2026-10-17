{{-- Champs de nom traduit : anglais (référence, obligatoire) et français (facultatif). --}}
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="name_en" class="form-label">Nom (anglais, référence)</label>
        <input id="name_en" name="name[en]" type="text" required maxlength="255"
               value="{{ old('name.en', $model->name?->get('en')) }}" class="form-input">
        @error('name.en')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="name_fr" class="form-label">Nom (français)</label>
        <input id="name_fr" name="name[fr]" type="text" maxlength="255"
               value="{{ old('name.fr', $model->name?->has('fr') ? $model->name->get('fr') : '') }}" class="form-input">
        <p class="form-help">Laisser vide pour afficher le nom anglais.</p>
        @error('name.fr')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
</div>
