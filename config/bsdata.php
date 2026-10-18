<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Source BSData
    |--------------------------------------------------------------------------
    |
    | Dépôt GitHub des catalogues BattleScribe de Warhammer 40 000 (11e édition, fichiers JSON ;
    | la 10e édition, en XML, reste lisible avec BSDATA_REPOSITORY=BSData/wh40k-10e)
    | et dossier local où ils sont téléchargés avant l'import.
    |
    */

    'repository' => env('BSDATA_REPOSITORY', 'BSData/wh40k-11e'),

    'branch' => env('BSDATA_BRANCH', 'main'),

    'directory' => storage_path('app/bsdata'),

    /*
    |--------------------------------------------------------------------------
    | Factions mères
    |--------------------------------------------------------------------------
    |
    | Noms (attribut « name » du catalogue, pas le nom de fichier) des catalogues
    | dont les catalogues qui importent les unités deviennent des sous-factions
    | (ex. les chapitres Space Marines).
    |
    */

    'parent_catalogues' => [
        'Imperium - Adeptus Astartes - Space Marines',
    ],

];
