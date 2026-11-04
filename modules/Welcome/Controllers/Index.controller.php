<?php declare(strict_types=1);

namespace Modules\Welcome\Controllers;

use System\Core\PublicController;

/**
 * Default controller for the Welcome module.
 *
 * File: Index.controller.php
 */
class Index extends PublicController
{
    public static function index(): void
    {
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
}
