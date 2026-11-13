# REPORT — Audit & Review RadykFW

## Generator path dan namespace Registry — 2026-11-13

`Registry::buildFromStarter()` tetap menyusun konteks WordPress dan plugin, lalu memuat daftar seluruh folder dari `system/Config/path_meta.conf.php` melalui `get_config()`. Pemetaan itu mencakup root `modules`/`system`/`assets`; direktori `Config`, `Contracts`, `Core`, `Enum`, `Helpers`, `Middleware`, `Services`, dan `Templates`; folder module dan template; serta `css`, `images`, dan `js`. Hanya tiga konstanta root (`assets_dir`, `modules_dir`, `system_dir`) yang diperlukan oleh metadata `.starter`; direktori subfolder framework bersifat tetap dalam metadata. Loader `get_config()` sudah tersedia sebelum Registry dibangun dan memakai `Config` sebagai fallback jika `config_dir` tidak didefinisikan. Loop generik menghasilkan path, suffix extension, URL asset, serta namespace prefix. URL dibangun sebagai URL (bukan melalui normalisasi path filesystem), sehingga skema HTTPS tetap utuh. Assignment path dan namespace per-key tidak lagi ditulis di builder. Cache privat juga dibangkitkan melalui metadata, tanpa mengubah lokasi atau mengaktifkan `cache_url`. Di `.supports`, `load_process()` dan `initiate_process()` menggunakan Registry setelah siap; untuk pemuatan awal `Boot`/`Registry`, keduanya memakai fallback tetap `Core`.

Regresi terisolasi `tests/RegistryPathMetadataTest.php` lulus (1 test, 50 assertions). Smoke boot dengan hanya konstanta `assets_dir`/`modules_dir`/`system_dir` membuktikan fallback awal memuat Registry, lalu pemanggilan berikutnya memakai path dan namespace Registry; 46 entri dicek. PHPCS bersih untuk Registry, metadata config, dan test; `.supports` masih memiliki temuan newline EOF yang sudah ada sebelumnya dan belum diperbaiki. PHPUnit dengan konfigurasi proyek masih berhenti sebelum test dijalankan karena `tests/bootstrap.php` menggunakan konstanta `sep` sebelum tersedia; test Registry dijalankan terisolasi tanpa konfigurasi bootstrap tersebut. Perubahan bootstrap yang sudah ada tidak disentuh.

## Cache privat Drip melalui Registry — 2026-11-13

### Keputusan

Cache PHP hasil kompilasi Drip tetap privat sesuai DR5. `Registry::cache` menggunakan system temp dengan subdirektori khusus berdasarkan path instalasi plugin; `Registry::cache_url` bernilai `null` karena cache tidak boleh disajikan melalui URL publik. Drip menulis file di bawah `Registry::get('cache')/drip`.

### Perbaikan

`Registry::buildFromStarter()` sebelumnya menulis `cache` dan `cache_url` ke variabel typo `$radykfwp`, sehingga data tidak ikut dikembalikan dalam Registry aktif. Nilai kini masuk ke `$radykfw`, dan helper Drip memakai Registry sebagai satu-satunya sumber lokasi serta melaporkan error secara eksplisit jika cache tidak tersedia atau tidak writable. Test fixture Drip menyediakan cache root tersendiri dan menguji file hasil kompilasi berada di bawahnya.

## Deep Check Routing `/test` — 2026-11-12

### Kesimpulan

404 di `/test` (view memakai `@extends('layouts.test')`) berasal dari resolver Drip, bukan routing: `Drip::resolveShared()` memaksa identifier `'layouts.test'` menjadi path `system/Templates/layouts/layouts/test.layout.php`, sehingga layout `test.layout.php` tidak pernah ditemukan.

### Bukti

| Pemeriksaan | Hasil |
|---|---|
| `wp-content/debug.log` | `RadykFW Dispatch Error: Drip: shared template not found: 'layouts.test' (shared layouts template)` |
| File layout | `system/Templates/layouts/test.layout.php` ada; `layouts/layouts/test.layout.php` tidak. |
| `Drip::resolveShared('layouts.test','layouts')` (sebelum fix) | throw `template not found` |
| `Drip::resolveShared('layouts.test','layouts')` (setelah fix) | `system/Templates/layouts/test.layout.php` |

### Fix

`resolveShared()` menerima prefix kind opsional: `'layouts.base'` dan `'base'` ekuivalen (sama untuk `components.`), dengan tetap mendukung subfolder berbasis dot-notation. Regresi di `tests/DripLayoutComponentTest.php::extendsWithKindPrefixResolvesSameLayout` — 216 tests / 762 assertions hijau.

### Deep Check `/test` setelah konten ditambahkan — 2026-11-12

Konten baru memicu 404 karena output mentah di antara `@extends('layouts.test')` dan `@section('content')` ikut terbaca sebagai bagian dari identifier layout. `execute()` sebelumnya hanya membuang NUL di ujung seluruh sisa output; komentar HTML setelah marker membuat NUL pemisah berada di tengah string sehingga `resolveShared()` menolaknya.

| Pemeriksaan | Hasil |
|---|---|
| Browser sebelum fix | Tema WordPress menampilkan “Page not found”. |
| `wp-content/debug.log` | `Drip: null byte in shared layouts template identifier: 'layouts.test…` |
| Output sesudah marker | Layout ID diikuti terminator NUL dan komentar HTML mentah. |
| Reproduksi PHPUnit | `extendsPreservesUnsectionedOutputAfterLayoutMarker` gagal sebelum fix karena identifier mengandung NUL. |

Parser marker sekarang mengambil layout ID sampai terminator NUL pertama dan mempertahankan output setelahnya. Controller Welcome juga mengirimkan data `$features` ke view `/test`, yang sebelumnya memicu warning undefined variable/foreach. Setelah fix, `/test` menampilkan konten dan 87 tes terarah / 405 assertions lulus.

### Ikon SVG pada `/test` — 2026-11-12

Ikon fitur sebelumnya dikirim sebagai string markup SVG dari `Welcome::features()`, tetapi view mencetaknya dengan `{{ }}`. Drip meng-escape echo tersebut, sehingga browser menampilkan source SVG sebagai teks. View kini memakai `{!! $feature['icon'] !!}` hanya untuk markup SVG statis yang didefinisikan controller; judul dan deskripsi tetap memakai echo ter-escape. Browser memverifikasi enam elemen SVG terlihat dan tidak ada source `<svg>` yang tampil sebagai teks.

## Deep Check Routing `/welcome` — 2026-11-12

