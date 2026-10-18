<?php

namespace App\Http\Controllers;

use App\Enums\UnitRole;
use App\Http\Requests\UnitRequest;
use App\Models\Faction;
use App\Models\Keyword;
use App\Models\PointSet;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UnitController extends Controller
{
    /**
     * List units, optionally filtered by faction (own units only) and role.
     */
    public function index(Request $request): View
    {
        $role = UnitRole::tryFrom((string) $request->query('role'));
        $factionId = $request->integer('faction') ?: null;
        $search = trim((string) $request->query('q'));
        $withLegends = $request->boolean('legendes');

        $units = Unit::query()
            ->when($factionId, fn ($query) => $query->where('faction_id', $factionId))
            ->when($role, fn ($query) => $query->where('role', $role))
            ->listingFilters($search, $withLegends)
            ->withListingData(PointSet::current())
            ->orderByName()
            ->paginate(50)
            ->withQueryString();

        return view('units.index', [
            'units' => $units,
            'factionOptions' => $this->factionOptions(),
            'selectedFactionId' => $factionId,
            'selectedRole' => $role,
            'search' => $search,
            'withLegends' => $withLegends,
        ]);
    }

    public function create(Request $request): View
    {
        return view('units.create', $this->formData(new Unit([
            'faction_id' => $request->integer('faction_id') ?: null,
            'role' => UnitRole::Other,
        ])));
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        $unit = DB::transaction(fn (): Unit => $this->save(new Unit, $request));

        return redirect()->route('factions.show', $unit->faction_id)->with('status', "Unité « {$unit->name} » créée.");
    }

    public function edit(Unit $unit): View
    {
        return view('units.edit', $this->formData($unit));
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        DB::transaction(fn (): Unit => $this->save($unit, $request));

        return redirect()->route('factions.show', $unit->faction_id)->with('status', "Unité « {$unit->name} » mise à jour.");
    }

    /**
     * Delete a unit, only when no collection lot or army list references it.
     */
    public function destroy(Unit $unit): RedirectResponse
    {
        if ($unit->isInUse()) {
            return back()->withErrors(['unit' => "Impossible de supprimer « {$unit->name} » : elle est utilisée dans une collection ou une liste d'armée."]);
        }

        $unit->delete();

        return redirect()->route('factions.show', $unit->faction_id)->with('status', "Unité « {$unit->name} » supprimée.");
    }

    /**
     * Save the unit, its keywords (found by English name or created) and its costs in the current point set.
     */
    private function save(Unit $unit, UnitRequest $request): Unit
    {
        $unit->fill([
            ...$request->safe()->only(['faction_id', 'name', 'role']),
            'is_epic_hero' => $request->boolean('is_epic_hero'),
        ])->save();

        $unit->keywords()->sync(collect($request->keywordNames())
            ->map(fn (string $name): int => $this->findOrCreateKeyword($name)->id)
            ->unique()
            ->all());

        $pointSet = PointSet::current();
        if ($pointSet) {
            $unit->pointCosts()->whereBelongsTo($pointSet)->delete();
            $unit->pointCosts()->createMany(collect($request->validated('costs', []))
                ->map(fn (array $cost): array => [...$cost, 'point_set_id' => $pointSet->id])
                ->all());
        }

        return $unit;
    }

    /**
     * Find a keyword by its English name, case-insensitively, or create it.
     */
    private function findOrCreateKeyword(string $englishName): Keyword
    {
        return Keyword::query()->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))) = ?", [mb_strtolower($englishName)])->first()
            ?? Keyword::create(['name' => ['en' => $englishName]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Unit $unit): array
    {
        $pointSet = PointSet::current();

        return [
            'unit' => $unit,
            'factionOptions' => $this->factionOptions(),
            'pointSet' => $pointSet,
            'costs' => $unit->exists && $pointSet
                ? $unit->pointCosts()->whereBelongsTo($pointSet)->orderBy('model_count')->get(['model_count', 'points'])->toArray()
                : [],
        ];
    }

    /**
     * Factions as select options, sub-factions shown under their parent.
     *
     * @return array<int, string>
     */
    private function factionOptions(): array
    {
        return Faction::query()
            ->with('parent')
            ->get()
            ->mapWithKeys(fn (Faction $faction): array => [$faction->id => $faction->fullName()])
            ->sort()
            ->all();
    }
}
