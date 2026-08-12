<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * A 60-second cache for the marketing pages that can be invalidated the instant
 * a price is published.
 *
 * Laravel's cache tags would be the obvious tool, but the `database` store this
 * app deploys on does not support them and the Lightsail box does not run Redis.
 * A version stamp folded into every key does the same job on any store:
 * bump() makes every previously cached page unreachable at once.
 */
final class PageCache
{
    private const VERSION_KEY = 'pages.version';

    public const TTL_SECONDS = 60;

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public static function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember(self::key($key), self::TTL_SECONDS, $callback);
    }

    public static function key(string $key): string
    {
        return sprintf('page.v%d.%s', self::version(), $key);
    }

    public static function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn () => 1);
    }

    /** Publishing a price, or changing a setting, retires every cached page. */
    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }
}
