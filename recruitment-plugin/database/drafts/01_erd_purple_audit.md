# Audit ERD: 19 tabel ungu

## Status dan batasan

- Sumber struktur: `ERD.drawio.html`, XML draw.io yang tertanam.
- Seleksi tabel: bentuk `shape=table` dengan `fillColor=#6a00ff`.
- Hasil hitung: 19 tabel; diagram memiliki 39 tabel keseluruhan (10 biru `#0050ef`, 19 ungu `#6a00ff`, 10 pink muda `#f8cecc`).
- Semua nama tabel dan label kolom di bawah dipertahankan seperti yang terlihat di ERD, termasuk ejaan, kapitalisasi, spasi, duplikasi, dan label tanda minus.
- `[PK]` / `[FK]` menandakan teks yang terlihat di kolom ERD; tanda itu bukan bukti constraint sudah diterapkan.
- Tipe setelah `—` adalah teks tipe dari ERD, belum merupakan keputusan pemetaan Oracle. `NEEDS CONFIRMATION` berarti tipe/ukuran atau interpretasinya belum dinyatakan dengan cukup jelas.
- Nullability: PK akan menjadi `NOT NULL` jika PK dibuat. Untuk kolom non-PK, ERD tidak menyatakan nullability kecuali label yang secara eksplisit memuat `nullable`; karena itu nullability kolom lainnya `NEEDS CONFIRMATION`.
- Pemeriksaan Oracle terakhir yang tersedia (read-only) melaporkan schema `APP_USER`, service `XEPDB1`, dan hanya tabel `DAW_USER`. Oracle tidak dihubungi ulang saat menyiapkan draft ini; verifikasi terkini harus dilakukan sebelum penerapan.
- Tidak ada file kode/ERD, tabel, atau data yang diubah. Tidak ada SQL yang dijalankan.

## Rincian tabel menurut ERD

### 1. `jawaban_psikotes` — 7 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `jawaban_psikotes_id` | Tidak dicantumkan | PK | NEEDS CONFIRMATION untuk tipe; PK akan NOT NULL |
| `pertanyaan_psikotes_id` | Tidak dicantumkan | FK | NEEDS CONFIRMATION |
| `apakah_benar` | `Boolean` | — | NEEDS CONFIRMATION |
| `urutan_tampilan` | `Int` | — | NEEDS CONFIRMATION |
| `jawaban_teks` | `text` | — | NEEDS CONFIRMATION |
| `nilai` | `float` | — | NEEDS CONFIRMATION |
| `dijawab_pada` | `timestamp` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- `pertanyaan_psikotes` → `jawaban_psikotes` (panah ER `one-to-many`).
- `jawaban_psikotes` → `Pilihan_Respon_Psikotes` (panah ER `one-to-many`); tabel target bukan bagian dari 19 tabel ungu.
- FK `pertanyaan_psikotes_id` belum dapat dibuat aman: PK tabel pertanyaan tertulis `peertanyaan_psikotes_id`, bukan nama kolom yang sama.

### 2. `Persyaratan_Lowongan` — 9 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `persyaratan_lowongan_id` | Tidak dicantumkan | PK | NEEDS CONFIRMATION untuk tipe; PK akan NOT NULL |
| `judul_pekerjaan` | `string` | — | NEEDS CONFIRMATION |
| `nama_Persyaratan` | `string` | — | NEEDS CONFIRMATION |
| `jenis_Persyaratan` | `enum` | — | NEEDS CONFIRMATION |
| `created_at` | `timestamp` | — | NEEDS CONFIRMATION |
| `- updated_at` | `timestamp` | — | NEEDS CONFIRMATION; tanda `- ` tampak di label |
| `kategori_Persyaratan` | `enum` | — | NEEDS CONFIRMATION |
| `jenis_Persyaratan` | `enum` | — | Duplikat persis; NEEDS CONFIRMATION |
| `hr_id` | `int` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- `hr_user` → `Persyaratan_Lowongan` (panah ER `one-to-many`).
- Kolom `hr_id` tidak ditandai FK di ERD; target FK belum dikonfirmasi.

### 3. `status_request_lowongan` — 7 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `status_lowongan_id` | Tidak dicantumkan | PK | NEEDS CONFIRMATION untuk tipe; PK akan NOT NULL |
| `status_id` | `int` | — | NEEDS CONFIRMATION |
| `permintaan_id` | `int` | — | NEEDS CONFIRMATION |
| `status_pengajuan` | `enum` | — | NEEDS CONFIRMATION |
| `catatan_review` | `text` | — | NEEDS CONFIRMATION |
| `tanggal_dibuat` | `timestamp` | — | NEEDS CONFIRMATION |
| `hr_id` | `int` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- `hr_user` → `status_request_lowongan` (panah ER `one-to-many`).
- ERD tidak memberi penanda FK pada `status_id`, `permintaan_id`, atau `hr_id`, dan tidak menunjukkan target `permintaan_id`.

