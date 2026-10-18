<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paid wargear item taken by a unit of an army list, with its number of items.
 */
#[Table('army_list_entry_wargear')]
#[Fillable(['army_list_entry_id', 'name', 'quantity'])]
#[WithoutTimestamps]
class ArmyListEntryWargear extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ArmyListEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(ArmyListEntry::class, 'army_list_entry_id');
    }
}
