<?php declare(strict_types=1);

namespace Modules\Dashboard\Controllers;

use System\Core\PrivateController;

/**
 * Dashboard module — requires login.
 * File: Dashboard.controller.php
 * URL: /dashboard
 */
class Dashboard extends PrivateController
{
    /**
     * Show dashboard page.
     *
     * URL: /dashboard
     */
    public static function index(): void
    {
        // Auth check enforced by requireAuth() (called automatically if needed)
        self::requireAuth();

        view('index');
    }

    /**
     * Stats endpoint for AJAX calls within dashboard.
     *
     * URL: /dashboard/stats
     */
    public static function stats(): void
    {
        self::requireAuth();
        view('stats');
    }
}