### 4. `bagian_pendaftaran` — 7 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `bagian_pendaftaran_id` | `int` | PK | PK akan NOT NULL |
| `nama_Section` | `string` | — | NEEDS CONFIRMATION |
| `deskripsi` | `text` | — | NEEDS CONFIRMATION |
| `display_order` | `int` | — | NEEDS CONFIRMATION |
| `created_at` | `int` | — | NEEDS CONFIRMATION |
| `update_at` | `int` | — | NEEDS CONFIRMATION |
| `hr_id` | `int` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- `hr_user` → `bagian_pendaftaran` (panah ER `one-to-many`).
- `bagian_pendaftaran` → `pilihan_fields_pendaftaran` (panah ER `one-to-many`).
- `hr_id` dan `pilihan_fields_pendaftaran.bagian_pendaftaran_id` tidak diberi penanda FK. Kolom terakhir cocok nama dengan PK, tetapi constraint tetap perlu dikonfirmasi.

### 5. `jadwal_psikotes` — 17 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `jadwal_psikotes_id` | Tidak dicantumkan | PK | NEEDS CONFIRMATION untuk tipe; PK akan NOT NULL |
| `metode_psikotes` | `enum` | — | NEEDS CONFIRMATION |
| `tanggal` | `date` | — | NEEDS CONFIRMATION |
| `lokasi` | `string` | — | NEEDS CONFIRMATION |
| `waktu` | `time` | — | NEEDS CONFIRMATION |
| `lokasi` | `string` | — | Duplikat persis; NEEDS CONFIRMATION |
| `alamat_lengkap` | `string` | — | NEEDS CONFIRMATION |
| `kontak_perorang` | `string` | — | NEEDS CONFIRMATION |
| `dress_code` | `string` | — | NEEDS CONFIRMATION |
| `maks_peserta` | `string` | — | NEEDS CONFIRMATION |
| `perlu_konfirmasi_kehadiran` | `boolean` | — | NEEDS CONFIRMATION |
| `dokumen_yang_dibawa` | `boolean/string` | — | Tipe ambigu; NEEDS CONFIRMATION |
| `informasi_tambah` | `text` | — | NEEDS CONFIRMATION |
| `create_by` | `string` | — | NEEDS CONFIRMATION |
| `created_at` | `string` | — | NEEDS CONFIRMATION |
| `update_at` | `string` | — | NEEDS CONFIRMATION |
| `hr_id` | `int` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- `hr_user` → `jadwal_psikotes` (panah ER `one-to-many`).
- `jadwal_psikotes` → `jadwal_kandidat` (panah ER `one-to-many`).
- Tidak ada penanda FK pada `hr_id` atau `jadwal_kandidat.jadwal_psikotes_id`.

### 6. `template_email_rekrutment` — 9 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `template_email_id` | Tidak dicantumkan | PK | NEEDS CONFIRMATION untuk tipe; PK akan NOT NULL |
| `email_rekruitmen_id` | Tidak dicantumkan | FK | NEEDS CONFIRMATION |
| `kategori_template` | `enum` | — | NEEDS CONFIRMATION |
| `subjek_email` | `varchar(50)` | — | NEEDS CONFIRMATION |
| `isi_email` | `text` | — | NEEDS CONFIRMATION |
| `is_active` | `boolean` | — | NEEDS CONFIRMATION |
| `created_by` | `string` | — | NEEDS CONFIRMATION |
| `created_at` | `timestamp` | — | NEEDS CONFIRMATION |
| `updated_at` | `timestamp` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- Konektor `email_rekrutment` → `template_email_rekrutment` (panah ER `one-to-many`).
- FK bernama `email_rekruitmen_id` berbeda ejaan dari PK `email_rekrutmen_id` pada tabel `email_rekrutment`; arah relasi dan pemilik FK NEEDS CONFIRMATION.

