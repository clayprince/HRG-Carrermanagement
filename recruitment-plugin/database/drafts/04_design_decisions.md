# Keputusan desain dan asumsi untuk DDL Oracle

Dokumen ini mencatat pertanyaan desain yang perlu disetujui tim dan asumsi teknis yang dipakai oleh `database/oracle/05_create_purple_tables_final.sql`. Asumsi tersebut membuat DDL konkret, tetapi bukan bukti keputusan bisnis atau persetujuan final tim. Nilai ENUM/status dan relasi yang tidak didukung bukti tidak dikarang.

## P0 — Menghalangi `CREATE TABLE`

### 1. Primary key: tipe dan pembentukan ID

ERD menandai PK untuk setiap tabel. Untuk 12 kolom ID berikut, tipe tidak ditulis:

| Tabel | Kolom PK persis di ERD | Pertanyaan untuk tim |
|---|---|---|
| `jawaban_psikotes` | `jawaban_psikotes_id` | Tipe Oracle apa? ID dibuat oleh identity/sequence, aplikasi, atau cara lain? |
| `Persyaratan_Lowongan` | `persyaratan_lowongan_id` | Tipe Oracle apa? Strategi pembentukan ID? |
| `status_request_lowongan` | `status_lowongan_id` | Tipe Oracle apa? Strategi pembentukan ID? |
| `jadwal_psikotes` | `jadwal_psikotes_id` | Tipe Oracle apa? Strategi pembentukan ID? |
| `template_email_rekrutment` | `template_email_id` | Tipe Oracle apa? Strategi pembentukan ID? |
| `email_rekrutment` | `email_rekrutmen_id` | Tipe Oracle apa? Strategi pembentukan ID? |
| `rekapan_keputusan` | `rekapan_keputusan_id` | Tipe Oracle apa? Strategi pembentukan ID? |
| `jadwal_kandidat` | `jadwal_kandidat_id` | Tipe Oracle apa? Strategi pembentukan ID? |
| `tes_psikologi` | `tes_psikologi_id` | Tipe Oracle apa? Strategi pembentukan ID? |
| `pertanyaan_psikotes` | `peertanyaan_psikotes_id` | Tipe Oracle apa? Strategi pembentukan ID? Apakah ejaan kolom PK ini memang disengaja? |
| `konfiguasi_global_psikotes` | `konfigurasi_pisikotes` | Tipe Oracle apa? Strategi pembentukan ID? Apakah perbedaan nama tabel/kolom disengaja? |
| `slot_wawancara` | `slot_wawancara_id` | Tipe Oracle apa? Strategi pembentukan ID? |

**Pertanyaan untuk seluruh 19 PK:** apakah semua ID dibuat oleh database atau disediakan aplikasi? Untuk tujuh PK bertipe `int` di ERD, apakah tipe Oracle dan strategi pembentukannya sama dengan 12 ID yang belum bertipe? PK tidak boleh dibuat final sebelum jawabannya disetujui.

### 2. Empat label kolom duplikat

Nama sama muncul dua kali di masing-masing tabel berikut. Oracle tidak dapat membuat dua kolom bernama sama di satu tabel. Tim perlu memastikan apakah ada satu kolom yang tercatat ganda, atau dua atribut berbeda yang perlu nama berbeda.

| Tabel | Label duplikat persis di ERD |
|---|---|
| `Persyaratan_Lowongan` | `jenis_Persyaratan` |
| `jadwal_psikotes` | `lokasi` |
| `request_lowongan` | `Hiring_user pic` |
| `request_lowongan` | `status_karyawan` |

Jangan menghapus salah satu kemunculan atau mengganti nama tanpa keputusan pemilik schema.

### 3. Tipe Oracle, ukuran, precision/scale, dan nullability

Untuk setiap kolom dari total 214 entri, tim perlu memutuskan hal-hal berikut yang belum dinyatakan secara eksplisit:

