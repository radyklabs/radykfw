<?php declare(strict_types=1);

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * RadykFW Application Configuration
 * File: system/Config/app.conf.php
 *
 * Identity-level settings. Access via `get_config('app.debug')` dot-notation
 * (domain = file name, flat values — no top-level wrapper key).
 * Runtime settings split into domain-specific files (routing/auth/modules).
 *
 * @return array<string, mixed>
 */
return [
    'name'    => 'RadykFW',
    'version' => plugin_meta ('Version') ?? '1.0.1',
    'locale'  => null,
];