### 7. `email_rekrutment` — 12 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `email_rekrutmen_id` | Tidak dicantumkan | PK | NEEDS CONFIRMATION untuk tipe; PK akan NOT NULL |
| `template_email_id` | Tidak dicantumkan | FK | NEEDS CONFIRMATION |
| `kandidat_id` | `int` | FK | NEEDS CONFIRMATION; target berada di luar kumpulan tabel ungu |
| `lamaran_id` | `int` | — | NEEDS CONFIRMATION |
| `email_penerima` | `varchar(50)` | — | NEEDS CONFIRMATION |
| `subject_email` | `varchar(50)` | — | NEEDS CONFIRMATION |
| `isi_email` | `text` | — | NEEDS CONFIRMATION |
| `status_pengiriman` | `enum` | — | NEEDS CONFIRMATION |
| `terkirim_pada` | `timestamp` | — | NEEDS CONFIRMATION |
| `dibaca_pada` | `timestamp` | — | NEEDS CONFIRMATION |
| `created_by` | `string` | — | NEEDS CONFIRMATION |
| `hr_id` | `int` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- `Profil_Kandidat` → `email_rekrutment` (panah ER `one-to-many`; tabel target `Profil_Kandidat` berwarna pink dan bukan dalam cakupan draft).
- Konektor email↔template terlihat, tetapi tidak konsisten dengan FK yang ditandai pada kedua tabel.

### 8. `rekapan_keputusan` — 12 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `rekapan_keputusan_id` | Tidak dicantumkan | PK | NEEDS CONFIRMATION untuk tipe; PK akan NOT NULL |
| `kandidat_id` | `int` | — | NEEDS CONFIRMATION; relasi menuju tabel di luar cakupan |
| `nama_kandidat` | `string` | — | NEEDS CONFIRMATION |
| `nama_departement` | `enum` | — | NEEDS CONFIRMATION |
| `tanggal_wawancara` | `date` | — | NEEDS CONFIRMATION |
| `total_nilai` | `int` | — | NEEDS CONFIRMATION |
| `rekomendasi_user` | `enum` | — | NEEDS CONFIRMATION |
| `keputusan_akhir` | `enum` | — | NEEDS CONFIRMATION |
| `total_status_rekomterima` | `int` | — | NEEDS CONFIRMATION |
| `diterima_hr` | `int` | — | NEEDS CONFIRMATION |
| `menunggu_keputusan` | `int` | — | NEEDS CONFIRMATION |
| `hr_id` | `int` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- `Profil_Kandidat` → `rekapan_keputusan` (panah ER `one-to-many`; tabel pink di luar cakupan).
- `hr_user` → `rekapan_keputusan` (panah ER `one-to-many`); `hr_id` tidak ditandai FK.

### 9. `Hasil_wawancara_user` — 21 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `hasil_wawancara_user_id` | `int` | PK | PK akan NOT NULL |
| `kandidat_id` | `int` | FK | NEEDS CONFIRMATION; target `Profil_Kandidat` di luar cakupan |
| `nama_kandidat` | `string` | — | NEEDS CONFIRMATION |
| `nama_pewawancara` | `string` | — | NEEDS CONFIRMATION |
| `departemen_id` | `int` | FK | NEEDS CONFIRMATION; target `Departemen` di luar cakupan |
| `nama_departemen` | `string` | — | NEEDS CONFIRMATION |
| `nilai_penampilan` | `int` | — | NEEDS CONFIRMATION |
| `nilai_komunikasi` | `int` | — | NEEDS CONFIRMATION |
| `nilai_motivasi` | `int` | — | NEEDS CONFIRMATION |
| `nilai_pengalaman` | `int` | — | NEEDS CONFIRMATION |
| `nilai_keterampilan` | `int` | — | NEEDS CONFIRMATION |
| `nilai_kerjasama` | `int` | — | NEEDS CONFIRMATION |
| `nilai_problemsolving` | `int` | — | NEEDS CONFIRMATION |
| `nilai_adaptasi` | `int` | — | NEEDS CONFIRMATION |
| `nilai_total` | `int` | — | NEEDS CONFIRMATION |
| `rekomendasi` | `enum` | — | NEEDS CONFIRMATION |
| `catatan_wawancara` | `varchar(250)` | — | NEEDS CONFIRMATION |
| `tanggal_wawancara` | `int` | — | NEEDS CONFIRMATION |
| `dikumpulkan_pada` | `int` | — | NEEDS CONFIRMATION |
| `status_kandidat_id` | `int` | FK | NEEDS CONFIRMATION; target `Status_Kandidat` di luar cakupan |
| `status_kandidat` | `enum` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- Konektor ke `Profil_Kandidat` (tanpa kardinalitas tercantum).
- Konektor ke `Status_Kandidat` dengan panah ER `zero-to-one`.
- Dua konektor ke `Departemen`; satu memiliki panah `zero-to-one`, satu tidak mencantumkan kardinalitas.
- Nama tabel ini berbeda kapitalisasi dari tabel pink `Hasil_Wawancara_user`; keduanya tidak boleh dianggap tabel yang sama tanpa persetujuan.

