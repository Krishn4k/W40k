<?php

namespace App\Models;

use App\Enums\UnitRole;
use App\Models\Concerns\HasTranslationReview;
use App\Support\LocalizedText;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['bsdata_id', 'faction_id', 'name', 'role', 'is_epic_hero'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
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

    /**
     * Lots of other units played as this one (proxies).
     *
     * @return HasMany<CollectionLot, $this>
     */
    public function proxyLots(): HasMany
    {
        return $this->hasMany(CollectionLot::class, 'counts_as_unit_id');
    }

    /**
     * @return HasMany<ArmyListEntry, $this>
     */
    public function armyListEntries(): HasMany
    {
        return $this->hasMany(ArmyListEntry::class);
    }

    /**
     * Whether personal data (collection lots, proxies or army lists) still references this unit.
     */
    public function isInUse(): bool
    {
        return $this->collectionLots()->exists()
            || $this->proxyLots()->exists()
            || $this->armyListEntries()->exists();
    }

    /**
     * Eager load what unit listings display: faction, keywords and the costs of the given point set.
     *
     * @param  Builder<Unit>  $query
     */
    #[Scope]
    protected function withListingData(Builder $query, ?PointSet $pointSet): void
    {
        $query->with([
            'faction',
            'keywords',
            'pointCosts' => fn ($query) => $query->where('point_set_id', $pointSet?->id)->orderBy('model_count'),
        ]);
    }

    /**
     * Whether the unit is a Legends unit (outside the current rules, flagged "[Legends]" in its English name).
     */
    public function isLegends(): bool
    {
        return str_contains($this->name->get('en'), '[Legends]');
    }

    /**
     * Exclude Legends units.
     *
     * @param  Builder<Unit>  $query
     */
    #[Scope]
    protected function withoutLegends(Builder $query): void
    {
        $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) NOT LIKE ?", ['%[Legends]%']);
    }

    /**
     * Units whose English or French name contains the given text, case-insensitively.
     *
     * @param  Builder<Unit>  $query
     */
    #[Scope]
    protected function nameContains(Builder $query, string $text): void
    {
        $pattern = '%'.mb_strtolower($text).'%';

        $query->where(fn (Builder $query) => $query
            ->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))) LIKE ?", [$pattern])
            ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.fr'))) LIKE ?", [$pattern]));
    }

    /**
     * Filters shared by unit listings: name search, Legends hidden unless asked for.
     *
     * @param  Builder<Unit>  $query
     */
    #[Scope]
    protected function listingFilters(Builder $query, string $search, bool $withLegends): void
    {
        $query->when($search !== '', fn (Builder $query) => $query->nameContains($search))
            ->unless($withLegends, fn (Builder $query) => $query->withoutLegends());
    }

    /**
     * Order units by their English (reference) name.
     *
     * @param  Builder<Unit>  $query
     */
    #[Scope]
    protected function orderByName(Builder $query): void
    {
        $query->orderBy('name->en');
    }
}
