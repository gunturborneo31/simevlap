# DOKUMENTASI SIMEVLAP 2.0
## Sistem Monitoring Evaluasi Laporan

---

## 1. KONSEP APLIKASI

### 1.1 Deskripsi Umum
**SIMEVLAP 2.0** adalah platform terintegrasi untuk monitoring, evaluasi, dan pelaporan pembangunan daerah secara digital, cepat, dan transparan. Aplikasi ini dirancang untuk membantu pemerintah daerah dalam:
- Monitoring realisasi fisik & keuangan seluruh OPD
- Evaluasi program, kegiatan, dan sub kegiatan
- Upload & manajemen dokumen perencanaan
- Resume laporan dan analitik capaian
- Integrasi data antar bidang & OPD

### 1.2 Teknologi Stack
**Backend:**
- Laravel Framework 13.0 (PHP 8.3)
- Spatie Laravel Permission 6.0 (Role & Permission Management)
- Laravel Sanctum 4.0 (API Authentication)

**Frontend:**
- Inertia.js 2.0 (Modern Monolith Architecture)
- Vue.js 3.5 (Progressive JavaScript Framework)
- Tailwind CSS 4.0 (Utility-First CSS)
- Ziggy 2.0 (Laravel Route Helper for JavaScript)
- Vue Multiselect 3.0 (Dropdown Component)

**Development Tools:**
- Vite 6.0 (Build Tool)
- Laravel Pint (Code Style Fixer)
- PHPUnit 11.5 (Testing)

---

## 2. STRUKTUR DATABASE

### 2.1 Hierarki Data Perencanaan Pembangunan

Database dirancang mengikuti hierarki perencanaan pembangunan daerah:

```
VISI
  └── MISI
       └── TUJUAN
            └── SASARAN
                 └── STRATEGI
                      └── ARAH KEBIJAKAN
                           └── PROGRAM
                                └── KEGIATAN
                                     └── SUB KEGIATAN
```

### 2.2 Tabel-Tabel Utama

#### A. MASTER DATA

**1. opds (Organisasi Perangkat Daerah)**
```
- id (PK)
- kode (unique, 20 char)
- nama (255 char)
- singkatan (50 char, nullable)
- kepala_opd (255 char, nullable)
- nip_kepala (30 char, nullable)
- is_active (boolean, default: true)
- timestamps
```
**Fungsi:** Menyimpan data OPD/SKPD yang ada di daerah

**2. users**
```
- id (PK)
- opd_id (FK to opds, nullable)
- name
- email (unique)
- password
- timestamps
```
**Fungsi:** Menyimpan data pengguna sistem dengan relasi ke OPD
**Relasi:** Menggunakan Spatie Permission untuk role management (superadmin, user OPD, dll)

**3. kepmen (Keputusan Menteri/Peraturan)**
```
- id (PK)
- kode (unique, 50 char)
- nama (500 char)
- tahun (10 char)
- keterangan (text, nullable)
- timestamps
```
**Fungsi:** Menyimpan referensi peraturan/keputusan yang menjadi dasar program

**4. bidang_urusans**
```
- id (PK)
- kode
- nama
- timestamps
```
**Fungsi:** Menyimpan bidang urusan pemerintahan

**5. urusans**
```
- id (PK)
- bidang_urusan_id (FK)
- kode
- nama
- timestamps
```
**Fungsi:** Menyimpan urusan pemerintahan per bidang

#### B. HIERARKI PERENCANAAN

**1. visi**
```
- id (PK)
- opd_id (FK to opds, nullable, nullOnDelete)
- document_type (enum: rpjmd, renstra, renja, dpa)
- kode (50 char)
- uraian (text)
- deskripsi (text, nullable)
- tahun_awal (integer)
- tahun_akhir (integer)
- timestamps
```
**Scope:** OpdScope (auto-filter berdasarkan OPD user)

**2. misi**
```
- id (PK)
- visi_id (FK to visi, cascadeOnDelete)
- kode (50 char)
- uraian (text)
- deskripsi (text, nullable)
- timestamps
```

**3. tujuan**
```
- id (PK)
- misi_id (FK to misi, cascadeOnDelete)
- kode (50 char)
- uraian (text)
- deskripsi (text, nullable)
- timestamps
```

**4. sasaran**
```
- id (PK)
- tujuan_id (FK to tujuan, cascadeOnDelete)
- kode (50 char)
- uraian (text)
- deskripsi (text, nullable)
- timestamps
```

