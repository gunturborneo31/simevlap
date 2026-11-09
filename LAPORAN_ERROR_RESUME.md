# LAPORAN PENGECEKAN MENU RESUME
## SIMEVLAP 2.0

**Tanggal pengecekan:** 18 Oktober 2026
**Cakupan:** Semua menu, sub-tabel, dan tombol export di menu Resume

---

## 1. RINGKASAN

| Kategori | Jumlah temuan |
|---|---|
| A. Tombol Download/Export gagal (error 500/404) | 3 |
| B. Tabel kosong / tidak tampil dengan benar | 5 |
| C. Error di tampilan (frontend) | 3 |
| D. Halaman sangat lambat | 4 |
| E. Catatan tambahan | 4 |
| **Total** | **19** |

**Kesimpulan singkat:**
- Semua **halaman tabel bisa dibuka** (tidak ada halaman yang crash).
- Masalah utama ada di **tombol Download** (7 tabel gagal), **tabel yang kosong**, dan **halaman yang sangat lambat**.
- Hasil sama untuk role **superadmin**, **tamu**, dan **OPD**.

---

## 2. CARA PENGECEKAN

1. Setiap URL menu Resume (59 URL) dijalankan langsung melalui Laravel dengan database MySQL lokal.
2. Dijalankan sebagai 3 jenis user: `superadmin` (id 1), `tamu` (id 51), dan user OPD (id 37).
3. Untuk setiap URL dicatat: status HTTP, pesan error, jumlah baris data, dan waktu proses.
4. Kode `ResumeController.php` dan `TableView.vue` diperiksa untuk menemukan penyebabnya.

---

## 3. DAFTAR ERROR

### A. TOMBOL DOWNLOAD / EXPORT GAGAL

#### A1. Konsistensi RPJMD–RKPD → Tabel 4 → Download PDF/Excel
- **Gejala:** Error 500 — `Undefined array key "program_name"`
- **Penyebab:** Fungsi `getAlignedAnggaranRows()` (`app/Http/Controllers/ResumeController.php` baris ±2841) menghasilkan key **camelCase** (`programName`, `rpjmdPagu`, `statusRpjmdRenstra`, …), sedangkan template `resources/views/exports/resume_tabel_4.blade.php` baris 91 membaca key **snake_case** (`program_name`, `rpjmd_pagu`, `status_rpjmd_renstra`, …).
- **Saran perbaikan:** Samakan nama key — ubah template blade agar memakai camelCase, atau ubah output fungsi menjadi snake_case.
- **Tingkat:** 🔴 Tinggi

#### A2. Konsistensi RKPD–APBD → Tabel 4, 5, 6, 7 → Download PDF/Excel
- **Gejala:** Error 500 — sama seperti A1.
- **Penyebab:** Sama seperti A1. Selain itu, di `ResumeController::export()` (baris ±2244) tabel 4–7 RKPD–APBD dipaksa memakai template `resume_tabel_4` yang kolomnya untuk **RPJMD / Renstra / RKPD**, bukan RKPD / APBD. Jadi meski error key diperbaiki, isi PDF akan **salah konteks**.
- **Saran perbaikan:** Perbaiki key (A1), lalu buat/gunakan template export yang sesuai untuk RKPD–APBD tabel 4–7.
- **Tingkat:** 🔴 Tinggi

#### A3. Konsistensi RKPD–APBD → Tabel 8 dan 9 → Download
- **Gejala:** Error 404 (halaman tidak ditemukan).
- **Penyebab:** Tombol Download tetap ditampilkan (`resources/js/Pages/Resume/TableView.vue` baris ±3358), tetapi `tabel-8` dan `tabel-9` tidak ada di daftar `$allowedTables` di `ResumeController::export()` (baris ±2193).
- **Saran perbaikan:** Tambahkan dukungan export untuk tabel 8 & 9, **atau** sembunyikan tombol Download untuk kedua tabel tersebut.
- **Tingkat:** 🔴 Tinggi

---

### B. TABEL KOSONG / TIDAK TAMPIL DENGAN BENAR

