<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Outlined\Sentinel\Events\MonitoringEventLogged;
use Outlined\Sentinel\Facades\Sentinel;
use Outlined\Sentinel\Models\SentinelEvent;
use Outlined\Sentinel\Services\MonitoringService;

beforeEach(function () {
    config(['sentinel.enabled' => true]);
    config(['sentinel.slack.enabled' => false]);
    config(['sentinel.discord.enabled' => false]);
    config(['sentinel.database.enabled' => true]);
});

it('registers service provider correctly', function () {
    expect(app()->bound(MonitoringService::class))->toBeTrue();
});

it('resolves monitoring service as singleton', function () {
    $instance1 = app(MonitoringService::class);
    $instance2 = app(MonitoringService::class);

    expect($instance1)->toBe($instance2);
});

it('facade resolves to monitoring service', function () {
    expect(Sentinel::getFacadeRoot())->toBeInstanceOf(MonitoringService::class);
});

it('logs error to database when enabled', function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

    $exception = new RuntimeException('Test error');

    Sentinel::logError($exception);

    expect(SentinelEvent::count())->toBe(1);

    $event = SentinelEvent::first();
    expect($event->level)->toBe('error');
    expect($event->message)->toBe('Test error');
    expect($event->event_type)->toBe('error');
});

it('logs business event to database', function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

    Sentinel::logBusinessEvent(
        type: 'payment',
        success: false,
        message: 'Payment failed',
        additionalContext: ['order_id' => 123]
    );

    $event = SentinelEvent::first();
    expect($event->level)->toBe('error');
    expect($event->event_type)->toBe('business_payment');
    expect($event->context['data']['order_id'])->toBe(123);
});

it('logs provider error to database', function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

    Sentinel::logProviderError('Stripe', 'Card declined', ['customer_id' => 456]);

    $event = SentinelEvent::first();
    expect($event->level)->toBe('error');
    expect($event->message)->toBe('[Stripe] Card declined');
    expect($event->context['data']['provider'])->toBe('Stripe');
});

it('logs threshold alert to database', function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

    Sentinel::logThresholdAlert(
        type: 'balance',
        message: 'Low balance warning',
        currentValue: 50.00,
        threshold: 100.00
    );

    $event = SentinelEvent::first();
    expect($event->level)->toBe('warning');
    expect((float) $event->context['data']['current_value'])->toBe(50.00);
    expect((float) $event->context['data']['threshold'])->toBe(100.00);
});

it('dispatches event when logging', function () {
    Event::fake([MonitoringEventLogged::class]);

    Sentinel::log('info', 'Test message');

    Event::assertDispatched(MonitoringEventLogged::class, function ($event) {
        return $event->level === 'info' && $event->message === 'Test message';
    });
});

it('does not log when disabled', function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

    Sentinel::disable();
    Sentinel::log('error', 'Should not be logged');

    expect(SentinelEvent::count())->toBe(0);
});

it('can use withoutMonitoring', function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

    Sentinel::withoutMonitoring(function () {
        Sentinel::log('error', 'Should not be logged');
    });

    expect(SentinelEvent::count())->toBe(0);
    expect(Sentinel::isEnabled())->toBeTrue();
});
