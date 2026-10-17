<?php

namespace App\Models;

use Database\Factories\EnhancementPointCostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['enhancement_id', 'point_set_id', 'points'])]
#[WithoutTimestamps]
class EnhancementPointCost extends Model
{
    /** @use HasFactory<EnhancementPointCostFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Enhancement, $this>
     */
    public function enhancement(): BelongsTo
    {
        return $this->belongsTo(Enhancement::class);
    }

    /**
     * @return BelongsTo<PointSet, $this>
     */
    public function pointSet(): BelongsTo
    {
        return $this->belongsTo(PointSet::class);
    }
}
