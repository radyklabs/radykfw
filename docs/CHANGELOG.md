# Changelog

All notable changes to RadykFW will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Admin UI Shared Environment (2026-11-08, branch `dev`)

- **DRAFT.md moved to `/docs`** — draft plan now lives in `docs/DRAFT.md` (was root).
- **Admin UI shared RADYK Labs environment** — design added to PLAN.md §8.1 H4:
  - Top-level menu `RADYK Labs` (`add_menu_page`, capability `manage_options`, position 80)
  - Sub-menu: Dashboard, Modules (RadykFW), Themes (RadykThemes), Settings, Tools
  - Contract interface `RadykLabs_AdminUI_Contract` — `register_menu()`, `render_page()`, `enqueue_assets()`
  - Auto-discovery logic: if another RADYK Labs product already registered the top-level menu, reuse it (add sub-menu only); otherwise register new top-level + default sub-menus
  - Shared asset enqueueing via `admin_enqueue_scripts` on `radyk-labs*` pages
  - Settings persistence via WP Options API with per-product namespace
- **QA decisions added** — PLAN.md §8.6 #6 (MVP scope) + #7 (contract location)
- **Execution order updated** — Wave 6.1 now includes H4.1–H4.10
- **New tasks added** — H4.8 refine Overview page (greeting, quick actions, version badge), H4.9 About page submenu (System Requirements, Features, Resources, License), H4.10 `menus.conf.php` for default menu data replacing hardcode in `Runner::registerAdminMenus()`
- **H4.8 executed** — `Pages::renderDashboardPage()` + `dashboard.page.php` updated with time-based greeting, user name, quick action cards (Settings, Modules, About), version badge, maintenance notice placeholder
- **H4.9 executed** — `Pages::renderAboutPage()` + `about.page.php` created with System Requirements, Features, Online Resources, Final Notes, License sections
- **H4.10 executed** — `system/Config/menus.conf.php` created; `Runner::registerAdminMenus()` refactored to read from config; hardcode removed
- **H4.11 executed** — `system/Config/actions.conf.php` created for nonce actions (moved from menus.conf.php); `Runner::initHooks()` registers nonce actions early (not in admin_menu hook); menus.conf.php restructured to `menus`/`submenus`/`separator` arrays

### Service Extraction & Router Refactor (2026-11-08, branch `feat/phase-6-hygiene-admin-ui`)

- **Extracted services from monolithic Runner** — `system/Core/Runner.core.php` refactored to pure orchestrator. Four new static service classes under `system/Services/`:
  - **`AdminUI.service.php`** — Admin UI registry: top-level menu (first-owner logic), submenu registration with position support, asset registration, notice registry with per-page gating, nonce registry, menu separator support. Replaces deleted `AdminUI.core.php`.
  - **`Actions.service.php`** — Nonce/CSRF protection service. Static `registerNonceAction()` / `verifyNonce()`.
  - **`Pages.service.php`** — Page renderers (Dashboard, Modules). Called by AdminUI callbacks.
  - **`Assets.service.php`** — Asset enqueueing service. Conditional on `radyk*` admin pages.
- **Router extended with routing methods** — `system/Core/Router.core.php` now contains all routing logic:
  - `tryAliases()` — exact + pattern matching (`{seg}`, `{seg:int}`)
  - `matchAliasPattern()` / `dispatchAlias()` — pattern parsing & dispatch
  - `getRequestPath()` — normalized request path from WP object
  - `shouldSkipRouting()` — admin/ajax/rest/static/skip-paths/skip-extensions gate
  - `wpHasRealContent()` — WP content detection via `get_page_by_path` + `get_posts`
  - `filterCanonicalRedirect()` — canonical redirect exemption from `routing.aliases` config
- **Runner now pure orchestrator** — `initHooks()` wires hooks to services, `declaredType()` for helper guard, `handleRouting()` delegates to Router, `registerAdminMenus()` uses AdminUI registry, `activate/deactivate/uninstall/flushRewriteRules` lifecycle.
- **Static callbacks** — `admin_enqueue_scripts` uses `[Assets::class, 'enqueueAdminAssets']` instead of `[$this, 'enqueueAdminAssets']` (no Runner instance needed).
- **Autoloading** — Uses existing `ext_maps` (Services → `.service.php`, Core → `.core.php`), no ext_maps changes needed.
- **Guard test** — `tests/HelperTypeGuardCliTest.php` updated to use `System\Services\AdminUI`. Standalone CLI test verifies helper type detection (9 cases) and admin notice gating (dashboard/plugins vs post).

