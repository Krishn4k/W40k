@extends('layouts.app')

@section('title', 'Inscription')

@section('content')
    <div class="mx-auto max-w-sm">
        <h1 class="mb-2 text-2xl font-semibold">Inscription</h1>
        <p class="mb-6 text-sm text-zinc-600 dark:text-zinc-400">Ton compte sera actif dès qu'un administrateur l'aura validé.</p>

        <form method="POST" action="{{ route('register.store') }}" class="card flex flex-col gap-4">
            @csrf

            <div>
                <label for="name" class="form-label">Nom affiché</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus maxlength="255" autocomplete="name" class="form-input">
                @error('name')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="form-label">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="username" class="form-input">
                @error('email')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="form-label">Mot de passe (8 caractères minimum)</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" class="form-input">
                @error('password')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="form-label">Confirmation du mot de passe</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="form-input">
            </div>

            <button type="submit" class="btn-primary">M'inscrire</button>
        </form>

        <p class="mt-4 text-center text-sm text-zinc-600 dark:text-zinc-400">
            Déjà un compte ? <a href="{{ route('login') }}" class="link">Se connecter</a>
        </p>
    </div>
@endsection
