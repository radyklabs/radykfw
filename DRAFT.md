# DRAFT — RadykFW Development Plan

> Draft (not canonical — replace PLAN.md after QA approval)
> Date: 2026-11-08 | Branch: `dev` (rev 15) | Version: 1.0.9 → 1.0.10 in-flight
>
> Basis: full code audit (`system/`, `modules/`, `tests/`, `.supports`, `.starter`, `radykfw.php`)
> + docs cross-ref (PRD, PLAN rev 15, REPORT rev 15, CHANGELOG).

---

## 0. Current State

### 0.1 What works
- Boot chain: `.starter → .supports → Boot → Runner → helpers` — clean, no fatal
- Alias router (v1.0.9 Opsi B gate): exact-first + pattern `{seg}`/`{seg:int}`, browser-verified
- Eloquent-lite data layer (Phase 2): `Database` + `Model` + `QueryBuilder` + `Record` + `Relation`
- Security polish (Phase 3): S1–S5 all resolved
- Test infra (Phase 5): `tests/bootstrap.php` + 2 pure-PHP test files
- Docs synchronized (rev 15)

### 0.2 Uncommitted in-flight work (v1.0.10 infra)
| File | Change |
|---|---|
| `.supports` | New `plugin_meta()` helper (parse WP plugin header at runtime) |
| `.starter` | Version + textdomain now dynamic via `plugin_meta()` |
| `system/Core/Boot.core.php` | Refactored init sequence (Registry → autoloader → Runner) |
| `system/Core/Runner.core.php` | Minor whitespace normalization |
| `radykfw.php` | New Description, whitespace normalization |

**This is the highest-priority item — do not build on it until committed.**

### 0.3 Key architectural finding: alias router ≠ auto-discovery
Since commit `1c3ebbc` (`feat/phase-2`), `modules.auto_discover = false`.
The framework is now an **alias-only router**. Every route must be explicitly declared in
`routing.conf.php`. This means:

- `Dashboard`, `Api`, `Auth` modules exist with full controllers/views/models but **no active route**
- `/login/authenticate` (POST) has no alias — the login flow relies on WP rewrite + form action
- `/api/posts`, `/api/health`, `/api/user` all commented out — API module is dead
- PRD §4.2 still documents `auto_discover = true` as default — **docs drift**

---

## 1. P0 — Unblock & Stabilize

### P0-1. Commit in-flight v1.0.10 infra
- **Depends on:** —
- **Effort:** < 1 hour
- **Impact:** 🔴 Critical — all subsequent work builds on this. Locked files touched (`.starter`, `.supports`, `Boot.core.php`, `radykfw.php`) need QA sign-off.
- **QA decision needed:** commit as-is or revert `Boot.core.php` changes?

### P0-2. Decision: alias-only router vs. re-enable auto-discovery
- **Depends on:** P0-1
- **Options:**
  - **A.** Stay alias-only (current). All routes explicit in `routing.conf.php`.
    - Pro: deterministic, no DB lookup for auto-discovery, predictable
    - Con: every new module/method needs a config edit; `/login/authenticate` is still missing
  - **B.** Re-enable `auto_discover = true` as opt-in per module
    - Pro: less config maintenance, new modules self-register
    - Con: adds a DB lookup on every alias-miss request (object-cache friendly, but still)
- **Impact:** 🟠 Major — shapes the entire routing strategy; blocks P1-2 and P1-3

### P0-3. Clean up junk files
- **Depends on:** —
- **Files:**
  - `radykfw.php.old.junk` — untracked, delete
  - `system/Config/app.conf.php.junk` — untracked, delete
  - `system/Core/Boot.core.php.bak` — untracked, delete
  - `system/Core/Config.core.php.junk` — untracked, delete
  - `system/Core/Paths.core.php.junk` — untracked, delete
  - `PRD.md.bak` — untracked, delete
- **Impact:** Low — hygiene only, but `.gitignore` already covers them; just delete from disk

---

## 2. P1 — Activate Dead Modules

