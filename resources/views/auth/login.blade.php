@extends('layouts.app')

@section('title', 'Connexion')

@section('content')
    <div class="mx-auto max-w-sm">
        <h1 class="mb-6 text-2xl font-semibold">Connexion</h1>

        <form method="POST" action="{{ route('login.store') }}" class="card flex flex-col gap-4">
            @csrf

            <div>
                <label for="email" class="form-label">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="form-input">
                @error('email')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="form-label">Mot de passe</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="form-input">
                @error('password')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" class="rounded border-zinc-300 dark:border-zinc-700">
                Rester connecté
            </label>

            <button type="submit" class="btn-primary">Se connecter</button>
        </form>
    </div>
@endsection
