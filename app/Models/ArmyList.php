<?php

namespace App\Models;

use App\Enums\ForceDisposition;
use Database\Factories\ArmyListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'faction_id', 'game_format_id', 'force_disposition', 'point_set_id', 'notes'])]
class ArmyList extends Model
{
    /** @use HasFactory<ArmyListFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'force_disposition' => ForceDisposition::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Faction, $this>
     */
    public function faction(): BelongsTo
    {
        return $this->belongsTo(Faction::class);
    }

    /**
     * Selected detachments, in the order they were chosen (11th edition: several per list, within the DP budget).
     *
     * @return BelongsToMany<Detachment, $this>
     */
    public function detachments(): BelongsToMany
    {
        return $this->belongsToMany(Detachment::class, 'army_list_detachments')->withPivot('position')->orderByPivot('position');
    }

    /**
     * Replace the selected detachments, keeping the given order.
     *
     * @param  list<int>  $detachmentIds
     */
    public function syncDetachments(array $detachmentIds): void
    {
        $this->detachments()->sync(collect(array_values(array_unique($detachmentIds)))
            ->mapWithKeys(fn (int $id, int $position): array => [$id => ['position' => $position]])
            ->all());
    }

    /**
     * Detachment names joined for display ("Gladius Task Force + Bastion Task Force").
     */
    public function detachmentNames(): string
    {
        return $this->detachments->map(fn (Detachment $detachment): string => (string) $detachment->name)->join(' + ');
    }

    /**
     * Force dispositions granted by the selected detachments, in game order.
     *
     * @return list<ForceDisposition>
     */
    public function availableForceDispositions(): array
    {
        $granted = $this->detachments
            ->flatMap(fn (Detachment $detachment): array => $detachment->force_dispositions ?? [])
            ->map(fn (string $name): ?ForceDisposition => ForceDisposition::fromMfm($name))
            ->filter();

        return array_values(array_filter(ForceDisposition::cases(), fn (ForceDisposition $disposition): bool => $granted->contains($disposition)));
    }

    /**
     * @return BelongsTo<GameFormat, $this>
     */
    public function gameFormat(): BelongsTo
    {
        return $this->belongsTo(GameFormat::class);
    }

    /**
     * Point set pinned on this list; null means the point set currently in force.
     *
     * @return BelongsTo<PointSet, $this>
     */
    public function pointSet(): BelongsTo
    {
        return $this->belongsTo(PointSet::class);
    }

    /**
     * @return HasMany<ArmyListEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ArmyListEntry::class)->orderBy('position');
    }

    /**
     * Point set used to compute this list's points.
     */
    public function effectivePointSet(): ?PointSet
    {
        return $this->pointSet ?? PointSet::current();
    }
}