- Pemetaan tipe Oracle yang tepat untuk label ERD seperti `int`, `string`/`String`, `text`, `enum`, `boolean`, `time`, `float`, `decimal`, `file`, `date`, `timestamp`, dan `VARCHAR`.
- Panjang karakter untuk `string`, `VARCHAR` tanpa panjang, dan teks terbatas; batas `VARCHAR2` yang diperlukan; serta apakah `text`/`file` berarti konten, nama/path, atau referensi penyimpanan. Ini pertanyaan desain, bukan rekomendasi implementasi.
- Precision dan scale semua `decimal`/`float`, termasuk `nilai_kelulusan`, `nilai_pertanyaan`, dan `nilai`.
- Representasi nilai boolean dan waktu tanpa tanggal di Oracle 21c.
- Nullability tiap kolom non-PK. ERD hanya secara eksplisit menandai `jumlah_kebutuhan` pada `detail_info_lowongan` dan `batas_lamaran` pada `kolom_landing_pendaftaran` sebagai `nullable`; tanda itu perlu dipertahankan atau dikoreksi oleh tim. Nullability kolom lain belum dipastikan oleh diagram.
- Makna dan tipe `created_at`/`update_at` yang di ERD bertipe `int` atau `string`, serta apakah label `- updated_at` mencakup tanda minus atau hanya artefak diagram.
- Tipe, ukuran, dan kebijakan hash/penyimpanan untuk `hr_user.password`; jangan menetapkan penyimpanan plaintext.

Tipe atau ukuran yang sudah tertulis di ERD tetap dicatat di [01_erd_purple_audit.md](./01_erd_purple_audit.md); pemetaan ke Oracle memerlukan persetujuan.

## P1 — Foreign key dan constraint

Tidak ada target atau arah yang boleh dianggap final hanya dari kemiripan nama. Untuk setiap relasi yang disetujui, tim perlu memastikan child/parent, kolom target yang merupakan PK/unique key, kardinalitas, optionality/nullability, dan tindakan referensial yang diinginkan. Daftar di bawah mengelompokkan bukti ERD, bukan menetapkan FK final.

### Kandidat target di antara 19 tabel ungu

| Kolom kandidat child | Kandidat target | Mengapa perlu keputusan |
|---|---|---|
| `jawaban_psikotes.pertanyaan_psikotes_id` | `pertanyaan_psikotes.peertanyaan_psikotes_id` | ERD menandai FK, tetapi nama kolom target berbeda dari `pertanyaan_psikotes_id`. |
| `template_email_rekrutment.email_rekruitmen_id` | `email_rekrutment.email_rekrutmen_id` | Ejaan berbeda; konektor terlihat menuju arah yang bertentangan dengan label FK. |
| `email_rekrutment.template_email_id` | `template_email_rekrutment.template_email_id` | FK ditandai; konektor/relasi email-template tampak tidak konsisten. |
| `detail_info_lowongan.request_lowongan_id` | `request_lowongan.request_lowongan_id` | FK ditandai dan nama target cocok, tetapi konektor langsung tidak terlihat. |
| `detail_info_lowongan.kolom_pendaftaran_id` | `kolom_landing_pendaftaran.kolom_pendaftaran_id` | FK ditandai dan konektor terlihat; arah/kardinalitas tetap perlu dikonfirmasi. |

Konektor tanpa FK marker yang eksplisit dan perlu keputusan: `pertanyaan_psikotes.tes_psikologi_id`, `jadwal_kandidat.jadwal_psikotes_id`, `hr_id` pada tabel-tabel yang terhubung ke `hr_user`, serta `pilihan_fields_pendaftaran.bagian_pendaftaran_id`.

### Kandidat target di luar 19 tabel ungu

| Kolom kandidat child | Kandidat target di ERD | Yang perlu dikonfirmasi |
|---|---|---|
| `email_rekrutment.kandidat_id` | `Profil_Kandidat` (pink) | Nama PK target tercatat sebagai `kandidat id`; pastikan nama kolom dan objek target final. |
| `detail_info_lowongan.departement_id` | `Departemen` (pink) | ERD menulis `departement_id`; target disebut sebagai `departemen_id`. Pastikan ejaan dan kolom target. |
| `request_lowongan.departement_id` | `Departemen` (pink) | Pastikan nama tabel dan kolom target. |
| `kolom_landing_pendaftaran.departemen_id` | `Departemen` (pink) | FK marker terlihat; pastikan key target yang sebenarnya. |
| `Hasil_wawancara_user.kandidat_id` | `Profil_Kandidat` (pink) | Pastikan key target, ejaan, dan kardinalitas. |
| `Hasil_wawancara_user.departemen_id` | `Departemen` (pink) | ERD menampilkan dua konektor ke `Departemen`; tentukan relasi mana yang diwakili FK ini. |
| `Hasil_wawancara_user.status_kandidat_id` | `Status_Kandidat` (pink) | Pastikan key target dan optionality dari konektor `zero-to-one`. |
| `slot_wawancara.kandidat_id` | Kandidat di `Profil_Kandidat` (pink) | Pastikan key target dan relasi. |

