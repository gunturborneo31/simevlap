# FITUR MANAJEMEN DOKUMEN PERENCANAAN DAERAH

Buat fitur manajemen dokumen perencanaan daerah dengan role OPD dan Super Admin.

## Tujuan

Fitur ini digunakan untuk mengumpulkan dan memonitor dokumen:

* Renstra (5 tahunan)
* Renja (tahunan)
* DPA (tahunan)

Dokumen harus tersusun berdasarkan hubungan:

Renstra (5 Tahun)
→ Renja (Tahunan)
→ DPA (Tahunan)

Contoh:

Renstra 2025-2029
├── Tahun 2025
│   ├── Renja 2025
│   └── DPA 2025
├── Tahun 2026
│   ├── Renja 2026
│   └── DPA 2026
├── Tahun 2027
│   ├── Renja 2027
│   └── DPA 2027
├── Tahun 2028
│   ├── Renja 2028
│   └── DPA 2028
└── Tahun 2029
├── Renja 2029
└── DPA 2029

---

# ROLE OPD

OPD hanya dapat melihat dan mengelola dokumen miliknya sendiri.

## Halaman Daftar Renstra

Tampilkan tabel:

| Periode Renstra | Nomor Dokumen | Status | Kelengkapan | Aksi |

Keterangan:

* Status = Aktif / Tidak Aktif
* Kelengkapan = jumlah dokumen yang sudah diunggah dibanding total dokumen yang wajib tersedia
* Aksi:

  * Detail
  * Edit
  * Hapus (opsional)

Tambahkan tombol:

* Tambah Periode Renstra

---

## Form Tambah Renstra

Field:

* Periode Awal
* Periode Akhir
* Nomor Dokumen
* Tanggal Dokumen
* Upload File PDF
* Keterangan

Validasi:

* PDF only
* Maksimal ukuran file sesuai konfigurasi sistem

---

## Halaman Detail Renstra

Contoh:

Renstra 2025-2029

Informasi Renstra:

* Periode
* Nomor Dokumen
* Tanggal
* File Renstra

Di bawahnya tampil tabel tahunan:

| Tahun | Renja | DPA | Status | Aksi |

Status dihitung otomatis:

* Lengkap = Renja dan DPA tersedia
* Belum Lengkap = salah satu belum tersedia

Kolom Renja dan DPA menampilkan:

* Badge Hijau = Sudah Upload
* Badge Merah = Belum Upload

Aksi:

* Kelola Tahun

---

## Halaman Kelola Tahun

Contoh Tahun 2025

Tampilkan tabel:

| Jenis Dokumen | Nomor Dokumen | Tanggal | File | Status | Aksi |

Jenis dokumen:

* Renja
* DPA

Aksi:

* Upload
* Edit
* Hapus
* Preview PDF
* Download

Tambahkan tombol:

* Upload Renja
* Upload DPA

---

# ROLE SUPER ADMIN

Super Admin dapat melihat seluruh OPD.

Tujuan halaman ini adalah monitoring kelengkapan dokumen seluruh OPD.

## Dashboard Rekap Dokumen

Tampilkan tabel monitoring dengan struktur header bertingkat.

Header:

| OPD | Renstra | 2025  | 2025 | 2026  | 2026 | 2027  | 2027 | 2028  | 2028 | 2029  | 2029 |
| --- | ------- | ----- | ---- | ----- | ---- | ----- | ---- | ----- | ---- | ----- | ---- |
|     |         | Renja | DPA  | Renja | DPA  | Renja | DPA  | Renja | DPA  | Renja | DPA  |

Contoh data:

| OPD      | Renstra | Renja 2025 | DPA 2025 | Renja 2026 | DPA 2026 | Renja 2027 | DPA 2027 | Renja 2028 | DPA 2028 | Renja 2029 | DPA 2029 |
| -------- | ------- | ---------- | -------- | ---------- | -------- | ---------- | -------- | ---------- | -------- | ---------- | -------- |
| Bappeda  | Ya      | Ya         | Ya       | Ya         | Ya       | Ya         | Tidak    | Tidak      | Tidak    | Tidak      | Tidak    |
| Dinas PU | Ya      | Ya         | Ya       | Ya         | Ya       | Ya         | Ya       | Ya         | Ya       | Ya         | Ya       |

Gunakan badge status:

* Hijau = Sudah Upload
* Merah = Belum Upload
* Kuning = Menunggu Verifikasi (jika fitur verifikasi diaktifkan)

---

## Filter Dashboard

Sediakan filter:

* Periode Renstra
* OPD
* Status Kelengkapan

Contoh:

[Periode 2025-2029]
[Semua OPD]
[Semua Status]

---

## Kartu Statistik Dashboard

Tampilkan ringkasan:

* Total OPD
* OPD Lengkap
* OPD Belum Lengkap
* Total Dokumen Renstra
* Total Dokumen Renja
* Total Dokumen DPA

Gunakan card modern dengan icon dan warna yang konsisten.

---

# DESAIN UI

Gunakan desain modern pemerintahan:

* Clean
* Minimalis
* Responsive
* Rounded corner
* Soft shadow
* Warna utama biru muda
* Secondary kuning
* Tabel dengan sticky header
* Support dark mode
* Search realtime
* Pagination
* Export Excel
* Export PDF

Gunakan layout profesional seperti dashboard monitoring pemerintah daerah dan sistem e-Monev modern.
