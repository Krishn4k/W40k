<?php

namespace App\Models;

use App\Support\LocalizedText;
use Database\Factories\CompositionRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Generic army composition rule, scoped to a game format, a faction or a detachment.
 */
#[Fillable(['scope_type', 'scope_id', 'rule_type', 'parameters', 'message'])]
class CompositionRule extends Model
{
    /** @use HasFactory<CompositionRuleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'message' => LocalizedText::class,
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function scope(): MorphTo
    {
        return $this->morphTo();
    }
}
