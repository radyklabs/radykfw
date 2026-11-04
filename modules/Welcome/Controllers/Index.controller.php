<?php declare(strict_types=1);

namespace Modules\Welcome\Controllers;

/**
 * Default controller for the Welcome module.
 *
 * File: Index.controller.php
 * Serves as the fallback controller for any /welcome route that does not
 * match a more specific controller (e.g. /welcome/home without a Home controller).
 *
 * Param style: variadic (string ...$args). The router maps URL segments 3+
 * to $args, so /welcome/index/home/args1/args2 -> home('args1','args2').
 */
class Index
{
    /**
     * Default route for /welcome (and /)
     */
    public static function index(): void
    {
        view('modules/Welcome/Views/index.view');
    }

    /**
     * Serves /welcome/home, /welcome/index/home, /welcome/index/home/args1/args2
     * URL segments after "home" land in ...$args (0, 1, or N segments).
     */
    public static function home(string ...$args): void
    {
        view('modules/Welcome/Views/home.view', ['args' => $args]);
    }

    /**
     * Serves /welcome/home/door, /welcome/index/home/door/args...
     * URL segments after "door" land in ...$args.
     */
    public static function door(string ...$args): void
    {
        view('modules/Welcome/Views/door.view', ['args' => $args]);
    }
}
