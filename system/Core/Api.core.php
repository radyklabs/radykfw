<?php declare (strict_types=1);

namespace System\Core;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * Base controller for API routes (JSON responses).
 *
 * Module controllers that expose endpoints extend this:
 *
 *     class StatsController extends \System\Core\ApiController {}
 *
 * @since 1.0.1
 */
abstract class ApiController
{
    /**
     * Get the current module name (set by Router before dispatch).
     */
    protected static function module (): string
    {
        return \System\Core\Router::currentModule ();
    }

    /**
     * Send a JSON response and halt execution.
     *
     * @param mixed $data Payload
     * @param int $status HTTP status code
     */
    protected static function respond (mixed $data, int $status = 200): void
    {
        http_response_code ($status);
        header ('Content-Type: application/json; charset=UTF-8');
        $json = json_encode ($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        echo $json;
        exit;
    }

    /**
     * Send a JSON error response and halt.
     */
    protected static function error (string $message, int $status = 400): void
    {
        self::respond (['error' => $message], $status);
    }
}
