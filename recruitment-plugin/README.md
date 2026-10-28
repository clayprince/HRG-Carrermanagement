# Recruitment Plugin

Custom WordPress plugin untuk sistem rekrutmen dengan dua area utama:

- **Public / Applicant Area** — halaman Karir terbuka untuk umum; pendaftaran, profil, formulir lamaran, dan proses pelamar menggunakan akun WordPress ber-role `daw_applicant`.
- **Admin / HRD Area** — dashboard rekrutmen (`/recruitment-admin/`) dengan autentikasi.

## Struktur Modular

```text
recruitment-plugin/
├── assets/                  # CSS, JavaScript, images, icons
├── pages/                   # Entry point layout per halaman
│   ├── public/
│   └── admin/
├── components/              # UI reusable
│   ├── public/
│   └── admin/
├── routes/                  # Registry dan renderer route
├── database/
│   ├── connection/           # Konfigurasi dan koneksi bersama Oracle
│   ├── migrations/           # Migrasi skema Oracle
│   └── class-database.php    # Query dan transaksi domain rekrutmen
├── includes/                # Helper, auth, dan bootstrap
├── public/                  # Template lama selama migrasi bertahap
├── admin/                   # Template lama selama migrasi bertahap
└── templates/               # Email dan halaman error
```

Alur request: `recruitment-plugin.php` -> `includes/class-plugin.php` -> `routes/class-router.php` -> `pages/*` -> `components/*`.

Konfigurasi Oracle dibaca dengan urutan prioritas: konstanta PHP, environment variable proses, file `.env` yang ditunjuk oleh `RECRUITMENT_DOTENV_PATH`, lalu option WordPress `recruitment_oracle_config`. Untuk konfigurasi lokal, simpan file `.env` di luar document root dan jangan commit file rahasia. Salin format dari [`.env.example`](../.env.example) ke file lokal, isi kredensial Oracle, lalu tentukan path file tersebut di `wp-config.php` sebelum plugin dimuat. Pada instalasi Laragon ini, path yang digunakan adalah `D:/laragon/etc/hr-management.oracle.env`:

```php
define( 'RECRUITMENT_DOTENV_PATH', 'D:/laragon/etc/hr-management.oracle.env' );
```

File `.env` harus berisi pasangan `KEY=VALUE`, satu per baris. Baris komentar penuh diawali `#`; nilai boleh dibungkus tanda kutip tunggal atau ganda. Pastikan PHP dapat membaca file tersebut. Untuk produksi, lebih baik gunakan environment variable yang dikelola server. Pastikan ekstensi PHP `pdo_oci` tersedia. Koneksi dibuat secara lazy dan digunakan bersama selama request; operasi data gagal dengan pesan error jika Oracle belum terkonfigurasi atau tidak tersedia. Page tidak boleh membuat koneksi atau menulis query langsung. Tidak ada fallback penyimpanan database sandbox.

Untuk instruksi setup lokal langkah demi langkah di Windows dan macOS, termasuk pemeriksaan PHP CLI/web dan troubleshooting, lihat [Panduan setup Oracle lokal](docs/oracle-env-setup.md). Setiap developer harus memakai path env lokalnya sendiri; jangan menyalin kredensial ke repository.

Folder `public/` dan `admin/` dipertahankan sebagai bridge kompatibilitas agar migrasi template dapat dilakukan per halaman tanpa mengubah output.

## Workflow Tim Setelah Git Pull

Source code route dan template disimpan di Git, sedangkan page WordPress, login admin, permalink, cache, dan option `recruitment_public_page_id` berada di database lokal masing-masing developer. Jalankan langkah berikut setelah mengambil perubahan:

```powershell
git switch master
git pull --ff-only origin master
```

Pastikan plugin aktif di WordPress dan halaman Career berisi shortcode berikut:

```text
[recruitment_careers]
```

Kemudian buka **Settings > Permalinks** dan klik **Save Changes**, lalu bersihkan cache browser/plugin. **Screen Explorer** menyediakan preview halaman front-end, termasuk halaman akun pelamar, tanpa perlu login. Preview dibatasi ke route front-end yang didukung; route admin tetap memerlukan kapabilitas WordPress. Preview hanya menampilkan data contoh dan menonaktifkan pengiriman form. Preview manual memakai parameter berikut:

```text
?daw_ui_preview=1
```

Mode preview hanya untuk tampilan dan tidak mengirim lamaran, membuat akun, atau menyimpan perubahan profil. Di luar preview, pelamar menggunakan akun; halaman input tracking dan halaman hasil berbasis token tidak lagi digunakan. Riwayat dan status lamaran menampilkan pemberitahuan integrasi sampai data akun tersambung ke Oracle. Booking wawancara juga dinonaktifkan sementara. Kolom dan indeks token Oracle dipertahankan dan tidak diubah oleh perubahan front-end ini.

## Catatan Migrasi

`index.php` tetap menjadi entry point sandbox. Pada WordPress, bootstrap utama menggunakan hooks, shortcode, dan `Recruitment_Router`. Asset didaftarkan melalui `wp_enqueue_style()` dan `wp_enqueue_script()`, sedangkan akses data diarahkan melalui `database/class-database.php`.