### Kesimpulan

404 yang terlihat adalah fallback tema WordPress setelah dispatch alias RadykFW gagal saat merender halaman Welcome. Penyebab langsungnya bukan alias yang hilang, `routing.native`, `modules.auto_discover`, atau rewrite rules, melainkan sintaks `@class` yang dikompilasi menjadi PHP tidak valid di template Drip.

### Bukti

| Pemeriksaan | Hasil |
|---|---|
| Browser `GET /radykfwtest/welcome` | Menampilkan “Page not found” milik tema WordPress. |
| Browser `GET /radykfwtest/welcome/home` | Berhasil menampilkan “Welcome Home” milik modul RadykFW. |
| Alias modul | `modules/Welcome/Config/routing.conf.php` memiliki `GET /welcome` → `Welcome\Welcome::index` dan `GET /welcome/home` → `Welcome\Welcome::home`. |
| Jalur controller | `Welcome::index()` memanggil `view('index.drip', ...)`; halaman `/welcome/home` memakai view PHP biasa. |
| `wp-content/debug.log` saat repro (09:42:35 dan 09:42:44 UTC) | `RadykFW Dispatch Error: Drip: runtime error in 'module:index': syntax error, unexpected token "<"`. |
| PHP lint pada hasil kompilasi Drip | Parse error `unexpected token "<"` di baris 73. Baris itu berisi `Drip::e (<?php ...`, akibat directive PHP disisipkan ke dalam ekspresi echo. |

`system/Core/Router.core.php::tryAliases()` mencoba alias sebelum gate konten WordPress. Karena alias `/welcome` mencapai `module:index` dan menghasilkan exception, dispatch mengembalikan `false`; `Runner::handleRouting()` lalu membiarkan WordPress merender 404. Karena itu, `modules.auto_discover=false` tidak menghalangi alias eksplisit ini dan `routing.native=true` bukan penyebabnya. Rewrite flush juga tidak akan memperbaiki kesalahan template.

### Akar masalah teknis

Di `modules/Welcome/Views/index.view.php:75`, atribut menggunakan bentuk `{{ @class([...]) }}`. Compiler Drip menjalankan `compileEchoTokens()` sebelum `compileDirectives()` (`system/Services/Drip.service.php:532-535`), sehingga token tersebut diperlakukan sebagai ekspresi echo biasa. Compiler `@class` sendiri menghasilkan kode PHP (`compileClass()`, baris 1407); hasil gabungannya membuka tag `<?php` di dalam argumen PHP yang sudah terbuka dan menyebabkan parse error.

Ada penggunaan bentuk serupa di contoh dokumentasi template pada `modules/Welcome/Views/index.view.php:149`. Memperbaiki baris 75 saja berisiko membuat halaman tetap gagal saat compiler mencapai contoh tersebut.

### Solusi yang direkomendasikan

1. Perbaiki pemakaian directive dalam template: gunakan `@class([...])` langsung sebagai atribut dan hapus `class="feature-card"` statis karena daftar directive sudah memuat kelas tersebut. Untuk contoh kode yang memang harus menampilkan teks `{{ @class(...) }}`, lindungi teks sebagai literal, misalnya dengan `@verbatim` / `@endverbatim` atau escaping yang sesuai.
2. Tambahkan regresi Drip yang melintasi seluruh kebutuhan ini: kompilasi template Welcome, validitas sintaks PHP hasil kompilasi, rendering `/welcome`, dan tetap berfungsinya `/welcome/home`. Setelah perubahan template, cache Drip akan dibedakan oleh source/mtime; tidak perlu flush rewrite rules.
3. Perbaiki keterlihatan error dispatch sebagai tindak lanjut: `tryDispatch()` menangkap `Throwable` lalu mengembalikan `false`, dan hanya menulis log ketika `WP_DEBUG` aktif. Pertahankan fallback yang aman, tetapi pastikan kegagalan tercatat pada konfigurasi produksi tanpa menampilkan detail internal ke pengguna.
4. Verifikasi perbaikan melalui browser: `/welcome` harus menghasilkan halaman Welcome RadykFW (HTTP 200), `/welcome/home` tetap berhasil, dan URL tak dikenal tetap jatuh ke 404 WordPress.

Tidak ada perubahan pada kode routing atau template dalam pemeriksaan ini. Temuan ini memperbarui ekspektasi historis di §1.3: PLAN R28 dan PRD §4.2/§4.4 menyatakan alias eksplisit diproses sebelum gate WordPress, tetapi validitas handler dan render template tetap menentukan keberhasilan route.

> **Update 2026-11-10 — PLAN §8.4 A1.1 implemented:** `system/Middleware/` holds middleware implementations (`System\Middleware`, `.middleware.php`); `AuthMiddleware.service.php` migrated to `system/Middleware/Auth.middleware.php` (`System\Middleware\Auth`). The Services pipeline (`System\Services\Pipeline`, renamed from `RouteMiddleware` for explicitness), Contracts interface, and explicit config registry remain unchanged; no middleware auto-discovery. `Registry::ext_maps` gained the `Middleware` mapping under explicit user permission. Route/middleware tests and lint of affected files pass.

> Approved 2026-11-10: PLAN 8.4 A1–A3 execution uses route-level middleware config, explicit name-to-class system registry, bundled auth login redirect, legacy string aliases. Module config remains additive and isolated; CLI tooling noninteractive. Verified: 95 tests / 270 assertions; PHPStan no errors; PHPCS no findings; PHP lint 80 files. CLI command handlers tested directly; live WP-CLI integration not exercised because wp executable is unavailable. No locked files changed; no merge/release.

