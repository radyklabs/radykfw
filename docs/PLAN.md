# PLAN — RadykFW Development Roadmap

> Dokumen ini merangkum prioritas perbaikan & pengembangan berdasarkan audit REPORT.md + PRD.md.
> Terkini: 2026-11-06 | Branch: `dev` (sinkron dengan `origin/dev`)
>
> **Status aktual (verifikasi + eksekusi 2026-11-06, rev 10):** Fase 1 R1–R24 ✅. **Fase 2: Eloquent-lite layer (Database + Model + QueryBuilder + Record + Relation) ✅ rev 2** (branch `feat/phase-2`, smoke-test 13/13 pass). Keputusan QA routing: (1) **framework = alias router** (auto-discovery non-alias non-aktif under `routing.native=true` — by design); (2) alias pattern `{seg}`/`{seg:int}` dengan exact-first; (3) F3+F4 dead code dihapus; (4) 404 = WP theme (status quo).
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

### 2.2 `system/Core/Model.core.php` — ✅ Done (rewritten, Eloquent-lite)
- `abstract class Model` + `QueryBuilder` + `Record` + `Relation` (4 class dalam 1 file, autoloader load `Model`)
- `Model` entry: `query()`, `where()`, `orderBy()`, `limit()`, `offset()`, `first()`, `get()`, `find()`, `count()`, `exists()`, `insert()`, `update()`, `delete()`
- **Fluent builder**: `Model::where(...)->orderBy(...)->limit(...)->get()` — query string tak terlihat di module
- **Relations (Eloquent-lite)**: `belongsTo()`, `hasMany()`, `hasOne()` + lazy-load via `$record->author`
- `$table` nama logis (auto-prefix), `$key` primary-key
- Tanpa full-ORM: tak ada migration, eager-load, casts, events — pragmatis di atas skema WP tetap

### 2.3 Sample Module Models — ✅ Done (rewritten ke gaya ORM)
- `modules/Dashboard/Models/Stats.model.php` — `$table='posts'`, `$key='ID'`; `publishedCount()` = `where('post_status','publish')->count()`, `total()`, `recent($limit)` (fluent chain)
- `modules/Api/Models/Posts.model.php` — `$table='posts'`, `$key='ID'`; `list($page,$perPage)` (fluent), `author()` = `belongsTo('users','post_author')`

---

## 3. Fase 3: Security Polish (Prio: Medium)

| No | Item | Status | Detail |
|---|------|--------|--------|
| S1 | `Login.controller.php` password sanitize | ✅ Done | Baris 49: ganti `$_POST['password'] ?? ''` → `filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW)`. **Keputusan: YA** (QA jawab). |
| S2 | `login.view.php` `$_POST['username']` → pass via view data | ⚠️ Open | Baris 132: controller pass `['error'=>$error, 'username'=>$username, 'action_url'=>home_url('/login/authenticate')]`. View ganti `$_POST['username']` → `$username`, `action=""` → `action="<?php echo esc_url($action_url); ?>"`. **Keputusan: YA** (QA jawab). **Belum dieksekusi**. |
| S3 | `login.view.php` action eksplisit | ⚠️ Open | Digabung ke S2 — `action` dari view data, bukan `""`. **Belum dieksekusi**. |
| S4 | `*.bak` / `*.junk` di `.gitignore` konsisten | ✅ Done | Sudah di-.gitignore |
| S5 | Login redirect flow via `/auth` | ✅ Done | `Login::authenticate` → redirect `config('auth.post_login_url','/auth')`; `Auth` module (`PrivateController`) validasi session → `/dashboard` bila valid, `/login` bila tidak. `logout` → `config('auth.post_logout_url','/login')`. |

---

## 4. Fase 4: Configuration & Assets

