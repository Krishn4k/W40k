<?php

namespace App\Models;

use App\Models\Concerns\HasTranslationReview;
use App\Support\LocalizedText;
use Database\Factories\KeywordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['bsdata_id', 'name'])]
class Keyword extends Model
{
    /** @use HasFactory<KeywordFactory> */
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
        ];
    }

    /**
     * @return BelongsToMany<Unit, $this>
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class);
    }
}
