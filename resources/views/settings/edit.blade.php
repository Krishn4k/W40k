@extends('layouts.app')

@section('title', 'Paramètres')

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Paramètres</h1>

    <form method="POST" action="{{ route('settings.update') }}" class="card flex max-w-xl flex-col gap-4">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="form-label">Nom affiché</label>
            <input id="name" name="name" type="text" required maxlength="255" value="{{ old('name', $user->name) }}" class="form-input">
            @error('name')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="playable_min_status" class="form-label">Statut minimal pour qu'une figurine soit jouable</label>
            <select id="playable_min_status" name="playable_min_status" class="form-input">
                @foreach (App\Enums\PaintStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('playable_min_status', $user->playable_min_status->value) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <p class="form-help">Utilisé pour vérifier qu'une liste d'armée est jouable avec ta collection.</p>
            @error('playable_min_status')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <button type="submit" class="btn-primary">Enregistrer</button>
        </div>
    </form>
@endsection
