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
    | Munitorum Field Manual (points officiels, 11e édition)
    |--------------------------------------------------------------------------
    |
    | Dépôt des fichiers YAML extraits du MFM : points par taille et par rang
    | d'exemplaire, coûts d'équipement, détachements (DP, tag Unique) et
    | optimisations, unités Leader / Support. Les alias relient un fichier MFM
    | (slug) à une faction du référentiel dont le nom anglais diffère.
    |
    */

    'mfm_repository' => env('MFM_REPOSITORY', 'BSData/wh40k-11e-mfm'),

    'mfm_directory' => storage_path('app/mfm'),

    'mfm_faction_aliases' => [
        'imperial-agents' => 'Agents of the Imperium',
        'tau-empire' => "T'au Empire",
        'titan-legions' => 'Adeptus Titanicus',
        'chaos-titan-legions' => 'Titanicus Traitoris',
    ],

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
