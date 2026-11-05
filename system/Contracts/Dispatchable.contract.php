<?php declare(strict_types=1);

namespace System\Contracts;

/**
 * Contract for dispatchable classes (controllers, tools, etc.)
 */
interface Dispatchable
{
    /**
     * Handle the request.
     *
     * @param array<string> $segments URI segments
     * @return bool True if a route was matched and dispatched, false to fall through to WordPress
     */
    public static function handle (array $segments): bool;
}