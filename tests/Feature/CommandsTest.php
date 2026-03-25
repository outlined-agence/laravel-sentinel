<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Outlined\Sentinel\Models\SentinelEvent;

beforeEach(function () {
    config(['sentinel.enabled' => true]);
    config(['sentinel.slack.enabled' => false]);
    config(['sentinel.discord.enabled' => false]);
    config(['sentinel.database.enabled' => true]);
});

it('can run test monitoring command', function () {
    $this->artisan('sentinel:test')
        ->expectsOutputToContain('Configuration:')
        ->assertFailed(); // Fails because no webhook is configured
});

it('can run check resources command with no resources', function () {
    $this->artisan('sentinel:check-resources')
        ->expectsOutputToContain('No resources registered')
        ->assertSuccessful();
});

it('can run check resources command with json output', function () {
    $this->artisan('sentinel:check-resources', ['--json' => true])
        ->expectsOutputToContain('no_resources')
        ->assertSuccessful();
});

it('can run prune command when database disabled', function () {
    config(['sentinel.database.enabled' => false]);

    $this->artisan('sentinel:prune')
        ->expectsOutputToContain('Database storage is not enabled')
        ->assertSuccessful();
});

it('can run prune command with no events', function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

    $this->artisan('sentinel:prune')
        ->expectsOutputToContain('No events to prune')
        ->assertSuccessful();
});

it('can run prune command with dry run', function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

    // Create an old event (bypass mass assignment for timestamps)
    $event = new SentinelEvent(['level' => 'error', 'message' => 'Old error']);
    $event->created_at = now()->subDays(60);
    $event->updated_at = now()->subDays(60);
    $event->save();

    $this->artisan('sentinel:prune', ['--dry-run' => true])
        ->expectsOutputToContain('Would delete')
        ->assertSuccessful();

    expect(SentinelEvent::count())->toBe(1);
});

it('prunes old events', function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

    // Create old event (bypass mass assignment for timestamps)
    $oldEvent = new SentinelEvent(['level' => 'error', 'message' => 'Old error']);
    $oldEvent->created_at = now()->subDays(60);
    $oldEvent->updated_at = now()->subDays(60);
    $oldEvent->save();

    // Create recent event
    SentinelEvent::create([
        'level' => 'error',
        'message' => 'Recent error',
    ]);

    expect(SentinelEvent::count())->toBe(2);

    $this->artisan('sentinel:prune')
        ->expectsOutputToContain('Successfully pruned')
        ->assertSuccessful();

    expect(SentinelEvent::count())->toBe(1);
    expect(SentinelEvent::first()->message)->toBe('Recent error');
});
