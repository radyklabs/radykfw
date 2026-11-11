<?php declare (strict_types=1);

namespace System\Services;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * Drip — template engine service (DR1: API, resolvers, renderer seam).
 *
 * Identifiers are logical, never filesystem paths:
 *   module: file.drip             -> modules/{Module}/Views/file.view.php
 *   system: pages.dashboard.drip  -> system/Templates/pages/dashboard.page.php
 *   error:  404.drip              -> system/Templates/errors/404.error.html
 *
 * Dot-notation maps to subfolders ('partials.card' -> Views/partials/card.view.php);
 * a Capitalized first segment selects an explicit module — the same rule as
 * resolve_module_view(). The '.drip' suffix selects the Drip renderer in the
 * view helpers; the physical file extension never changes.
 *
 * Absolute paths, '..' traversal and null bytes are rejected. A missing
 * template throws with the logical identifier only — never a filesystem path,
 * and never a silent fallback to the 404 template.
 */
final class Drip
{
    private function __construct () {}

    /**
     * Resolve a kind-prefixed identifier and render it, returning output.
     *
     * @param string $identifier 'module:file.drip' | 'system:pages.dashboard.drip' | 'error:404.drip'
     * @param array  $data       Extracted into the render scope (EXTR_SKIP)
     * @throws \InvalidArgumentException Unknown kind prefix or unsafe identifier
     * @throws \RuntimeException Template missing
     */
    public static function render (string $identifier, array $data = []): string
    {
        if (str_starts_with ($identifier, 'module:'))
        {
            $file = self::resolveModule ($identifier);
        }
        elseif (str_starts_with ($identifier, 'system:'))
        {
            $file = self::resolveSystem ($identifier);
        }
        elseif (str_starts_with ($identifier, 'error:'))
        {
            $file = self::resolveError ($identifier);
        }
        else
        {
            throw new \InvalidArgumentException (
                "Drip: template identifier must start with 'module:', 'system:' or 'error:': '{$identifier}'"
            );
        }

        return self::execute ($file, $data);
    }

    /**
     * Resolve a module view identifier to an absolute path.
     *
     * @throws \InvalidArgumentException Invalid or unsafe identifier
     * @throws \RuntimeException Template missing (logical identifier only)
     */
    public static function resolveModule (string $identifier): string
    {
        $id      = self::normalize ($identifier);
        $kind    = 'module view';
        self::guard ($id, $kind, $identifier);

        $parts   = explode ('.', $id);
        $current = \current_module ();

        if (count ($parts) >= 2)
        {
            $first     = $parts [0];
            $viewName  = end ($parts);
            $explicit  = ctype_upper ($first [0]);
            $module    = $explicit ? $first : $current;

            // Strict casing rule: Capitalized first segment = explicit module,
            // lowercase = subfolder of the current module (legacy parity).
            $subfolder = $explicit
                ? (count ($parts) > 2 ? implode ('/', array_slice ($parts, 1, -1)) : '')
                : implode ('/', array_slice ($parts, 0, -1));

            if ($module)
            {
                $file = \module_path ($module) . '/Views/'
                    . ($subfolder ? $subfolder . '/' : '') . $viewName . '.view.php';
                if (is_file ($file)) {return $file;}
            }
        }
        elseif (($current = \current_module ()))
        {
            $file = \module_path ($current) . '/Views/' . $parts [0] . '.view.php';
            if (is_file ($file)) {return $file;}
        }

        throw new \RuntimeException ("Drip: template not found: '{$id}' ({$kind})");
    }

    /**
     * Resolve a system template identifier to an absolute path.
     *
     * Dot-notation maps to subfolders ('pages.dashboard' -> pages/dashboard);
     * slash paths ('pages/dashboard') resolve exactly as the legacy resolver.
     *
     * @throws \InvalidArgumentException Invalid or unsafe identifier
     * @throws \RuntimeException Template missing (logical identifier only)
     */
    public static function resolveSystem (string $identifier): string
    {
        $id   = self::normalize ($identifier);
        $kind = 'system template';
        self::guard ($id, $kind, $identifier);

        $forms = [$id];
        if (! str_contains ($id, '/'))
        {
            $converted = str_replace ('.', '/', $id);
            if ($converted !== $id) {$forms [] = $converted;}
        }

        foreach ($forms as $form)
        {
            foreach (['.page.php', '.html', '.php'] as $extension)
            {
                $file = \system_path ('Templates/' . $form . $extension);
                if (is_file ($file)) {return $file;}
            }
        }

        throw new \RuntimeException ("Drip: template not found: '{$id}' ({$kind})");
    }

    /**
     * Resolve an error template identifier to an absolute path.
     * '404' and '404.drip' both resolve to system/Templates/errors/404.error.html.
     *
     * @throws \InvalidArgumentException Invalid or unsafe identifier
     * @throws \RuntimeException Template missing (logical identifier only)
     */
    public static function resolveError (string $identifier): string
    {
        $id   = self::normalize ($identifier);
        $kind = 'error template';
        self::guard ($id, $kind, $identifier);

        $file = \system_path ("Templates/errors/{$id}.error.html");
        if (is_file ($file)) {return $file;}

        throw new \RuntimeException ("Drip: template not found: '{$id}' ({$kind})");
    }

    /**
     * Render a resolved template file and return its output.
     *
     * ponytail: DR2 swaps this direct include for the Blade-inspired compiler
     * plus a compiled-cache lookup (compile on source change).
     */
    private static function execute (string $file, array $data): string
    {
        ob_start ();
        extract ($data, EXTR_SKIP);
        include $file;
        return (string) ob_get_clean ();
    }

    /**
     * Strip the optional kind prefix and '.drip' suffix from an identifier.
     */
    private static function normalize (string $identifier): string
    {
        $id = $identifier;
        foreach (['module:', 'system:', 'error:'] as $prefix)
        {
            if (str_starts_with ($id, $prefix))
            {
                $id = substr ($id, strlen ($prefix));
                break;
            }
        }

        if (str_ends_with ($id, '.drip')) {$id = substr ($id, 0, -strlen ('.drip'));}

        return $id;
    }

    /**
     * Reject unsafe template identifiers: absolute paths, traversal, null bytes.
     * Messages carry the logical identifier only — never a filesystem path.
     *
     * @throws \InvalidArgumentException
     */
    private static function guard (string $identifier, string $kind, string $display = ''): void
    {
        $shown = $display !== '' ? $display : $identifier;

        if ($identifier === '')
        {
            throw new \InvalidArgumentException ("Drip: empty {$kind} identifier.");
        }

        if (str_contains ($identifier, "\0"))
        {
            throw new \InvalidArgumentException ("Drip: null byte in {$kind} identifier: '{$shown}'");
        }

        if (preg_match ('#^(?:[A-Za-z]:|/|\\\\)#', $identifier) === 1)
        {
            throw new \InvalidArgumentException ("Drip: absolute path in {$kind} identifier: '{$shown}'");
        }

        if (str_contains ($identifier, '..'))
        {
            throw new \InvalidArgumentException ("Drip: path traversal in {$kind} identifier: '{$shown}'");
        }

        if (str_contains ($identifier, ':'))
        {
            throw new \InvalidArgumentException ("Drip: invalid character in {$kind} identifier: '{$shown}'");
        }

        foreach (preg_split ('#[./\\\\]#', $identifier) ?: [] as $segment)
        {
            if ($segment === '')
            {
                throw new \InvalidArgumentException ("Drip: empty segment in {$kind} identifier: '{$shown}'");
            }
        }
    }
}
