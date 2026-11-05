# PLAN — RadykFW Development Roadmap

> Dokumen ini merangkum prioritas perbaikan & pengembangan berdasarkan audit REPORT.md + PRD.md.
> Terkini: 2026-11-05 | Branch: `dev` | Commit terbaru: `f43334c`
>
> **Status aktual (verifikasi 2026-11-05):** Item "Sudah selesai" sudah di-commit. Item "Masih open" terverifikasi masih ada di kode.
> **Catatan:** Dokumen ini hanya perencanaan — eksekusi menunggu instruksi terpisah.

---

## 1. Fase 1: Cleanup & Hardening (Prio: High)

### 1.1 Runner.core.php — Dead Code & Latent Bugs
| No | Item | Status | Detail |
|---|------|--------|--------|
| R1 | Hapus `initRegistry()` | ⚠️ Open | Method kosong, tidak pernah dipanggil (baris 41-46) |
| R2 | Tambah filter skip di `shouldSkipRouting` | ⚠️ Open | Baris 156: **tambahkan** filter `['login','signup','activate']` (path tanpa `.php`) di samping `wp-login.php` yang sudah ada, **bukan** menggantikan. Request WP berisi path tanpa extension. |
| R3 | Hapus `add_action('template_redirect', fn(){exit;})` | ⚠️ Open | Baris 130 — redundant, view sudah `exit` sendiri |
| R4 | Hapus `flush_rewrite_rules()` dari `uninstall()` | ⚠️ Open | Baris 99 — riskan di konteks uninstall |
| R5 | Hapus param `$extra_query_vars` dari `handleRouting()` | ⚠️ Open | Baris 114 — tak dipakai WP |
| R6 | Escape version di `adminNotices()` | ✅ Done | Sudah pakai `Registry::get('wp_version')` (baris 178, 180) |

### 1.2 Router.core.php — Dead Methods
| No | Item | Status | Detail |
|---|------|--------|--------|
| R7 | Hapus `dispatchFallback()` param `$params` | ⚠️ Open | Baris 220 — param tak terpakai |
| R8 | Fix docblock `dispatchFallback` | ⚠️ Open | Bilang "Retry the SAME method" padahal retry ke `index` |
| R9 | Extract `invokeMethod()` helper | ⚠️ Open | Duplikasi ~80% antara `tryDispatch` & `tryDispatchNoRecurse` |
| R10 | Normalisasi tanpa `strtolower` | ⚠️ Open | `ucfirst(strtolower())` di baris 57, 65, 73 merusak camelCase |

### 1.3 Module Controllers — Inconsistencies
| No | Item | Status | Detail |
|---|------|--------|--------|
| R11 | `Welcome.controller.php` konfirmasi mati | ✅ Done | `/welcome` → `Welcome::index` (Router case-1, `controller = $module`). `Index.controller.php` dihapus karena redundan. |
| R12 | `...$args` tak terpakai di views | ⚠️ Open | `home.view.php`, `door.view.php` tidak akses `$args` |
| R13 | Footer link hardcode (`/documentation`, `/github`) | ⚠️ Open | `index.view.php` baris 172-174 tak ada route-nya |
| R14 | Font `Inter` referensi tapi tak enqueue | ✅ Done | `assets/css/fonts/inter.css` dibuat + `radykfw_asset()` di-enqueue di `Welcome::index`. (File `.woff2` perlu ditambahkan manual ke `assets/css/fonts/`.) |

---

## 2. Fase 2: Base Model & Database Layer (Prio: Medium-High)

Sesuai PRD §8 & REPORT §7. Implementasi `Database.core.php` dulu, baru `Model.core.php`.

### 2.1 `system/Core/Database.core.php` (prerequisite)
- Wrapper `$wpdb` dengan API re-branded: `select()`, `insert()`, `update()`, `delete()`, `transaction()`
- Query builder minimal (opsional): `Database::table('posts')->where(...)->get()`
- Load via autoloader (namespace `System\Core\Database`)

### 2.2 `system/Core/Model.core.php`
- Abstract base: `abstract class Model`
- Method: `module()`, `db()` (return Database instance), `find()`, `findAll()`, `create()`, `update()`, `delete()`
- Table name bisa di-set via protected static `$table = 'wp_posts'`

