<?php declare (strict_types=1);

namespace System\Core;

use System\Core\Registry;
use System\Contracts\DispatchableInterface;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

final class Router implements DispatchableInterface
{
    private static string $currentModule = '';

    /**
     * Get the current module name (set during dispatch).
     *
     * @return string The current module name, empty string if not set
     */
    public static function currentModule (): string
    {
        return self::$currentModule;
    }

    /**
     * Set the current module (called by handle() before dispatch).
     */
    public static function setCurrentModule (string $module): void
    {
        self::$currentModule = $module;
    }

    /**
     * Handle the request by dispatching to the appropriate controller.
     *
     * @param array<string> $segments URI segments after parsing
     * @return bool True if a route matched and was dispatched, false if falling through to WordPress
     */
    public static function handle (array $segments): bool
    {
        $segments_structure = ['module', 'controller', 'method'];

        // Auto-discovery logic for module/controller/method/params
        $nsmod = Registry::get ('nsmod');
        // Namespace prefix from Registry (derived from .starter modules_dir)

        // Check auto-discovery toggle
        $autoDiscover = \System\Services\Settings::getConfigValue (
            'modules.auto_discover',
            get_config ('modules.auto_discover', true)
        );
        if (!$autoDiscover) {
            // Auto-discovery disabled, fall through to WP
            return false;
        }

        // Check routing.native - if true, don't claim root path
        $routingNative = \System\Services\Settings::getConfigValue (
            'routing.native',
            get_config ('routing.native', true)
        );
        if ($routingNative && count ($segments) === 0) {
            // Root path - let WordPress handle it
            return false;
        }

        foreach ($segments_structure as $segs) {
            $$segs = '';
        }

        // $module = '';
        // $controller = '';
        // $method = '';
        $params = [];

        switch (count ($segments)) {
            case 0:
                // Empty path - default route (only reached if routing.native = false)
                // routing.default = module name; infer controller = module, method = index
                $module = self::normalizeClassName ((string) get_config ('routing.default', 'Welcome'));
                $controller = $module;
                $method = 'index';
                break;

            case 1:
                // /module -> Module/Controller = Module, Method = index
                if (!self::isValidSegment ($segments[0])) {return false;}
                $module = self::normalizeClassName ($segments[0]);
                $controller = $module;
                $method = 'index';
                break;

            case 2:
                // /module/controller -> Module/Controller = Controller, Method = index
                if (!self::isValidSegment ($segments[0]) || !self::isValidSegment ($segments[1])) {return false;}
                $module = self::normalizeClassName ($segments[0]);
                $controller = self::normalizeClassName ($segments[1]);
                $method = 'index';
                break;

            default:
                // /module/controller/method/param1/param2/...
                if (!self::isValidSegment ($segments[0]) || !self::isValidSegment ($segments[1])) {return false;}
                $module = self::normalizeClassName ($segments[0]);
                $controller = self::normalizeClassName ($segments[1]);
                $method = $segments[2];
                $params = array_slice ($segments, 3);
                break;
        }

        // Build controller class name (prefix from Registry nsmod — derived from .starter modules_dir)
        $controllerClass = "{$nsmod}{$module}\\Controllers\\{$controller}";

        // Store module for view resolution
        self::setCurrentModule ($module);

        // Try to autoload and dispatch
        if (self::tryDispatch ($controllerClass, $method, $params)) {
            return true;
        }

        // Fallback 2: only when the module's Index controller is the fallback
        // target (cases 0 & 1 where no specific controller was named, or where
        // the named controller IS Index). For case 2 (/module/home) where a
        // specific controller is named but doesn't exist, fall through to WP.
        $indexClass = "{$nsmod}{$module}\\Controllers\\Index";
        if ($indexClass !== $controllerClass) {
            if (count ($segments) < 2) {
                // 0 or 1 segments: Index controller is the module's home fallback
                if (self::tryDispatch ($indexClass, $method, $params)) {
                    return true;
                }
                if (self::tryDispatch ($indexClass, 'index', $params)) {
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
    private static function isValidSegment (string $segment): bool
    {
        return preg_match ('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) === 1;
    }

    /**
     * Normalize a class name segment: first char uppercase, rest preserve case.
     * E.g., 'userProfile' -> 'UserProfile', 'user' -> 'User'
     *
     * @param string $segment URL segment
     * @return string Normalized class name
     */
    private static function normalizeClassName (string $segment): string
    {
        return ucfirst ($segment);
    }

    /**
     * Try to dispatch to a controller method (public for Runner aliases).
     *
     * @param string $controllerClass Fully qualified controller class name
     * @param string $method Method name
     * @param array<string> $params Method parameters
     * @return bool True if dispatched successfully
     */
    public static function tryDispatch (string $controllerClass, string $method, array $params): bool
    {
        $catalog = \System\Services\Module::discover ();
        if ($catalog['errors'] !== []) {return \System\Services\Pipeline::fail ();}
        $module = self::currentModule ();
        if (isset ($catalog['modules'][$module]) && !$catalog['modules'][$module]['routes']) {http_response_code (404); return true;}
        try {
            foreach (get_config ('routing.aliases', []) + $catalog['aliases'] as $key => $route) {
                \System\Services\Pipeline::validateRoute ($key, $route);
                if (!is_array ($route)) {continue;}
                [$name, $action] = explode ('::', $route['target']);
                $parts = explode ('\\', $name);
                $class = Registry::get ('nsmod') . $parts[0] . '\\Controllers\\' . ($parts[1] ?? $parts[0]);
                if ($class === $controllerClass && $action === $method && !\System\Services\Pipeline::allow ($route)) {return true;}
            }
        } catch (\Throwable $e) {return \System\Services\Pipeline::fail ();}

        // Trigger autoload (via Boot's autoloader - single path, no manual file loading)
        if (!class_exists ($controllerClass)) {return false;}

        if (!method_exists ($controllerClass, $method)) {return false;}

        // Check if method is public and static (or callable)
        $refMethod = new \ReflectionMethod ($controllerClass, $method);

        if (!$refMethod->isPublic ()) {return false;}

        // Match URL segments against the method's declared parameters.
        // Supports BOTH styles:
        //   positional:  home(string $a, string $b)      — up to N segments
        //   variadic:    home(string ...$args)           — any number of segments
        // If the segment count is out of range, try the fallback 'index' method
        // instead of hard-failing (index() may take no params).
        $refParams = $refMethod->getParameters ();
        $variadic  = $refMethod->isVariadic ();
        $required  = 0;
        $optional  = 0;

        foreach ($refParams as $rp)
        {
            // variadic absorbs the remainder
            if ($rp->isVariadic ()) {break;}

            if ($rp->isDefaultValueAvailable ()) {$optional++;}
            else {$required++;}
        }

        $maxFixed = $required + $optional;

        if ($variadic)
        {
            // must supply at least the required (non-variadic) params
            if (count ($params) < $required)
            {
                return self::dispatchFallback ($controllerClass);
            }
            
            $args = $params; // remainder absorbed by ...$args
        }
        
        else
        {
            // positional: too many segments -> fallback; too few (with optional) -> pad nulls
            if (count ($params) > $maxFixed)
            {
                return self::dispatchFallback ($controllerClass);
            }
            
            $args = array_slice (array_pad ($params, $maxFixed, null), 0, $required + count ($params));
        }

        // Dispatch using shared helper
        return self::invokeMethod ($controllerClass, $method, $args);
    }

    /**
     * Fallback to 'index' method when URL has more segments than the method can accept.
     *
     * @param string $controllerClass FQCN of the controller
     * @return bool
     */
    private static function dispatchFallback (string $controllerClass): bool
    {
        // Fallback to 'index' method (covers optional-only params)
        if (method_exists ($controllerClass, 'index'))
        {
            return self::tryDispatchNoRecurse ($controllerClass, 'index', []);
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
    private static function tryDispatchNoRecurse (string $controllerClass, string $method, array $params): bool
    {
        return self::tryDispatch ($controllerClass, $method, $params);
    }

    /**
     * Invoke a controller method with arguments (shared dispatch logic).
     *
     * @param string $controllerClass FQCN of the controller
     * @param string $method method name
     * @param array<string> $params args to pass
     * @return bool True if dispatched successfully
     */
    private static function invokeMethod (string $controllerClass, string $method, array $params): bool
    {
        if (!method_exists ($controllerClass, $method)) {return false;}

        $refMethod = new \ReflectionMethod ($controllerClass, $method);

        if (!$refMethod->isPublic ()) {return false;}

        try
        {
            if ($refMethod->isStatic ())
            {
                $refMethod->invoke (null, ...$params);
            }
            
            else
            {
                // For non-static, instantiate first
                $instance = new $controllerClass ();
                $refMethod->invoke ($instance, ...$params);
            }
            
            return true;
        }
        
        catch (\Throwable $e)
        {
            if (defined ('WP_DEBUG') && WP_DEBUG)
            {
                error_log ("RadykFW Dispatch Error: " . $e->getMessage());
            }
            
            return false;
        }
    }

    /**
     * Try to dispatch via route aliases.
     *
     * @return bool True if alias matched and dispatched
     */
    public static function tryAliases (): bool
    {
        $catalog = \System\Services\Module::discover ();
        if ($catalog['errors'] !== []) {return \System\Services\Pipeline::fail ();}
        $aliases = get_config ('routing.aliases', []) + $catalog['aliases'];
        try {
            foreach ($aliases as $key => $route) {\System\Services\Pipeline::validateRoute ($key, $route);}
        } catch (\Throwable $e) {return \System\Services\Pipeline::fail ();}
        $nsmod = Registry::get ('nsmod'); // trailing backslash: "Modules\"
        $httpMethod = strtoupper (catch_server ('request.method', 'get'));
    
        // Use original request URI for alias matching (before WP rewrite)
        $requestUri = catch_server ('request.uri', '');
        $requestPath = parse_url ($requestUri, PHP_URL_PATH);
        $requestPath = trim ($requestPath, '/');
    
        // Strip plugin base path (e.g. /radykfwtest/login -> login)
        $pluginBase = trim (parse_url (home_url ('/'), PHP_URL_PATH), '/');
        if (!empty ($pluginBase) && str_starts_with ($requestPath, $pluginBase . '/')) {
            $requestPath = substr ($requestPath, strlen ($pluginBase) + 1);
        }

        $aliasKey = "{$httpMethod} /" . $requestPath;

        // 1. Exact match — highest priority (no placeholders)
        if (isset ($aliases[$aliasKey])) {
            return self::dispatchAlias ($aliases[$aliasKey], $nsmod, []);
        }

        // 2. Pattern match — placeholders {seg} / {seg:int}, equal segment count
        $prefix = "{$httpMethod} /";
        foreach ($aliases as $key => $target) {
            if (!str_starts_with ($key, $prefix)) {continue;}
            $pattern = substr ($key, strlen ($prefix));
            if (!str_contains ($pattern, '{')) {continue;}
            $params = [];
            if (self::matchAliasPattern ($pattern, $requestPath, $params)
                && self::dispatchAlias ($target, $nsmod, $params)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match an alias pattern with placeholders against a request path.
     *
     * Segment {name}     = string, must match [A-Za-z0-9_-]+ (blocks traversal)
     * Segment {name:int} = digits only; value still passed as string
     * Literal segments must match exactly; segment counts must be equal.
     *
     * @param string $pattern Alias path after the METHOD prefix (e.g. 'door/{id:int}')
     * @param string $path    Normalised request path (e.g. 'door/42')
     * @param array  $params  Captured values (always strings), by reference
     * @return bool True if pattern matches
     */
    public static function matchAliasPattern (string $pattern, string $path, array &$params): bool
    {
        $patternSegs = explode ('/', $pattern);
        $pathSegs    = explode ('/', $path);

        if (count ($patternSegs) !== count ($pathSegs)) {return false;}

        $params = [];
        foreach ($patternSegs as $i => $pSeg) {
            if (preg_match ('/^\{(\w+)(?::(\w+))?\}$/', $pSeg, $m)) {
                $type = $m[2] ?? 'string';
                if ($type === 'int') {
                    if (preg_match ('/^\d+$/', $pathSegs[$i]) !== 1) {return false;}
                } else {
                    if (preg_match ('/^[A-Za-z0-9_-]+$/', $pathSegs[$i]) !== 1) {return false;}
                }
                $params[] = $pathSegs[$i];
            } elseif ($pSeg !== $pathSegs[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse an alias target and dispatch it with captured params.
     *
     * @param string $target Alias value: 'Module\Controller::method' or 'Module::method'
     * @param string $nsmod  Namespace prefix from Registry (trailing backslash)
     * @param array  $params Params to pass to the method
     * @return bool True if dispatched successfully
     */
    private static function dispatchAlias (string|array $target, string $nsmod, array $params): bool
    {
        $protected = is_array ($target);
        $target = $protected ? $target['target'] : $target;
        if (preg_match ('/^([A-Za-z_][A-Za-z0-9_]*)\\\\([A-Za-z_][A-Za-z0-9_]*)::(\w+)$/', $target, $m)) {
            // Explicit: Module\Controller::method
            $class = "{$nsmod}{$m[1]}\\Controllers\\{$m[2]}";
            self::setCurrentModule ($m[1]);
            if (self::tryDispatch ($class, $m[3], $params)) {return true;}
        } elseif (preg_match ('/^([A-Za-z_][A-Za-z0-9_]*)::(\w+)$/', $target, $m)) {
            // Shorthand: Module::method (controller = module)
            $class = "{$nsmod}{$m[1]}\\Controllers\\{$m[1]}";
            self::setCurrentModule ($m[1]);
            if (self::tryDispatch ($class, $m[2], $params)) {return true;}
        }

        return $protected ? \System\Services\Pipeline::fail () : false;
    }

    /**
     * Get the request path from the WP object.
     *
     * @param \WP $wp WordPress instance
     * @return string Normalised request path (no leading slash)
     */
    public static function getRequestPath (\WP $wp): string
    {
        $request = isset ($wp->request) ? $wp->request : '';
        $path = trim ($request, '/');

        // Return empty string for root path - let Router decide
        return $path;
    }

    /**
     * Decide whether routing should be skipped for this path.
     *
     * @param string $path Normalised request path (no leading slash)
     * @param \WP $wp WordPress instance
     * @return bool True if routing should be skipped
     */
    public static function shouldSkipRouting (string $path, \WP $wp): bool
    {
        // Skip admin
        if (is_admin () && !wp_doing_ajax ()) {return true;}

        // Skip AJAX
        if (wp_doing_ajax ()) {return true;}

        // Skip REST API
        if (str_starts_with ($path, 'wp-json/') || str_starts_with ($path, 'rest/')) {return true;}

        // Skip login/wp-admin related + infra paths (from config, tanpa .php)
        if (in_array ($path, get_config ('routing.skip_paths', []), true)) {return true;}

        // Skip static files (from config)
        $skipExt = strtolower (pathinfo ($path, PATHINFO_EXTENSION));
        if ($skipExt !== '' && in_array ($skipExt, get_config ('routing.skip_extensions', []), true)) {return true;}

        // Skip if already handled by RadykFW
        if (isset ($wp->query_vars['radykfw_handled'])) {return true;}

        return false;
    }

    /**
     * Decide whether WordPress actually owns the given path (a real page or
     * post exists for it), BEFORE WordPress runs its main query.
     *
     * Replaces the old query-var heuristic which was always true for any
     * path shape under pretty permalinks. Uses object-cache-friendly core
     * lookups so the cost is minimal.
     *
     * @param string $path Normalised request path (no leading slash)
     * @return bool True if WordPress has a real page/post at this path
     */
    public static function wpHasRealContent (string $path): bool
    {
        $cleaned = trim ($path, '/');
        if ($cleaned === '' || str_contains ($cleaned, '.')) {return false;}

        // Page at this slug (post_type 'page')
        if (\get_page_by_path ($cleaned, OBJECT, 'page') !== null) {return true;}

        // Post at this slug (object-cache friendly via get_posts by name)
        $found = \get_posts ([
            'name' => $cleaned,
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'no_found_rows' => true,
        ]);
        if (!empty ($found)) {return true;}

        return false;
    }

    /**
     * Prevent WordPress canonical redirect for our routes.
     * WordPress's redirect_canonical fires on template_redirect and can
     * conflict with our routes that don't have trailing slashes.
     *
     * @param string $redirect_url The redirect URL WordPress wants to use
     * @param string $requested_url The originally requested URL
     * @return string|false False to prevent redirect, URL to allow redirect
     */
    public static function filterCanonicalRedirect (string $redirect_url, string $requested_url): string|false
    {
        $home_url = home_url ('/');
        $requested = rtrim ($requested_url, '/');

        // Static claimed routes come from config (routing.aliases keys) — no hardcoded list.
        // Pattern keys (containing '{') are skipped: they are not literal URLs.
        foreach (array_keys (get_config ('routing.aliases', [])) as $key)
        {
            $space = strpos ($key, ' ');
            if ($space === false) {continue;}

            $path = trim (substr ($key, $space), ' /');
            if ($path === '' || str_contains ($path, '{')) {continue;}

            // Match both with and without trailing slash
            if ($requested === $home_url . $path) {
                return false; // Don't redirect our routes
            }
        }

        return $redirect_url; // Allow redirect for other URLs
    }
}