#### B1. Hasil Pelaksanaan RKPD → Tabel 1 → selalu kosong (0 baris)
- **Penyebab:** **Masalah data, bukan kode.** Tabel `indikatorables` masih menunjuk ke Tujuan id **1–4** dan Sasaran id **1–12**, sedangkan data di database sekarang Tujuan id **5–8** dan Sasaran id **13–24** (kemungkinan akibat seeder dijalankan ulang). Relasi polymorphic tidak punya foreign key sehingga data "yatim" tidak terdeteksi.
- **Saran perbaikan:** Perbarui `indicatorable_id` di `indikatorables` agar menunjuk ke id Tujuan/Sasaran yang benar, atau jalankan ulang seeder indikator Tujuan/Sasaran (`CreateIndikatorForTujuanSeeder`, `CreateIndikatorForSasaranSeeder`).
- **Tingkat:** 🔴 Tinggi

#### B2. Hasil Pelaksanaan RKPD → Tabel 4 dan Tabel 6 → kosong
- **Penyebab:** Tidak ada logika pengambilan data untuk kedua tabel ini di `ResumeController::index()`. Tampilannya sudah ada di frontend, tetapi backend tidak mengirim data.
- **Saran perbaikan:** Buat query data untuk tabel 4 dan 6, atau sembunyikan kartu menu sampai fiturnya siap.
- **Tingkat:** 🟠 Sedang

#### B3. Hasil Pelaksanaan RKPD → Tabel 5 dan Tabel 8 → layout khusus tidak pernah tampil
- **Gejala:** Yang tampil justru tabel umum yang sangat lebar (2600px), bukan layout khusus tabel 5 / tabel 8.
- **Penyebab:** Di `TableView.vue` baris **671** (tabel 8) dan **712** (tabel 5), blok tampilannya **terletak di dalam** blok `<div v-else-if="currentView === 'konsistensi-rkpd-apbd' ...">` (baris 435–807). Karena view-nya `hasil-pelaksanaan-rkpd`, blok tersebut tidak akan pernah dirender.
- **Saran perbaikan:** Pindahkan kedua blok keluar dari blok `konsistensi-rkpd-apbd` dan jadikan bagian dari rantai `v-else-if` utama.
- **Tingkat:** 🟠 Sedang

#### B4. Hasil Pelaksanaan RKPD → Tabel 5 → berisiko menampilkan DATA PALSU
- **Gejala:** Jika data asli kosong, tabel menampilkan "Program Contoh A" dan "Program Contoh B" dengan angka karangan.
- **Penyebab:** Ada "fallback sintetis sementara" di `ResumeController.php` baris ±244.
- **Catatan:** Saat dicek, data asli ada (562 baris) sehingga data palsu belum muncul — tetapi akan muncul untuk tahun/filter yang datanya kosong.
- **Saran perbaikan:** Hapus blok fallback tersebut.
- **Tingkat:** 🔴 Tinggi (dapat menyesatkan pimpinan)

#### B5. Realisasi → RPJMD, RKPD, APBD, RENSTRA, RENJA, DPA → halaman kosong
- **Gejala:** 6 kartu menu bisa diklik, tetapi halaman kosong tanpa pesan apa pun.
- **Penyebab:** Menu didefinisikan di `resources/js/Pages/Resume/Index.vue` baris 164–169, tetapi tidak ada logika backend maupun template tampilannya.
- **Saran perbaikan:** Sembunyikan keenam kartu sampai fiturnya dibuat, atau tampilkan pesan "Fitur dalam pengembangan".
- **Tingkat:** 🟠 Sedang

---

### C. ERROR DI TAMPILAN (FRONTEND)

#### C1. Dropdown "OPD" di halaman daftar tabel Resume tidak berfungsi
- **Gejala:** Saat memilih OPD, halaman tidak berpindah; muncul error JavaScript di console browser.
- **Penyebab:** `resources/js/Pages/Resume/Index.vue` baris 321 memakai `page.props.value.selectedYear`. `usePage().props` bukan `ref`, sehingga `.value` bernilai `undefined`.
- **Saran perbaikan:** Ganti menjadi `page.props.selectedYear ?? page.props.year ?? null`.
- **Tingkat:** 🟠 Sedang

