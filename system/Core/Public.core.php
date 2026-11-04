<?php declare (strict_types=1);

namespace System\Core;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * Base controller for public (unauthenticated) routes.
 *
 * Module controllers that serve guest-facing pages extend this:
 *
 *     namespace Modules\Dashboard;
 *
 *     class DashboardController extends \System\Core\PublicController {}
 *
 * @since 1.0.1
 */
abstract class PublicController
{
    /**
     * Get the current module name (set by Router before dispatch).
     *
     * @return string Module name, empty string if not in a dispatch context
     */
    protected static function module (): string
    {
        return \System\Core\Router::currentModule ();
    }
}