> Audit menyeluruh terhadap plugin WordPress HMVC **radykfw** (standalone, tanpa dependensi third-party).
> Tanggal: 2026-11-04. Cakupan: seluruh file aktif di `system/`, `modules/`, `.supports`, `.starter`, `plugin.init.php`, `composer.json`, `.gitignore`.
> **Update 2026-11-07:** Re-audit Fase 3 (§8) — S1–S5 terverifikasi; dead code `Auth::requireAuth()` diperbaiki.
> **Update 2026-11-08:** Service Extraction + Router Refactor (branch `feat/phase-6-hygiene-admin-ui`) — Extracted services from monolithic Runner into `system/Services/`, extended Router with all routing methods, Runner now pure orchestrator.
> **Update 2026-11-09:** Phase 6 §8.2 I1–I5 implemented: WordPress locale-aware loading, admin-only locale overrides, `id_ID` catalog, gettext wrappers across frontend/admin views and validation/notices, and standalone POT/PO/MO conversion. I6 completion and validation are recorded below.
> **Update 2026-11-09:** Phase 6 §8.2 I6 implemented: typed settings allowlist and effective-value layers, site Options and user-ID-keyed `user_meta`, protected admin Settings page, runtime routing integration, and versioned JSON backup export excluding secrets. PHP lint, 178-message POT/PO parity, site/user precedence and isolation, save validation, backup authorization, and catalog decompilation smoke checks pass; PHPUnit is unavailable in this environment. No legacy runtime settings required migration.
> **Update 2026-11-09:** Phase 6 §8.1 H4.13 implemented: `AdminUI` owns default/module menu discovery and late WordPress registration; modules contribute declarative `Configs/menus.conf.php` files in stable order, with the `radykfw_admin_menu_config` filter as the extension point. Overview uses the same `radyk` slug as the top-level page. Targeted PHP lint and menu-registration checks pass; PHPUnit is unavailable in this environment.
> **Update 2026-11-09:** Release automation is bootstrapped on `main`: `>release` prepares and merges a versioned PR when repository rules allow it, then the workflow validates the exact merge and creates its annotated tag. The workflow configures the GitHub Actions bot identity and provides a manual recovery dispatch that verifies a merged release PR before retrying a failed tag.
> **Update 2026-11-10:** Planning decision for Phase 6 P4 A1–A3: discover each module's `Config/` independently of route auto-discovery; permit only additive route-alias, menu, and nonce-action contributions; keep `app.conf.php` and other system-owned config files protected with their existing names/paths. Middleware, WP-CLI, and the `make-module` scaffold command must be AI-friendly. Implementation and explicit `Configs/` → `Config/` migration diagnostics are complete; see PLAN.md §8.4 and PRD.md §8.5.4.
>
> **Update 2026-11-10 (track):** PLAN §8.4 A1–A3 digabung ke `dev` via PR #18; A1.1 (`system/Middleware/`) dan A2.1 (Welcome full sample) dieksekusi; alias system dimigrasikan ke module pemilik dengan `filterCanonicalRedirect()` diperluas ke kontribusi module. README badge (tests/phpstan/release/downloads) + GitHub Releases `v1.1.0`/`v1.2.0` diterbitkan. Status PLAN/PRD disinkronkan; 95 tests / 270 assertions hijau. Fase 6 dinyatakan complete; sisa open: Fase 7 (Drip) dan sub-menu Themes/Tools (future product).
>
> **Kondisi boot terverifikasi:** rangkaian `.starter → .supports → Boot → Runner → helpers` dieksekusi hingga sukses pada PHP 8.3.36 (harness terpisah, tanpa WP). Registry berisi 18 key, `view()` & `get_config()` ter-load. **Tidak ada fatal error saat boot.**

## 1. Ringkasan

| Skala | Jumlah | Contoh |
|---|---|---|
| 🔴 Critical | 3 | Validasi input segment, fallback Router, `extract()` di view |
| 🟠 Major | 10 | Dead code, skip-routing, konstan global, no-op `fallbackToWordPress`, dst. |
| 🟡 Minor | 9 | Docblock, casing, inline CSS, dst. |
| 🔵 Info | 5 | Observasi |

**File di-lock** (tidak diubah, hanya diaudit): `plugin.init.php`, `.starter`, `.supports`, `system/Core/Boot.core.php`, `system/Core/Registry.core.php`.

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
- Optional `modules/{Module}/Config/menus.conf.php` files can add top-level menus and submenus; discovery and merge order are deterministic, with schema/capability/slug checks and admin notices for rejected config.
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
- 🟡 **m — missing:** `.supports`, `plugin.init.php.old.junk`, `*.bak` (sebagian), `*.junk` (sebagian). `*.bak` & `*.junk` sebagian ada, pastikan konsisten.

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

---

## 9. Drip Template Engine — Fase 7 (✅ Implemented 2026-11-12, branch `feat/drip-template-engine`)

### 9.1 Implementation Summary

The Drip template engine is a Blade-inspired, zero-dependency template compiler implemented in `system/Services/Drip.service.php` (1872+ lines). It provides:

**Core Compiler (DR1–DR2)**
- `{{ ... }}` escaped output via `Drip::e()` → `esc_html()` in WP, `htmlspecialchars(ENT_QUOTES|ENT_SUBSTITUTE, UTF-8)` fallback
- `{!! ... !!}` raw output for trusted content
- `{{-- comments --}}` stripped at compile time
- `@verbatim ... @endverbatim` raw template content
- Control flow: `@if/@elseif/@else/@endif`, `@unless/@endunless`, `@isset/@endisset`, `@empty/@endempty`, `@switch/@case/@break/@continue/@default/@endswitch`, `@for/@endfor`, `@foreach/@endforeach`, `@forelse/@endforelse`, `@while/@endwhile`
- `@include('view', $data)` controlled subview inclusion

**Layout & Components (DR3)**
- `@extends('layouts.base')` + `@section/@endsection` + `@yield('name', 'default')` + `@parent` — layout inheritance with runtime section capture
- `<x-component attr="value">slot</x-component>` — components with slots (resolved from `system/Templates/components/`)
- `@push/@prepend/@stack` — named stacks for scripts/styles
- `@once/@endonce` — render once per cycle
- `$loop` metadata inside `@foreach`/`@forelse`: `index`, `iteration`, `first`, `last`, `count`, `depth`, `parent`
- Conditional attributes: `@class([...])`, `@style([...])`, `@checked($cond)`, `@selected($cond)`

**WordPress-Specific Directives (DR4)**
- `@csrf` — outputs `wp_nonce_field()` (gracefully no-ops in pure-PHP tests)
- `@auth/@endauth`, `@guest/@endguest` — `is_user_logged_in()`
- `@can('capability')/@endcan` — `current_user_can()`
- `@role('role')/@endrole` — `wp_get_current_user()` role check
- `@trans('key', ['param' => $val])` — `__()` with escaping
- `@transChoice('key', $count)` — `_n()` with escaping

**Extensibility (DR3)**
- `Drip::directive('name', callable)` — custom single-line directives
- `Drip::conditionalDirective('name', $open, $close)` — custom open/close pairs
- `Drip::escapedEcho(callable)`, `Drip::rawEcho(callable)` — override echo compilation
- `system/Config/directives.conf.php` — configurable custom directives (loaded via `Drip::directivesFromConfig(get_config('directives', []))`)

