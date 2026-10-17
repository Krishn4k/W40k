<?php

namespace App\Models;

use App\Enums\UnitRole;
use App\Support\LocalizedText;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['faction_id', 'name', 'role', 'is_epic_hero'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => LocalizedText::class,
            'role' => UnitRole::class,
            'is_epic_hero' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Faction, $this>
     */
    public function faction(): BelongsTo
    {
        return $this->belongsTo(Faction::class);
    }

    /**
     * @return BelongsToMany<Keyword, $this>
     */
    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class);
    }

    /**
     * @return HasMany<UnitPointCost, $this>
     */
    public function pointCosts(): HasMany
    {
        return $this->hasMany(UnitPointCost::class);
    }

    /**
     * @return HasMany<CollectionLot, $this>
     */
    public function collectionLots(): HasMany
    {
        return $this->hasMany(CollectionLot::class);
    }
}