#### C2. Export Excel versi server selalu gagal (CSRF 419)
- **Gejala:** Tidak terlihat langsung oleh user — export otomatis beralih ke cara cadangan (SheetJS dari CDN). Jika CDN tidak bisa diakses, export gagal total.
- **Penyebab:** `resources/views/app.blade.php` tidak memiliki tag `<meta name="csrf-token">`, padahal `TableView.vue` baris ±2706 membaca token dari tag tersebut untuk POST ke `/resume/export-server`.
- **Saran perbaikan:** Tambahkan `<meta name="csrf-token" content="{{ csrf_token() }}">` di `<head>` file `app.blade.php`.
- **Tingkat:** 🟡 Rendah–Sedang

#### C3. Banner "Debug" terlihat oleh semua user
- **Gejala:** Kotak kuning bertuliskan `Debug: view=… — table=… — rows=… — activeBranch=…` muncul di setiap tabel Resume, termasuk untuk akun tamu/pimpinan. Tabel 8 juga menampilkan judul "DEBUG: Visual-only tabel-8".
- **Penyebab:** Sisa kode debug di `TableView.vue` baris 9–11 dan baris 673.
- **Saran perbaikan:** Hapus banner debug tersebut (dan `console.debug` di baris ±1894).
- **Tingkat:** 🟡 Rendah (tampilan tidak profesional)

---

### D. HALAMAN SANGAT LAMBAT

| No | Menu | Waktu proses | Risiko |
|---|---|---|---|
| D1 | Kertas Kerja → **Download Semua OPD** (Excel) | ± 40 detik | Timeout di server produksi |
| D2 | Realisasi → **REALISASI** (dpa-readonly) | ± 40 detik | Timeout / user mengira error |
| D3 | Hasil Pelaksanaan RKPD → **Tabel 7** | ± 39 detik | 2.291 baris dikirim sekaligus tanpa pagination |
| D4 | Hasil Pelaksanaan RKPD → **Tabel 8** | ± 11 detik | Lambat |

- **Saran perbaikan umum:** Tambahkan cache (`Cache::remember`) untuk data yang jarang berubah, kurangi query di dalam loop (N+1), dan pertimbangkan pagination atau filter OPD wajib untuk tabel besar. Untuk "Download Semua OPD", pertimbangkan proses di background (queue).

---

### E. CATATAN TAMBAHAN

| No | Temuan | Dampak |
|---|---|---|
| E1 | **37 dari 46 user OPD tidak memiliki `opd_id`** | Data tidak tersaring per OPD untuk user tersebut; Kertas Kerja menampilkan OPD pertama secara alfabet |
| E2 | Log `storage/logs/laravel.log` dibanjiri pesan `resume.tableData.targetRow` (OPD 20, kode `5.03.01.2.01.0001` di-hardcode) | File log membengkak; sisa debug |
| E3 | Route `/resume*` tidak memakai middleware role | Akun tamu dapat membuka tabel Konsistensi lewat URL langsung walaupun menunya disembunyikan |
| E4 | Route `/__debug/resume-json` dapat diakses **tanpa login** | 🔴 Kebocoran data seluruh OPD — wajib dihapus sebelum produksi |

---

## 4. MENU YANG BERJALAN NORMAL ✅

**Halaman tabel (tampil & berisi data):**
- Konsistensi RPJMD–RKPD: Tabel 1, 2, 3, 4
- Konsistensi RKPD–APBD: Tabel 1 s.d. 10
- Hasil Pelaksanaan RKPD: Tabel 2, 3, 5, 7, 8
- Rekap Permasalahan: Berdasarkan OPD, Sasaran, Bidang Urusan
- Dokumen → Monitoring Dokumen
- Kertas Kerja → Tabel Kertas Kerja
- Realisasi → IKU, IKK, REALISASI
- Lampiran (attachments)

**Export (berhasil diunduh):**
- Konsistensi RPJMD–RKPD: Tabel 1, 2, 3
- Konsistensi RKPD–APBD: Tabel 1, 2, 3, 10
- Realisasi IKU
- Kertas Kerja → Download Semua OPD (berhasil, tetapi lambat)

