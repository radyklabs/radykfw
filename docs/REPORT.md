# REPORT — Audit & Review RadykFW

> **Update 2026-11-10 — PLAN §8.4 A1.1 implemented:** `system/Middleware/` holds middleware implementations (`System\Middleware`, `.middleware.php`); `AuthMiddleware.service.php` migrated to `system/Middleware/Auth.middleware.php` (`System\Middleware\Auth`). The Services pipeline (`System\Services\Pipeline`, renamed from `RouteMiddleware` for explicitness), Contracts interface, and explicit config registry remain unchanged; no middleware auto-discovery. `Registry::ext_maps` gained the `Middleware` mapping under explicit user permission. Route/middleware tests and lint of affected files pass.

> Approved 2026-11-10: PLAN 8.4 A1–A3 execution uses route-level middleware config, explicit name-to-class system registry, bundled auth login redirect, legacy string aliases. Module config remains additive and isolated; CLI tooling noninteractive. Verified: 95 tests / 270 assertions; PHPStan no errors; PHPCS no findings; PHP lint 80 files. CLI command handlers tested directly; live WP-CLI integration not exercised because wp executable is unavailable. No locked files changed; no merge/release.

> Audit menyeluruh terhadap plugin WordPress HMVC **radykfw** (standalone, tanpa dependensi third-party).
> Tanggal: 2026-11-04. Cakupan: seluruh file aktif di `system/`, `modules/`, `.supports`, `.starter`, `radykfw.php`, `composer.json`, `.gitignore`.
> **Update 2026-11-07:** Re-audit Fase 3 (§8) — S1–S5 terverifikasi; dead code `Auth::requireAuth()` diperbaiki.
> **Update 2026-11-08:** Service Extraction + Router Refactor (branch `feat/phase-6-hygiene-admin-ui`) — Extracted services from monolithic Runner into `system/Services/`, extended Router with all routing methods, Runner now pure orchestrator.
> **Update 2026-11-09:** Phase 6 §8.2 I1–I5 implemented: WordPress locale-aware loading, admin-only locale overrides, `id_ID` catalog, gettext wrappers across frontend/admin views and validation/notices, and standalone POT/PO/MO conversion. I6 completion and validation are recorded below.
> **Update 2026-11-09:** Phase 6 §8.2 I6 implemented: typed settings allowlist and effective-value layers, site Options and user-ID-keyed `user_meta`, protected admin Settings page, runtime routing integration, and versioned JSON backup export excluding secrets. PHP lint, 178-message POT/PO parity, site/user precedence and isolation, save validation, backup authorization, and catalog decompilation smoke checks pass; PHPUnit is unavailable in this environment. No legacy runtime settings required migration.
> **Update 2026-11-09:** Phase 6 §8.1 H4.13 implemented: `AdminUI` owns default/module menu discovery and late WordPress registration; modules contribute declarative `Configs/menus.conf.php` files in stable order, with the `radykfw_admin_menu_config` filter as the extension point. Overview uses the same `radyk` slug as the top-level page. Targeted PHP lint and menu-registration checks pass; PHPUnit is unavailable in this environment.
> **Update 2026-11-09:** Release automation is bootstrapped on `main`: `>release` prepares and merges a versioned PR when repository rules allow it, then the workflow validates the exact merge and creates its annotated tag. The workflow configures the GitHub Actions bot identity and provides a manual recovery dispatch that verifies a merged release PR before retrying a failed tag.
> **Update 2026-11-10:** Planning decision for Phase 6 P4 A1–A3: discover each module's `Config/` independently of route auto-discovery; permit only additive route-alias, menu, and nonce-action contributions; keep `app.conf.php` and other system-owned config files protected with their existing names/paths. Middleware, WP-CLI, and the `make-module` scaffold command must be AI-friendly. Implementation and explicit `Configs/` → `Config/` migration diagnostics are complete; see PLAN.md §8.4 and PRD.md §8.5.4.
>
> **Kondisi boot terverifikasi:** rangkaian `.starter → .supports → Boot → Runner → helpers` dieksekusi hingga sukses pada PHP 8.3.36 (harness terpisah, tanpa WP). Registry berisi 18 key, `view()` & `get_config()` ter-load. **Tidak ada fatal error saat boot.**

## 1. Ringkasan

| Skala | Jumlah | Contoh |
|---|---|---|
| 🔴 Critical | 3 | Validasi input segment, fallback Router, `extract()` di view |
| 🟠 Major | 10 | Dead code, skip-routing, konstan global, no-op `fallbackToWordPress`, dst. |
| 🟡 Minor | 9 | Docblock, casing, inline CSS, dst. |
| 🔵 Info | 5 | Observasi |

**File di-lock** (tidak diubah, hanya diaudit): `radykfw.php`, `.starter`, `.supports`, `system/Core/Boot.core.php`, `system/Core/Registry.core.php`.

---

## 1.1 Audit Verification Summary (2026-11-06, rev 7)

