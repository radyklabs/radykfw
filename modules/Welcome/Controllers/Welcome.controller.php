<?php declare(strict_types=1);

namespace Modules\Welcome\Controllers;

use System\Core\PublicController;

/**
 * Welcome controller
 * File: Welcome.controller.php
 */
class Welcome extends PublicController
{
    public static function index(): void
    {
        radykfw_asset('radykfw-inter-font', 'css/fonts/inter.css');
        view('index');
    }

    public static function home(string ...$args): void
    {
        view('home', ['args' => $args]);
    }

    public static function door(string ...$args): void
    {
        view('door', ['args' => $args]);
    }

    /**
     * Documentation page.
     * URL: /documentation (alias → Welcome\Welcome::documentation)
     */
    public static function documentation(): void
    {
        view('documentation');
    }

    /**
     * Changelog page.
     * URL: /changelog (alias → Welcome\Welcome::changelog)
     */
    public static function changelog(): void
    {
        view('changelog');
    }
}