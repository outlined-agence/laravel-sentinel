<?php

declare(strict_types=1);

use Outlined\Sentinel\Resources\ResourceStatus;

it('creates healthy status', function () {
    $status = ResourceStatus::healthy(
        value: 100,
        warningThreshold: 50,
        criticalThreshold: 20,
        higherIsBetter: true
    );

    expect($status->success)->toBeTrue();
    expect($status->isHealthy())->toBeTrue();
    expect($status->isWarning())->toBeFalse();
    expect($status->isCritical())->toBeFalse();
    expect($status->getLevel())->toBe('info');
});

it('detects warning when higher is better', function () {
    $status = ResourceStatus::healthy(
        value: 40,
        warningThreshold: 50,
        criticalThreshold: 20,
        higherIsBetter: true
    );

    expect($status->isHealthy())->toBeFalse();
    expect($status->isWarning())->toBeTrue();
    expect($status->isCritical())->toBeFalse();
    expect($status->getLevel())->toBe('warning');
});

it('detects critical when higher is better', function () {
    $status = ResourceStatus::healthy(
        value: 15,
        warningThreshold: 50,
        criticalThreshold: 20,
        higherIsBetter: true
    );

    expect($status->isHealthy())->toBeFalse();
    expect($status->isWarning())->toBeFalse();
    expect($status->isCritical())->toBeTrue();
    expect($status->getLevel())->toBe('critical');
});

it('detects warning when lower is better', function () {
    $status = ResourceStatus::healthy(
        value: 70,
        warningThreshold: 50,
        criticalThreshold: 90,
        higherIsBetter: false
    );

    expect($status->isHealthy())->toBeFalse();
    expect($status->isWarning())->toBeTrue();
    expect($status->isCritical())->toBeFalse();
});

it('detects critical when lower is better', function () {
    $status = ResourceStatus::healthy(
        value: 95,
        warningThreshold: 50,
        criticalThreshold: 90,
        higherIsBetter: false
    );

    expect($status->isHealthy())->toBeFalse();
    expect($status->isWarning())->toBeFalse();
    expect($status->isCritical())->toBeTrue();
});

it('creates failed status', function () {
    $status = ResourceStatus::failed('Check failed', ['error' => 'Connection timeout']);

    expect($status->success)->toBeFalse();
    expect($status->isHealthy())->toBeFalse();
    expect($status->getLevel())->toBe('error');
    expect($status->metadata)->toHaveKey('error');
});

it('serializes to JSON correctly', function () {
    $status = ResourceStatus::healthy(
        value: 100,
        warningThreshold: 50,
        criticalThreshold: 20,
        higherIsBetter: true,
        message: 'All good'
    );

    $json = $status->jsonSerialize();

    expect($json)->toHaveKeys([
        'success',
        'value',
        'warning_threshold',
        'critical_threshold',
        'higher_is_better',
        'message',
        'metadata',
        'level',
        'is_healthy',
    ]);

    expect($json['success'])->toBeTrue();
    expect($json['value'])->toBe(100.0);
    expect($json['level'])->toBe('info');
    expect($json['is_healthy'])->toBeTrue();
});

it('handles boundary values correctly', function () {
    // Exactly at warning threshold (higher is better)
    $status = ResourceStatus::healthy(
        value: 50,
        warningThreshold: 50,
        criticalThreshold: 20,
        higherIsBetter: true
    );

    expect($status->isWarning())->toBeTrue();
    expect($status->isCritical())->toBeFalse();
});
