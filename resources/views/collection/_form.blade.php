@csrf

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        @include('partials.filterable-unit-select', [
            'name' => 'unit_id',
            'id' => 'unit_id',
            'label' => 'Unité',
            'options' => $unitOptions,
            'selected' => old('unit_id', $lot->unit_id),
            'required' => true,
        ])
        @error('unit_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="label" class="form-label">Libellé (facultatif)</label>
        <input id="label" name="label" type="text" maxlength="255" value="{{ old('label', $lot->label) }}" placeholder="Ex. escouade Rouge, boîte de démarrage" class="form-input">
        @error('label')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
</div>

<fieldset x-data="{ counts: @js(collect(App\Enums\PaintStatus::cases())->mapWithKeys(fn ($status) => [$status->value => (int) old('statuses.'.$status->value, $countsByStatus[$status->value] ?? 0)])) }">
    <legend class="form-label">
        Figurines par statut
        <span class="font-normal text-zinc-500">— total : <strong x-text="Object.values(counts).reduce((sum, count) => sum + (parseInt(count) || 0), 0)">{{ array_sum($countsByStatus) }}</strong></span>
    </legend>
    <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach (App\Enums\PaintStatus::cases() as $status)
            <div>
                <label for="status_{{ $status->value }}" class="flex items-center gap-1.5 text-sm">
                    <span class="inline-block size-2.5 rounded-full {{ $status->colorClasses() }}"></span>
                    {{ $status->label() }}
                </label>
                <input id="status_{{ $status->value }}" name="statuses[{{ $status->value }}]" type="number" min="0" max="999"
                       x-model.number="counts['{{ $status->value }}']"
                       value="{{ old('statuses.'.$status->value, $countsByStatus[$status->value] ?? 0) }}" class="form-input">
            </div>
        @endforeach
    </div>
    <p class="form-help">La quantité du lot est la somme des statuts. Une figurine perdue : diminuer le statut concerné.</p>
    @error('statuses')
        <p class="form-error">{{ $message }}</p>
    @enderror
    @foreach ($errors->get('statuses.*') as $messages)
        @foreach ($messages as $message)
            <p class="form-error">{{ $message }}</p>
        @endforeach
    @endforeach
</fieldset>

<details @if (old('counts_as_unit_id', $lot->counts_as_unit_id)) open @endif>
    <summary class="cursor-pointer text-sm font-medium">Proxy / kitbash</summary>
    <div class="mt-2 max-w-md">
        @include('partials.filterable-unit-select', [
            'name' => 'counts_as_unit_id',
            'id' => 'counts_as_unit_id',
            'label' => 'Joué comme',
            'options' => $unitOptions,
            'selected' => old('counts_as_unit_id', $lot->counts_as_unit_id),
            'emptyLabel' => 'Lui-même',
        ])
        <p class="form-help">Pour la jouabilité des listes, ce lot comptera comme l'unité choisie.</p>
        @error('counts_as_unit_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
</details>

<div>
    <label for="notes" class="form-label">Notes</label>
    <textarea id="notes" name="notes" rows="3" maxlength="5000" class="form-input">{{ old('notes', $lot->notes) }}</textarea>
    @error('notes')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
