<?php

namespace App\Models;

use Database\Factories\ArmyListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'faction_id', 'detachment_id', 'game_format_id', 'point_set_id', 'notes'])]
class ArmyList extends Model
{
    /** @use HasFactory<ArmyListFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
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
     * @return BelongsTo<GameFormat, $this>
     */
    public function gameFormat(): BelongsTo
    {
        return $this->belongsTo(GameFormat::class);
    }

    /**
     * Point set pinned on this list; null means the point set currently in force.
     *
     * @return BelongsTo<PointSet, $this>
     */
    public function pointSet(): BelongsTo
    {
        return $this->belongsTo(PointSet::class);
    }

    /**
     * @return HasMany<ArmyListEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ArmyListEntry::class)->orderBy('position');
    }

    /**
     * Point set used to compute this list's points.
     */
    public function effectivePointSet(): ?PointSet
    {
        return $this->pointSet ?? PointSet::current();
    }
}
