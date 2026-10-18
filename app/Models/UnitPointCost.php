<?php

namespace App\Models;

use Database\Factories\UnitPointCostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Points cost of a unit for a given squad size, in a given point set, from a given copy rank in the army
 * (11th edition: the 3rd and following copies of a unit may cost more).
 */
#[Fillable(['unit_id', 'point_set_id', 'model_count', 'from_copy', 'points'])]
#[WithoutTimestamps]
class UnitPointCost extends Model
{
    /** @use HasFactory<UnitPointCostFactory> */
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
            'from_copy' => 'integer',
            'points' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<PointSet, $this>
     */
    public function pointSet(): BelongsTo
    {
        return $this->belongsTo(PointSet::class);
    }
}
