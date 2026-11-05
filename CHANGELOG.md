# Changelog

All notable changes to RadykFW will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - 2026-11-03 (MVP Release)

### Added
- **Core HMVC Framework** with modular architecture following miniradf structure
- **Custom Routing System** using `parse_request` hook for early interception
- **Auto-Discovery Router** - maps URL segments to Module/Controller/Method/Params
- **PSR-4 Autoloader** - convention-based file loading (no Composer required)
- **Module Structure** with Controllers, Models, Views, Services folders
- **File Extension Conventions** - specific extensions for each file type
- **Static Controller Methods** - for performance and simplicity
- **Helper Functions** - global functions for views, config, assets, logging
- **System Views** - error pages, components, layouts
- **Example Modules** - Login and Welcome modules

### Module Structure (HMVC)
```
modules/{Module}/
├── Controllers/     # *.controller.php (static methods)
├── Models/          # *.model.php
├── Views/           # *.view.php, *.drip.php
└── Services/        # *.service.php
```

### Routing Logic (Auto-Discovery)
| URL | Module | Controller | Method | Params |
|-----|--------|------------|--------|--------|
| `/` | Welcome | Welcome | index | [] |
| `/welcome` | Welcome | Welcome | index | [] |
| `/welcome/home` | Welcome | Home | index | [] |
| `/welcome/home/door` | Welcome | Home | door | [] |
| `/welcome/home/door/p1/p2` | Welcome | Home | door | ['p1', 'p2'] |

### File Extensions by Type
| Type | Extension | Location |
|------|-----------|----------|
| Config | `.conf.php` | `system/config/` |
| Contracts | `.contract.php` | `system/contracts/` |
| Core | `.core.php` | `system/core/` |
| Enums | `.enum.php` | `system/enums/` |
| Helpers | `.helper.php` | `system/helpers/` |
| Services | `.service.php` | `system/services/` / `modules/*/Services/` |
| Controllers | `.controller.php` | `modules/*/Controllers/` |
| Models | `.model.php` | `modules/*/Models/` |
| Views | `.view.php` | `modules/*/Views/` / `system/templates/` |
| Drip Views | `.drip.php` | `modules/*/Views/` |
| Error Views | `.error.html` | `system/templates/errors/` |
| Components | `.component.php` | `system/templates/components/` |
| Layouts | `.layout.php` | `system/templates/layouts/` |
| Pages | `.page.php` | `system/templates/pages/` |

### System Structure
```
system/
├── config/app.conf.php
├── contracts/Dispatchable.contract.php
├── core/Router.core.php
├── enums/
├── helpers/system.helper.php
├── services/
└── templates/
    ├── errors/404.error.html
    ├── components/button.component.php
    ├── layouts/
    └── pages/
```

### Example Modules
- **Login Module**: Custom login handler at `/login` with `Login.controller.php` (login, authenticate, logout methods)
- **Welcome Module**: Landing page at `/welcome`, `/welcome/home`, `/welcome/home/door` with `Welcome.controller.php`

---

## [Unreleased]

### Security & Architecture (2026-11-05)