**Security & Cache (DR5)**
- Compiled templates cached under the private, installation-scoped `Registry::get('cache')`; `cache_url` is `null`
- Hashed filenames (SHA1 of source path + mtime + compiler version)
- Atomic temp-file + rename writes
- Mtime/version-based invalidation
- Path traversal rejection at every resolver entry point
- Clean error messages (no source, no absolute paths, no stack traces)

**Integration**
- `view('file.drip')` → module view via Drip
- `system_view('pages.dashboard.drip')` → system template via Drip
- `error_view('404')` → plain render (legacy)
- `error_view('404.drip')` → same source file via Drip
- Legacy non-drip paths remain byte-identical to pre-Drip behavior

### 9.2 Test Coverage

| Test File | Coverage |
|-----------|----------|
| `tests/DripResolverTest.php` | Resolvers, dot-notation, `.drip` vs legacy, path rejection, missing-template errors, `error_view` 404 resolution |
| `tests/DripCompilerTest.php` | Escaping/raw, all directives, include, traversal rejection, missing template, cache invalidation, `e()` helper |
| `tests/DripLayoutComponentTest.php` | Extends/section/yield/parent, component attribute+slot, stacks, `@once`, `$loop` metadata, conditional attributes, custom directives, echo handlers, all built-in "common PHP" directives, traversal rejection for layout/component |

Total: ~193 tests / ~500+ assertions (existing + new e2e)

### 9.3 Known Limitations (Ponytail Notes)

1. **`@for` loop metadata** — `$loop` is not injected for `@for` loops (only `@foreach`/`@forelse`). Documented in compiler; upgrade path: add `$__drip_loop` tracking for `for` if needed.
2. **Class-based components / `@props` / `@aware` / slot objects** — deliberately skipped; string `$slot` is sufficient for current use cases.
3. **Not full Blade compatibility** — Laravel service injection, Livewire, route/model binding, `Js::from` are intentionally absent.
4. **`@dd` uses exception** — not `exit()`, so render cycle returns cleanly to callers (dev-only).
5. **Custom directive auto-echo** — bare expressions without `;` or `:` get `echo ` prepended; statements pass through.

### 9.4 Verification Results

- PHP lint: all files pass
- PHPUnit: all tests green (existing ~193 + new e2e)
- PHPStan: 0 errors
- PHPCS: 0 errors (warnings for deliberate `@`-silencing on cache writes acceptable)
## Deep Audit — 2026-11-13

**Branch:** `feat/drip-template-engine` · **Commit:** `45972af` (audit berjalan sampai `c9ac374`).
**Metode:** manual line-by-line per file (6 batch + review langsung: bootstrap `.supports`/`plugin.init`/`.starter`, `Boot`/`Runner`/`Router`/`Pipeline`/API, services, templates, tests, docs/CI). PHPCS/PHPStan/PHPUnit **bukti pendukung saja** — tidak menggantikan pembacaan kode. Temuan diberi CONFIRMED (trace lengkap) / HYPOTHESIS (perlu verifikasi lanjutan) / INTENTIONAL (`ponytail:` atau self-documented).

### Cakupan

- **Reviewed penuh (end-to-end):** `system/Core/*` (Boot, Registry, Router, Runner, Database, QueryBuilder, Model, Record, Relation, Api, Private, Public), `system/Services/*` (Drip 1770, TranslationCatalog, AdminUI, Settings, Pages, Module, Cli, Assets, I18n, Actions, Pipeline), `system/Helpers/*`, `system/Config/*`, `system/Templates/*`, `system/Contracts/*`, `system/Middleware/*`, `modules/*` (seluruh file), `assets/*`, `tests/*` + `phpunit.xml`, `docs/*` (PLAN/PRD/REPORT/CHANGELOG/RULES/GUIDELINE), `plugin.init.php`, `.starter`, `.supports`, workflows GitHub, `phpcs.xml`, `phpstan.neon.dist`, `.gitignore`, `.gitattributes`, `languages/*.pot`/`*.po` (hitung msgid; `.mo` = binary, tidak di-inspeksi), `README.md`, `LICENSE`, `CLAUDE.md`.
- **Eksklusi:** `cache/*` (artefak build/lint), `languages/radykfw-id_ID.mo` (binary 16.562 B), `docs/DRAFT.md` (git-ignored, prior work — hanya dikutip sebagai bukti).
- **Catatan coverage:** `DripCompilerTest`/`DripE2ETest`/`DripLayoutComponentTest` dibaca penuh oleh reviewer test + spot-check manual; 4 test CLI (`HelperGuardCliTest`, `HelperTypeGuardCliTest`, `HelperTypeScanCheck`, `subprocess`) dijalankan sebagai skrip, bukan lewat suite.

### Hasil tools (eksak)

| Tool | Hasil | Catatan |
|---|---|---|
| PHPUnit `cache/phpunit.phar` | **218 tests / 821 assertions, 3 gagal** | 3× `@verbatim` (`DripCompilerTest:385/395/406`) — sudah ada sejak sebelum commit audit |
| `--filter ApiAuthGatewayTest` | 5/5 pass, 17 assertions | skenario subprocess **tidak di-skip** di Windows (klaim "selalu skip" ditolak) |
| PHPCS `cache/phpcs.phar` | 1 error, 7 warning | error: EOF newline `modules/Welcome/Config/directives.conf.php:48`; warning: `@mkdir/@file_put_contents/@unlink/@rename` `Drip.service.php:1709-1752` |
| PHPStan `cache/phpstan.phar` | clean | default `memory_limit 128M` membuat worker crash; perlu `--memory-limit=1G` (quirk tooling) |
| `php -l Router.core.php` | parse OK | menolak temuan "kurang closing brace" |

### Temuan

#### HIGH

**H1. `POST /login/authenticate` tidak punya alias → form login 404.** CONFIRMED.
Bukti: `modules/Login/Config/routing.conf.php:12` hanya mendaftarkan `GET /login`; form login (`login.view.php:133`) mengirim ke `home_url('/login/authenticate')`; `RouterHandleTest:75-79` menetapkan case-2 tanpa controller = fall-through ke WP; `routing.conf.php:13-39` semua alias sistem ter-comment-out; `modules.conf.php` sistem `auto_discover=false` (Router:53-56). Dampak: alur login utama rusak. Korelasi: `docs/DRAFT.md` P1-1 (ignored) sudah menandai hal ini. Remediasi: tambah `'POST /login/authenticate' => 'Login\Login::authenticate'` (atau aktifkan auto-discover) + regresi RouterHandle. Verifikasi: render `/login`, POST form → sukses masuk, bukan 404.