### 10. `jadwal_kandidat` — 8 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `jadwal_kandidat_id` | Tidak dicantumkan | PK | NEEDS CONFIRMATION untuk tipe; PK akan NOT NULL |
| `jadwal_psikotes_id` | `int` | — | NEEDS CONFIRMATION |
| `nama_kandidat` | `string` | — | NEEDS CONFIRMATION |
| `tanggal_wawancara` | `date` | — | NEEDS CONFIRMATION |
| `lokasi_wawancara` | `time` | — | NEEDS CONFIRMATION |
| `metode_wawancara` | `varchar(50)` | — | NEEDS CONFIRMATION |
| `hasil_hr` | `enum` | — | NEEDS CONFIRMATION |
| `status_wawancara` | `enum` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- `jadwal_psikotes` → `jadwal_kandidat` (panah ER `one-to-many`).
- Kolom `jadwal_psikotes_id` tidak diberi penanda FK; PK target `jadwal_psikotes_id` belum bertipe di ERD.

### 11. `tes_psikologi` — 11 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `tes_psikologi_id` | Tidak dicantumkan | PK | NEEDS CONFIRMATION untuk tipe; PK akan NOT NULL |
| `judul` | `string` | — | NEEDS CONFIRMATION |
| `petunjuk_pengerjaan` | `text` | — | NEEDS CONFIRMATION |
| `durasi_pengerjaan` | `int` | — | NEEDS CONFIRMATION |
| `updated_at` | `timestamp` | — | NEEDS CONFIRMATION |
| `jumlah soal` | `int` | — | Nama kolom mengandung spasi |
| `nilai_kelulusan` | `decimal` | — | Precision/scale NEEDS CONFIRMATION |
| `kamera_wajib_aktif` | `boolean` | — | Representasi Oracle NEEDS CONFIRMATION |
| `is_active` | `boolean` | — | Representasi Oracle NEEDS CONFIRMATION |
| `urutan_tampilan` | `int` | — | NEEDS CONFIRMATION |
| `created_at` | `timestamp` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- `tes_psikologi` → `pertanyaan_psikotes` (panah ER `one-to-many`).
- `tes_psikologi` → `hasil_tes_psikologi` (panah ER `one-to-many`; tabel target bukan bagian dari 19 tabel ungu).

### 12. `pertanyaan_psikotes` — 6 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `peertanyaan_psikotes_id` | Tidak dicantumkan | PK | NEEDS CONFIRMATION untuk tipe/ejaan; PK akan NOT NULL |
| `tes_psikologi_id` | Tidak dicantumkan | — | NEEDS CONFIRMATION |
| `teks_pertanyaan` | `text` | — | NEEDS CONFIRMATION |
| `jenis_pertanyaan` | `enum` | — | NEEDS CONFIRMATION |
| `nilai_pertanyaan` | `decimal` | — | Precision/scale NEEDS CONFIRMATION |
| `display_order` | `int` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- `tes_psikologi` → `pertanyaan_psikotes` (panah ER `one-to-many`).
- `pertanyaan_psikotes` → `respon_psikotes` dan `jawaban_psikotes` (panah ER `one-to-many`; `respon_psikotes` di luar cakupan).
- `tes_psikologi_id` tidak ditandai FK; ejaan PK `peertanyaan_psikotes_id` tidak sama dengan nama FK pada tabel jawaban.

### 13. `detail_info_lowongan` — 19 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `detail_lowongan_id` | `int` | PK | PK akan NOT NULL |
| `request_lowongan_id` | `int` | FK | NEEDS CONFIRMATION; tidak ada konektor langsung yang memastikan target |
| `kolom_pendaftaran_id` | `int` | FK | NEEDS CONFIRMATION; ada konektor ke `kolom_landing_pendaftaran` |
| `judul_pekerjaan` | `string` | — | NEEDS CONFIRMATION |
| `deskripsi_pekerjaan` | `text` | — | NEEDS CONFIRMATION |
| `tanggung_jawab` | `text` | — | NEEDS CONFIRMATION |
| `no_fptk` | `VARCHAR(15)` | — | NEEDS CONFIRMATION |
| `request_at` | `timestamp` | — | NEEDS CONFIRMATION |
| `departement_id` | `int` | FK | NEEDS CONFIRMATION; target tabel departemen berwarna pink dan ejaan berbeda |
| `nama_departement` | `string` | — | NEEDS CONFIRMATION |
| `level_jabatan` | `string` | — | NEEDS CONFIRMATION |
| `kategori_KJ` | `string` | — | NEEDS CONFIRMATION |
| `jumlah_kebutuhan` | `int` | — | ERD menandai nullable |
| `prioritas` | `enum` | — | NEEDS CONFIRMATION |
| `batas_lamaran` | `date` | — | NEEDS CONFIRMATION |
| `lokasi_penempatan` | `string` | — | NEEDS CONFIRMATION |
| `pendidikan_minimum` | `string` | — | NEEDS CONFIRMATION |
| `pengalaman_minimum` | `text` | — | NEEDS CONFIRMATION |
| `sertifikasi_wajib` | `text` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- `detail_info_lowongan` → `kolom_landing_pendaftaran` (panah berada pada ujung sumber `zero-to-one`; arah FK perlu dikonfirmasi).
- `request_lowongan_id` memiliki nama kandidat yang sama dengan PK `request_lowongan.request_lowongan_id`, tetapi tidak ada konektor eksplisit.
- `departement_id` tidak sama ejaannya dengan `Departemen.departemen_id`; tabel tujuan pink.

