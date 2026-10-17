<?php

namespace App\Http\Controllers;

use App\Enums\GameResult;
use App\Http\Requests\GameRequest;
use App\Models\ArmyList;
use App\Models\Faction;
use App\Models\Game;
use App\Support\GameStatistics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GameController extends Controller
{
    /**
     * The user's games, most recent first, with their statistics.
     */
    public function index(Request $request): View
    {
        $games = $request->user()->games()
            ->with(['faction.parent', 'detachment', 'opponentFaction.parent'])
            ->latest('played_on')
            ->latest('id')
            ->get();

        return view('games.index', [
            'games' => $games,
            'statistics' => new GameStatistics($games),
        ]);
    }

    public function create(Request $request): View
    {
        return view('games.create', $this->formData($request, new Game([
            'played_on' => today(),
            'army_list_id' => $request->integer('army_list_id') ?: null,
            'result' => GameResult::Win,
        ])));
    }

    public function store(GameRequest $request): RedirectResponse
    {
        $game = new Game(['user_id' => $request->user()->id]);
        $this->save($game, $request);

        return redirect()->route('games.show', $game)->with('status', 'Partie enregistrée.');
    }

    public function show(Game $game): View
    {
        Gate::authorize('view', $game);

        $game->load(['armyList', 'faction.parent', 'detachment', 'opponentFaction.parent']);

        return view('games.show', ['game' => $game]);
    }

    public function edit(Request $request, Game $game): View
    {
        Gate::authorize('update', $game);

        return view('games.edit', $this->formData($request, $game));
    }

    public function update(GameRequest $request, Game $game): RedirectResponse
    {
        $this->save($game, $request);

        return redirect()->route('games.show', $game)->with('status', 'Partie mise à jour.');
    }

    public function destroy(Game $game): RedirectResponse
    {
        Gate::authorize('delete', $game);

        $game->delete();

        return redirect()->route('games.index')->with('status', 'Partie supprimée.');
    }

    /**
     * Save the game; the list copy is taken when a list is chosen or changed, or on request.
     */
    private function save(Game $game, GameRequest $request): void
    {
        $game->fill($request->safe()->except(['army_list_id', 'faction_id', 'refresh_snapshot']));

        $armyListId = $request->validated('army_list_id');
        $listChanged = (int) $armyListId !== (int) $game->army_list_id;

        if ($armyListId && ($listChanged || $request->boolean('refresh_snapshot'))) {
            $game->snapshotArmyList(ArmyList::findOrFail($armyListId));
        } elseif (! $armyListId) {
            $game->fill([
                'army_list_id' => null,
                'list_name' => null,
                'detachment_id' => null,
                'list_points' => null,
                'list_snapshot' => null,
                'faction_id' => $request->validated('faction_id'),
            ]);
        }

        $game->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, Game $game): array
    {
        return [
            'game' => $game,
            'armyLists' => $request->user()->armyLists()->with('faction.parent')->latest('updated_at')->get(),
            'factionOptions' => Faction::query()->with('parent')->get()
                ->mapWithKeys(fn (Faction $faction): array => [$faction->id => $faction->fullName()])
                ->sort()
                ->all(),
        ];
    }
}