**5. strategi**
```
- id (PK)
- sasaran_id (FK to sasaran, cascadeOnDelete)
- kode (50 char)
- uraian (text)
- deskripsi (text, nullable)
- timestamps
```

**6. arah_kebijakan**
```
- id (PK)
- strategi_id (FK to strategi, cascadeOnDelete)
- kode (50 char)
- uraian (text)
- deskripsi (text, nullable)
- timestamps
```

#### C. PROGRAM, KEGIATAN, SUB KEGIATAN

**1. program**
```
- id (PK)
- opd_id (FK to opds, nullable, nullOnDelete)
- kepmen_id (FK to kepmen, nullable)
- document_type (enum: rpjmd, renstra, renja, dpa)
- jenis_program (string, nullable)
- kode_rek (50 char)
- nama_rincian (500 char)
- deskripsi (text, nullable)
- pagu (decimal 15,2, default: 0)
- tahun_awal (integer, nullable)
- tahun_akhir (integer, nullable)
- target_t1 (decimal 10,2, nullable)
- target_t2 (decimal 10,2, nullable)
- target_t3 (decimal 10,2, nullable)
- target_t4 (decimal 10,2, nullable)
- target_t5 (decimal 10,2, nullable)
- target_tahunan (decimal 10,2, nullable)
- tahun (integer, nullable)
- is_prioritas (boolean, default: false)
- catatan_evaluasi (text, nullable)
- timestamps
```
**Scope:** OpdScope
**Relasi:** belongsTo Opd, Kepmen; hasMany Kegiatan; morphMany Realisasi; morphToMany Indikator

**2. kegiatan**
```
- id (PK)
- program_id (FK to program, cascadeOnDelete)
- opd_id (FK to opds, nullable, nullOnDelete)
- kepmen_id (FK to kepmen)
- document_type (enum: rpjmd, renstra, renja, dpa)
- kode_rek (50 char)
- nama_rincian (500 char)
- deskripsi (text, nullable)
- pagu (decimal 15,2, default: 0)
- tahun_awal (integer, nullable)
- tahun_akhir (integer, nullable)
- target_t1 sampai target_t5 (decimal 10,2, nullable)
- target_tahunan (decimal 10,2, nullable)
- tahun (integer, nullable)
- catatan_evaluasi (text, nullable)
- timestamps
```
**Scope:** OpdScope
**Relasi:** belongsTo Program, Opd, Kepmen; hasMany SubKegiatan; morphMany Realisasi; morphToMany Indikator

**3. sub_kegiatan**
```
- id (PK)
- kegiatan_id (FK to kegiatan, cascadeOnDelete)
- opd_id (FK to opds, nullable, nullOnDelete)
- kepmen_id (FK to kepmen)
- document_type (enum: rpjmd, renstra, renja, dpa)
- kode_rek (50 char)
- nama_rincian (500 char)
- deskripsi (text, nullable)
- pagu (decimal 15,2, default: 0)
- tahun_awal sampai tahun_akhir
- target_t1 sampai target_t5
- target_tahunan
- tahun
- catatan_evaluasi
- timestamps
```
**Scope:** OpdScope
**Relasi:** belongsTo Kegiatan, Opd, Kepmen; morphMany Realisasi; morphToMany Indikator

#### D. INDIKATOR & REALISASI

**1. indikator**
```
- id (PK)
- opd_id (FK to opds, nullable, nullOnDelete)
- document_type (enum: rpjmd, renstra, renja, dpa)
- jenis_indikator (enum: IKU, IKK, Program Prioritas, Program Aksi)
- uraian (text)
- satuan (100 char)
- jenis (enum: input, process, output, outcome, impact)
- sifat (enum: maximize, minimize, stabilize)
- keterangan (text, nullable)
- timestamps
```
**Scope:** OpdScope
**Relasi:** morphedByMany Program, Kegiatan, SubKegiatan (polymorphic many-to-many)
**Pivot Table:** indikatorables (target, realisasi, tahun, triwulan, catatan)

**Method Khusus:**
```php
hitungCapaian(float $target, float $realisasi): float
- maximize: (realisasi / target) * 100
- minimize: (target / realisasi) * 100
- stabilize: 100 - (abs((realisasi - target) / target) * 100)
```