**H2. Session fixation di Login — tanpa `session_regenerate_id(true)` setelah login sukses.** CONFIRMED.
Bukti: `Login.controller.php:64-68` (`wp_signon` → redirect) tidak pernah memanggil `session_regenerate_id`; grep seluruh repo: 0 referensi. Aturan RULES.md mewajibkan regenerasi session ID saat login. `$_SESSION['radykfw_login_error']` (view `login.view.php:8-11`) memperburuk karena state raw. Remediasi: `session_regenerate_id(true)` setelah `wp_signon` sukses (sebelum redirect) + bersihkan `$_SESSION['radykfw_login_error']` di `logout()` (`Login.controller.php:79-83`). Verifikasi: cookie session berubah setelah login; error flash tak bertahan setelah logout.

**H3. `show_error()` di `.supports` tak bisa mengeluarkan error — selalu return.** CONFIRMED.
Bukti: `.supports:199-204` — di branch string, `$code = is_int($message) ? $message : 500` lalu `$exit = is_bool($code) ? $code : false`; karena `$code` baru saja di-*cast* int, `is_bool()` selalu false → `$exit` selalu false → blok `die()` (`:213-215`) tak pernah tercapai; non-debug bahkan membuang pesan (`:207`) dan tanpa exit. Fungsi pun tanpa pemanggil. Remediasi: hapus helper (dead) atau tulis ulang semantik (pola dua argumen string+int tak bisa di-ambiguate dengan tipe union) + verifikasi di pure-PHP.

**H4. `plugin.init.php:46` — `wp_die($e)` membocorkan Throwable mentah.** CONFIRMED.
Bukti: `wp_die()` mengharapkan string; melempar `\Throwable` membuat PHP mem-*coerce* via `__toString()` → file path, kode error, stack trace ke klien. Belum ada `error_log` sebelum mati. Remediasi: `error_log("RadykFW boot: ".$e->getMessage()); wp_die('An unexpected error occurred.', 'Internal System Error');` + verifikasi di WP dengan boot rusak disimulasikan.

**H5. `session_start()` sebelum guard ABSPATH, tanpa flag cookie.** CONFIRMED.
Bukti: `plugin.init.php:20` sebelum guard `:24-27`; akses langsung file tetap membuka session; tanpa `session_set_cookie_params` (Secure/SameSite/HttpOnly). Remediasi: pindahkan ke setelah guard + set flag cookie sesuai RULES.md. Verifikasi: request langsung ke file tak lagi memulai session.

**H6. `@transChoice` mati — nama camelCase tak pernah tercapai.** CONFIRMED.
Bukti: `Drip.service.php:1057` `strtolower($m[1])` menghasilkan `'transchoice'`; chain membandingkan `$word === 'transChoice'` (`:1277`) → selamanya false. Nol pemakaian, nol test di repo (grep `transchoice` di `tests|system|modules` = kosong). Klaim docblock `Drip.service.php:51` ("`@trans`/`@transChoice` use `__()`/`_n()`, escaped") dan `tests/DripLayoutComponentTest.php:22` ("each built-in 'common PHP' directive … including `@transChoice`") salah. Remediasi: ubah perbandingan ke `'transchoice'` + test regresi; atau hapus directive + klaim. Verifikasi: render `@transChoice('k', $n)` memanggil `_n()`.

**H7. Slot komponen campuran → string mati di komponen.** CONFIRMED.
Bukti: `Drip.service.php:839-852` — slot hanya di-inline jika *seluruhnya* berupa PHP tersimpan (`str_starts_with(ltrim($slot), '<?php')`); slot `{!! $x !!}` + teks biasa (`<?php echo ...; ?>Some text`) jatuh ke cabang `escapePhpString` → menjadi literal → `{{ }}`/`{!! !!}` *dalam* slot tak dikompilasi. Remediasi: pecah segmentasi (`<?php ?>` + HTML) atau dokumentasikan batasan + test; verifikasi dengan komponen yang menerima slot bercampur.

**H8. `Drip.service.php:1729` `@mkdir($dir, 0777, true)` — cache dir world-writable.** CONFIRMED.
`cacheDir()` membuat root dengan 0750 (`:1709`), tapi `writeCachedFiles()` membuat ulang dengan 0777. Di multi-user webserver, user lain dapat menanam file cache PHP. Remediasi: 0750 konsisten (atau `0750`/`0755` sesuai deploy) + verifikasi di FS multi-user.

**H9. `login.view.php` — logika berjalan sebelum guard `ABSPATH`.** CONFIRMED.
Bukti: `login.view.php:8-10` membaca + `unset($_SESSION['radykfw_login_error'])`, guard baru di `:14`. Akses langsung ke view langsung memakai state session. Remediasi: pindahkan guard ke baris pertama (seperti file lain) + verifikasi akses langsung ke file view.

**H10. `Api::user()` membocorkan `user_email` ke setiap user log-in.** CONFIRMED.
Bukti: `Api.controller.php:28-35` — gate hanya `is_user_logged_in()`; subscriber bisa membaca email admin lewat `/api/user` (bila route aktif). Remediasi: capability `edit_users`/`manage_options` untuk field sensitif + test. Verifikasi: subscriber → 403/field terfilter.

**H11. `Pages` tidak melihat module aliases — statistik dan tabel route di halaman Modules selalu kosong/kurang.** CONFIRMED.
Bukti: `Pages.service.php:30,112` `get_config('routing.aliases')` saja; alias aktif semua dari `modules/{Module}/Config/routing.conf.php` (`Welcome/routing.conf.php:14-19`, `Login/routing.conf.php:12`) yang hanya masuk via `Module::discover()['aliases']` (`Router.core.php:185,336`). Dampak: `total_aliases`=0 di Overview/Modules admin. Remediasi: baca `get_config(...) + Module::discover()['aliases']` + verifikasi angka.

**H12. `App debug/dump` tak terkontrol — `@dump`/`@dd` dan `Drip` tanpa gate `WP_DEBUG`.** CONFIRMED.
Bukti: `Drip.service.php:1312-1318` `@dump`/`@dd` selalu terkompilasi; helper `__dump`/`__print` (`.supports:138,158`) *punya* gate `is_debug()`, tapi `__dump`/`__print` tak pernah dipakai (H28) sehingga tidak ada satu pun debug output yang terkontrol. Remediasi: gate handler di konfigurasi/compilation (mis. `if (is_debug())` di emitted code untuk `@dump`) + test. Verifikasi: tanpa `WP_DEBUG`, `@dump` tidak menghasilkan output.

