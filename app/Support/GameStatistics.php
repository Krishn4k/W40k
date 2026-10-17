<?php

namespace App\Support;

use App\Enums\GameResult;
use App\Models\Game;
use Illuminate\Support\Collection;

/**
 * Win / draw / loss tallies of a set of games, overall and grouped by list, faction, detachment or opponent.
 *
 * @phpstan-type Tally array{games: int, wins: int, draws: int, losses: int, winRate: int}
 */
final class GameStatistics
{
    /**
     * @param  Collection<int, Game>  $games  Games with their factions (and parents), detachment and opponent faction loaded.
     */
    public function __construct(private readonly Collection $games) {}

    /**
     * @return Tally
     */
    public function overall(): array
    {
        return $this->tally($this->games);
    }

    /**
     * @return array<string, Tally>
     */
    public function byList(): array
    {
        return $this->groupedBy(fn (Game $game): string => $game->list_name ?? 'Sans liste');
    }

    /**
     * @return array<string, Tally>
     */
    public function byFaction(): array
    {
        return $this->groupedBy(fn (Game $game): string => $game->faction?->fullName() ?? 'Non renseignée');
    }

    /**
     * @return array<string, Tally>
     */
    public function byDetachment(): array
    {
        return $this->groupedBy(fn (Game $game): string => $game->detachment ? (string) $game->detachment->name : 'Non renseigné');
    }

    /**
     * @return array<string, Tally>
     */
    public function byOpponentFaction(): array
    {
        return $this->groupedBy(fn (Game $game): string => $game->opponentFaction?->fullName() ?? 'Non renseignée');
    }

    /**
     * Groups sorted by number of games, then by name.
     *
     * @param  callable(Game): string  $key
     * @return array<string, Tally>
     */
    private function groupedBy(callable $key): array
    {
        return $this->games
            ->groupBy($key)
            ->map(fn (Collection $games): array => $this->tally($games))
            ->sortKeys()
            // Sorting is stable: groups with as many games stay in name order.
            ->sortByDesc('games')
            ->all();
    }

    /**
     * @param  Collection<int, Game>  $games
     * @return Tally
     */
    private function tally(Collection $games): array
    {
        $count = $games->count();
        $wins = $games->where('result', GameResult::Win)->count();

        return [
            'games' => $count,
            'wins' => $wins,
            'draws' => $games->where('result', GameResult::Draw)->count(),
            'losses' => $games->where('result', GameResult::Loss)->count(),
            'winRate' => $count > 0 ? intdiv($wins * 100, $count) : 0,
        ];
    }
}