**2. realisasi**
```
- id (PK)
- realisaseable_id (polymorphic)
- realisaseable_type (polymorphic)
- opd_id (FK to opds, nullable, nullOnDelete)
- document_type (enum: rpjmd, renstra, renja, dpa)
- tahun (integer)
- tahun_ke (integer, nullable)
- triwulan (integer)
- realisasi_fisik (decimal 10,2, default: 0)
- realisasi_keuangan (decimal 15,2, nullable)
- sisa_anggaran (decimal 15,2, nullable)
- catatan (text, nullable)
- input_by (FK to users)
- timestamps
- UNIQUE: (realisaseable_id, realisaseable_type, tahun, tahun_ke, triwulan)
```
**Relasi:** Polymorphic - bisa untuk Program, Kegiatan, atau SubKegiatan

#### E. DOKUMEN & IKU

**1. dokumen**
```
- id (PK)
- opd_id (FK)
- document_type
- nama_dokumen
- file_path
- tahun
- keterangan
- timestamps
```
**Fungsi:** Menyimpan dokumen perencanaan (RPJMD, Renstra, Renja, DPA)

**2. ikus (Indikator Kinerja Utama)**
```
- id (PK)
- indikator (string)
- satuan (string)
- capaian_2024 (string)
- target_2025 sampai target_2030 (string)
- timestamps
```
**Fungsi:** Menyimpan IKU dengan target multi-tahun

#### F. ANGGARAN DPA

**1. komponen_anggaran**
```
- id (PK)
- parent_id (FK to komponen_anggaran, nullable, cascade)
- kode (string)
- jenis (enum: program, kegiatan, sub_kegiatan)
- sub_unit (string)
- urusan (string)
- bidang_urusan (string)
- nama_komponen (string)
- tahun (integer)
- timestamps
```
**Fungsi:** Struktur hierarki anggaran DPA

**2. indikator_anggaran**
```
- id (PK)
- komponen_anggaran_id (FK)
- indikator
- satuan
- target_kinerja
- timestamps
```
**Fungsi:** Indikator kinerja per komponen anggaran

#### G. RELASI DATA DASAR

**data_dasar_relasi**
```
- id (PK)
- parent_type (string)
- parent_id (bigint)
- child_type (string)
- child_id (bigint)
- timestamps
```
**Fungsi:** Menyimpan relasi fleksibel antar entitas data dasar (Visi-Misi-Tujuan-Sasaran-Strategi-ArahKebijakan-Program-Kegiatan-SubKegiatan)

---

## 3. LAYOUT & STRUKTUR APLIKASI

### 3.1 Arsitektur Frontend (Inertia.js + Vue.js)

**Layout Utama:** `resources/js/Layouts/AppLayout.vue`

**Komponen Header:**
- Logo: "SIMEVLAP 2.0"
- Navigasi: Dashboard, Data Dasar, Dokumen, Realisasi, Resume
- Dropdown Pengaturan (khusus superadmin): OPD, User, Kepmen
- Info User: Nama & OPD/Singkatan
- Tombol Logout