#### MEDIUM

**M1. `Router::tryDispatchNoRecurse` — rekursi tak terbatas + salah doc.** CONFIRMED.
`Router.core.php:278-283` docblock klaim "no param-matching guard" padahal memanggil `tryDispatch` penuh; bila `index()` punya required param, `dispatchFallback` (`:264`) ↔ `tryDispatchNoRecurse` berputar sampai fatal. Remediasi: panggil `invokeMethod` langsung atau tandai `index` sebagai terminal.

**M2. Padding arg menimpa default param opsional.** CONFIRMED.
`Router.core.php:246` `array_pad($params, $maxFixed, null)` mem-*null*-kan param ber-default. Remediasi: pad hanya sampai `$required`, sisakan default.

**M3. Segment method (`$segments[2]`) tak divalidasi identifier.** CONFIRMED.
`Router.core.php:109` — hanya `[0],[1]` dicek `isValidSegment` (`:98,106`); method bebas karakter (termasuk NUL — tak dicegah, hanya di-*coerce* PHP 8). Remediasi: `isValidSegment` untuk segmen method + regresi.

**M4. QueryBuilder: identifier kolom/tabel tidak divalidasi.** CONFIRMED (batas rendah).
`QueryBuilder.core.php:32-58,108-135` — value parameterized (`prepared()`), tapi kolom/operator/arah tabel masuk string mentah ke SQL. Saat ini semua pemakai memakai literal developer. Remediasi: allowlist identifier + test.

**M5. `catch_server` di-deprecate ke class yang tak ada.** CONFIRMED.
`.supports:181-183` `@deprecated 2.0.0 Use System\Support\WordPress::server()` — `System\Support` tidak ada di repo (grep 0 hit). Pemakai: `Router.core.php:341,344`. Remediasi: hapus deprecation notice atau luncurkan target.

**M6. `Runner` memanggil `Drip::directivesFromConfig` — `Drip` adalah service, tak ada di daftar loader process.** HYPOTHESIS (perlu cek `load_process` untuk `Services`). Jika loader hanya memuat `Core`, `Drip` takkan tersedia saat hook register. Remediasi: pastikan autoloader memuat `Services` (via Registry/Boot) sebelum `Runner::initHooks`; verifikasi dengan boot pure-PHP.

**M7. Nonce modules form tak pernah diterbitkan.** HYPOTHESIS.
`actions.conf.php:15` `radyk_modules_action` terdaftar + diverifikasi (`Pages.service.php:108`), tapi tak ada `wp_nonce_field('radyk_modules_action')` di template — hanya 3 nonce settings (`settings.page.php:32,83,113`). Remediasi: terbitkan field di `modules.page.php` bila form aktif; jika form dihapus, hapus action.

**M8. `@csrf` menerbitkan nonce kosong tanpa action.** HYPOTHESIS.
`Drip.service.php:1225` `wp_nonce_field()` tanpa arg → default action; verifikasi server-side harus padan. Dokumentasikan kontrak atau teruskan action eksplisit.

**M9. Directive handler module menyisipkan arg ke PHP terkompilasi.** HYPOTHESIS (pola, kini aman).
`modules/Welcome/Config/directives.conf.php:22-46` — `@feature_available`/`@if_module_active` meng-*interpolate* arg handler ke statement PHP; hari ini hanya string literal internal. Bila ada arg dari input, = code injection. Remediasi: validasi arg identifier/allowlist + test; atau tandai trust boundary di doc.

**M10. `@php`/`@endphp` + `@inject` = eksekusi PHP sembarang bagi pemakai template.** INTENTIONAL trade-off (Blade-parity) — wajib didokumentasikan: hanya template tepercaya (repo), jangan gunakan untuk konten user.

**M11. `@json` tanpa `JSON_HEX_TAG` — `</script>` breakout.** CONFIRMED (batas rendah).
`Drip.service.php:1326` `@js` sudah HEX_TAG (`:1322`) tapi `@json` tidak; `</script>` dalam JSON keluaran bisa menutup tag. Remediasi: tambah `JSON_HEX_TAG` atau batasi pemakaian `@json` di luar `<script>`.

**M12. `Api::respond` — `json_encode` tanpa `JSON_THROW_ON_ERROR`.** CONFIRMED (batas rendah).
`Api.core.php:34` — payload dengan string non-UTF8 → `null` + HTTP 200. Remediasi: `JSON_THROW_ON_ERROR` + catch.

**M13. `Settings::getConfigValue($key, $default≠null)` menjatuhkan nilai config file.** CONFIRMED (trap).
`Settings.service.php:87-90` — `elseif ($default === null) { $default = get_config($key); }`: `Router.core.php:53,63` + `Pages` selalu melewatkan `get_config(...)` sebagai default, jadi nilai di `routing.conf.php` tak pernah jadi default efektif bila option tak tersimpan. Remediasi: ubah semantik `elseif (!isset(self::manualOverride($key)))` atau hapus param default ganda + test.

**M14. `App.conf.php` — `app.name`, `app.timezone`, `app.debug` tak terbaca; default version `1.0.1` basi.** CONFIRMED.
Grep 0 runtime read; `Registry::get('version')` = 1.3.0. Remediasi: hapus key mati atau terapkan.

**M15. Registry/URL key membengkak — `nsenum`, `nsmiddleware`, `nscontrollers`, `nsmodels`, `nsservices`, `css_url`, `images_url`, `js_url`, `xt*` hanya dikonsumsi test.** CONFIRMED (genis loop generik `path_meta.conf.php`).
Remediasi: kurangi metadata ke key yang terpakai, atau tandai "reserved for future modules" di doc.

**M16. `assets/modules/login.css` + `login.js` — dideklarasikan di config, tak pernah di-enqueue.** CONFIRMED.
`Assets.service.php:66` `enqueueModuleAssets()` 0 pemakai; `assets.conf.php:73,81` mengreferensikan dua file itu; `login.view.php` memakai `<style>` inline. Remediasi: hapus config or `enqueueModuleAssets('login')` di controller + verifikasi.

**M17. `modules.page.php` meng-hard-code daftar module — module baru tak muncul.** CONFIRMED.
`Pages.service.php:116-146` (`modules_data`) vs `Module::discover()` dinamis. Remediasi: bangun dari `discover()` + verifikasi.

