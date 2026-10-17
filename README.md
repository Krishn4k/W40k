# W40k Collection — Gestion d'armées et de collection Warhammer 40 000

## 1. Présentation

Application web personnelle pour gérer une collection de figurines Warhammer 40 000
et construire des listes d'armée à partir de cette collection.

**Objectifs**
- Suivre la collection : unités possédées, quantités, état d'avancement (montage, peinture), photos, notes.
- Visualiser la progression de peinture par faction (en % et en points).
- Construire des listes d'armée avec calcul automatique des points et validation des règles de composition.
- Savoir si une liste est jouable avec la collection réelle, et sinon ce qu'il manque.
- Plus tard : suivi des parties et statistiques, import des catalogues BSData, multi-utilisateur.

**Périmètre**
- Mono-utilisateur au départ, architecture prête pour plusieurs comptes.
- Interface en français ; données de référence (noms d'unités, mots-clés) en anglais, avec traduction française.
- Saisie manuelle des données dès le départ ; import BSData optionnel en phase ultérieure.

## 2. Prérequis et installation

### Prérequis
- Windows 11 + [Laragon](https://laragon.org/) avec :
  - PHP ≥ 8.3 (testé avec 8.3.30)
  - MySQL 8 (ou MariaDB ≥ 10.6)
  - Node.js ≥ 20 et npm
  - Composer
- Git

### Installation sur une machine Laragon vierge
Depuis le terminal Laragon (bouton « Terminal ») :

```bash
cd C:\laragon\www
git clone https://github.com/krishn4k/w40k.git w40k
cd w40k
composer install
copy .env.example .env
php artisan key:generate
npm ci
```

> `npm ci` installe exactement les versions de `package-lock.json` sans le modifier.
> Réserver `npm install <paquet>` à l'ajout d'une dépendance.

Créer les bases de données (MySQL de Laragon démarré) : `w40k` pour l'application,
`w40k_testing` pour les tests :

```bash
mysql -u root -e "CREATE DATABASE w40k CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE DATABASE w40k_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed
npm run build
```

> `--seed` charge les données de test : formats de jeu, une version de points, Space Marines
> et Blood Angels, et un utilisateur `test@example.com` (mot de passe `password`).
> Créer son propre compte : `php artisan app:create-user` (pas d'inscription publique avant la Phase 6).
> Lancer les tests : `php artisan test --compact` (ils utilisent `w40k_testing`, jamais `w40k`).

Puis dans Laragon : menu → Apache → Recharger (création du virtual host automatique),
et ouvrir http://w40k.test

> Au premier lancement, démarrer Laragon **en administrateur** (clic droit → Exécuter en tant
> qu'administrateur) : il doit écrire `w40k.test` dans le fichier `hosts` de Windows. Si le site est
> introuvable, recharger Apache dans ce mode. Si le `DocumentRoot` du fichier
> `C:\laragon\etc\apache2\sites-enabled\auto.w40k.test.conf` ne se termine pas par `/public`,
> supprimer ce fichier et recharger Apache.
>
> `http://localhost:5173` (ouvert par `npm run dev`) n'est que le serveur d'assets Vite,
> pas l'application.

En développement, lancer `npm run dev` pour le rechargement à chaud des assets.

## 3. Stack et choix techniques

| Élément | Choix | Raison |
|---|---|---|
| Framework | Laravel 13 | Dernière version stable, écosystème complet (ORM, migrations, validation, auth à venir). |
| PHP | 8.3 | Minimum requis par Laravel 13, disponible dans Laragon. |
| Base de données | MySQL | Fourni par Laragon, adapté au relationnel. |
| Templates | Blade, layouts via `@extends` / `@section` | Rendu serveur simple, héritage de layouts explicite. Pas de composants Blade pour les layouts. |
| CSS | Tailwind CSS 4 via Vite | Inclus par défaut dans Laravel 13, styles utilitaires sans CSS sur mesure. |
| JS | Alpine.js 3 (npm, bundlé par Vite) | Interactivité légère (formulaires dynamiques, calcul de points en direct) sans SPA. |
| Police | Instrument Sans via Fontsource (npm) | Police auto-hébergée, build reproductible sans accès réseau, aucune requête externe à l'exécution. |
| Assistance IA | Laravel Boost (dev) | Consignes et skills adaptées aux versions installées, serveur MCP (docs, schéma BDD) pour l'assistant de code. |
| Environnement | Laragon | Environnement local Windows tout-en-un, virtual hosts automatiques (`w40k.test`). |

## 4. Modèle de données

Schéma validé le 2026-10-17 (Phase 1). Les migrations suivent ce schéma.

```mermaid
erDiagram
    factions ||--o{ factions : "parent_id (sous-faction)"
    factions ||--o{ detachments : ""
    factions ||--o{ units : ""
    detachments ||--o{ enhancements : ""
    units }o--o{ keywords : "keyword_unit"
    point_sets ||--o{ unit_point_costs : ""
    units ||--o{ unit_point_costs : ""
    point_sets ||--o{ enhancement_point_costs : ""
    enhancements ||--o{ enhancement_point_costs : ""
    game_formats ||--o{ composition_rules : "portée"
    users ||--o{ collection_lots : ""
    units ||--o{ collection_lots : "unit_id / counts_as_unit_id"
    collection_lots ||--o{ lot_status_counts : ""
    collection_lots ||--o{ lot_photos : ""
    users ||--o{ army_lists : ""
    army_lists ||--o{ army_list_entries : ""
    units ||--o{ army_list_entries : ""
    collection_lots |o--o{ army_list_entries : "lot affecté"
    enhancements |o--o{ army_list_entries : ""
```

### Référentiel partagé (sans `user_id`)

| Table | Colonnes clés |
|---|---|
| `factions` | `parent_id` (nullable : sous-faction), `slug`, `name` |
| `detachments` | `faction_id`, `name` |
| `units` | `faction_id`, `name`, `role` (Character, Battleline…), `is_epic_hero` |
| `keywords` + `keyword_unit` | Mots-clés (Infantry, Terminator…), table pivot |
| `point_sets` | `name` (ex. « MFM 2026 v1 »), `valid_from`, `valid_to` |
| `unit_point_costs` | `unit_id`, `point_set_id`, `model_count`, `points` (un palier par ligne) |
| `enhancements` | `detachment_id`, `name` |
| `enhancement_point_costs` | `enhancement_id`, `point_set_id`, `points` |
| `game_formats` | `name` (Incursion, Strike Force, Onslaught), `points_limit` |
| `composition_rules` | `scope_type` + `scope_id` (format, faction, détachement), `rule_type`, `parameters` (JSON), `message` |

Les colonnes `name` du référentiel sont en JSON : `{"en": "...", "fr": "..."}`.
L'anglais est la langue de référence, et une autre langue s'ajoute sans migration.

### Données personnelles (avec `user_id`)

| Table | Colonnes clés |
|---|---|
| `users` | Colonnes Laravel + `playable_min_status` (`assembled` par défaut) |
| `collection_lots` | `user_id`, `unit_id`, `counts_as_unit_id` (proxy, nullable), `label`, `quantity`, `notes` |
| `lot_status_counts` | `collection_lot_id`, `status`, `count` (somme des `count` = `quantity`) |
| `lot_photos` | `collection_lot_id`, `path`, `caption`, `position` |
| `army_lists` | `user_id`, `name`, `faction_id`, `detachment_id`, `game_format_id`, `point_set_id` (nullable = version en vigueur) |
| `army_list_entries` | `army_list_id`, `unit_id`, `model_count`, `collection_lot_id` (nullable, unique par liste), `enhancement_id`, `is_warlord`, `position` |

Statuts de peinture, dans l'ordre :

| Valeur | Libellé |
|---|---|
| `on_sprue` | Sur grappe |
| `assembled` | Monté |
| `primed` | Sous-couché |
| `painted` | Peint |
| `finished` | Terminé (soclage, vernis) |

Règles de composition saisies par les seeders (portée : format de jeu), interprétées par `App\Support\ArmyListAnalysis`.
Les règles de portée faction (et faction mère) ou détachement s'appliquent aussi ; un type inconnu est ignoré :

| `rule_type` | Paramètres | Règle |
|---|---|---|
| `max_points` | — | Total ≤ `points_limit` du format |
| `max_unit_copies` | `default`, `battleline`, `dedicated_transport`, `epic_hero` | Nombre maximal d'exemplaires d'une même unité selon son rôle |
| `max_enhancements` | `max` | Nombre maximal d'optimisations |
| `unique_enhancements` | — | Chaque optimisation au plus une fois |
| `enhancement_requires_character` | — | Optimisations réservées aux Personnages non Héros épiques |
| `requires_warlord` | — | Exactement un Seigneur de guerre, de rôle Personnage |

La portée polymorphe (`scope_type`) utilise des alias (`faction`, `detachment`, `game_format`)
déclarés dans `AppServiceProvider`, jamais les noms de classes.

**Hors périmètre pour l'instant** : options d'équipement, rattachement Leader/Bodyguard, parties (Phase 4).

### Principes retenus

- **Référentiel partagé** (factions, sous-factions, détachements, unités, coûts en points, contraintes)
  séparé des **données personnelles** (collection, listes, parties) rattachées à un utilisateur.
- **Hiérarchie faction → sous-faction**, la sous-faction héritant des unités de sa faction mère.
- **Coûts en points versionnés** (édition / dataslate, date de validité), par palier de taille d'escouade.
- **Aucun total stocké dans une liste** : toujours recalculé.
- **Collection par lots** : une ligne = un groupe de figurines d'une même unité, avec quantité ajustable
  (ex. figurine perdue : 10 → 9) et répartition par statut de peinture.
- **Jouabilité par lot physique** : un lot ne peut être affecté qu'une fois dans une même liste,
  mais peut servir dans plusieurs listes. Deux lots de 5 Terminators = deux unités jouables.
- **Seuil de jouabilité réglable** (statut minimal pour qu'une figurine compte, « monté » par défaut).
- **Proxys / kitbash** prévus dans le modèle (un lot peut compter comme une autre unité).
- **Contraintes de composition génériques**, saisies à la main, et alimentées plus tard par BSData.

## 5. Feuille de route

### Phase 0 : Initialisation
- [x] Création du projet Laravel et configuration Laragon
- [x] Base de données et .env
- [x] Tailwind et Alpine via Vite
- [x] Layout principal Blade
- [x] README initial
- [x] Initialisation Git et premier commit

### Phase 1 : Conception
- [x] Modèle de données complet, présenté sous forme de schéma avant toute migration
- [x] Validation du schéma
- [x] Migrations, modèles Eloquent et relations
- [x] Seeders avec une faction de test (Space Marines + Blood Angels)

### Phase 2 : Collection
- [x] CRUD factions et unités (avec traduction FR)
- [x] CRUD figurines possédées (lots, quantités, statuts)
- [x] Upload de photos
- [x] Tableau de bord de progression
- [x] Authentification (connexion, déconnexion) et paramètres (seuil de jouabilité)

### Phase 3 : Listes d'armée
- [x] Création et édition de listes (format, faction, détachement)
- [x] Calcul des points
- [x] Validation des règles de composition (moteur générique, contraintes saisies à la main)
- [x] Vérification de jouabilité par rapport à la collection
- [x] Duplication et export texte

### Phase 4 : Parties et statistiques
### Phase 5 : Import BSData (alimentation du référentiel et des contraintes)
### Phase 6 : Authentification multi-utilisateur
### Phase ultérieure : Localisation (autres langues que FR/EN)

## 6. Journal des décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-10-03 | Laravel 13 / PHP 8.3 | Dernière version stable ; PHP 8.3.30 disponible dans Laragon. |
| 2026-10-03 | Code généré et testé dans un environnement distant, récupéré par `git pull` | Permet de tester avant de pousser ; le README garde les commandes d'installation locale. |
| 2026-10-17 | Fin de la Phase 0 : développement désormais en local (`C:\laragon\www\w40k`), push Git en fin de session | Travail direct dans l'environnement réel (Laragon, MySQL) ; remplace le développement distant. |
| 2026-10-17 | `npm ci` pour installer les dépendances JS | Évite les modifications locales de `package-lock.json` qui bloquent `git pull`. |
| 2026-10-03 | Collection par lots avec quantité ajustable | Saisie rapide, tout en gérant pertes et répartition par statut. |
| 2026-10-03 | Validation complète des règles via un moteur de contraintes générique | Validation complète demandée, sans dépendance du cœur à BSData. |
| 2026-10-03 | Données de référence en anglais, traduction FR en v1, interface FR | Compatibilité avec BSData (anglais) ; localisation extensible. |
| 2026-10-03 | Jouabilité calculée par lot physique, seuil de statut réglable | Une figurine physique ne compte qu'une fois par liste. |
| 2026-10-03 | Tests MySQL via MariaDB 10.11 dans l'environnement distant | Valider les migrations sur un moteur compatible MySQL avant chaque push. |
| 2026-10-17 | MySQL utf8mb4_unicode_ci, fuseau UTC, locale fr (repli en) | Support complet des caractères ; dates stockées en UTC et converties à l'affichage. |
| 2026-10-17 | `package-lock.json` versionné | Installations npm reproductibles d'une machine à l'autre. |
| 2026-10-17 | Laravel Boost installé (dev), agent Claude Code, sans Laravel Cloud | Suivre les conventions Laravel à jour ; projet local sous Laragon, pas de déploiement Cloud. |
| 2026-10-17 | `AGENTS.md` supprimé, `boost:update` lancé après chaque `composer update` | Seul Claude Code est utilisé ; consignes Boost toujours synchronisées avec les dépendances. |
| 2026-10-17 | Protocole de travail ajouté en tête de `CLAUDE.md` | Appliqué automatiquement par les futures sessions de l'assistant ; préservé par `boost:update`. |
| 2026-10-17 | Alpine.js installé via npm et bundlé par Vite (pas de CDN) | Version figée dans `package-lock.json`, un seul bundle JS, fonctionne hors ligne. |
| 2026-10-17 | Police via Fontsource au lieu de Bunny Fonts | Build sans réseau et testable partout ; police servie depuis l'application. |
| 2026-10-17 | Layout unique `layouts/app` : barre de navigation en haut, menu mobile Alpine | Navigation simple ; liens grisés « bientôt » tant que les pages n'existent pas. |
| 2026-10-17 | Thème clair/sombre automatique (`prefers-color-scheme`, variantes `dark:` de Tailwind) | Suit le réglage du système, sans JS ni préférence à stocker. |
| 2026-10-17 | Traductions du référentiel en colonnes JSON (`{"en", "fr"}`), cast maison | Aucune dépendance ; nouvelle langue sans migration ; anglais = clé de rapprochement avec BSData. |
| 2026-10-17 | Répartition par statut dans une table `lot_status_counts` | Un statut s'ajoute sans migration ; la somme est contrôlée en code. |
| 2026-10-17 | 5 statuts de peinture : sur grappe, monté, sous-couché, peint, terminé | Granularité suffisante pour la progression ; « monté » sert de seuil de jouabilité par défaut. |
| 2026-10-17 | Tests sur une base MySQL dédiée `w40k_testing` (au lieu de SQLite en mémoire) | Même moteur qu'en développement : JSON, clés étrangères et index uniques testés en conditions réelles. |
| 2026-10-17 | Suppressions : cascade vers les enfants, refus pour le référentiel utilisé, lot supprimé → entrée de liste conservée sans lot | Aucune donnée personnelle perdue par erreur ; l'historique des listes reste lisible. |
| 2026-10-17 | Statuts et rôles stockés en `string`, castés en enums PHP | Nouvelle valeur sans migration (contrairement au type `ENUM` MySQL). |
| 2026-10-17 | Données de seed Space Marines / Blood Angels avec points plausibles, marquées « données de test » | Teste l'héritage faction → sous-faction sans prétendre reproduire le MFM. |
| 2026-10-17 | Authentification faite main dès la Phase 2 : connexion/déconnexion, 5 essais max, comptes créés par `app:create-user` | Aucune dépendance (pas de starter kit) ; données déjà rattachées au bon utilisateur ; inscription publique reportée à la Phase 6. |
| 2026-10-17 | `lang/fr` écrit à la main (validation, auth, pagination, mots de passe) ; `lang/en` non publié | Pas de dépendance ; l'anglais reste fourni par le framework. |
| 2026-10-17 | Styles partagés (`card`, `form-input`, `btn-primary`…) déclarés en `@utility` dans `app.css` | Vues homogènes sans composants Blade ; `@utility` requis par Tailwind 4 pour `@apply`. |
| 2026-10-17 | Quantité d'un lot = somme de sa répartition par statut (saisie par statut, total calculé) | Garantit l'invariant somme = quantité sans contrôle séparé ; une perte se saisit en baissant un statut. |
| 2026-10-17 | Photos sur le disque privé (`storage/app/private`), servies par une route contrôlant le propriétaire | Pas de `storage:link` ; photos jamais publiques, prêt pour le multi-utilisateur. |
| 2026-10-17 | Points d'un lot estimés au coût par figurine du plus petit palier qui le contient (sinon le plus grand) | Donne une progression « en points » lisible même quand la taille du lot ne correspond à aucun palier. |
| 2026-10-17 | Référentiel modifiable par tout utilisateur connecté | Mono-utilisateur pour l'instant ; rôle administrateur à prévoir en Phase 6. |
| 2026-10-17 | `/` = tableau de bord ; coûts saisis dans la version de points en vigueur uniquement | Page d'accueil utile ; gestion des versions de points reportée (voir points en attente). |
| 2026-10-18 | Règles de composition signalées, pas bloquantes ; seules les contraintes dures (unité disponible, taille d'escouade, lot) refusent la saisie | Permet de construire une liste brouillon et de voir tout ce qui ne va pas. |
| 2026-10-18 | Jouabilité : lot affecté qui joue l'unité, avec assez de figurines au statut minimal de l'utilisateur | Montre précisément ce qu'il reste à monter ou peindre. |
| 2026-10-18 | Changer de détachement retire les optimisations ; changer de faction est refusé si la liste contient des unités | Évite des entrées incohérentes avec le référentiel. |
| 2026-10-18 | Duplication conservant les lots affectés ; export en texte brut (noms FR + VO entre parenthèses) | Un lot peut servir dans plusieurs listes ; texte collable partout. |
| 2026-10-17 | URLs en français (`/connexion`, `/collection`…), noms de routes en anglais | Interface FR ; noms de routes stables pour le code. |

## 7. Problèmes connus et points en attente

- Moteur de contraintes : 6 types de règles de base ; pas encore d'écran pour saisir des règles
  de faction ou de détachement (seeders uniquement). Rattachement Leader/Bodyguard non géré.
- Données de seed : points et optimisations Blood Angels indicatifs, à remplacer par les vraies valeurs
  (saisie manuelle ou import BSData en Phase 5).
- Gestion des versions de points (création d'une nouvelle version, copie des coûts) : pas encore d'interface ;
  les coûts se saisissent dans la version en vigueur.
- Détachements et optimisations : saisis par seeder uniquement, pas encore d'écran de gestion.
- Le navigateur intégré de l'assistant ne charge pas les assets du serveur Vite (`npm run dev`, autre origine) :
  sans incidence pour l'application.
