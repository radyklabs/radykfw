# RadykFW - WordPress HMVC Framework

A powerful **Hierarchical Model-View-Controller (HMVC)** framework for WordPress with custom routing that intercepts requests before WordPress native handling.

## Features

- 🎯 **Custom Routing** - Intercept `/login`, `/welcome`, or any path before WordPress
- 🏗️ **HMVC Architecture** - Self-contained modules with Controllers, Models, Views, Services
- ⚡ **High Performance** - Static methods, convention-based autoloading, minimal overhead
- 🔧 **Developer Friendly** - PHP 8.1+, strict types, comprehensive docs
- 🎨 **Design System** - Built-in components, layouts, error views
- 📦 **Zero Config** - Works out of the box, extensible when needed

## Installation

```bash
# Manual: copy to wp-content/plugins/radykfw
# Standalone plugin — no runtime dependencies (custom autoloader, not Composer)
```

Activate the plugin in WordPress Admin → Plugins.

## Quick Start

### 1. Module Structure

```
modules/
└── MyModule/
    ├── Controllers/
    │   └── MyController.controller.php
    ├── Models/
    │   └── MyModel.model.php
    ├── Views/
    │   └── my-view.view.php
    └── Services/
        └── MyService.service.php
```

### 2. Controller Example

```php
<?php declare(strict_types=1);
// modules/MyModule/Controllers/MyController.controller.php

namespace Modules\MyModule\Controllers;

class MyController
{
    /**
     * URL: /my-module
     * Routing: module=MyModule, controller=MyController, method=myController → index
     */
    public static function myController(): void
    {
        view('modules/MyModule/Views/my-view.view');
    }

    /**
     * Fallback index method.
     */
    public static function index(): void
    {
        self::myController();
    }

    /**
     * URL: /my-module/my-controller/custom-method
     */
    public static function customMethod(string $param1 = '', string $param2 = ''): void
    {
        // $param1, $param2 come from URL segments 4, 5, etc.
        view('modules/MyModule/Views/custom.view', [
            'param1' => $param1,
            'param2' => $param2,
        ]);
    }
}
```

### 3. View Example

```php
<?php
// modules/MyModule/Views/my-view.view.php

if (!defined('ABSPATH')) {
    exit;
}
?>
<h1>My Module</h1>
<p>Welcome to my custom module!</p>
```

## How It Works

### Routing Flow

```
Request → parse_request hook (priority 10)
    ↓
Parse URL into segments: /welcome/home/door → ['welcome', 'home', 'door']
    ↓
Validate segments: /^[A-Za-z_][A-Za-z0-9_]*$/ — reject if invalid → WP 404
    ↓
Router::handle(segments)
    ↓
Auto-Discovery Logic:
  - 0 segments    → Welcome module → Welcome::index
  - 1 segment     → /module → Module::index
  - 2 segments    → /module/controller → Controller::index
  - 3+ segments   → /module/controller/method/params... → Controller::method (params)
    ↓
Fallback (0–1 segments only): if named controller not found → module's Index controller
  (2+ segments: no fallback → unknown routes fall through to WordPress 404)
    ↓
Load class: Modules\{Module}\Controllers\{Controller}
    ↓
Call static method with params
    ↓
If found → render view & exit
If NOT found → return false → WordPress native handling
```

### Routing Examples

| URL | Module | Controller | Method | Params |
|-----|--------|------------|--------|--------|
| `/` | Welcome | Welcome | index | [] |
| `/login` | Login | Login | index | [] |
| `/welcome` | Welcome | Welcome | index | [] |
| `/welcome/home` | Welcome | Home | index | [] |
| `/welcome/home/door` | Welcome | Home | door | [] |
| `/welcome/home/door/p1/p2` | Welcome | Home | door | ['p1', 'p2'] |
| `/welcome/home/unknown` | — | — | — | 404 (no method `unknown`) |

## File Structure

```
radykfw/
├── radykfw.php              # Main plugin entry
├── PRD.md                   # Product Requirements
├── CHANGELOG.md             # Version history
├── README.md                # This file
├── .gitignore
├── tests/                   # Dev-QA test suite (pure-PHP, no Composer)
├── phpunit.xml
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── modules/
│   ├── Login/
│   │   ├── Controllers/
│   │   │   ├── Index.controller.php
│   │   │   └── Login.controller.php
│   │   ├── Models/
│   │   │   └── Login.model.php
│   │   ├── Views/
│   │   │   └── login.view.php
│   │   └── Services/
│   └── Welcome/
│       ├── Controllers/
│       │   └── Welcome.controller.php
│       ├── Models/
│       ├── Views/
│       │   ├── index.view.php
│       │   ├── home.view.php
│       │   └── door.view.php
│       └── Services/
└── system/
    ├── config/
    │   └── app.conf.php
    ├── contracts/
    │   └── Dispatchable.contract.php
    ├── core/
    │   └── Router.core.php
    ├── enums/
    ├── helpers/
    │   └── system.helper.php
    ├── services/
    └── views/
        ├── errors/
        │   └── 404.error.html
        ├── components/
        │   └── button.component.php
        ├── layouts/
        └── pages/
```

