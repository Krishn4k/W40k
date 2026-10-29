<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/**
 * Point d'entrée publié dans krishnak/w40k/ chez OVH (copié par deploy/build.ps1).
 * Le reste de l'application (code, .env, photos) est dans w40k-app/, à la racine de l'hébergement,
 * à côté de krishnak/ et hors de portée du web.
 */
$appRoot = __DIR__.'/../../w40k-app';

if (file_exists($maintenance = $appRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appRoot.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appRoot.'/bootstrap/app.php';

// Assets compilés (build/) et polices servis depuis ce dossier, pas depuis w40k-app/public.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
