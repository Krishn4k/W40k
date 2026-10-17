{{-- Galerie et envoi des photos d'un lot (fichiers privés servis par l'application). --}}
<section class="max-w-4xl">
    <h2 class="mb-3 text-lg font-semibold">Photos</h2>

    @if ($lot->photos->isNotEmpty())
        <ul class="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($lot->photos as $photo)
                <li class="card flex flex-col gap-2 p-2">
                    <a href="{{ route('collection.photos.show', [$lot, $photo]) }}" target="_blank" rel="noopener">
                        <img src="{{ route('collection.photos.show', [$lot, $photo]) }}" alt="{{ $photo->caption ?? 'Photo de '.$lot->unit->name }}"
                             loading="lazy" class="aspect-square w-full rounded object-cover">
                    </a>
                    @if ($photo->caption)
                        <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $photo->caption }}</p>
                    @endif
                    <form method="POST" action="{{ route('collection.photos.destroy', [$lot, $photo]) }}" onsubmit="return confirm('Supprimer cette photo ?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="link text-xs">Supprimer</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('collection.photos.store', $lot) }}" enctype="multipart/form-data" class="card flex flex-col gap-4">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="photos" class="form-label">Ajouter des photos</label>
                <input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required class="form-input">
                <p class="form-help">JPG, PNG ou WebP, 10 Mo maximum par photo, 10 photos par envoi.</p>
                @foreach (['photos', 'photos.*'] as $field)
                    @foreach ($errors->get($field) as $messages)
                        @foreach ((array) $messages as $message)
                            <p class="form-error">{{ $message }}</p>
                        @endforeach
                    @endforeach
                @endforeach
            </div>
            <div>
                <label for="caption" class="form-label">Légende (facultatif)</label>
                <input id="caption" name="caption" type="text" maxlength="255" class="form-input">
                @error('caption')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <div>
            <button type="submit" class="btn-secondary">Envoyer</button>
        </div>
    </form>
</section>
