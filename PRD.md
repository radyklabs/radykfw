# Product Requirements Document: RadykFW - WordPress HMVC Framework

## 1. Overview
**Product Name**: RadykFW  
**Type**: WordPress Plugin (HMVC Framework)  
**Version**: 1.0.1 (updated)  
**Goal**: Provide a modular HMVC architecture for WordPress with custom routing that intercepts requests before WordPress native handling.

---

## 2. Problem Statement
WordPress native routing:
- `/login` → redirects to `wp-login.php`
- `/welcome` → 404 page (no native page)

Developers need a way to handle custom routes via modules without modifying theme files or using page templates.

---

## 3. Solution: HMVC Framework with Custom Routing

### Core Architecture (Referencing miniradf structure)
```
radykfw/
├── assets/                    # CSS, JS, images (public)
├── modules/                   # HMVC modules
│   └── {Module}/
│       ├── Controllers/       # Controller files (*.controller.php)
│       ├── Models/            # Model files (*.model.php)
│       ├── Views/             # View files (*.view.php)
│       └── Services/          # Service files (*.service.php)
├── system/                    # Framework core
│   ├── config/                # Configuration files (*.conf.php)
│   ├── contracts/             # Interfaces & Traits (*.contract.php)
│   ├── core/                  # Core classes (*.core.php)
│   ├── enums/                 # Enums (*.enum.php)
│   ├── helpers/               # Helper functions (*.helper.php)
│   ├── services/              # Services (*.service.php)
│   └── templates/             # Shared templates (was "views")
│       ├── errors/            # Error pages (*.error.html)
│       ├── components/        # Components (*.component.php)
│       ├── layouts/           # Layouts (*.layout.php)
│       └── pages/             # Pages (*.page.php)
└── radykfw.php                # Main plugin file
```
---

## 4. MVP Scope (Routing Only)

### 4.1 Routing Interception
- **Hook**: `parse_request` (priority 10)
- **Logic**:
  1. Parse current request path into segments
  2. Validate all segments against `/^[A-Za-z_][A-Za-z0-9_]*$/` — reject if invalid
  3. Apply auto-discovery routing logic:
     - 0 segments → default module (Welcome) → `Welcome::index`
     - 1 segment → `/module` → `Module::index`
     - 2 segments → `/module/controller` → `Controller::index`
     - 3+ segments → `/module/controller/method/params...` → `Controller::method` with params
  4. Fallback rules:
     - 0–1 segments: if named controller/method not found, fall back to module's `Index` controller
     - 2+ segments: no fallback — unknown routes return `false` (WordPress 404)
  5. If dispatched → `exit` via `template_redirect` (early); if not → return `false` (WordPress handles natively)

### 4.2 Module Structure
Each module contains:
```
modules/Login/
├── Controllers/
│   ├── Index.controller.php
│   └── Login.controller.php
├── Models/
│   └── Login.model.php
├── Views/
│   ├── login.view.php
│   └── register.view.php
└── Services/
    └── AuthService.service.php
```

### 4.3 Bootstrap Flow (new)
1. `radykfw.php` loads **.supports** first (provides debugging helpers, `__dump`, etc.).
2. Loads **.starter** (defines `$radykfw` config array and registers constants via `RadykFW_Config`).
3. Calls **Boot.core.php** (`RadykFW\System\Core\Boot`) which now reads configuration from `RadykFW_Config` registry instead of global constants.
4. Autoloader registers namespaces using values from `RadykFW_Config`.
5. Activation/Deactivation hooks use plugin key from `RadykFW_Config`.

---

## 5. Technical Requirements

### 5.1 WordPress Integration
- Plugin header with proper metadata (updated version 1.0.1)
- Activation/deactivation hooks (flush rewrite rules)
- `parse_request` hook for early interception
- No template_redirect usage beyond exit after routing

---

## 6. Non-Functional Requirements
| Requirement | Target |
|-------------|--------|
| Performance | < 5ms overhead per request |
| Memory | < 2MB additional |
| Compatibility | WP 6.0+, PHP 8.1+ |
| Extensibility | Easy to add new modules |

---

## 7. View & Template System

### 7.1 View Resolution (Module Views)
Module views use `view()` with strict casing rule:
```php
view('home')          # current module -> modules/{current}/Views/home.view.php
view('folder.home')   # current module + subfolder -> modules/{current}/Views/folder/home.view.php
view('Login.home')    # explicit module -> modules/Login/Views/home.view.php
```

### 7.2 System Templates
System templates use `system_view()` -- explicit path under `system/Templates/`:
```php
system_view('errors/404.error')  # -> system/Templates/errors/404.error.html
system_view('components/button') # -> system/Templates/components/button.component.php
```

No ambiguity: `view()` = modules, `system_view()` = system templates.

---