**PLAN.md claims vs Actual Code State:**
- ✅ **28 items match** — PLAN claims "Done" and code confirms done
- ⚠️ **5 items mismatch** — PLAN claims "Done" but code shows still open:
  1. `login.view.php` `$_POST['username']` (S2) — not executed
  2. `login.view.php` `action=""` (S3) — not executed
  3. Footer links point to non-existent routes `/documentation`, `/changelog` (C4) — kode sudah `home_url()`, route tak ada
  4. `.gitignore` overly broad pattern (T3) — line 52 still present
  5. Composer dev-deps without `tests/` (T2) — not executed

**Re-verifikasi Fase 3 (2026-11-07, `feat/phase-3`):** item 1 & 2 ternyata **salah audit** — sudah tereksekusi commit `eb942dd` (2026-11-05), status PLAN direvisi → ✅. S5 diperbaiki: panggilan `requireAuth()` di `Auth::index()` dihapus (dead code, redirect ke `wp_login_url()` WP sehingga blok `/login` tak pernah tercapai). Sisa mismatch: C4, T2, T3 (Fase 4/5).

**Eksekusi Fase 5 (2026-11-07, `feat/phase-5`):** item 4 & 5 (T2, T3) kini **RESOLVED** — T3 baris 52 dihapus (remove-only-52), T2 dev-deps keep-all + `tests/` eksis. T4: logika `shouldSkipRouting` tak diubah; edge bare-vs-`.php` didokumentasikan + dikunci regresi di `tests/SkipPathsRegressionTest.php`. T1: `tests/` + bootstrap + 2 test pure-PHP. Sisa mismatch saat ini: hanya C4 (Fase 4, footer routes).

**Resolved sejak audit awal:** M2 `shouldSkipRouting` (commit 7415e29); R12 `$args` pass-through & R15 routing.default simplify (eksekusi Fase 1, rev 7).

