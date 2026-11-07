# Product Requirements Document: RadykFW - WordPress HMVC Framework

## 1. Overview
**Product Name**: RadykFW  
**Type**: WordPress Plugin (HMVC Framework)  
**Version**: 1.0.9 (Opsi B — gate cek konten nyata di parse_request)  
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
- **Priority**: Configurable via `routing.native` (default: `true` = WordPress first)
- **Logic**:
  1. Parse current request path into segments
  2. Validate all segments against `/^[A-Za-z_][A-Za-z0-9_]*$/` — reject if invalid
  3. **If `routing.native = true` (WordPress first):**
     - Let WordPress resolve query first (posts, pages, terms, archives)
     - If WP has a match → return `false` (WP handles natively)
  4. Check route aliases (if configured) — match path + HTTP method before auto-discovery
  5. Apply auto-discovery routing logic (exact match only — class + method MUST exist):
     - 0 segments → **return `false`** (WordPress handles root/landing page)
     - 1 segment → `/module` → `Module::index` (if class exists)
     - 2 segments → `/module/controller` → `Controller::index` (if class exists)
     - 3+ segments → `/module/controller/method/params...` → `Controller::method` (if class + method exist)
  6. Fallback rules:
     - 0–1 segments: if named controller/method not found, fall back to module's `Index` controller
     - 2+ segments: no fallback — unknown routes return `false` (WordPress 404)
  7. If dispatched → `exit` immediately; if not → return `false` (WordPress handles natively)

### 4.2 Routing Configuration
- **Default module**: `Welcome` (module name only; controller=module, method=index by default)
- **Priority**: `routing.native` (default: `true` = WordPress first, `false` = HMVC first)
- **Route aliases** (`routing.aliases`): Map path + HTTP method to controller::method
  ```php
  'routing' => [
      'native' => true,  // WordPress first priority
      'default' => 'Welcome',  // module name only
      'aliases' => [
          'POST /login' => 'Login\Login::login',
          'GET  /auth'  => 'Auth\Auth::index',
      ],
  ]
  ```
  Format: `{Module}\{Controller}::{method}` (explicit) atau `{Module}::{method}` (shorthand, controller = module) — Runner parses both formats.
  **Pattern placeholder** (sejak 1.0.6): path alias boleh memuat segment placeholder —
  - `{seg}` = string segment, wajib cocok `[A-Za-z0-9_-]+` (menolak traversal)
  - `{seg:int}` = digit saja (`^\d+$`); value tetap dikirim sebagai string (validasi-only)
  - Segment count wajib sama; **precedence: exact match → pattern → WP 404**
  ```php
  'aliases' => [
      'GET /welcome/door'          => 'Welcome\Welcome::door',      // exact
      'GET /welcome/door/{a}/{b}'  => 'Welcome\Welcome::door',      // 2 string args
      'GET /welcome/home/{id:int}' => 'Welcome\Welcome::home',      // integer-only
  ]
  ```
  Alias resolution di `Runner::tryAliases()` — memakai `REQUEST_URI` original, jalan **sebelum semua gate**. Aliyun = klaim eksplisit, menang di **KEDUA** mode (`native=true` dan `false`). Desain gate detail → §4.4.
- **Auto-discovery**: Enabled via `modules.auto_discover` (default: `true`)
  - When `false`: only explicit aliases are matched; unknown routes fall through to WP
- **Namespace prefix**: Derived from `.starter` `modules_dir` (`ucfirst(modules_dir)` = `Modules`)
  - Config `namespace_prefix` removed (redundant)

### 4.3 Module Structure
Each module contains:
```
modules/Login/
├── Controllers/
│   └── Login.controller.php    # extends PublicController
├── Views/
│   └── login.view.php
└── (Models/, Services/ — optional)

modules/Auth/
├── Controllers/
│   └── Auth.controller.php     # extends PrivateController (post-login gateway)
└── Views/
```

### 4.4 Routing Priority & Gate Logic (Design Rationale — Opsi B)

> **Catatan:** Keputusan ini adalah **desain inti** routing RadykFW. Perubahan urutan = breaking behavior change.

**Prinsip utama: eksplisit > implisit > tebakan.**
- Alias = klaim eksplisit (framework assert owns path)
- Auto-discovery = inferensi implisit (tebakan dari bentuk path)
- WP 404 = fallback akhir

