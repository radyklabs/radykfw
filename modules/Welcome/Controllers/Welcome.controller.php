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
        
            $features = [
                [
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect></svg>',
                    'title' => esc_html__('HMVC Architecture', 'radykfw'),
                    'description' => esc_html__('Hierarchical Model-View-Controller for scalable, maintainable code organization.', 'radykfw'),
                ],
                [
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M12 1v6m0 6v6M1 12h6m6 0h6"></path></svg>',
                    'title' => esc_html__('Custom Routing', 'radykfw'),
                    'description' => esc_html__('Intercept and handle custom routes before WordPress native routing.', 'radykfw'),
                ],
                [
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.37 3.13a2.12 2.12 0 0 1 3 3l-7 7a2.12 2.12 0 0 1-3 0l-7-7a2.12 2.12 0 0 1 0-3l7-7a2.12 2.12 0 0 1 3 0z"></path></svg>',
                    'title' => esc_html__('Modular Design', 'radykfw'),
                    'description' => esc_html__('Self-contained modules with their own controllers, models, and views.', 'radykfw'),
                ],
                [
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 19 22 12 13 5 13 19"></polygon><polyline points="2 22 13 19 2 16"></polyline></svg>',
                    'title' => esc_html__('High Performance', 'radykfw'),
                    'description' => esc_html__('Lightweight core with intelligent caching for minimal overhead.', 'radykfw'),
                ],
                [
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
                    'title' => esc_html__('Drip Template Engine', 'radykfw'),
                    'description' => esc_html__('Blade-inspired template engine with layouts, components, and directives.', 'radykfw'),
                ],
                [
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 14.21 3 7 7 0 0 0 21 12.79z"></path></svg>',
                    'title' => esc_html__('Developer Experience', 'radykfw'),
                    'description' => esc_html__('WP-CLI scaffolding, middleware, and type-safe PHP 8.3+ codebase.', 'radykfw'),
                ],
            ];
        
            view('index.drip', ['features' => $features]);
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
