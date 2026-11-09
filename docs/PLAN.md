# PLAN — RadykFW Development Roadmap

> Dokumen ini merangkum prioritas perbaikan & pengembangan berdasarkan audit REPORT.md + PRD.md.
> Terkini: 2026-11-09 | Branch: `dev` (sinkron dengan `origin/dev`, rev 17)
>
> **Status aktual (verifikasi + eksekusi 2026-11-09, rev 29):** Fase 1 R1–R29 ✅. **Fase 2: Eloquent-lite layer ✅** (branch `feat/phase-2`). **Fase 3: Security Polish ✅** (branch `feat/phase-3`). **Fase 5: Testing Infrastructure ✅** (branch `feat/phase-5`, rev 14). **Fase 6 (hygiene/i18n/CI/P4)** ⚠️ Open — sebagian dieksekusi dan digabung ke `dev`. **H4.13 Modular Admin Menu Registration ✅** (PR #11): `AdminUI` menemukan config default/module, mendaftarkan menu, dan menyediakan filter WordPress; Overview menggunakan slug `radyk`. Settings UI serta H1/H2 cleanup ✅. P0-1 dan keputusan QA P0-2/P1/C2 kini tercatat pada §8.6. **Admin UI MVP complete** — Dashboard, Modules, About, Settings.
> **Catatan:** Dokumen ini hanya perencanaan — eksekusi menunggu instruksi terpisah.

---

## 1. Fase 1: Cleanup & Hardening (Prio: High)

### 1.1 Runner.core.php — Dead Code & Latent Bugs
| No | Item | Status | Detail |
|---|------|--------|--------|
| R1 | Hapus `initRegistry()` | ✅ Done | Method sudah dihapus (verifikasi 2026-11-06: tak ada di kode) |
| R2 | Update `shouldSkipRouting` filter | ✅ Done | **Terverifikasi** baris 281: `['wp-login','wp-signup','wp-activate','login','signup','activate']`. **Keputusan QA: tambahkan, bukan replace**. Commit 7415e29. |
| R3 | Hapus `add_action('template_redirect', fn(){exit;})` | ✅ Done | Tak ada `add_action('template_redirect')` — hanya menyebut di docblock `filterCanonicalRedirect` |
| R4 | Hapus `flush_rewrite_rules()` dari `uninstall()` | ✅ Done | `uninstall()` hanya `delete_transient()` ×2 |
| R5 | Hapus param `$extra_query_vars` dari `handleRouting()` | ✅ Done | Terverifikasi baris 127: `handleRouting(\WP $wp): void` |
| R6 | Escape version di `adminNotices()` | ✅ Done | Terverifikasi baris 303, 305: `Registry::get('wp_version')` |
| R22 | **Alias pattern placeholder** | ✅ Done | `Runner::tryAliases`: exact match dulu → loop pattern `{seg}` (string, `[A-Za-z0-9_-]+`) / `{seg:int}` (digits only, value tetap string). Helper `matchAliasPattern()` + `dispatchAlias()`. Args `/welcome/door/a/b` jalan sebelum semua gate. **Keputusan QA:** notasi explicit suffix, cast = validasi saja. |
| R25 | **Canonical route list dari config** | ✅ Done | `filterCanonicalRedirect()` — hardcode `$our_routes` dihapus; derive dari `config('routing.aliases')` (key statis, skip pattern `{}`). Route baru otomatis ke-cover. |
| R26 | **Native query vars dari config** | ✅ Done | `isWpNativePath()` — hardcode 18 `$nativeQueryVars` → `config('routing.native_query_vars')` di `app.conf.php`. Pola sama dengan `skip_paths`. |
| R28 | **Gate WP-first: cek konten nyata** | ✅ Done | `isWpNativePath()` (query-var, selalu true di pretty permalink) digantikan `wpHasRealContent()` — cek `\get_page_by_path()` + `\get_posts()` by name di `parse_request` (objek-cache). Opsi B: native=true = alias → cek konten WP → auto-discovery → 404; native=false = alias → auto-discovery → WP. Hook `template_redirect` (Opsi A) dibatalkan karena `get_post_by_path()` tak tersedia + status-404 re-send. |
| R27 | **Skip paths & extensions dari config** | ✅ Done | `shouldSkipRouting()` — hardcode list login-path → `config('routing.skip_paths')` (digabung ke config: WP auth/admin + infra); hardcode regex ekstensi → `pathinfo()` + `config('routing.skip_extensions')` (config itu dulu dead, tak pernah dibaca). |
| R29 | **Helper type guard** | ✅ Done (2026-11-08) | `initHooks()`: helper file yang mengandung deklarasi type (class/interface/trait/enum) tak di-`require` → skip + tercatat di `Runner::$helper_skips` + admin notice error di `admin_notices`. Deteksi: `Runner::declaredType()` (`token_get_all`, `Foo::class` dikecualikan), pola dari `miniradf Bootstrap.core:299` diperluas. **Test: (1)** `php tests/HelperTypeScanCheck.php` — 9 case pass; **(2)** `php tests/HelperTypeGuardCliTest.php` — e2e guard + notice tanpa WP runtime (sample `guard-probe.helper.php` + entri di `helper.conf.php`; hapus probe setelah verifikasi). **Gate notice**: `screenMatches()` (`get_current_screen()`) dibatasi ke `dashboard` + `plugins` saja. Test 3 case screen: dashboard/plugins = notice muncul; post = notice hilang. **Refactor notice-registry (2026-11-08):** `adminNotices()` kini notice-registry global (`Runner::$admin_notices`), API `Runner::addAdminNotice(string $message, string\|array\|null $in_page = null, string $type = 'error')` (static, callable dari modul/fungsi lain). PHP/WP version notice + helper class-guard notice register via API yang sama; tiap notice di-gate per `in_page`. |

### 1.2 Router.core.php — Dead Methods
| No | Item | Status | Detail |
|---|------|--------|--------|
| R7 | Hapus `dispatchFallback()` param `$params` | ✅ Done | Param `$params` dihapus dari `dispatchFallback()`. Method fallback ke `index` tanpa params. |
| R8 | Fix docblock `dispatchFallback` | ✅ Done | Docblock diperbaiki: "Fallback to 'index' method..." bukan "Retry the SAME method". |
| R9 | Extract `invokeMethod()` helper | ✅ Done | Extracted `invokeMethod()` helper from `tryDispatch`/`tryDispatchNoRecurse`. Eliminated ~80% code duplication. |
| R10 | Normalisasi tanpa `strtolower` | ✅ Done | `ucfirst()` tanpa `strtolower()` menjaga camelCase. `ucfirst($segment)` mempertahankan camelCase (e.g., `userProfile` → `UserProfile`). |
| R23 | Hapus Fallback1 (dead code) | ✅ Done | Fallback1 duplikat murni (case 0/1/2 selalu `method='index'` → panggilan identik dengan percobaan pertama). Dihapus + var `$isDefaultRoute` ikut dibersihkan. Zero behavior change. |
| R24 | Konsolidasi parser alias (hapus F4) | ✅ Done | Blok alias duplikat di `Router::handle` dihapus — `Runner::tryAliases` satu-satunya parser (selalu jalan sebelum gate, key dari REQUEST_URI original). `Router::handle` = murni auto-discovery. |

### 1.3 Module Controllers — Inconsistencies
| No | Item | Status | Detail |
|---|------|--------|--------|
| R11 | `Welcome.controller.php` konfirmasi mati | ✅ Done | `/welcome` → `Welcome::index` (Router case-1, `controller = $module`). `Index.controller.php` dihapus karena redundan. |
| R12 | `...$args` tak terpakai di views | ✅ Done | **Dieksekusi (2026-11-06):** `Welcome::home(string ...$args)` & `door(string ...$args)` kini teruskan `['args' => $args]` ke view — blok `!empty($args)` aktif. Variadic via alias dispatch → `[]`; contoh route auto-discovery `/welcome/welcome/door/p1` hanya tersedia jika setting site mengaktifkan auto-discovery (default `false`). |
| R13 | Footer link hardcode (`/documentation`, `/github`) | ✅ Done | `index.view.php` link di-footer menggunakan `esc_url(home_url())` dan `home_url('/documentation')`. GitHub link pakai URL absolut dengan `target="_blank"`. |
| R14 | Font `Inter` referensi tapi tak enqueue | ✅ Done | `assets/css/fonts/inter.css` dibuat + `radykfw_asset()` di-enqueue di `Welcome::index`. (File `.woff2` perlu ditambahkan manual ke `assets/css/fonts/`.) |
| R15 | Routing default simplifikasi | ✅ Done | **Dieksekusi (2026-11-06):** `routing.default` di `app.conf.php` disederhanakan jadi `'Welcome'` (module name saja). Router `case 0` kini **baca config** (`config('routing.default','Welcome')`) + `normalizeClassName`, infer `controller=module`, `method='index'`. (Dulu: array 3-key yang tak pernah dibaca.) |
| R16 | Route aliases dengan HTTP method | ✅ Done | `routing.aliases` berada di `system/Config/routing.conf.php`, format `'METHOD /path' => 'Module\Controller::method'`; `Router::tryAliases()` memeriksa aliases sebelum gate routing. Hanya aliases yang tercantum aktif ketika `modules.auto_discover=false`. |
| R17 | Namespace prefix dari `.starter` | ✅ Done | **Diperkuat (2026-11-06):** `namespace_prefix` dihapus dari config; `nsmod` = `ucfirst(modules_dir)` + trailing `\` (Registry:95). Hardcode `"Modules\\"` diganti `{$nsmod}` di **6 titik** (Router: alias explicit/shorthand, build utama, `$indexClass`; Runner: `tryAliases` ×2) — variabel `$nsmod` kini benar-benar dipakai, dispatch & autoloader satu sumber. |
| R18 | Auto-discover toggle | ✅ Done | `modules.auto_discover` berada di `system/Config/modules.conf.php`, default `false` (keputusan alias-only). Jika false, hanya aliases eksplisit yang dapat ditangani RadykFW; nilai site-scope Settings dapat mengaktifkan auto-discovery. |
| R19 | **Routing priority: WordPress first** | ✅ Done | `routing.native` (default `true`) berada di `system/Config/routing.conf.php`. Router memeriksa explicit aliases terlebih dahulu, lalu membiarkan konten WordPress yang nyata ditangani WP sebelum mencoba auto-discovery. |
| R20 | **Alias format perbaikan** | ✅ Done | Ubah alias format dari `Auth\Controllers\Custom::login` → `Login\Login::login` dan `Auth\Controllers\Auth::index` → `Auth\Auth::index`. Runner parse `Module\Controller::method` (explicit) dan `Module::method` (shorthand). |
| R21 | **Root path handling** | ✅ Done | `Runner::getRequestPath()` return empty string untuk root. `Router::handle()` `case 0` return `false` jika `routing.native = true`. |

---

## 2. Fase 2: Base Model & Database Layer — **Eloquent-lite** (Prio: Medium-High)

Sesuai PRD §8 & REPORT §7. **Di-rewrite 2026-11-06 dari wrapper $wpdb polos menjadi Eloquent-lite layer** (permintaan developer-familiarity: query string tak lagi terlihat di model, gaya ORM familiar).

**Status: ✅ Done (2026-11-06, branch `feat/phase-2`, rev 2)** — semua item terimplementasi + smoke-test 13/13 pass.

### 2.1 `system/Core/Database.core.php` (prerequisite) — ✅ Done (rewritten)
- `final class Database` singleton — wrapper `$wpdb`: `select/selectOne/selectValue/count/insert/update/delete/execute/transaction` + `prefix()` auto-prefixed via `$wpdb->prefix`
- **Semua nilai lewat placeholder `?` ke `$wpdb->prepare()`** — tak ada concat string (anti-injection)
- `insert()/update()/delete()` auto-prefix nama tabel logis (`posts` → `wp_posts`)
- Load via autoloader (`System\Core\Database`, `.core.php`)

### 2.2 `system/Core/Model.core.php` (+ QueryBuilder/Record/Relation) — ✅ Done (split, rev 3)
- `Model.core.php` (9.5KB) — abstract base + write helpers + relation factories. `require_once` 3 sibling di top-level.
- `QueryBuilder.core.php` (5.3KB) — fluent `where/orWhere/orderBy/limit/offset/select/get/first/count/exists`
- `Record.core.php` (4.6KB) — row wrapper, magic `__get` lazy relations, `ArrayAccess`, `toArray()`
- `Relation.core.php` (4.4KB) — `belongsTo/hasMany/hasOne` descriptor + lazy loader
- Semua file `.core.php` di `system/Core/` — autoloader `ext_maps` `Core → .core.php` resolve masing-masing class.

### 2.3 Sample Module Models — ✅ Done (rewritten ke gaya ORM)
- `modules/Dashboard/Models/Stats.model.php` — `$table='posts'`, `$key='ID'`; `publishedCount()` = `where('post_status','publish')->count()`, `total()`, `recent($limit)` (fluent chain)
- `modules/Api/Models/Posts.model.php` — `$table='posts'`, `$key='ID'`; `list($page,$perPage)` (fluent), `author()` = `belongsTo('users','post_author')`

---

## 3. Fase 3: Security Polish (Prio: Medium) — **✅ Done (2026-11-07, branch `feat/phase-3`)**

**Status: ✅ Done** — S1–S5 semua terverifikasi; revisi klaim basi (S2/S3 sudah tereksekusi commit `eb942dd`) + fix dead code `Auth::requireAuth()`.

| No | Item | Status | Detail |
|---|------|--------|--------|
| S1 | `Login.controller.php` password sanitize | ✅ Done | **Terverifikasi 2026-11-07:** baris **52** (klaim lama "49" basi): `$password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);`. **Keputusan: YA** (QA jawab). |
| S2 | `login.view.php` `$_POST['username']` → pass via view data | ✅ Done | **Terverifikasi 2026-11-07 (commit `eb942dd`, 2026-11-05):** view bersih — 0 referensi `$_POST` di `login.view.php`. Controller `Login::login()` (baris 20–23) pass `['username' => $_POST['username'] ?? '', 'action_url' => home_url('/login/authenticate')]`; view pakai `esc_attr($username ?? '')` (baris 136). **Koreksi detail:** `error` TIDAK via param view — mengalir via `$_SESSION['radykfw_login_error']` (set di `loginWithError()`, dibaca+di-`unset` di view baris 8–10) karena redirect-based flow. **Keputusan: YA** (QA jawab). |
| S3 | `login.view.php` action eksplisit | ✅ Done | **Terverifikasi 2026-11-07 (commit `eb942dd`):** baris 131: `<form method="post" action="<?php echo esc_url($action_url); ?>" novalidate>`. |
| S4 | `*.bak` / `*.junk` di `.gitignore` konsisten | ✅ Done | Sudah di-.gitignore (baris 3–4 `.bak`/`.junk`, baris 58–60 `*.junk`/`*.bak`/`*.old.junk`). `git ls-files` = 0 file bak/junk ter-track. |
| S5 | Login redirect flow via `/auth` | ✅ Done | `Login::authenticate` → redirect `config('auth.post_login_url','/auth')`; `Auth` module validasi session → `config('auth.login_url','/login')` bila tidak valid, `/dashboard` bila valid. `logout` → `config('auth.post_logout_url','/login')`. **Fix fase 3 (2026-11-07):** panggilan `requireAuth()` di `Auth::index()` dihapus — dead code (selalu `exit` ke `wp_login_url()` WP, sehingga blok redirect `/login` tak pernah tercapai, bertentangan dengan spec S5). |

---

## 4. Fase 4: Configuration & Assets

| No | Item | Status | Detail |
|---|------|--------|--------|
| C1 | Buat `system/Config/app.conf.php` | ✅ Done | Sudah dibuat: `app`, `modules`, `routing` (default + skip_paths + skip_extensions), `auth` (post_login_url=/auth, post_logout_url=/login, login_url=/login) |
| C2 | `config()` helper general (semua file config) | ✅ Done (digantikan C5) | **Superseded oleh C5 (2026-11-07):** `config()` helper dihapus; semua akses config kini via `get_config()` dot-notation per-domain file (`routing.conf.php`, `auth.conf.php`, dst). Lihat C5. |
| C3 | Enqueue font `Inter` (jika tetap dipakai) | ✅ Done | `assets/css/fonts/inter.css` dibuat; `radykfw_asset('radykfw-inter-font','css/fonts/inter.css')` di-enqueue di `Welcome::index`. Catatan: file `.woff2` (inter-regular/medium/semibold/bold) perlu ditambahkan manual ke `assets/css/fonts/`. |
| C4 | Footer link — route tak ada (`/documentation`, `/changelog`) | ✅ Done | **Dieksekusi 2026-11-07 (branch `feat/phase-4`):** `Welcome` module handle kedua route (modul terpisah tak dibuat — keputusan QA: `welcome`). Method `Welcome::documentation()` + `Welcome::changelog()` (view placeholder di `modules/Welcome/Views/`). Alias `GET /documentation` + `GET /changelog` → `Welcome\Welcome::*` ditambahkan di `routing.conf.php` (wajib: `auto_discover=false`, hanya alias yang resolve). |
| C5 | Konsolidasi `config()` & `get_config()` — **split config + dot-notation** | ✅ Done | **Dieksekusi 2026-11-07 (branch `feat/phase-4`, `.supports` unlock per QA):** (1) `get_config()` di `.supports` signature `mixed` return + fix cache-mutation bug (tak lagi mutasi `$cache[$key]` saat traverse dot). (2) `app.conf.php` dipecah: `routing.conf.php`, `auth.conf.php`, `modules.conf.php` (domain = nama file, **tanpa** top-level wrapper key — `get_config('routing.aliases')` → `routing.conf.php['aliases']`). `app.conf.php` kini hanya `app` block. (3) `config.helper.php` dihapus + `'config'` dihilangkan dari `helper.conf.php`. (4) Semua caller `config()` di Runner ×5, Router ×3, Auth/Login ×3 → `get_config()`. (5) Runner tetap `get_config('helper')`. Smoke-test: helper/routing.aliases/auth.post_login_url/modules.auto_discover/routing.native semua resolve benar. |

---

## 5. Fase 5: Testing Infrastructure (Prio: Low-Medium)

| No | Item | Status | Detail |
|---|------|--------|--------|
| T1 | Buat struktur `tests/` minimal | ✅ Done (rev 14, branch `feat/phase-5`) | **Scope QA: minimal + pure-PHP.** Dibuat `tests/bootstrap.php` (stub `ABSPATH`/`keyfile`/`sep`, muat `.supports` untuk `get_config`+`interlace`+`pathify`, register autoloader PSR-4 `System\Core\`→`system/Core/*.core.php`, `System\`→`system/`, `Modules\`→`modules/`). `phpunit.xml` (PHPUnit 10, bootstrap `tests/bootstrap.php`, test suite `RadykFW`). `tests/SmokeTest.php` (autoloader + config accessor `routing.native/default/skip_paths`). `tests/SkipPathsRegressionTest.php` (regresi T4). **Validasi: semua assertion pass via `php tests/bootstrap.php` runner manual (PHP 8.3.36); `php -l` clean.** Komponen phpunit `composer install` belum dijalankan (composer tak tersedia di env; dev-deps tetap di-declare). |
| T2 | Hapus dev-deps dari `composer.json` jika tests belum siap | ✅ Done (keputusan: keep-all) | **Keputusan QA: keep-all.** Dev-deps tetap (`phpunit/phpunit ^10.0`, `squizlabs/php_codesniffer ^3.7`, `phpstan/phpstan ^1.10`). `autoload-dev` `RadykFW\Tests\→tests/` kini benar (folder `tests/` eksis). phpcs/phpstan tetap berguna independen dari test; phpunit kini berfungsi. Tanpa perubahan file. |
| T3 | `.gitignore` scope `vendor/` | ✅ Done (rev 14) | Baris 52 `wp-content/plugins/*/vendor/` dihapus (over-broad — sudah di-cover `vendor/` baris 7 untuk repo ini). **Keputusan QA: remove-only-52.** Baris 53 (`wp-content/themes/*/vendor/`) + baris 56 (`modules/*/vendor/`) **tetap** (di luar scope remove-only-52). `vendor/` (baris 7) tidak berubah. |
| T4 | `Runner::shouldSkipRouting` path check | ✅ Done (rev 14, review-edge) | **Detail PLAN lama stale** (klaim "baris 156 + hardcode array" — tak sesuai). Kode aktual: `shouldSkipRouting` di baris 312, config-driven via `get_config('routing.skip_paths')` (hasil R2/R26/R27). Edge nyata: entry `wp-login` (tanpa `.php`) di `routing.conf.php` tidak exact-match `wp-login.php` (pretty-permalink). **Keputusan QA: review-edge, tanpa ubah logika.** Ditulis `tests/SkipPathsRegressionTest.php` mengunci behavior as-is: (a) bare path match itself, (b) `.php` dotted **tidak** match bare entry — mendokumentasikan edge agar keputusan strip-suffix di masa depan tak regresi diam-diam. |

---

## 6. Priority Matrix

| Priority | Fase | Estimasi Effort |
|----------|------|-----------------|
| 🔴 High | Fase 1 (Runner + Router cleanup) | ✅ Done |
| 🟠 High | Fase 2 (Database + Model core) | ✅ Done |
| 🟡 Medium | Fase 3 (Security polish) | ✅ Done |
| 🟡 Medium | Fase 4 (Config & assets) | 2 jam |
| 🔵 Low | Fase 5 (Testing infra) | ✅ Done (rev 14) |
| 🟡 Medium | Fase 7 (Drip template engine, Blade-inspired) | Open |

---

## 7. Decisions Made (Already Implemented)

| Item | Status |
|------|--------|
| Autoloader strip `Controller` only for `System\` | ✅ |
| `view()` module-only + `system_view()` for templates | ✅ |
| Rename `system/Views` → `system/Templates` | ✅ |
| Base controllers (Public/Private/Api) | ✅ |
| Helper split & cleanup | ✅ |
| Sample modules (Dashboard, Api) | ✅ |
| Router segment validation (`isValidSegment`) | ✅ |
| `view()` `extract()` → `EXTR_SKIP` | ✅ |
| `adminNotices()` escape versi | ✅ |
| Auth module (post-login `/auth` gateway) | ✅ (S5) |
| `system/Config/app.conf.php` + `config()` helper | ✅ (C1, C2) |
| Inter font asset + `radykfw_asset()` | ✅ (C3/R14, `.woff2` files pending) |
| `/welcome` → `Welcome::index`, `Index.controller` removed | ✅ (R11) |
| `config()` general (dot-notation, file param) | ✅ (C2, commit `f43334c`) |
| **Runner `shouldSkipRouting` path check** | ✅ (R2 — fixed, commit `7415e29`) |
| **Login view `$_POST['username']` & `action`** | ✅ (S2/S3 — sudah tereksekusi `eb942dd`, status PLAN direvisi 2026-11-07) |
| **Footer links dan routes** | ✅ (C4 — `/documentation` dan `/changelog` tersedia sebagai aliases; link internal memakai `home_url()`, GitHub memakai URL eksternal) |
| **`.gitignore` overly broad pattern** | ✅ (T3 — remove-only-52; baris 52 dihapus, 53/56 tetap; rev 14) |
| **Composer dev-deps without tests/** | ✅ (T2 keep-all + T1 `tests/` eksis; rev 14) |
| **Runner `shouldSkipRouting` skip_paths bare vs .php edge** | ✅ (T4 review-edge; regresi terkunci di `SkipPathsRegressionTest`, logika tak diubah; rev 14) |
| **Framework = alias router** | ✅ (QA 2026-11-06 — gate `isWpNativePath` dibiarkan; auto-discovery non-alias sekunder) |
| **Alias pattern `{seg}` / `{seg:int}`** | ✅ (R22 — exact-first, lalu pattern; value tetap string) |
| **Dead code routing: F3 + F4 dihapus** | ✅ (R23, R24 — 1 parser alias di Runner) |
| **Gaya 404** | ✅ (WP theme, status quo — tanpa perubahan) |

---

## 8. Fase 6: Hygiene, i18n & CI (Prio: Medium)

> Ditambahkan 2026-11-08 dari DRAFT.md. **Status: ⚠️ Open — hygiene/admin UI dan i18n MVP sudah dikerjakan; CI masih terbuka dan ada task lanjutan konfigurasi settings.**
> P0-1 (commit in-flight v1.0.10 infra) & P1 (aktivasi alias routes) di luar scope fase ini (menunggu QA P0-2 decision alias-vs-auto-disc).

### 8.1 Hygiene & Admin UI Shared Environment
| No | Item | Status | Detail |
|---|------|--------|--------|
| H1 | Hapus file junk untracked | ✅ Done | Tidak ada file junk/bak/old di disk (verifikasi `git ls-files --others --exclude-standard` kosong; `*.junk`/`*.bak`/`*.old` tidak ditemukan). Tidak ada yang perlu dihapus. `.gitignore` sudah cover. |
| H2 | Junk `system/Config/app.conf.php` — cek duplikasi | ✅ Done | File `app.conf.php.junk` tidak ada di disk. Tidak ada duplikasi yang perlu diverifikasi. |
| H3 | **P0-1 commit in-flight v1.0.10 infra** | ✅ Resolved | Semua file in-flight (`.supports`, `.starter`, `Boot.core.php`, `Runner.core.php`, `radykfw.php`) sudah di-commit & push ke `origin/dev` di commit `5d1621b` (2026-11-08). Tidak ada lagi item yang menahan P0-2. |
| H4 | **Service Extraction + Router Refactor** | ✅ Done (2026-11-08) | **Branch `feat/phase-6-hygiene-admin-ui`** — Extracted services from monolithic Runner into `system/Services/`: `AdminUI.service.php` (menu/submenu/asset/notice/nonce/separator registry), `Actions.service.php` (nonce/CSRF), `Pages.service.php` (Dashboard/Modules renderers), `Assets.service.php` (conditional asset enqueue). Router extended with all routing methods (`tryAliases`, `matchAliasPattern`, `dispatchAlias`, `getRequestPath`, `shouldSkipRouting`, `wpHasRealContent`, `filterCanonicalRedirect`). Runner now pure orchestrator. Static callbacks (`[Assets::class, 'enqueueAdminAssets']`). Autoloading via existing `ext_maps`. Guard test updated. Commits: `83ffbb9`, `adfd96d`, `e91d107`. |
| H5 | **RadykFW Admin UI — top-level menu + shared admin-UI contract** | ✅ Done | Top-level menu `RADYK` (slug `radyk`) + sub-menu Overview (`radyk`), Modules (`radyk-modules`), Settings (`radyk-settings`), and About (`radyk-about`) load from `system/Config/menus.conf.php` and register through `AdminUI`. Module menus use `Configs/menus.conf.php`; `AdminUIInterface` and `Settings.service.php` remain in place. Sub-menu Themes/Tools belum ada (scope MVP RadykFW saja). |
| H4.1 | **Top-level menu** `RADYK` | ✅ Done | `AdminUI` registers `RADYK` (`radyk`) from `system/Config/menus.conf.php`; its explicit Overview submenu uses the same slug. |
| H4.2 | **Sub-menu** (didaftarkan via `AdminUI::registerSubmenu`) | ✅ Done (MVP) | - **Overview** (`radyk`) — replaces WordPress's generated parent-title submenu and shares the top-level slug ✅ Done<br>- **Modules** (`radyk-modules`) — overview routing aliases, debug log (RadykFW) ✅ Done<br>- **About** (`radyk-about`) — system info, features, license ✅ Done<br>- **Settings** (`radyk-settings`) — framework settings ✅ Done<br>- **Themes** (`radyk-labs-themes`) — theme options, customizer bridge (RadykThemes) ⚠️ Open (future product)<br>- **Tools** (`radyk-labs-tools`) — debug, cache clear, health check ⚠️ Open (future product) |
| H4.3 | **Shared admin-UI contract** (PHP interface) | ✅ Done | `interface AdminUIInterface` dibuat di `system/Contracts/AdminUIInterface.contract.php` (2026-11-09). Methods: `register_menu(): array`, `render_page(string $slug): void`, `enqueue_assets(): void`. First-owner logic sudah terekspose sebagai kontrak formal. Penamaan konsisten: `Dispatchable` → `DispatchableInterface` (file `DispatchableInterface.contract.php`). |
| H4.4 | **Auto-discovery & registration logic** | ✅ Done | `AdminUI::registerMenus()` owns discovery and WordPress registration. First-owner behavior is per top-level slug; submenu contributions can still attach to a shared registered parent. |
| H4.5 | **Capability & access control** | ✅ Done | Default capability `manage_options` di `registerMenu()` dan `registerSubmenu()`. `Pages::renderDashboardPage()` / `renderModulesPage()` cek `current_user_can('manage_options')` → `wp_die('Insufficient permissions')`. |
| H4.6 | **Asset enqueueing** | ✅ Done | `Assets::enqueueAdminAssets()` + `Assets::enqueueRadykAdminAssets()` di-hook ke `admin_enqueue_scripts`. Kondisional: hanya enqueue di halaman `radyk*` (cek `get_current_screen()->id`). Shared CSS/JS via `assets.conf.php` (`admin.css`, `admin.js`). |
| H4.7 | **Settings persistence** | ✅ Done | `system/Services/Settings.service.php` dibuat dengan WP Options API. Static methods: `getSettings($namespace)`, `setSettings($namespace, $settings)`, `registerSetting($namespace, $key, $sanitize_callback)`. Namespace per produk (`radykfw_`, `radykthemes_`). Sanitization callbacks per key. Cache + lazy-load. |
| H4.8 | **Refine Overview page (Dashboard)** | ✅ Done | `Pages::renderDashboardPage()` + `system/Templates/pages/dashboard.page.php` diganti dengan konten mirip `overview.page.php` (RADYKLabs.old): greeting personal (waktu + user), quick actions cards (Settings, Modules, About), version badge, maintenance notice support. Data via `Pages::renderDashboardPage()` terpisah. |
| H4.9 | **Add About page (submenu)** | ✅ Done | Sub-menu `About` (`radyk-about`) ditambah di `menus.conf.php` + `Pages::renderAboutPage()` + `system/Templates/pages/about.page.php`. Konten adaptasi dari `about.page.php` (RADYKLabs.old): System Requirements, Features, Online Resources, Final Notes, License. i18n-ready (`__()` / `esc_html_e()`). |
| H4.10 | **menus.conf.php — default menu data** | ✅ Done | `system/Config/menus.conf.php` provides `menus`, `submenus`, and `separator`; `AdminUI` loads it and applies the `radykfw_admin_menu_config` WordPress filter after module configs are merged. |
| H4.11 | **actions.conf.php — nonce actions** | ✅ Done | `system/Config/actions.conf.php` dibuat berisi `nonce_actions` array. Dipindahkan dari `menus.conf.php` agar eksplisit dan tidak membingungkan. `Runner::initHooks()` baca config via `get_config('actions.nonce_actions')` dan register nonce actions di awal (bukan di `admin_menu` hook). |
| H4.12 | **Update Dashboard/About page content to match actual project** | ✅ Done | `system/Templates/pages/dashboard.page.php` + `about.page.php` updated with actual RadykFW data: version 1.0.9, modules (Api, Auth, Dashboard, Login, Welcome), routes from routing.conf.php, requirements PHP 8.3+/WP 6.0+, features HMVC/Service-oriented/Security-first, resources README.md + GitHub repo. |
| H4.13 | **Modular Admin Menu Registration** | ✅ Done | `AdminUI::registerHooks()` owns the late `admin_menu` hook and loads `system/Config/menus.conf.php`; `Runner` no longer registers menus. Optional `modules/{Module}/Configs/menus.conf.php` files contribute top-level menus and submenus in stable module-name order. The merged config is validated, invalid/duplicate entries are reported in admin notices, and `radykfw_admin_menu_config` provides the WordPress filter extension point. First-owner is preserved per top-level slug. Overview uses submenu slug `radyk`, so WordPress does not create its automatic duplicate parent-title link and the explicit Overview link targets the top-level page. Tests cover module discovery/ordering, filter contributions, duplicate rejection, Overview slug, and Runner ownership. |

### 8.2 i18n / Internationalization
| No | Item | Status | Detail |
|---|------|--------|--------|
| I1 | Domain text + katalog template | ✅ Done | `I18n::register()` mendaftarkan `load_plugin_textdomain('radykfw', false, dirname(plugin_basename(keyfile)).'/languages')` pada `init`; `languages/radykfw.pot` dihasilkan dari string literal domain `radykfw`. English (`en_US`) memakai string sumber. |
| I2 | 404 template i18n | ✅ Done | Fallback judul dan pesan serta tombol `Go Home`/`Go Back` memakai wrapper gettext dengan escaping HTML. |
| I3 | String wrapping di semua views | ✅ Done | Teks antarmuka user-facing pada view Welcome, Login, Dashboard, admin Dashboard/Modules/About, serta 404 memakai text domain `radykfw` dan escaping sesuai konteks. Pesan validasi login dan notice admin juga di-wrap; route/path, brand, dan markup tidak diterjemahkan. |
| I4 | Locale admin + katalog Bahasa Indonesia | ✅ Done | Locale admin mengikuti locale WordPress (termasuk preferensi bahasa admin) secara default. `app.conf.php['locale']` bernilai `null` untuk defer ke WordPress; override manual domain-only untuk admin tersedia melalui `settings.conf.php['locale']`. `languages/radykfw-id_ID.po/.mo` disediakan; frontend tetap mengikuti locale WordPress. |
| I5 | Layanan katalog gettext tanpa dependensi | ✅ Done | `TranslationCatalog` menyediakan ekstraksi literal PHP/HTML → POT, kompilasi PO → MO, dan dekompilasi MO → PO. Input/format dibatasi dan error dilaporkan eksplisit; belum ada UI atau endpoint untuk operasi file. |
| I6 | Layer konfigurasi umum + personalisasi Settings | ✅ Done | Halaman `RADYK > Settings` menyediakan allowlist bertipe untuk locale admin, `routing.native`, dan `modules.auto_discover`. Default dibaca dari file konfigurasi domain; `settings.conf.php` tetap manual/read-only (`locale` serta `overrides`), kemudian WP Options menimpa nilai site-scope dan `user_meta` keyed by user ID menimpa locale per-user. Form admin dilindungi capability + scope-specific nonce, validasi ketat; `get_config()` tetap file-only. JSON backup versioned mengekspor site settings dan preferensi user aktif yang allowlisted, tanpa secret. Belum ada nilai runtime lama yang memerlukan migrasi; penambahan setting wajib melalui schema/allowlist dan menentukan scope secara eksplisit. |

### 8.3 CI Pipeline
| No | Item | Status | Detail |
|---|------|--------|--------|
| C1 | GitHub Actions `.github/workflows/php-lint.yml` | ⚠️ Open | PHP 8.1/8.3 lint + PHPUnit pure-PHP tests (`tests/bootstrap.php`). Trigger: PR + push `dev`. Tak perlu composer — tests jalan standalone. |
| C2 | PHPStan baseline | ⚠️ Open | Tambah `phpstan.neon.dist` (level 0–1) dan jalankan standalone PHAR (keputusan QA §8.6 #5); tanpa menambahkan Composer ke repository. |
| C3 | Test coverage expansion | ⚠️ Open | Area tak tertest: `Router::handle()` (case 0/1/2/default, mock Registry+get_config), `Runner::tryAliases()` (exact/pattern/miss), `Runner::wpHasRealContent()` (mock `\get_page_by_path`, `\get_posts`), `Database`+`QueryBuilder` (mock wpdb), `Model`/`Record`/`Relation` (lazy relations), `ApiController::respond()/error()`, `Auth` gateway flow. |
| C4 | PHPCS advisory (opsional) | ⚠️ Open | `phpcs.xml` (PSR-12 atau custom). CI step advisory, non-blocking. |

### 8.4 Architecture Extensions (P4)
| No | Item | Status | Detail |
|---|------|--------|--------|
| A1 | Middleware support | ⚠️ Open | PRD §9 future enhancement. Desain: (A) route-level array di `routing.conf.php` (middleware list per alias key) atau (B) PHP 8 attribute di controller method. Buka setelah I1 (i18n) selesai agar pesan middleware sudah di-i18n. Effort 4–6 h. |
| A2 | Module registration system | ⚠️ Open | Per-module manifest di `modules.conf.php` (`namespace`, `depends_on`, `routes` flag). Skala saat >5 module. Effort 3–4 h. |
| A3 | WP-CLI commands | ⚠️ Open | `wp radykfw list-modules` / `test-routes` / `debug-route`. Developer tooling. Dependen A1. Effort 2–3 h. |
| A4 | *(dipindah ke §8.1 H4 — Admin UI shared environment RADYK Labs)* | — | Lihat **8.1 H4** untuk desain lengkap top-level menu + shared contract. |

### 8.5 Docs Drift — Sync List
| No | Doc | Stale Content | Aksi |
|---|-----|---------------|------|
| D1 | `PRD.md` §4.2 dan routing summary | Klaim default `auto_discover = true` | ✅ Diselaraskan ke keputusan QA #2: default `false`, route alias eksplisit saja; Settings dapat mengubah nilai site-scope. |
| D2 | `PRD.md` §9 | "Full ORM / Migration system / Composer" masih listed | Tandai "revisit" atau hapus (sudah decided against) |
| D3 | `README.md` | Routing examples yang ter-comment dapat dianggap sebagai routes aktif; `/auth` keliru digambarkan sebagai alias aktif | ✅ README menandai Dashboard/API/Auth sebagai route sample/nonaktif. Modul selain Welcome adalah contoh uji routing HMVC, bukan fitur aplikasi utuh yang harus diaktifkan. |
| D4 | `PLAN.md` §4 C4 | Footer routes `/documentation` + `/changelog` bergantung `auto_discover=true` | ✅ Tidak bergantung auto-discovery; keduanya terdaftar eksplisit di `routing.conf.php` dan bekerja saat auto-discovery `false`. |
| D5 | `CHANGELOG.md` `[Unreleased]` | ✅ Updated | Entri sinkronisasi routing/docs dan spesifikasi Drip ditambahkan bersama perubahan PLAN, PRD, dan README. |

### 8.6 QA Decisions (Fase 6)
| # | Pertanyaan | Keputusan / Status |
|---|-----------|--------------------|
| 1 | P0-1: Commit in-flight v1.0.10 infra as-is? | ✅ Resolved: infra in-flight dipertahankan dan di-commit (`5d1621b`). |
| 2 | P0-2: Alias-only atau re-enable auto-discovery? | ✅ Resolved: alias-only; `modules.auto_discover` default `false`. Nilai dapat diubah sebagai setting site-scope, tetapi keputusan desain default tetap alias-only. |
| 3 | P1: Dashboard/API masih sample atau siap aktivasi? | ✅ Resolved: RadykFW adalah plugin WordPress dengan fitur HMVC, bukan aplikasi utuh. Hanya Welcome yang menjadi modul halaman utama; modul lain (termasuk Login/Auth, Dashboard, dan API) adalah contoh/uji coba routing dan tidak wajib diaktifkan atau memiliki alur aplikasi lengkap. Tidak perlu mengubah redirect login sebagai bagian dari keputusan ini. |
| 4 | I1: Target bahasa pertama `.po` atau hanya `.pot` template? | ✅ Resolved: `.pot` + katalog `id_ID`; `en_US` memakai string sumber. |
| 5 | C2: Tooling dev — re-add `composer.json` atau standalone PHAR? | ✅ Resolved: standalone PHAR (tanpa Composer); saat ini menunggu implementasi C2 di CI. |
| 6 | H4: Admin UI shared environment — scope MVP? | ✅ Resolved: RADYK top-level dengan Overview/Modules/About/Settings untuk RadykFW; Themes/Tools tetap future scope. Implementasi H4.13 digabung melalui PR #11. |
| 7 | H4.3: Contract interface — di mana didefinisikan? | ✅ Resolved: kontrak terpisah di `system/Contracts/AdminUIInterface.contract.php`. |
| 8 | I6: Persistensi Settings UI dan multi-user | ✅ Resolved and implemented: configuration-file defaults, read-only manual overrides, WP Options for site scope, user-ID-keyed `user_meta` for personal locale, protected allowlisted form, and JSON data-only backup with secrets excluded. |

### 8.7 Recommended Execution Order (Fase 6)
```
Wave 6.1 (Hygiene):  H1 → H2 → H3 → H4.1–H4.10  [≤ 4 h]
Wave 6.2 (i18n):     I1 → I2 → I3 → I4 → I5 → I6 ✅
Wave 6.3 (CI):       C1 → C2 → C3 → C4         [≤ 6 h]
Wave 6.4 (P4):       A1 → A2 → A3              [depend scope decision QA #6]
```

### 8.8 Follow-up Routing
| No | Item | Status | Detail |
|---|------|--------|--------|
| R1 | **Batas scope modul dan routing** | ✅ Resolved | RadykFW membangun plugin WordPress yang menyediakan fitur HMVC, bukan aplikasi utuh. `Welcome` adalah modul halaman utama; modul selain Welcome (termasuk Login/Auth, Dashboard, dan API) dipertahankan sebagai contoh/uji coba routing. Alias `/auth` dan `/dashboard` yang tidak aktif bukan blocker dan tidak mewajibkan perubahan flow login; aktivasi atau pengembangan modul-modul tersebut memerlukan permintaan/scope terpisah. |

## 9. Fase 7: Drip Template Engine — Blade-inspired (Prio: Medium)

> **Tujuan:** Membuat `system/Services/Drip.service.php`, service template engine ringan tanpa dependency pihak ketiga, yang mengadaptasi pola utama Laravel Blade ke konvensi HMVC dan WordPress RadykFW. Ini adalah task perencanaan; belum ada service atau syntax Drip yang dianggap sudah tersedia.
>
> **Riset acuan:** [Laravel 13.x Blade documentation](https://laravel.com/docs/13.x/blade) dan [Laravel Blade compiler source](https://github.com/laravel/framework/tree/13.x/src/Illuminate/View/Compilers). Blade mengompilasi template menjadi PHP, menyimpan hasil kompilasi, lalu mengompilasi ulang saat sumber berubah. Fitur Blade mencakup escaped/raw echo, directives, subview, layout inheritance, component/slot, loop metadata, stack/once, form helpers, comments/verbatim, dan extensibility. Adaptasi dilakukan secara selektif; bukan menyalin implementasi Laravel atau membawa dependency Laravel.
>
> **Resolver yang dituju:** `view('file.drip')` memilih renderer Drip dan mencari `file.view.php` di module aktif; dot-notation sebelum `.drip` menjadi subfolder/filename (mis. `partials.card.drip` → `Views/partials/card.view.php`). `system_view('pages.dashboard.drip')` mencari `system/Templates/pages/dashboard.page.php` dan merendernya dengan Drip. Tanpa suffix `.drip`, kedua helper mempertahankan render PHP saat ini. `error_view('404')` mencari `system/Templates/errors/404.error.html` sebagai template biasa; `error_view('404.drip')` memakai sumber file yang sama dengan renderer Drip. Pemanggil cukup menulis identifier logis seperti `system_view('pages.dashboard')` atau `error_view('404')`, bukan path fisik seperti `Templates/errors/404.error.html`; resolver wajib menolak absolute path serta traversal.
>
> **Design system:** Layout bersama berada di `system/Templates/layouts/` dan components bersama di `system/Templates/components/`; template module maupun system dapat menggunakannya tanpa menduplikasi markup.

| No | Item | Status | Detail |
|---|------|--------|--------|
| DR1 | **Rancang dan buat `system/Services/Drip.service.php`** | ⚠️ Open | Implementasikan service PHP 8.3+, `strict_types`, mengikuti pola `System\Services` yang ada dan tanpa third-party dependency. Public API eksplisit: `view()` untuk module, `system_view()` untuk system, `error_view()` untuk error template; suffix identifier `.drip` memilih renderer tanpa mengubah extension fisik. Resolver: module `file.drip` → `Views/file.view.php`; system `pages.file.drip` → `Templates/pages/file.page.php`; error `file` → `Templates/errors/file.error.html`, sedangkan `file.drip` me-render sumber error yang sama melalui Drip. Gunakan dot-notation untuk subfolder, data passing konsisten, dan return/echo API yang eksplisit. Pertahankan panggilan lama tanpa suffix `.drip`; tolak absolute path/traversal dan laporkan template tak ditemukan secara eksplisit. |
| DR2 | **Compiler dan sintaks inti Blade-inspired** | ⚠️ Open | Kompilasi file template tepercaya menjadi PHP yang dapat dieksekusi native; jangan `eval()` string template dari user. MVP: `{{ ... }}` sebagai output escaped secara default, `{!! ... !!}` sebagai escape hatch eksplisit untuk konten tepercaya, escape literal `@`/interpolasi, comments dan `@verbatim`; directives control-flow (`if/elseif/else`, `unless`, `isset/empty`, `switch`, `for`, `foreach`, `forelse`, `while`, `break/continue`) serta include subview dengan data terkontrol. Parser harus menangani directive bertingkat, quoted strings, dan tanda kurung pada expression; jangan mengandalkan regex dangkal yang merusak ekspresi/nesting. |
| DR3 | **Layout, components, dan directive extension** | ⚠️ Open | Design system wajib reusable lintas module/system: layouts disimpan di `system/Templates/layouts/`, components di `system/Templates/components/`, dan keduanya dapat dipanggil dari template Drip module maupun system. Sediakan layout inheritance (`@extends`, `@section`, `@yield`, `@parent`) sebagai fitur inti; komponen/slot (`<x-...>`), stacks (`@push/@prepend/@stack`), `@once`, loop `$loop` (index/iteration/first/last/count/depth/parent), conditional attributes (`@class`, `@style`, `@checked`, `@selected`), custom directives/conditional directives, dan custom echo handlers diuji bertahap. Hindari menjanjikan kompatibilitas penuh dengan seluruh versi Blade. |
| DR4 | **Adaptasi khusus WordPress** | ⚠️ Open | Petakan kebutuhan auth/capability/forms ke API WordPress (mis. `is_user_logged_in()`, `current_user_can()`, nonce API) hanya melalui syntax/API yang ditetapkan dan terdokumentasi; jangan meniru Laravel guard, container, session, provider, Livewire, route/model binding, atau `Js::from` seolah-olah tersedia. Output nonce di template bukan pengganti verifikasi nonce dan capability di handler. |
| DR5 | **Keamanan, cache, error handling, dan test coverage** | ⚠️ Open | Output escaped harus memakai encoding HTML yang benar (`ENT_QUOTES`, `ENT_SUBSTITUTE`, UTF-8 / helper escape WP sesuai konteks); raw echo hanya untuk nilai tepercaya/sudah disanitasi (mis. `wp_kses_post` bila memang HTML post diizinkan). Jangan menganggap escaping HTML melindungi konteks JavaScript/CSS/URL. Simpan cache kompilasi di lokasi writable yang tidak dapat diakses publik/diunduh langsung (bukan source plugin), gunakan nama file ter-hash, penulisan aman, dan invalidasi saat source/compiler berubah. Jangan tampilkan source template, path absolut, atau stack trace kepada pengunjung. Tambah test untuk kompilasi/render, nested directives, escaping/raw output, include/layout/components, path traversal, missing template, cache invalidation, dan regresi helper lama serta `error_view()`. Setelah API/syntax ditetapkan, sinkronkan dokumentasi PRD/README dan CHANGELOG pada saat implementasi. |

### 9.1 Urutan implementasi
```
Wave 7.1: DR1 — tetapkan API, extension, resolver, dan batas kompatibilitas
Wave 7.2: DR2 + DR5 (cache/security minimum) — compiler MVP dan test unit
Wave 7.3: DR3 — shared layouts/components lalu tahap lanjutan stacks/directives
Wave 7.4: DR4 + integrasi view/system/error helpers, dokumentasi dan regresi end-to-end
```

### 9.2 Batas kesesuaian dengan Blade
- **Sasaran:** sintaks dan alur kerja yang familier untuk template authoring, kompilasi/cache template, komposisi view, dan keamanan default; bukan drop-in replacement untuk Laravel.
- **Bukan bagian MVP:** seluruh directive Laravel, service injection, ekosistem Livewire, dan fitur yang bergantung pada Laravel Container/HTTP/session/auth/route/model.
- **Kriteria selesai:** API dan extension terdokumentasi; fitur MVP dan security tests lulus; cache aman dari stale/partial output; view PHP yang sudah ada tetap bekerja; template invalid atau path di luar root gagal secara eksplisit.

---

*Dibuat berdasarkan audit REPORT.md dan PRD.md. Revision 29 — 2026-11-09 (verifikasi footer routes, sinkronisasi Docs Drift dan QA Decisions, serta rincian API/resolver/design system untuk Fase 7). Sebelumnya rev 28: 2026-11-09 (Phase 6 §8.2 I1–I6 ✅; Settings UI, scoped persistence, and backup implemented).*