**Alasan alias selalu lebih dulu (native=true & false):**
1. Alias tanpa query DB — path alias (login, dashboard, api/*) tak mungkin punya konten WP; DB check untuk mereka sia-sia.
2. Alias menang bentrok slug — kalau ada `/welcome` alias DAN WP page slug `welcome`, alias menang karena paling spesifik. `native=true` artinya "WP konten menang untuk path yang BUKAN klaim alias", bukan "WP menang untuk semua path".
3. Keputusan bentrok-slug QA berlaku untuk auto-disc vs WP konten, bukan alias vs WP.

**Opsi B vs Opsi C (status quo lama) vs Opsi A:**
- Opsi C (`isWpNativePath` query-var, sudah dihapus): gate selalu true di pretty permalink (`pagename` terisi untuk SEMUA path non-root) → auto-disc mati total. Rusak.
- Opsi A (`template_redirect` + `is_404`, dibatalkan): hook terlalu telat, `get_post_by_path` undefined di WP 7.1.4, state 404 re-send mengalahkan `status_header(200)`.
- Opsi B (`parse_request` + `wpHasRealContent()` DB check): hook benar (pra-output), fungsi tersedia (`get_page_by_path` + `get_posts` by name), status 200 bersih. **Adopsi ini.**

**Urutan final:**
```
native = true  (WP konten dulu):
  parse_request → alias → wpHasRealContent? → auto-disc → 404

native = false (HMVC dulu):
  parse_request → alias → auto-disc → WP → 404
```

**Implikasi:**
- `/welcome/welcome/index` (path tak ada isinya) → alias miss → `wpHasRealContent` = false → auto-disc → `Welcome::index` = **200**
- `/sample-page/` (page WP nyata) → alias miss → `wpHasRealContent` = true → return → WP render = **200**

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
| `Model.core.php` (Eloquent-lite) | ✅ Implemented 2026-11-06 | Medium |
| `Database.core.php` ($wpdb wrapper + auto-prefix) | ✅ Implemented 2026-11-06 | Medium-High |
| Full ORM | **No** — overkill | No |
| `$wpdb` wrapper in Model | Yes (via Database, Eloquent-lite layer) | Yes |
| Migration system | No (WP handles schema) | No |
| Relations (hasMany/hasOne/belongsTo) | ✅ Eloquent-lite + lazy-load, no eager/N+1 cache | Yes |

> **Note (2026-11-06, `feat/phase-2` rev 2):** Fase 2 di-rewrite dari wrapper `$wpdb` polos menjadi **Eloquent-lite layer** — `Model` + `QueryBuilder` + `Record` + `Relation`, auto-prefix `$wpdb->prefix`, nilai selalu via placeholder `?` (anti-injection). Developer yang familiar Eloquent bisa langsung `Model::where(...)->orderBy(...)->get()` tanpa query string mentah.

---

## 8.5 Configuration Architecture (Planned)

### 8.5.1 Current State

- **`get_config()`** in `.supports`: loads entire `system/Config/{X}.conf.php` file, returns full array. Used by `Runner::initHooks()` pre-autoload (`get_config('helper')`).
- **`config()`** in `config.helper.php`: dot-notation access to **`app.conf.php`** only (default file param `'app'`). Used by controllers at runtime.

### 8.5.2 Planned Consolidation (Decision: Ops A — Split Config + Unified `get_config`)

**Rationale:** Two functions (`get_config` + `config`) overlapping; developers confused which to use. `config()` limited to `app.conf.php`; `get_config()` pre-autoload only.

**Plan:**
1. **Split `app.conf.php`** into domain-specific files:
   - `app.conf.php` → core identity (name, version, debug, timezone)
   - `routing.conf.php` → `skip_paths`, `skip_extensions`, `default`
   - `auth.conf.php` → `post_login_url`, `post_logout_url`, `login_url`
   - `modules.conf.php` → `auto_discover`, `namespace_prefix`
   - `assets.conf.php` → `version`
   - (future) `database.conf.php`, `cache.conf.php`, etc.

2. **Unify via `get_config()` with dot-notation:**
   - `get_config('auth.post_login_url')` → resolves to `auth.conf.php['auth']['post_login_url']`
   - `get_config('routing.skip_paths')` → `routing.conf.php['routing']['skip_paths']`
   - `get_config('app.debug')` → `app.conf.php['debug']` (domain = nama file, flat — tanpa top-level wrapper key)

3. **Deprecate & remove `config.helper.php`**:
   - All callers: `config('auth.post_login_url')` → `get_config('auth.post_login_url')`
   - `helper.conf.php` removes `'config'` entry

4. **`get_config()` behavior change**:
   - `get_config('X')` → returns whole file (backward compat)
   - `get_config('domain.key.subkey')` → dot-notation access (new)

5. **Caller updates**:
   - `Runner::initHooks()`: `get_config('helper')` unchanged (returns whole `helper.conf.php`)
   - Controllers: `config('auth.post_login_url')` → `get_config('auth.post_login_url')`

---

*Decision recorded: 2026-11-05 (QA: split config + unified get_config with dot-notation). Implementation pending.*

---

### 8.5.3 Routing Configuration (Decided)

- **Default module**: `Welcome` (module name only; controller=module, method=index by default)
- **Route aliases** (`routing.aliases`): Map path + HTTP method to controller::method — **contoh aktual (R20)**:
  ```php
  'routing' => [
      'default' => 'Welcome',  // module name only
      'aliases' => [
          'POST /login' => 'Login\Login::login',
          'GET /auth'   => 'Auth\Auth::index',
      ],
  ]
  ```
  Pattern placeholder `{seg}` / `{seg:int}` + precedence → lihat §4.2 (sejak 1.0.6).
- **Auto-discovery**: Enabled via `modules.auto_discover` (default: `true`)
  - When `false`: only explicit aliases are matched; unknown routes fall through to WP
  - Catatan 1.0.6: dengan `routing.native=true`, auto-discovery non-alias sekunder — framework = alias router (QA decision, lihat §4.2)
- **Namespace prefix**: Derived from `.starter` `modules_dir` (`ucfirst(modules_dir)` = `Modules`)
  - Config `namespace_prefix` removed (redundant)

---

*Decision recorded: 2026-11-05 (QA: split config + unified get_config with dot-notation). Implementation pending.*

---

## 9. Future Enhancements (Post‑MVP)

| Component | Status | Priority |
|-----------|--------|----------|
| `Model.core.php` | ✅ Implemented (Eloquent-lite, 2026-11-06) | Medium |
| `Database.core.php` | ✅ Implemented ($wpdb wrapper + auto-prefix, 2026-11-06) | Medium-High |
| Testing infra (`tests/` + PHPUnit) | ✅ Implemented (Fase 5, 2026-11-07) | Low-Medium |
| Full ORM | **No** — overkill | No |
| `$wpdb` wrapper in Model | Yes (via Database) | Yes |
| Migration system | No (WP handles schema) | No |

---

## 9. Future Enhancements (Post‑MVP)
- Admin UI for module management
- REST API endpoints per module
- Module dependencies & autoloading (Composer)
- CLI commands (WP‑CLI)
- Marketplace/installer
- Middleware support (auth, rate limit, etc.)
- Drip template engine integration

---

## 10. Acceptance Criteria (MVP)
1. ✅ Plugin activates without errors
2. ✅ Access `/login` → loads `Modules\Login\Controllers\Login::index()`
3. ✅ Access `/welcome` → loads `Modules\Welcome\Controllers\Welcome::index()`
4. ✅ Access `/welcome/home` → loads `Modules\Welcome\Controllers\Welcome::home()`
5. ✅ Access `/welcome/home/door` → loads `Modules\Welcome\Controllers\Welcome::door()`
6. ✅ Access `/login` without module → redirects to `wp-login.php`
7. ✅ Access `/welcome` without module → shows 404 (WP native)
8. ✅ Module with full HMVC structure works (Controller, Model, View, Services)
9. ✅ No conflicts with existing WP routes
10. ✅ Proper uninstall cleanup
11. ✅ Login → `/auth` (post-login gateway) → `/dashboard` (valid) or `/login` (invalid)
12. ✅ Inter font enqueued via `radykfw_asset()` in Welcome controller

> **Note (2026-11-07, `feat/phase-3`):** Fase 3 (Security Polish) dieksekusi — verifikasi S1–S5: password via `filter_input` (S1), login view bebas `$_POST` + form action `esc_url($action_url)` (S2/S3, sudah tereksekusi `eb942dd`), `.gitignore` `*.bak`/`*.junk` konsisten (S4), dan fix dead code `Auth::requireAuth()` agar kriteria #11 (invalid → `/login`, bukan `wp-login.php` WP) benar-benar tercapai (S5).

---

## 11. File Structure (Deliverables)
```
radykfw/
├── radykfw.php                # Main plugin file (header, bootstrap)
├── PRD.md                     # Updated product requirements (this file)
├── CHANGELOG.md
├── README.md
├── .gitignore
├── tests/                     # Dev-QA test suite (pure-PHP, no Composer)
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