### P1-1. Restore `/login/authenticate` alias
- **Depends on:** P0-2
- `Login::authenticate()` exists and is wired to the login form's `action_url`, but there is no
  `POST /login/authenticate` entry in `routing.conf.php`. The form posts to
  `home_url('/login/authenticate')` — if auto-discovery is off, **this route 404s unless WP's
  rewrite handles it**.
- **Action:** add `'POST /login/authenticate' => 'Login\Login::authenticate'` to aliases
  (or confirm auto-discovery handles it — test in browser)
- **Impact:** 🔴 Critical — login flow is broken without this

### P1-2. Restore Dashboard routes
- **Depends on:** P0-2
- `/dashboard` and `/dashboard/stats` are commented out in `routing.conf.php`
- `Dashboard` module extends `PrivateController` (auth-guarded)
- **Action:** uncomment the 2 alias entries (or keep commented if dashboard is still a sample)
- **Impact:** 🟠 Major — makes the framework actually usable post-login

### P1-3. Restore API routes
- **Depends on:** P0-2
- `/api/health`, `/api/user`, `/api/posts` are all commented out
- `Api` module extends `ApiController` (JSON responses)
- `Api::health()` hardcodes version `1.0.1` (stale — should use `Registry::get('version')`)
- **Action:** uncomment 3 alias entries; fix version reference in `Api::health()`
- **Impact:** 🟡 Medium — API module is the most developer-facing feature

### P1-4. Wire `/auth` to `Auth::index`
- **Depends on:** P1-2
- `auth.conf.php` has `post_login_url = '/auth'` and `Auth::index()` is the gateway, but
  there is no `'GET /auth' => 'Auth\Auth::index'` alias entry.
- Without it, post-login redirect hits a dead path
- **Action:** add alias entry
- **Impact:** 🔴 Critical — post-login flow is broken

---

## 3. P2 — Internationalization

### P2-1. Create `languages/` + i18n support
- **Depends on:** P0-1
- `radykfw.php` header has `Text Domain: radykfw` + `Domain Path: /languages` but no
  `languages/` directory and no `load_plugin_textdomain()` call
- All user-facing strings in views (login form, welcome, dashboard, 404) are hardcoded English
- **Action:**
  1. Create `languages/radykfw.pot` (template)
  2. Add `load_plugin_textdomain()` to `Runner::init()`
  3. Wrap all user-facing strings with `__()` / `esc_html_e()`
  4. `languages/` dir empty in git (WP provides `.po`/`.mo` via L10N)
- **Impact:** 🟡 Medium — "class world" requirement for public distribution
- **Effort:** 2–3 hours (bulk of work is string wrapping)

### P2-2. 404 template i18n
- **Depends on:** P2-1
- `system/Templates/errors/404.error.html` also needs i18n strings
- **Impact:** Low

---

## 4. P3 — Code Quality & CI

### P3-1. CI pipeline (GitHub Actions)
- **Depends on:** P0-1
- No `.github/` directory, no CI config
- **Action:**
  1. `.github/workflows/php-lint.yml` — PHP 8.1/8.3 lint + PHPUnit (pure-PHP tests)
  2. Optional: PHPStan level 0 as PR check
  3. Optional: PHPCS (Squiz/Symfony) as advisory
- **Impact:** 🟡 Medium — catches regressions, required for "class world" public repo
- **Effort:** 1–2 hours

### P3-2. PHPStan static analysis baseline
- **Depends on:** P3-1
- **Action:**
  1. Add `phpstan.neon.dist` (level 0–1 to start)
  2. Add `phpstan/` dir to `.gitignore`
  3. Add CI step
- **Impact:** Low — catches type errors before runtime
- **Note:** `composer.json` was deleted (T2 keep-all, composer.json removed per QA 2026-11-07).
  Dev tooling currently runs manually. If PHPStan is needed in CI, either:
  - re-add `composer.json` with dev-only deps (PHPUnit, PHPStan, PHPCS), or
  - run PHPStan as a standalone PHAR (no composer)

