<?php declare (strict_types=1);

/**
 * Subprocess runner for code paths that call exit() (ApiController::respond,
 * Auth gateway redirects). PHPUnit cannot survive exit, so ApiAuthGatewayTest
 * executes this file per scenario and asserts on stdout markers.
 *
 * Usage: php tests/support/subprocess.php <scenario>
 *
 * Markers printed at shutdown (registered before the code under test exits):
 *   __REDIRECT__=<url>   wp_safe_redirect() was called
 *   __STATUS__=<code>    final http_response_code() (empty when unset)
 */

require dirname (__DIR__) . '/bootstrap.php';

register_shutdown_function (static function (): void {
    $status = http_response_code ();
    echo '__STATUS__=' . ($status === false ? '' : $status) . "\n";
});

$scenario = $argv [1] ?? '';

switch ($scenario)
{
    case 'api-health':
        require_once dirname (__DIR__, 2) . '/system/Core/Api.core.php';
        require_once dirname (__DIR__, 2) . '/modules/Api/Controllers/Api.controller.php';
        \Modules\Api\Controllers\Api::health ();
        break;

    case 'api-user-guest':
        require_once dirname (__DIR__, 2) . '/system/Core/Api.core.php';
        require_once dirname (__DIR__, 2) . '/modules/Api/Controllers/Api.controller.php';
        $GLOBALS ['radykfw_test_logged_in'] = false;
        \Modules\Api\Controllers\Api::user ();
        break;

    case 'api-user-authenticated':
        require_once dirname (__DIR__, 2) . '/system/Core/Api.core.php';
        require_once dirname (__DIR__, 2) . '/modules/Api/Controllers/Api.controller.php';
        $GLOBALS ['radykfw_test_logged_in'] = true;
        $GLOBALS ['radykfw_test_current_user'] = (object) [
            'ID'           => 7,
            'display_name' => 'Ada',
            'user_email'   => 'ada@example.test',
        ];
        \Modules\Api\Controllers\Api::user ();
        break;

    case 'auth-guest':
        require_once dirname (__DIR__, 2) . '/system/Core/Private.core.php';
        require_once dirname (__DIR__, 2) . '/modules/Auth/Controllers/Auth.controller.php';
        $GLOBALS ['radykfw_test_logged_in'] = false;
        \Modules\Auth\Controllers\Auth::index ();
        break;

    case 'auth-user':
        require_once dirname (__DIR__, 2) . '/system/Core/Private.core.php';
        require_once dirname (__DIR__, 2) . '/modules/Auth/Controllers/Auth.controller.php';
        $GLOBALS ['radykfw_test_logged_in'] = true;
        \Modules\Auth\Controllers\Auth::index ();
        break;

    default:
        fwrite (STDERR, "Unknown scenario: {$scenario}\n");
        exit (2);
}