FK ke tabel non-ungu hanya boleh dirancang setelah objek dan key targetnya dikonfirmasi terpisah; draft ini tidak meminta pembuatan tabel non-ungu.

### Target belum jelas atau konflik relasi

- `slot_wawancara.lowongan_id` ditandai FK tetapi target tabel/key tidak dapat dipastikan.
- `status_request_lowongan.status_id` dan `permintaan_id` tidak memiliki target FK yang jelas di ERD.
- `pilihan_fields_pendaftaran.field_id` tidak memiliki target yang jelas.
- Relasi `email_rekrutment` ↔ `template_email_rekrutment` berpotensi sirkular/terbalik bila kedua FK marker diterapkan; tentukan arah dan kebutuhan relasi.
- Konektor `kolom_landing_pendaftaran` ↔ `detail_info_lowongan` menampilkan optionality/kardinalitas yang perlu dipastikan terhadap FK marker pada `detail_info_lowongan.kolom_pendaftaran_id`.
- Relasi `konfiguasi_global_psikotes` ↔ `hr_user` ditampilkan `mandatory one` di kedua ujung, sedangkan kolom `hr_id` tidak diberi FK marker; tentukan model satu-ke-satu dan lokasi FK.
- Relasi `hr_user` ke tabel lain tampak pada konektor, tetapi kolom `hr_id` di tabel-tabel itu tidak diberi penanda FK. Konfirmasi masing-masing relasi sebelum constraint dibuat.
- Tentukan PK/unique key, nullability, nama constraint, dan tindakan `ON DELETE`/`ON UPDATE` bila relevan. Tidak ada tindakan referensial yang ditentukan oleh draft.

### Nilai status dan ENUM

ERD menyebut tipe `enum`, tetapi tidak mencantumkan daftar nilai untuk kolom-kolom berikut. Pemilik domain harus memberikan nilai persis, aturan transisi jika berlaku, dan apakah nilai dapat diperluas:

- `Persyaratan_Lowongan.jenis_Persyaratan` (muncul dua kali) dan `kategori_Persyaratan`
- `status_request_lowongan.status_pengajuan`
- `jadwal_psikotes.metode_psikotes`
- `template_email_rekrutment.kategori_template`
- `email_rekrutment.status_pengiriman`
- `rekapan_keputusan.nama_departement`, `rekomendasi_user`, dan `keputusan_akhir`
- `Hasil_wawancara_user.rekomendasi` dan `status_kandidat`
- `jadwal_kandidat.hasil_hr` dan `status_wawancara`
- `pertanyaan_psikotes.jenis_pertanyaan`
- `detail_info_lowongan.prioritas`
- `request_lowongan.prioritas`
- `kolom_landing_pendaftaran.nama_departemen`, `nama_dealer`, `kategori_kj`, dan `persyaratan_utama`
- `slot_wawancara.metode_wawancara`

Jangan mengambil daftar nilai dari status di gateway `daw_*` atau membuat `CHECK` constraint sebelum tim menyetujui domain tersebut.

## P2 — Konsistensi penamaan

### Identifier dengan spasi atau karakter khusus

Tim perlu menetapkan apakah nama berikut adalah identifier literal atau tampilan/format diagram, dan memilih kebijakan konsisten: quoted identifiers dengan kapitalisasi persis ERD, atau nama unquoted Oracle dengan aturan normalisasi yang disepakati.

- `tes_psikologi.jumlah soal`
- `request_lowongan.Hiring_user pic`
- `Profil_Kandidat.kandidat id` (tabel di luar cakupan ungu, terkait kandidat FK)
- `Persyaratan_Lowongan.- updated_at` — pastikan apakah `- ` bagian nama atau artefak bullet.
- `Users AD (Admin Departemen)` muncul sebagai nama di bagian lain ERD; pastikan apakah itu nama tabel, label UI, atau entitas di luar cakupan sebelum menggunakannya sebagai target.

Identifier duplikat tetap blocker P0, terlepas dari kebijakan quoting.

### Nama yang mungkin typo atau tidak konsisten

Jangan koreksi otomatis. Konfirmasikan apakah pasangan/variasi berikut disengaja:

- Tabel `konfiguasi_global_psikotes` dan PK `konfigurasi_pisikotes`.
- PK `pertanyaan_psikotes.peertanyaan_psikotes_id` versus FK `jawaban_psikotes.pertanyaan_psikotes_id`.
- `email_rekrutment.email_rekrutmen_id` versus `template_email_rekrutment.email_rekruitmen_id`.
- `departement_id`, `nama_departement` versus `departemen_id`, `nama_departemen`.
- `Hasil_wawancara_user` ungu versus `Hasil_Wawancara_user` pink; pastikan apakah dua entitas berbeda. Oracle unquoted identifiers tidak membedakan kapitalisasi, sedangkan quoted identifiers membedakannya.
- `create_by`, `created_by`, `created_at`, `update_at`, `updated_at`, serta label `- updated_at`; tentukan ejaan dan makna waktu/audit field tanpa mengubah label ERD sebelum persetujuan.
- `jadwal_kandidat.lokasi_wawancara` bertipe `time` pada ERD walau nama menyiratkan lokasi; konfirmasi apakah label/tipenya yang dimaksud.
- `jadwal_psikotes.dokumen_yang_dibawa` tertulis `boolean/string`; tentukan makna dan tipe yang dimaksud.

## Gerbang persetujuan tim

File DDL aktif disusun memakai asumsi eksplisit di bawah, sesuai permintaan agar tersedia DDL konkret. Ini tidak menggantikan persetujuan tim: tinjau keputusan P0 sebelum eksekusi, tinjau FK P1 sebelum menganggap relasi final, dan tinjau nama P2 sebelum mengunci kontrak yang dipakai aplikasi. Tidak ada perubahan ke `DAW_USER` atau objek lain di luar cakupan.

## Asumsi yang dipakai dalam DDL final untuk ditinjau tim

Bagian ini melengkapi daftar pertanyaan di atas. Keputusan teknis berikut dipakai agar tersedia DDL aktif untuk ditinjau; **belum dikonfirmasi oleh tim**.

### P0 — Asumsi agar tabel dapat dibuat

| Area | Asumsi pada DDL | Alasan / dampak |
|---|---|---|
| Kebijakan nama identifier | Semua nama tabel dan kolom ditulis sebagai quoted identifiers Oracle, mempertahankan kapitalisasi yang dipilih di bawah. | Menghindari normalisasi huruf besar pada identifier campuran. Perubahan nama kolom khusus/duplikat tetap eksplisit. |
| PK semua tabel | Setiap PK adalah `NUMBER(19,0) GENERATED BY DEFAULT ON NULL AS IDENTITY`; setiap tabel memiliki PK constraint. | Satu strategi ID seragam untuk 19 tabel. Ini asumsi teknis, bukan pola yang telah dibuktikan dari schema aplikasi. |
| Integer non-ID | `int`/`Int` non-ID dipetakan ke `NUMBER(10,0)`. ID dan FK ID memakai `NUMBER(19,0)` agar tipe target sepadan. | Menyediakan domain integer Oracle tanpa menambahkan batasan nilai bisnis. |
| `string` / `String` | `VARCHAR2(255 CHAR)`. | Panjang 255 adalah asumsi praktis, perlu dikonfirmasi terhadap payload nyata. |
| `VARCHAR(n)` yang tertulis | `VARCHAR2(n CHAR)`; dengan demikian `varchar(50)`, `varchar(250)`, dan `VARCHAR(15)` menjadi ukuran karakter yang tertulis. | Menggunakan bentuk Oracle dan interpretasi panjang karakter. |
| `VARCHAR` tanpa panjang | `VARCHAR2(255 CHAR)`. | ERD tidak memberi panjang. |
| `text` | `CLOB`. | Menyediakan teks panjang; dampak akses/indexing perlu ditinjau aplikasi. |
| `enum` | `VARCHAR2(100 CHAR)`, tanpa `CHECK` constraint. | Nilai sah belum ada di ERD; DDL tidak membatasi atau mengarang nilai. |
| `boolean` | `NUMBER(1,0)`, tanpa `CHECK` constraint. | Representasi numerik yang kompatibel dengan Oracle 21c SQL table columns; batas nilai 0/1 belum ditegakkan. |
| `float` | `NUMBER(18,6)`. | Asumsi numerik desimal yang menghindari pemetaan ke binary floating point; precision/scale perlu dikonfirmasi. |
| `decimal` | `NUMBER(18,4)`. | Precision/scale tidak ditulis di ERD; nilai yang dipilih perlu ditinjau. |
| `time` | `VARCHAR2(8 CHAR)`, diasumsikan sebagai teks waktu lokal `HH24:MI:SS`; tidak ada format/check constraint. | Oracle 21c tidak menyediakan SQL `TIME` standalone. Perlu dikonfirmasi, khususnya untuk `jadwal_kandidat.lokasi_wawancara`. |
| `date` / `timestamp` | `DATE` / `TIMESTAMP(6)`. | `DATE` menyimpan tanggal dan waktu pada Oracle; penggunaan aktual perlu ditinjau. |
| `boolean/string` | `VARCHAR2(1000 CHAR)` untuk `jadwal_psikotes.dokumen_yang_dibawa`. | Memilih interpretasi teks agar daftar/nama dokumen bisa ditampung; belum memastikan makna field. |
| Nullability | Semua kolom non-PK dibiarkan nullable; PK menjadi `NOT NULL` melalui primary key constraint. | ERD tidak menetapkan `NOT NULL` untuk kolom lain. Dua label `nullable` di ERD konsisten dengan pilihan ini. |
| Password HR | `hr_user.password` memakai `VARCHAR2(255 CHAR)` sebagai kapasitas nilai/hash; DDL tidak menambahkan plaintext, default, atau aturan autentikasi. | ERD hanya menyebut `string`; kebijakan hash dan apakah tabel ini dipakai aplikasi tetap harus disepakati. |

