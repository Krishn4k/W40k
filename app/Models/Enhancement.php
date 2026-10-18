<?php

namespace App\Models;

use App\Models\Concerns\HasTranslationReview;
use App\Support\LocalizedText;
use Database\Factories\EnhancementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['bsdata_id', 'detachment_id', 'name', 'is_upgrade'])]
class Enhancement extends Model
{
    /** @use HasFactory<EnhancementFactory> */
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
            'is_upgrade' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Detachment, $this>
     */
    public function detachment(): BelongsTo
    {
        return $this->belongsTo(Detachment::class);
    }

    /**
     * @return HasMany<EnhancementPointCost, $this>
     */
    public function pointCosts(): HasMany
    {
        return $this->hasMany(EnhancementPointCost::class);
    }

    /**
     * @return HasMany<ArmyListEntry, $this>
     */
    public function armyListEntries(): HasMany
    {
        return $this->hasMany(ArmyListEntry::class);
    }
}
