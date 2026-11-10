# RadykFW

<!-- Add the project logo here when available (for example: assets/images/radykfw-logo.svg). -->

[![tests](https://img.shields.io/github/actions/workflow/status/radyklabs/radykfw/php-lint.yml?job=tests&label=tests)](https://github.com/radyklabs/radykfw/actions/workflows/php-lint.yml)
[![phpstan](https://img.shields.io/github/actions/workflow/status/radyklabs/radykfw/php-lint.yml?job=phpstan&label=phpstan)](https://github.com/radyklabs/radykfw/actions/workflows/php-lint.yml)
[![Release](https://img.shields.io/github/v/release/radyklabs/radykfw)](https://github.com/radyklabs/radykfw/releases)
[![Downloads](https://img.shields.io/github/downloads/radyklabs/radykfw/total)](https://github.com/radyklabs/radykfw/releases)
[![License](https://img.shields.io/github/license/radyklabs/radykfw?cacheSeconds=300)](LICENSE)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B?logo=wordpress&logoColor=white)
[![GitHub stars](https://img.shields.io/github/stars/radyklabs/radykfw?cacheSeconds=300)](https://github.com/radyklabs/radykfw/stargazers)

RadykFW is a WordPress plugin for developing and evaluating an HMVC-style
architecture inside WordPress. It provides modular PHP conventions and
explicit routing while leaving WordPress in control of native site requests.

> **Project scope:** RadykFW is a WordPress plugin, not a standalone framework
> for building complete applications. The `Welcome` module is the primary
> built-in module; other modules are examples for exercising routing and are
> not complete, production-ready features.

## What it provides

- HMVC-style modules with controllers, models, and views.
- Explicit route aliases with HTTP-method matching.
- WordPress-first routing by default, so native pages and other WordPress
  requests remain under WordPress.
- A convention-based autoloader and split PHP configuration, with no Composer
  runtime dependency.
- WordPress admin settings and modular menu configuration.

## Requirements

- WordPress 6.0 or later
- PHP 8.1 or later

## Installation

1. Copy or clone this repository into `wp-content/plugins/radykfw/`.
2. In WordPress Admin, open **Plugins** and activate **RadykFW**.

No Composer installation or build step is required.

## Routing

RadykFW handles explicitly configured aliases. The default configuration gives
WordPress priority over native paths (`routing.native` is `true`) and disables
module auto-discovery (`modules.auto_discover` is `false`). Requests that do
not match an alias are left to WordPress.

Built-in aliases are owned by their modules (additive module config,
PLAN §8.4 A2):

| Method | Path | Purpose | Source |
| --- | --- | --- | --- |
| `GET` | `/welcome` | Welcome module landing page | `modules/Welcome/Config/routing.conf.php` |
| `GET` | `/documentation` | Welcome module documentation page | `modules/Welcome/Config/routing.conf.php` |
| `GET` | `/changelog` | Welcome module changelog page | `modules/Welcome/Config/routing.conf.php` |
| `GET` | `/login` | Login module example | `modules/Login/Config/routing.conf.php` |

To add an alias, edit the `aliases` array in the owning module's
`Config/routing.conf.php` (create the file if the module has none), or in
`system/Config/routing.conf.php` for framework-wide routes:

```php
'GET /my-module' => 'MyModule\MyModule::index',
```

The target above resolves to
`Modules\MyModule\Controllers\MyModule::index()`. Keep routes explicit unless
you intentionally enable auto-discovery in `system/Config/modules.conf.php`.

## Creating a module

Modules use the `Modules\{Module}` namespace and convention-based file names.
A minimal module can look like this:

```text
modules/
└── MyModule/
    ├── Controllers/
    │   └── MyModule.controller.php
    └── Views/
        └── index.view.php
```

Controller:

```php
<?php declare(strict_types=1);

namespace Modules\MyModule\Controllers;

final class MyModule
{
    public static function index(): void
    {
        view('index');
    }
}
```

The `view('index')` helper resolves the view from the current module. Use
`system_view()` for framework-owned templates. Escape dynamic values when
rendering them in a view, using the appropriate WordPress escaping function
such as `esc_html()` or `esc_attr()`.

## Configuration

Runtime configuration is in `system/Config/`:

- `routing.conf.php` — route aliases and WordPress routing behavior.
- `modules.conf.php` — module auto-discovery.
- `menus.conf.php` — default admin menus; modules can provide menu
  configuration of their own.
- `auth.conf.php` and `settings.conf.php` — authentication and settings.

## License

RadykFW is distributed under the [MIT License](LICENSE).

## Maintainer

Developed by [RADYK Labs](https://radyklabs.com).

## Architecture extensions (PLAN 8.4)

Module configuration is discovered even when controller auto-discovery is disabled.
Use singular `modules/Example/Config/`; migrate legacy `Configs/menus.conf.php` explicitly (rejected with a diagnostic, never silently loaded). Existing modules without manifests retain defaults.

`Config/modules.conf.php` returns:

```php
return ['namespace' => 'Modules\\Example', 'depends_on' => [], 'routes' => true];
```

Namespace must match the existing autoloader prefix plus directory name. Dependencies use exact existing module names, sorted topologically with lexical tie-breaking; missing dependencies/cycles fail validation. `routes=false` disables dispatch to that module, not its menu/action contributions. Config files are trusted PHP, not an untrusted upload format. Symlinks, unknown filenames and system-owned config files are rejected before executing any module configuration.

Only additive `routing.conf.php` (`aliases`), `menus.conf.php` (`menus`, `submenus`), and `actions.conf.php` (`nonce_actions` list) are accepted. No generic config merge: `get_config()` remains system-only. Duplicate routes/actions fail; menu validation rejects duplicate slugs without replacing system menus.

`Config/routing.conf.php` example:

```php
return ['aliases' => [
    'GET /example' => ['target' => 'Example::index', 'middleware' => ['auth']],
]];
```

String aliases remain supported. Middleware names resolve through `system/Config/middleware.conf.php`. Implement `System\Contracts\MiddlewareInterface::allow(array $route): bool` with a public zero-argument constructor; names execute in declaration order. The whole chain validates before execution. False consumes the request; exceptions/configuration errors produce a generic HTTP 500, never fall through to WordPress or another route. Built-in `auth` permits logged-in users, redirects others to WordPress login with a same-origin return URL; it does not grant capabilities. Controllers still enforce authorization/nonces. Middleware attached to a target also guards direct auto-discovery and other aliases targeting that method, preventing alternate-URL bypass. Multiple declarations on the same target compose conservatively.

Commands return JSON by default; `--format=json` is explicit. Validation errors exit nonzero. Commands never execute controllers or middleware.

```shell
wp radykfw list-modules --format=json
wp radykfw test-routes --format=json
wp radykfw debug-route /example --method=GET --format=json
wp radykfw make-module Example --format=json
wp help radykfw make-module
```

`debug-route` reports alias candidates and auto-discovery setting, not WordPress content ownership or authorization outcomes. `test-routes` validates declarations and public controller targets. The generator exclusively creates Config manifest, controller and view; it never overwrites, edits system config, or activates an alias. Reserved scaffold names: app, assets, auth, helper, settings, system, modules (case-insensitive). Add an explicit alias afterwards.