## 8. Model & Data Layer (Planned — Post-MVP)

> **Note:** This section describes the planned architecture for database access and models. No implementation exists yet.

### 8.1 Base Model (`Model.core.php`)

**Need:** Module models are currently empty. A base `System\Core\Model` class is needed:
- Extendable by `Modules\{Module}\Models\{Name}.model.php`
- Provides access to Registry/paths
- Optional: thin CRUD wrappers over `$wpdb`

**Proposed design:**
```php
// system/Core/Model.core.php
abstract class Model
{
    protected static function module(): string { return \System\Core\Router::currentModule(); }
    protected static function db(): \wpdb { global $wpdb; return $wpdb; }
    // Optional: find(), findAll(), create(), update(), delete() thin wrappers
}
```

### 8.2 Database Abstraction / Re-brand WP Query

**Need:** "Rebrand all database action functions of WordPress" — a cleaner API over `$wpdb`.

**Approach:** Dedicated `Database.core.php` wrapper over `$wpdb` with:
- `select()`, `insert()`, `update()`, `delete()`, `transaction()`
- Simple query builder (optional)
- No full ORM — stay close to SQL for performance/control

**Why separate `Database.core.php`?** Explicit separation of concerns; `Model` injects `Database` instance. Enables "re-brand all database action" with a clean API like `Database::table('posts')->where(...)->get()`.

### 8.3 ORM / Data Mapping (Pragmatic)

**Constraint:** Full ORM (Eloquent/Doctrine) is overkill for a standalone WP plugin. WP has fixed table structures (`wp_posts`, `wp_postmeta`, etc.) — full ORM would require complex mapping.

**Pragmatic approach:** "Light Active Record" in `Model` + `Database` wrapper. No migrations, no relationship loading.

**Proposed structure:**
```
system/Core/
├── Database.core.php   # $wpdb wrapper + minimal query builder
└── Model.core.php      # Abstract base, injects Database, CRUD helpers

modules/{Module}/Models/
├── Post.model.php      # extends Model, table = 'wp_posts'
└── User.model.php      # extends Model, table = 'wp_users'
```

### 8.4 Implementation Decision Summary

| Component | Status | Priority |
|-----------|--------|----------|
| `Model.core.php` | Planned (when first model needed) | Medium |
| `Database.core.php` | Planned (prerequisite for Model) | Medium-High |
| Full ORM | **No** — overkill | No |
| `$wpdb` wrapper in Model | Yes (via Database) | Yes |
| Migration system | No (WP handles schema) | No |

---

## 8. Future Enhancements (Post‑MVP)
- Admin UI for module management
- REST API endpoints per module
- Module dependencies & autoloading (Composer)
- CLI commands (WP‑CLI)
- Marketplace/installer
- Middleware support (auth, rate limit, etc.)
- Drip template engine integration

---

## 8. Acceptance Criteria (MVP)
1. ✅ Plugin activates without errors
2. ✅ Access `/login` → loads `Modules\Login\Controllers\Login::login()` if exists
3. ✅ Access `/welcome` → loads `Modules\Welcome\Controllers\Welcome::welcome()` if exists
4. ✅ Access `/welcome/home` → loads `Modules\Welcome\Controllers\Home::home()`
5. ✅ Access `/welcome/home/door` → loads `Modules\Welcome\Controllers\Home::door()`
6. ✅ Access `/login` without module → redirects to `wp-login.php`
7. ✅ Access `/welcome` without module → shows 404 (WP native)
8. ✅ Module with full HMVC structure works (Controller, Model, View, Services)
9. ✅ No conflicts with existing WP routes
10. ✅ Proper uninstall cleanup

---

## 9. File Structure (Deliverables)
```
radykfw/
├── radykfw.php                # Main plugin file (header, bootstrap)
├── PRD.md                     # Updated product requirements (this file)
├── CHANGELOG.md
├── README.md
├── composer.json
├── .gitignore
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
├── system/
│   ├── config/
│   ├── contracts/
│   ├── core/
│   │   └── Boot.core.php       # Core bootstrap, uses RadykFW_Config
│   ├── enums/
│   ├── helpers/
│   ├── services/
│   └── templates/              # Renamed from "views"
│       ├── errors/
│       │   └── 404.error.html
│       ├── components/
│       │   └── button.component.php
│       ├── layouts/
│       └── pages/
```

---

## 4. MVP Scope (Routing Only)

### 4.1 Routing Interception
- **Hook**: `parse_request` (priority 10)
- **Logic**:
  1. Parse current request path into segments
  2. Apply auto-discovery routing logic
  3. If controller/method exists → dispatch
  4. If not → return false (let WordPress handle natively)

