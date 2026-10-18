<?php

namespace Tests\Unit;

use App\Enums\ForceDisposition;
use PHPUnit\Framework\TestCase;

class ForceDispositionTest extends TestCase
{
    public function test_dispositions_are_read_from_their_field_manual_spelling(): void
    {
        $this->assertSame(ForceDisposition::TakeAndHold, ForceDisposition::fromMfm('TAKE AND HOLD'));
        $this->assertSame(ForceDisposition::PurgeTheFoe, ForceDisposition::fromMfm(' Purge the Foe '));
        $this->assertSame('Priority Assets', ForceDisposition::fromMfm('PRIORITY ASSETS')->englishName());
        $this->assertNull(ForceDisposition::fromMfm('UNKNOWN'));
    }

    public function test_every_pair_of_dispositions_gives_each_player_a_primary_mission(): void
    {
        foreach (ForceDisposition::cases() as $disposition) {
            foreach (ForceDisposition::cases() as $opponent) {
                $mission = $disposition->primaryMissionAgainst($opponent);

                $this->assertNotSame('', $mission['en']);
                $this->assertNotSame('', $mission['fr']);
            }
        }

        $this->assertSame('Battlefield Dominance', ForceDisposition::TakeAndHold->primaryMissionAgainst(ForceDisposition::TakeAndHold)['en']);
        $this->assertSame('Immovable Object', ForceDisposition::TakeAndHold->primaryMissionAgainst(ForceDisposition::PurgeTheFoe)['en']);
        $this->assertSame('Unstoppable Force', ForceDisposition::PurgeTheFoe->primaryMissionAgainst(ForceDisposition::TakeAndHold)['en']);
    }
}
