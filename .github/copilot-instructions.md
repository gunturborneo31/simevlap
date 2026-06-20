# Copilot Instructions – Laravel Web App (Indonesia)

Proyek ini adalah aplikasi web berbasis Laravel (PHP) dengan UI tradisional (Blade + Bootstrap) atau Livewire.  
Gunakan instruksi ini sebagai aturan dasar untuk setiap kode yang dibuat atau disarankan oleh Copilot.

---

## 1. Arsitektur & struktur proyek

- Aplikasi ini adalah Laravel murni (PHP), tidak hybrid framework lain.  
- Gunakan pola:  
  - Controller untuk request–response.  
  - Service / Action untuk logika bisnis.  
  - Repository hanya jika sangat diperlukan (hindari abstraksi berlebihan).  
- Kelompokkan kode per domain/modul:  
  - Contoh: `app/Orders`, `app/Users`, `app/Reports`, `app/Billing`.  
- Nama file & class:  
  - PascalCase: `UserController`, `OrderService`, `CreateOrderAction`.  
  - snake_case: view `orders.index`, migration `create_orders_table`, route `orders.index`.  
- Nama route:  
  - Gunakan pola resource: `orders.index`, `orders.create`, `orders.store`, `orders.edit`, `orders.update`, `orders.destroy`.  
  - Group route dengan namespace modul: `Route::prefix('orders')`, `Route::group(...)`.

---

## 2. Halaman tabel (data list)

Semua halaman yang menampilkan daftar data (order, user, product, dll.) WAJIB:

### 2.1. Pagination

- Gunakan `->paginate(10)` di controller, tidak pakai `->get()`.  
- Jika data banyak, boleh `->paginate(15)` atau `->paginate(25)`, tidak 50.  
- Tampilkan pagination Bootstrap atau Livewire Table dengan komponen pagination.  
- Pertahankan search/filter saat ganti halaman: `->withQueryString()`.  
- Parameter standar: `?page=2`.

### 2.2. Search global

- Tambahkan input text search di atas tabel.  
- Parameter: `?search=...`.  
- Cari di kolom penting (misal `name`, `email`, `order_code`).  
- Gunakan `where('name', 'like', "%{$search}%")` atau `whereAny()` jika banyak kolom.  

### 2.3. Filter

- Minimal satu filter:  
  - Status: dropdown `active`, `inactive`, `pending`, `completed`, `cancelled`.  
  - Tanggal: `start_date` dan `end_date`.  
  - Kategori / relasi: `user_id`, `category_id`.  
- Parameter: `?status=active`, `?start_date=2026-01-01&end_date=2026-04-15`.  
- Tambahkan tombol "Reset Filter" yang menghapus semua parameter kecuali `page`.  
- Gunakan satu trait atau helper `canFilter()` jika memungkinkan, jangan duplikasi logika filter di tiap controller.  

### 2.4. Sort

- Header kolom yang bisa di‑sort tambahkan ikon panah (↑ ↓).  
- Parameter: `?sort=created_at&direction=desc`.  
- Default sort: `created_at` terbaru (`desc`).  
- Gunakan satu trait / helper `canSort()` di query builder.  
- Kolom yang boleh di‑sort: `name`, `email`, `created_at`, `updated_at`, `total`, `status`.  

### 2.5. UX tabel & state handling

- Tabel pakai Bootstrap `table table-striped table-hover`.  
- Kolom penting (nama, kode, status, total) di depan, kolom minor di belakang.  
- Responsive:  
  - Desktop: tabel normal.  
  - Mobile: `table-responsive` atau convert ke card list.  
- Empty state (data kosong):  
  - Tampilkan: "Tidak ada data yang ditemukan."  
  - Tambahkan tombol "Reset Filter" jika filter sedang aktif.  
- Loading state (Livewire/SPA):  
  - Placeholder baris tabel atau skeleton loading.  
- Konversi ID ke label:  
  - Jangan tampilkan ID mentah (misal user_id=123).  
  - Gunakan relasi atau service: `->with('user')` lalu tampilkan `$user->name`.  

---

## 3. Format data: uang, angka, tanggal

### 3.1. Format mata uang (IDR)

- Di database:  
  - Field `price`, `total`, `amount`, `fee`, `discount_amount` → **integer** satuan rupiah (1500000 = 1.500.000,00).  
- Di tampilan (Blade/frontend):  
  - Format: pemisah ribuan titik, desimal koma, 2 angka (`1.500.000,00`).  
  - WAJIB pakai helper:

```php
function rupiah($value): string
{
    return number_format($value, 0, ',', '.');
}
```

  - Penggunaan: `{{ rupiah($order->total) }}`.  
  - Tidak pernah tampilkan angka mentah tanpa format.  

### 3.2. Format angka ribuan (non‑uang)

- Untuk `quantity`, `stock`, `total_items`:  
  - Format: pemisah ribuan titik, tanpa desimal (`1.250`).  
  - Helper:

```php
function ribuan($value): string
{
    return number_format($value, 0, ',', '.');
}
```

### 3.3. Format tanggal

- Semua operasi tanggal pakai `Carbon`.  
- Format tampilan Indonesia:  
  - `d/m/Y` (15/04/2026).  
  - `d M Y` (15 Apr 2026).  
- Helper:

```php
function formatTanggal($date, $format = 'd/m/Y'): string
{
    return $date->format($format);
}
```

- Kolom `created_at`, `updated_at`, `order_date` WAJIB diformat sebelum tampil.  

## 4. Form, validasi, dan UI form

### 4.1. Struktur form dasar

- Semua form WAJIB:  
  - `@csrf`.  
  - Jika edit: `@method('PUT')` atau `@method('PATCH')`.  
- Layout:  
  - Gunakan `form-group` atau grid rapi (Bootstrap `row`/`col`).  
  - Label di atas input, rata kiri.  
  - Input lebar penuh (`100%`).  
- Field wajib:  
  - Tambahkan tanda `*` atau keterangan "wajib diisi".  

### 4.2. Validasi

- Gunakan **FormRequest** untuk semua form:  
  - `CreateOrderRequest`, `UpdateUserRequest`, `StoreProductRequest`.  
- Aturan umum:  
  - `required` untuk field wajib.  
  - `nullable` hanya untuk opsional.  
  - `integer`, `string`, `email`, `date` sesuai tipe.  
  - `unique` untuk field unik (username, email, kode).  
  - `exists` untuk relasi (`user_id`, `role_id`, `category_id`).  
- Pesan error:  
  - Bahasa Indonesia, singkat, jelas.  
  - Contoh: `Nama wajib diisi.`, `Email harus valid.`, `Stok harus berupa angka.`  

### 4.3. Error handling di UI

- Input error:  
  - Tambah `is-invalid` (Bootstrap) atau class sejenis.  
  - Pesan error di bawah input dengan `@error`.  
- Pertahankan data input:  
  - Gunakan `old()`:

```blade
<input type="text" name="name" value="{{ old('name') }}">
```

- Untuk form panjang, grup field dengan `card` atau `fieldset`, letakkan error di bawah tiap grup.  

### 4.4. Button & aksi

- Label:  
  - `Simpan` (bukan `Save`).  
  - `Kembali` (bukan `Back`).  
  - `Hapus` (bukan `Delete`).  
- Style:  
  - Utama: `primary` (misal `Simpan`).  
  - Secondary: `secondary` (misal `Kembali`).  
  - Hapus: `danger`.  
- Hapus data:  
  - WAJIB pakai modal konfirmasi.  
  - Tidak boleh langsung delete tanpa konfirmasi.  

---

## 5. Notifikasi ke user (flash messages)

- Gunakan session flash:  
  - `session('success')` → sukses.  
  - `session('error')` → gagal.  
  - `session('warning')` → peringatan.  
- Tampilkan:  
  - Di atas content utama, tidak modal random.  
  - Contoh:  
    - `Sukses: Data berhasil disimpan.`  
    - `Error: Gagal menyimpan data.`  
- Hanya untuk aksi utama, bukan klik minor.  

## 6. Breadcrumbs & navigasi

### 6.1. Breadcrumbs

- Semua halaman (kecuali login, error 404/500) WAJIB punya breadcrumbs.  
- Struktur:  
  - `Home > Modul > Sub‑halaman`.  
  - Contoh: `Home > Penjualan > Pesanan`, `Home > Pengguna > Daftar Pengguna`.  
- `Home` → link ke dashboard.  
- Modul utama → link ke index modul.  
- Halaman aktif → tidak link, gunakan `class="active"`.  
- Gunakan named route, jangan hardcode URL.  

### 6.2. Sidebar / menu navigasi

- Sidebar kiri:  
  - Urutan: `Dashboard`, `Penjualan`, `Pengguna`, `Laporan`, `Pengaturan`, dll.  
- Submenu:  
  - Hanya muncul jika ada sub‑halaman.  
  - Contoh: `Penjualan` → `Pesanan`, `Retur`, `Laporan Penjualan`.  
- Aktifkan menu:  
  - Tambah `class="active"` jika route aktif.  
  - Gunakan `request()->routeIs('orders.*')` atau setara.  