### 4.2 Module Structure
Each module contains:
```
modules/Login/
├── Controllers/
│   ├── Index.controller.php
│   └── Login.controller.php
├── Models/
│   └── Login.model.php
├── Views/
│   ├── login.view.php
│   └── register.view.php
└── Services/
    └── AuthService.service.php
```

### 4.3 Controller Convention
- Static methods only (for simplicity and performance)
- File naming: `{ControllerName}.controller.php`
- Class name matches file name (without extension)
- Namespace: `Modules\{Module}\Controllers`

### 4.4 Routing Rules (Auto-Discovery)
| Request Path | Segments | Module | Controller | Method | Params |
|--------------|----------|--------|------------|--------|--------|
| `/` | `[]` | Welcome | Welcome | index | [] |
| `/welcome` | `['welcome']` | Welcome | Welcome | welcome → index | [] |
| `/welcome/home` | `['welcome', 'home']` | Welcome | Home | home → index | [] |
| `/welcome/home/door` | `['welcome', 'home', 'door']` | Welcome | Home | door | [] |
| `/welcome/home/door/p1/p2` | `['welcome', 'home', 'door', 'p1', 'p2']` | Welcome | Home | door | ['p1', 'p2'] |

### 4.5 Fallback Logic (Case 1 & 2)
- **1 segment**: Try method = segment[0] (welcome), fallback to `index`
- **2 segments**: Try method = segment[1] (home), fallback to `index`
- **3+ segments**: Use segment[2] as method, rest as params

---

## 5. Technical Requirements

### 5.1 WordPress Integration
- Plugin header with proper metadata
- Activation/deactivation hooks (flush rewrite rules)
- `parse_request` hook for early interception
- No template_redirect or wp action (avoid late interception)

### 5.2 Module Discovery
- Scan `modules/` directory on request (via autoloader)
- No caching needed for MVP (autoloader handles file existence)

### 5.3 Namespace Convention
- System: `System\{Folder}\{ClassName}` → `system/{folder}/{ClassName}.core.php`
- Modules: `Modules\{Module}\{Folder}\{ClassName}` → `modules/{Module}/{Folder}/{ClassName}.{extension}.php`

### 5.4 File Extensions by Type
| Type | Extension | Folder |
|------|-----------|--------|
| Config | `.conf.php` | `system/config/` |
| Contracts (Interface/Trait) | `.contract.php` | `system/contracts/` |
| Core Classes | `.core.php` | `system/core/` |
| Enums | `.enum.php` | `system/enums/` |
| Helpers | `.helper.php` | `system/helpers/` |
| Services | `.service.php` | `system/services/` / `modules/{Module}/Services/` |
| Controllers | `.controller.php` | `modules/{Module}/Controllers/` |
| Models | `.model.php` | `modules/{Module}/Models/` |
| Views (standard) | `.view.php` | `modules/{Module}/Views/` / `system/views/` |
| Views (drip/blade-like) | `.drip.php` | `modules/{Module}/Views/` |
| Error Views | `.error.html` | `system/views/errors/` |
| Components | `.component.php` | `system/views/components/` |
| Layouts | `.layout.php` | `system/views/layouts/` |
| Pages | `.page.php` | `system/views/pages/` |

---

## 6. Non-Functional Requirements

| Requirement | Target |
|-------------|--------|
| Performance | < 5ms overhead per request |
| Memory | < 2MB additional |
| Compatibility | WP 6.0+, PHP 8.1+ |
| Extensibility | Easy to add new modules |

---

## 7. Future Enhancements (Post-MVP)
- [ ] Admin UI for module management
- [ ] REST API endpoints per module
- [ ] Module dependencies & autoloading
- [ ] CLI commands (WP-CLI integration)
- [ ] Module marketplace/installer
- [ ] Middleware support (auth, rate limit, etc.)
- [ ] Drip template engine integration

---

## 8. Acceptance Criteria (MVP)

1. ✅ Plugin activates without errors
2. ✅ Access `/login` → loads `Modules\Login\Controllers\Login::login()` if exists
3. ✅ Access `/welcome` → loads `Modules\Welcome\Controllers\Welcome::welcome()` if exists
4. ✅ Access `/welcome/home` → loads `Modules\Welcome\Controllers\Home::home()` if exists
5. ✅ Access `/welcome/home/door` → loads `Modules\Welcome\Controllers\Home::door()`
6. ✅ Access `/login` without module → redirects to `wp-login.php` (WP native)
7. ✅ Access `/welcome` without module → shows 404 (WP native)
8. ✅ Module with full HMVC structure works (Controller, Model, View, Services)
9. ✅ No conflicts with existing WP routes
10. ✅ Proper uninstall cleanup

---

## 9. File Structure (Deliverables)

```
radykfw/
├── radykfw.php
├── PRD.md
├── CHANGELOG.md
├── README.md
├── composer.json
├── .gitignore
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