# RadykFW - WordPress HMVC Framework

A **Hierarchical Model-View-Controller (HMVC)** plugin for WordPress with
custom routing. Alias routes are explicit claims; auto-discovery is a
secondary fallback. WordPress-native paths take priority
(`routing.native=true` by default).

## Features

- 🎯 **Custom Routing** — alias router: explicit route aliases take
  priority; WordPress-native paths win (`routing.native=true`)
- 🏗️ **HMVC Architecture** — self-contained modules with
  Controllers, Models, Views, Services
- ⚡ **High Performance** — convention-based autoloading (no Composer at
  runtime), strict types, zero runtime dependencies
- 🔧 **Developer Friendly** — PHP 8.1+, split config files
- 📦 **Standalone** — no runtime dependencies; custom SPL autoloader

## Installation

```bash
# Copy the plugin folder to: wp-content/plugins/radykfw/
# No Composer install required — the framework uses its own autoloader.
```

Activate the plugin in WordPress Admin → Plugins.

## Quick Start

### 1. Module Structure

```
modules/
└── MyModule/
    ├── Controllers/
    │   └── MyModule.controller.php
    ├── Models/
    │   └── Post.model.php
    └── Views/
        └── index.view.php
```

### 2. Controller Example

```php
<?php declare (strict_types=1);
// modules/MyModule/Controllers/MyModule.controller.php

namespace Modules\MyModule\Controllers;

final class MyModule
{
    /**
     * URL: /my-module  (module name, controller = module, method = index)
     */
    public static function index (): void
    {
        view ('index');
    }

    /**
     * URL: /my-module/detail/42  (module/controller/method + params)
     */
    public static function detail (int $id = 0, string ...$args): void
    {
        view ('detail', ['id' => $id, 'args' => $args]);
    }
}
```

> Convention (R11): when a controller is not explicitly named, the
> **module name is the controller name** (`Modules\MyModule\Controllers\MyModule`).
> `index` is the default method.

### 3. View Example

```php
<?php
// modules/MyModule/Views/index.view.php

if (!defined ('ABSPATH')) {exit;}
?>
<h1>My Module</h1>
<p>Welcome to my custom module!</p>
```

> `view('index')` resolves the view within the current module
> (`Modules\MyModule\Views\index.view.php`). Use `system_view('errors/404.error')`
> for framework templates.

## How It Works

### Routing Flow

```
Request → parse_request hook (priority 10)
    ↓
Runner::shouldSkipRouting() — admin/AJAX/REST/static → fall through to WP
    ↓
Runner::tryAliases() — match REQUEST_URI against routing.aliases
  (exact first, then {seg} / {seg:int} patterns)
    ↓
If alias matched → dispatch to Module\Controller::method → exit
    ↓
If not matched:
  routing.native=true  → WordPress owns the path unless no real WP content
  (Runner::wpHasRealContent()) exists, then auto-discovery runs
  routing.native=false → auto-discovery runs first
    ↓
Auto-discovery: Router::handle(segments)
  - 0 segments    → get_config('routing.default','Welcome') module, method index
                    (only reached when routing.native=false)
  - 1 segment     → /module → Module::index
  - 2 segments    → /module/controller → Module\Controller::index
  - 3+ segments   → /module/controller/method/params... → Module\Controller::method($params...)
                    (controller = module when a controller segment is absent)
    ↓
If a route resolves → set $wp->query_vars['radykfw_handled'] → exit
If NOT found        → fall through → WordPress native handling / 404
```

> **Framework = alias router (QA decision):** explicit aliases are the
> primary routing surface. Auto-discovery is a secondary fallback, and
> `routing.native=true` lets WordPress win native paths first.

### Routing Examples (actual aliases in `routing.conf.php`)

| URL | Route | Target |
|-----|-------|--------|
| `/login` | alias | `Modules\Login\Controllers\Login::login()` |
| `/welcome` | alias | `Modules\Welcome\Controllers\Welcome::index()` |
| `/documentation` | alias | `Welcome::documentation()` |
| `/changelog` | alias | `Welcome::changelog()` |
| `/` | auto-discovery (native=true → WP) | WordPress root |

> With `routing.native=true`, WordPress-native paths (posts/pages/terms)
> are served by WordPress; RadykFW only claims the alias routes above.

## File Structure

