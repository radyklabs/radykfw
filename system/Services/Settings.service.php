<?php declare (strict_types=1);

namespace System\Services;

use InvalidArgumentException;
use RuntimeException;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * Settings — Persistent site/user settings and settings-page handlers.
 *
 * Each product stores settings in its own namespace (e.g., 'radykfw_', 'radykthemes_').
 * General settings are stored in a namespaced WP option or user meta. Existing
 * shared RADYK product settings continue to use the namespace-based API.
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
    private const CONFIG_OPTION = 'radykfw_config';
    private const USER_CONFIG_META = 'radykfw_config';
    private const CONFIG_SCHEMA = [
        'admin_locale' => [
            'type' => 'locale',
            'scopes' => ['site', 'user'],
            'sensitive' => false,
        ],
        'routing.native' => [
            'type' => 'boolean',
            'scopes' => ['site'],
            'sensitive' => false,
        ],
        'modules.auto_discover' => [
            'type' => 'boolean',
            'scopes' => ['site'],
            'sensitive' => false,
        ],
    ];

    /** @var array<string, array<string, callable>> Sanitization callbacks keyed by namespace => key => callback. */
    private static array $sanitizers = [];

    /** @var array<string, array<string, mixed>> Cached settings keyed by namespace. */
    private static array $cache = [];

    /** @var array<string, bool> Tracks whether a namespace has been loaded from DB. */
    private static array $loaded = [];

    private function __construct () {}

    public static function registerHooks (): void
    {
        add_action ('admin_post_radykfw_save_settings', [self::class, 'handleSaveRequest']);
        add_action ('admin_post_radykfw_export_settings', [self::class, 'handleExportRequest']);
    }

    /**
     * Resolve an allowlisted configuration value without changing get_config()'s
     * static file-only behavior.
     */
    public static function getConfigValue (string $key, mixed $default = null, ?int $userId = null): mixed
    {
        if (! isset (self::CONFIG_SCHEMA[$key]))
        {
            throw new InvalidArgumentException ("Configuration key is not registered: {$key}");
        }

        if ($key === 'admin_locale')
        {
            $appLocale = get_config ('app.locale');
            if (is_string ($appLocale) && $appLocale !== '') {$default = $appLocale;}
        }
        elseif ($default === null)
        {
            $default = get_config ($key);
        }

        $effective = self::validatedLayerValue ($key, $default, 'default');
        $manual = self::manualOverride ($key);
        if ($manual !== null)
        {
            $resolved = self::validatedLayerValue ($key, $manual, 'settings.conf.php');
            if ($resolved !== null) {$effective = $resolved;}
        }

        $siteSettings = get_option (self::CONFIG_OPTION, []);
        if (! is_array ($siteSettings))
        {
            trigger_error ('RadykFW site settings must be stored as an array.', E_USER_WARNING);
            $siteSettings = [];
        }
        if (array_key_exists ($key, $siteSettings) && $siteSettings[$key] !== null)
        {
            $resolved = self::validatedLayerValue ($key, $siteSettings[$key], 'site settings');
            if ($resolved !== null) {$effective = $resolved;}
        }

        $userId ??= function_exists ('get_current_user_id') ? (int) get_current_user_id () : 0;
        if ($userId > 0 && in_array ('user', self::CONFIG_SCHEMA[$key]['scopes'], true))
        {
            $userSettings = get_user_meta ($userId, self::USER_CONFIG_META, true);
            if ($userSettings === '' || $userSettings === null) {$userSettings = [];}
            if (! is_array ($userSettings))
            {
                trigger_error ('RadykFW user settings must be stored as an array.', E_USER_WARNING);
                $userSettings = [];
            }
            if (array_key_exists ($key, $userSettings) && $userSettings[$key] !== null)
            {
                $resolved = self::validatedLayerValue ($key, $userSettings[$key], 'user settings');
                if ($resolved !== null) {$effective = $resolved;}
            }
        }

        return $effective;
    }

    /**
     * Read persisted settings restricted to registered keys and the given scope.
     *
     * @return array<string, mixed>
     */
    public static function getSavedConfig (string $scope, ?int $userId = null): array
    {
        if (! in_array ($scope, ['site', 'user'], true))
        {
            throw new InvalidArgumentException ('Settings scope must be site or user.');
        }
        if ($scope === 'user')
        {
            $userId ??= function_exists ('get_current_user_id') ? (int) get_current_user_id () : 0;
            if ($userId < 1) {throw new InvalidArgumentException ('A valid user is required for user settings.');}
        }

        $stored = $scope === 'site'
            ? get_option (self::CONFIG_OPTION, [])
            : get_user_meta ($userId, self::USER_CONFIG_META, true);
        if ($stored === '' || $stored === null) {$stored = [];}
        if (! is_array ($stored))
        {
            trigger_error ("RadykFW {$scope} settings must be stored as an array.", E_USER_WARNING);
            return [];
        }
        return self::filterBackupSettings ($stored, $scope);
    }

    /**
     * Validate values accepted by the Settings UI and its persistence layer.
     */
    public static function validateConfigValue (string $key, mixed $value): mixed
    {
        if (! isset (self::CONFIG_SCHEMA[$key]))
        {
            throw new InvalidArgumentException ("Configuration key is not registered: {$key}");
        }

        if ($value === null || $value === '')
        {
            return null;
        }

        return match (self::CONFIG_SCHEMA[$key]['type']) {
            'locale' => self::validateLocale ($value),
            'boolean' => self::validateBoolean ($value),
        };
    }

    /**
     * Persist only registered keys in a declared scope.
     *
     * @param array<string, mixed> $values
     */
    public static function saveConfig (string $scope, array $values, ?int $userId = null): bool
    {
        if (! in_array ($scope, ['site', 'user'], true))
        {
            throw new InvalidArgumentException ('Settings scope must be site or user.');
        }
        if ($scope === 'user')
        {
            $userId ??= function_exists ('get_current_user_id') ? (int) get_current_user_id () : 0;
            if ($userId < 1) {throw new InvalidArgumentException ('A valid user is required for user settings.');}
        }

        foreach ($values as $key => $value)
        {
            if (! is_string ($key)
                || ! isset (self::CONFIG_SCHEMA[$key])
                || ! in_array ($scope, self::CONFIG_SCHEMA[$key]['scopes'], true))
            {
                throw new InvalidArgumentException ('Settings contain a key that is not allowed in this scope.');
            }
            $values[$key] = self::validateConfigValue ($key, $value);
        }

        $stored = $scope === 'site'
            ? get_option (self::CONFIG_OPTION, [])
            : get_user_meta ($userId, self::USER_CONFIG_META, true);
        if ($stored === '' || $stored === null) {$stored = [];}
        if (! is_array ($stored))
        {
            trigger_error ("RadykFW {$scope} settings must be stored as an array.", E_USER_WARNING);
            $stored = [];
        }

        $allowedKeys = array_keys (array_filter (
            self::CONFIG_SCHEMA,
            static fn (array $definition): bool => in_array ($scope, $definition['scopes'], true)
        ));
        $updated = array_intersect_key ($stored, array_flip ($allowedKeys));
        foreach ($values as $key => $value)
        {
            if ($value === null) {unset ($updated[$key]);}
            else {$updated[$key] = $value;}
        }

        if ($scope === 'site')
        {
            if ($updated === [])
            {
                $deleted = delete_option (self::CONFIG_OPTION);
                return $deleted || get_option (self::CONFIG_OPTION, []) === [];
            }
            $saved = update_option (self::CONFIG_OPTION, $updated, false);
            return $saved || get_option (self::CONFIG_OPTION, []) === $updated;
        }

        if ($updated === [])
        {
            $deleted = delete_user_meta ($userId, self::USER_CONFIG_META);
            $remaining = get_user_meta ($userId, self::USER_CONFIG_META, true);
            return $deleted || $remaining === [] || $remaining === '' || $remaining === null;
        }
        $saved = update_user_meta ($userId, self::USER_CONFIG_META, $updated);
        return $saved !== false || get_user_meta ($userId, self::USER_CONFIG_META, true) === $updated;
    }

    /**
     * Export non-sensitive allowlisted settings as a versioned JSON backup.
     */
    public static function createBackup (int $userId): string
    {
        if (! function_exists ('current_user_can') || ! current_user_can ('manage_options'))
        {
            throw new RuntimeException ('Insufficient permissions to export RadykFW settings.');
        }
        if (! function_exists ('get_current_user_id') || $userId < 1 || $userId !== (int) get_current_user_id ())
        {
            throw new RuntimeException ('Only the current administrator preferences can be exported.');
        }

        $siteSettings = get_option (self::CONFIG_OPTION, []);
        $userSettings = get_user_meta ($userId, self::USER_CONFIG_META, true);
        if (! is_array ($siteSettings))
        {
            trigger_error ('RadykFW site settings must be stored as an array.', E_USER_WARNING);
            $siteSettings = [];
        }
        if ($userSettings === '' || $userSettings === null) {$userSettings = [];}
        if (! is_array ($userSettings))
        {
            trigger_error ('RadykFW user settings must be stored as an array.', E_USER_WARNING);
            $userSettings = [];
        }

        $siteSettings = self::filterBackupSettings ($siteSettings, 'site');
        $userSettings = self::filterBackupSettings ($userSettings, 'user');
        $json = wp_json_encode ([
            'schema_version' => 1,
            'plugin' => [
                'name' => 'RadykFW',
                'version' => get_config ('app.version', 'unknown'),
            ],
            'exported_at' => gmdate (DATE_ATOM),
            'site_settings' => $siteSettings,
            'user_settings' => $userSettings,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string ($json)) {throw new RuntimeException ('Could not encode the settings backup.');}
        return $json;
    }

    public static function handleSaveRequest (): void
    {
        if (! current_user_can ('manage_options')) {wp_die (esc_html__ ('Insufficient permissions', 'radykfw'));}

        $rawScope = isset ($_POST['scope']) ? wp_unslash ($_POST['scope']) : null;
        if (! is_string ($rawScope)) {wp_die (esc_html__ ('Invalid settings scope.', 'radykfw'));}
        $scope = sanitize_key ($rawScope);
        $nonceAction = match ($scope) {
            'site', 'user' => 'radykfw_save_' . $scope . '_settings',
            default => '',
        };
        if ($nonceAction === '') {wp_die (esc_html__ ('Invalid settings scope.', 'radykfw'));}
        check_admin_referer ($nonceAction);

        $submitted = isset ($_POST['config']) ? wp_unslash ($_POST['config']) : null;
        if (! is_array ($submitted)) {wp_die (esc_html__ ('Invalid settings submitted.', 'radykfw'));}

        $allowedKeys = array_keys (array_filter (
            self::CONFIG_SCHEMA,
            static fn (array $definition): bool => in_array ($scope, $definition['scopes'], true)
        ));
        if (array_diff (array_keys ($submitted), $allowedKeys) !== [])
        {
            wp_die (esc_html__ ('One or more submitted settings are not allowed.', 'radykfw'));
        }

        $values = [];
        foreach ($allowedKeys as $key)
        {
            if (! array_key_exists ($key, $submitted))
            {
                wp_die (esc_html__ ('One or more settings are missing from the request.', 'radykfw'));
            }
            $value = $submitted[$key];
            if (self::CONFIG_SCHEMA[$key]['type'] === 'boolean')
            {
                if (in_array ($value, ['1', 1], true)) {$value = true;}
                elseif (in_array ($value, ['0', 0], true)) {$value = false;}
                else {wp_die (esc_html__ ('A boolean setting contains an invalid value.', 'radykfw'));}
            }
            $values[$key] = $value;
        }

        try
        {
            $saved = self::saveConfig ($scope, $values);
        }
        catch (InvalidArgumentException $exception)
        {
            wp_die (esc_html ($exception->getMessage()));
        }

        if (! $saved) {wp_die (esc_html__ ('Settings could not be saved.', 'radykfw'));}

        $redirected = wp_safe_redirect (add_query_arg (
            ['page' => 'radyk-settings', 'settings-updated' => $scope],
            admin_url ('admin.php')
        ));
        if (! $redirected) {wp_die (esc_html__ ('Settings were saved, but the admin page redirect failed.', 'radykfw'));}
        exit;
    }

    public static function handleExportRequest (): void
    {
        if (! current_user_can ('manage_options')) {wp_die (esc_html__ ('Insufficient permissions', 'radykfw'));}
        check_admin_referer ('radykfw_export_settings');

        $filename = 'radykfw-settings-' . gmdate ('Ymd-His') . '.json';
        nocache_headers ();
        header ('X-Content-Type-Options: nosniff');
        header ('Content-Type: application/json; charset=utf-8');
        header ('Content-Disposition: attachment; filename="' . $filename . '"');
        echo self::createBackup ((int) get_current_user_id ());
        exit;
    }

    private static function validateLocale (mixed $value): string
    {
        if (! is_string ($value)
            || strlen ($value) > 64
            || ! preg_match ('/^[A-Za-z]{2,3}(?:_[A-Za-z0-9]{2,8})*(?:@[A-Za-z0-9_-]+)?$/D', $value))
        {
            throw new InvalidArgumentException ('The locale must be a valid locale code.');
        }
        return $value;
    }

    private static function validateBoolean (mixed $value): bool
    {
        if (is_bool ($value)) {return $value;}
        if (in_array ($value, [0, '0'], true)) {return false;}
        if (in_array ($value, [1, '1'], true)) {return true;}
        throw new InvalidArgumentException ('Boolean settings must be true or false.');
    }

    private static function validatedLayerValue (string $key, mixed $value, string $source): mixed
    {
        if ($value === null || $value === '') {return null;}
        try
        {
            return self::validateConfigValue ($key, $value);
        }
        catch (InvalidArgumentException $exception)
        {
            trigger_error ("Invalid RadykFW {$key} value in {$source}.", E_USER_WARNING);
            return null;
        }
    }

    private static function manualOverride (string $key): mixed
    {
        $settings = get_config ('settings', []);
        if (! is_array ($settings))
        {
            trigger_error ('RadykFW settings.conf.php must return an array.', E_USER_WARNING);
            return null;
        }
        if ($key === 'admin_locale') {return $settings['locale'] ?? null;}

        $value = $settings['overrides'] ?? [];
        foreach (explode ('.', $key) as $segment)
        {
            if (! is_array ($value) || ! array_key_exists ($segment, $value)) {return null;}
            $value = $value[$segment];
        }
        return $value;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private static function filterBackupSettings (array $settings, string $scope): array
    {
        $filtered = [];
        foreach ($settings as $key => $value)
        {
            if (! is_string ($key)
                || ! isset (self::CONFIG_SCHEMA[$key])
                || self::CONFIG_SCHEMA[$key]['sensitive']
                || ! in_array ($scope, self::CONFIG_SCHEMA[$key]['scopes'], true))
            {
                continue;
            }
            try
            {
                $filtered[$key] = self::validateConfigValue ($key, $value);
            }
            catch (InvalidArgumentException)
            {
                trigger_error ("Invalid RadykFW {$scope} backup setting was excluded.", E_USER_WARNING);
                continue;
            }
        }
        return $filtered;
    }

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
