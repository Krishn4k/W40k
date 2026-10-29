<#
.SYNOPSIS
    Prépare le script SQL de première installation de W40k Collection sur le serveur (deploy/dist/install.sql.gz).

.DESCRIPTION
    Sur ce poste, dans une base temporaire (la base de développement n'est jamais modifiée) :
      1. crée la base temporaire et y applique les migrations avec le préfixe de tables du serveur (w40k_) ;
      2. y copie le référentiel de la base de développement (reference:export-sql puis reference:import-sql) ;
      3. y crée ton compte administrateur : c'est toi qui saisis le mot de passe, seul son hachage est exporté ;
      4. exporte le tout (mysqldump) dans deploy/dist/install.sql.gz, puis supprime la base temporaire.

    Le fichier s'importe une seule fois, dans phpMyAdmin, sur la base du serveur (partagée avec WordPress : seules
    des tables w40k_ sont créées). Les mises à jour suivantes passent par la page Maintenance.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File deploy\install-database.ps1
#>
param(
    [string] $Php = 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe',
    [string] $MySqlBin = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin',
    [string] $MySqlUser = 'root',
    [string] $Database = 'w40k_install',
    [string] $Prefix = 'w40k_',
    [string] $Output = (Join-Path $PSScriptRoot 'dist\install.sql.gz'),
    [switch] $SkipAdmin
)

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$mysql = Join-Path $MySqlBin 'mysql.exe'
$mysqldump = Join-Path $MySqlBin 'mysqldump.exe'

function Step([string] $message) {
    Write-Host "==> $message" -ForegroundColor Cyan
}

function Invoke-Native([string] $description, [scriptblock] $command) {
    & $command
    if ($LASTEXITCODE -ne 0) {
        throw "$description a échoué (code $LASTEXITCODE)."
    }
}

# Commandes artisan exécutées sur la base temporaire : les variables d'environnement priment sur le .env.
function Invoke-OnInstallDatabase([string] $description, [scriptblock] $command) {
    $env:DB_DATABASE = $Database
    $env:DB_PREFIX = $Prefix
    try {
        Invoke-Native $description $command
    } finally {
        Remove-Item Env:DB_DATABASE, Env:DB_PREFIX -ErrorAction SilentlyContinue
    }
}

foreach ($tool in @($Php, $mysql, $mysqldump)) {
    if (-not (Test-Path $tool)) {
        throw "Introuvable : $tool. Indiquer le bon chemin avec -Php ou -MySqlBin."
    }
}
if ($Database -notmatch '^[A-Za-z0-9_]+$' -or $Database -eq 'w40k') {
    throw "Nom de base temporaire refusé : $Database."
}

Push-Location $root
try {
    Step "Base temporaire $Database"
    Invoke-Native 'Création de la base' { & $mysql -u $MySqlUser -e "DROP DATABASE IF EXISTS $Database; CREATE DATABASE $Database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" }

    try {
        Step "Migrations (tables préfixées $Prefix)"
        Invoke-OnInstallDatabase 'migrate' { & $Php artisan migrate --force --no-interaction }

        Step 'Référentiel de la base de développement'
        $reference = Join-Path ([IO.Path]::GetTempPath()) "w40k-reference-$PID.sql.gz"
        Invoke-Native 'reference:export-sql' { & $Php artisan reference:export-sql "--prefix=$Prefix" "--output=$reference" }
        try {
            Invoke-OnInstallDatabase 'reference:import-sql' { & $Php artisan reference:import-sql $reference --force }
        } finally {
            Remove-Item $reference -ErrorAction SilentlyContinue
        }

        if (-not $SkipAdmin) {
            Step 'Ton compte administrateur (mot de passe saisi par toi, jamais affiché)'
            Invoke-OnInstallDatabase 'app:create-user' { & $Php artisan app:create-user --admin }
        }

        Step "Export vers $Output"
        New-Item -ItemType Directory -Force -Path (Split-Path $Output -Parent) | Out-Null
        $dump = [IO.Path]::ChangeExtension($Output, $null).TrimEnd('.')
        Invoke-Native 'mysqldump' {
            & $mysqldump -u $MySqlUser --no-tablespaces --single-transaction --skip-lock-tables --set-gtid-purged=OFF `
                --default-character-set=utf8mb4 "--result-file=$dump" $Database
        }

        $source = [IO.File]::OpenRead($dump)
        $gzip = [IO.Compression.GZipStream]::new([IO.File]::Create($Output), [IO.Compression.CompressionLevel]::Optimal)
        try {
            $source.CopyTo($gzip)
        } finally {
            $gzip.Dispose()
            $source.Dispose()
        }
        Remove-Item $dump
    } finally {
        Step "Suppression de la base temporaire $Database"
        & $mysql -u $MySqlUser -e "DROP DATABASE IF EXISTS $Database;"
    }

    Write-Host ''
    Write-Host "Script d'installation prêt : $Output ($([math]::Round((Get-Item $Output).Length / 1KB)) Ko)." -ForegroundColor Green
    Write-Host 'À importer une seule fois dans phpMyAdmin, sur la base du serveur (onglet Importer).'
    if ($SkipAdmin) {
        Write-Host 'Attention : aucun compte administrateur (option -SkipAdmin).' -ForegroundColor Yellow
    }
} finally {
    Pop-Location
}
