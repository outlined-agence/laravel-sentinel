<?php

declare(strict_types=1);

namespace Outlined\Sentinel;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Outlined\Sentinel\Commands\CheckResourcesCommand;
use Outlined\Sentinel\Commands\PruneEventsCommand;
use Outlined\Sentinel\Commands\TestMonitoringCommand;
use Outlined\Sentinel\Contracts\MetricsCollector;
use Outlined\Sentinel\Logging\DiscordLogger;
use Outlined\Sentinel\Logging\SlackLogger;
use Outlined\Sentinel\Metrics\NullMetricsCollector;
use Outlined\Sentinel\Metrics\PrometheusCollector;
use Outlined\Sentinel\Metrics\StatsdCollector;
use Outlined\Sentinel\Resources\ResourceChecker;
use Outlined\Sentinel\Services\MonitoringService;
use Outlined\Sentinel\Filament\FilamentVersion;
use Outlined\Sentinel\Support\AlertDeduplicator;
use Outlined\Sentinel\Support\ContextBuilder;
use Outlined\Sentinel\Support\ContextSanitizer;
use Outlined\Sentinel\Support\RateLimiter;
use Livewire\Livewire;

class SentinelServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sentinel.php', 'sentinel');

        // Register ContextBuilder
        $this->app->singleton(ContextBuilder::class, function ($app) {
            return new ContextBuilder($app['request'] ?? null);
        });

        // Register AlertDeduplicator
        $this->app->singleton(AlertDeduplicator::class);

        // Register RateLimiter
        $this->app->singleton(RateLimiter::class);

        // Register ContextSanitizer
        $this->app->singleton(ContextSanitizer::class);

        // Register MetricsCollector
        $this->app->singleton(MetricsCollector::class, function () {
            if (! config('sentinel.metrics.enabled')) {
                return new NullMetricsCollector();
            }

            $driver = config('sentinel.metrics.driver', 'prometheus');

            return match ($driver) {
                'prometheus' => new PrometheusCollector(),
                'statsd' => new StatsdCollector(),
                default => new NullMetricsCollector(),
            };
        });

        // Register MonitoringService as singleton
        $this->app->singleton(MonitoringService::class, function ($app) {
            $service = new MonitoringService(
                $app[ContextBuilder::class],
                $app[ContextSanitizer::class],
            );
            $service->setMetricsCollector($app[MetricsCollector::class]);

            return $service;
        });

        // Register ResourceChecker
        $this->app->singleton(ResourceChecker::class, function ($app) {
            return new ResourceChecker($app[MonitoringService::class]);
        });

        // Register alias for app('sentinel')
        $this->app->alias(MonitoringService::class, 'sentinel');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPublishables();
        $this->registerCommands();
        $this->registerLogChannels();
        $this->registerViews();
        $this->registerFilament();
        $this->validateConfig();
    }

    /**
     * Register publishable resources.
     */
    protected function registerPublishables(): void
    {
        if ($this->app->runningInConsole()) {
            // Config
            $this->publishes([
                __DIR__ . '/../config/sentinel.php' => config_path('sentinel.php'),
            ], 'sentinel-config');

            // Migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'sentinel-migrations');

            // All assets
            $this->publishes([
                __DIR__ . '/../config/sentinel.php' => config_path('sentinel.php'),
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'sentinel');
        }
    }

    /**
     * Register Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TestMonitoringCommand::class,
                CheckResourcesCommand::class,
                PruneEventsCommand::class,
            ]);
        }
    }

    /**
     * Register views.
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'sentinel');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/sentinel'),
            ], 'sentinel-views');
        }
    }

    /**
     * Register custom log channels.
     */
    protected function registerLogChannels(): void
    {
        // Register Slack channel
        $this->app['config']->set('logging.channels.sentinel-slack', [
            'driver' => 'custom',
            'via' => SlackLogger::class,
            'level' => config('sentinel.slack.level', 'debug'),
        ]);

        // Register Discord channel
        $this->app['config']->set('logging.channels.sentinel-discord', [
            'driver' => 'custom',
            'via' => DiscordLogger::class,
            'level' => config('sentinel.discord.level', 'debug'),
        ]);
    }

    /**
     * Register Filament resources if Filament is installed.
     */
    protected function registerFilament(): void
    {
        if (! config('sentinel.filament.enabled', true)) {
            return;
        }

        // Check if Filament is installed
        if (! class_exists(\Filament\FilamentServiceProvider::class)) {
            return;
        }

        // Check if Livewire is available
        if (! class_exists(Livewire::class)) {
            return;
        }

        // Register Livewire components for widgets
        $this->registerLivewireComponents();
    }

    /**
     * Register Livewire components for Filament widgets.
     */
    protected function registerLivewireComponents(): void
    {
        if (FilamentVersion::isV4()) {
            Livewire::component('outlined.sentinel.filament.widgets.v4.stats-overview', \Outlined\Sentinel\Filament\Widgets\V4\StatsOverview::class);
            Livewire::component('outlined.sentinel.filament.widgets.v4.events-by-level-chart', \Outlined\Sentinel\Filament\Widgets\V4\EventsByLevelChart::class);
            Livewire::component('outlined.sentinel.filament.widgets.v4.events-over-time-chart', \Outlined\Sentinel\Filament\Widgets\V4\EventsOverTimeChart::class);
            Livewire::component('outlined.sentinel.filament.widgets.v4.recent-events-table', \Outlined\Sentinel\Filament\Widgets\V4\RecentEventsTable::class);
        } else {
            Livewire::component('outlined.sentinel.filament.widgets.v3.stats-overview', \Outlined\Sentinel\Filament\Widgets\V3\StatsOverview::class);
            Livewire::component('outlined.sentinel.filament.widgets.v3.events-by-level-chart', \Outlined\Sentinel\Filament\Widgets\V3\EventsByLevelChart::class);
            Livewire::component('outlined.sentinel.filament.widgets.v3.events-over-time-chart', \Outlined\Sentinel\Filament\Widgets\V3\EventsOverTimeChart::class);
            Livewire::component('outlined.sentinel.filament.widgets.v3.recent-events-table', \Outlined\Sentinel\Filament\Widgets\V3\RecentEventsTable::class);
        }
    }

    /**
     * Validate configuration and warn about common misconfigurations.
     */
    protected function validateConfig(): void
    {
        if (! config('sentinel.enabled')) {
            return;
        }

        if (config('sentinel.slack.enabled') && empty(config('sentinel.slack.webhook_url'))) {
            Log::warning('[Sentinel] Slack is enabled but SENTINEL_SLACK_WEBHOOK is not set. Slack notifications will not be sent.');
        }

        if (config('sentinel.discord.enabled') && empty(config('sentinel.discord.webhook_url'))) {
            Log::warning('[Sentinel] Discord is enabled but SENTINEL_DISCORD_WEBHOOK is not set. Discord notifications will not be sent.');
        }
    }
}