**Warna Tema:**
- Primary: Emerald/Green gradient (#064E3B, #0B5F49, #0E6B52)
- Accent: Lime (#C7EA46, #D4F06A)
- Background: Slate-50
- Text: Emerald-900, Slate-700

### 3.2 Halaman-Halaman Utama

#### A. Dashboard (Landing Page)
**File:** `resources/js/Pages/Dashboard.vue`

**Tampilan:**
- Background: Gradient emerald-900 → emerald-700 → lime-600
- Header besar: "SIMEVLAP 2.0"
- Subtitle: "Sistem Monitoring Evaluasi Laporan"
- 3 Card Statistik:
  - 🏛️ Total OPD Aktif
  - 📊 Total Program
  - 📈 Total Realisasi
- 2 Panel:
  - Rangkuman Sistem (fitur-fitur)
  - Akses Cepat (link ke menu utama)

#### B. Dashboard Classic (Setelah Login)
**Route:** `/dashboard`
**Menggunakan:** AppLayout.vue
**Konten:** Statistik yang sama dengan landing page tapi dalam layout aplikasi

#### C. Data Dasar
**Route Base:** `/data-dasar`

**Sub Menu:**
1. **Bank Data** (`/data-dasar/bank-data`)
   - Mengelola data per level: Visi, Misi, Tujuan, Sasaran, Strategi, Arah Kebijakan, Program, Kegiatan, Sub Kegiatan
   - CRUD operations per level

2. **Relasi** (`/data-dasar/relasi`)
   - Mengelola hubungan antar level data
   - Ringkasan relasi
   - Update relasi per parent atau per item

3. **Program Prioritas** (`/data-dasar/program-prioritas`)
   - Daftar program yang ditandai prioritas
   - Toggle prioritas program

4. **Dokumen** (`/data-dasar/dokumen`)
   - Upload & manage dokumen perencanaan
   - Sub-menu: IKU

5. **Urusan & Bidang Urusan**
   - Manage urusan pemerintahan
   - Manage bidang urusan

6. **DPA (Dokumen Pelaksanaan Anggaran)**
   - Manage komponen anggaran
   - Struktur hierarki program-kegiatan-subkegiatan

#### D. Realisasi
**Route:** `/realisasi`
**Fungsi:**
- Input realisasi fisik & keuangan
- Per triwulan
- Untuk Program/Kegiatan/Sub Kegiatan
- CRUD operations

#### E. Resume
**Route:** `/resume`
**Fungsi:**
- Laporan & analitik capaian
- Resume per OPD
- Resume per periode

#### F. Pengaturan (Superadmin Only)
**Route Base:** `/pengaturan`

**Sub Menu:**
1. **OPD** - Manage data OPD
2. **User** - Manage user & assign ke OPD
3. **Kepmen** - Manage keputusan/peraturan dasar

### 3.3 Komponen Reusable

**Location:** `resources/js/Components/`

**Komponen Utama:**
- `NavItem.vue` - Item navigasi header
- Form components (input, select, dll)
- Modal components
- Table components

### 3.4 Utilities

**Location:** `resources/js/utils/`

**Fungsi:**
- Helper functions
- Format data
- Validasi

---

## 4. FITUR-FITUR KHUSUS

### 4.1 OPD Scope
Menggunakan `App\Models\Scopes\OpdScope` untuk auto-filter data berdasarkan OPD user yang login. Model yang menggunakan scope:
- Visi
- Program
- Kegiatan
- SubKegiatan
- Indikator

### 4.2 Document Type
Semua data perencanaan memiliki field `document_type`:
- **rpjmd** - Rencana Pembangunan Jangka Menengah Daerah
- **renstra** - Rencana Strategis
- **renja** - Rencana Kerja
- **dpa** - Dokumen Pelaksanaan Anggaran

### 4.3 Polymorphic Relations

**Realisasi:**
- Bisa untuk Program, Kegiatan, atau SubKegiatan
- Menggunakan `realisaseable_type` dan `realisaseable_id`

**Indikator:**
- Many-to-many polymorphic dengan Program, Kegiatan, SubKegiatan
- Pivot table: `indikatorables`
- Pivot data: target, realisasi, tahun, triwulan, catatan

### 4.4 Target Multi-Tahun
Program, Kegiatan, dan SubKegiatan memiliki:
- `tahun_awal` & `tahun_akhir` - periode pelaksanaan
- `target_t1` sampai `target_t5` - target per tahun (tahun ke-1 sampai ke-5)
- `target_tahunan` - target tahunan spesifik
- `tahun` - tahun spesifik

### 4.5 Role & Permission
Menggunakan Spatie Laravel Permission:
- **superadmin** - Akses penuh, bisa manage OPD, User, Kepmen
- **user** - User OPD, hanya bisa akses data OPD sendiri

### 4.6 Flash Messages
Sistem menggunakan flash messages untuk feedback:
- `flash.success` - Pesan sukses (hijau)
- `flash.error` - Pesan error (merah)

---

## 5. ALUR KERJA APLIKASI

### 5.1 Setup Awal (Superadmin)
1. Login sebagai superadmin
2. Tambah data OPD di menu Pengaturan → OPD
3. Tambah user dan assign ke OPD di menu Pengaturan → User
4. Tambah Kepmen/Peraturan dasar di menu Pengaturan → Kepmen
5. Setup Urusan & Bidang Urusan

### 5.2 Input Data Perencanaan (User OPD)
1. Login sebagai user OPD
2. Masuk ke Data Dasar → Bank Data
3. Input data hierarki:
   - Visi → Misi → Tujuan → Sasaran → Strategi → Arah Kebijakan
   - Program → Kegiatan → Sub Kegiatan
4. Atur relasi di menu Relasi
5. Tandai program prioritas jika perlu

### 5.3 Input Indikator
1. Buat indikator di menu yang sesuai
2. Attach indikator ke Program/Kegiatan/SubKegiatan
3. Set target per tahun/triwulan

### 5.4 Input Realisasi
1. Masuk ke menu Realisasi
2. Pilih Program/Kegiatan/SubKegiatan
3. Input realisasi fisik & keuangan per triwulan
4. Tambah catatan jika perlu

### 5.5 Monitoring & Evaluasi
1. Lihat Resume untuk analitik capaian
2. Bandingkan target vs realisasi
3. Evaluasi kinerja per OPD/Program
4. Export laporan

---

## 6. API & ROUTES

### 6.1 Public Routes
- `GET /` - Landing page dashboard
- `GET /login` - Halaman login
- `POST /login` - Proses login

### 6.2 Authenticated Routes
- `GET /dashboard` - Dashboard setelah login
- `POST /logout` - Logout

**Data Dasar:**
- `GET /data-dasar` - Index
- `GET /data-dasar/bank-data` - Menu bank data
- `GET /data-dasar/bank-data/{level}` - Data per level
- `POST /data-dasar/bank-data/{level}` - Store data
- `PUT /data-dasar/bank-data/{level}/{id}` - Update data
- `DELETE /data-dasar/bank-data/{level}/{id}` - Delete data
- `GET /data-dasar/relasi` - Menu relasi
- `GET /data-dasar/relasi/ringkasan` - Ringkasan relasi
- `GET /data-dasar/relasi/{level}` - Relasi per level
- `PUT /data-dasar/relasi/{level}/parent/{parentId}` - Update relasi by parent
- `PUT /data-dasar/relasi/{level}/{id}` - Update relasi
- Resource routes untuk: visi, program, kegiatan, sub-kegiatan, dokumen, iku, urusan, bidang-urusan

**Realisasi:**
- Resource routes: index, store, update, destroy

**Resume:**
- `GET /resume` - Index resume

### 6.3 Superadmin Routes
Prefix: `/pengaturan`
- Resource routes untuk: opd, user, kepmen
- `POST /pengaturan/kepmen/{kepmen}/activate` - Aktivasi kepmen

---

## 7. CATATAN PENTING

### 7.1 Migrasi Database
Urutan migrasi sudah diatur dengan timestamp untuk memastikan foreign key constraints terpenuhi:
1. opds (000001)
2. users (000002)
3. password_reset_tokens (000003)
4. sessions (000004)
5. kepmen (000005)
6. Hierarki: visi → misi → tujuan → sasaran → strategi → arah_kebijakan (000006-000011)
7. program → kegiatan → sub_kegiatan (000012-000014)
8. indikator → indikatorables (000015-000016)
9. realisasi → dokumen (000017-000018)
10. Permission tables (2026_04_01)
11. Modifikasi & tambahan (2026_04_xx, 2026_05_xx)

### 7.2 Seeder
- `DatabaseSeeder` - Main seeder
- `SuperadminSeeder` - Create superadmin user
- `RoleSeeder` - Create roles
- `OpdSeeder` - Seed OPD data
- `KepmenSeeder` - Seed Kepmen data
- `DataDasarSeeder` - Seed data dasar
- `ProgramUnggulanSeeder` - Seed program unggulan
- `ProgramAksiSeeder` - Seed program aksi

### 7.3 Referensi Data
Folder `referensi/` berisi data JSON untuk seeding:
- `referensi/apbd/skpd.json` - Data SKPD/OPD
- `database/json/master.json` - Data master
- `database/json/program_unggulan.json` - Program unggulan
- `database/json/visi.json` - Data visi

### 7.4 Environment
File `.env.example` berisi konfigurasi yang diperlukan:
- Database connection
- App key
- Mail configuration
- dll

---

## 8. KESIMPULAN

SIMEVLAP 2.0 adalah sistem komprehensif untuk monitoring dan evaluasi pembangunan daerah dengan fitur:

✅ **Hierarki Data Lengkap** - Dari Visi sampai Sub Kegiatan
✅ **Multi-Document Type** - RPJMD, Renstra, Renja, DPA
✅ **Polymorphic Relations** - Fleksibel untuk berbagai entitas
✅ **OPD Scope** - Auto-filter data per OPD
✅ **Role Management** - Superadmin & User OPD
✅ **Realisasi Triwulanan** - Fisik & Keuangan
✅ **Indikator Kinerja** - Dengan perhitungan capaian otomatis
✅ **Modern UI** - Inertia.js + Vue.js + Tailwind CSS
✅ **Responsive Design** - Mobile-friendly
✅ **Flash Messages** - User feedback yang jelas

Database dirancang dengan normalisasi yang baik, relasi yang jelas, dan cascade delete untuk menjaga integritas data.

---

**Dibuat:** 18 Juni 2026
**Versi:** 2.0
**Framework:** Laravel 13 + Inertia.js + Vue.js 3
