<?php

namespace Tests\Feature;

use App\Enums\GameResult;
use App\Models\Faction;
use App\Models\Game;
use App\Models\User;
use App\Support\GameStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_tallies_results_overall_and_by_group(): void
    {
        $user = User::factory()->create();
        $spaceMarines = Faction::factory()->create(['name' => ['en' => 'Space Marines']]);
        $orks = Faction::factory()->create(['name' => ['en' => 'Orks']]);

        foreach ([
            [GameResult::Win, 'Liste A', $orks],
            [GameResult::Win, 'Liste A', $orks],
            [GameResult::Loss, 'Liste A', null],
            [GameResult::Draw, 'Liste B', $orks],
        ] as [$result, $listName, $opponentFaction]) {
            Game::factory()->for($user)->create([
                'result' => $result,
                'list_name' => $listName,
                'faction_id' => $spaceMarines->id,
                'opponent_faction_id' => $opponentFaction?->id,
            ]);
        }

        $statistics = new GameStatistics($user->games()->with(['faction.parent', 'detachment', 'opponentFaction.parent'])->get());

        $this->assertSame(['games' => 4, 'wins' => 2, 'draws' => 1, 'losses' => 1, 'winRate' => 50], $statistics->overall());
        $this->assertSame(['Liste A', 'Liste B'], array_keys($statistics->byList()));
        $this->assertSame(66, $statistics->byList()['Liste A']['winRate']);
        $this->assertSame(['Orks', 'Non renseignée'], array_keys($statistics->byOpponentFaction()));
        $this->assertSame(['Space Marines' => 4], array_map(fn (array $tally) => $tally['games'], $statistics->byFaction()));
        $this->assertSame(['Non renseigné'], array_keys($statistics->byDetachment()));
    }

    public function test_no_games_gives_a_zero_win_rate(): void
    {
        $this->assertSame(0, (new GameStatistics(collect()))->overall()['winRate']);
    }
}
