<?php declare (strict_types=1);

/**
 * Pure-PHP global helper stubs for DripResolverTest.
 *
 * The Drip service and the view/path helpers call module_path(), system_path()
 * and current_module() from the System\Services namespace; PHP falls back to
 * the global namespace for unqualified function names, so guarded globals
 * here let production code run unmodified under PHPUnit. Definitions are
 * skipped when a real WordPress runtime has already provided them.
 */

if (!function_exists ('interlace'))
{
    require_once __DIR__ . '/../../.supports';
}

if (!function_exists ('trailingslashit'))
{
    function trailingslashit (string $string): string
    {
        return rtrim ($string, '/\\') . '/';
    }
}

if (!function_exists ('module_path'))
{
    function module_path (string $module = ''): string
    {
        $base = \System\Core\Registry::get ('modules');
        return $module ? trailingslashit ($base) . ltrim ($module, '/') : $base;
    }
}

if (!function_exists ('system_path'))
{
    function system_path (string $path = ''): string
    {
        $base = \System\Core\Registry::get ('system');
        return $path ? trailingslashit ($base) . ltrim ($path, '/') : $base;
    }
}

if (!function_exists ('current_module'))
{
    function current_module (): ?string
    {
        $value = \System\Core\Router::currentModule ();
        return $value !== '' ? $value : null;
    }
}