### Helper Class Guard (2026-11-08, branch `dev`)

- **`Runner` helper type guard** — `system/Core/Runner.core.php`: helper file yang mendeklarasikan type (class/interface/trait/enum) tidak lagi di-`require`. File dilewati, dicatat di `Runner::$helper_skips`, dan ditampilkan sebagai admin notice error: helper tidak dimuat karena mengandung deklarasi class. Mencegah fatal "cannot redeclare" / bentrok dengan autoloader.
- **`Runner::declaredType()`** — scan `token_get_all`, meniru pola `miniradf` `Bootstrap.core:299` tapi diperluas: selain `T_CLASS` juga `T_INTERFACE`, `T_TRAIT`, `T_ENUM`; `Foo::class` constant dikecualikan; string & docblock tidak false-positive.
- **Check standalone** — `php tests/HelperTypeScanCheck.php`: 9 case pass (deklarasi terdeteksi, `::class`/string/docblock lolos). Tanpa PHPUnit.
- **Admin notice-registry** — `adminNotices()` di-refactor dari hardcode helper class-guard + PHP/WP version check menjadi notice-registry global `Runner::$admin_notices`. API: `Runner::addAdminNotice(string $message, string|array|null $in_page = null, string $type = 'error'): void` (static, callable dari modul/fungsi lain). Render terpusat di `adminNotices()`: tiap notice di-gate per `in_page` (`null` = semua halaman) via `screenMatches()` (`get_current_screen()`). `$type` = `error|warning|info` → class CSS `notice-{type}`. PHP/WP version notice + helper class-guard notice kini register via API yang sama (konsisten, gate fleksibel, testable).
- **E2E test** — `tests/HelperTypeGuardCliTest.php`: verifikasi guard + notice secara end-to-end tanpa WP runtime. Sample helper `guard-probe.helper.php` (mengandung class `RadykfwGuardProbe`) didaftarkan di `helper.conf.php`; guard melewatkan file, notice register di `Runner::getAdminNotices()`, ter-render di halaman Dashboard + Plugins, tidak di halaman Posts. **Probe + entri config masih aktif** (lihat PLAN R29) — hapus setelah verifikasi.

### Docs + License Alignment (2026-11-07, branch `dev`)

