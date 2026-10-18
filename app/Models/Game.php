<?php

namespace App\Models;

use App\Enums\GameResult;
use App\Support\ArmyListAnalysis;
use App\Support\ArmyListTextExport;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A game played by the user, with a copy of the army list as it was at the time.
 */
#[Fillable([
    'user_id', 'played_on', 'army_list_id', 'list_name', 'faction_id', 'detachment_id', 'list_points', 'list_snapshot',
    'opponent_name', 'opponent_faction_id', 'mission', 'result', 'score_for', 'score_against', 'notes',
])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'played_on' => 'date',
            'result' => GameResult::class,
            'list_points' => 'integer',
            'score_for' => 'integer',
            'score_against' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The list the copy was taken from; null once that list is deleted.
     *
     * @return BelongsTo<ArmyList, $this>
     */
    public function armyList(): BelongsTo
    {
        return $this->belongsTo(ArmyList::class);
    }

    /**
     * The user's faction for this game.
     *
     * @return BelongsTo<Faction, $this>
     */
    public function faction(): BelongsTo
    {
        return $this->belongsTo(Faction::class);
    }

    /**
     * @return BelongsTo<Detachment, $this>
     */
    public function detachment(): BelongsTo
    {
        return $this->belongsTo(Detachment::class);
    }

    /**
     * @return BelongsTo<Faction, $this>
     */
    public function opponentFaction(): BelongsTo
    {
        return $this->belongsTo(Faction::class, 'opponent_faction_id');
    }

    /**
     * Copy the list's name, faction, main (first) detachment, points and text export into the game.
     */
    public function snapshotArmyList(ArmyList $armyList): void
    {
        $analysis = new ArmyListAnalysis($armyList);

        $this->fill([
            'army_list_id' => $armyList->id,
            'list_name' => $armyList->name,
            'faction_id' => $armyList->faction_id,
            'detachment_id' => $armyList->detachments()->value('detachments.id'),
            'list_points' => $analysis->totalPoints(),
            'list_snapshot' => (new ArmyListTextExport($analysis))->render(),
        ]);
    }
}