- **Autoloader**: `Controller` suffix stripping now restricted to `System\` namespace only. Module controllers keep their naming intact (e.g., `class Login` stays `Login`, not `LoginController`).
- **System Templates**: Renamed `system/Views/` → `system/Templates/` to avoid confusion with module `Views/` folders. `404.error.html` moved to `system/Templates/errors/`.
- **Helper Consolidation**: Removed unused helpers (`config`, `asset`, `debug`). Remaining: `path` (paths + `current_module`), `view` (resolver with strict casing).
- **Base Controllers**: Added three abstract base controllers in `System\Core\`:
  - `PublicController` — unauthenticated routes (login, welcome, marketing)
  - `PrivateController` — authenticated routes (`requireAuth()` enforces login)
  - `ApiController` — JSON endpoints (`respond()`, `error()` helpers)
- **Module Controller Inheritance**: Module controllers now extend base:
  - `Login` → `PublicController` (login form, authenticate, logout)
  - `Welcome` / `Index` → `PublicController`
  - **New samples**: `Dashboard` → `PrivateController`, `Api` → `ApiController`
- **View Resolver Split**: `view()` now handles **module views only** (strict casing rule). New `system_view()` handles **system templates only** (explicit path under `system/Templates/`). No ambiguity between module and system views.
- **View Resolver**: Strict casing rule for `view()`:
  - `view('home')` → current module
  - `view('folder.home')` → current module, subfolder `folder`
  - `view('Login.home')` → explicit module `Login`
  - `system_view('errors/404.error')` → system template
- **System Templates**: `404.error.html` moved to `system/Templates/errors/`.
- **Removed Unused Helpers**: `debug.helper.php`, `utilities.helper.php`. Restored `asset.helper.php` (needed for font enqueue) and `config.helper.php` (app.conf access).
- **Config**: `helper.conf.php` now loads `['path', 'view', 'asset', 'config']`. `config($key, $default, $file='app')` reads dot-notation values from **any** `system/Config/*.conf.php` (not only `app`). New `system/Config/app.conf.php` holds runtime settings (routing, auth URLs, modules).
- **Config Consolidation (Planned)**: `app.conf.php` will be split into domain-specific files (`auth.conf.php`, `routing.conf.php`, etc.). `get_config()` unified with dot-notation: `get_config('auth.post_login_url')`. `config.helper.php` to be removed. Callers updated to `get_config('auth.post_login_url')`. `config.helper.php` removed. `helper.conf.php` drops `'config'`. **Planned, not yet implemented.**
- **Auth Module (new)**: `modules/Auth/` (`Auth` → `PrivateController`). Post-login redirect to `/auth`, which validates session and routes to `/dashboard` or back to `/login`.
- **Asset**: `assets/css/fonts/inter.css` + `radykfw_asset()` re-added; Welcome controller enqueues the Inter font.
- **Welcome Module**: `Welcome.controller.php` is the primary target for `/welcome` (Router case-1 → `Welcome::index`); removed redundant `Index.controller.php`.

### Changed
- **Router autoloader**: `Controller` suffix strip only for `System\` namespace (line 53-57 in `Boot.core.php`).
- **View helper split**: `view()` now handles module views only; new `system_view()` handles system templates only.
- **`system/` prefix removed from `view()`**: no more ambiguity. `view()` = modules, `system_view()` = templates.
- **All `system/Views/` paths updated to `system/Templates/`** in docs and code.
- **Module structure docs**: Updated to reflect `system/Templates/` rename.

### Removed
- `modules/Welcome/Controllers/Index.controller.php` (redundant — `Welcome` controller is the primary target for `/welcome` via Router case-1)
- `system/Views/` directory (renamed to `system/Templates/`)
- Unused helper entries from `helper.conf.php`

### Added
- `modules/Auth/Controllers/Auth.controller.php` + `modules/Auth/Views/` (post-login validation gateway)
- `system/Config/app.conf.php` (runtime settings: routing defaults, skip paths, auth URLs)
- `assets/css/fonts/inter.css` (`@font-face` for Inter; self-hosted `.woff2` files expected in same dir)
- `system/Helpers/asset.helper.php`, `system/Helpers/config.helper.php` (restored)

### Known / Not Fixed (see REPORT.md)
- `Runner::initRegistry()` dead method (minor, cosmetic)
- `Runner::shouldSkipRouting()` checks non-existent `.php` paths (latent)
- `uninstall()` calls `flush_rewrite_rules()` (risky, deferred)
- `composer.json` dev-deps without `tests/` (deferred)
- `.gitignore` overly-broad `wp-content/plugins/*/vendor/` (deferred)

### Planned for 1.1.0