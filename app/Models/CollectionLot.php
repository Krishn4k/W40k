<?php

namespace App\Models;

use App\Enums\PaintStatus;
use Database\Factories\CollectionLotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Group of physical models of the same unit owned by a user.
 */
#[Fillable(['user_id', 'unit_id', 'counts_as_unit_id', 'label', 'quantity', 'notes'])]
class CollectionLot extends Model
{
    /** @use HasFactory<CollectionLotFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
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
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Unit this lot is played as (proxy or kitbash).
     *
     * @return BelongsTo<Unit, $this>
     */
    public function countsAsUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'counts_as_unit_id');
    }

    /**
     * @return HasMany<LotStatusCount, $this>
     */
    public function statusCounts(): HasMany
    {
        return $this->hasMany(LotStatusCount::class);
    }

    /**
     * @return HasMany<LotPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(LotPhoto::class)->orderBy('position');
    }

    /**
     * @return HasMany<ArmyListEntry, $this>
     */
    public function armyListEntries(): HasMany
    {
        return $this->hasMany(ArmyListEntry::class);
    }

    /**
     * Identifier of the unit this lot is played as: the proxied unit if any, else its own unit.
     */
    public function playedUnitId(): int
    {
        return $this->counts_as_unit_id ?? $this->unit_id;
    }

    /**
     * Number of models of this lot that have reached the given status.
     */
    public function countAtLeast(PaintStatus $threshold): int
    {
        return $this->statusCounts
            ->filter(fn (LotStatusCount $statusCount): bool => $statusCount->status->isAtLeast($threshold))
            ->sum('count');
    }
}
