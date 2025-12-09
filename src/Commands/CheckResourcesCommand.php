<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Commands;

use Illuminate\Console\Command;
use Outlined\Sentinel\Resources\ResourceChecker;
use Outlined\Sentinel\Resources\ResourceStatus;

class CheckResourcesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sentinel:check-resources
        {--resource= : Check a specific resource by identifier}
        {--no-alert : Check without sending alerts}
        {--json : Output results as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all registered monitoring resources and send alerts if thresholds are exceeded';

    public function handle(ResourceChecker $checker): int
    {
        // Register resources from config
        $checker->registerFromConfig(config('sentinel.resources', []));

        $resources = $checker->getResources();

        if (empty($resources)) {
            if ($this->option('json')) {
                $this->line(json_encode(['status' => 'no_resources', 'message' => 'No resources registered']));
            } else {
                $this->warn('No resources registered for monitoring.');
                $this->line('Register resources in config/sentinel.php under the "resources" key.');
            }

            return self::SUCCESS;
        }

        $specificResource = $this->option('resource');
        $noAlert = $this->option('no-alert');
        $asJson = $this->option('json');

        if ($specificResource) {
            return $this->checkSingleResource($checker, $specificResource, $noAlert, $asJson);
        }

        return $this->checkAllResources($checker, $noAlert, $asJson);
    }

    protected function checkSingleResource(ResourceChecker $checker, string $identifier, bool $noAlert, bool $asJson): int
    {
        if (! $checker->hasResource($identifier)) {
            if ($asJson) {
                $this->line(json_encode(['status' => 'error', 'message' => "Resource '{$identifier}' not found"]));
            } else {
                $this->error("Resource '{$identifier}' not found.");
            }

            return self::FAILURE;
        }

        $resource = $checker->getResource($identifier);

        if ($noAlert) {
            $status = $resource->check();
        } else {
            $status = $checker->check($resource);
        }

        if ($asJson) {
            $this->line(json_encode([
                'resource' => $identifier,
                'status' => $status,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->outputStatus($identifier, $resource->getName(), $status);
        }

        return $status->isHealthy() ? self::SUCCESS : self::FAILURE;
    }

    protected function checkAllResources(ResourceChecker $checker, bool $noAlert, bool $asJson): int
    {
        if ($noAlert) {
            $results = $checker->status();
        } else {
            $results = $checker->checkAll();
        }

        if ($asJson) {
            $this->line(json_encode([
                'checked_at' => now()->toIso8601String(),
                'resources' => $results,
            ], JSON_PRETTY_PRINT));

            $hasUnhealthy = collect($results)->contains(fn (ResourceStatus $s) => ! $s->isHealthy());

            return $hasUnhealthy ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Checking all registered resources...');
        $this->newLine();

        $hasUnhealthy = false;
        $rows = [];

        foreach ($results as $identifier => $status) {
            $resource = $checker->getResource($identifier);
            $name = $resource?->getName() ?? $identifier;

            if (! $status->isHealthy()) {
                $hasUnhealthy = true;
            }

            $levelIcon = $this->getLevelIcon($status->getLevel());
            $rows[] = [
                $identifier,
                $name,
                "{$levelIcon} " . ucfirst($status->getLevel()),
                $status->value,
                $status->message ?: '-',
            ];
        }

        $this->table(
            ['Identifier', 'Name', 'Status', 'Value', 'Message'],
            $rows
        );

        $this->newLine();

        if ($hasUnhealthy) {
            $this->warn('Some resources are in warning or critical state.');

            return self::FAILURE;
        }

        $this->info('All resources are healthy.');

        return self::SUCCESS;
    }

    protected function outputStatus(string $identifier, string $name, ResourceStatus $status): void
    {
        $level = $status->getLevel();
        $icon = $this->getLevelIcon($level);

        $this->line("{$icon} {$name} ({$identifier})");
        $this->line("   Status: {$level}");
        $this->line("   Value: {$status->value}");
        $this->line("   Warning Threshold: {$status->warningThreshold}");
        $this->line("   Critical Threshold: {$status->criticalThreshold}");

        if ($status->message) {
            $this->line("   Message: {$status->message}");
        }

        $this->newLine();
    }

    protected function getLevelIcon(string $level): string
    {
        return match ($level) {
            'info' => '<fg=green>✓</>',
            'warning' => '<fg=yellow>⚠</>',
            'critical', 'error' => '<fg=red>✗</>',
            default => '•',
        };
    }
}
