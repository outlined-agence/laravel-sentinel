<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $level
 * @property string $message
 * @property string|null $event_type
 * @property array<string, mixed> $context
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property string|null $url
 * @property string|null $environment
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class SentinelEvent extends Model
{
    use Prunable;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'level',
        'message',
        'event_type',
        'context',
        'user_id',
        'ip_address',
        'url',
        'environment',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'context' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('sentinel.database.table', 'sentinel_events'));
        $this->setConnection(config('sentinel.database.connection'));
    }

    /**
     * Get the user associated with this event.
     *
     * @return BelongsTo<Model, self>
     */
    public function user(): BelongsTo
    {
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * Get the prunable model query.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        $retentionDays = config('sentinel.database.retention_days', 30);

        return static::where('created_at', '<=', now()->subDays($retentionDays));
    }

    /**
     * Scope a query to only include events of a given level.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }

    /**
     * Scope a query to only include events of a given type.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope a query to only include error-level and above events.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeErrors(Builder $query): Builder
    {
        return $query->whereIn('level', ['error', 'critical', 'alert', 'emergency']);
    }

    /**
     * Scope a query to only include events from a specific environment.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeEnvironment(Builder $query, string $environment): Builder
    {
        return $query->where('environment', $environment);
    }

    /**
     * Scope a query to only include events from today.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope a query to only include events from the last N hours.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeLastHours(Builder $query, int $hours): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Get the exception class if this is an error event.
     */
    public function getExceptionClassAttribute(): ?string
    {
        return $this->context['exception']['class'] ?? null;
    }

    /**
     * Get the exception file if this is an error event.
     */
    public function getExceptionFileAttribute(): ?string
    {
        return $this->context['exception']['file'] ?? null;
    }

    /**
     * Get the exception line if this is an error event.
     */
    public function getExceptionLineAttribute(): ?int
    {
        return $this->context['exception']['line'] ?? null;
    }

    /**
     * Get the level badge color for display.
     */
    public function getLevelColorAttribute(): string
    {
        return match ($this->level) {
            'debug' => 'gray',
            'info' => 'blue',
            'notice' => 'green',
            'warning' => 'yellow',
            'error' => 'red',
            'critical' => 'purple',
            'alert' => 'pink',
            'emergency' => 'black',
            default => 'gray',
        };
    }
}
