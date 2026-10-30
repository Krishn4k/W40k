<#
.SYNOPSIS
    Prépare le paquet de mise en ligne de W40k Collection pour l'hébergement OVH (offre Perso, sans SSH).

.DESCRIPTION
    Construit deploy/dist/ avec les deux dossiers à envoyer par FTP à la racine de l'hébergement :

      w40k-app/   code de l'application (export Git du commit courant), dépendances de production,
                  dossiers storage/ vides ; ni .env, ni photos, ni données BSData. Hors de portée du web.
      krishnak/   racine de krishnak.fr : page d'accueil « Projets », .htaccess, et w40k/ (partie publique
                  de l'application : assets compilés, polices, point d'entrée deploy/index.php).

    Le script refuse de travailler sur un arbre Git modifié (sauf -AllowDirty) pour que la version en ligne
    corresponde toujours à un commit, inscrit dans w40k-app/VERSION.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File deploy\build.ps1
#>
param(
    [string] $Destination = (Join-Path $PSScriptRoot 'dist'),
    [string] $Php = 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe',
    [string] $Composer = 'C:\laragon\bin\composer\composer.phar',
    [switch] $AllowDirty
)

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent

function Step([string] $message) {
    Write-Host "==> $message" -ForegroundColor Cyan
}

function Invoke-Native([string] $description, [scriptblock] $command) {
    & $command
    if ($LASTEXITCODE -ne 0) {
        throw "$description a échoué (code $LASTEXITCODE)."
    }
}

# Empreinte du contenu d'un dossier (chemins et contenus). installed.php/json en sont exclus : Composer y inscrit
# le commit courant, sans effet sur le chargement des classes.
function Get-DirectoryFingerprint([string] $directory) {
    $prefix = (Resolve-Path $directory).Path.Length
    $lines = Get-ChildItem $directory -Recurse -File |
        Where-Object { $_.Name -notin @('installed.php', 'installed.json') } |
        Sort-Object FullName |
        ForEach-Object { $_.FullName.Substring($prefix) + ' ' + (Get-FileHash $_.FullName -Algorithm SHA256).Hash }
    $sha = [Security.Cryptography.SHA256]::Create()
    return [BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($lines -join "`n"))).Replace('-', '')
}

foreach ($tool in @($Php, $Composer)) {
    if (-not (Test-Path $tool)) {
        throw "Introuvable : $tool. Indiquer le bon chemin avec -Php ou -Composer."
    }
}