| No | Item | Status | Detail |
|---|------|--------|--------|
| C1 | Buat `system/Config/app.conf.php` | ✅ Done | Sudah dibuat: `app`, `modules`, `routing` (default + skip_paths + skip_extensions), `auth` (post_login_url=/auth, post_logout_url=/login, login_url=/login) |
| C2 | `config()` helper general (semua file config) | ✅ Done | `config($key, $default, $file='app')` — akses dot-notation ke **semua** `system/Config/*.conf.php`, bukan hanya `app`. Default file `app.conf.php`. Terdaftar di `helper.conf.php`. |
| C3 | Enqueue font `Inter` (jika tetap dipakai) | ✅ Done | `assets/css/fonts/inter.css` dibuat; `radykfw_asset('radykfw-inter-font','fonts/inter.css')` di-enqueue di `Welcome::index`. Catatan: file `.woff2` (inter-regular/medium/semibold/bold) perlu ditambahkan manual ke `assets/css/fonts/`. |
| C4 | Footer link — route tak ada (`/documentation`, `/changelog`) | ⚠️ Open | **Koreksi (2026-11-06):** Link SUDAH pakai `esc_url(home_url(...))` (sesuai R13 — klaim "masih hardcode" basi). Sisa issue: route `/documentation` & `/changelog` tidak ada → jatuh ke WP 404. Perbu: buat route/modul, atau ganti href. |
| C5 | Konsolidasi `config()` & `get_config()` — **split config + dot-notation** | ⚠️ Open | **Keputusan QA:** (1) `get_config()` (di `.supports`) di-general: `get_config('auth.post_login_url')` → baca **per-domain file** `system/Config/{domain}.conf.php` via dot-notation. (2) `app.conf.php` dipecah jadi beberapa file per domain: `app.conf.php`, `routing.conf.php`, `auth.conf.php`, `modules.conf.php`, dst. (3) `config.helper.php` dihapus — semua caller `config('auth.post_login_url')` diganti `get_config('auth.post_login_url')`. (4) Caller `Runner` tetap `get_config('helper')` (infra). (5) Autoloader tak terpengaruh — config bukan class. **Belum dieksekusi.** |

---

## 5. Fase 5: Testing Infrastructure (Prio: Low-Medium)

| No | Item | Status | Detail |
|---|------|--------|--------|
| T1 | Buat struktur `tests/` minimal | ⚠️ Open | PHPUnit + bootstrap |
| T2 | Hapus dev-deps dari `composer.json` jika tests belum siap | ⚠️ Open | Atau implementasikan |
| T3 | `.gitignore` scope `vendor/` | ⚠️ Open | Hapus `wp-content/plugins/*/vendor/` (terlalu luas, baris 52) |
| T4 | `Runner::shouldSkipRouting` path check | ⚠️ Open | Baris 156: cek `['wp-login','wp-signup','wp-activate']` tanpa `.php` — path ini tidak pernah match format request WP. Perlu review logika. |

---

## 6. Priority Matrix

| Priority | Fase | Estimasi Effort |
|----------|------|-----------------|
| 🔴 High | Fase 1 (Runner + Router cleanup) | ✅ Done |
| 🟠 High | Fase 2 (Database + Model core) | ✅ Done |
| 🟡 Medium | Fase 3 (Security polish) | 1 jam |
| 🟡 Medium | Fase 4 (Config & assets) | 2 jam |
| 🔵 Low | Fase 5 (Testing infra) | 2–3 jam |

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
| **Login view `$_POST['username']` & `action`** | ⚠️ Open (S2/S3 - not executed) |
| **Footer hardcode links** | ⚠️ Open (C4 — kode sudah `home_url()`; route tak ada) |
| **`.gitignore` overly broad pattern** | ⚠️ Open (T3 - line 52) |
| **Composer dev-deps without tests/** | ⚠️ Open (T2 - not executed) |
| **Framework = alias router** | ✅ (QA 2026-11-06 — gate `isWpNativePath` dibiarkan; auto-discovery non-alias sekunder) |
| **Alias pattern `{seg}` / `{seg:int}`** | ✅ (R22 — exact-first, lalu pattern; value tetap string) |
| **Dead code routing: F3 + F4 dihapus** | ✅ (R23, R24 — 1 parser alias di Runner) |
| **Gaya 404** | ✅ (WP theme, status quo — tanpa perubahan) |

---

*Dibuat berdasarkan audit REPORT.md dan PRD.md. Revision 10 — 2026-11-06 (Fase 2 rewrite Eloquent-lite: Database wrapper + Model + QueryBuilder + Record + Relation, auto-prefix, smoke-test 13/13 pass).*
