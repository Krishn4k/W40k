{{--
    Page de secours autonome (sans layout, assets, menu ni session) : elle doit fonctionner même quand le reste
    de l'application ne peut pas s'afficher. Attend $token, $pendingMigrations, $output et facultativement $failed.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Maintenance de secours · {{ config('app.name') }}</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; line-height: 1.5; background: #fafafa; color: #18181b; }
        main { max-width: 42rem; margin: 3rem auto; padding: 0 1rem; }
        h1 { font-size: 1.5rem; margin: 0 0 .5rem; }
        .box { background: #fff; border: 1px solid #e4e4e7; border-radius: .5rem; padding: 1rem 1.25rem; margin: 1rem 0; }
        code, pre { font-family: ui-monospace, monospace; font-size: .8125rem; }
        pre { white-space: pre-wrap; background: #f4f4f5; padding: .75rem; border-radius: .375rem; }
        button { background: #0f766e; color: #fff; border: 0; border-radius: .375rem; padding: .5rem 1rem; font-size: .9375rem; cursor: pointer; }
        button:disabled { opacity: .5; cursor: not-allowed; }
        .warning { color: #92400e; }
        .error { color: #b91c1c; font-weight: 600; }
        .ok { color: #047857; font-weight: 600; }
    </style>
</head>
<body>
    <main>
        <h1>Maintenance de secours</h1>
        <p>Application des migrations quand l'application ne s'affiche plus. Ensuite : <strong>Administration → Maintenance → Optimiser</strong>.</p>

        @if ($output !== null)
            <div class="box">
                <p class="{{ ($failed ?? false) ? 'error' : 'ok' }}">{{ ($failed ?? false) ? 'Les migrations ont échoué.' : 'Migrations appliquées.' }}</p>
                <pre>{{ $output }}</pre>
            </div>
        @endif

        <div class="box">
            @if ($pendingMigrations === [])
                <p>Aucune migration en attente : la base est à jour.</p>
            @else
                <p>{{ count($pendingMigrations) }} migration(s) en attente :</p>
                <ul>
                    @foreach ($pendingMigrations as $migration)
                        <li><code>{{ $migration }}</code></li>
                    @endforeach
                </ul>
                <p class="warning">Avant d'appliquer : exporter la base dans phpMyAdmin (sauvegarde).</p>
            @endif

            <form method="POST" action="{{ route('maintenance.emergency.migrate', $token) }}" onsubmit="return confirm('Appliquer les migrations en attente ?')">
                <button type="submit" @disabled($pendingMigrations === [])>Appliquer les migrations</button>
            </form>
        </div>
    </main>
</body>
</html>
