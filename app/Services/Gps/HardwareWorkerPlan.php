<?php

namespace App\Services\Gps;

final class HardwareWorkerPlan
{
    public static function desired(int $pending, int $max, int $jobsPerWorker, float $oldestSeconds = 0): int
    {
        // Keep one worker warm, add bounded concurrency for volume or an aging
        // queue, and retire extra workers between jobs through --max-time.
        return min(max(1, $max), max(1, (int) ceil(max(0, $pending) / max(1, $jobsPerWorker)),
            $pending > 1 && $oldestSeconds >= 2 ? 2 : 1));
    }
}
