<?php declare(strict_types=1);

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
    'name'       => 'RadykFW',
    'version'    => version ?? '1.0.1',
    'debug'      => defined('WP_DEBUG') && WP_DEBUG,
    'timezone'   => 'UTC',
    'locale'     => 'en_US',
];