---

## 5. URUTAN PRIORITAS PERBAIKAN

| Prioritas | Temuan | Perkiraan usaha |
|---|---|---|
| 1 | E4 — Hapus route `/__debug/*` tanpa login | Sangat kecil |
| 2 | B4 — Hapus data palsu tabel 5 | Sangat kecil |
| 3 | A1, A2 — Perbaiki key export tabel 4–7 | Kecil |
| 4 | A3 — Export tabel 8 & 9 (atau sembunyikan tombol) | Kecil |
| 5 | B1 — Perbaiki data indikator Tujuan/Sasaran | Kecil (data) |
| 6 | C1, C2, C3 — Perbaikan frontend | Kecil |
| 7 | B3 — Pindahkan blok tabel 5 & 8 di TableView | Sedang |
| 8 | B2, B5 — Lengkapi atau sembunyikan menu kosong | Sedang |
| 9 | E1 — Lengkapi `opd_id` user OPD | Sedang (data) |
| 10 | D1–D4 — Optimasi performa | Besar |

---

## 6. LAMPIRAN: HASIL UJI PER URL (superadmin)

| URL | Status | Baris data | Waktu |
|---|---|---|---|
| `/resume` | 200 | – | 6,6 dtk* |
| `konsistensi-rpjmd-rkpd` tabel-1 s.d. 4 | 200 | 45 | 0,06–0,4 dtk |
| `konsistensi-rkpd-apbd` tabel-1 s.d. 10 | 200 | 45 | 0,2–2,8 dtk |
| `hasil-pelaksanaan-rkpd` tabel-1 | 200 | **0** | 0,08 dtk |
| `hasil-pelaksanaan-rkpd` tabel-2 | 200 | 10 | 0,1 dtk |
| `hasil-pelaksanaan-rkpd` tabel-3 | 200 | 555 | 0,95 dtk |
| `hasil-pelaksanaan-rkpd` tabel-4 | 200 | **0** | 0,03 dtk |
| `hasil-pelaksanaan-rkpd` tabel-5 | 200 | 562 | 0,5 dtk |
| `hasil-pelaksanaan-rkpd` tabel-6 | 200 | **0** | 0,03 dtk |
| `hasil-pelaksanaan-rkpd` tabel-7 | 200 | 2.291 | **39 dtk** |
| `hasil-pelaksanaan-rkpd` tabel-8 | 200 | 45 | **11 dtk** |
| `rekap-permasalahan` (3 tabel) | 200 | 1.659 | 1,3–2,5 dtk |
| `dokumen` monitoring | 200 | 45 | 0,06 dtk |
| `kertas-kerja` tabel-1 | 200 | 61 | 0,3 dtk |
| `realisasi` iku | 200 | 10 | 0,05 dtk |
| `realisasi` ikk | 200 | 252 | 0,08 dtk |
| `realisasi` rpjmd/rkpd/apbd/renstra/renja/dpa | 200 | **0** | 0,03 dtk |
| `realisasi` dpa-readonly | 200 | – | **40 dtk** |
| `/resume/attachments` | 200 | – | 3,8 dtk |
| Export `rpjmd-rkpd` tabel-1, 2, 3 | 200 (PDF) | – | 1,6–5,8 dtk |
| Export `rpjmd-rkpd` tabel-4 | **500** | – | – |
| Export `rkpd-apbd` tabel-1, 2, 3, 10 | 200 (PDF) | – | 0,8–1,2 dtk |
| Export `rkpd-apbd` tabel-4, 5, 6, 7 | **500** | – | – |
| Export `rkpd-apbd` tabel-8, 9 | **404** | – | – |
| Export realisasi IKU | 200 (PDF) | – | 0,3 dtk |
| Kertas kerja semua OPD (JSON) | 200 | – | 9,5 dtk |
| Kertas kerja semua OPD (Excel) | 200 | – | **40 dtk** |

\* Waktu `/resume` pertama termasuk proses booting aplikasi.
