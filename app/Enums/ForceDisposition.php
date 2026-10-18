<?php

namespace App\Enums;

/**
 * Force dispositions granted by detachments (11th edition). In a Warhammer Event, each player picks one
 * of the dispositions of their detachments; both picks give each player's primary mission (Event Companion 1.3).
 */
enum ForceDisposition: string
{
    case TakeAndHold = 'take_and_hold';
    case PriorityAssets = 'priority_assets';
    case PurgeTheFoe = 'purge_the_foe';
    case Disruption = 'disruption';
    case Reconnaissance = 'reconnaissance';

    /**
     * Primary missions, English name then proposed French name (to be checked), by disposition then opponent's
     * disposition. Each pair gives the mission of the player holding the first disposition.
     *
     * @var array<string, array<string, array{0: string, 1: string}>>
     */
    private const PRIMARY_MISSIONS = [
        'take_and_hold' => [
            'take_and_hold' => ['Battlefield Dominance', 'Domination du champ de bataille'],
            'priority_assets' => ['Inescapable Dominion', 'Domination inéluctable'],
            'purge_the_foe' => ['Immovable Object', 'Objet inébranlable'],
            'disruption' => ['Determined Acquisition', 'Acquisition résolue'],
            'reconnaissance' => ['Purge and Secure', 'Purger et sécuriser'],
        ],
        'priority_assets' => [
            'take_and_hold' => ['Secure Asset', 'Sécuriser l\'atout'],
            'priority_assets' => ['Sabotage', 'Sabotage'],
            'purge_the_foe' => ['Vital Link', 'Lien vital'],
            'disruption' => ['Extract Relic', 'Extraire la relique'],
            'reconnaissance' => ['Vanguard Operation', 'Opération d\'avant-garde'],
        ],
        'purge_the_foe' => [
            'take_and_hold' => ['Unstoppable Force', 'Force irrésistible'],
            'priority_assets' => ['Destroyer\'s Wrath', 'Courroux du destructeur'],
            'purge_the_foe' => ['Meatgrinder', 'Hachoir'],
            'disruption' => ['Punishment', 'Châtiment'],
            'reconnaissance' => ['Consecrate', 'Consacrer'],
        ],
        'disruption' => [
            'take_and_hold' => ['Death Trap', 'Piège mortel'],
            'priority_assets' => ['Locate and Deny', 'Localiser et refuser'],
            'purge_the_foe' => ['Delaying Action', 'Action retardatrice'],
            'disruption' => ['Outmanoeuvre', 'Déborder'],
            'reconnaissance' => ['Smoke and Mirrors', 'Écran de fumée'],
        ],
        'reconnaissance' => [
            'take_and_hold' => ['Reconnaissance Sweep', 'Ratissage de reconnaissance'],
            'priority_assets' => ['Search and Scour', 'Fouiller et ratisser'],
            'purge_the_foe' => ['Triangulation', 'Triangulation'],
            'disruption' => ['Surveil the Foe', 'Surveiller l\'ennemi'],
            'reconnaissance' => ['Gather Intel', 'Recueillir des renseignements'],
        ],
    ];

    /**
     * French label displayed in the interface (proposed translation, to be checked).
     */
    public function label(): string
    {
        return match ($this) {
            self::TakeAndHold => 'Tenir et contrôler',
            self::PriorityAssets => 'Atouts prioritaires',
            self::PurgeTheFoe => 'Purger l\'ennemi',
            self::Disruption => 'Perturbation',
            self::Reconnaissance => 'Reconnaissance',
        };
    }

    /**
     * Official English name, as written in the Munitorum Field Manual ("Take and Hold").
     */
    public function englishName(): string
    {
        return match ($this) {
            self::TakeAndHold => 'Take and Hold',
            self::PriorityAssets => 'Priority Assets',
            self::PurgeTheFoe => 'Purge the Foe',
            self::Disruption => 'Disruption',
            self::Reconnaissance => 'Reconnaissance',
        };
    }

    /**
     * Disposition from its Munitorum Field Manual spelling ("TAKE AND HOLD"), null when unknown.
     */
    public static function fromMfm(string $name): ?self
    {
        return self::tryFrom(str_replace(' ', '_', strtolower(trim($name))));
    }

    /**
     * Primary mission of the player holding this disposition against the opponent's one.
     *
     * @return array{en: string, fr: string}
     */
    public function primaryMissionAgainst(self $opponent): array
    {
        [$english, $french] = self::PRIMARY_MISSIONS[$this->value][$opponent->value];

        return ['en' => $english, 'fr' => $french];
    }
}
