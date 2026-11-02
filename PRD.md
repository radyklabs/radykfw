# Product Requirements Document: RadykFW - WordPress HMVC Framework

## 1. Overview
**Product Name**: RadykFW  
**Type**: WordPress Plugin (HMVC Framework)  
**Version**: 1.0.0 (MVP)  
**Goal**: Provide a modular HMVC architecture for WordPress with custom routing that intercepts requests before WordPress native handling.

---

## 2. Problem Statement
WordPress native routing:
- `/login` → redirects to `wp-login.php`
- `/welcome` → 404 page (no native page)

Developers need a way to handle custom routes via modules without modifying theme files or using page templates.

---

## 3. Solution: HMVC Framework with Custom Routing

### Core Architecture
```
radykfw/
├── assets/           # CSS, JS, images (public)
├── modules/          # HMVC modules (login, welcome, etc.)
│   └── {module}/
│       ├── Controller.php
│       ├── Model.php
│       ├── View/
│       └── routes.php
├── system/           # Framework core
│   ├── config/       # Configuration files
│   ├── contracts/    # Interfaces & Traits
│   ├── core/         # Core classes (Router, ModuleLoader, Dispatcher)
│   ├── enum/         # Enums (RouteType, ModuleStatus, etc.)
│   ├── helpers/      # Global helper functions
│   ├── services/     # Services (Cache, Logger, etc.)
│   └── views/        # Shared views (errors, layout, components)
└── radykfw.php       # Main plugin file
```

---

## 4. MVP Scope (Routing Only)

### 4.1 Routing Interception
- **Hook**: `parse_request` (priority 10)
- **Logic**:
  1. Parse current request path
  2. Check if module exists in `modules/{path}/`
  3. If module exists → dispatch to module controller
  4. If not exists → return false (let WordPress handle natively)

### 4.2 Module Structure (HMVC Full)
Each module contains:
```
modules/login/
├── Controller.php      # Main controller, implements ModuleControllerInterface
├── Model.php           # Data logic (optional)
├── routes.php          # Module-specific routes
└── View/
    ├── index.php       # Main view
    └── form.php        # Partial views
```

### 4.3 Module Controller Interface
```php
interface ModuleControllerInterface {
    public function index(array $params = []): void;
    public function __construct(array $config = []);
}
```

### 4.4 Routing Flow
```
Request → parse_request hook → Router::match($path)
    │
    ├─ Module found → ModuleLoader::load($module) → Dispatcher::dispatch($controller, 'index')
    │
    └─ Module NOT found → return false → WordPress native handling
```

---

## 5. Technical Requirements

### 5.1 WordPress Integration
- Plugin header with proper metadata
- Activation/deactivation hooks (flush rewrite rules)
- `parse_request` hook for early interception
- No template_redirect or wp action (avoid late interception)

### 5.2 Module Discovery
- Scan `modules/` directory on init
- Cache module list (transient, 1 hour)
- Support nested modules: `modules/auth/login/`

### 5.3 Routing Rules
| Request Path | Module Expected | Fallback |
|--------------|-----------------|----------|
| `/login`     | `modules/login/` | WP → wp-login.php |
| `/welcome`   | `modules/welcome/` | WP → 404 |
| `/custom/path` | `modules/custom/path/` | WP → 404 |

### 5.4 Error Handling
- Module not found → return false (WP handles)
- Controller error → log error, return false
- View not found → trigger WP 404

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

---

## 8. Acceptance Criteria (MVP)

1. ✅ Plugin activates without errors
2. ✅ Access `/login` → loads `modules/login/Controller.php::index()` if exists
3. ✅ Access `/welcome` → loads `modules/welcome/Controller.php::index()` if exists
4. ✅ Access `/login` without module → redirects to `wp-login.php` (WP native)
5. ✅ Access `/welcome` without module → shows 404 (WP native)
6. ✅ Module with full HMVC structure works (Controller, Model, View, routes)
7. ✅ No conflicts with existing WP routes
8. ✅ Proper uninstall cleanup

---

## 9. File Structure (Deliverables)

```
radykfw/
├── radykfw.php              # Main plugin entry
├── PRD.md                   # This file
├── CHANGELOG.md             # Version history
├── composer.json            # Autoload config
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── modules/
│   ├── login/               # Example module
│   │   ├── Controller.php
│   │   ├── Model.php
│   │   ├── routes.php
│   │   └── View/
│   └── welcome/             # Example module
│       ├── Controller.php
│       ├── Model.php
│       ├── routes.php
│       └── View/
└── system/
    ├── config/
    │   └── app.php
    ├── contracts/
    │   ├── ModuleControllerInterface.php
    │   ├── ModuleInterface.php
    │   └── RoutableInterface.php
    ├── core/
    │   ├── Router.php
    │   ├── ModuleLoader.php
    │   ├── Dispatcher.php
    │   └── Application.php
    ├── enum/
    │   └── RouteType.php
    ├── helpers/
    │   └── functions.php
    ├── services/
    │   └── CacheService.php
    └── views/
        ├── errors/
        │   └── 404.php
        ├── layouts/
        │   └── base.php
        └── components/
            └── button.php
```