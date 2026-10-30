<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use App\Models\Concerns\HasTranslationReview;
use App\Support\LocalizedText;
use Database\Factories\DetachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['bsdata_id', 'faction_id', 'name', 'detachment_points', 'unique_tag', 'force_dispositions'])]
class Detachment extends Model
{
    /** @use HasFactory<DetachmentFactory> */
    use HasFactory, HasLocalizedName, HasTranslationReview;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => LocalizedText::class,
            'detachment_points' => 'integer',
            'force_dispositions' => 'array',
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
     * Army lists that selected this detachment.
     *
     * @return BelongsToMany<ArmyList, $this>
     */
    public function armyLists(): BelongsToMany
    {
        return $this->belongsToMany(ArmyList::class, 'army_list_detachments');
    }

    /**
     * @return MorphMany<CompositionRule, $this>
     */
    public function compositionRules(): MorphMany
    {
        return $this->morphMany(CompositionRule::class, 'scope');
    }
}
