<?php declare(strict_types=1);

namespace Modules\Welcome\Controllers;

/**
 * Welcome controller (static methods)
 * File: Welcome.controller.php
 */
class Welcome
{
    /**
     * Show welcome page.
     *
     * URL: /welcome
     * Fallback: if no exact method match, use index
     */
    public static function welcome(): void
    {
        view('modules/Welcome/Views/index.view');
    }

    public static function index(): void
    {
        self::welcome();
    }

    /**
     * URL: /welcome/home
     */
    public static function home(): void
    {
        view('modules/Welcome/Views/home.view');
    }

    /**
     * URL: /welcome/home/door (or any method after 2 segments)
     */
    public static function door(): void
    {
        view('modules/Welcome/Views/door.view');
    }
}