```
radykfw/
├── radykfw.php               # Main plugin entry (WP header, boot)
├── PRD.md                    # Product Requirements
├── docs/
│   ├── CHANGELOG.md          # Version history
│   ├── PLAN.md               # Roadmap
│   ├── PRD.md                # Product Requirements
│   └── REPORT.md             # Audit report
├── README.md
├── .gitignore
├── .starter                  # Boot constants (dirs, appinfo) — pre-load
├── .supports                 # Core support fns (get_config, interlace, pathify, …)
├── tests/                    # Dev-QA test suite (pure-PHP, no Composer)
│   ├── bootstrap.php
│   ├── SmokeTest.php
│   └── SkipPathsRegressionTest.php
├── phpunit.xml
├── assets/
│   ├── css/
│   └── js/
├── modules/
│   ├── Api/                  # /api endpoints (health, user, posts)
│   │   ├── Controllers/
│   │   │   └── Api.controller.php
│   │   └── Models/
│   │       └── Posts.model.php
│   ├── Auth/                 # /auth post-login gateway
│   │   └── Controllers/
│   │       └── Auth.controller.php
│   ├── Dashboard/            # /dashboard + /dashboard/stats
│   │   ├── Controllers/
│   │   │   └── Dashboard.controller.php
│   │   ├── Models/
│   │   │   └── Stats.model.php
│   │   └── Views/
│   │       ├── index.view.php
│   │       └── stats.view.php
│   ├── Login/                # /login custom login
│   │   ├── Controllers/
│   │   │   └── Login.controller.php
│   │   └── Views/
│   │       └── login.view.php
│   └── Welcome/              # /welcome + landing
│       ├── Controllers/
│       │   └── Welcome.controller.php
│       └── Views/
│           ├── index.view.php
│           ├── home.view.php
│           ├── door.view.php
│           ├── documentation.view.php
│           └── changelog.view.php
└── system/
    ├── Config/
    │   ├── app.conf.php      # app block only (name, version, debug, …)
    │   ├── auth.conf.php     # auth block
    │   ├── modules.conf.php  # modules block
    │   ├── routing.conf.php  # routing block
    │   └── helper.conf.php   # which helpers to load
    ├── Contracts/
    │   └── Dispatchable.contract.php
    ├── Core/
    │   ├── Api.core.php
    │   ├── Boot.core.php     # entry + autoloader
    │   ├── Database.core.php
    │   ├── Model.core.php    # Eloquent-lite base
    │   ├── QueryBuilder.core.php
    │   ├── Record.core.php
    │   ├── Relation.core.php
    │   ├── Registry.core.php
    │   ├── Router.core.php
    │   └── Runner.core.php   # routing + skip-logic
    ├── Enum/
    ├── Helpers/
    │   ├── asset.helper.php  # asset_url, asset_path, radykfw_asset
    │   ├── path.helper.php   # module_path, system_path, module_url, current_module
    │   └── view.helper.php   # view, system_view
    ├── Services/
    └── Templates/
        └── errors/
            └── 404.error.html
```

> **Note:** `system/` sub-directories are **PascalCase**
> (`Config/`, `Core/`, `Contracts/`, `Helpers/`, `Templates/`, …),
> matching `.starter` `config_dir`/`core_dir`/… constants.

## File Naming Conventions

| Type | Extension | Example |
|------|-----------|---------|
| Config | `.conf.php` | `routing.conf.php`, `auth.conf.php` |
| Contracts (Interface/Trait) | `.contract.php` | `Dispatchable.contract.php` |
| Core Classes | `.core.php` | `Router.core.php`, `Registry.core.php` |
| Enums | `.enum.php` | `RouteType.enum.php` |
| Helpers | `.helper.php` | `view.helper.php`, `asset.helper.php` |
| Controllers | `.controller.php` | `Login.controller.php` |
| Models | `.model.php` | `Posts.model.php` |
| Views | `.view.php` | `login.view.php` |
| Error Views | `.error.html` | `404.error.html` |
| Services | `.service.php` | `AuthService.service.php` |

## Namespace Convention

- **System**: `System\{Folder}\{ClassName}` → `system/{Folder}/{ClassName}.core.php`
- **Modules**: `Modules\{Module}\{Folder}\{ClassName}` → `modules/{Module}/{Folder}/{ClassName}.{extension}.php`

Examples:
- `System\Core\Router` → `system/Core/Router.core.php`
- `Modules\Login\Controllers\Login` → `modules/Login/Controllers/Login.controller.php`
- `Modules\Api\Models\Posts` → `modules/Api/Models/Posts.model.php`

## Helper Functions