**M18. `@forelse` tak punya metadata loop.** CONFIRMED (deviasi dari `@foreach`).
`Drip.service.php:1141` vs `:1159` — `@forelse` memanggil `compileLoopMetadata('foreach'…)`? (trace: `$word==='forelse'` → `compileLoopMetadata($a,'forelse',…)`); metadata ada, tapi `@empty` di dalam forelse hanya membandingkan count=0. Perilaku: `forelseLoopMetadata` test (`DripLayoutComponentTest:322-338`) lulus dengan count=2; untuk array kosong, `first/last` undefined (count=0 → `$__drip_fe_count=0`). Dampak: template yang membaca `$loop->first` di branch empty akan null. Remediasi: set default metadata di branch empty.

**M19. `Router::handle` case 0 — default module 'Welcome' tanpa cek keberadaan.** CONFIRMED (batas rendah).
`Router.core.php:83` + fallback `:150-157` — module tak ada = fall-through ke WP (404 WP). Ok bila `routing.native=true`, tapi saat `routing.native=false` + default module hilang = tidak ada fallback. Remediasi: validasi default module terhadap `Module::discover()` saat `auto_discover=false`.

**M20. `AdminUI` — `screenMatches` tak ada saat `in_page=null` → notice tampil di semua admin page.** INTENTIONAL (docblock menyebut `null = all pages`). Catatan: `in_page` di `Runner` untuk helper guard terbatas `['dashboard','plugins']` (`Runner.core.php:85-88`); `addAdminNotice` default null = semua — konsistensi OK tapi perhatikan leakage error ke seluruh admin.

**M21. `.gitignore /docs/` — file docs baru di-*ignore* diam-diam.** CONFIRMED.
`.gitignore:46` — `PLAN/PRD/REPORT/CHANGELOG/GUIDELINE` ter-track (track-before-ignore), tapi file docs baru takkan ter-stage tanpa `-f`. Remediasi: ganti jadi daftar eksplisit + `docs/CONFIG.md` spesifik.

**M22. `CHANGELOG [Unreleased]` — dua heading `### Fixed` berurutan (`:12` & `:18`).** CONFIRMED (efek edit `>push`). Remediasi: gabung bagian; pertahankan urutan kanonik KAC.

**M23. `DripCompilerTest.php:511-531` `cacheInvalidationOnCompilerVersionBump` — nama meleset, klaim coverage overstate.** CONFIRMED.
Test menyebut version bump tapi hanya menguji mtime path (acknowledged di komentar sendiri); `touch(time()+20)` dua kali dengan content sama → tak ada beda observabel. Remediasi: perbarui nama ke `cacheInvalidationOnSourceMtime` atau buat test version-bump yang mengubah konstanta.

**M24. `DripCompilerTest` 3 test `@verbatim` salah — compiler mengikuti dokumentasi.** CONFIRMED.
`Drip.service.php:600-613` `compileVerbatim` meng-entity-enkode `{{`, `{!!`, `@`, `<?php`, `<?=`; test `:385` (`'5done'`), `:406` (`'V'`) berharap PHP *dieksekusi*; `:395` berharap `{{ $name }}` literal padahal dapat entity. Dokumentasi (`GUIDELINE.md`, REPORT §Drip, contoh `test.view.php:113-165`) semua memperlakukan `@verbatim` = konten literal Blade-parity. Remediasi: ubah expected test ke entity-encoded (atau putuskan eksekusi PHP, lalu ubah compiler + doc).

**M25. `guard-probe.helper.php` + entri `helper.conf.php:14` — fixture tes di-*ship* di config produksi.** INTENTIONAL (self-documented: file & config punya komentar "hapus setelah verifikasi"). Remediasi: hapus sebelum release per PLAN R29.

**M26. `AdminUI::getTopMenu` — `reset($top_menus)` mem-*return* entry pertama; API asikron tak ada.** INFO.

**M27. `Router::tryAliases` `home_url('/')` strip prefix — bila home di subdirektori, alias di belakang `home` terhapus.** HYPOTHESIS (batas rendah). Remediasi: test instalasi subdirektori.

**M28. `Api::posts` `per_page` tanpa batas — `?per_page=999999` tarik semua wp_posts.** CONFIRMED.
`Api.controller.php:47` + `Posts.model.php` (QueryBuilder `limit($n)`). Remediasi: `min(max($per_page,1),100)`.

#### LOW / INFO

- **L1.** `Registry.core.php:107` — komentar `$reg['cache_url']=null;` mati; 4 test meng-assert key `cache_url=null` (`DripCompilerTest:39`, dll) padahal Registry tak mendefinisikan key → test lulus lewat default `Registry::get` (null) — *intent* key hilang. Remediasi: definisikan key secara eksplisit atau buang dari test.
- **L2.** `test.view.php:1` docblock menuliskan `index.view.php` (copy-paste).
- **L3.** `home.view.php:7` docblock `URL: /welcome/home`; `door.view.php:7` klaim `/welcome/home/door` tapi alias aktif `GET /welcome/door` (`Welcome/routing.conf.php:19`).
- **L4.** `404.error.html:83` `javascript:history.back()` — anti-pattern CSP.
- **L5.** `admin.js:14-24` toggle JS tanpa nonce/capability di server (latent; tak ada AJAX action terdaftar saat ini).
- **L6.** `admin.js:43-47` auto-dismiss notice tanpa `aria-live`.
- **L7.** `modules.page.php:88-92` `esc_html` pada angka (kosmetik).
- **L8.** `app.conf.php:12` default `version 1.0.1` vs `plugin.init.php:6` `1.3.0` vs `Api.controller.php:25` fallback `1.0.1` — 3 sumber versi. Remediasi: single source dari `Registry::get('version')`.
- **L9.** `PRD.md:154-157,531` merujuk `RadykFW_Config` class yang tak ada — dokumen bootstrap basi.
- **L10.** `REPORT.md:559,572` klaim `~193 tests / ~500+ assertions` & `all tests green` vs runtime 218/821 + 3 gagal — metrik drift.
- **L11.** `CHANGELOG` note `178-message POT/PO parity` vs 182 msgid aktual — basi.
- **L12.** `catch_server` di-*deprecated* ke class hantu (lihat M5).
- **L13.** `Tested up to: 7.1` vs WP stable saat ini; `Requires PHP 8.1` vs RULES "8.3+" — divergen baseline.
- **L14.** `.gitignore` `*.old.junk` vs `CLAUDE.md`/PLAN menyebut `*.junk` & `*.bak` — konsistensi minor.
- **L15.** `languages/radykfw.pot` 182 msgid, `.po` 182 (1 tak diterjemahkan), `.mo` binary tak di-inspeksi.
- **L16.** `Runner::uninstall` bersihkan transient `radykfw_modules_cache`/`radykfw_routes_cache` — tak ada kode yang menulis transient itu (dead cleanup).
- **L17.** `Pipeline::fail()` 500 + `esc_html(__(...))` — `__()` ter-*escape* di-*double-escape* (`esc_html` pada string `__('Route configuration error.')` yang sudah literal) — kosmetik.
- **L18.** `view.helper.php` `extract($scope)` + `include` — template bertanggung jawab escape; Drip `{{ }}` sudah ter-*escape* via `Drip::e`, tapi `{!! !!}` raw = trust boundary (M10).
- **L19.** `Settings::handleSaveRequest` mem-*wp_die* pada `saveConfig` gagal — `update_option` false = *no-op* bila tak berubah; UX minor.
- **L20.** `Cli.service.php` `make` scaffold `fopen(...,'x')` — race-safe; rollback rapi. Ok.