**Eksekusi Fase 1 (rev 7):** R1–R21 **semua Done**. Fix: `Welcome::home/door` variadic → view; `routing.default` = module name + Router `case 0` baca config; hardcode `Modules\` → `$nsmod` (6 titik). Semua sisa mismatch (S2/S3/C4/T2/T3) di luar Fase 1. *(Update 2026-11-07: S2/S3 diselesaikan — lihat §8.)*

**Action:** PLAN.md Revision 7. Fase 1 complete. 5 non-Fase-1 items need execution. *(Update 2026-11-07: S2/S3 selesai — sisa 3 items: C4, T2, T3, Fase 4/5.)* *(Update 2026-11-07: T2/T3/T4 selesai pada `feat/phase-5` — sisa mismatch 1 item: C4.)*

---

## 1.3 Gate Routing Re-design (Opsi B — cek konten nyata)

**Problem (F1 revisi):** `isWpNativePath()` memeriksa query-var WP (`pagename`, `name`, dst.) pada `parse_request`. Pada pretty permalink, WP selalu mengisi query-var untuk SEMUA path non-root — sehingga gate **selalu true**, dan auto-discovery tak pernah berjalan (bukti: `/welcome/welcome/index` 404 padahal `Welcome::index` valid).

**Revisi (Opsi B):** Ganti query-var check dengan `wpHasRealContent()` yang melakukan **DB lookup object-cache-friendly** di `parse_request`:
- `\get_page_by_path($path, 'page')` — cek page slug
- `\get_posts()` by name — cek post slug
- `true` → WP wins (konten nyata ada); `false` → alias → auto-discovery → 404

**Order spec final (QA):**
```
native = true :  parse_request → alias → wpHasRealContent? → auto-discovery → 404
native = false:  parse_request → alias → auto-discovery → 404
```

**Browser test (9/9 pass):**
| Path | Hasil | Penjelasan |
|---|---|---|
| `/welcome/welcome/index` | 200 | auto-disc `Welcome::index` (kasus awal) |
| `/welcome/home` | 200 | alias exact |
| `/welcome/door/a` | 200 | pattern alias 1 arg |
| `/welcome/door/a/b` | 200 | pattern alias 2 args |
| `/welcome/home/123` | 200 | pattern `{id:int}` valid |
| `/sample-page/` | 200 | WP page asli (gate menang) |
| `/login` | 302 | alias → `wp-login.php` |
| `/welcome/abc/xyz/def` | 404 | auto-disc miss → WP 404 |
| `/nonexistent/unknown/route` | 404 | miss semua → WP 404 |

**Catatan:** `get_post_by_path()` tidak tersedia di WP 7.1.4 → ganti dengan `\get_posts(['name'=>...,'post_type'=>'post','post_status'=>'publish'])`. Opsi A (`template_redirect` + `is_404()`) dibatalkan karena: (1) hook terlalu telat — WP sudah set state 404; (2) `wp_head()`/`wp_footer()` di view memicu re-send 404 yang mengalahkan `status_header(200)`; (3) `get_post_by_path()` undefined di versi ini.

---

## 1.2 Routing Deep Audit (2026-11-06, browser + kode)

| # | Temuan | Status | Resolusi (QA) |
|---|---|---|---|
| F1 | `isWpNativePath` terlalu luas — WP selalu set `pagename` (catch-all rewrite) → `Router::handle` tak pernah jalan untuk path non-root; **hanya alias yang dispatch** (bukti: `/welcome/welcome/door` 404 walau class+method ada) | 🔵 By design | **Keputusan: biarkan** — framework resmi = alias router |
| F2 | Konflik makna segment `/welcome/home`: strict (Welcome/Home/index → 404) vs alias (Welcome::home → 200) | ✅ Resolved | **Keputusan: alias menang** — alias = klaim eksplisit |
| F3 | Fallback1 duplikat murni (case 0/1/2 selalu `method='index'`) | ✅ Resolved | Dihapus (PLAN R23) |
| F4 | Blok alias duplikat di `Router::handle` (parser ganda, drift risk) | ✅ Resolved | Dihapus — 1 parser di Runner (PLAN R24) |
| F5 | Fallback2 `Index` selalu false (tak ada `Index.controller`) | 🔵 Info | Mekanisme dorman, dibiarkan |
| F6 | View `door` klaim URL `/welcome/home/door/...` — path itu 404 | ⚠️ Minor | Kosmetik, tercatat |
| F7 | Alias bypass gate native/skip (jalan duluan) | 🔵 By design | Dibutuhkan untuk `/login` & pattern args |
| **F8** | **Alias exact-match tak menerima args** (`/welcome/door/a/b` = 404) | ✅ Resolved | **Pattern placeholder** `{seg}` / `{seg:int}` — exact-first, lalu loop pattern (PLAN R22) |

**Browser test (7/7 pass):** `/welcome/door/a` ✓200 | `/welcome/door/a/b` ✓200 | `/welcome/home/123` ✓200 | `/welcome/home/abc` ✓404 | `/welcome/home` ✓200 exact | `/login` ✓200 | `/welcome/welcome/door` ✓404 (gate F1).

---

## 2. Temuan per File

> Status: ✅ RESOLVED (sudah di-fix), ⚠️ STILL OPEN (masih perlu perhatian), 🔵 Info

### 2.1 `system/Core/Router.core.php`

- ✅ **C1 — Input segment tak tervalidasi** — **RESOLVED**. `isValidSegment()` ditambahkan, semua case validasi segment.

### 2.1.4 `system/Core/Router.core.php` + `system/Core/Runner.core.php` + `system/Services/` — Service Extraction & Router Refactor (2026-11-08, branch `feat/phase-6-hygiene-admin-ui`)

**Status: ✅ RESOLVED** — Major architectural refactor completed.

**Changes:**
- **Extracted 4 services from monolithic Runner** into `system/Services/` (static services, autoloaded via existing `ext_maps`):
  - `AdminUI.service.php` — Admin UI registry: per-slug first-owner top-level menus, submenu with position support, asset registration, notice registry with per-page gating, nonce registry, menu separator support. Replaces deleted `AdminUI.core.php`.
  - `Actions.service.php` — Nonce/CSRF protection service. Static `registerNonceAction()` / `verifyNonce()`.
  - `Pages.service.php` — Page renderers (Dashboard, Modules). Called by AdminUI callbacks.
  - `Assets.service.php` — Asset enqueueing service. Conditional on `radyk*` admin pages.
- **Router extended with all routing methods** (moved from Runner):
  - `tryAliases()` — exact + pattern matching (`{seg}`, `{seg:int}`)
  - `matchAliasPattern()` / `dispatchAlias()` — pattern parsing & dispatch
  - `getRequestPath()` — normalized request path from WP object
  - `shouldSkipRouting()` — admin/ajax/rest/static/skip-paths/skip-extensions gate
  - `wpHasRealContent()` — WP content detection via `get_page_by_path` + `get_posts`
  - `filterCanonicalRedirect()` — canonical redirect exemption from `routing.aliases` config
- **Runner now pure orchestrator** — `initHooks()` wires service hooks, `declaredType()` for helper guard, `handleRouting()` delegates to Router, and `activate/deactivate/uninstall/flushRewriteRules` lifecycle. Menu config loading and `admin_menu` ownership belong to AdminUI.
- **Static callbacks** — `admin_enqueue_scripts` uses `[Assets::class, 'enqueueAdminAssets']` instead of `[$this, 'enqueueAdminAssets']` (no Runner instance needed).
- **Autoloading** — Uses existing `ext_maps` (Services → `.service.php`, Core → `.core.php`), no ext_maps changes needed.
- **Guard test** — `tests/HelperTypeGuardCliTest.php` updated to use `System\Services\AdminUI`. Standalone CLI test verifies helper type detection (9 cases) and admin notice gating (dashboard/plugins vs post).

**Commits:** `83ffbb9` (extract services + Router methods), `adfd96d` (static callback), `e91d107` (fix missing filterCanonicalRedirect).

### Modular Admin Menu Registration (Phase 6 H4.13)

- `AdminUI::registerHooks()` owns the late `admin_menu` hook and registers the default menu config plus module contributions.
- Optional `modules/{Module}/Configs/menus.conf.php` files can add top-level menus and submenus; discovery and merge order are deterministic, with schema/capability/slug checks and admin notices for rejected config.
- The WordPress filter `radykfw_admin_menu_config` receives the fully merged configuration. No separate hook engine was introduced.
- First-owner behavior is per top-level slug. The explicit Overview submenu uses slug `radyk`, avoiding WordPress's generated parent-title submenu while targeting the same page.
- `tests/AdminUIMenuTest.php` covers ordering, module contributions, filter use, duplicate rejection, same-slug Overview, and Runner separation.
- ✅ **C2 — Fallback `Index::index()` membuat URL tak dikenal selalu "matched"** — **RESOLVED**. Fallback Index hanya untuk 0–1 segments; deep routes fall-through ke WP 404.
- ✅ **M — `dispatchFallback` mengabaikan `$params` + docblock menyesatkan** (baris 220–227) — **RESOLVED**. `dispatchFallback($class, $params)` tidak pakai `$params`; docblock bilang "Retry the SAME method" padahal retry ke `index`. **Fix:** hapus param tak terpakai, perbaiki docblock. ✅ **DONE** (commit `eb942dd`).
- ✅ **M — `fallbackToWordPress()` no-op & mati** — **RESOLVED**. Sudah dihapus.
- ✅ **M — `strtolower` merusak camelCase** (baris 57, 65, 73) — **RESOLVED**. `ucfirst()` tanpa `strtolower()` menjaga camelCase. `ucfirst($segment)` mempertahankan camelCase (e.g., `userProfile` → `UserProfile`). **Fix:** validasi + normalisasi seragam (tanpa `strtolower`). ✅ **DONE** (commit `787da28`).
- ✅ **M — case 0 hardcode `Welcome/Welcome/index`** — **RESOLVED**. Sekarang pakai `Index` controller fallback.
- 🔵 **m — `method_exists` sebelum `ReflectionMethod`** (baris 147, 239) — **Info**. Sekarang di luar `try`, aman. Tidak perlu ubah.
- 🔵 **i — `tryDispatchNoRecurse` duplikat ~80% `tryDispatch`** (baris 237–259) — **RESOLVED**. Extracted `invokeMethod()` helper. Eliminated ~80% code duplication. ✅ **DONE** (commit `787da28`).
- 🔵 **i — class `final` tapi tanpa private constructor** — **Info**. Masih bisa di-`new`. Opsional: tutup.

### 2.2 `system/Core/Runner.core.php`

- ✅ **M1 — `initRegistry()` mati** (baris 41–46) — **RESOLVED**. Method sudah dihapus dari kode.
- 🟠 **M2 — `shouldSkipRouting` cek path yang tak akan muncul** (baris 156) — **RESOLVED**. `in_array($path, ['wp-login','wp-signup','wp-activate','login','signup','activate'])` — added short forms. **PLAN.md R2: ✅ Done** (commit 7415e29).
- ✅ **M3 — `add_action('template_redirect', fn(){exit;})` per request** — **RESOLVED**. Sudah dihapus dari `initHooks()`.
- ✅ **M4 — `uninstall()` memanggil `flush_rewrite_rules()`** — **RESOLVED**. `uninstall()` hanya `delete_transient()`, tidak memanggil `flush_rewrite_rules()`.
- ✅ **R5 — hapus param `$extra_query_vars` dari `handleRouting()`** — **RESOLVED**. Signature sekarang `handleRouting(\WP $wp)`.

### 2.1.1 `system/Core/Router.core.php` — Routing Enhancements (Planned)

- ✅ **P1 — Routing default simplifikasi** — **RESOLVED**. `routing.default` hanya `'Welcome'` (module name). Router infer `controller=module`, `method='index'`.
- ✅ **P2 — Route aliases dengan HTTP method** — **RESOLVED**. `routing.aliases` di `app.conf.php`: format `'POST /login' => 'Login\Login::login'`, `'GET /auth' => 'Auth\Auth::index'`. Runner cek aliases sebelum auto-discovery.
- ✅ **P3 — Auto-discovery toggle** — **RESOLVED**. `modules.auto_discover` di `modules.conf.php` (default `true`). Jika `false`: hanya aliases yang match, sisanya fall-through ke WP.
- ✅ **P4 — Namespace prefix dari `.starter`** — **RESOLVED**. `namespace_prefix` dihapus dari config. Diambil dari `.starter` `modules_dir`: `ucfirst(modules_dir)` = `Modules`. Router pakai nilai ini.
- ⚠️ **P5 — Router alias parsing inconsistency** — **OPEN**. Router `handle()` hanya parse shorthand `Module::method` (regex `/^([A-Za-z_][A-Za-z0-9_]*)::(\w+)$/`), tapi config & Runner pakai explicit `Module\Controller::method`. Perlu update regex di Router.
- 🟡 **m5 — `activate()` `wp_mkdir_p` tanpa cek return** (baris 85) — **STILL OPEN**. **Fix:** cek return.
- ✅ **m6 — `adminNotices()` escape versi** — **RESOLVED**. Sudah pakai `Registry::get('wp_version')` di baris 178, 180.
- ✅ **i7 — `handleRouting(\WP $wp, array $extra_query_vars = [])`** — **RESOLVED**. Param 2 dihapus.
- 🔵 **i8 — `register_uninstall_hook` dengan `static::uninstall`** (baris 68) — **Info**. Bekerja, tapi tidak standar.
- 🔵 **i9 — priority `parse_request` = 10** (baris 71) — **Info**. Setara dengan core; urutannya registration order.
- 🔵 **i10 — method publik tanpa `@since`** — **Info**. Konsisten dengan file lain, opsional.

### 2.1.2 `system/Core/Router.core.php` — Issues Found (New)

- 🟠 **N1 — `strtolower` merusak camelCase** — **RESOLVED**. `normalizeClassName()` pakai `ucfirst()` tanpa `strtolower()`. `ucfirst($segment)` mempertahankan camelCase (e.g., `userProfile` → `UserProfile`). ✅ **DONE** (commit `787da28`).
- 🟠 **N2 — `dispatchFallback` param `$params` tidak dipakai** — **RESOLVED**. `dispatchFallback(string $controllerClass)` tanpa `$params`; docblock diperbaiki. ✅ **DONE** (commit `eb942dd`).
- 🟠 **N3 — Router alias parsing hanya shorthand** — **OPEN**. `handle()` regex hanya match `Module::method`, tidak support `Module\Controller::method` seperti di config & Runner. Perlu update regex.

### 2.1.3 `system/Core/Runner.core.php` — Helper Type Guard (New)

- ✅ **H1 — helper file yang mengandung class tidak di-load** — **RESOLVED** (2026-11-08). `initHooks()` mendeteksi deklarasi type di tiap file helper via `Runner::declaredType()` (`token_get_all`: T_CLASS/T_INTERFACE/T_TRAIT/T_ENUM, `Foo::class` dikecualikan). File ber-type dilewati + tercatat di `Runner::$helper_skips` + admin notice error `admin_notices` ("Helper file X was not loaded because it contains a class declaration"). Pola scan diambil dari `miniradf` `Bootstrap.core:299`, diperluas ke interface/trait/enum. Check: `php tests/HelperTypeScanCheck.php` (9 case pass).
- ✅ **H2 — notice gate ke Dashboard + Plugins** — **RESOLVED** (2026-11-08). `screenMatches()` (`get_current_screen()`) dibatasi ke `dashboard` + `plugins`. Test 3 case screen: dashboard/plugins = notice muncul; post = notice hilang.
- ✅ **H3 — notice-registry global** — **RESOLVED** (2026-11-08). `adminNotices()` di-refactor dari hardcode helper class-guard + PHP/WP version check menjadi notice-registry global `Runner::$admin_notices`. API: `Runner::addAdminNotice(string $message, string|array|null $in_page = null, string $type = 'error'): void` (static, callable dari modul/fungsi lain). Render terpusat di `adminNotices()`: tiap notice di-gate per `in_page` (`null` = semua halaman) via `screenMatches()`. `$type` = `error|warning|info` → class CSS `notice-{type}`. PHP/WP version notice + helper class-guard notice kini register via API yang sama (konsisten, gate fleksibel, testable).

### 2.3 `system/Helpers/*` (post-cleanup)

- ✅ **C3 — `extract($data)` di `view()`** — **RESOLVED**. `view()` sekarang di `view.helper.php` pakai `extract($scope, EXTR_SKIP)`.
- ✅ **M — Duplikasi `dd()`/`dump()` dengan `.supports`** — **RESOLVED**. Helper `debug.helper.php` dihapus; `dd`/`dump` tak terpakai.
- ✅ **i — helper global mem-pollute namespace global** — **RESOLVED**. `dd()`/`dump()` dihapus. `config()` & `asset_*()` **dipertahankan** (diperlukan untuk config & asset enqueue).
- 🟡 **m — `config()` vs `get_config()` mirip** — **STILL OPEN (keputusan QA: split + konsolidasi)**. Keputusan: (1) `get_config()` di `.supports` di-general → `get_config('auth.post_login_url')` baca per-domain file `system/Config/{domain}.conf.php`. (2) `app.conf.php` dipecah jadi `auth.conf.php`, `routing.conf.php`, `modules.conf.php`, dst. (3) `config.helper.php` dihapus; semua caller `config('a.b.c')` → `get_config('a.b.c')`. (4) `Runner` tetap `get_config('helper')`. **Belum dieksekusi** — lihat PLAN C4.

### 2.4 `modules/Login/Controllers/Login.controller.php`

- ✅ **M — `include` bypass `view()`** — **RESOLVED**. `loginWithError()` sekarang pakai `view('login', ['error' => $error])`.
- ✅ **M — password tidak di-sanitize** (baris 49) — **RESOLVED**. Sekarang pakai `filter_input(INPUT_POST, \x27password\x27, FILTER_UNSAFE_RAW)`.
- ✅ **m — hardcode redirect `/dashboard`** (baris 68) — **RESOLVED** commit `eda01cd`. Sekarang redirect ke `/auth` (gateway module, `PrivateController`), lalu ke `/dashboard` bila valid. URL dari `config('auth.*')`.
- 🔵 **i — seluruh method static** — **Info**. Konsisten dengan Welcome, tapi batasan arsitektur.
- 🔵 **i — `declare(strict_types)`** — **OK** di baris 1.

### 2.5 `modules/Welcome/Controllers/{Index,Welcome}.controller.php`

- ✅ **M — `Welcome.controller.php` kemungkinan mati** — **RESOLVED** commit `eda01cd`. `/welcome` → `Welcome::index` (Router case-1: `controller = $module`). `Index.controller.php` dihapus karena redundan.
- 🟡 **m — `...$args` tak terpakai di view** — **STILL OPEN** di `home.view.php`, `door.view.php`.
- 🔵 **i — semua static** — **Info**.

### 2.6 `modules/Welcome/Views/index.view.php`

- 🟡 **m — footer link hardcode** `/documentation`, `/github`, `/changelog` (baris 172–174) — **STILL OPEN** tak ada route-nya. **PLAN.md C4: ⚠️ Open**.
- ✅ **m — font `Inter` referenced tapi tak di-enqueue** — **RESOLVED** commit `eda01cd`. `assets/css/fonts/inter.css` dibuat + `radykfw_asset()` enqueue di `Welcome::index`. (File `.woff2` masih perlu ditambahkan manual.)

### 2.7 `modules/Login/Views/login.view.php`

- ✅ **m — `$_POST['username']` di view** — **RESOLVED** (commit `eb942dd`, direvisi 2026-11-07). View bersih: 0 referensi `$_POST`. Controller pass `username` + `action_url`; view pakai `esc_attr($username ?? '')`. `error` via `$_SESSION['radykfw_login_error']` (redirect-based flow). **PLAN.md S2: ✅ Done**.
- ✅ **m — `action=""`** — **RESOLVED** (commit `eb942dd`). Baris 131: `action="<?php echo esc_url($action_url); ?>"`. **PLAN.md S3: ✅ Done**.
- 🔵 **i — nonce** sudah benar (`wp_nonce_field` + `wp_verify_nonce`). **OK**.

### 2.8 `composer.json`

- 🟠 **M — dev deps (`phpunit`, `php_codesniffer`, `phpstan`) tapi tak ada `tests/`**
  `composer test/cs/analyse` gagal.
  **Fix:** buat `tests/` minimal, atau hapus script/dev-deps sampai siap. **PLAN.md T2: ⚠️ Open**.
- 🔵 **i — `autoload.files` memuat `utilities.helper.php`** — helper global, bukan PSR-4; bekerja tapi tidak lazim.
- ✅ **i — Tidak ada runtime dependensi** — sesuai syarat "standalone". Bagus.

### 2.9 `.gitignore`

- 🟠 **M — pola `wp-content/plugins/*/vendor/` terlalu luas** (baris 52–53)
  Men-ignore `vendor/` di **semua** plugin, bukan hanya ini.
  **Fix:** sudah ada `vendor/` (baris 7) + `modules/*/vendor/` (baris 56) — hapus dua baris yang terlalu luas. **PLAN.md T3: ⚠️ Open**.
- 🟡 **m — missing:** `.supports`, `radykfw.php.old.junk`, `*.bak` (sebagian), `*.junk` (sebagian). `*.bak` & `*.junk` sebagian ada, pastikan konsisten.

### 2.10 `.supports` & `.starter` (di-lock, hanya catat)

- 🟡 **m — `is()` sebagai nama fungsi global** — `is` sangat umum di plugin lain; risiko tabrakan global.
  **Catatan:** karena di-lock, hanya direkomendasikan.
- 🔵 **i — `.starter` memakai `require` (bukan `require_once`) untuk `.supports`** — aman karena hanya di-load sekali di boot, tapi rapuh bila di-include ulang.

### 2.11 `system/Contracts/DispatchableInterface.contract.php`

- ✅ **Clean** — interface `DispatchableInterface` (sudah di-rename dari `Dispatchable`), cocok dengan `Router implements DispatchableInterface`. Nama file == nama interface. Sesuai aturan.

### 2.12 `system/Core/Registry.core.php` (di-lock)

- ✅ **Clean** — single source of truth; `ext` method memakai default `system_dir` (konstan `.starter`) — acceptable karena `.starter` memang sumber nama direktori.
- 🔵 **i — `Registry::ext()` saat ini tidak punya konsumen aktif** (hanya di `.bak`/`.junk`). Dead API, tapi di-lock.

---

## 3. Prioritas Perbaikan (jika mulai eksekusi)

### ✅ Sudah Selesai (sebelum audit, commit `736ebb5` dan sebelumnya):
1. Router C1 + C2 — validasi segmen + perbaiki fallback Index
2. `view()` extract — sudah pakai `EXTR_SKIP`
3. Runner M3 (exit redundan) — masih ada di baris 130, tapi view sudah `exit` sendiri
4. `utilities.helper` dedup — sudah dihapus
5. `.gitignore` scope — sudah update
6. Helpers cleanup (`config`, `asset`, `debug`, `utilities`) — sudah dihapus
7. Base controllers (`Public`/`Private`/`Api`) — sudah dibuat
8. `system/Views` → `system/Templates` — sudah rename

### ⚠️ Masih Perlu (Fase 1 — Cleanup & Hardening):
1. **Runner M4** — hapus `flush_rewrite_rules()` dari `uninstall()` (baris 99)
2. **Runner M1** — hapus `initRegistry()` mati (baris 41–46)
3. **Runner M2** — fix `shouldSkipRouting` path `.php` (baris 156)
4. **Runner M3** — hapus `add_action('template_redirect')` (baris 130)
5. **Runner R5** — hapus param `$extra_query_vars` (baris 114)
6. **Router strtolower** — normalisasi tanpa `strtolower` (baris 57, 65, 73)
7. **Router R7** — hapus `dispatchFallback` param `$params` (baris 220)
8. **Login password** — `filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW)` (baris 49)
9. **Welcome controller** — konfirmasi apakah mati (hapus jika ya)

### ⚠️ Masih Perlu (Fase 1b — Routing Priority & Logic):
10. **Routing priority** — tambah `routing.native` (default `true`) di `app.conf.php`. WP first, HMVC second.
11. **Alias format** — perbaiki format alias di `app.conf.php`: `Login::login`, `Auth::index` (bukan `Auth\Controllers\Custom::login`).
12. **Root path** — `Runner::getRequestPath()` jangan paksa empty path → `'home'`. `Router::handle()` `case 0` return `false` jika `routing.native = true`.
13. **WP native paths** — `Runner::handleRouting()` cek apakah WP sudah resolve query (post/page/term) sebelum HMVC claim.

### ✅ Selesai (Fase 3 — Security Polish, 2026-11-07, branch `feat/phase-3`):
1. ~~`login.view.php` — `$_POST['username']` → pass via view data~~ — RESOLVED `eb942dd` (S2)
2. ~~`login.view.php` — `action=""` → eksplisit~~ — RESOLVED `eb942dd` (S3)
3. ~~`Login.controller.php` — hardcode redirect `/dashboard`~~ — RESOLVED `eda01cd` (redirect `/auth` via `config('auth.*')`)
4. **`Auth::index()` — hapus panggilan `requireAuth()`** — dead code: selalu `exit` ke `wp_login_url()` (WP login), sehingga blok redirect `config('auth.login_url','/login')` tak pernah tercapai. Sekarang redirect `/login` (config) bila tak valid, `/dashboard` bila valid.

### 🔵 Masih Perlu (Fase 4 — Config & Assets):
1. Buat `system/Config/app.conf.php`
2. Enqueue font `Inter` atau hapus referensi
3. Hapus footer link hardcode `/documentation`, `/github`, `/changelog`

### ✅ Selesai (Fase 5 — Testing Infrastructure, 2026-11-07, branch `feat/phase-5`):
1. ~~Buat struktur `tests/` minimal + bootstrap (T1)~~ — `tests/` + `bootstrap.php` + `SmokeTest` + `SkipPathsRegressionTest` (pure-PHP), `phpunit.xml`
2. ~~Composer dev-deps tanpa tests (T2)~~ — **SUPERSEDED: `composer.json` dihapus (QA, 2026-11-07).** Plugin standalone, zero runtime dep, autoloader sendiri (tak pakai Composer). Dev-QA kini pure-PHP (test jalan via `tests/bootstrap.php`, tanpa Composer).
3. ~~`.gitignore` overly broad (T3)~~ — remove-only-52; `vendor/` (baris 7) tetap
4. ~~`shouldSkipRouting` path check (T4)~~ — logika tak diubah; edge bare-vs-`.php` regresi terkunci di `SkipPathsRegressionTest`

---

## 4. Yang Dipertahankan (sudah kelas dunia)

- Arsitektur Boot→Registry→autoloader→Runner→Router: deterministik, zero-dependency.
- `.starter` sebagai single source of truth untuk nama direktori.
- `Registry` sebagai single source of truth untuk path/namespace di runtime.
- Konvensi nama file == nama class (tanpa suffix) — konsisten.
- Helper config-driven (`helper.conf.php`) — extensible tanpa ubah kode.
- Nonce di Login view — benar.
- Standalone, tanpa dependensi runtime.

---

---

## 7. Data Layer — Phase 2 (✅ Implemented 2026-11-06, branch `feat/phase-2`)

> Catatan: Poin 7–9 dari instruksi adalah **desain/planned** — belum ada implementasi kode. Berikut rangkuman keputusan arsitektur & pertimbangan untuk implementasi nanti.
> **Update 2026-11-06 (Phase 2):** Opsi B terimplementasi — `Database.core.php` + `Model.core.php` + 2 sample model.

### 7.1 Base Model (`Model.core.php`)

**Kebutuhan:** Module models saat ini kosong (tidak ada base class). Perlu base `System\Core\Model` yang:
- Dapat di-extend oleh `Modules\{Module}\Models\{Name}.model.php`
- Menyediakan akses ke Registry/paths
- Opsional: helper CRUD sederhana (opsional karena WP sudah punya `$wpdb`)

**Desain yang disarankan:**
```php
// system/Core/Model.core.php
abstract class Model
{
    protected static function module(): string { return \System\Core\Router::currentModule(); }
    protected static function db(): \wpdb { global $wpdb; return $wpdb; }
    // Optional: find(), findAll(), create(), update(), delete() thin wrappers
}
```

### 7.2 Database Abstraction / Re-brand WP Query

**Kebutuhan:** "Rebrand semua fungsi database action milik WordPress" — wrapper di atas `$wpdb` dengan API yang lebih modern/bersih.

**Opsi A: Wrapper `$wpdb` di `Model` (minimal)**
```php
// di Model.core.php
protected function query(string $sql, array $params = []): array|false {
    return $this->db()->get_results($this->db()->prepare($sql, ...$params), ARRAY_A);
}
```

**Opsi B: `Database.core.php` terpisah (lebih eksplisit)**
File `system/Core/Database.core.php` yang:
- Wrapper penuh `$wpdb` dengan method `select`, `insert`, `update`, `delete`, `transaction`
- Query builder sederhana (opsional)
- Tanpa ORM penuh — tetap dekat ke SQL agar performa & kontrol penuh

**Rekomendasi:** **Opsi B (`Database.core.php`)** — lebih eksplisit, memisahkan concern, dan memungkinkan `Model` inject `Database` instance. Cocok dengan "re-brand all database action" karena API-nya bisa desain ulang (mis. `Database::table('posts')->where(...)->get()`).

### 7.3 ORM / Data Mapping Konsep

**Kebutuhan:** "Membawa struktur ORM / Data mapping untuk rebrand semua fungsi database action milik WordPress."

**Batasan realistis:**
- Full ORM (seperti Eloquent/Doctrine) overkill untuk plugin WP standalone.
- WP sudah punya struktur tabel tetap (`wp_posts`, `wp_postmeta`, dll) — ORM murni akan memaksa mapping kompleks.
- **Pragmatis:** "Active Record ringan" di `Model` + `Database` wrapper. Tidak butuh migration system, relationship loading, dll.

**Struktur yang disarankan:**
```
system/Core/
├── Database.core.php   # Wrapper $wpdb + query builder minimal
└── Model.core.php      # Abstract base, inject Database, helper CRUD

modules/{Module}/Models/
├── Post.model.php      # extends Model, table = 'wp_posts'
└── User.model.php      # extends Model, table = 'wp_users'
```

### 7.4 Keputusan Implementasi (Simpulan)

| Komponen | Status | Prioritas |
|---|---|---|
| `Model.core.php` | Planned (buat saat butuh model pertama) | Medium |
| `Database.core.php` | Planned (prerequisite untuk Model) | Medium-High |
| ORM full | **Tidak** — overkill | Tidak |
| Wrapper `$wpdb` di Model | Ya (via Database) | Ya |
| Migration system | Tidak (WP handle schema) | Tidak |

---

*Catatan: Semua poin di atas adalah **planned/desain** — belum ada implementasi kode. Implementasi akan dilakukan saat ada module yang butuh akses database (mis. Dashboard stats, Api posts, dll).*

> **Update 2026-11-06 (Phase 2, `feat/phase-2`):** Opsi B terimplementasi sebagai **Eloquent-lite layer** — `Database.core.php` (singleton, `select/selectOne/selectValue/count/insert/update/delete/execute/transaction` + `prefix()` auto-prefix, nilai selalu via placeholder `?` → anti-injection), `Model.core.php` (abstract `Model` + `QueryBuilder` + `Record` + `Relation`: fluent `where/orderBy/limit/offset/get/first/find/count/exists/insert/update/delete`, relations `belongsTo/hasMany/hasOne` + lazy-load `$record->author`, auto-prefix tabel logis, tanpa full-ORM/migration). Sample `Dashboard\Models\Stats` + `Api\Models\Posts` pakai gaya ORM. Query string tak lagi terlihat di module. Smoke-test 13/13 pass.

---

## 8. Security Polish — Phase 3 (✅ Implemented 2026-11-07, branch `feat/phase-3`)

**Verifikasi S1–S5 terhadap kode aktual (2026-11-07):**

| Item | Hasil verifikasi | Aksi |
|---|---|---|
| S1 password sanitize | ✅ `Login.controller.php:52` — `filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW)` (klaim "baris 49" basi) | Revisi baris di PLAN |
| S2 view tanpa `$_POST` | ✅ Sudah tereksekusi commit `eb942dd` (2026-11-05) — 0 `$_POST` di view; `error` via `$_SESSION` (redirect flow), bukan param | Revisi status PLAN Open → Done |
| S3 form action eksplisit | ✅ Sudah tereksekusi `eb942dd` — `login.view.php:131` `esc_url($action_url)` | Revisi status PLAN Open → Done |
| S4 `.gitignore` bak/junk | ✅ Baris 3–4 + 58–60; `git ls-files` = 0 file bak/junk ter-track | Tidak ada |
| S5 redirect flow `/auth` | ⚠️ **Dead code:** `Auth::index()` panggil `requireAuth()` → selalu `exit` ke `wp_login_url()` (WP login) sebelum blok `if (!is_user_logged_in())` redirect `config('auth.login_url','/login')` | **Fix: hapus `requireAuth()`** dari `Auth::index()` |

**Fix kode (satu-satunya perubahan behavior fase 3):**
- `modules/Auth/Controllers/Auth.controller.php` — `self::requireAuth()` dihapus dari `index()`. Gateway kini: tak login → `config('auth.login_url','/login')`; login → `/dashboard`. Sesuai spec S5 + PRD akseptasi #11.

**Bug audit ditemukan & dikoreksi:** klaim PLAN S2/S3 "Belum dieksekusi" salah — sudah dieksekusi 2 hari sebelum rev 12 (commit `eb942dd`); REPORT §1.1 "5 items mismatch" turun jadi 3 (C4, T2, T3).