### P3-3. Test coverage expansion
- **Depends on:** P3-1
- Current tests: `SmokeTest` (autoloader + config) + `SkipPathsRegressionTest` (T4 edge)
- Missing test areas:
  - `Router::handle()` — case 0/1/2/default segment routing (mock `Registry`, `get_config`)
  - `Runner::tryAliases()` — exact match, pattern match, miss
  - `Runner::wpHasRealContent()` — mock `\get_page_by_path`, `\get_posts`
  - `Database` + `QueryBuilder` — mock `wpdb` (previously smoke-tested, tests deleted after pass)
  - `Model`/`Record`/`Relation` — mock DB, test lazy relations
  - `ApiController::respond()` / `error()` — HTTP response shape
  - `Auth` gateway flow — valid/invalid session paths
- **Impact:** 🟡 Medium — catches routing regressions (high-risk area per REPORT)
- **Effort:** 4–6 hours for meaningful coverage

### P3-4. `Api::health()` version fix
- **Depends on:** P1-3
- `Api::health()` hardcodes `'version' => Registry::get('version', '1.0.1')` — fallback is stale
- After P0-1 (`.starter` now calls `plugin_meta('Version')` at runtime), `Registry::get('version')`
  will return the correct plugin header version. The `1.0.1` fallback is just stale.
- **Action:** update fallback to `'1.0.10'` or remove the fallback entirely (Registry always has it after P0-1)
- **Impact:** Low — cosmetic

---

## 5. P4 — Architecture Extensions

