<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Outlined\Sentinel\Support\RateLimiter;

beforeEach(function () {
    Cache::flush();
    config(['sentinel.rate_limit.enabled' => true]);
    config(['sentinel.rate_limit.max_per_minute' => 5]);
    config(['sentinel.rate_limit.max_per_hour' => 20]);
    $this->rateLimiter = new RateLimiter();
});

it('allows requests under the limit', function () {
    expect($this->rateLimiter->allow('slack'))->toBeTrue();
});

it('records hits', function () {
    $this->rateLimiter->hit('slack');

    $status = $this->rateLimiter->status('slack');

    expect($status['minute']['current'])->toBe(1);
    expect($status['hour']['current'])->toBe(1);
});

it('blocks requests over minute limit', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->rateLimiter->hit('slack');
    }

    expect($this->rateLimiter->allow('slack'))->toBeFalse();
});

it('blocks requests over hour limit', function () {
    config(['sentinel.rate_limit.max_per_minute' => 100]);
    config(['sentinel.rate_limit.max_per_hour' => 5]);
    $rateLimiter = new RateLimiter();

    for ($i = 0; $i < 5; $i++) {
        $rateLimiter->hit('slack');
    }

    expect($rateLimiter->allow('slack'))->toBeFalse();
});

it('calculates remaining correctly', function () {
    config(['sentinel.rate_limit.max_per_minute' => 10]);
    $rateLimiter = new RateLimiter();

    $rateLimiter->hit('slack');
    $rateLimiter->hit('slack');

    expect($rateLimiter->remainingPerMinute('slack'))->toBe(8);
});

it('can reset limits for a channel', function () {
    $this->rateLimiter->hit('slack');
    $this->rateLimiter->hit('slack');

    $this->rateLimiter->reset('slack');

    $status = $this->rateLimiter->status('slack');

    expect($status['minute']['current'])->toBe(0);
    expect($status['hour']['current'])->toBe(0);
});

it('allows all requests when disabled', function () {
    config(['sentinel.rate_limit.enabled' => false]);
    $rateLimiter = new RateLimiter();

    for ($i = 0; $i < 100; $i++) {
        $rateLimiter->hit('slack');
    }

    expect($rateLimiter->allow('slack'))->toBeTrue();
});

it('tracks channels independently', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->rateLimiter->hit('slack');
    }

    expect($this->rateLimiter->allow('slack'))->toBeFalse();
    expect($this->rateLimiter->allow('discord'))->toBeTrue();
});

it('returns correct status structure', function () {
    $status = $this->rateLimiter->status('slack');

    expect($status)->toHaveKeys(['channel', 'minute', 'hour', 'allowed']);
    expect($status['minute'])->toHaveKeys(['current', 'max', 'remaining']);
    expect($status['hour'])->toHaveKeys(['current', 'max', 'remaining']);
});