### P0 — Koreksi label kolom agar identifier Oracle unik dan dapat dipakai

Nama sumber tetap tersedia pada tabel audit; file DDL memakai koreksi berikut. Keempat label duplikat menghasilkan nama kolom unik dengan sufiks `_2`; tidak ada kolom yang dibuang.

| Tabel | Label ERD | Nama pada DDL | Jenis perubahan |
|---|---|---|---|
| `Persyaratan_Lowongan` | `- updated_at` | `updated_at` | Menghapus awalan tanda minus/spasi yang ambigu. |
| `Persyaratan_Lowongan` | Kemunculan pertama dan kedua `jenis_Persyaratan` | `jenis_Persyaratan`, `jenis_Persyaratan_2` | Menambahkan sufiks pada kemunculan kedua supaya unik. |
| `jadwal_psikotes` | Kemunculan pertama dan kedua `lokasi` | `lokasi`, `lokasi_2` | Menambahkan sufiks pada kemunculan kedua supaya unik. |
| `request_lowongan` | Kemunculan pertama dan kedua `Hiring_user pic` | `Hiring_user_pic`, `Hiring_user_pic_2` | Mengganti spasi dengan underscore, lalu memberi sufiks pada kemunculan kedua. |
| `request_lowongan` | Kemunculan pertama dan kedua `status_karyawan` | `status_karyawan`, `status_karyawan_2` | Menambahkan sufiks pada kemunculan kedua supaya unik. |
| `tes_psikologi` | `jumlah soal` | `jumlah_soal` | Mengganti spasi dengan underscore. |

Sufiks `_2` hanya membuat struktur SQL dapat dibentuk; makna dua kolom dengan label sumber sama tetap perlu dipetakan oleh tim/aplikasi. DDL tidak mengklaim keduanya punya makna yang berbeda.

### P1 — FK yang dipasang dan relasi yang ditunda

DDL memasang 8 FK internal yang didukung oleh marker/konektor ERD dengan target yang dapat ditentukan di antara 19 tabel ungu. Tipe PK dan FK disamakan ke `NUMBER(19,0)`. FK tidak diberi `ON DELETE` atau `ON UPDATE`.

| Child | Parent | Dasar penggunaan / catatan |
|---|---|---|
| `Persyaratan_Lowongan.hr_id` | `hr_user.hr_id` | Konektor ERD one-to-many; label kolom ID cocok. |
| `status_request_lowongan.hr_id` | `hr_user.hr_id` | Konektor ERD one-to-many; label kolom ID cocok. |
| `rekapan_keputusan.hr_id` | `hr_user.hr_id` | Konektor ERD one-to-many; label kolom ID cocok. |
| `pertanyaan_psikotes.tes_psikologi_id` | `tes_psikologi.tes_psikologi_id` | Konektor ERD one-to-many dan nama PK yang cocok; FK tidak diberi marker teks. |
| `jadwal_kandidat.jadwal_psikotes_id` | `jadwal_psikotes.jadwal_psikotes_id` | Konektor ERD one-to-many dan nama PK yang cocok; FK tidak diberi marker teks. |
| `jawaban_psikotes.pertanyaan_psikotes_id` | `pertanyaan_psikotes.peertanyaan_psikotes_id` | FK marker dan konektor ERD. Ejaan PK yang berbeda dipertahankan; perlu konfirmasi koreksi typo. |
| `email_rekrutment.template_email_id` | `template_email_rekrutment.template_email_id` | FK marker di tabel email, konektor ke template, dan nama target yang cocok. |
| `detail_info_lowongan.kolom_pendaftaran_id` | `kolom_landing_pendaftaran.kolom_pendaftaran_id` | FK marker, konektor, dan nama PK target cocok; arah/kardinalitas konektor tetap perlu review. |

