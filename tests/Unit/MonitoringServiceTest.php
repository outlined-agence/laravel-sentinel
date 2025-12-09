<?php

declare(strict_types=1);

use Outlined\Sentinel\Services\MonitoringService;
use Outlined\Sentinel\Support\ContextBuilder;

beforeEach(function () {
    $this->contextBuilder = new ContextBuilder();
    $this->service = new MonitoringService($this->contextBuilder);
});

it('can be instantiated', function () {
    expect($this->service)->toBeInstanceOf(MonitoringService::class);
});

it('is enabled by default', function () {
    expect($this->service->isEnabled())->toBeTrue();
});

it('can be disabled and enabled', function () {
    $this->service->disable();
    expect($this->service->isEnabled())->toBeFalse();

    $this->service->enable();
    expect($this->service->isEnabled())->toBeTrue();
});

it('can execute callback without monitoring', function () {
    $result = $this->service->withoutMonitoring(function () {
        return 'test';
    });

    expect($result)->toBe('test');
    expect($this->service->isEnabled())->toBeTrue();
});

it('can log an error', function () {
    $exception = new RuntimeException('Test error');

    // Should not throw
    $this->service->logError($exception);
})->throwsNoExceptions();

it('can log a business event', function () {
    // Should not throw
    $this->service->logBusinessEvent(
        type: 'payment',
        success: false,
        message: 'Payment failed',
        additionalContext: ['order_id' => 123]
    );
})->throwsNoExceptions();

it('can log a provider error', function () {
    // Should not throw
    $this->service->logProviderError(
        provider: 'Stripe',
        message: 'Card declined',
        data: ['customer_id' => 456]
    );
})->throwsNoExceptions();

it('can log a threshold alert', function () {
    // Should not throw
    $this->service->logThresholdAlert(
        type: 'balance',
        message: 'Low balance warning',
        currentValue: 50.00,
        threshold: 100.00,
        critical: false
    );
})->throwsNoExceptions();

it('does not log when disabled', function () {
    config(['sentinel.enabled' => false]);

    $service = new MonitoringService($this->contextBuilder);

    expect($service->isEnabled())->toBeFalse();
});
