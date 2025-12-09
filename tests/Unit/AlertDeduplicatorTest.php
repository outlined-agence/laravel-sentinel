<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Outlined\Sentinel\Support\AlertDeduplicator;

beforeEach(function () {
    Cache::flush();
    config(['sentinel.deduplication.enabled' => true]);
    config(['sentinel.deduplication.ttl' => 3600]);
    $this->deduplicator = new AlertDeduplicator();
});

it('allows first alert', function () {
    $result = $this->deduplicator->shouldSend('error', 'Test error message');
    expect($result)->toBeTrue();
});

it('blocks duplicate alert within TTL', function () {
    $this->deduplicator->shouldSend('error', 'Test error message');

    $result = $this->deduplicator->shouldSend('error', 'Test error message');
    expect($result)->toBeFalse();
});

it('allows different messages', function () {
    $this->deduplicator->shouldSend('error', 'Error 1');

    $result = $this->deduplicator->shouldSend('error', 'Error 2');
    expect($result)->toBeTrue();
});

it('allows different types with same message', function () {
    $this->deduplicator->shouldSend('error', 'Same message');

    $result = $this->deduplicator->shouldSend('warning', 'Same message');
    expect($result)->toBeTrue();
});

it('uses custom dedup key when provided', function () {
    $this->deduplicator->shouldSend('error', 'Message', ['dedup_key' => 'custom_key']);

    $result = $this->deduplicator->shouldSend('error', 'Different message', ['dedup_key' => 'custom_key']);
    expect($result)->toBeFalse();
});

it('can check if alert is deduplicated', function () {
    expect($this->deduplicator->isDeduplicated('error', 'Test'))->toBeFalse();

    $this->deduplicator->shouldSend('error', 'Test');

    expect($this->deduplicator->isDeduplicated('error', 'Test'))->toBeTrue();
});

it('can manually mark alert as sent', function () {
    $this->deduplicator->markAsSent('error', 'Manual alert');

    $result = $this->deduplicator->shouldSend('error', 'Manual alert');
    expect($result)->toBeFalse();
});

it('can clear specific alert', function () {
    $this->deduplicator->shouldSend('error', 'Test');
    expect($this->deduplicator->isDeduplicated('error', 'Test'))->toBeTrue();

    $this->deduplicator->clear('error', 'Test');
    expect($this->deduplicator->isDeduplicated('error', 'Test'))->toBeFalse();
});

it('allows all alerts when disabled', function () {
    config(['sentinel.deduplication.enabled' => false]);
    $deduplicator = new AlertDeduplicator();

    $deduplicator->shouldSend('error', 'Test');
    $result = $deduplicator->shouldSend('error', 'Test');

    expect($result)->toBeTrue();
});

it('can get deduplication info', function () {
    $this->deduplicator->shouldSend('error', 'Test message');

    $info = $this->deduplicator->getInfo('error', 'Test message');

    expect($info)->toBeArray()
        ->toHaveKey('sent_at')
        ->toHaveKey('type')
        ->toHaveKey('message_hash');
});

it('can set custom TTL for type', function () {
    $this->deduplicator->setTtlForType('critical', 7200);

    expect($this->deduplicator->getTtlForType('critical'))->toBe(7200);
    expect($this->deduplicator->getTtlForType('unknown'))->toBe(3600);
});
