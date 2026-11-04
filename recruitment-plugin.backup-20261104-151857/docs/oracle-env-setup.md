# Setup Oracle environment lokal (Windows dan macOS)

Panduan ini menyiapkan koneksi Oracle untuk Recruitment Plugin di lingkungan
WordPress lokal. Masing-masing developer perlu memiliki akses jaringan ke
server Oracle dan menerima host, port, service name, username, serta password
dari administrator database.

## Alur koneksi

```text
WordPress memuat wp-config.php
  -> RECRUITMENT_DOTENV_PATH menunjuk file rahasia di luar document root
  -> Recruitment_Oracle_Connection membaca tiga nilai konfigurasi
  -> PDO menggunakan driver oci (PDO_OCI)
  -> Oracle menerima koneksi
```

Database WordPress (konstanta `DB_*`) tetap terpisah. Plugin menggunakan Oracle
untuk data rekrutmen. Mengaktifkan `oci8` saja tidak cukup: plugin memerlukan
PDO driver bernama `oci`.

## 1. Dapatkan detail koneksi

Minta detail berikut kepada administrator Oracle:

- Hostname atau alamat server Oracle
- Port listener (sering kali `1521`, tetapi konfirmasikan)
- Service name (konfirmasikan; jangan menebak SID)
- Username dan password database
- Konfirmasi VPN/firewall mengizinkan koneksi dari perangkatmu

Contoh DSN menggunakan service name:

```text
oci:dbname=//HOST:1521/SERVICE_NAME;charset=AL32UTF8
```

Ganti `HOST`, `1521`, dan `SERVICE_NAME` dengan nilai yang diberikan. Jangan
memakai contoh tersebut sebagai kredensial nyata.

## 2. Pastikan PHP yang dipakai WordPress memiliki PDO_OCI

PDO_OCI adalah ekstensi PHP yang menyediakan PDO driver Oracle `oci`. PHP harus
memiliki ekstensi tersebut dan library Oracle Client yang sesuai. Instalasi
ekstensi harus cocok dengan PHP yang benar-benar menjalankan WordPress, bukan
hanya PHP di terminal.

### Windows

1. Buka terminal Laragon dan catat versi/build PHP serta konfigurasi CLI:

   ```cmd
   php -v
   php --ini
   ```

2. Periksa apakah PDO_OCI sudah dimuat dan driver `oci` terdaftar:

   ```cmd
   php -m | findstr /i "pdo oci"
   php -r "print_r(PDO::getAvailableDrivers());"
   ```

   Hasil driver harus mencantumkan `oci`. Nama modul `PDO_OCI` pada `php -m`
   saja belum membuktikan bahwa driver terdaftar; verifikasi daftar driver juga.

3. Jika belum ada, identifikasi build PHP Laragon yang aktif (versi PHP,
   x64/x86, TS/NTS, dan compiler). Gunakan paket/binary PDO_OCI yang benar-benar
   cocok dengan build itu atau siapkan build sesuai dokumentasi PHP. Jangan
   menyalin DLL `pdo_oci` dari versi PHP atau arsitektur lain.
4. Pastikan Oracle Instant Client yang kompatibel tersedia dan dapat ditemukan
   oleh PHP web server melalui konfigurasi `PATH`. Tambahkan konfigurasi
   extension pada `php.ini` yang dipakai web server, bukan hanya CLI.
5. Restart web server/Laragon setelah perubahan. Lanjutkan ke pemeriksaan PHP
   web di bawah.

### macOS

1. Periksa PHP aktif dan konfigurasi CLI:

   ```sh
   php -v
   php --ini
   php -m | grep -Ei 'pdo|oci'
   php -r 'print_r(PDO::getAvailableDrivers());'
   ```

   Daftar driver harus mencantumkan `oci`.

