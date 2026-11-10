<?php declare (strict_types=1);

namespace System\Services;

final class ModuleCli
{
    public static function register (): void
    {
        if (!defined ('WP_CLI') || !WP_CLI) {return;}
        foreach (['list-modules', 'test-routes', 'debug-route', 'make-module'] as $command) {
            \WP_CLI::add_command ('radykfw ' . $command, static function ($args, $options) use ($command): void {
                try {
                    $result = self::run ($command, $args, $options);
                    \WP_CLI::line (json_encode ($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                    if (($result['errors'] ?? []) !== []) {\WP_CLI::halt (1);}
                } catch (\Throwable $e) {\WP_CLI::error ($e->getMessage ());}
            }, ['shortdesc' => 'Inspect or scaffold RadykFW modules without dispatching controllers.',
                'synopsis' => ($command === 'make-module' ? '<ModuleName> ' : ($command === 'debug-route' ? '<path> [--method=<method>] ' : '')) . '[--format=<format>]']);
        }
    }

    public static function run (string $command, array $args, array $options): array
    {
        if (($options['format'] ?? 'json') !== 'json') {throw new \InvalidArgumentException ('Supported format: json.');}
        if ($command === 'make-module') {return self::make ($args[0] ?? '');}
        $catalog = ModuleCatalog::discover ();
        if ($command === 'list-modules') {return ['modules' => $catalog['modules'], 'errors' => $catalog['errors']];}
        $routes = get_config ('routing.aliases', []) + $catalog['aliases'];
        $errors = $catalog['errors'];
        foreach ($routes as $key => $route) {
            try {
                RouteMiddleware::validateRoute ($key, $route);
                $target = is_array ($route) ? $route['target'] : $route;
                [$name, $method] = explode ('::', $target);
                $parts = explode ('\\', $name);
                $class = (\System\Core\Registry::get ('nsmod') ?: 'Modules\\') . $parts[0] . '\\Controllers\\' . ($parts[1] ?? $parts[0]);
                if (!class_exists ($class) || !method_exists ($class, $method) || !(new \ReflectionMethod ($class, $method))->isPublic ()) {
                    throw new \RuntimeException ("{$key}: target must resolve to a public controller method.");
                }
            }
            catch (\Throwable $e) {$errors[] = $e->getMessage ();}
        }
        if ($command === 'test-routes') {return ['routes' => $routes, 'errors' => $errors];}
        if ($command !== 'debug-route') {throw new \InvalidArgumentException ('Unknown command.');}
        $method = strtoupper ($options['method'] ?? 'GET');
        if (!in_array ($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true)) {throw new \InvalidArgumentException ('Unsupported HTTP method.');}
        $path = $args[0] ?? '';
        if ($path === '' || $path[0] !== '/' || str_contains ($path, '?') || str_contains ($path, '#')) {
            throw new \InvalidArgumentException ('Provide an absolute request path without query or fragment.');
        }
        $key = $method . ' ' . $path;
        $matches = [];
        if (isset ($routes[$key])) {$matches[$key] = $routes[$key];}
        else {
            $matcher = new \ReflectionMethod (\System\Core\Router::class, 'matchAliasPattern');
            foreach ($routes as $pattern => $route) {
                if (!str_starts_with ($pattern, $method . ' /')) {continue;}
                $params = [];
                $arguments = [substr ($pattern, strlen ($method) + 2), ltrim ($path, '/'), &$params];
                if ($matcher->invokeArgs (null, $arguments)) {$matches[$pattern] = ['route' => $route, 'params' => $params];}
            }
        }
        return ['request' => $key, 'matches' => $matches, 'auto_discover' => Settings::getConfigValue ('modules.auto_discover', false), 'errors' => $errors];
    }

    private static function make (string $name): array
    {
        if (!preg_match ('/^[A-Z][A-Za-z0-9_]*$/D', $name)
            || in_array (strtolower ($name), ['app', 'assets', 'auth', 'helper', 'settings', 'system', 'modules'], true)) {
            throw new \InvalidArgumentException ('Use a non-reserved PascalCase module name.');
        }
        $root = realpath (ModuleCatalog::root ());
        if ($root === false || is_link (ModuleCatalog::root ())) {throw new \RuntimeException ('Modules root must exist and cannot be a symbolic link.');}
        foreach (scandir ($root) ?: [] as $entry) {
            if (strcasecmp ($entry, $name) === 0) {throw new \RuntimeException ('Module already exists; nothing overwritten.');}
        }
        $dir = $root . DIRECTORY_SEPARATOR . $name;
        if (!mkdir ($dir)) {throw new \RuntimeException ('Cannot create module directory.');}
        $created = [];
        try {
            foreach (['Config', 'Controllers', 'Views'] as $folder) {
                if (!mkdir ($dir . DIRECTORY_SEPARATOR . $folder)) {throw new \RuntimeException ('Cannot create module subdirectory.');}
            }
            $namespace = (\System\Core\Registry::get ('nsmod') ?: 'Modules\\') . $name;
            $files = [
                'Config/modules.conf.php' => "<?php declare (strict_types=1);\n\nreturn " . var_export (['namespace' => $namespace, 'depends_on' => [], 'routes' => true], true) . ";\n",
                'Controllers/' . $name . '.controller.php' => "<?php declare (strict_types=1);\n\nnamespace {$namespace}\\Controllers;\n\nfinal class {$name}\n{\n    public static function index (): void\n    {\n        view ('index');\n    }\n}\n",
                'Views/index.view.php' => "<?php declare (strict_types=1); ?>\n<p><?= esc_html__ ('Module ready.', 'radykfw') ?></p>\n",
            ];
            foreach ($files as $relative => $content) {
                $path = $dir . DIRECTORY_SEPARATOR . str_replace ('/', DIRECTORY_SEPARATOR, $relative);
                $handle = fopen ($path, 'x');
                if ($handle === false) {throw new \RuntimeException ('Cannot exclusively create scaffold file.');}
                $created[] = $path;
                try {if (fwrite ($handle, $content) !== strlen ($content)) {throw new \RuntimeException ('Incomplete scaffold write.');}}
                finally {fclose ($handle);}
            }
        } catch (\Throwable $e) {
            foreach ($created as $path) {unlink ($path);}
            foreach (['Config', 'Controllers', 'Views'] as $folder) {if (is_dir ($dir . DIRECTORY_SEPARATOR . $folder)) {rmdir ($dir . DIRECTORY_SEPARATOR . $folder);}}
            rmdir ($dir);
            throw $e;
        }
        ModuleCatalog::reset ();
        return ['module' => $name, 'created' => array_keys ($files)];
    }
}