### 14. `request_lowongan` — 29 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `request_lowongan_id` | `int` | PK | PK akan NOT NULL |
| `judul_pekerjaan` | `string` | — | NEEDS CONFIRMATION |
| `deskripsi_pekerjaan` | `text` | — | NEEDS CONFIRMATION |
| `tanggung_jawab` | `text` | — | NEEDS CONFIRMATION |
| `Hiring_user_id` | `int` | — | NEEDS CONFIRMATION |
| `Hiring_user pic` | `string` | — | Spasi; kolom duplikat |
| `Hiring_user pic` | `string` | — | Spasi; duplikat persis |
| `no_fptk` | `VARCHAR(15)` | — | NEEDS CONFIRMATION |
| `request_at` | `timestamp` | — | NEEDS CONFIRMATION |
| `departement_id` | `int` | FK | NEEDS CONFIRMATION; nama berbeda dari tabel/kolom departemen |
| `nama_departement` | `string` | — | NEEDS CONFIRMATION |
| `kebutuhan_posisi` | `string` | — | NEEDS CONFIRMATION |
| `level_jabatan` | `string` | — | NEEDS CONFIRMATION |
| `kategori_KJ` | `string` | — | NEEDS CONFIRMATION |
| `jumlah_kebutuhan` | `int` | — | NEEDS CONFIRMATION |
| `status_karyawan` | `string` | — | Duplikat persis |
| `status_karyawan` | `string` | — | Duplikat persis |
| `prioritas` | `enum` | — | NEEDS CONFIRMATION |
| `batas_lamaran` | `date` | — | NEEDS CONFIRMATION |
| `lokasi_penempatan` | `string` | — | NEEDS CONFIRMATION |
| `alasan_kebutuhan` | `text` | — | NEEDS CONFIRMATION |
| `tujuan_utama_posisi` | `text` | — | NEEDS CONFIRMATION |
| `tanggung_jawab_utama` | `text` | — | NEEDS CONFIRMATION |
| `output_harapan` | `text` | — | NEEDS CONFIRMATION |
| `dampak_kekosongan` | `text` | — | NEEDS CONFIRMATION |
| `pendidikan_minimum` | `string` | — | NEEDS CONFIRMATION |
| `pengalaman_minimum` | `text` | — | NEEDS CONFIRMATION |
| `sertifikasi_wajib` | `text` | — | NEEDS CONFIRMATION |
| `hr_id` | `int` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- `Departemen` → `request_lowongan` (panah ER `many`; tabel pink).
- `hr_user` → `request_lowongan` (panah ER `one-to-many`); `hr_id` tidak ditandai FK.

### 15. `kolom_landing_pendaftaran` — 12 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `kolom_pendaftaran_id` | `int` | PK | PK akan NOT NULL |
| `judul_pekerjaan` | `String` | — | NEEDS CONFIRMATION |
| `departemen_id` | `int` | FK | NEEDS CONFIRMATION; target `Departemen` pink |
| `nama_departemen` | `enum` | — | NEEDS CONFIRMATION |
| `nama_dealer` | `enum` | — | NEEDS CONFIRMATION |
| `kategori_kj` | `enum` | — | NEEDS CONFIRMATION |
| `deskripsi_pekerjaan` | `text` | — | NEEDS CONFIRMATION |
| `persyaratan_utama` | `enum` | — | NEEDS CONFIRMATION |
| `batas_lamaran` | `date` | — | ERD menandai nullable |
| `diposting_pada` | `date` | — | NEEDS CONFIRMATION |
| `status_kolom` | `boolean` | — | Representasi Oracle NEEDS CONFIRMATION |
| `urutan_tampil` | `int` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- Konektor ke `detail_info_lowongan` (panah ujung sumber `zero-to-one`; arah FK belum jelas).
- Konektor ke `Departemen`; kolom `departemen_id` ditandai FK, tetapi target di luar kumpulan ungu.

