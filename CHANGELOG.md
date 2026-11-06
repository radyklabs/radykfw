# Changelog

All notable changes to RadykFW will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.2] - 2026-11-06 (Audit Verification & Documentation Sync)

### Changed
- **PLAN.md** — Updated status for 6 items that claimed "Done" but were still open:
  - `Runner::shouldSkipRouting` path check (R2) → ⚠️ Open
  - `login.view.php` `$_POST['username']` & `action` (S2/S3) → ⚠️ Open
  - Footer hardcode links in `index.view.php` (C4) → ⚠️ Open
  - `.gitignore` overly broad pattern (T3) → ⚠️ Open
  - Composer dev-deps without `tests/` (T2) → ⚠️ Open
  - `Runner::shouldSkipRouting` path `.php` check (M2) → ⚠️ Open
- **REPORT.md** — Added audit verification summary; updated M2, S2, S3, C4, T2, T3 status references
- **CHANGELOG.md** — This entry added

### Notes
- 27/33 audit items verified as matching (PLAN claims Done = code confirms Done)
- 6 items require execution to match PLAN claims
- No code changes in this version — documentation sync only

---

## [1.0.3] - 2026-11-06 (Phase 1.1 Runner & Router Fixes)

### Fixed
- **Runner.core.php** — `shouldSkipRouting()` now also skips short WP paths (`login`, `signup`, `activate`) in addition to `wp-login`, `wp-signup`, `wp-activate` (R2)
- **Router.core.php** — Alias parsing now supports both explicit `Module\Controller::method` and shorthand `Module::method` formats (R20)

### Notes
- Phase 1.1 (Runner + Router cleanup) now complete
- 28/33 audit items match (was 27)
- 5 items remaining open (S2, S3, C4, T2, T3)

---

## [1.0.4] - 2026-11-06 (PLAN Revision 6 — Fase 1 Fact-Check)

### Changed
- **PLAN.md (Revision 6)** — Fase 1.1–1.3 dicocokkan ulang vs kode (21 item):
  - **R12 → ⚠️ Open** — controller `Welcome::home/door` tidak pernah meneruskan `$args` ke view (blok tampilan args mati; klaim lama "Index::home/door" salah)
  - **R15 → ⚠️ Open** — `routing.default` di `app.conf.php` berupa array 3 key, bukan module name; nilainya tak pernah dibaca kode (Router hardcode `Welcome/Welcome/index`); `routing.conf.php` tidak ada
  - **R16, R18 detail dikoreksi** — file config aktual `app.conf.php` (bukan `routing.conf.php`/`modules.conf.php`)
  - **C4 direvisi** — link footer sudah `esc_url(home_url())`; sisa issue: route `/documentation`, `/changelog` tidak ada (kontradiksi R13/C4 resolved)
  - Header: commit terbaru → `bb625d0`; referensi baris 1.1 diperbarui (R2→281, R5→127, R6→303/305)
- **REPORT.md §1.1** — Count diperbarui: 26 match / 7 mismatch (R12a + R12b masuk daftar)

### Notes
- Audit result: 19/21 Fase 1 items akurat; 2 status turun (R12, R15)
- Documentation-only changes

---

## [1.0.1] - 2026-11-06 (Routing Priority Fix)

### Added
- **Routing Priority Configuration** — `routing.native` (default `true` = WordPress first, `false` = HMVC first)
- **Route Aliases with Explicit Format** — Support `Module\Controller::method` (explicit) and `Module::method` (shorthand)
- **Alias Priority** — Route aliases checked first using original `REQUEST_URI` (before WP rewrite)
- **WordPress Native Path Detection** — `isWpNativePath()` checks WP query vars before HMVC auto-discovery
- **Root Path Handling** — Empty path returns `false` when `routing.native=true` (WP handles landing page)
- **Router `tryDispatch()` Public** — Made public for Runner alias dispatch

### Changed
- **Router Alias Parsing** — Updated to support both explicit `Module\Controller::method` and shorthand `Module::method`
- **Router `case 0` (root)** — Returns `false` when `routing.native=true` (WP handles root)
- **Runner `handleRouting()`** — Priority logic: aliases → WP native (if native=true) → HMVC auto-discovery
- **Runner `getRequestPath()`** — Returns empty string for root (no more forced `'home'`)
- **Login Controller** — Password sanitization with `filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW)`

### Fixed
- **Header Conflict** — `exit` after successful route dispatch prevents "headers already sent" errors
- **Root Path Claim** — HMVC no longer claims `/` when `routing.native=true`
- **Alias Matching** — Uses original `REQUEST_URI` to match before WP rewrites `/login` → `wp-login.php`

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
- **Routing (Implemented)**: 
  - Default module simplified to `'Welcome'` (module name only; controller=module, method=index by default)
  - Route aliases (`routing.aliases`) with HTTP method support: `'POST /login' => 'Auth\Controllers\Custom::login'`
  - Auto-discovery toggle via `modules.auto_discover` (default: `true`)
  - Namespace prefix derived from `.starter` `modules_dir` (`ucfirst(modules_dir)` = `Modules`); config `namespace_prefix` removed (redundant)
- **Auth Module (new)**: `modules/Auth/` (`Auth` → `PrivateController`). Post-login redirect to `/auth`, which validates session and routes to `/dashboard` or back to `/login`.
- **Asset**: `assets/css/fonts/inter.css` + `radykfw_asset()` re-added; Welcome controller enqueues the Inter font.
- **Welcome Module**: `Welcome.controller.php` is the primary target for `/welcome` (Router case-1 → `Welcome::index`); removed redundant `Index.controller.php`.

### Changed
- **Router core**: `dispatchFallback()` param `$params` dihapus (R7). Docblock diperbaiki: "Fallback to 'index' method..." bukan "Retry the SAME method" (R8).
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