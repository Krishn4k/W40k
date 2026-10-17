<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Marks the French name of a reference record (faction, unit, keyword, detachment, enhancement) as still to be checked.
 */
#[Fillable(['translatable_type', 'translatable_id'])]
class TranslationReview extends Model
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