FK berikut sengaja tidak dibuat:

- `request_lowongan.hr_id`: konektor aktual berawal pada baris `hr_user.nama_pengguna` dan berakhir pada `request_lowongan.status_karyawan`; ini tidak mendukung FK `hr_id` ke `hr_id`. Konektor terpisah dari tabel non-ungu `Departemen` menuju `request_lowongan.departement_id` juga bukan relasi ke `hr_user`.
- `bagian_pendaftaran.hr_id`: konektor berawal pada `hr_user.nama_pengguna`, bukan PK `hr_user.hr_id`; target parent untuk kolom `hr_id` tidak dapat dipastikan.
- `jadwal_psikotes.hr_id`: konektor berawal pada `hr_user.password`, bukan PK `hr_user.hr_id`; target parent untuk kolom `hr_id` tidak dapat dipastikan.
- `pilihan_fields_pendaftaran.bagian_pendaftaran_id`: konektor menghubungkan baris PK `bagian_pendaftaran_id` ke baris PK `pilihan_pendaftaran_id`; diagram tidak menghubungkan kolom kandidat FK `bagian_pendaftaran_id` di tabel pilihan.
- `detail_info_lowongan.request_lowongan_id`: kolom memiliki marker FK, tetapi tidak ditemukan konektor langsung ke `request_lowongan`; nama kolom saja tidak memastikan target. Kolom tetap dibuat tanpa constraint.
- Target non-ungu yang berada di luar cakupan DDL, termasuk kandidat `Profil_Kandidat`, `Departemen`, `Status_Kandidat`, `Pilihan_Respon_Psikotes`, dan tabel booking/wawancara. Tidak ada DDL untuk target tersebut.
- `template_email_rekrutment.email_rekruitmen_id`: marker dan konektor email-template bertentangan arah dengan `email_rekrutment.template_email_id`. Menambahkan keduanya akan membentuk siklus; hanya FK email-ke-template yang didukung bersama oleh marker/konektor.
- `konfiguasi_global_psikotes.hr_id`: konektor one-to-one dua arah ada, tetapi lokasi FK/unique key tidak jelas.
- `slot_wawancara.lowongan_id`, `status_request_lowongan.status_id`, `status_request_lowongan.permintaan_id`, dan `pilihan_fields_pendaftaran.field_id`: target belum diketahui.
- `email_rekrutment.kandidat_id` dan ID kandidat/departemen/status di `Hasil_wawancara_user` serta `slot_wawancara`: target di luar 19 tabel ungu.
- `detail_info_lowongan.departement_id`, `request_lowongan.departement_id`, dan `kolom_landing_pendaftaran.departemen_id`: target tabel `Departemen` berada di luar scope.

Relasi yang ditunda tidak dianggap tidak ada; hanya tidak ditegakkan sebagai FK dalam file final ini.

### P1 — Nilai status/ENUM

Semua field ERD bertipe `enum` memakai `VARCHAR2(100 CHAR)` tanpa daftar nilai dan tanpa `CHECK`. Ini menghindari nilai bisnis rekaan. Domain enum/status masih perlu disepakati sebelum constraint validasi ditambahkan.

### Status dan tinjauan tim

`05_create_purple_tables_final.sql` berisi DDL Oracle aktif yang memakai asumsi di atas dan ditujukan untuk ditinjau manual. “Final” pada nama file berarti DDL konkret tersedia, bukan bahwa semua asumsi telah disetujui. Tidak ada SQL yang dijalankan ke Oracle dalam penyusunan ini. Minta tim menyetujui/menyesuaikan asumsi, empat nama kolom hasil koreksi, pemetaan `peertanyaan_psikotes_id`, serta daftar FK sebelum eksekusi manual.
