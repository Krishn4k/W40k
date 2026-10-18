<?php

namespace App\Models;

use Database\Factories\ArmyListEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unit fielded in an army list, optionally backed by a physical collection lot.
 */
#[Fillable(['army_list_id', 'unit_id', 'model_count', 'collection_lot_id', 'enhancement_id', 'attached_to_entry_id', 'is_warlord', 'position'])]
class ArmyListEntry extends Model
{
    /** @use HasFactory<ArmyListEntryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'model_count' => 'integer',
            'is_warlord' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ArmyList, $this>
     */
    public function armyList(): BelongsTo
    {
        return $this->belongsTo(ArmyList::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<CollectionLot, $this>
     */
    public function collectionLot(): BelongsTo
    {
        return $this->belongsTo(CollectionLot::class);
    }

    /**
     * @return BelongsTo<Enhancement, $this>
     */
    public function enhancement(): BelongsTo
    {
        return $this->belongsTo(Enhancement::class);
    }

    /**
     * Bodyguard unit this Leader or Support unit is attached to.
     *
     * @return BelongsTo<ArmyListEntry, $this>
     */
    public function attachedTo(): BelongsTo
    {
        return $this->belongsTo(ArmyListEntry::class, 'attached_to_entry_id');
    }

    /**
     * Leader and Support units attached to this bodyguard unit.
     *
     * @return HasMany<ArmyListEntry, $this>
     */
    public function attachedEntries(): HasMany
    {
        return $this->hasMany(ArmyListEntry::class, 'attached_to_entry_id');
    }

    /**
     * Paid wargear items taken by this unit.
     *
     * @return HasMany<ArmyListEntryWargear, $this>
     */
    public function wargear(): HasMany
    {
        return $this->hasMany(ArmyListEntryWargear::class);
    }
}
