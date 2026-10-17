<?php

namespace App\Models;

use App\Models\Concerns\HasTranslationReview;
use App\Support\LocalizedText;
use Database\Factories\DetachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['bsdata_id', 'faction_id', 'name'])]
class Detachment extends Model
{
    /** @use HasFactory<DetachmentFactory> */
    use HasFactory, HasTranslationReview;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => LocalizedText::class,
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
     * @return HasMany<Enhancement, $this>
     */
    public function enhancements(): HasMany
    {
        return $this->hasMany(Enhancement::class);
    }

    /**
     * @return HasMany<ArmyList, $this>
     */
    public function armyLists(): HasMany
    {
        return $this->hasMany(ArmyList::class);
    }

    /**
     * @return MorphMany<CompositionRule, $this>
     */
    public function compositionRules(): MorphMany
    {
        return $this->morphMany(CompositionRule::class, 'scope');
    }
}
