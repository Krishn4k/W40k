<?php

namespace App\Models;

use App\Enums\PaintStatus;
use Database\Factories\LotStatusCountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Number of models of a collection lot at a given painting status.
 */
#[Fillable(['collection_lot_id', 'status', 'count'])]
#[WithoutTimestamps]
class LotStatusCount extends Model
{
    /** @use HasFactory<LotStatusCountFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaintStatus::class,
            'count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CollectionLot, $this>
     */
    public function collectionLot(): BelongsTo
    {
        return $this->belongsTo(CollectionLot::class);
    }
}
