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
| `/welcome` | Welcome | Welcome | welcome → index | [] |
| `/welcome/home` | Welcome | Home | home → index | [] |
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
| Views | `.view.php` | `modules/*/Views/` / `system/views/` |
| Drip Views | `.drip.php` | `modules/*/Views/` |
| Error Views | `.error.html` | `system/views/errors/` |
| Components | `.component.php` | `system/views/components/` |
| Layouts | `.layout.php` | `system/views/layouts/` |
| Pages | `.page.php` | `system/views/pages/` |

### System Structure
```
system/
├── config/app.conf.php
├── contracts/Dispatchable.contract.php
├── core/Router.core.php
├── enums/
├── helpers/system.helper.php
├── services/
└── views/
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

### Security (audit fixes, 2026-11-04)
- **Router**: Added `isValidSegment()` — URL segments are now validated against `/^[A-Za-z_][A-Za-z0-9_]*$/` before building class names. Invalid segments (e.g. `..`, `a/b`, `%2f`) cause fall-through to WordPress instead of building an unintended file path in the autoloader.
- **Router**: `Index` fallback now restricted to 0–1 segment routes only. `/welcome/home` (2 segments, no `Home` controller) now correctly falls through to WordPress 404 instead of silently rendering `Index::index`.
- **Router**: Removed dead `fallbackToWordPress()` no-op method.
- **Helpers**: `view()` — replaced bare `extract($data)` with `extract($scope, EXTR_SKIP)`, preventing variable-injection clobbering of internal view variables.

### Changed
- **Router**: 1-segment route `/welcome` now defaults to `Welcome::index` (was `Welcome::welcome`). 2-segment route `/welcome/home` now defaults to `Home::index` (was `Home::home`). Explicit method routing unchanged: `/welcome/index/window` → `Index::window` → 404 if method doesn't exist.
- `utilities.helper.php` — `view()` now builds a clean scope before `extract`; `args` data keys are safe.
- `Router.core.php` — new `isValidSegment()` guard; `handle()` returns `false` on invalid input.

### Known / Not Fixed (see REPORT.md)
- `Runner::initRegistry()` dead method (minor, cosmetic)
- `Runner::shouldSkipRouting()` checks non-existent `.php` paths (latent)
- `uninstall()` calls `flush_rewrite_rules()` (risky, deferred)
- `Welcome.controller` possibly dead (needs confirmation)
- `composer.json` dev-deps without `tests/` (deferred)
- `.gitignore` overly-broad `wp-content/plugins/*/vendor/` (deferred)

### Planned for 1.1.0
- [ ] Admin dashboard for module management
- [ ] REST API endpoint registration per module
- [ ] Middleware system (auth, rate limiting, CORS)
- [ ] WP-CLI commands for module scaffolding
- [ ] Module dependency resolution

### Planned for 1.2.0
- [ ] Module installer/marketplace
- [ ] Database migration system for modules
- [ ] Event/hook system for inter-module communication
- [ ] Asset pipeline (CSS/JS bundling)
- [ ] Drip template engine integration

---

## Version History Summary

| Version | Date | Type | Description |
|---------|------|------|-------------|
| 1.0.0 | 2026-11-03 | MVP | HMVC framework with auto-discovery routing |
| Unreleased | - | - | Future enhancements |

---

## Upgrade Guide

### From 0.x to 1.0.0
First stable release with new folder structure - no upgrade path needed.

### Future Upgrades
Will be documented here when released.