<?php declare (strict_types=1);

namespace System\Services;

use System\Core\Registry;

/** Explicit, additive module configuration. Never mutates get_config(). */
final class ModuleCatalog
{
    private static array $cache = [];

    public static function reset (): void
    {
        self::$cache = [];
    }

    public static function root (): string
    {
        return Registry::get ('modules') ?: plugin_dir_path (keyfile) . 'modules';
    }

    /** @return array{modules: array, aliases: array, menus: array, actions: array, errors: array} */
    public static function discover (): array
    {
        $root = self::root ();
        if (isset (self::$cache[$root])) {return self::$cache[$root];}
        $result = ['modules' => [], 'aliases' => [], 'menus' => [], 'actions' => [], 'errors' => []];
        $files = [];
        $names = [];
        $allowed = ['modules.conf.php', 'routing.conf.php', 'menus.conf.php', 'actions.conf.php'];
        if (is_link ($root)) {$result['errors'][] = 'Modules root cannot be a symbolic link.'; return $result;}
        $entries = is_dir ($root) ? scandir ($root) : [];
        if ($entries === false) {$result['errors'][] = 'Cannot read modules directory.'; $entries = [];}
        foreach ($entries as $name) {
            $dir = $root . DIRECTORY_SEPARATOR . $name;
            if ($name === '.' || $name === '..' || !is_dir ($dir)) {continue;}
            try {
                if (isset ($names[strtolower ($name)])) {throw new \RuntimeException ('Case-insensitive module name collision.');}
                $names[strtolower ($name)] = true;
                if (!preg_match ('/^[A-Z][A-Za-z0-9_]*$/D', $name) || is_link ($dir)) {
                    throw new \RuntimeException ('Invalid module directory name or symbolic link.');
                }
                if (is_file ($dir . DIRECTORY_SEPARATOR . 'Configs' . DIRECTORY_SEPARATOR . 'menus.conf.php')) {
                    throw new \RuntimeException ('Legacy Configs/menus.conf.php must be migrated to Config/menus.conf.php.');
                }
                $configDir = $dir . DIRECTORY_SEPARATOR . 'Config';
                $files[$name] = [];
                if (is_dir ($configDir)) {
                    if (is_link ($configDir)) {throw new \RuntimeException ('Config symbolic links are not allowed.');}
                    foreach (scandir ($configDir) ?: [] as $file) {
                        if ($file === '.' || $file === '..') {continue;}
                        if (!in_array ($file, $allowed, true)) {
                            throw new \RuntimeException ("Unsupported or system-owned config: {$file}.");
                        }
                        $path = $configDir . DIRECTORY_SEPARATOR . $file;
                        if (is_link ($path) || !is_file ($path) || !is_readable ($path)) {
                            throw new \RuntimeException ("Unreadable or linked config: {$file}.");
                        }
                        $files[$name][$file] = $path;
                    }
                }
                $result['modules'][$name] = ['namespace' => (Registry::get ('nsmod') ?: 'Modules\\') . $name, 'depends_on' => [], 'routes' => true];
            } catch (\Throwable $e) {$result['errors'][] = "{$name}: {$e->getMessage()}";}
        }
        // Scan every filename before executing any PHP configuration.
        if ($result['errors'] !== []) {return self::$cache[$root] = $result;}
        foreach ($result['modules'] as $name => &$manifest) {
            try {
                if (isset ($files[$name]['modules.conf.php'])) {
                    $value = self::load ($files[$name]['modules.conf.php']);
                    if (array_diff (array_keys ($value), ['namespace', 'depends_on', 'routes']) !== []
                        || ($value['namespace'] ?? null) !== $manifest['namespace']
                        || !is_array ($value['depends_on'] ?? null) || !array_is_list ($value['depends_on'])
                        || !is_bool ($value['routes'] ?? null)) {
                        throw new \RuntimeException ('Manifest requires canonical namespace, depends_on list and boolean routes.');
                    }
                    foreach ($value['depends_on'] as $dependency) {
                        if (!is_string ($dependency) || !isset ($result['modules'][$dependency])) {
                            throw new \RuntimeException ('Unknown dependency; use an existing exact module name.');
                        }
                    }
                    if (count (array_unique ($value['depends_on'])) !== count ($value['depends_on'])) {
                        throw new \RuntimeException ('Duplicate dependency.');
                    }
                    $manifest = $value;
                }
            } catch (\Throwable $e) {$result['errors'][] = "{$name}: {$e->getMessage()}";}
        }
        unset ($manifest);
        $ordered = []; $visiting = [];
        $visit = function (string $name) use (&$visit, &$ordered, &$visiting, &$result): void {
            if (isset ($ordered[$name])) {return;}
            if (isset ($visiting[$name])) {throw new \RuntimeException ("Dependency cycle at {$name}.");}
            $visiting[$name] = true;
            $dependencies = $result['modules'][$name]['depends_on'];
            sort ($dependencies, SORT_STRING);
            foreach ($dependencies as $dependency) {$visit ($dependency);}
            unset ($visiting[$name]);
            $ordered[$name] = $result['modules'][$name];
        };
        if ($result['errors'] === []) {
            try {foreach (array_keys ($result['modules']) as $name) {$visit ($name);}}
            catch (\Throwable $e) {$result['errors'][] = $e->getMessage ();}
        }
        if ($result['errors'] !== []) {return self::$cache[$root] = $result;}
        $result['modules'] = $ordered;
        $aliases = get_config ('routing.aliases', []);
        $actions = get_config ('actions.nonce_actions', []);
        foreach ($ordered as $name => $manifest) {
            try {
                foreach ($files[$name] as $file => $path) {
                    if ($file === 'modules.conf.php') {continue;}
                    $value = self::load ($path);
                    if ($file === 'routing.conf.php') {
                        if (array_keys ($value) !== ['aliases'] || !is_array ($value['aliases'])) {
                            throw new \RuntimeException ('routing.conf.php permits only aliases.');
                        }
                        foreach ($value['aliases'] as $key => $target) {
                            if (array_key_exists ($key, $aliases)) {throw new \RuntimeException ("Duplicate route: {$key}.");}
                            RouteMiddleware::validateRoute ($key, $target);
                            $shape = preg_replace ('/\{[A-Za-z_][A-Za-z0-9_]*(:(?:int))?\}/', '{$1}', $key);
                            foreach (array_keys ($aliases) as $existing) {
                                if (preg_replace ('/\{[A-Za-z_][A-Za-z0-9_]*(:(?:int))?\}/', '{$1}', $existing) === $shape) {
                                    throw new \RuntimeException ("Duplicate route pattern: {$key}.");
                                }
                            }
                            $aliases[$key] = $target;
                            if ($manifest['routes']) {$result['aliases'][$key] = $target;}
                        }
                    } elseif ($file === 'actions.conf.php') {
                        if (array_keys ($value) !== ['nonce_actions'] || !is_array ($value['nonce_actions']) || !array_is_list ($value['nonce_actions'])) {
                            throw new \RuntimeException ('actions.conf.php requires nonce_actions list.');
                        }
                        foreach ($value['nonce_actions'] as $action) {
                            if (!is_string ($action) || !preg_match ('/^[A-Za-z0-9_-]+$/D', $action) || in_array ($action, $actions, true)) {
                                throw new \RuntimeException ('Invalid or duplicate nonce action.');
                            }
                            $actions[] = $action;
                            $result['actions'][] = $action;
                        }
                    } elseif ($file === 'menus.conf.php') {
                        if (array_diff (array_keys ($value), ['menus', 'submenus']) !== []) {throw new \RuntimeException ('Only menus and submenus are allowed.');}
                        $result['menus'][$name] = $value;
                    }
                }
            } catch (\Throwable $e) {$result['errors'][] = "{$name}: {$e->getMessage()}";}
        }
        if ($result['errors'] !== []) {$result['aliases'] = $result['menus'] = $result['actions'] = [];}
        return self::$cache[$root] = $result;
    }

    private static function load (string $path): array
    {
        ob_start ();
        try {$value = (static function (string $file) {return require $file;}) ($path);}
        finally {$output = ob_get_clean ();}
        if ($output !== '' || !is_array ($value)) {throw new \RuntimeException ('Config must return an array without output.');}
        return $value;
    }
}