- **README.md full revision** — factual audit vs actual code: 28 stale/wrong items corrected. Key changes:
  - Removed stale "intercept before WordPress" claim → replaced with alias-router + WP-first gate (`routing.native=true`)
  - Fixed module structure example (controller = module name, R11)
  - Rewrote Routing Flow to reflect `Runner::tryAliases()` → gate → auto-discovery order
  - Replaced fictional Routing Examples with actual aliases from `routing.conf.php`
  - Rebuilt File Structure tree: 5 modules (Api, Auth, Dashboard, Login, Welcome), PascalCase `system/` dirs, real Core files, removed `system.helper.php` (doesn't exist)
  - Removed fictional `.drip.php`/`.component.php`/`.layout.php`/`.page.php` from Naming Conventions
  - Replaced missing helpers (`radykfw()`, `config()`, `is_radykfw_request()`) with actual API (`get_config()`, `view()`, `system_view()`)
  - Rewrote Config section: split file structure (`routing.conf.php`, `auth.conf.php`, `modules.conf.php`), `auto_discover=false`
  - Replaced Built-in Modules: 7 real modules, removed "rate limiting" claim (no `Login.model.php`)
  - Removed fictional `system/Templates/components/` + `layouts/` + `pages/`
  - Fixed Auth Flow to match S5 (post-login → `/auth` → valid/invalid redirect)
  - License header: `GPL-2.0-or-later` → `MIT` (aligns with `LICENSE` file + QA decision)
  - Credits: kept `RADYK Labs` as per QA `align-readme-mit` decision

### Testing Infrastructure (Phase 5 — 2026-11-07, branch `feat/phase-5`)

- **T1 Struktur `tests/` minimal + bootstrap (pure-PHP):** dibuat `tests/bootstrap.php` (stub `ABSPATH`/`keyfile`/`sep`, muat `.supports` untuk `get_config`+`interlace`+`pathify`, register autoloader PSR-4 `System\Core\`→`system/Core/*.core.php`, `System\`→`system/`, `Modules\`→`modules/`). `phpunit.xml` (PHPUnit 10, test suite `RadykFW`). `tests/SmokeTest.php` (autoloader + config accessor). `tests/SkipPathsRegressionTest.php` (regresi T4). Validasi: semua assertion pass via runner manual PHP 8.3.36; `php -l` clean.
- **T2 keep-all dev-deps:** `phpunit/phpunit ^10.0`, `squizlabs/php_codesniffer ^3.7`, `phpstan/phpstan ^1.10` tetap. `autoload-dev` `RadykFW\Tests\→tests/` kini benar (folder eksis). Tanpa perubahan `composer.json`.
- **T3 remove-only-52 `.gitignore`:** baris 52 `wp-content/plugins/*/vendor/` dihapus (over-broad, sudah di-cover `vendor/` baris 7). Baris 53/56 tetap.
- **T4 review-edge `Runner::shouldSkipRouting`:** logika tak diubah (sudah config-driven `get_config('routing.skip_paths')`). Edge `wp-login.php` (pretty-permalink) vs entry bare `wp-login` didokumentasikan + dikunci regresi di `SkipPathsRegressionTest.php`.
- **Docs sinkron:** PLAN (rev 14, Fase 5 T1–T4 ✅), CHANGELOG (entry ini).

### Configuration & Assets (Phase 4 — C5, 2026-11-07, branch `feat/phase-4`)

- **C5 Konsolidasi `config()`/`get_config()` — split config + unified `get_config()` (executed):** `.supports` di-unlock (QA approval):
  - `get_config()` signature di-general: return `mixed`, default `mixed $default = null`.
  - Fix bug cache mutation: dot-notation traversal kini pakai local `$data` (tak lagi mutasi `$cache[$key]`).
  - `app.conf.php` dipecah jadi file per-domain **tanpa top-level wrapper key**: `routing.conf.php`, `auth.conf.php`, `modules.conf.php`. `app.conf.php` kini hanya `app` block. Domain = nama file (`get_config('routing.aliases')` → `routing.conf.php['aliases']`).
  - `system/Helpers/config.helper.php` dihapus; `'config'` dihilangkan dari `helper.conf.php` → `['path','view','asset']`.
  - Semua caller `config()` migrasi ke `get_config()`: Runner ×5, Router ×3, Auth.controller ×1, Login.controller ×2.
- **C5 smoke-test** (standalone, `plugin_dir_path` stub + constants): `helper` → `['path','view','asset']`; `routing.aliases` (array berisi 2 alias), `auth.post_login_url` → `/auth`, `modules.auto_discover` → `false`, `routing.native` → `true`, default pada key tak ada → value default. Semua pass.
- **Docs sinkron:** PLAN (Fase 4: C5 ✅, C2 superseded, C3 path koreksi `css/fonts/inter.css`), CHANGELOG (entry ini).

### Security Polish (Phase 3 — 2026-11-07, branch `feat/phase-3`)

- **`Auth::index()` dead code fix** — panggilan `requireAuth()` dihapus dari `modules/Auth/Controllers/Auth.controller.php`. Sebelumnya `requireAuth()` selalu `exit` redirect ke `wp_login_url()` (WP login) sebelum blok `if (!is_user_logged_in())` yang redirect ke `config('auth.login_url','/login')` — sehingga redirect `/login` tak pernah tercapai. Gateway kini: tak login → `/login` (config), login → `/dashboard`.
- **Verifikasi S1–S5 (PLAN Fase 3):** semua item terverifikasi terhadap kode aktual.
  - S1 ✅ — `filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW)` di `Login.controller.php:52` (klaim "baris 49" direvisi).
  - S2 ✅ — sudah tereksekusi commit `eb942dd`: view bersih dari `$_POST`, controller pass `username` + `action_url`, error via `$_SESSION` (redirect flow). Status PLAN direvisi Open → Done.
  - S3 ✅ — sudah tereksekusi `eb942dd`: `action="<?php echo esc_url($action_url); ?>"` (`login.view.php:131`). Status PLAN direvisi Open → Done.
  - S4 ✅ — `.gitignore` konsisten (`.bak`/`.junk` baris 3–4 + `*.bak`/`*.junk`/`*.old.junk` baris 58–60); 0 file bak/junk ter-track.
  - S5 ✅ — setelah fix dead code di atas.
- **Docs sinkron:** PLAN (rev 13, Fase 3 ✅), REPORT (§1.1 mismatch 5 → 3; §2.7 → RESOLVED; §3 Fase 3 → Selesai; §8 baru), CHANGELOG (entry ini).

### Data Layer (Phase 2 — 2026-11-06, branch `feat/phase-2`)

**File split (rev 3)**
- `system/Core/Model.core.php` dipecah dari 24KB monolith → 4 file terpisah:
  - `Model.core.php` (9.5KB) — `abstract class Model` saja; auto-`require_once` 3 sibling
  - `QueryBuilder.core.php` (5.3KB) — fluent builder
  - `Record.core.php` (4.6KB) — row wrapper + magic `__get` lazy relations
  - `Relation.core.php` (4.4KB) — `belongsTo/hasMany/hasOne` descriptor
- Setiap file tetap `.core.php` di `system/Core/` → autoloader `ext_maps` `Core → .core.php` tetap resolve. Autoloader load class `QueryBuilder`/`Record`/`Relation` secara langsung, TANPA perlu `Model` loaded dulu.
- `Model.core.php` top-level `require_once` 3 file sibling (belt-and-suspenders: guarantee load order even if autoloader tidak pernah resolve class sibling sebelum `Model`).

**Eloquent-lite layer (rev 2 — rewrite)**
- **`system/Core/Database.core.php`** (rewritten) — `final class Database` singleton atas `$wpdb`: `select/selectOne/selectValue/count/insert/update/delete/execute/transaction` + `prefix()` (auto-prefix `$wpdb->prefix`, `posts`→`wp_posts`, idempotent). Semua nilai lewat placeholder `?` ke `$wpdb->prepare()` — tak ada string concat (anti-injection). `insert/update/delete` auto-prefix nama tabel logis.
- **`system/Core/Model.core.php`** (rewritten, Eloquent-lite) — 4 class dalam 1 file (autoloader load `Model`):
  - `abstract class Model`: entry `query/where/orderBy/limit/offset/first/get/find/count/exists/insert/update/delete`; `$table` nama logis (auto-prefix), `$key` PK.
  - `QueryBuilder` (fluent): `where/where(col,op,value)/orWhere/orderBy/limit/offset/select/get/first/count/exists` — prepared values only.
  - `Record`: row wrapper, magic `__get` untuk lazy-relation + array-access, `toArray()`, `id()`.
  - `Relation` (`belongsTo`/`hasMany`/`hasOne`): descriptor + lazy loader; best-effort resolve model class `Modules\{Module}\Models\{Pascal}`.
- Sample model di-update ke gaya ORM: `modules/Dashboard/Models/Stats.model.php` (`$table='posts'`, `publishedCount()=where('post_status','publish')->count()`, `recent()` fluent), `modules/Api/Models/Posts.model.php` (`$table='posts'`, `list()` fluent, `author()=belongsTo('users','post_author')`).
- Desain keputusan (QA): Eloquent-lite (bukan full-ORM/data-mapper penuh) — 3 relations + fluent builder + auto-prefix, tanpa migration/eager-load/casts/events. Kompat developer yang familiar Laravel/Eloquent.
- **Smoke-test 13/13 pass** (mock `wpdb`, fixture `wp_posts`/`wp_users`): prefix, count, get/first, find, transaction, orWhere, insert, lazy belongsTo, no double-prefix. Test file dihapus setelah pass.
- **Split-file smoke-test 9/9 pass** (rev 3): prefix, count, get, lazy author, hasMany descriptor, insert, update, delete, find — semua resolve dari 4 file terpisah.

**Bug fix (verifikasi browser `/api/posts`)**
- **`wpdb->prepare()` placeholder**: placeholder berubah dari `?` → `%s`/`%d` (WP `wpdb::prepare` pakai gaya printf, bukan PDO-style `?`). QueryBuilder WHERE, `Model::find`, Relation loader — semua diubah ke `%s`/`%d`. Nilai tetap via prepared (anti-injection).
- **`prepared()` varargs**: `$wpdb->prepare($sql, $values)` (1 array) diinterpretasi WP sebagai 0-placeholder + 1-arg → notice. Diperbaiki: single-value path `prepare($sql, $values[0])`, multi-value `prepare($sql, ...$values)`.
- **QueryBuilder `compileWhere` OR bug**: clause pertama emit `AND col = %s` (invalid SQL) saat where tunggal. Joiner default sekarang `''` (bukan `'AND'`).
- **Multisite table prefix (WP `wp_N_`)**: `Database::prefix()` kini resolve tabel WP core lewat properti `$wpdb->posts`/`users`/dst (authoritative, handle `wp_N__posts`), fallback `prefix concat` untuk tabel kustom (separator adaptif `'_'`/`''` tergantung suffix `$wpdb->prefix`). Sebelumnya generate `wp_3___posts` (3 underscore) → 0 row; sekarang `wp_3_posts` benar.
- **`/api/posts` endpoint aktif**: `Api::posts()` kini query nyata via `Posts::list()` + `Posts::totalPublished()` (sebelumnya `data => []` hardcode). Browser-verified: return 4 post publish.

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
- **Config Consolidation (Executed — Phase 4, 2026-11-07):** `app.conf.php` split into domain-specific files (`routing.conf.php`, `auth.conf.php`, `modules.conf.php`) **without top-level wrapper key** — domain = file name. `get_config('auth.post_login_url')` → `auth.conf.php['post_login_url']`. `config.helper.php` removed; `helper.conf.php` drops `'config'` → `['path','view','asset']`. All callers migrated `config()` → `get_config()` (Runner ×5, Router ×3, Auth ×1, Login ×2). `.supports` unlocked: `get_config()` return type `mixed` + cache-mutation bug fixed (dot-traversal now uses local var, no longer mutates `$cache[$key]`).
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

---

## [1.0.9] - 2026-11-06 (Opsi B: Gate cek konten nyata DB — parse_request)

### Changed
- **`Runner::handleRouting()`** — native=true kini jalankan: alias → `wpHasRealContent()` → auto-discovery → 404. native=false: alias → auto-discovery → WP. Gate query-var (`isWpNativePath`) dihapus karena selalu true di pretty permalink (akar bug F1).
- **`Runner::wpHasRealContent()`** (baru) — cek konten WP nyata di `parse_request`: `\get_page_by_path()` untuk page + `\get_posts(['name'=>...])` untuk post. Object-cache-friendly, cost minimal.
- **`isWpNativePath()` + `handleRoutingFallback()` + hook `template_redirect`** — dihapus (Opsi A dibatalkan).

### Fixed
- `/welcome/welcome/index` kini **200** (auto-discovery jalan lagi). Sebelumnya 404 karena gate query-var selalu true.
- `status_header(200)` fix Opsi A tidak diperlukan lagi — dispatch terjadi di `parse_request` sebelum WP output.

### Decisions (QA 2026-11-06)
- Opsi A (`template_redirect` + `is_404()`) dibatalkan: hook terlalu telat, `get_post_by_path()` undefined di WP 7.1.4, state-404 re-send tak bisa dicegah
- Opsi B: DB lookup di `parse_request` — trade-off +1 query ringan (object-cache mitigasi) demi gate akurat

### Verified
- 9/9 browser test pass: alias 200, pattern 200, auto-disc deep 200, WP page 200 (gate), miss 404
- `php -l` clean

---

## [1.0.8] - 2026-11-06 (Config-Driven Query Vars + Skip Lists)

### Changed
- **`Runner::isWpNativePath()`** — hardcoded `$nativeQueryVars` array (18 var) replaced with `config('routing.native_query_vars', [])`; list moved to `app.conf.php` `routing.native_query_vars`. Follows the same `skip_paths` pattern already used in the same method — one config section owns the gate.
- **`Runner::shouldSkipRouting()`** — hardcoded login-path list (`['wp-login','wp-signup','wp-activate','login','signup','activate']`) replaced with `config('routing.skip_paths')` (merged into the existing config list: WP auth/admin + infra paths); hardcoded static-extension regex replaced with `pathinfo()` + `config('routing.skip_extensions')` — that config key existed but was never read (dead config) until now.

### Verified
- `php -l` clean (Runner, app.conf); behavior identical: `/login` 200 (alias before skip), `/welcome` 200, `/welcome/door/a` 200, `/sample-page/` 200 (WP), `/welcome/welcome/door` 404 (gate intact); zero leftover hardcoded query vars / extension list in Runner

---

## [1.0.7] - 2026-11-06 (Config-Driven Canonical Route List)

### Changed
- **`Runner::filterCanonicalRedirect()`** — hardcoded `$our_routes` array (login/auth/welcome) replaced with derivation from `config('routing.aliases')`: static keys (after `METHOD ` prefix) become the exempt list automatically; pattern keys containing `{}` skipped (not literal URLs). Superset behavior: all static alias routes (dashboard, dashboard/stats, api/*, …) now canonical-exempt too — adding an alias automatically covers it, no second list to maintain.

### Verified
- `php -l` clean; `/welcome` → 200 (no 301), `/welcome/` → 200, `/login/` → 200 — canonical redirect still blocked for claimed routes
- `assets/css/fonts/inter.css` → 200 (was 500)

### Fixed
- **Inter font URL (pre-existing)** — `Welcome::index` passed `fonts/inter.css` resolving to `assets/fonts/inter.css` while the file lives at `assets/css/fonts/inter.css` → asset request 500'd (server config on missing file). Caller now passes `css/fonts/inter.css`.

---

## [1.0.6] - 2026-11-06 (Alias Pattern Placeholder + Routing Dead-Code Cleanup)

### Added
- **Alias pattern placeholders** — `Runner::tryAliases()` now resolves in two phases: exact match first, then pattern loop for keys containing `{seg}` (string, must match `[A-Za-z0-9_-]+`) or `{seg:int}` (digits only; value still passed as string per QA decision). Equal segment count required; new helpers `matchAliasPattern()` and `dispatchAlias()`. Args routes dispatch **before all gates** (native/skip untouched by design).
- **Pattern alias entries** — `GET /welcome/door/{a}`, `GET /welcome/door/{a}/{b}`, `GET /welcome/home/{id:int}` in `app.conf.php`

### Removed
- **Router Fallback 1 (dead code)** — case 0/1/2 always set `method='index'`, so the retry was an identical call that could never succeed where the first attempt failed; `$isDefaultRoute` variable removed with it (PLAN R23)
- **Router duplicate alias block (F4)** — `Runner::tryAliases()` is now the single alias parser (always runs before every gate, keyed on original `REQUEST_URI`); `Router::handle()` is pure auto-discovery (PLAN R24)

### Decisions (QA 2026-11-06)
- Framework officially an **alias router**: `isWpNativePath` gate left as-is (F1); non-alias auto-discovery secondary under `routing.native=true`
- Alias claims win over strict segment interpretation (`/welcome/home` = 200 via alias = correct)
- 404 style: WP theme template (status quo)

### Verified
- PHP lint clean (Router, Runner, app.conf)
- Browser 7/7: door 1-arg ✓, door 2-arg ✓, home int ✓, home non-int → 404 ✓, exact aliases ✓, login ✓, non-alias path → 404 ✓

---

## [1.0.5] - 2026-11-06 (Fase 1 Complete — R12, R15, R17 Fixes)

### Fixed
- **Welcome.controller.php (R12)** — `home()`/`door()` now accept `string ...$args` and forward `['args' => $args]` to views; the `!empty($args)` display block is now reachable
- **app.conf.php + Router (R15)** — `routing.default` simplified from 3-key array (never read) to module name `'Welcome'`; Router `case 0` now reads config via `config('routing.default','Welcome')` + `normalizeClassName()` and infers controller=module, method=index
- **Router.core.php + Runner.core.php (R17)** — 6 hardcoded `"Modules\\"` class-name builds replaced with `{$nsmod}` (Registry, derived from `.starter` `modules_dir`, trailing backslash); the previously dead `$nsmod` variable is now actually used — dispatch and autoloader share one source of truth

### Notes
- Fase 1 (R1–R21) fully ✅ after line-by-line verification + execution
- PHP lint clean on all 5 changed files
- Remaining PLAN open items (S2, S3, C4, T2, T3) belong to Fase 3/4/5

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

## [1.0.3] - 2026-11-06 (Phase 1.1 Runner & Router Fixes)

### Fixed
- **Runner.core.php** — `shouldSkipRouting()` now also skips short WP paths (`login`, `signup`, `activate`) in addition to `wp-login`, `wp-signup`, `wp-activate` (R2)
- **Router.core.php** — Alias parsing now supports both explicit `Module\Controller::method` and shorthand `Module::method` formats (R20)

### Notes
- Phase 1.1 (Runner + Router cleanup) now complete
- 28/33 audit items match (was 27)
- 5 items remaining open (S2, S3, C4, T2, T3)

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
