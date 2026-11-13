<?php
/**
 * Welcome Module Custom Directives
 * File: modules/Welcome/Config/directives.conf.php
 *
 * Example custom directives for the Welcome module.
 * These are loaded automatically when the module is discovered.
 */
if (!defined('ABSPATH')) {
    exit;
}

return [
    // Single-line directive: @welcome_badge outputs a styled badge
    'welcome_badge' => [
        'handler' => function (string $args): string {
            $text = trim($args, " '\"");
            return 'echo "<span class=\"welcome-badge\">' . esc_html($text) . '</span>";';
        },
    ],

    // Conditional directive pair: @feature_available / @endfeature_available
    // Checks if a feature flag is enabled (example: from module config).
    // NB (M9): the argument is interpolated into compiled PHP — keep this a
    // literal identifier only; anything else is skipped.
    'feature_available' => [
        'handler' => function (string $args): string {
            $feature = trim($args, " '\"");
            if (!preg_match ('/^[A-Za-z_][A-Za-z0-9_]*$/D', $feature)) {return '';}
            return "if (\\Modules\\Welcome\\Config\\Features::isEnabled('{$feature}')):";
        },
        'close' => '@endfeature_available',
    ],

    // Single-line directive: @module_version outputs the module version
    'module_version' => [
        'handler' => function (string $args): string {
            return 'echo esc_html(\\Modules\\Welcome\\Config\\Manifest::version());';
        },
    ],

    // Conditional directive: @if_module_active / @endif_module_active
    // Checks if another module is active.
    // NB (M9): arg is interpolated into compiled PHP — identifier literal only.
    'if_module_active' => [
        'handler' => function (string $args): string {
            $module = trim($args, " '\"");
            if (!preg_match ('/^[A-Z][A-Za-z0-9_]*$/D', $module)) {return '';}
            return "if (\\System\\Services\\Module::isActive('{$module}')):";
        },
        'close' => '@endif_module_active',
    ],
];