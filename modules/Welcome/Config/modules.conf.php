<?php declare (strict_types=1);

/**
 * Welcome module manifest (PLAN §8.4 A2).
 *
 * Discovered on every boot by System\Services\Module::discover(),
 * independent of modules.auto_discover (that toggle only gates
 * route/controller auto-discovery).
 *
 * @return array{namespace: string, depends_on: list<string>, routes: bool}
 */
return [
    // Canonical namespace mirrors Module::discover() exactly so the
    // manifest stays valid when the modules namespace prefix changes
    // (production 'Modules\', test harness overrides).
    'namespace'  => (\System\Core\Registry::get ('nsmod') ?: 'Modules\\') . 'Welcome',
    // Exact names of existing modules this module depends on; ordered
    // topologically by the discovery (cycles fail closed).
    'depends_on' => [],
    // false = manifest/config only; its routing contributions are ignored.
    'routes'     => true,
];
