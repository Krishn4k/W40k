<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faction;
use App\Models\TranslationReview;
use App\Support\ReferenceTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * French names of the reference data: overview of what is left by faction, working list,
 * one-by-one editing and bulk validation.
 *
 * Each name has a status: "missing" (no French name), "review" (French name to check) or "validated".
 */
class TranslationController extends Controller
{
    /**
     * French labels of the translatable types.
     *
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        'faction' => 'Factions',
        'detachment' => 'Détachements',
        'enhancement' => 'Optimisations',
        'unit' => 'Unités',
        'keyword' => 'Mots-clés',
    ];

    /**
     * French labels of the statuses.
     *
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        'missing' => 'À traduire',
        'review' => 'À vérifier',
        'validated' => 'Validé',
    ];

    /**
     * Types whose records belong to a faction, shown as columns of the overview.
     *
     * @var list<string>
     */
    private const FACTION_TYPES = ['unit', 'detachment', 'enhancement'];

    /**
     * What is left to translate and to check, by faction and type.
     */
    public function overview(): View
    {
        $countsByType = collect(self::FACTION_TYPES)->mapWithKeys(fn (string $type): array => [$type => $this->statusCounts($type, byFaction: true)]);

        $rows = Faction::query()->with('parent')->get()
            ->map(function (Faction $faction) use ($countsByType): array {
                $cells = $countsByType->map(fn (Collection $counts): array => $this->bucket($counts->get($faction->id)))->all();

                return [
                    'faction' => $faction,
                    'cells' => $cells,
                    'remaining' => collect($cells)->sum(fn (array $cell): int => $cell['missing'] + $cell['review']),
                ];
            })
            ->sortBy([['remaining', 'desc'], fn (array $a, array $b): int => strcmp($a['faction']->fullName(), $b['faction']->fullName())])
            ->values();

        $totals = collect(ReferenceTranslations::TYPES)
            ->keys()
            ->mapWithKeys(fn (string $type): array => [$type => $this->bucket($this->statusCounts($type)->first())]);

        return view('translations.overview', [
            'rows' => $rows,
            'totals' => $totals,
            'grandTotal' => [
                'missing' => $totals->sum('missing'),
                'review' => $totals->sum('review'),
                'validated' => $totals->sum('validated'),
                'total' => $totals->sum('total'),
            ],
        ]);
    }

    /**
     * Working list of one type, filtered by status, faction and English or French name.
     */
    public function index(Request $request): View
    {
        $type = array_key_exists($request->query('type'), ReferenceTranslations::TYPES) ? $request->query('type') : 'unit';
        $status = in_array($request->query('status'), ['missing', 'review', 'validated', 'all'], true) ? $request->query('status') : 'missing';
        $factionId = $request->integer('faction') ?: null;
        $search = trim((string) $request->query('q'));

        $records = ReferenceTranslations::TYPES[$type]::query()
            ->with(array_filter(['translationReview', $type === 'enhancement' ? 'detachment' : null]))
            ->when($status === 'missing', fn (Builder $query) => $query->whereNull('name->fr'))
            ->when($status === 'review', fn (Builder $query) => $query->whereHas('translationReview'))
            ->when($status === 'validated', fn (Builder $query) => $query->whereNotNull('name->fr')->whereDoesntHave('translationReview'))
            ->when($factionId && in_array($type, ['unit', 'detachment'], true), fn (Builder $query) => $query->where('faction_id', $factionId))
            ->when($factionId && $type === 'enhancement', fn (Builder $query) => $query->whereHas('detachment', fn (Builder $query) => $query->where('faction_id', $factionId)))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) COLLATE utf8mb4_unicode_ci LIKE ?", ['%'.$search.'%'])
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.fr')) COLLATE utf8mb4_unicode_ci LIKE ?", ['%'.$search.'%'])))
            ->orderBy('name->en')
            ->paginate(50)
            ->withQueryString();

