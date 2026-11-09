<?php declare (strict_types=1);

namespace System\Services;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

final class I18n
{
    private const TEXT_DOMAIN = 'radykfw';

    private function __construct () {}

    public static function register (): void
    {
        add_filter ('plugin_locale', [self::class, 'filterPluginLocale'], 10, 2);
        add_action ('init', [self::class, 'loadTextDomain'], 0);
    }

    public static function loadTextDomain (): void
    {
        load_plugin_textdomain (
            self::TEXT_DOMAIN,
            false,
            dirname (plugin_basename (keyfile)) . '/languages'
        );
    }

    public static function filterPluginLocale (string $locale, string $domain): string
    {
        if ($domain !== self::TEXT_DOMAIN || ! function_exists ('is_admin') || ! is_admin ())
        {
            return $locale;
        }

        return self::resolveLocale (
            $locale,
            $domain,
            true,
            Settings::getConfigValue ('admin_locale', $locale),
            null
        );
    }

    public static function resolveLocale (
        string $locale,
        string $domain,
        bool $isAdmin,
        mixed $settingsLocale,
        mixed $appLocale
    ): string
    {
        if ($domain !== self::TEXT_DOMAIN || ! $isAdmin) {return $locale;}

        $configured_locale = $settingsLocale;
        if ($configured_locale === null || $configured_locale === '')
        {
            $configured_locale = $appLocale;
        }

        if ($configured_locale === null || $configured_locale === '') {return $locale;}

        if (! is_string ($configured_locale)
            || ! preg_match ('/^[A-Za-z]{2,3}(?:_[A-Za-z0-9]{2,8})*(?:@[A-Za-z0-9_-]+)?$/D', $configured_locale))
        {
            trigger_error (
                'RadykFW locale configuration must be a valid locale code.',
                E_USER_WARNING
            );
            return $locale;
        }

        return $configured_locale;
    }
}