2. Jika `oci` belum ada, tentukan PHP yang menjalankan WordPress (misalnya
   PHP dari Homebrew, MAMP, Herd, atau Valet). Ikuti panduan instalasi PDO_OCI
   yang sesuai dengan versi PHP dan cara instalasi PHP tersebut. Pada macOS,
   extension sering harus dikompilasi untuk PHP aktif.
3. Siapkan Oracle Instant Client yang sesuai, termasuk paket SDK/development
   yang diperlukan saat membangun extension. Pastikan arsitektur PHP, extension,
   dan Oracle Client cocok (misalnya arm64 dengan arm64, atau x86_64 dengan
   x86_64).
4. Aktifkan extension pada `php.ini` yang digunakan PHP web server. Pastikan
   proses web juga dapat menemukan library Oracle Client; konfigurasi shell
   terminal saja belum tentu diwarisi service web.
5. Restart PHP/web server, lalu periksa kembali dari CLI dan browser.

Instruksi instalasi extension bergantung pada build PHP. Gunakan dokumentasi
[PDO_OCI PHP](https://www.php.net/manual/en/ref.pdo-oci.php),
[PECL PDO_OCI](https://pecl.php.net/package/PDO_OCI), dan
[Oracle Instant Client](https://www.oracle.com/database/technologies/instant-client.html).
PDO_OCI disediakan melalui PECL pada PHP 8.4 dan yang lebih baru; untuk versi
lebih lama, ikuti petunjuk versi PHP yang digunakan.

### Periksa PHP web, bukan hanya CLI

PHP CLI dan WordPress dapat memakai versi PHP atau `php.ini` yang berbeda.
Untuk memeriksa PHP web, buat sementara file bernama `pdo-oci-check.php` di
document root WordPress:

```php
<?php
header( 'Content-Type: text/plain; charset=utf-8' );

echo 'PHP version: ', PHP_VERSION, PHP_EOL;
echo 'Loaded php.ini: ', php_ini_loaded_file() ?: '(none)', PHP_EOL;

if ( ! class_exists( 'PDO' ) ) {
    echo 'PDO is NOT loaded', PHP_EOL;
} else {
    echo 'PDO drivers: ', implode( ', ', PDO::getAvailableDrivers() ), PHP_EOL;
}
```

Buka file itu melalui domain lokal WordPress. Hasilnya harus menunjukkan driver
`oci`. Setelah pengecekan, **hapus file tersebut segera**; jangan tinggalkan
file pemeriksaan atau `phpinfo()` yang dapat diakses melalui web.

## 3. Buat file env lokal, di luar document root

Salin format dari [`.env.example`](../../.env.example), lalu simpan dengan tiga
baris berikut. Ganti nilai placeholder dengan detail dari administrator Oracle:

```dotenv
RECRUITMENT_ORACLE_DSN="oci:dbname=//HOST:1521/SERVICE_NAME;charset=AL32UTF8"
RECRUITMENT_ORACLE_USER="YOUR_ORACLE_USERNAME"
RECRUITMENT_ORACLE_PASSWORD="YOUR_ORACLE_PASSWORD"
```

Contoh lokasi:

- Windows/Laragon: `D:/laragon/etc/hr-management.oracle.env`
- macOS: `/Users/USERNAME/.config/hr-karir/hr-management.oracle.env`

Buat file di luar folder publik WordPress. Di macOS, batasi permission file
agar hanya akun yang perlu dapat membacanya (contoh: `chmod 600 <path-file>`).
Pastikan user yang menjalankan PHP web server tetap memiliki akses baca. Jangan
commit file ini, mengirimkannya ke chat, atau menyimpan kredensial di file
plugin. Aturan `.gitignore` mengecualikan file `.env` lokal, tetapi tetap cek
sebelum commit.

## 4. Hubungkan path file env ke WordPress

Tambahkan konstanta berikut di `wp-config.php`, pada bagian sebelum
`require_once ABSPATH . 'wp-settings.php';`. Gunakan path lokal masing-masing
developer.

Windows:

```php
define( 'RECRUITMENT_DOTENV_PATH', 'D:/laragon/etc/hr-management.oracle.env' );
```

macOS:

```php
define( 'RECRUITMENT_DOTENV_PATH', '/Users/USERNAME/.config/hr-karir/hr-management.oracle.env' );
```

jangan mendefinisikan variabel `RECRUITMENT_ORACLE_*` sebagai konstanta di tempat lain
jika ingin file env menjadi sumber konfigurasi. Prioritas plugin adalah
konstanta PHP, environment variable proses, file yang ditunjuk konstanta ini,
lalu option WordPress `recruitment_oracle_config`; nilai dengan prioritas lebih
tinggi akan mengalahkan nilai di file env.

## 5. Uji koneksi

Setelah PDO driver web menampilkan `oci` dan file env terisi, pastikan plugin
aktif di WordPress. Untuk tes lokal yang eksplisit, buat file sementara
`oracle-connection-check.php` di root WordPress (sejajar dengan `wp-load.php`)
dengan isi berikut:

```php
<?php
header( 'Content-Type: text/plain; charset=utf-8' );
require_once __DIR__ . '/wp-load.php';

if ( ! class_exists( 'Recruitment_Oracle_Connection' ) ) {
    http_response_code( 500 );
    exit( 'Recruitment Plugin belum aktif atau class koneksi tidak termuat.' );
}

try {
    $connection = ( new Recruitment_Oracle_Connection() )->get_connection();
    $connection->query( 'SELECT 1 FROM DUAL' );
    echo 'Oracle connection: OK';
} catch ( Throwable $exception ) {
    error_log( 'Temporary Oracle connection check failed: ' . $exception->getMessage() );
    http_response_code( 500 );
    echo 'Oracle connection failed. Periksa PHP/web server error log.';
}
```

Buka URL file itu hanya di lingkungan lokal, misalnya
`http://hr-karir.test/oracle-connection-check.php`. Jika gagal, periksa log
PHP/web server untuk detail dan jangan menyalin kredensial ke chat. **Hapus
file tes segera setelah selesai.** Jangan membuat endpoint debug seperti ini
di server publik atau mengeluarkan password, DSN, maupun pesan exception mentah.

Driver `oci` yang tersedia hanya memastikan PHP memiliki driver; itu belum
membuktikan host dapat dijangkau, kredensial benar, atau skema Oracle sudah
sesuai. Migrasi skema, jika diperlukan, harus dilakukan oleh pihak yang
berwenang pada database target.

## Troubleshooting

| Gejala | Yang perlu diperiksa |
| --- | --- |
| `oci` tidak ada pada `PDO::getAvailableDrivers()` | PDO_OCI belum dimuat oleh PHP yang diperiksa. Cek versi PHP dan `php.ini`, lalu restart proses PHP. |
| CLI menampilkan `oci`, browser tidak | CLI dan PHP web berbeda versi atau konfigurasi. Periksa output file sementara di bagian 2 dan aktifkan extension pada PHP web yang benar. |
| `PDO_OCI` gagal dimuat atau DLL/library tidak ditemukan | Cocokkan versi, arsitektur, TS/NTS, compiler, dan Oracle Client. Periksa log PHP/web server. |
| Konfigurasi Oracle incomplete | Periksa path `RECRUITMENT_DOTENV_PATH`, permission baca, ejaan ketiga key, dan apakah ada konstanta/environment variable yang mengambil prioritas lebih tinggi. |
| Koneksi timeout atau network error | Periksa VPN, DNS, firewall, host, port, serta akses jaringan ke listener Oracle. |
| Login/layanan Oracle ditolak | Konfirmasi username, password, service name, dan status akun ke administrator Oracle. Jangan tempel password di log atau chat. |
| Koneksi berhasil, tetapi tabel/kolom tidak ditemukan | Skema atau migrasi belum sesuai. Minta pemilik database meninjau dan menerapkan migrasi yang diperlukan. |
