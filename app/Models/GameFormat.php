<?php

namespace App\Models;

use App\Support\LocalizedText;
use Database\Factories\GameFormatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['name', 'points_limit'])]
class GameFormat extends Model
{
    /** @use HasFactory<GameFormatFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => LocalizedText::class,
            'points_limit' => 'integer',
        ];
    }

    /**
     * @return MorphMany<CompositionRule, $this>
     */
    public function compositionRules(): MorphMany
    {
        return $this->morphMany(CompositionRule::class, 'scope');
    }
}
