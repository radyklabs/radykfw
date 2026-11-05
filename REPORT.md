# REPORT — Audit & Review RadykFW

> Audit menyeluruh terhadap plugin WordPress HMVC **radykfw** (standalone, tanpa dependensi third-party).
> Tanggal: 2026-11-04. Cakupan: seluruh file aktif di `system/`, `modules/`, `.supports`, `.starter`, `radykfw.php`, `composer.json`, `.gitignore`.
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

## 2. Temuan per File

> Status: ✅ RESOLVED (sudah di-fix), ⚠️ STILL OPEN (masih perlu perhatian), 🔵 Info

### 2.1 `system/Core/Router.core.php`

- ✅ **C1 — Input segment tak tervalidasi** — **RESOLVED**. `isValidSegment()` ditambahkan, semua case validasi segment.
- ✅ **C2 — Fallback `Index::index()` membuat URL tak dikenal selalu "matched"** — **RESOLVED**. Fallback Index hanya untuk 0–1 segments; deep routes fall-through ke WP 404.
- 🟠 **M — `dispatchFallback` mengabaikan `$params` + docblock menyesatkan** (baris 220–227) — **STILL OPEN**. `dispatchFallback($class, $params)` tidak pakai `$params`; docblock bilang "Retry the SAME method" padahal retry ke `index`. **Fix:** hapus param tak terpakai atau retry method asli; perbaiki docblock.
- ✅ **M — `fallbackToWordPress()` no-op & mati** — **RESOLVED**. Sudah dihapus.
- ⚠️ **M — `strtolower` merusak camelCase** (baris 57, 65, 73) — **STILL OPEN**. `ucfirst(strtolower($seg))` mengubah `userProfile` → `Userprofile`. **Fix:** validasi + normalisasi seragam (tanpa `strtolower`).
- ✅ **M — case 0 hardcode `Welcome/Welcome/index`** — **RESOLVED**. Sekarang pakai `Index` controller fallback.
- 🔵 **m — `method_exists` sebelum `ReflectionMethod`** (baris 147, 239) — **Info**. Sekarang di luar `try`, aman. Tidak perlu ubah.
- 🔵 **i — `tryDispatchNoRecurse` duplikat ~80% `tryDispatch`** (baris 237–259) — **STILL OPEN**. Risiko drift. **Fix:** ekstrak helper `invokeMethod()`.
- 🔵 **i — class `final` tapi tanpa private constructor** — **Info**. Masih bisa di-`new`. Opsional: tutup.

### 2.2 `system/Core/Runner.core.php`

- 🟠 **M1 — `initRegistry()` mati** (baris 41–46) — **STILL OPEN**. Method kosong + tercomment; tak pernah dipanggil. **Fix:** hapus.
- 🟠 **M2 — `shouldSkipRouting` cek path yang tak akan muncul** (baris 156) — **STILL OPEN**. `in_array($path, ['wp-login.php','wp-signup.php','wp-activate.php'])` tak pernah match. **Keputusan (QA):** *tambahkan* filter `['login','signup','activate']` (tanpa `.php`), bukan menggantikan. Belum dieksekusi.
- 🟠 **M3 — `add_action('template_redirect', fn(){exit;})` per request** (baris 130) — **STILL OPEN**. Redundansi. **Fix:** hapus; biarkan view/controller mengurus exit.
- 🟠 **M4 — `uninstall()` memanggil `flush_rewrite_rules()`** (baris 99) — **STILL OPEN**. Potensi fatal di konteks uninstall. **Keputusan (QA):** hapus. Belum dieksekusi.
- 🟡 **m5 — `activate()` `wp_mkdir_p` tanpa cek return** (baris 85) — **STILL OPEN**. **Fix:** cek return.
- ✅ **m6 — `adminNotices()` escape versi** — **RESOLVED**. Sudah pakai `Registry::get('wp_version')` di baris 178, 180.
- 🔵 **i7 — `handleRouting(\WP $wp, array $extra_query_vars = [])`** (baris 114) — **STILL OPEN**. Param 2 tak pernah diisi WP. **Fix:** hapus.
- 🔵 **i8 — `register_uninstall_hook` dengan `static::uninstall`** (baris 68) — **Info**. Bekerja, tapi tidak standar.
- 🔵 **i9 — priority `parse_request` = 10** (baris 71) — **Info**. Setara dengan core; urutannya registration order.
- 🔵 **i10 — method publik tanpa `@since`** — **Info**. Konsisten dengan file lain, opsional.

### 2.3 `system/Helpers/*` (post-cleanup)

- ✅ **C3 — `extract($data)` di `view()`** — **RESOLVED**. `view()` sekarang di `view.helper.php` pakai `extract($scope, EXTR_SKIP)`.
- ✅ **M — Duplikasi `dd()`/`dump()` dengan `.supports`** — **RESOLVED**. Helper `debug.helper.php` dihapus; `dd`/`dump` tak terpakai.
- ✅ **i — helper global mem-pollute namespace global** — **RESOLVED**. `dd()`/`dump()` dihapus. `config()` & `asset_*()` **dipertahankan** (diperlukan untuk config & asset enqueue).
- 🟡 **m — `config()` vs `get_config()` mirip** — **STILL OPEN (keputusan QA: konsolidasikan)**. `get_config('X')` di `.supports` (read file config whole, pre-helper) dan `config($key,$d,$file)` di helper (dot-notation, runtime) mirip. Keputusan: `get_config` menyerap dot-notation, hapus `config.helper.php`. **Belum dieksekusi** — lihat PLAN C4.

