<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Leader or Support unit that can be attached to a given bodyguard unit (11th edition).
 * Support units must be attached; Leader units may be.
 */
#[Fillable(['leader_unit_id', 'bodyguard_unit_id', 'kind'])]
#[WithoutTimestamps]
class UnitAttachment extends Model
{
    public const LEADER = 'leader';

    public const SUPPORT = 'support';

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'leader_unit_id');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function bodyguard(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'bodyguard_unit_id');
    }
}
