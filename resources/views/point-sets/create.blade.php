@extends('layouts.app')

@section('title', 'Nouvelle version des points')

@section('content')
    <p class="text-sm"><a href="{{ route('point-sets.index') }}" class="link">Versions des points</a></p>
    <h1 class="mb-6 text-2xl font-semibold">Nouvelle version des points</h1>

    <form method="POST" action="{{ route('point-sets.store') }}" class="card flex max-w-2xl flex-col gap-4">
        @include('point-sets._form')

        <div>
            <label for="copy_from_id" class="form-label">Partir des coûts de</label>
            <select id="copy_from_id" name="copy_from_id" class="form-input">
                <option value="">Aucune (version vide)</option>
                @foreach ($copyOptions as $id => $name)
                    <option value="{{ $id }}" @selected((string) old('copy_from_id', $defaultCopyFromId) === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
            <p class="form-help">Copie tous les coûts d'unités et d'optimisations ; il ne reste qu'à modifier ceux qui changent.</p>
            @error('copy_from_id')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Créer</button>
            <a href="{{ route('point-sets.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
@endsection