        return view('translations.index', [
            'records' => $records,
            'type' => $type,
            'status' => $status,
            'factionId' => $factionId,
            'search' => $search,
            'factionOptions' => Faction::selectOptions(),
        ]);
    }

    /**
     * Save one French name: validated, still to check, or removed when left empty.
     * Answers in JSON to the list, which saves without reloading.
     */
    public function update(Request $request, ReferenceTranslations $translations, string $type, int $id): JsonResponse|RedirectResponse
    {
        $modelClass = ReferenceTranslations::TYPES[$type] ?? abort(404);
        $record = $modelClass::query()->findOrFail($id);

        $validated = $request->validate([
            'fr' => ['nullable', 'string', 'max:255'],
            'verified' => ['boolean'],
        ]);

        if (blank($validated['fr'] ?? null)) {
            $record->update(['name' => array_diff_key($record->name->toArray(), ['fr' => true])]);
            $record->markTranslationToReview(false);
        } else {
            $translations->apply($record, $validated['fr'], ! $request->boolean('verified'));
        }

        $record->refresh();
        $status = $this->statusOf($record);

        if ($request->expectsJson()) {
            return response()->json([
                'fr' => $record->name->has('fr') ? $record->name->get('fr') : '',
                'status' => $status,
                'statusLabel' => self::STATUS_LABELS[$status],
            ]);
        }

        return back()->with('status', "« {$record->name->get('en')} » enregistré.");
    }

    /**
     * Validate the French names of the selected records (records without a French name are skipped).
     */
    public function validateSelection(Request $request, string $type): RedirectResponse
    {
        $modelClass = ReferenceTranslations::TYPES[$type] ?? abort(404);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $ids = $modelClass::query()->whereKey($validated['ids'])->whereNotNull('name->fr')->pluck('id');
        $count = TranslationReview::query()->where('translatable_type', $type)->whereIn('translatable_id', $ids)->delete();

        return back()->with('status', "{$count} traduction(s) validée(s).");
    }

    /**
     * @return 'missing'|'review'|'validated'
     */
    private function statusOf(object $record): string
    {
        return match (true) {
            ! $record->name->has('fr') => 'missing',
            $record->translationReview()->exists() => 'review',
            default => 'validated',
        };
    }

    /**
     * Number of records, of names without French and of names to check, for a type (optionally by faction).
     *
     * @return Collection<int|string, object{total: int, missing: int, review: int}>
     */
    private function statusCounts(string $type, bool $byFaction = false): Collection
    {
        $table = (new (ReferenceTranslations::TYPES[$type]))->getTable();
        $factionColumn = $type === 'enhancement' ? 'detachments.faction_id' : "{$table}.faction_id";
        // Raw SQL must name the real tables, including the configured prefix (e.g. "w40k_" in production).
        $prefix = DB::getTablePrefix();

        return DB::table($table)
            ->when($type === 'enhancement', fn ($query) => $query->join('detachments', 'detachments.id', '=', 'enhancements.detachment_id'))
            ->when($byFaction, fn ($query) => $query->selectRaw("{$prefix}{$factionColumn} as faction_id")->groupBy($factionColumn))
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(JSON_EXTRACT({$prefix}{$table}.name, '$.fr') IS NULL) as missing")
            ->selectRaw("SUM(EXISTS(SELECT 1 FROM {$prefix}translation_reviews WHERE {$prefix}translation_reviews.translatable_type = ? AND {$prefix}translation_reviews.translatable_id = {$prefix}{$table}.id)) as review", [$type])
            ->get()
            ->keyBy(fn (object $row): int|string => $byFaction ? $row->faction_id : 'all');
    }

    /**
     * @return array{total: int, missing: int, review: int, validated: int}
     */
    private function bucket(?object $counts): array
    {
        $total = (int) ($counts->total ?? 0);
        $missing = (int) ($counts->missing ?? 0);
        $review = (int) ($counts->review ?? 0);

        return ['total' => $total, 'missing' => $missing, 'review' => $review, 'validated' => max(0, $total - $missing - $review)];
    }
}