### 16. `konfiguasi_global_psikotes` — 7 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `konfigurasi_pisikotes` | Tidak dicantumkan | PK | NEEDS CONFIRMATION untuk tipe/ejaan; PK akan NOT NULL |
| `kamera_wajib_global` | `boolean` | — | Representasi Oracle NEEDS CONFIRMATION |
| `acak_soal` | `boolean` | — | Representasi Oracle NEEDS CONFIRMATION |
| `status_tes` | `boolean` | — | Representasi Oracle NEEDS CONFIRMATION |
| `updated_by` | `string` | — | NEEDS CONFIRMATION |
| `updated_at` | `timestamp` | — | NEEDS CONFIRMATION |
| `hr_id` | `int` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- `konfiguasi_global_psikotes` ↔ `hr_user` (panah kedua ujung `mandatory one`).
- `hr_id` tidak ditandai FK.

### 17. `hr_user` — 3 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `hr_id` | `int` | PK | PK akan NOT NULL |
| `nama_pengguna` | `string` | — | NEEDS CONFIRMATION |
| `password` | `string` | — | NEEDS CONFIRMATION; kebijakan hash/penyimpanan wajib diputuskan |

Relasi/kandidat dependensi yang terlihat:
- `hr_user` → `jadwal_psikotes`, `rekapan_keputusan`, `Persyaratan_Lowongan`, `status_request_lowongan`, `request_lowongan`, dan `bagian_pendaftaran` (panah ER `one-to-many`).
- Relasi dua arah `mandatory one` dengan `konfiguasi_global_psikotes`.
- Kolom `hr_id` pada tabel-tabel anak tidak ditandai FK.
- Kode saat ini mengelola akun departemen dengan WordPress Users API dan user meta; tidak ada bukti kode yang mengonfirmasi penggunaan tabel Oracle `hr_user`.

### 18. `slot_wawancara` — 12 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `slot_wawancara_id` | Tidak dicantumkan | PK | NEEDS CONFIRMATION untuk tipe; PK akan NOT NULL |
| `lowongan_id` | `int` | FK | NEEDS CONFIRMATION; target tidak jelas |
| `start_at` | `timestamp` | — | NEEDS CONFIRMATION |
| `ends_at` | `timestamp` | — | NEEDS CONFIRMATION |
| `zona_waktu` | `VARCHAR` | — | Panjang NEEDS CONFIRMATION |
| `metode_wawancara` | `enum` | — | NEEDS CONFIRMATION |
| `lokasi` | `VARCHAR` | — | Panjang NEEDS CONFIRMATION |
| `kapasitas` | `int` | — | NEEDS CONFIRMATION |
| `nama_pewawancara` | `string` | — | NEEDS CONFIRMATION |
| `kandidat_id` | `int` | FK | NEEDS CONFIRMATION; target kandidat di luar cakupan |
| `nama_kandidat` | `string` | — | NEEDS CONFIRMATION |
| `meeting_url` | `VARCHAR` | — | Panjang NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- `slot_wawancara` → `booking_interview` (panah ER `one-to-many`); tabel target bukan bagian dari 19 tabel ungu.
- Tidak ada target yang dapat dipastikan bagi `lowongan_id`.

### 19. `pilihan_fields_pendaftaran` — 6 kolom

| Kolom persis pada ERD | Tipe tertulis | Penanda | Nullability |
|---|---|---|---|
| `pilihan_pendaftaran_id` | `int` | PK | PK akan NOT NULL |
| `field_id` | `int` | — | NEEDS CONFIRMATION |
| `nilai` | `string` | — | NEEDS CONFIRMATION |
| `label` | `string` | — | NEEDS CONFIRMATION |
| `urutan_tampilan` | `int` | — | NEEDS CONFIRMATION |
| `bagian_pendaftaran_id` | `int` | — | NEEDS CONFIRMATION |

Relasi/kandidat dependensi yang terlihat:
- `bagian_pendaftaran` → `pilihan_fields_pendaftaran` (panah ER `one-to-many`).
- `bagian_pendaftaran_id` cocok nama dengan PK tabel parent, tetapi tidak diberi penanda FK.
- Target `field_id` tidak terlihat.

## Dependensi FK dan urutan kandidat

Ini adalah dependensi/kandidat yang berasal dari penanda FK, nama kolom, atau konektor ERD; bukan keputusan constraint final.

