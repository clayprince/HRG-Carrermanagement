# Recruitment Plugin

Custom WordPress plugin untuk sistem rekrutmen dengan dua area utama:

- **Public / Applicant Area** — halaman Karir terbuka untuk umum; pendaftaran, profil, formulir lamaran, dan proses pelamar menggunakan akun WordPress ber-role `daw_applicant`.
- **Admin / HRD Area** — dashboard rekrutmen (`/recruitment-admin/`) dengan autentikasi.

## Struktur Modular

```text
recruitment-plugin/
├── admin/                   # Dashboard HR, halaman, dan komponen admin
├── assets/                  # CSS, JavaScript, gambar, dan ikon
├── components/              # Komponen bersama
├── database/
│   ├── connection/          # Koneksi Oracle lazy bersama
│   ├── migrations/          # Migrasi skema Oracle
│   └── class-database.php   # Gateway query/transaksi domain rekrutmen
├── docs/                    # Panduan setup dan pengembangan
├── includes/                # Bootstrap, autentikasi, dan layanan
├── pages/                   # Halaman bersama
├── public/                  # Loader, halaman, dan komponen publik
├── routes/                  # Registry dan renderer route
├── templates/               # Template email dan error
├── index.php                # Entry point sandbox/compatibility
└── recruitment-plugin.php   # Bootstrap plugin WordPress
```

Alur request WordPress dimulai dari `recruitment-plugin.php`, memuat
`includes/class-plugin.php` dan `routes/class-router.php`, lalu meneruskan
request ke halaman dan komponen yang sesuai.

Oracle recruitment data uses the dedicated PDO_OCI connection, separate from
the WordPress database configured by `DB_*` in `wp-config.php`. For local
configuration, keep the Oracle env file outside the repository and document
root. Copy the format from [`.env.example`](../.env.example), supply credentials
locally, and set `RECRUITMENT_DOTENV_PATH` in `wp-config.php` before the plugin
loads. Do not put credential values in this repository or in AI prompts.

The Oracle connection reads `RECRUITMENT_ORACLE_DSN`,
`RECRUITMENT_ORACLE_USER`, and `RECRUITMENT_ORACLE_PASSWORD` according to the
priority and behavior documented in
[`database/README.md`](database/README.md). The PHP `pdo_oci` driver must be
available. Connection creation is lazy and shared for the request. Pages must
not create their own PDO connection or embed Oracle queries; use the database
gateway and existing service patterns.

Use WordPress APIs for WordPress user accounts, roles, and options. Do not use
Oracle for those records unless the feature requirements and existing data
model explicitly call for it.

Untuk instruksi setup lokal langkah demi langkah di Windows dan macOS, termasuk pemeriksaan PHP CLI/web dan troubleshooting, lihat [Panduan setup Oracle lokal](docs/oracle-env-setup.md). Setiap developer harus memakai path env lokalnya sendiri; jangan menyalin kredensial ke repository.

Folder `public/` dan `admin/` memuat loader serta halaman aktif plugin. Jangan
menganggapnya sekadar bridge lama; ikuti struktur dan pemanggil yang benar-benar
digunakan sebelum memindahkan template.

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

Mode preview hanya untuk tampilan dan tidak mengirim lamaran, membuat akun,
atau menyimpan perubahan profil. Perilaku tracking, status, dan booking dapat
berubah mengikuti implementasi; periksa route dan layanan terkait sebelum
mengubah dokumentasi atau alur tersebut. Lamaran publik yang tersimpan di
Oracle dikelola melalui database gateway.

## Catatan Migrasi

`index.php` tetap menjadi entry point sandbox. Pada WordPress, bootstrap utama
menggunakan hooks, shortcode, dan `Recruitment_Router`. Asset didaftarkan
melalui `wp_enqueue_style()` dan `wp_enqueue_script()`. Untuk Oracle, baca
[`database/README.md`](database/README.md) sebelum menambah atau mengubah CRUD.