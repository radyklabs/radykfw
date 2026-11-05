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
        radykfw_asset('radykfw-inter-font', 'fonts/inter.css');
        view('index');
    }

    public static function home(): void
    {
        view('home');
    }

    public static function door(): void
    {
        view('door');
    }
}