## File Naming Conventions

| Type | Extension | Example |
|------|-----------|---------|
| Config | `.conf.php` | `app.conf.php` |
| Contracts (Interface/Trait) | `.contract.php` | `Dispatchable.contract.php` |
| Core Classes | `.core.php` | `Router.core.php` |
| Enums | `.enum.php` | `RouteType.enum.php` |
| Helpers | `.helper.php` | `system.helper.php` |
| Services | `.service.php` | `AuthService.service.php` |
| Controllers | `.controller.php` | `Login.controller.php` |
| Models | `.model.php` | `Login.model.php` |
| Views (standard) | `.view.php` | `login.view.php` |
| Views (drip/blade) | `.drip.php` | `template.drip.php` |
| Error Views | `.error.html` | `404.error.html` |
| Components | `.component.php` | `button.component.php` |
| Layouts | `.layout.php` | `base.layout.php` |
| Pages | `.page.php` | `dashboard.page.php` |

## Namespace Convention

- **System**: `System\{Folder}\{ClassName}` → `system/{folder}/{ClassName}.core.php`
- **Modules**: `Modules\{Module}\{Folder}\{ClassName}` → `modules/{Module}/{Folder}/{ClassName}.{extension}.php`

Examples:
- `System\Core\Router` → `system/core/Router.core.php`
- `Modules\Login\Controllers\Login` → `modules/Login/Controllers/Login.controller.php`
- `Modules\Welcome\Models\User` → `modules/Welcome/Models/User.model.php`

## Helper Functions

```php
// Get plugin instance
radykfw();

// Paths
module_path('Login');              // /path/to/modules/Login/
system_path('config/app.conf.php'); // /path/to/system/config/app.conf.php
asset_url('css/style.css');        // https://site.com/wp-content/plugins/radykfw/assets/css/style.css

// Views (module views)
view('login', ['data' => $data]);
view('folder.home', ['data' => $data]);
view('Login.home', ['data' => $data]);

// System templates
system_view('errors/404.error', ['title' => 'Not Found']);
system_view('components/button', ['text' => 'Click Me']);

// Config
config('app.debug');               // From system/config/app.conf.php

// Utilities
module_url('welcome', 'home');     // https://site.com/welcome/home
current_module();                  // 'login' or 'welcome'
is_radykfw_request();              // true if handled by RadykFW
```

## Configuration

Edit `system/config/app.conf.php`:

```php
return [
    'app' => [
        'name' => 'RadykFW',
        'version' => '1.0.0',
        'debug' => defined('WP_DEBUG') && WP_DEBUG,
    ],
    'modules' => [
        'auto_discover' => true,
        'namespace_prefix' => 'Modules',
    ],
    'routing' => [
        'skip_paths' => ['wp-admin', 'wp-login.php', 'wp-json'],
    ],
];
```

## Built-in Modules

### Login Module (`/login`)

Custom login page that replaces `wp-login.php`:
- ✅ Beautiful, accessible design
- ✅ Remember me functionality
- ✅ Rate limiting protection (via model)
- ✅ Nonce verification
- ✅ Redirect after login

### Welcome Module (`/welcome`, `/welcome/home`, `/welcome/home/door`)

Landing page with:
- ✅ Hero section with CTA
- ✅ Feature grid
- ✅ Responsive design
- ✅ Dark mode support

## System Templates

Reusable templates in `system/Templates/`:

```
system/Templates/
├── errors/
│   └── 404.error.html         # Beautiful 404 page
├── components/
│   └── button.component.php   # Button component
├── layouts/
│   └── base.layout.php        # Base layout (future)
└── pages/
    └── dashboard.page.php     # Dashboard page (future)
```

Usage:
```php
system_view('errors/404.error', ['title' => 'Not Found']);
system_view('components/button.component', [
    'text' => 'Click Me',
    'variant' => 'primary',
    'url' => '/action'
]);
```

## Auth Flow

Post-login redirect flow (configured in `system/Config/app.conf.php`):
```
/login/authenticate (POST, valid credentials)
    → wp_signon() success
    → redirect to /auth (auth.post_login_url)
    → Auth::index() validates session (is_user_logged_in)
        → valid   → /dashboard
        → invalid → /login
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

**RadykFW** - Build modular WordPress applications with modern HMVC architecture.