@csrf

<div>
    <label for="name" class="form-label">Nom</label>
    <input id="name" name="name" type="text" required maxlength="255" value="{{ old('name', $pointSet->name) }}" placeholder="Ex. MFM 2026 v2" class="form-input">
    @error('name')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="valid_from" class="form-label">En vigueur à partir du</label>
        <input id="valid_from" name="valid_from" type="date" required value="{{ old('valid_from', $pointSet->valid_from?->toDateString()) }}" class="form-input">
        @error('valid_from')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="valid_to" class="form-label">Jusqu'au (facultatif)</label>
        <input id="valid_to" name="valid_to" type="date" value="{{ old('valid_to', $pointSet->valid_to?->toDateString()) }}" class="form-input">
        @error('valid_to')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
</div>
<p class="form-help">La version en vigueur est la plus récente dont la période contient la date du jour.</p>
