<?php

namespace App\Models;

use Database\Factories\PointSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Versioned set of points costs (e.g. a Munitorum Field Manual edition).
 */
#[Fillable(['name', 'valid_from', 'valid_to'])]
class PointSet extends Model
{
    /** @use HasFactory<PointSetFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    /**
     * @return HasMany<UnitPointCost, $this>
     */
    public function unitPointCosts(): HasMany
    {
        return $this->hasMany(UnitPointCost::class);
    }

    /**
     * @return HasMany<EnhancementPointCost, $this>
     */
    public function enhancementPointCosts(): HasMany
    {
        return $this->hasMany(EnhancementPointCost::class);
    }

    /**
     * Point sets valid on the given date (today by default).
     *
     * @param  Builder<PointSet>  $query
     */
    #[Scope]
    protected function validOn(Builder $query, ?Carbon $date = null): void
    {
        $date ??= today();

        $query->whereDate('valid_from', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date));
    }

    /**
     * The point set currently in force: the most recent one valid today.
     */
    public static function current(): ?self
    {
        return static::query()->validOn()->latest('valid_from')->first();
    }
}
