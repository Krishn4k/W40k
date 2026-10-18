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
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
     * @return HasMany<UnitWargearCost, $this>
     */
    public function wargearCosts(): HasMany
    {
        return $this->hasMany(UnitWargearCost::class);
    }

    /**
     * Bodyguard units this Leader or Support unit can be attached to.
     *
     * @return HasMany<UnitAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(UnitAttachment::class, 'leader_unit_id');
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
            'pointCosts' => fn ($query) => $query->where('point_set_id', $pointSet?->id)->orderBy('model_count')->orderBy('from_copy'),
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
     * Free-text search: every word must match the unit's name, one of its keywords, its faction
     * (French or English, ignoring case and accents) or its role.
     *
     * @param  Builder<Unit>  $query
     */
    #[Scope]
    protected function matchingSearch(Builder $query, string $search): void
    {
        $translatedNameLike = fn (Builder $query, string $pattern): Builder => $query->where(fn (Builder $query) => $query
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) COLLATE utf8mb4_unicode_ci LIKE ?", [$pattern])
            ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.fr')) COLLATE utf8mb4_unicode_ci LIKE ?", [$pattern]));

        foreach (preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $pattern = '%'.addcslashes($word, '\\%_').'%';
            $roles = collect(UnitRole::cases())
                ->filter(fn (UnitRole $role): bool => str_contains(Str::lower(Str::ascii($role->label().' '.$role->value)), Str::lower(Str::ascii($word))))
                ->values();

            $query->where(fn (Builder $query) => $translatedNameLike($query, $pattern)
                ->orWhereHas('keywords', fn (Builder $query) => $translatedNameLike($query, $pattern))
                ->orWhereHas('faction', fn (Builder $query) => $translatedNameLike($query, $pattern))
                ->when($roles->isNotEmpty(), fn (Builder $query) => $query->orWhereIn('role', $roles)));
        }
    }

    /**
     * Filters shared by unit listings: free-text search, Legends hidden unless asked for.
     *
     * @param  Builder<Unit>  $query
     */
    #[Scope]
    protected function listingFilters(Builder $query, string $search, bool $withLegends): void
    {
        $query->when(trim($search) !== '', fn (Builder $query) => $query->matchingSearch($search))
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

    /**
     * Sort column and direction requested by a listing ("?tri=points&ordre=desc"), name ascending by default.
     *
     * @return array{0: 'name'|'role'|'points', 1: 'asc'|'desc'}
     */
    public static function listingSort(Request $request): array
    {
        $column = ['nom' => 'name', 'role' => 'role', 'points' => 'points'][$request->query('tri')] ?? 'name';

        return [$column, $request->query('ordre') === 'desc' ? 'desc' : 'asc'];
    }

    /**
     * Sort a unit listing by displayed name, role (in game order) or lowest points cost in the point set.
     *
     * @param  Builder<Unit>  $query
     * @param  'name'|'role'|'points'  $column
     * @param  'asc'|'desc'  $direction
     */
    #[Scope]
    protected function sortedBy(Builder $query, string $column, string $direction, ?PointSet $pointSet): void
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';
        $locale = preg_replace('/[^a-z]/', '', app()->getLocale()) ?: 'en';
        $displayedName = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$locale}')), JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))) COLLATE utf8mb4_unicode_ci";

        match ($column) {
            'role' => $query->orderByRaw(
                'FIELD(role, '.collect(UnitRole::cases())->map(fn (UnitRole $role): string => "'{$role->value}'")->join(', ').") {$direction}",
            ),
            'points' => $query->orderBy(
                UnitPointCost::query()->selectRaw('MIN(points)')->whereColumn('unit_id', 'units.id')->where('point_set_id', $pointSet?->id),
                $direction,
            ),
            default => null,
        };

        $query->orderByRaw("{$displayedName} ".($column === 'name' ? $direction : 'asc'));
    }
}
