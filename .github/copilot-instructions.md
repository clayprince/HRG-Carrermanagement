# Batasan Perubahan AI

Aturan ini wajib dipatuhi pada setiap permintaan perubahan di workspace ini.

## Scope pekerjaan

- Kerjakan hanya bagian yang secara spesifik diminta oleh pengguna.
- Jika pengguna meminta perubahan pada bagian A, ubah atau buat hanya bagian A tersebut.
- Jangan mengubah, menghapus, memindahkan, mengganti nama, memformat ulang, atau merapikan bagian lain yang tidak diminta.
- Jangan melakukan refactor, upgrade dependency, perubahan database, perubahan API, atau perubahan konfigurasi di luar scope tanpa izin eksplisit.
- Jangan mengganti implementasi atau hasil kerja yang sudah dibuat oleh anggota tim lain.
- Pertahankan perilaku, struktur, penamaan, gaya kode, dan konfigurasi yang sudah ada di luar area yang diminta.

## Sebelum mengubah file

- Identifikasi file, fungsi, komponen, atau blok yang benar-benar terkait dengan permintaan.
- Baca perubahan yang sudah ada dan perlakukan sebagai pekerjaan yang harus dipertahankan.
- Jika scope permintaan belum jelas, tanyakan klarifikasi sebelum mengedit.
- Jika solusi yang benar membutuhkan perubahan di luar scope, jelaskan file dan alasan perubahan tersebut, lalu minta persetujuan terlebih dahulu.

## Saat mengedit

- Buat perubahan sekecil mungkin dan hanya pada area yang diperlukan.
- Jangan menghapus perubahan pengguna atau anggota tim lain.
- Jangan melakukan perubahan massal atau formatting seluruh file jika tidak diminta.
- Jangan menyentuh file yang tidak berkaitan dengan permintaan.

## Setelah mengedit

- Validasi hanya area yang berubah dengan pemeriksaan atau test yang relevan.
- Laporkan file dan bagian yang diubah secara ringkas.
- Laporkan juga hal yang sengaja tidak diubah karena berada di luar scope.
- Jika menemukan masalah lain yang tidak berkaitan, jangan memperbaikinya otomatis; cukup laporkan.

## Aturan persetujuan

Jika ada keraguan apakah suatu perubahan termasuk scope, anggap perubahan tersebut di luar scope dan minta persetujuan pengguna. Tidak boleh memperluas pekerjaan berdasarkan asumsi.

## Pemeriksaan update GitHub dan database

Setiap kali menarik, menggabungkan, atau menerapkan update dari GitHub, periksa perubahan upstream sebelum menyelesaikan merge. Jika perubahan menyentuh database, migrasi, atau alur CRUD, lakukan pemeriksaan berikut:

- Periksa seluruh diff upstream untuk operasi baca/tulis, perubahan kolom atau tabel, migrasi, data backfill, serta pemanggil dan konsumen datanya. Jangan hanya mengandalkan pesan commit atau nama file.
- Bandingkan asumsi skema di kode dan migrasi dengan skema Oracle serta pola akses yang sudah digunakan proyek. Telusuri alur CRUD dari handler/route melalui service hingga `Recruitment_Database`, termasuk validasi input, query berparameter, transaksi untuk penulisan multi-record, dan penanganan error.
- Pastikan migrasi yang masuk memiliki nomor/nama unik dan urutan yang benar; periksa prasyarat skema, kemungkinan benturan dengan migrasi lokal atau upstream lain, kompatibilitas dengan data yang sudah ada, serta dampaknya pada versi kode sebelum dan sesudah update. Jangan menyelesaikan benturan migrasi dengan sekadar memilih satu sisi.
- Gabungkan perubahan dengan mempertahankan perilaku yang diperlukan dari kedua sisi. Jika skema, tujuan migrasi, status penerapan di lingkungan, atau kompatibilitas data tidak dapat dipastikan dari repository, hentikan perubahan database yang berisiko dan minta klarifikasi atau verifikasi pemilik skema.
- Jalankan pemeriksaan sintaks dan test yang relevan untuk CRUD serta migrasi jika tersedia. Laporkan asumsi, konflik, dan pemeriksaan yang belum dapat dilakukan.
- Jangan menjalankan migrasi atau DDL terhadap database lokal bersama, staging, atau produksi tanpa persetujuan eksplisit. Jangan membaca, menyalin, mencetak, atau meng-commit kredensial database.

Jika update tidak menyentuh database atau alur CRUD, jangan menambah atau mengubah migrasi tanpa kebutuhan yang jelas.
