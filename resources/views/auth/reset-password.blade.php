@extends('layouts.app')

@section('title', 'Nouveau mot de passe')

@section('content')
    <div class="mx-auto max-w-sm">
        <h1 class="mb-6 text-2xl font-semibold">Nouveau mot de passe</h1>

        <form method="POST" action="{{ route('password.update') }}" class="card flex flex-col gap-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="form-label">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username" class="form-input">
                @error('email')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="form-label">Nouveau mot de passe (8 caractères minimum)</label>
                <input id="password" name="password" type="password" required autofocus autocomplete="new-password" class="form-input">
                @error('password')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="form-label">Confirmation</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="form-input">
            </div>

            <button type="submit" class="btn-primary">Enregistrer</button>
        </form>
    </div>
@endsection
