# PLAN — RadykFW Development Roadmap

> Dokumen ini merangkum prioritas perbaikan & pengembangan berdasarkan audit REPORT.md + PRD.md.
> Terkini: 2026-11-06 | Branch: `dev` (sinkron dengan `origin/dev`)
>
> **Status aktual (verifikasi + eksekusi 2026-11-07, rev 14):** Fase 1 R1–R24 ✅. **Fase 2: Eloquent-lite layer (Database + Model + QueryBuilder + Record + Relation) ✅ rev 3 (split 4 file Core)** (branch `feat/phase-2`, smoke-test 13/13 + 9/9 pass). **Fase 3: Security Polish ✅ (branch `feat/phase-3` — S1–S5 terverifikasi, fix dead code `Auth::requireAuth()`).** Bug fix multisite prefix + wpdb prepare + API endpoint aktif (browser-verified `/api/posts` return 4 post publish). **Fase 5: Testing Infrastructure ✅ (branch `feat/phase-5` — T1 `tests/` + bootstrap + 2 test pure-PHP, T2 keep-all dev-deps, T3 remove-only-52, T4 regresi T4 terkunci; rev 14).** Keputusan QA routing: (1) **framework = alias router** (auto-discovery non-alias non-aktif under `routing.native=true` — by design); (2) alias pattern `{seg}`/`{seg:int}` dengan exact-first; (3) F3+F4 dead code dihapus; (4) 404 = WP theme (status quo).
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
| R12 | `...$args` tak terpakai di views | ✅ Done | **Dieksekusi (2026-11-06):** `Welcome::home(string ...$args)` & `door(string ...$args)` kini teruskan `['args' => $args]` ke view — blok `!empty($args)` aktif. Variadic: alias dispatch → `[]` (block hidden), auto-discovery `/welcome/welcome/door/p1` → tampil. |
| R13 | Footer link hardcode (`/documentation`, `/github`) | ✅ Done | `index.view.php` link di-footer menggunakan `esc_url(home_url())` dan `home_url('/documentation')`. GitHub link pakai URL absolut dengan `target="_blank"`. |
| R14 | Font `Inter` referensi tapi tak enqueue | ✅ Done | `assets/css/fonts/inter.css` dibuat + `radykfw_asset()` di-enqueue di `Welcome::index`. (File `.woff2` perlu ditambahkan manual ke `assets/css/fonts/`.) |
| R15 | Routing default simplifikasi | ✅ Done | **Dieksekusi (2026-11-06):** `routing.default` di `app.conf.php` disederhanakan jadi `'Welcome'` (module name saja). Router `case 0` kini **baca config** (`config('routing.default','Welcome')`) + `normalizeClassName`, infer `controller=module`, `method='index'`. (Dulu: array 3-key yang tak pernah dibaca.) |
| R16 | Route aliases dengan HTTP method | ✅ Done | **Detail dikoreksi (2026-11-06):** `routing.aliases` di `app.conf.php` (bukan `routing.conf.php`): format aktual `'POST /login' => 'Login\Login::login'`, `'GET /auth' => 'Auth\Auth::index'`. Runner + Router cek aliases sebelum auto-discovery. |
| R17 | Namespace prefix dari `.starter` | ✅ Done | **Diperkuat (2026-11-06):** `namespace_prefix` dihapus dari config; `nsmod` = `ucfirst(modules_dir)` + trailing `\` (Registry:95). Hardcode `"Modules\\"` diganti `{$nsmod}` di **6 titik** (Router: alias explicit/shorthand, build utama, `$indexClass`; Runner: `tryAliases` ×2) — variabel `$nsmod` kini benar-benar dipakai, dispatch & autoloader satu sumber. |
| R18 | Auto-discover toggle | ✅ Done | **Detail dikoreksi (2026-11-06):** `modules.auto_discover` di `app.conf.php` (bukan `modules.conf.php` — file itu tak ada, split config C5 belum jalan), default `true`. Jika `false`: hanya aliases yang match, sisanya fall-through ke WP. |
| R19 | **Routing priority: WordPress first** | ✅ Done | Tambah `routing.native` (default `true`) di `app.conf.php`. Root `/` dan path WP native (post/page/term) ditangani WP dulu. HMVC hanya claim path yang exact match module/controller. |
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
| **Footer hardcode links** | ⚠️ Open (C4 — kode sudah `home_url()`; route tak ada) |
| **`.gitignore` overly broad pattern** | ✅ (T3 — remove-only-52; baris 52 dihapus, 53/56 tetap; rev 14) |
| **Composer dev-deps without tests/** | ✅ (T2 keep-all + T1 `tests/` eksis; rev 14) |
| **Runner `shouldSkipRouting` skip_paths bare vs .php edge** | ✅ (T4 review-edge; regresi terkunci di `SkipPathsRegressionTest`, logika tak diubah; rev 14) |
| **Framework = alias router** | ✅ (QA 2026-11-06 — gate `isWpNativePath` dibiarkan; auto-discovery non-alias sekunder) |
| **Alias pattern `{seg}` / `{seg:int}`** | ✅ (R22 — exact-first, lalu pattern; value tetap string) |
| **Dead code routing: F3 + F4 dihapus** | ✅ (R23, R24 — 1 parser alias di Runner) |
| **Gaya 404** | ✅ (WP theme, status quo — tanpa perubahan) |

---

*Dibuat berdasarkan audit REPORT.md dan PRD.md. Revision 14 — 2026-11-07 (Fase 5 complete: T1 minimal pure-PHP tests + bootstrap + 2 test, T2 keep-all dev-deps, T3 remove-only-52 gitignore, T4 regresi terkunci tanpa ubah logika; branch `feat/phase-5`). Sebelumnya rev 13: Fase 3 complete: S1–S5 verifikasi + fix dead code `Auth::requireAuth()` redirect; klaim S2/S3 direvisi → Done (sudah tereksekusi `eb942dd`); branch `feat/phase-3`).*
