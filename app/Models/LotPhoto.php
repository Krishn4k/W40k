<?php

namespace App\Models;

use Database\Factories\LotPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['collection_lot_id', 'path', 'caption', 'position'])]
class LotPhoto extends Model
{
    /** @use HasFactory<LotPhotoFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
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