### 2.4 `modules/Login/Controllers/Login.controller.php`

- ✅ **M — `include` bypass `view()`** — **RESOLVED**. `loginWithError()` sekarang pakai `view('login', ['error' => $error])`.
- 🟠 **M — password tidak di-sanitize** (baris 49) — **STILL OPEN (keputusan QA: pakai `filter_input`)**. `$_POST['password'] ?? ''` tanpa `filter_input`. **Fix:** `filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW)`. **Belum dieksekusi** — lihat PLAN S1.
- ✅ **m — hardcode redirect `/dashboard`** (baris 68) — **RESOLVED** commit `eda01cd`. Sekarang redirect ke `/auth` (gateway module, `PrivateController`), lalu ke `/dashboard` bila valid. URL dari `config('auth.*')`.
- 🔵 **i — seluruh method static** — **Info**. Konsisten dengan Welcome, tapi batasan arsitektur.
- 🔵 **i — `declare(strict_types)`** — **OK** di baris 1.

### 2.5 `modules/Welcome/Controllers/{Index,Welcome}.controller.php`

- ✅ **M — `Welcome.controller.php` kemungkinan mati** — **RESOLVED** commit `eda01cd`. `/welcome` → `Welcome::index` (Router case-1: `controller = $module`). `Index.controller.php` dihapus karena redundan.
- 🟡 **m — `...$args` tak terpakai di view** — **STILL OPEN** di `home.view.php`, `door.view.php`.
- 🔵 **i — semua static** — **Info**.

### 2.6 `modules/Welcome/Views/index.view.php`

- 🟡 **m — footer link hardcode** `/documentation`, `/github`, `/changelog` (baris 172–174) — **STILL OPEN** tak ada route-nya.
- ✅ **m — font `Inter` referenced tapi tak di-enqueue** — **RESOLVED** commit `eda01cd`. `assets/css/fonts/inter.css` dibuat + `radykfw_asset()` enqueue di `Welcome::index`. (File `.woff2` masih perlu ditambahkan manual.)

### 2.7 `modules/Login/Views/login.view.php`

- 🟡 **m — `$_POST['username']` di view** (baris 132) — **STILL OPEN (keputusan QA: pass via view data)**. Baca superglobal di view. **Fix:** controller pass `['error','username','action_url']`, view pakai `$username` & `$action_url`. **Belum dieksekusi** — lihat PLAN S2.
- 🟡 **m — `action=""`** (baris 127) — **STILL OPEN (keputusan QA: pass via view data)**. **Fix:** `action="<?php echo esc_url($action_url); ?>"`. **Belum dieksekusi** — lihat PLAN S3.
- 🔵 **i — nonce** sudah benar (`wp_nonce_field` + `wp_verify_nonce`). **OK**.

### 2.8 `composer.json`

- 🟠 **M — dev deps (`phpunit`, `php_codesniffer`, `phpstan`) tapi tak ada `tests/`**
  `composer test/cs/analyse` gagal.
  **Fix:** buat `tests/` minimal, atau hapus script/dev-deps sampai siap.
- 🔵 **i — `autoload.files` memuat `utilities.helper.php`** — helper global, bukan PSR-4; bekerja tapi tidak lazim.
- ✅ **i — Tidak ada runtime dependensi** — sesuai syarat "standalone". Bagus.

### 2.9 `.gitignore`

- 🟠 **M — pola `wp-content/plugins/*/vendor/` terlalu luas** (baris 52–53)
  Men-ignore `vendor/` di **semua** plugin, bukan hanya ini.
  **Fix:** sudah ada `vendor/` (baris 7) + `modules/*/vendor/` (baris 56) — hapus dua baris yang terlalu luas.
- 🟡 **m — missing:** `.supports`, `radykfw.php.old.junk`, `*.bak` (sebagian), `*.junk` (sebagian). `*.bak` & `*.junk` sebagian ada, pastikan konsisten.

### 2.10 `.supports` & `.starter` (di-lock, hanya catat)

- 🟡 **m — `is()` sebagai nama fungsi global** — `is` sangat umum di plugin lain; risiko tabrakan global.
  **Catatan:** karena di-lock, hanya direkomendasikan.
- 🔵 **i — `.starter` memakai `require` (bukan `require_once`) untuk `.supports`** — aman karena hanya di-load sekali di boot, tapi rapuh bila di-include ulang.

### 2.11 `system/Contracts/Dispatchable.contract.php`

- ✅ **Clean** — interface `Dispatchable` (sudah di-rename dari `DispatchableContract`), cocok dengan `Router implements Dispatchable`. Nama file == nama interface. Sesuai aturan.

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

### 🟡 Masih Perlu (Fase 3 — Security Polish):
1. `login.view.php` — `$_POST['username']` → pass via view data (baris 132)
2. `login.view.php` — `action=""` → eksplisit (baris 127)
3. `Login.controller.php` — hardcode redirect `/dashboard` (baris 68)

### 🔵 Masih Perlu (Fase 4 — Config & Assets):
1. Buat `system/Config/app.conf.php`
2. Enqueue font `Inter` atau hapus referensi
3. Hapus footer link hardcode `/documentation`, `/github`, `/changelog`

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

## 7. Data Layer — Planned (Poin 7–9 dari Instruksi)

> Catatan: Poin 7–9 dari instruksi adalah **desain/planned** — belum ada implementasi kode. Berikut rangkuman keputusan arsitektur & pertimbangan untuk implementasi nanti.

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
