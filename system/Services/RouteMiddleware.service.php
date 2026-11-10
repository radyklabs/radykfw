<?php declare (strict_types=1);

namespace System\Services;

use System\Contracts\MiddlewareInterface;

final class RouteMiddleware
{
    public static function validateRoute ($key, $route): void
    {
        if (!is_string ($key) || !preg_match ('/^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS) \/[^?#\\s]*$/D', $key)) {
            throw new \RuntimeException ('Route key must be METHOD /path.');
        }
        foreach (explode ('/', substr ($key, strpos ($key, '/') + 1)) as $segment) {
            if ((str_contains ($segment, '{') || str_contains ($segment, '}'))
                && !preg_match ('/^\{[A-Za-z_][A-Za-z0-9_]*(?::int)?\}$/D', $segment)) {
                throw new \RuntimeException ("Unsupported placeholder for {$key}.");
            }
            if ($segment === '.' || $segment === '..') {throw new \RuntimeException ('Traversal in route path.');}
        }
        $target = is_array ($route) ? ($route['target'] ?? null) : $route;
        if (!is_string ($target) || !preg_match ('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)?::[A-Za-z_][A-Za-z0-9_]*$/D', $target)) {
            throw new \RuntimeException ("Invalid target for {$key}.");
        }
        if (is_array ($route)) {
            if (array_diff (array_keys ($route), ['target', 'middleware']) !== []
                || !isset ($route['middleware']) || !is_array ($route['middleware']) || !array_is_list ($route['middleware'])) {
                throw new \RuntimeException ("Route {$key} requires target and ordered middleware list.");
            }
            self::resolve ($route['middleware']);
        }
    }

    /** Validate the entire chain before executing the first middleware. */
    private static function resolve (array $names): array
    {
        $registry = get_config ('middleware', []);
        $classes = [];
        foreach ($names as $name) {
            if (!is_string ($name) || !isset ($registry[$name]) || !is_string ($registry[$name])
                || !is_subclass_of ($registry[$name], MiddlewareInterface::class)) {
                throw new \RuntimeException ('Unknown middleware or invalid MiddlewareInterface implementation.');
            }
            $class = new \ReflectionClass ($registry[$name]);
            if (!$class->isInstantiable () || ($class->getConstructor ()?->getNumberOfRequiredParameters () ?? 0) > 0) {
                throw new \RuntimeException ('Middleware must have a public zero-argument constructor.');
            }
            $classes[] = $registry[$name];
        }
        return $classes;
    }

    public static function allow (array $route): bool
    {
        foreach (self::resolve ($route['middleware']) as $class) {
            if (!(new $class ())->allow ($route)) {return false;}
        }
        return true;
    }

    public static function fail (): bool
    {
        http_response_code (500);
        echo esc_html (__ ('Route configuration error.', 'radykfw'));
        return true;
    }
}