| Child (kolom) | Kandidat parent | Dasar | Status |
|---|---|---|---|
| `jawaban_psikotes.pertanyaan_psikotes_id` | `pertanyaan_psikotes.peertanyaan_psikotes_id` | FK marker; label PK tidak sama | NEEDS CONFIRMATION |
| `template_email_rekrutment.email_rekruitmen_id` | `email_rekrutment.email_rekrutmen_id` | FK marker; perbedaan ejaan dan konektor berarah berlawanan | NEEDS CONFIRMATION |
| `email_rekrutment.template_email_id` | `template_email_rekrutment.template_email_id` | FK marker; konektor tidak konsisten | NEEDS CONFIRMATION |
| `email_rekrutment.kandidat_id` | `Profil_Kandidat.kandidat id` | FK marker; target di tabel pink dan nama PK mengandung spasi | NEEDS CONFIRMATION |
| `detail_info_lowongan.kolom_pendaftaran_id` | `kolom_landing_pendaftaran.kolom_pendaftaran_id` | FK marker, konektor, dan nama kolom PK cocok | Kandidat paling jelas; tetap konfirmasi arah/kardinalitas |
| `detail_info_lowongan.request_lowongan_id` | `request_lowongan.request_lowongan_id` | FK marker dan nama kolom PK cocok; tidak ada konektor langsung | NEEDS CONFIRMATION |
| `detail_info_lowongan.departement_id` | `Departemen.departemen_id` | FK marker; target tabel pink dan ejaan tidak sama | NEEDS CONFIRMATION |
| `request_lowongan.departement_id` | `Departemen.departemen_id` | FK marker; target tabel pink dan ejaan tidak sama | NEEDS CONFIRMATION |
| `kolom_landing_pendaftaran.departemen_id` | `Departemen.departemen_id` | FK marker; target tabel pink | NEEDS CONFIRMATION |
| `Hasil_wawancara_user.kandidat_id` | `Profil_Kandidat.kandidat id` | FK marker; tabel pink, nama tidak sama | NEEDS CONFIRMATION |
| `Hasil_wawancara_user.departemen_id` | `Departemen.departemen_id` | FK marker; tabel pink | NEEDS CONFIRMATION |
| `Hasil_wawancara_user.status_kandidat_id` | `Status_Kandidat.status_kandidat_id` | FK marker; tabel pink | NEEDS CONFIRMATION |
| `slot_wawancara.lowongan_id` | Tidak jelas | FK marker tanpa kolom PK target yang cocok/tegas | NEEDS CONFIRMATION |
| `slot_wawancara.kandidat_id` | `Profil_Kandidat.kandidat id` | FK marker; tabel pink dan nama tidak sama | NEEDS CONFIRMATION |

Konektor tanpa FK marker yang tetap perlu keputusan: `hr_id` ke `hr_user.hr_id` pada beberapa tabel; `jadwal_kandidat.jadwal_psikotes_id`; `pertanyaan_psikotes.tes_psikologi_id`; `pilihan_fields_pendaftaran.bagian_pendaftaran_id`; serta tabel `bagian_pendaftaran`/`hr_user` dan `status_request_lowongan`/`hr_user`.

Urutan pembuatan kandidat hanya untuk dependensi internal yang cukup jelas:

1. `hr_user`, `bagian_pendaftaran`, `tes_psikologi`, `kolom_landing_pendaftaran`, `request_lowongan` (urutan relatif belum final karena FK eksternal/ambigu).
2. `Persyaratan_Lowongan`, `status_request_lowongan`, `jadwal_psikotes`, `rekapan_keputusan`, `bagian_pendaftaran` (jika FK ke `hr_user` disetujui).
3. `pilihan_fields_pendaftaran` setelah `bagian_pendaftaran`.
4. `pertanyaan_psikotes` setelah `tes_psikologi`; `jawaban_psikotes` setelah target PK pertanyaan diputuskan.
5. `jadwal_kandidat` setelah `jadwal_psikotes`.
6. `detail_info_lowongan` setelah parent FK yang disetujui.
7. `template_email_rekrutment` dan `email_rekrutment`: relasi tampak berpotensi sirkular/terbalik dan belum dapat diurutkan aman.
8. `Hasil_wawancara_user` dan `slot_wawancara` bergantung pada target di luar kumpulan ungu.

Urutan di atas bukan urutan untuk langsung menjalankan DDL. FK yang targetnya berwarna selain ungu atau belum jelas sengaja tidak ditetapkan dalam draft.

## Temuan yang perlu keputusan

