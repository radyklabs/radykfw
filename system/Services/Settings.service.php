<?php declare (strict_types=1);

namespace System\Services;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * Settings — Persistent settings storage for RADYK products using WP Options API.
 *
 * Each product stores settings in its own namespace (e.g., 'radykfw_', 'radykthemes_').
 * Settings are stored as a single serialized array per namespace in wp_options.
 *
 * Usage:
 *   // Register a setting with sanitization
 *   Settings::registerSetting ('radykfw_', 'api_key', 'sanitize_text_field');
 *   Settings::registerSetting ('radykfw_', 'enabled_modules', fn ($v) => array_map ('sanitize_text_field', (array) $v));
 *
 *   // Get all settings for a namespace
 *   $settings = Settings::getSettings ('radykfw_');
 *
 *   // Update settings (merged with existing)
 *   Settings::setSettings ('radykfw_', ['api_key' => 'new-key']);
 */
final class Settings
{
    /** @var array<string, array<string, callable>> Sanitization callbacks keyed by namespace => key => callback. */
    private static array $sanitizers = [];

    /** @var array<string, array<string, mixed>> Cached settings keyed by namespace. */
    private static array $cache = [];

    /** @var array<string, bool> Tracks whether a namespace has been loaded from DB. */
    private static array $loaded = [];

    private function __construct () {}

    /**
     * Get the option name for a given namespace.
     *
     * @param string $namespace Product namespace (e.g., 'radykfw_', 'radykthemes_')
     * @return string Option name in wp_options table.
     */
    private static function optionName (string $namespace): string
    {
        return 'radyk_labs_settings_' . $namespace;
    }

    /**
     * Load settings from database for a namespace (if not already cached).
     *
     * @param string $namespace Product namespace.
     * @return array<string, mixed> Settings array (empty if none stored).
     */
    private static function load (string $namespace): array
    {
        if (isset (self::$loaded [$namespace])) {
            return self::$cache [$namespace] ?? [];
        }

        $option_name = self::optionName ($namespace);
        $stored = get_option ($option_name, []);

        // Ensure it's an array
        $settings = is_array ($stored) ? $stored : [];

        self::$cache [$namespace] = $settings;
        self::$loaded [$namespace] = true;

        return $settings;
    }

    /**
     * Save settings to database for a namespace.
     *
     * @param string $namespace Product namespace.
     * @param array<string, mixed> $settings Settings to save.
     * @return bool True on success, false on failure.
     */
    private static function save (string $namespace, array $settings): bool
    {
        $option_name = self::optionName ($namespace);
        $result = update_option ($option_name, $settings, false); // false = no autoload

        if ($result) {
            self::$cache [$namespace] = $settings;
        }

        return $result;
    }

    /**
     * Apply sanitization callbacks to settings array.
     *
     * @param string $namespace Product namespace.
     * @param array<string, mixed> $settings Raw settings.
     * @return array<string, mixed> Sanitized settings.
     */
    private static function sanitize (string $namespace, array $settings): array
    {
        $sanitizers = self::$sanitizers [$namespace] ?? [];
        $sanitized = [];

        foreach ($settings as $key => $value) {
            if (isset ($sanitizers [$key]) && is_callable ($sanitizers [$key])) {
                $sanitized [$key] = call_user_func ($sanitizers [$key], $value);
            } else {
                // Default: sanitize as text if no custom callback
                $sanitized [$key] = is_scalar ($value) ? sanitize_text_field ((string) $value) : $value;
            }
        }

        return $sanitized;
    }

    /**
     * Register a setting key with a sanitization callback for a namespace.
     *
     * @param string $namespace Product namespace (e.g., 'radykfw_', 'radykthemes_').
     * @param string $key Setting key.
     * @param callable $sanitize_callback Function that receives the raw value and returns sanitized value.
     * @return void
     */
    public static function registerSetting (string $namespace, string $key, callable $sanitize_callback): void
    {
        self::$sanitizers [$namespace] [$key] = $sanitize_callback;
    }

    /**
     * Get all settings for a namespace.
     *
     * @param string $namespace Product namespace (e.g., 'radykfw_', 'radykthemes_').
     * @return array<string, mixed> Settings array (empty if none stored).
     */
    public static function getSettings (string $namespace): array
    {
        return self::load ($namespace);
    }

    /**
     * Get a single setting value for a namespace.
     *
     * @param string $namespace Product namespace.
     * @param string $key Setting key.
     * @param mixed $default Default value if key not found.
     * @return mixed Setting value or default.
     */
    public static function getSetting (string $namespace, string $key, mixed $default = null): mixed
    {
        $settings = self::load ($namespace);
        return $settings [$key] ?? $default;
    }

    /**
     * Set (update) settings for a namespace. Merges with existing settings.
     *
     * @param string $namespace Product namespace (e.g., 'radykfw_', 'radykthemes_').
     * @param array<string, mixed> $settings New settings to merge.
     * @return bool True on success, false on failure.
     */
    public static function setSettings (string $namespace, array $settings): bool
    {
        $current = self::load ($namespace);
        $merged = array_merge ($current, $settings);
        $sanitized = self::sanitize ($namespace, $merged);

        return self::save ($namespace, $sanitized);
    }

    /**
     * Set a single setting value for a namespace.
     *
     * @param string $namespace Product namespace.
     * @param string $key Setting key.
     * @param mixed $value Setting value.
     * @return bool True on success, false on failure.
     */
    public static function setSetting (string $namespace, string $key, mixed $value): bool
    {
        return self::setSettings ($namespace, [$key => $value]);
    }

    /**
     * Delete a setting key for a namespace.
     *
     * @param string $namespace Product namespace.
     * @param string $key Setting key to delete.
     * @return bool True on success, false on failure.
     */
    public static function deleteSetting (string $namespace, string $key): bool
    {
        $current = self::load ($namespace);

        if (! array_key_exists ($key, $current)) {
            return true; // Already not set
        }

        unset ($current [$key]);
        return self::save ($namespace, $current);
    }

    /**
     * Delete all settings for a namespace (remove the option entirely).
     *
     * @param string $namespace Product namespace.
     * @return bool True on success, false on failure.
     */
    public static function deleteAllSettings (string $namespace): bool
    {
        $option_name = self::optionName ($namespace);
        $result = delete_option ($option_name);

        if ($result) {
            unset (self::$cache [$namespace], self::$loaded [$namespace]);
        }

        return $result;
    }

    /**
     * Check if a namespace has any settings stored.
     *
     * @param string $namespace Product namespace.
     * @return bool True if settings exist, false otherwise.
     */
    public static function hasSettings (string $namespace): bool
    {
        $settings = self::load ($namespace);
        return ! empty ($settings);
    }

    /**
     * Get all registered sanitizer keys for a namespace (for debugging/inspection).
     *
     * @param string $namespace Product namespace.
     * @return array<string> Array of setting keys that have custom sanitizers.
     */
    public static function getRegisteredKeys (string $namespace): array
    {
        return array_keys (self::$sanitizers [$namespace] ?? []);
    }

    /**
     * Clear internal cache for a namespace (forces reload on next getSettings).
     *
     * @param string $namespace Product namespace.
     * @return void
     */
    public static function clearCache (string $namespace): void
    {
        unset (self::$cache [$namespace], self::$loaded [$namespace]);
    }
}