### P4-1. Middleware support
- **Depends on:** P2-2 (i18n done first, so middleware messages are i18n'd)
- PRD §9 lists this as a future enhancement; not yet started
- **Design options:**
  - **A.** Route-level middleware array in `routing.conf.php`:
    ```php
    'GET /api/posts' => [
        'target'   => 'Api\Api::posts',
        'middleware' => ['auth', 'rate_limit'],
    ]
    ```
  - **B.** Controller method attribute (PHP 8 attribute):
    ```php
    #[Middleware(['auth', 'rate_limit'])]
    public static function posts() {}
    ```
- **Impact:** 🟠 Major — unlocks auth-guarded API routes without per-method boilerplate
- **Effort:** 4–6 hours (design + implement + test)

### P4-2. Module registration system
- **Depends on:** P0-2 (decision on auto-discovery)
- Currently each module is a directory; no manifest, no inter-module dependency declaration
- PRD §9 mentions "Module dependencies & autoloading (Composer)" — but plugin is standalone
- **Action:** add `modules.conf.php` per-module registration:
  ```php
  'modules' => [
      'Dashboard' => [
          'namespace' => 'Modules\Dashboard',
          'depends_on' => ['Login'],  // required modules
          'routes'    => true,       // auto-generate alias entries
      ],
      ...
  ]
  ```
- **Impact:** 🟡 Medium — scales beyond 5 modules
- **Effort:** 3–4 hours

### P4-3. WP-CLI commands
- **Depends on:** P4-1 (middleware)
- PRD §9 lists "CLI commands (WP-CLI)" as future enhancement
- **Action:** add `wp radykfw list-modules` / `wp radykfw test-routes` / `wp radykfw debug-route`
- **Impact:** Low — developer tooling
- **Effort:** 2–3 hours

### P4-4. Admin UI for module management
- **Depends on:** P4-2
- PRD §9 lists this as the highest-priority future enhancement
- **Action:** `wp-admin` plugin settings page — view active modules, toggle auto-discovery,
  enable/disable routes, view routing debug log
- **Impact:** 🟡 Medium — end-user facing
- **Effort:** 4–6 hours

---

## 6. Dependency Graph

```
P0-1 (commit in-flight)
├── P0-2 (alias vs auto-discovery decision)
│   ├── P1-1 (login/authenticate alias)
│   ├── P1-2 (dashboard aliases)
│   ├── P1-3 (API aliases + version fix)
│   │   └── P3-4 (health version fallback)
│   └── P1-4 (/auth alias)
├── P0-3 (junk cleanup)
├── P2-1 (i18n)
│   └── P2-2 (404 template i18n)
│       └── P4-1 (middleware)
├── P3-1 (CI)
│   ├── P3-2 (PHPStan)
│   └── P3-3 (test expansion)
└── P4-2 (module registration)
    └── P4-4 (admin UI)
P4-1 → P4-3 (WP-CLI)
```

---

## 7. Priority Summary

| Priority | Item | Impact | Effort | Blocking |
|----------|------|--------|--------|----------|
| P0-1 | Commit in-flight v1.0.10 infra | 🔴 | < 1 h | Yes |
| P0-2 | Alias-only vs auto-discovery decision | 🟠 | < 1 h (QA) | Yes |
| P0-3 | Junk file cleanup | Low | < 15 min | No |
| P1-1 | Restore `/login/authenticate` alias | 🔴 | < 1 h | Yes |
| P1-2 | Restore Dashboard routes | 🟠 | < 1 h | Yes |
| P1-3 | Restore API routes | 🟡 | < 1 h | Yes |
| P1-4 | Wire `/auth` to `Auth::index` | 🔴 | < 1 h | Yes |
| P2-1 | i18n infrastructure | 🟡 | 2–3 h | No |
| P2-2 | 404 template i18n | Low | < 1 h | No |
| P3-1 | CI pipeline | 🟡 | 1–2 h | No |
| P3-2 | PHPStan baseline | Low | 1–2 h | No |
| P3-3 | Test coverage expansion | 🟡 | 4–6 h | No |
| P3-4 | `Api::health()` version fix | Low | < 15 min | No |
| P4-1 | Middleware support | 🟠 | 4–6 h | No |
| P4-2 | Module registration | 🟡 | 3–4 h | No |
| P4-3 | WP-CLI commands | Low | 2–3 h | No |
| P4-4 | Admin UI | 🟡 | 4–6 h | No |

---

## 8. QA Decisions Needed Before Execution

1. **P0-1:** Commit in-flight changes as-is or revert `Boot.core.php` refactoring?
2. **P0-2:** Stay alias-only (A) or re-enable auto-discovery as opt-in (B)?
   - If B: add `modules.enable_auto_discovery` per-module flag in `modules.conf.php`
3. **P1:** Are Dashboard/Api modules ready to activate, or are they still "samples"?
   - If samples: keep commented and document as examples in README
4. **P2:** Target language for first `.po` translation, or just ship the `.pot` template?
5. **P3-2:** Re-add `composer.json` with dev-only deps, or use standalone PHARs for tooling?
6. **P4:** Which of P4-1 through P4-4 is in scope for the next release cycle?

---

## 9. Docs Drift — Sync Needed

| Doc | Stale Content | Fix |
|---|---|---|
| `PRD.md` §4.2 | Documents `auto_discover = true` as default | Update to reflect `false` + alias-only |
| `PRD.md` §9 | Lists "Full ORM / Migration system / Composer" — all decided against | Remove or mark "revisit" |
| `PLAN.md` §4 | C1–C5 all ✅ but C4 (footer routes) depends on `auto_discover=true` — C4 may be broken | Verify after P1 changes |
| `README.md` | "Routing Examples" section shows commented-out aliases as if they work | Update after P1 |
| `CHANGELOG.md` | `[Unreleased]` should accumulate P0/P1 items | Sync after execution |

---

## 10. Recommended Execution Order

```
Wave 1 (P0): P0-1 → P0-2 → P0-3        [≤ 2 h total]
Wave 2 (P1): P1-1 → P1-4 → P1-2 → P1-3 → P3-4  [≤ 4 h total]
Wave 3 (P2): P2-1 → P2-2               [≤ 4 h total]
Wave 4 (P3): P3-1 → P3-2 → P3-3        [≤ 8 h total]
Wave 5 (P4): P4-1 → P4-2 → P4-3 → P4-4 [depends on scope decision]
```

Each wave produces a commit + CHANGELOG entry + PLAN/REPORT sync.