- Icon: boleh tambahkan, asalkan konsisten (gunakan satu set icon).  

---

## 7. Responsif & aksesibilitas

### 7.1. Responsif

- Mobile‑friendly:  
  - Ukuran font legible, tidak terlalu kecil.  
  - Button/input cukup besar untuk tap.  
- Tabel:  
  - `table-responsive` di mobile.  
  - Atau konversi ke card list jika data sangat kompleks.  
- Layout:  
  - Desktop: multi‑kolom.  
  - Mobile: `col-12` untuk content utama.  

### 7.2. Aksesibilitas

- `label` untuk semua input, terhubung `for` dan `id`.  
- Field wajib: tanda `*` atau "wajib diisi".  
- Gunakan `aria‑label` jika perlu (misal tombol tanpa teks).  
- Hindari warna saja sebagai indikator error/warning (selalu tambah teks).  

## 8. Kinerja

- Gunakan `->paginate()` di semua halaman data, jangan `->get()` untuk data besar.  
- Gunakan `->with()` untuk relasi penting di index, hindari `N+1` (cek dengan debugbar/`dump($query->toSql())`).  
- Gunakan `->select()` di query untuk kolom yang benar‑benar dipakai di tabel.  
- Cache data jarang berubah dengan `Cache::remember()`:

```php
$countries = Cache::remember('countries', 3600, function () {
    return Country::pluck('name', 'id');
});
```

- Gunakan `whereHas()` / `whereDoesntHave()` untuk filter relasi, bukan `whereIn` jika tidak diperlukan.  

---

## 9. Keamanan

- Gunakan `FormRequest` untuk validasi, jangan `request->all()` langsung ke model.  
- Gunakan `fillable` di model, jangan `protected $guarded = []`.  
- Aktifkan middleware `TrimStrings` untuk trim semua input.  
- Gunakan `https` di environment production.  
- Middleware akses:  
  - `auth` untuk semua halaman terlindungi.  
  - `role:admin`, `permission:manage_orders`, dll. sesuai kebutuhan.  
- Gunakan `softDeletes` jika diperlukan, jangan hapus permanen tanpa prosedur jelas.  
- Hindari `whereRaw()` untuk filter dasar; gunakan query builder standar.  

---

## 10. Aturan kode yang tidak boleh dibuat

Tidak boleh:
- Taruh logika bisnis di controller; pindahkan ke `Service` atau `Action`.  
- Gunakan `request->all()` langsung di `create()`/`update()` model.  
- Gunakan raw query panjang di controller; letakkan di model atau service.  
- Gunakan `disable_csrf_token()` tanpa alasan spesifik dan review.  
- Gunakan `whereRaw()` hanya untuk fitur dasar; gunakan `where`, `whereHas`, `whereIn` jika memungkinkan.  
- Simpan harga/amount sebagai string di database (`1.500.000,00`); selalu simpan sebagai integer.  

---

## 11. Aturan khusus untuk AI / Copilot

- Untuk setiap halaman data:  
  - Otomatis tambahkan pagination, search, minimal satu filter, sort kolom.  
- Untuk setiap harga/amount:  
  - Gunakan helper `rupiah($value)` di Blade, bukan angka mentah.  
- Untuk setiap form kompleks:  
  - Sarankan FormRequest terpisah (`CreateOrderRequest`, `UpdateUserRequest`).  
- Untuk setiap tabel:  
  - Sarankan `withQueryString()` dan `table-responsive` untuk mobile.  

## 12. Gaya UI: Rounded

- Gunakan komponen dengan sudut membulat (rounded).  
- Contoh kelas yang boleh dipakai (misal Tailwind atau Bootstrap dengan utility class):  
  - `rounded` / `rounded-lg` / `rounded-xl`.  
  - `shadow-sm` / `shadow-md` untuk card.  
- Tabel:  
  - `overflow-hidden` + `rounded-lg` pada wrapper tabel.  
- Card:  
  - `rounded-lg` pada card (.card, .card-body, .card-header).  
- Button:  
  - `rounded-lg` atau `rounded-full` pada tombol utama.  
- Input:  
  - `rounded-lg` pada `.form-control`.  

- Hindari:  
  - Sudut sangat tajam (`rounded-0`).  
  - Gabungan `shadow-lg` di semua komponen; gunakan shadow hanya di card dan card utama.  
- Untuk warna:  
  - Gunakan palet warna yang konsisten dengan brand/app.  
  - Jangan ubah nama warna (misal: `primary`, `secondary`, `danger` tetap), hanya style (rounded + shadow) yang ditambahkan.  

  ## 13. Naming & konvensi nama

