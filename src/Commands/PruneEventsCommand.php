<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Commands;

use Illuminate\Console\Command;
use Outlined\Sentinel\Models\SentinelEvent;

class PruneEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sentinel:prune
        {--days= : Number of days to retain events (overrides config)}
        {--dry-run : Show how many events would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove old monitoring events from the database';

    public function handle(): int
    {
        if (! config('sentinel.database.enabled')) {
            $this->warn('Database storage is not enabled. Nothing to prune.');

            return self::SUCCESS;
        }

        $days = $this->option('days') ?? config('sentinel.database.retention_days', 30);
        $dryRun = $this->option('dry-run');

        $cutoff = now()->subDays((int) $days);

        $count = SentinelEvent::where('created_at', '<=', $cutoff)->count();

        if ($count === 0) {
            $this->info('No events to prune.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Would delete {$count} events older than {$days} days.");

            return self::SUCCESS;
        }

        $this->info("Pruning {$count} events older than {$days} days...");

        // Delete in batches by ID to avoid memory issues
        $deleted = 0;
        do {
            $ids = SentinelEvent::where('created_at', '<=', $cutoff)
                ->limit(1000)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $batch = SentinelEvent::whereIn('id', $ids)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Successfully pruned {$deleted} events.");

        return self::SUCCESS;
    }
}
