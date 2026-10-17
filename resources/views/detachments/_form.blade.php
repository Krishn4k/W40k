@csrf

@include('partials.translated-name-fields', ['model' => $detachment])

<fieldset x-data="{ rows: @js(array_values(old('enhancements', $enhancementRows))) }">
    <legend class="form-label">
        Optimisations
        @if ($pointSet)
            <span class="font-normal text-zinc-500">(points : {{ $pointSet->name }})</span>
        @endif
    </legend>

    <div class="mt-2 flex flex-col gap-3">
        <template x-for="(row, index) in rows" :key="index">
            <div class="grid items-end gap-2 sm:grid-cols-[1fr_1fr_8rem_auto]">
                <input type="hidden" :name="`enhancements[${index}][id]`" :value="row.id ?? ''">
                <div>
                    <label class="text-xs text-zinc-500" :for="`enhancement_en_${index}`">Nom (anglais)</label>
                    <input :id="`enhancement_en_${index}`" type="text" maxlength="255" :name="`enhancements[${index}][name][en]`" x-model="row.name.en" class="form-input mt-0">
                </div>
                <div>
                    <label class="text-xs text-zinc-500" :for="`enhancement_fr_${index}`">Nom (français)</label>
                    <input :id="`enhancement_fr_${index}`" type="text" maxlength="255" :name="`enhancements[${index}][name][fr]`" x-model="row.name.fr" class="form-input mt-0">
                </div>
                <div>
                    <label class="text-xs text-zinc-500" :for="`enhancement_points_${index}`">Points</label>
                    <input :id="`enhancement_points_${index}`" type="number" min="0" max="500" :name="`enhancements[${index}][points]`" x-model="row.points" class="form-input mt-0">
                </div>
                <button type="button" class="link pb-2 text-sm" @click="rows.splice(index, 1)">Retirer</button>
            </div>
        </template>
    </div>
    <button type="button" class="btn-secondary mt-3" @click="rows.push({ id: null, name: { en: '', fr: '' }, points: '' })">Ajouter une optimisation</button>
    <p class="form-help">Une optimisation déjà utilisée dans une liste d'armée est conservée même si elle est retirée ici.</p>
    @foreach ($errors->get('enhancements*') as $messages)
        @foreach ((array) $messages as $message)
            <p class="form-error">{{ $message }}</p>
        @endforeach
    @endforeach
</fieldset>
