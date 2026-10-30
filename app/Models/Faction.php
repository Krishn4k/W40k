<?php

namespace App\Models;

use App\Models\Concerns\HasTranslationReview;
use App\Support\LocalizedText;
use Database\Factories\FactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['bsdata_id', 'parent_id', 'slug', 'name'])]
class Faction extends Model
{
    /** @use HasFactory<FactionFactory> */
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
     * Parent faction of a sub-faction (e.g. Space Marines for Blood Angels).
     *
     * @return BelongsTo<Faction, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Faction::class, 'parent_id');
    }

    /**
     * @return HasMany<Faction, $this>
     */
    public function subFactions(): HasMany
    {
        return $this->hasMany(Faction::class, 'parent_id');
    }

    /**
     * @return HasMany<Detachment, $this>
     */
    public function detachments(): HasMany
    {
        return $this->hasMany(Detachment::class);
    }

    /**
     * Units defined directly on this faction (without the inherited ones).
     *
     * @return HasMany<Unit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
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

    /**
     * Display name including the parent faction, e.g. "Space Marines › Blood Angels".
     */
    public function fullName(): string
    {
        return $this->parent ? $this->parent->name.' › '.$this->name : (string) $this->name;
    }

    /**
     * Every faction as select options: id => full name ("Space Marines › Blood Angels"), sorted.
     *
     * @return array<int, string>
     */
    public static function selectOptions(): array
    {
        return self::query()->with('parent')->get()
            ->mapWithKeys(fn (Faction $faction): array => [$faction->id => $faction->fullName()])
            ->sort()
            ->all();
    }

    /**
     * Identifiers of this faction and of its parent, used to resolve inherited units and detachments.
     *
     * @return array<int, int>
     */
    public function lineageIds(): array
    {
        return array_values(array_filter([$this->id, $this->parent_id]));
    }

    /**
     * Units available to this faction: its own units and those of its parent faction.
     *
     * @return Builder<Unit>
     */
    public function availableUnits(): Builder
    {
        return Unit::query()->whereIn('faction_id', $this->lineageIds());
    }

    /**
     * Detachments available to this faction: its own detachments and those of its parent faction.
     *
     * @return Builder<Detachment>
     */
    public function availableDetachments(): Builder
    {
        return Detachment::query()->whereIn('faction_id', $this->lineageIds());
    }
}
