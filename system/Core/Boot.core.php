<?php declare (strict_types=1);

namespace System\Core;

use System\Core\Registry;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

final class Boot
{
    private static ?self $instance = null;

    private function __construct ()
    {
        initiate_process ('Registry');
        $this->autoloader ();
        initiate_process ('Runner');
    }

    public static function instance (): self
    {
        if (is (self::$instance, null)) {self::$instance = new self();}
        return self::$instance;
    }

    /**
     * Boot is entry point + autoloader ONLY.
     *
     * Load order (option A):
     *   1. manual-require Registry (pre-autoload exception - no deps, tiny)
     *   2. register SPL autoloader
     *   3. manual-require Runner + run (Runner may now rely on the autoloader)
     */
    private function autoloader (): void
    {
        spl_autoload_register (function (string $class): void
        {
            static $reg = null;
            if ($reg === null)
            {
                $needed = ['nssys', 'nsmod', 'ext_maps', 'system', 'modules'];
                foreach ($needed as $nd) {$reg[$nd] = Registry::get ($nd);}
            }

            if (str_starts_with ($class, $reg['nssys']) 
                || str_starts_with ($class, $reg['nsmod']))
            {
                $suffix = 'Controller';
                if (str_starts_with ($class, $reg['nssys']) 
                    && str_ends_with ($class, $suffix))
                {
                    $class = substr ($class, 0, -strlen ($suffix));
                }

                $parts = explode ('\\', $class);
                $lower_root = strtolower (array_shift ($parts));
                $classname = array_pop ($parts);
                $level = end ($parts);
                $path = implode ('\\', $parts);

                $orchestrator = $classname.$reg['ext_maps'][$lower_root][$level];
                $file = interlace ($reg[$lower_root], $path, $orchestrator);

                if (is_readable ($file)) {require_once $file;}
                return;
            }
        });
    }
}