```php
// Paths (system/Helpers/path.helper.php)
module_path('Login');         // …/modules/Login/
system_path('Core/Router.core.php');
module_url('welcome', 'home'); // https://site.com/welcome/home
current_module();             // 'welcome' (set by Router during dispatch)

// Views (system/Helpers/view.helper.php)
view('index');                // current module's index view
view('door', ['args' => $a]); // current module's door view
system_view('errors/404.error'); // framework template

// Assets (system/Helpers/asset.helper.php)
asset_url('css/style.css');   // absolute asset URL
asset_path('css/style.css');  // absolute asset path
radykfw_asset('radykfw-login', 'css/modules/login.css'); // enqueue

// Config (loaded from .supports)
get_config('routing.aliases');    // full routing domain
get_config('auth.post_login_url'); // dot-notation into auth.conf.php
get_config('routing.native');      // routing.conf.php['native']
```

> **Note:** `get_config()` (in `.supports`) is the **only** config
> accessor. The old `config()` helper was removed (PLAN C5).

> **Note:** `modules.auto_discover` defaults to `false` in
> `modules.conf.php` — only alias routes resolve; unknown paths fall
> through to WordPress. Set to `true` to enable auto-discovery.

## Configuration

Config is **split by domain** (`system/Config/`):

| File | Contents | Access |
|------|----------|--------|
| `app.conf.php` | `name`, `version`, `debug`, `timezone`, `locale` | `get_config('app.debug')` |
| `routing.conf.php` | `native`, `default`, `aliases`, `skip_paths`, `skip_extensions` | `get_config('routing.aliases')` |
| `auth.conf.php` | `post_login_url`, `post_logout_url`, `login_url` | `get_config('auth.post_login_url')` |
| `modules.conf.php` | `auto_discover` | `get_config('modules.auto_discover')` |

```php
// system/Config/app.conf.php
return [
    'name'     => 'RadykFW',
    'version'  => version ?? '1.0.1',
    'debug'    => defined ('WP_DEBUG') && WP_DEBUG,
    'timezone' => 'UTC',
];

// system/Config/routing.conf.php (excerpt)
return [
    'native'       => true,                 // WordPress-first gate
    'default'      => 'Welcome',
    'aliases'      => [
        'GET /login'        => 'Login\Login::login',
        'GET /welcome'      => 'Welcome\Welcome::index',
        'GET /documentation'=> 'Welcome\Welcome::documentation',
        'GET /changelog'    => 'Welcome\Welcome::changelog',
    ],
    'skip_paths'   => ['wp-admin','wp-login','wp-signup','wp-activate','wp-json','xmlrpc','feed','sitemap'],
    'skip_extensions' => ['css','js','jpg','png','svg','ico','woff','woff2'],
];
```

## Built-in Modules

| Module | Routes | Notes |
|--------|--------|-------|
| **Login** (`/login`) | alias → `Login::login()` | Custom login page, nonce verification, session-based redirect flow (`/auth`) |
| **Auth** (`/auth`) | alias → `Auth::index()` | Post-login gateway: valid → `/dashboard`, invalid → `/login` |
| **Welcome** (`/welcome`) | alias + auto-discovery | Landing + `/welcome/home`, `/welcome/home/door/{a}/{b}` (param support) |
| **Documentation** (`/documentation`) | alias → `Welcome::documentation()` | Placeholder docs page |
| **Changelog** (`/changelog`) | alias → `Welcome::changelog()` | Placeholder changelog page |
| **Dashboard** (`/dashboard`, `/dashboard/stats`) | auto-discovery | Sample module with Stats model |
| **Api** (`/api/*`) | auto-discovery | `health`, `user`, `posts` endpoints |

## System Templates

Reusable framework templates in `system/Templates/`:

```
system/Templates/
└── errors/
    └── 404.error.html
```

Usage:
```php
system_view('errors/404.error', ['title' => 'Not Found']);
```

## Auth Flow

Post-login redirect flow (configured in `system/Config/auth.conf.php`):
```
/login (GET) → Login::login() → render login view
    ↓
POST credentials → Login::authenticate() → wp_signon() success
    ↓
Redirect to /auth (auth.post_login_url)
    ↓
Auth::index() validates session (is_user_logged_in)
    → valid   → /dashboard (auth.post_login_url success path)
    → invalid → /login (auth.login_url)
```

## Development

Dev-QA tooling is pure-PHP — no Composer required. Tests run via a lightweight
bootstrap (no WordPress runtime needed):

```bash
# Run the test suite (requires phpunit installed locally, e.g. via phar)
phpunit -c phpunit.xml

# Manual smoke check without phpunit:
php tests/bootstrap.php
```

Code style / static analysis: run `phpcs`/`phpstan` directly if available —
this project does not depend on Composer at runtime.

## Requirements

- WordPress 6.0+
- PHP 8.1+

## License

MIT

## Credits

Developed by [RADYK Labs](https://radyklabs.com)

---

**RadykFW** — Build modular WordPress applications with modern HMVC architecture.