### 2.3 Sample Module Models
- `modules/Dashboard/Models/Stats.model.php` — extend Model, contoh pakai Database
- `modules/Api/Models/Posts.model.php` — contoh query wp_posts

---

## 3. Fase 3: Security Polish (Prio: Medium)

| No | Item | Status | Detail |
|---|------|--------|--------|
| S1 | `Login.controller.php` password sanitize | ⚠️ Open | Baris 49: ganti `$_POST['password'] ?? ''` → `filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW)`. **Keputusan: YA** (QA jawab). |
| S2 | `login.view.php` `$_POST['username']` → pass via view data | ⚠️ Open | Baris 132: controller pass `['error'=>$error, 'username'=>$username, 'action_url'=>home_url('/login/authenticate')]`. View ganti `$_POST['username']` → `$username`, `action=""` → `action="<?php echo esc_url($action_url); ?>"`. **Keputusan: YA** (QA jawab). |
| S3 | `login.view.php` action eksplisit | ⚠️ Open | Digabung ke S2 — `action` dari view data, bukan `""`. |
| S4 | `*.bak` / `*.junk` di `.gitignore` konsisten | ✅ Done | Sudah di-.gitignore |
| S5 | Login redirect flow via `/auth` | ✅ Done | `Login::authenticate` → redirect `config('auth.post_login_url','/auth')`; `Auth` module (`PrivateController`) validasi session → `/dashboard` bila valid, `/login` bila tidak. `logout` → `config('auth.post_logout_url','/login')`. |

---

## 4. Fase 4: Configuration & Assets

| No | Item | Status | Detail |
|---|------|--------|--------|
| C1 | Buat `system/Config/app.conf.php` | ✅ Done | Sudah dibuat: `app`, `modules`, `routing` (default + skip_paths + skip_extensions), `auth` (post_login_url=/auth, post_logout_url=/login, login_url=/login) |
| C2 | `config()` helper general (semua file config) | ✅ Done | `config($key, $default, $file='app')` — akses dot-notation ke **semua** `system/Config/*.conf.php`, bukan hanya `app`. Default file `app.conf.php`. Terdaftar di `helper.conf.php`. |
| C3 | Enqueue font `Inter` (jika tetap dipakai) | ✅ Done | `assets/css/fonts/inter.css` dibuat; `radykfw_asset('radykfw-inter-font','fonts/inter.css')` di-enqueue di `Welcome::index`. Catatan: file `.woff2` (inter-regular/medium/semibold/bold) perlu ditambahkan manual ke `assets/css/fonts/`. |
| C4 | Konsolidasi `config()` & `get_config()` | ⚠️ Open | `get_config('X')` (`.supports`, baca file config utuh, pre-autoload) dan `config($key,$d,$file)` (helper, dot-notation, runtime) **mirip tapi tak duplikat**: `get_config` dipakai `Runner` **sebelum** helper ter-load (chicken-egg), `config` dipakai controller **setelah**. **Keputusan:** buat `get_config()` menyerap kemampuan dot-notation `config()` lalu hapus `config.helper.php`, agar tak ada 2 fungsi mirip yang membingungkan. (Belum dieksekusi.) |

---

## 5. Fase 5: Testing Infrastructure (Prio: Low-Medium)

| No | Item | Status | Detail |
|---|------|--------|--------|
| T1 | Buat struktur `tests/` minimal | ⚠️ Open | PHPUnit + bootstrap |
| T2 | Hapus dev-deps dari `composer.json` jika tests belum siap | ⚠️ Open | Atau implementasikan |
| T3 | `.gitignore` scope `vendor/` | ⚠️ Open | Hapus `wp-content/plugins/*/vendor/` (terlalu luas) |

---

## 6. Priority Matrix

| Priority | Fase | Estimasi Effort |
|----------|------|-----------------|
| 🔴 High | Fase 1 (Runner + Router cleanup) | 1–2 jam |
| 🟠 High | Fase 2 (Database + Model core) | 3–4 jam |
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

---

*Dibuat berdasarkan audit REPORT.md dan PRD.md. Revision 4 — 2026-11-05 (QA R2/S1/S2/S3 + C4 config-consolidasi dicatat; eksekusi menunggu instruksi).*
