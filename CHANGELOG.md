# Changelog

All notable changes to RadykFW will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - 2026-11-03 (MVP Release)

### Added
- **Core HMVC Framework** with modular architecture
- **Custom Routing System** using `parse_request` hook for early interception
- **Module Loader** with discovery and caching (1-hour transient)
- **Router** with path-to-module mapping
- **Dispatcher** for controller execution
- **ModuleControllerInterface** for standardized controller structure
- **Application** bootstrap class for framework initialization
- **CacheService** for module list caching
- **Helper functions** for global use (`module_path()`, `view()`, `config()`)

### Module Structure (HMVC Full)
- Each module supports: Controller, Model, View, routes.php
- Nested module support (e.g., `modules/auth/login/`)
- View rendering with layout support

### Example Modules
- **Login Module**: Custom login handler at `/login`
- **Welcome Module**: Custom welcome page at `/welcome`

### System Structure
```
system/
├── config/app.php           # Framework configuration
├── contracts/               # Interfaces & Traits
│   ├── ModuleControllerInterface.php
│   ├── ModuleInterface.php
│   └── RoutableInterface.php
├── core/                    # Core framework classes
│   ├── Application.php      # Main bootstrap
│   ├── Router.php           # Route matching
│   ├── ModuleLoader.php     # Module discovery & loading
│   └── Dispatcher.php       # Controller dispatching
├── enum/RouteType.php       # Route type enumeration
├── helpers/functions.php    # Global helper functions
├── services/CacheService.php # Caching abstraction
└── views/                   # Shared views
    ├── errors/404.php
    ├── layouts/base.php
    └── components/button.php
```

### WordPress Integration
- Plugin activation/deactivation hooks
- Rewrite rules flush on activation
- `parse_request` hook (priority 10) for routing interception
- Graceful fallback to WordPress native handling

### Developer Experience
- PSR-4 autoloading via Composer
- Comprehensive inline documentation
- Type hints and return types (PHP 8.1+)

---

## [Unreleased]

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

---

## Version History Summary

| Version | Date | Type | Description |
|---------|------|------|-------------|
| 1.0.0 | 2026-11-03 | MVP | Initial HMVC framework with routing |
| Unreleased | - | - | Future enhancements |

---

## Upgrade Guide

### From 0.x to 1.0.0
First stable release - no upgrade path needed.

### Future Upgrades
Will be documented here when released.