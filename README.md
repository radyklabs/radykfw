# RadykFW

<!-- Add the project logo here when available (for example: assets/images/radykfw-logo.svg). -->

[![Latest tag](https://img.shields.io/github/v/tag/radyklabs/radykfw?label=latest%20tag)](https://github.com/radyklabs/radykfw/tags)
[![License](https://img.shields.io/github/license/radyklabs/radykfw)](LICENSE)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B?logo=wordpress&logoColor=white)
[![GitHub stars](https://img.shields.io/github/stars/radyklabs/radykfw)](https://github.com/radyklabs/radykfw/stargazers)

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

The built-in aliases in `system/Config/routing.conf.php` are:

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/welcome` | Welcome module landing page |
| `GET` | `/documentation` | Welcome module documentation page |
| `GET` | `/changelog` | Welcome module changelog page |
| `GET` | `/login` | Login module example |

To add an alias, edit the `aliases` array in
`system/Config/routing.conf.php`:

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

## Configuration and project documentation

Runtime configuration is in `system/Config/`:

- `routing.conf.php` — route aliases and WordPress routing behavior.
- `modules.conf.php` — module auto-discovery.
- `menus.conf.php` — default admin menus; modules can provide menu
  configuration of their own.
- `auth.conf.php` and `settings.conf.php` — authentication and settings.

For project requirements, roadmap, audit notes, and release history, see:

- [Product requirements](docs/PRD.md)
- [Development plan](docs/PLAN.md)
- [Project report](docs/REPORT.md)
- [Changelog](docs/CHANGELOG.md)

## Development

Run the smoke check with PHP:

```bash
php tests/bootstrap.php
```

If PHPUnit is available locally, run the test suite with:

```bash
phpunit -c phpunit.xml
```

## License

RadykFW is distributed under the [MIT License](LICENSE).

## Maintainer

Developed by [RADYK Labs](https://radyklabs.com).