Push-Location $root
try {
    Step 'Vérification de l''arbre Git'
    $changes = git status --porcelain
    if ($changes -and -not $AllowDirty) {
        throw "Des modifications ne sont pas commitées :`n$($changes -join "`n")`nCommiter d'abord (ou relancer avec -AllowDirty pour un essai local)."
    }
    $version = (git rev-parse --short HEAD).Trim()
    if ($changes) {
        $version += '-modifie'
    }

    Step "Préparation de $Destination"
    # Le .env de production éventuellement rangé dans dist/w40k-app/ est conservé d'une construction à l'autre.
    $envFile = Join-Path $Destination 'w40k-app\.env'
    $savedEnv = if (Test-Path $envFile) { [IO.File]::ReadAllBytes($envFile) } else { $null }
    # Empreinte du vendor/ du paquet précédent, pour savoir s'il faut le renvoyer.
    $vendorFingerprintFile = Join-Path $Destination 'vendor.sha256'
    $previousVendor = if (Test-Path $vendorFingerprintFile) { (Get-Content $vendorFingerprintFile -Raw).Trim() } else { $null }
    if (Test-Path $Destination) {
        Remove-Item $Destination -Recurse -Force
    }
    $app = Join-Path $Destination 'w40k-app'
    $site = Join-Path $Destination 'krishnak'
    $public = Join-Path $site 'w40k'
    New-Item -ItemType Directory -Force -Path $app, $public, (Join-Path $site 'fonts') | Out-Null
    if ($savedEnv) {
        [IO.File]::WriteAllBytes($envFile, $savedEnv)
        Write-Host '    .env de production conservé.'
    }

    Step "Export du commit $version"
    $archive = Join-Path $Destination 'source.zip'
    Invoke-Native 'git archive' { git archive --format=zip -o $archive HEAD }
    Expand-Archive -Path $archive -DestinationPath $app
    Remove-Item $archive
    foreach ($unused in @('tests', 'deploy', 'public', '.github', '.ai', '.claude', 'phpunit.xml', 'CLAUDE.md', 'boost.json', '.mcp.json')) {
        $path = Join-Path $app $unused
        if (Test-Path $path) {
            Remove-Item $path -Recurse -Force
        }
    }
    Set-Content -Path (Join-Path $app 'VERSION') -Value $version -Encoding ascii -NoNewline

    Step 'Dépendances PHP de production (composer install --no-dev)'
    # Repartir du vendor/ local (mêmes versions, composer.lock commun) : Composer n'a plus qu'à retirer les
    # outils de développement, sans rien télécharger (le PHP de Laragon n'a pas l'extension zip).
    if (-not (Test-Path (Join-Path $root 'vendor\autoload.php'))) {
        throw 'vendor/ absent : lancer d''abord composer install à la racine du projet.'
    }
    Copy-Item (Join-Path $root 'vendor') -Destination (Join-Path $app 'vendor') -Recurse -Force
    Push-Location $app
    try {
        Invoke-Native 'composer install' { & $Php $Composer install --no-dev --optimize-autoloader --no-interaction --no-progress }
    } finally {
        Pop-Location
    }
    # app/ et database/ étant exclus du classmap (composer.json), vendor/ ne change qu'avec les dépendances.
    $vendor = Get-DirectoryFingerprint (Join-Path $app 'vendor')
    Set-Content -Path $vendorFingerprintFile -Value $vendor -Encoding ascii -NoNewline

    Step 'Compilation des assets (npm run build)'
    Invoke-Native 'npm run build' { npm run build }

    Step 'Partie publique : krishnak/w40k'
    Get-ChildItem (Join-Path $root 'public') -Force |
        Where-Object { $_.Name -notin @('hot', 'fonts-manifest.dev.json', 'storage', 'index.php') } |
        Copy-Item -Destination $public -Recurse -Force
    Copy-Item (Join-Path $PSScriptRoot 'index.php') -Destination $public -Force
    # Redirections https et barre finale en tête du .htaccess de Laravel (celles de krishnak/ ne s'appliquent pas ici).
    $htaccess = Join-Path $public '.htaccess'
    $utf8 = [Text.UTF8Encoding]::new($false)
    [IO.File]::WriteAllText($htaccess, [IO.File]::ReadAllText((Join-Path $PSScriptRoot 'w40k.htaccess'), $utf8) + [IO.File]::ReadAllText($htaccess, $utf8), $utf8)

    Step 'Page d''accueil : krishnak/'
    Copy-Item (Join-Path $PSScriptRoot 'krishnak\*') -Destination $site -Recurse -Force
    Copy-Item (Join-Path $PSScriptRoot 'krishnak\.htaccess') -Destination $site -Force
    $oswald = Get-ChildItem (Join-Path $root 'public\build\assets') -Filter 'oswald-600-normal-*.woff2' | Select-Object -First 1
    if (-not $oswald) {
        throw 'Police Oswald 600 introuvable dans public/build/assets.'
    }
    Copy-Item $oswald.FullName -Destination (Join-Path $site 'fonts\oswald-600.woff2') -Force

    $files = (Get-ChildItem $Destination -Recurse -File).Count
    Write-Host ''
    Write-Host "Paquet prêt : $Destination (version $version, $files fichiers)." -ForegroundColor Green
    Write-Host 'À envoyer par FTP à la racine de l''hébergement (à côté de www/) :'
    Write-Host '  dist/w40k-app/  ->  /home/<login>/w40k-app/   (garder le .env et storage/ déjà en ligne)'
    Write-Host '  dist/krishnak/  ->  /home/<login>/krishnak/'
    Write-Host ''
    if ($previousVendor -eq $vendor) {
        Write-Host 'vendor/ inchangé depuis le paquet précédent : masque WinSCP  | .env; storage/; vendor/' -ForegroundColor Green
    } elseif ($previousVendor) {
        Write-Host 'vendor/ MODIFIÉ depuis le paquet précédent : masque WinSCP  | .env; storage/  (vendor/ à renvoyer)' -ForegroundColor Yellow
    } else {
        Write-Host 'Pas de paquet précédent pour comparer vendor/ : masque WinSCP  | .env; storage/  (vendor/ à renvoyer)' -ForegroundColor Yellow
    }
} finally {
    Pop-Location
}