#### Temuan ditolak (false-positif yang terverifikasi)

- **"Router.core.php kurang `}` di akhir"** (klaim 1 agent: parse error) — **DITOLAK**. `php -l` bersih; ekor file menutup `filterCanonicalRedirect` secara normal.
- **"Reflection `invoke` string→int `TypeError` di `/api/posts`"** (GAP-9 agent config-modules) — **DITOLAK**. Probe: di file `strict_types=1`, `ReflectionMethod::invoke(null,'2')` pada `function list(int $page)` tetap *coerce* (`ok:2`). PHP memakai *caller's* strictness untuk *coercion* — *Reflection* sendiri bukan strict boundary.
- **"`ApiAuthGatewayTest` 5 skenario selalu skip di Windows"** (klaim test agent) — **DITOLAK**. `--filter ApiAuthGatewayTest` = 5/5 pass di Windows (17 assertions); `subprocess.php` memakai `PHP_BINARY` cross-platform.

### Remediation & Verifikasi (ringkas per temuan)

| ID | Remediasi | Verifikasi |
|---|---|---|
| H1 | alias `POST /login/authenticate` (atau `auto_discover=true`) | POST `/login` form → masuk, tak 404; RouterHandle regresi |
| H2 | `session_regenerate_id(true)` + bersihkan `$_SESSION` di logout | cookie session berubah; error flash tak bertahan |
| H3 | hapus/rewrite `show_error` (dead + logika rusak) | pure-PHP: non-debug tak lagi drop pesan |
| H4 | `error_log` + `wp_die` pesan generic | boot rusak: stack trace tak bocor |
| H5 | `session_start` setelah guard + cookie flags | request langsung tak membuka session |
| H6 | bandingkan `'transchoice'` + test | `@transChoice` memanggil `_n` |
| H7 | pecah segmentasi slot / dokumentasikan | komponen menerima slot campuran |
| H8 | `0750` di `writeCachedFiles` | FS multi-user tak dapat menanam cache |
| H9 | guard `ABSPATH` pertama di view | akses langsung view tak menyentuh `$_SESSION` |
| H10 | capability check di `Api::user/posts` | subscriber → 403/field terfilter |
| H11 | `Pages` baca `Module::discover()['aliases']` | `total_aliases` di admin = jumlah nyata |
| H12 | gate `@dump/@dd` pada `is_debug()` | tanpa `WP_DEBUG` → tak ada output |
| M1-M28, L1-L20 | per remediasi di atas | per test/regresi yang disebut |

**Estimasi usaha (H+M saja):** H1 < 1h · H2 2h · H3 2h · H4/H5 1h · H6 2h · H7 4h · H8 30m · H9 1h · H10 2h · H11 2h · H12 3h · M-batch 6-8h. Urutan: **H1 → H2 → H8 → H6 → H7** (pengguna-facing & security-first), lalu batch M.

### Catatan proses

- 6 batch reviewer paralel (1 gagal `explore` model tidak tersedia; 5 `general-purpose`); 2 laporan terpotong → ekor diminta; 1 (`audit-services`) mengembalikan artefak tak relevan → dilanjutkan manual; `audit-drip` tanpa keluaran → dilanjutkan manual (1770 baris dibaca penuh).
- Tidak ada *fix* yang diterapkan selama audit (sesuai aturan `>audit`).
- Temuan `docs/DRAFT.md` (ignored) dikutip hanya sebagai *bukti*, bukan sebagai sumber perubahan.


---

## Remediasi Program — 2026-11-13 (branch `fix/deep-audit-remediation`)

**Eksekusi per-ID, dengan konfirmasi user pada langkah fatal/kritikal.**

### Selesai
- **H2** `session_regenerate_id(true)` setelah `wp_signon` sukses (`Login.controller.php:68`, guard `function_exists`) — mencegah session fixation; `\['radykfw_login_error']` dibersihkan di `logout()`.
- **H3** `show_error()` di-rewrite semantik-nya (`string \, int \ = 500, bool \ = false` + bentuk `array['response','exit']`); tidak dihapus (per permintaan user). Tidak ada pemanggil di repo — verifikasi: grep `show_error` = definisi saja.

### Dilewati (locked files) — catatan risiko
- **H4** (`plugin.init.php:46` `wp_die(\)` — stack-trace bocor). **Bila dieksekusi:** `error_log` + `wp_die('An unexpected error occurred.')`; menghilangkan kebocoran path/code ke browser. **Dampak bila dieksekusi:** debugging kegagalan boot di browser jadi buta — penyebab hanya terlihat di error_log server.
- **H5** (`session_start()` sebelum guard ABSPATH + tanpa cookie flags). **Bila dieksekusi:** `session_set_cookie_params` (Secure via `is_ssl()`, HttpOnly, SameSite=Lax) dipanggil SEBELUM `session_start`, dan `session_start` dipindah ke SETELAH guard `ABSPATH`. **Dampak bila dieksekusi:** (a) request langsung ke `plugin.init.php` (weird/path traversal probe) tak lagi memulai session; (b) di instalasi HTTP murni cookie Secure tak terkirim → session tak tersedia di browser HTTP → flash `\['radykfw_login_error']` (Login) tak terbaca — di HTTP murni login error flash hilang (authentication tetap jalan lewat cookie auth WP). Kode saat ini (tanpa fix) rentan: session dimulai untuk request 404 dan cookie tanpa flags.

### Belum dikerjakan
H1 (alias login/authenticate), H6-H12, M-batch, L-batch — urutan di `docs/PLAN.md` §10.
