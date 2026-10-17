<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faction;
use App\Support\ReferenceTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * French names of the reference data: progress, filters and one-by-one editing.
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

    public function index(Request $request): View
    {
        $type = array_key_exists($request->query('type'), ReferenceTranslations::TYPES) ? $request->query('type') : 'unit';
        $status = in_array($request->query('status'), ['missing', 'review', 'all'], true) ? $request->query('status') : 'missing';
        $factionId = $request->integer('faction') ?: null;
        $search = trim((string) $request->query('q'));

        $records = ReferenceTranslations::TYPES[$type]::query()
            ->with('translationReview')
            ->when($status === 'missing', fn (Builder $query) => $query->whereNull('name->fr'))
            ->when($status === 'review', fn (Builder $query) => $query->whereHas('translationReview'))
            ->when($factionId && in_array($type, ['unit', 'detachment'], true), fn (Builder $query) => $query->where('faction_id', $factionId))
            ->when($factionId && $type === 'enhancement', fn (Builder $query) => $query->whereHas('detachment', fn (Builder $query) => $query->where('faction_id', $factionId)))
            ->when($search !== '', fn (Builder $query) => $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))) LIKE ?", ['%'.mb_strtolower($search).'%']))
            ->orderBy('name->en')
            ->paginate(50)
            ->withQueryString();

        return view('admin.translations.index', [
            'records' => $records,
            'progress' => $this->progress(),
            'type' => $type,
            'status' => $status,
            'factionId' => $factionId,
            'search' => $search,
            'factionOptions' => Faction::query()->with('parent')->get()
                ->mapWithKeys(fn (Faction $faction): array => [$faction->id => $faction->fullName()])
                ->sort()
                ->all(),
        ]);
    }

    /**
     * Save one French name: validated, still to check, or removed when left empty.
     */
    public function update(Request $request, ReferenceTranslations $translations, string $type, int $id): RedirectResponse
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

        return back()->with('status', "« {$record->name->get('en')} » enregistré.");
    }

    /**
     * Number of records, of French names and of names still to check, by type.
     *
     * @return array<string, array{total: int, translated: int, toReview: int}>
     */
    private function progress(): array
    {
        return collect(ReferenceTranslations::TYPES)
            ->map(fn (string $modelClass): array => [
                'total' => $modelClass::query()->count(),
                'translated' => $modelClass::query()->whereNotNull('name->fr')->count(),
                'toReview' => $modelClass::query()->whereHas('translationReview')->count(),
            ])
            ->all();
    }
}
