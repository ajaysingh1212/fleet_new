<?php

namespace App\Console\Commands;

use App\Services\Gps\HardwareWorkerPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;

class SuperviseGpsWorkers extends Command
{
    protected $signature = 'gps:workers {--max=4 : Maximum workers per queue} {--jobs-per-worker=20} {--interval=2} {--once : Print demand without spawning workers}';

    protected $description = 'Keep GPS workers alive and add bounded workers when live/history/broadcast queues grow';

    public function handle(): int
    {
        $max = filter_var($this->option('max'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 16]]);
        $batch = filter_var($this->option('jobs-per-worker'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        $interval = filter_var($this->option('interval'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 30]]);
        if (! $max || ! $batch || ! $interval) {
            $this->error('Use --max=1..16, --jobs-per-worker=1..1000 and --interval=1..30.');

            return self::FAILURE;
        }
        $queues = [
            [config('gps.live_connection'), config('gps.live_queue')],
            [config('gps.live_connection'), config('gps.persistence_queue')],
            [config('gps.broadcast_connection'), config('gps.broadcast_queue')],
        ];
        foreach ($queues as [$connection]) {
            if (! in_array(config('queue.connections.'.$connection.'.driver'), ['database', 'redis'], true)) {
                $this->error('GPS workers require a database or Redis queue.');

                return self::FAILURE;
            }
        }
        $lease = $this->option('once') ? null : fopen(storage_path('framework/gps-workers.lock'), 'c');
        if (! $this->option('once') && (! $lease || ! flock($lease, LOCK_EX | LOCK_NB))) {
            $this->error('The GPS worker supervisor is already running.');

            return self::FAILURE;
        }
        $workers = [];
        $stopping = false;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function () use (&$stopping) {
                $stopping = true;
            });
            pcntl_signal(SIGINT, function () use (&$stopping) {
                $stopping = true;
            });
        }
        try {
            do {
                $status = [];
                foreach ($queues as [$connection, $queue]) {
                    $key = $connection.':'.$queue;
                    $workers[$key] ??= [];
                    foreach ($workers[$key] as $id => $process) {
                        if (! $process->isRunning()) {
                            if ($process->getExitCode() !== 0) {
                                $this->warn($key.' worker exited; replacing it.');
                            }
                            unset($workers[$key][$id]);
                        } else {
                            $process->clearOutput();
                            $process->clearErrorOutput();
                        }
                    }
                    $pending = Queue::connection($connection)->size($queue);
                    $oldest = 0;
                    if (config('queue.connections.'.$connection.'.driver') === 'database') {
                        $settings = config('queue.connections.'.$connection);
                        $created = DB::connection($settings['connection'] ?? null)->table($settings['table'] ?? 'jobs')
                            ->where('queue', $queue)->whereNull('reserved_at')->min('created_at');
                        $oldest = $created ? max(0, time() - $created) : 0;
                    }
                    $desired = HardwareWorkerPlan::desired($pending, $max, $batch, $oldest);
                    if (! $this->option('once')) {
                        while (count($workers[$key]) < $desired) {
                            $burst = count($workers[$key]) > 0;
                            $arguments = [PHP_BINARY, base_path('artisan'), 'queue:work', $connection,
                                '--queue='.$queue, '--sleep=0.1', '--tries=5', '--backoff=3', '--timeout=30',
                                '--max-time='.($burst ? 60 : 300), '--max-jobs=500'];
                            if ($burst) {
                                $arguments[] = '--stop-when-empty';
                            }
                            $process = new Process($arguments, base_path());
                            $process->setTimeout(null);
                            $process->start(function ($type, $output) use ($queue) {
                                file_put_contents(storage_path('logs/gps-pool-'.$queue.'.log'), $output, FILE_APPEND | LOCK_EX);
                            });
                            $workers[$key][] = $process;
                            $this->info('Started '.$key.' worker PID '.$process->getPid());
                        }
                    }
                    $status[] = ['queue' => $queue, 'connection' => $connection, 'pending' => $pending,
                        'oldest_seconds' => $oldest, 'desired' => $desired, 'running' => count($workers[$key]),
                        'pids' => array_values(array_map(fn ($process) => $process->getPid(), $workers[$key]))];
                }
                if ($this->option('once')) {
                    $this->line(json_encode($status, JSON_THROW_ON_ERROR));
                } else {
                    $target = storage_path('framework/gps-workers-status.json');
                    file_put_contents($target.'.tmp', json_encode(['updated_at' => now()->utc()->toIso8601String(), 'queues' => $status], JSON_THROW_ON_ERROR));
                    rename($target.'.tmp', $target);
                    usleep($interval * 1000000);
                }
            } while (! $stopping && ! $this->option('once'));
        } finally {
            foreach ($workers as $pool) {
                foreach ($pool as $process) {
                    $process->stop(5);
                }
            }
            if (is_resource($lease)) {
                flock($lease, LOCK_UN);
                fclose($lease);
            }
        }

        return self::SUCCESS;
    }
}
