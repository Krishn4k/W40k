<?php

namespace Tests\Unit;

use App\Enums\PaintStatus;
use PHPUnit\Framework\TestCase;

class PaintStatusTest extends TestCase
{
    public function test_statuses_follow_the_painting_progression(): void
    {
        $this->assertSame(
            [PaintStatus::OnSprue, PaintStatus::Assembled, PaintStatus::Primed, PaintStatus::Painted, PaintStatus::Finished],
            PaintStatus::cases(),
        );
        $this->assertSame(0, PaintStatus::OnSprue->rank());
        $this->assertSame(4, PaintStatus::Finished->rank());
    }

    public function test_is_at_least_compares_against_a_threshold(): void
    {
        $this->assertTrue(PaintStatus::Painted->isAtLeast(PaintStatus::Assembled));
        $this->assertTrue(PaintStatus::Assembled->isAtLeast(PaintStatus::Assembled));
        $this->assertFalse(PaintStatus::OnSprue->isAtLeast(PaintStatus::Assembled));
    }
}
