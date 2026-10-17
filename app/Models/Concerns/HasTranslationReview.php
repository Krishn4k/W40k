<?php

namespace App\Models\Concerns;

use App\Models\TranslationReview;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Reference records whose French name can be flagged as still to be checked.
 */
trait HasTranslationReview
{
    /**
     * @return MorphOne<TranslationReview, $this>
     */
    public function translationReview(): MorphOne
    {
        return $this->morphOne(TranslationReview::class, 'translatable');
    }

    /**
     * Flag the French name as still to be checked, or as validated.
     */
    public function markTranslationToReview(bool $toReview): void
    {
        if ($toReview) {
            $this->translationReview()->firstOrCreate([]);
        } else {
            $this->translationReview()->delete();
        }
    }
}
