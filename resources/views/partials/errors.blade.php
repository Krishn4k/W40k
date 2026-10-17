{{-- Erreurs générales (hors champs de formulaire), ex. suppression refusée. --}}
@if ($errors->hasAny($keys))
    <div role="alert" class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
        @foreach ($keys as $key)
            @foreach ($errors->get($key) as $message)
                <p>{{ $message }}</p>
            @endforeach
        @endforeach
    </div>
@endif
