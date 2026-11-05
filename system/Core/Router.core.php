<?php declare (strict_types=1);

namespace System\Core;

use System\Contracts\Dispatchable;

final class Router implements Dispatchable
{
    private static string $currentModule = '';

    /**
     * Get the current module name (set during dispatch).
     *
     * @return string The current module name, empty string if not set
     */
    public static function currentModule(): string
    {
        return self::$currentModule;
    }

    /**
     * Set the current module (called by handle() before dispatch).
     */
    public static function setCurrentModule(string $module): void
    {
        self::$currentModule = $module;
    }

    /**
     * Handle the request by dispatching to the appropriate controller.
     *
     * @param array<string> $segments URI segments after parsing
     * @return bool True if a route matched and was dispatched, false if falling through to WordPress
     */
    public static function handle(array $segments): bool
    {
        // Auto-discovery logic for module/controller/method/params
        $module = '';
        $controller = '';
        $method = '';
        $params = [];
        $isDefaultRoute = false;

        switch (count($segments)) {
            case 0:
                // Empty path - default route
                $isDefaultRoute = true;
                $module = 'Welcome';
                $controller = 'Welcome';
                $method = 'index';
                break;

            case 1:
                // /module -> Module/Controller = Module, Method = index
                if (!self::isValidSegment($segments[0])) {return false;}
                $isDefaultRoute = true;
                $module = ucfirst(strtolower($segments[0]));
                $controller = $module;
                $method = 'index';
                break;

            case 2:
                // /module/controller -> Module/Controller = Controller, Method = index
                if (!self::isValidSegment($segments[0]) || !self::isValidSegment($segments[1])) {return false;}
                $isDefaultRoute = true;
                $module = ucfirst(strtolower($segments[0]));
                $controller = ucfirst(strtolower($segments[1]));
                $method = 'index';
                break;

            default:
                // /module/controller/method/param1/param2/...
                if (!self::isValidSegment($segments[0]) || !self::isValidSegment($segments[1])) {return false;}
                $module = ucfirst(strtolower($segments[0]));
                $controller = ucfirst(strtolower($segments[1]));
                $method = $segments[2];
                $params = array_slice($segments, 3);
                break;
        }

        // Build controller class name
        $controllerClass = "Modules\\{$module}\\Controllers\\{$controller}";

        // Store module for view resolution
        self::setCurrentModule($module);

        // Try to autoload and dispatch
        if (self::tryDispatch($controllerClass, $method, $params)) {
            return true;
        }

        // Fallback 1: try 'index' method on the specific controller
        // Only for default routes (0–2 segments). Deep routes with an unknown
        // method should fall through to WordPress (404), not silently render index.
        if ($isDefaultRoute && self::tryDispatch($controllerClass, 'index', $params)) {
            return true;
        }

        // Fallback 2: only when the module's Index controller is the fallback
        // target (cases 0 & 1 where no specific controller was named, or where
        // the named controller IS Index). For case 2 (/module/home) where a
        // specific controller is named but doesn't exist, fall through to WP.
        $indexClass = "Modules\\{$module}\\Controllers\\Index";
        if ($indexClass !== $controllerClass) {
            if (count($segments) < 2) {
                // 0 or 1 segments: Index controller is the module's home fallback
                if (self::tryDispatch($indexClass, $method, $params)) {
                    return true;
                }
                if (self::tryDispatch($indexClass, 'index', $params)) {
                    return true;
                }
            }
        }

        // Not found - let WordPress handle it (404 or native routing)
        return false;
    }

    /**
     * Validate that a URL segment contains only safe characters.
     * Prevents path traversal / class-name injection into the autoloader.
     *
     * @param string $segment URL segment
     * @return bool True if the segment is a valid identifier
     */
    private static function isValidSegment(string $segment): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) === 1;
    }

    /**
     * Try to dispatch to a controller method.
     *
     * @param string $controllerClass Fully qualified controller class name
     * @param string $method Method name
     * @param array<string> $params Method parameters
     * @return bool True if dispatched successfully
     */
    private static function tryDispatch(string $controllerClass, string $method, array $params): bool
    {
        // Trigger autoload (via Boot's autoloader - single path, no manual file loading)
        if (!class_exists($controllerClass)) {
            return false;
        }

        if (!method_exists($controllerClass, $method)) {
            return false;
        }

        // Check if method is public and static (or callable)
        $refMethod = new \ReflectionMethod($controllerClass, $method);
        if (!$refMethod->isPublic()) {
            return false;
        }

        // Match URL segments against the method's declared parameters.
        // Supports BOTH styles:
        //   positional:  home(string $a, string $b)      — up to N segments
        //   variadic:    home(string ...$args)           — any number of segments
        // If the segment count is out of range, try the fallback 'index' method
        // instead of hard-failing (index() may take no params).
        $refParams = $refMethod->getParameters();
        $variadic  = $refMethod->isVariadic();
        $required  = 0;
        $optional  = 0;
        foreach ($refParams as $rp) {
            if ($rp->isVariadic()) {
                // variadic absorbs the remainder
                break;
            }
            if ($rp->isDefaultValueAvailable()) {
                $optional++;
            } else {
                $required++;
            }
        }
        $maxFixed = $required + $optional;

        if ($variadic) {
            // must supply at least the required (non-variadic) params
            if (count($params) < $required) {
                return self::dispatchFallback($controllerClass);
            }
            $args = $params; // remainder absorbed by ...$args
        } else {
            // positional: too many segments -> fallback; too few (with optional) -> pad nulls
            if (count($params) > $maxFixed) {
                return self::dispatchFallback($controllerClass);
            }
            $args = array_slice(array_pad($params, $maxFixed, null), 0, $required + count($params));
        }

        // Dispatch
        try {
            if ($refMethod->isStatic()) {
                $refMethod->invoke(null, ...$args);
            } else {
                // For non-static, instantiate first
                $instance = new $controllerClass();
                $refMethod->invoke($instance, ...$args);
            }
            return true;
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("RadykFW Dispatch Error: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Fallback to 'index' method when URL has more segments than the method can accept.
     *
     * @param string $controllerClass FQCN of the controller
     * @return bool
     */
    private static function dispatchFallback(string $controllerClass): bool
    {
        // Fallback to 'index' method (covers optional-only params)
        if (method_exists($controllerClass, 'index')) {
            return self::tryDispatchNoRecurse($controllerClass, 'index', []);
        }
        return false;
    }

    /**
     * Dispatch a method without the param-matching guard (for fallback 'index').
     *
     * @param string $controllerClass FQCN
     * @param string $method method name
     * @param array<string> $params args to pass
     * @return bool
     */
    private static function tryDispatchNoRecurse(string $controllerClass, string $method, array $params): bool
    {
        if (!method_exists($controllerClass, $method)) {
            return false;
        }
        $refMethod = new \ReflectionMethod($controllerClass, $method);
        if (!$refMethod->isPublic()) {
            return false;
        }
        try {
            if ($refMethod->isStatic()) {
                $refMethod->invoke(null, ...$params);
            } else {
                $instance = new $controllerClass();
                $refMethod->invoke($instance, ...$params);
            }
            return true;
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("RadykFW Fallback Dispatch Error: " . $e->getMessage());
            }
            return false;
        }
    }

}