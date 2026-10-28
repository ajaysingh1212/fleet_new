<?php

namespace Tests\Unit;

use App\Services\Gps\HardwareWorkerPlan;
use PHPUnit\Framework\TestCase;

class HardwareWorkerPlanTest extends TestCase
{
    public function test_keeps_one_warm_worker_and_scales_up_to_a_bounded_limit(): void
    {
        $this->assertSame(1, HardwareWorkerPlan::desired(0, 4, 20));
        $this->assertSame(1, HardwareWorkerPlan::desired(20, 4, 20));
        $this->assertSame(2, HardwareWorkerPlan::desired(21, 4, 20));
        $this->assertSame(4, HardwareWorkerPlan::desired(10000, 4, 20));
        $this->assertSame(2, HardwareWorkerPlan::desired(2, 4, 20, 3));
        $this->assertSame(1, HardwareWorkerPlan::desired(2, 1, 20, 3));
        $this->assertSame(1, HardwareWorkerPlan::desired(0, 4, 20, 100));
    }
}