- Nama model:  
  - Singular, PascalCase: `User`, `Order`, `Product`.  
- Nama controller:  
  - Singular: `UserController`, `OrderController`.  
- Nama route:  
  - plural dalam URL: `orders.index`, `orders.create`, `users.index`.  
- Nama file:  
  - PascalCase untuk class, snake_case untuk migration/view.  
- Nama variable:  
  - Gunakan nama deskriptif: `$activeOrders`, `$selectedUsers`.  
  - Hindari singkatan bertele‑tele: `$usr`, `$dt`.  

- Nama field di form:  
  - sama dengan atribut model: `name`, `email`, `status`, `order_date`.  
- Jangan ulang konteks:  
  - Misal: `$user->name()` bukan `$user->getUserName()`.  

  ## 14. Localization Indonesia

- Default bahasa: `id` (Indonesia).  
- Set di `config/app.php`:

```php
'locale' => 'id',
'fallback_locale' => 'id',
'faker_locale' => 'id_ID',
'timezone' => 'Asia/Jakarta',
```

- Semua label, pesan notifikasi, dan error harus ditulis dalam bahasa Indonesia yang jelas dan ringkas.  
- Gunakan `resources/lang/id/` untuk file translation:  
  - `messages.php` untuk pesan umum.  
  - `validation.php` untuk pesan validasi.  
- Copilot harus:  
  - Mengusulkan pesan error Indonesia, bukan Inggris.  
  - Menggunakan `trans('messages.saved')` atau `trans('validation.required')` jika memungkinkan.  

  ## 15. Logging

- Gunakan Laravel `Log` untuk:  
  - `debug` hanya di development.  
  - `info` untuk aksi penting (user login, transaksi dibuat, dsb.).  
  - `warning` untuk masalah yang perlu diperhatikan.  
  - `error` untuk kegagalan aksi.  
  - `critical`/`emergency` untuk error kritikal.  
- Gunakan konteks (context array) ketika log:

```php
Log::info('User login berhasil.', ['user_id' => $user->id, 'ip' => request()->ip()]);
```

- Untuk aksi kritikal (hapus data, ubah saldo, ubah hak akses), selalu ada log.  
- Copilot harus:  
  - Mengusulkan `Log::` ketika aksi penting muncul.  
  - Tidak pakai `dd()`/`dump()` di production‑code; ganti `Log::debug()` atau `Log::info()`.  

  ## 16. Upload file & image

- Semua upload file:  
  - Simpan di `storage/app/public/uploads` atau folder khusus.  
  - Jangan langsung simpan ke `public/` tanpa kontrol.  
- Gunakan `request()->file('image')->move()` atau `request()->file('image')->store()`.  
- Atur validasi:  
  - `nullable` untuk foto opsional.  
  - `mimes:jpeg,jpg,png` untuk gambar.  
  - `max:2048` untuk ukuran file (2 MB).  
- Untuk gambar di website, gunakan `Storage::url()` untuk link public.  
- Copilot harus:  
  - Mengusulkan validasi `mimes` dan `max` ketika ada upload file.  
  - Tidak mengusulkan simpan file ke `public` folder tanpa `Storage` abstraction.  

  ## 17. Aturan khusus untuk AI / Copilot (tambahan)

- Jika user (saya) menulis komentar `// TODO: buat halaman daftar ...`,  
  - Copilot harus:  
    - Langsung mengusulkan controller index, route, dan view blade dengan pagination, search, filter.  
    - Menambahkan helper `rupiah()` untuk kolom harga.  
    - Menambahkan validasi di `FormRequest` untuk semua input form.  

- Ketika saya sebut kata:  
  - `tabel` → pastikan ada `paginate()`, `search`, `filter`, `sort`.  
  - `uang` atau `harga` → gunakan `rupiah()` di Blade.  
  - `filter` → pastikan ada parameter `?status=...` atau `?start_date=...&end_date=...`.  
  - `rounded` → tambahkan class `rounded-lg` / `rounded` dan `shadow-sm` di card/button/tabel wrapper.  

- Jika ada paket yang sudah diinstall (misal Livewire Tables, DataTable, Backpack, dsb.):  
  - Ikuti pola dan naming paket tersebut, tidak membuat pola serupa di tempat lain.  
  - Contoh: Kalau pakai Livewire Tables, usulkan komponen Livewire, bukan controller‑style pagination biasa lagi.  

- Hindari:  
  - Duplikasi kode (misal kopas trait/filter di tiap controller).  
  - Hard‑kode SQL di controller.  
  - Langsung `request->all()` ke `create()`/`update()`.  