| Isu | Fakta ERD | Bukti kode/dokumentasi | Keputusan |
|---|---|---|---|
| Tipe tidak dicantumkan | Beberapa PK dan FK tidak memiliki tipe; beberapa FK hanya nama kolom | Tidak ada definisi kolom 19 tabel di kode atau migrasi | Tetapkan tipe untuk setiap field bertanda “tipe tidak dicantumkan” |
| Tipe domain tidak langsung Oracle | ERD memakai `string`, `text`, `file`, `enum`, `boolean`, `time` | Kode tidak memetakan field-field ini ke Oracle | Pilih ukuran `VARCHAR2`, `CLOB`/`BLOB` atau path, representasi boolean Oracle 21c, dan konvensi waktu |
| Panjang/precision | Banyak `string`, `text`, `VARCHAR` tidak berukuran; `decimal` tanpa precision/scale | Tidak ada batasan di kode | Tetapkan panjang karakter dan precision/scale secara eksplisit |
| Nullable | Hanya beberapa field bertanda `nullable`; sisanya tidak dijelaskan | Tidak ada constraint aplikasi/DB yang mengonfirmasi | Konfirmasi nullability seluruh kolom non-PK |
| ENUM | Ada sejumlah field `enum`, tetapi diagram tidak mencantumkan nilai | Status di kode hanya berlaku untuk tabel `daw_*`, bukan tabel ERD | Tentukan domain tiap enum; jangan menambahkan `CHECK` sebelum disetujui |
| Kolom duplikat | `jenis_Persyaratan`, `lokasi`, `Hiring_user pic`, `status_karyawan` berulang | Kode tidak memberi pemetaan alternatif | Konfirmasi apakah duplikasi disengaja atau label salah |
| Nama dengan spasi/karakter khusus | `Users AD (Admin Departemen)`, `jumlah soal`, `Hiring_user pic`, `kandidat id`, `- updated_at` | Kode tidak mengaksesnya | Konfirmasi nama identifier dan apakah identifier harus quoted; tanda `- ` pada label juga perlu dipastikan |
| Kemungkinan salah eja/variasi nama | `konfiguasi_global_psikotes`, `konfigurasi_pisikotes`, `peertanyaan_psikotes_id`, `email_rekruitmen_id`, `departement_id`, `nama_departement` | Tidak ada penggunaan yang mengonfirmasi ejaan yang dimaksud | Pertahankan ejaan ERD atau setujui koreksi secara eksplisit |
| Tabel wawancara mirip | Tabel ungu `Hasil_wawancara_user` berbeda kapitalisasi dengan tabel pink `Hasil_Wawancara_user` | Kode tidak menggunakan keduanya | Pastikan apakah dua entitas berbeda; Oracle identifier unquoted tidak membedakan kapitalisasi |
| FK/kardinalitas | Sejumlah marker FK tidak cocok dengan PK, tidak punya connector, atau connector berarah lain | Tidak ada FK 19 tabel di kode/migrasi | Setujui target, arah, optionality, dan nama constraint setiap FK |
| Tabel terkait di luar 19 ungu | Beberapa relasi menuju tabel pink/biru, misalnya `Profil_Kandidat`, `Departemen`, `Status_Kandidat`, `Pilihan_Respon_Psikotes`, `booking_interview` | Scope SQL dibatasi 19 tabel ungu; target tersebut tidak masuk cakupan | Tentukan apakah tabel target sudah ada dan apakah FK dibuat setelah metadata target diperiksa |
| `hr_user` dan password | ERD mencantumkan `password string` dan relasi HR | Akun departemen di kode menggunakan WordPress Users API/meta; bukan tabel Oracle `hr_user` | Tentukan apakah `hr_user` sumber akun terpisah dan kebijakan hash; jangan menyimpan password plaintext |
| Skema aplikasi saat ini berbeda | Gateway menggunakan `daw_vacancies`, `daw_applicants`, `daw_applications`, `daw_interview_slots`, `daw_interview_bookings`; migrasi menyentuh `daw_applications` | `database/README.md` menyebut payload lamaran dan insert transaction menunggu skema final | Konfirmasi apakah ERD ungu skema terpisah atau akan dipetakan ke gateway aplikasi; jangan mengubah `DAW_USER` atau tabel `daw_*` dalam draft ini |

## Usulan koreksi (belum diterapkan)

Usulan ini bukan fakta dan tidak diterapkan pada nama ERD atau kode:

- Pastikan kemungkinan pengetikan `konfiguasi_global_psikotes`, `konfigurasi_pisikotes`, `peertanyaan_psikotes_id`, `email_rekruitmen_id`, serta variasi `departemen`/`departement`.
- Tentukan apakah label `- updated_at` adalah nama kolom `updated_at` dengan bullet visual atau identifier yang memang diawali tanda minus.
- Bedakan secara eksplisit entitas wawancara yang berbeda kapitalisasi, dan hilangkan/beri nama berbeda untuk kolom-kolom duplikat.
- Tentukan nama kanonis untuk `kandidat id` versus `kandidat_id` sebelum menambahkan FK.

## Keputusan status draft

Draft `02_create_purple_tables.sql` sengaja menempatkan DDL di dalam komentar dan memuat placeholder `NEEDS CONFIRMATION`. DDL **belum siap dijalankan**. Draft baru boleh diaktifkan setelah tipe/ukuran/nullability, nama duplikat/ambigu, nilai ENUM, semua target FK, dan kebijakan autentikasi HR dikonfirmasi. `03_verify_purple_tables.sql` hanya berisi query metadata baca-saja untuk preflight dan verifikasi.
