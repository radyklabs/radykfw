<?php declare(strict_types=1);

namespace System\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contract for Admin UI modules.
 *
 * Modules implementing this contract can register themselves
 * with the shared Admin UI (top-level menu, sub-menus, assets).
 */
interface AdminUIInterface
{
    /**
     * Return menu configuration for this module.
     *
     * @return array<string, mixed> Menu config with keys:
     *   - slug: string (unique menu slug)
     *   - title: string (display title)
     *   - capability: string (required capability, default 'manage_options')
     *   - icon: string (dashicon or URL, optional)
     *   - position: int|float (menu position, optional)
     *   - submenus: array<int, array<string, mixed>> (optional sub-menu items)
     */
    public static function register_menu(): array;

    /**
     * Render the admin page for the given slug.
     *
     * @param string $slug The menu slug to render
     * @return void
     */
    public static function render_page(string $slug): void;

    /**
     * Enqueue admin assets (CSS/JS) for this module.
     *
     * @return void
     */
    public static function enqueue_assets(): void;
}