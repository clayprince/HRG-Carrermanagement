-- Oracle XE 21c metadata-only verification for APP_USER.
-- All statements are SELECT-only. No DDL or DML is performed.
-- Connect as APP_USER to service XEPDB1 before running these checks.
-- IMPORTANT: the team has not selected quoted or unquoted identifier policy.
-- Results below show exact quoted-name candidates and Oracle-uppercase
-- unquoted-name candidates separately. Neither result is final until the
-- naming policy is selected and recorded; no case-insensitive matching is used.
-- For quoted identifiers, dictionary names must match the exact ERD spelling.
-- For unquoted identifiers, Oracle stores names in uppercase; the uppercase
-- expected value is compared exactly to the dictionary name.

-- 1. Table presence candidates under both possible naming policies.
WITH target_tables (table_name) AS (
    SELECT 'jawaban_psikotes' FROM dual UNION ALL
    SELECT 'Persyaratan_Lowongan' FROM dual UNION ALL
    SELECT 'status_request_lowongan' FROM dual UNION ALL
    SELECT 'bagian_pendaftaran' FROM dual UNION ALL
    SELECT 'jadwal_psikotes' FROM dual UNION ALL
    SELECT 'template_email_rekrutment' FROM dual UNION ALL
    SELECT 'email_rekrutment' FROM dual UNION ALL
    SELECT 'rekapan_keputusan' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user' FROM dual UNION ALL
    SELECT 'jadwal_kandidat' FROM dual UNION ALL
    SELECT 'tes_psikologi' FROM dual UNION ALL
    SELECT 'pertanyaan_psikotes' FROM dual UNION ALL
    SELECT 'detail_info_lowongan' FROM dual UNION ALL
    SELECT 'request_lowongan' FROM dual UNION ALL
    SELECT 'kolom_landing_pendaftaran' FROM dual UNION ALL
    SELECT 'konfiguasi_global_psikotes' FROM dual UNION ALL
    SELECT 'hr_user' FROM dual UNION ALL
    SELECT 'slot_wawancara' FROM dual UNION ALL
    SELECT 'pilihan_fields_pendaftaran' FROM dual
)
SELECT e.table_name AS erd_table_name,
       q.table_name AS quoted_exact_candidate,
       u.table_name AS unquoted_uppercase_candidate,
       'POLICY UNDECIDED - select the relevant candidate after team approval'
           AS review_status
FROM target_tables e
LEFT JOIN user_tables q
       ON q.table_name = e.table_name
LEFT JOIN user_tables u
       ON u.table_name = UPPER(e.table_name)
ORDER BY e.table_name;

-- 2. Compare expected ERD column labels with actual columns.
-- A duplicate ERD label appears more than once in this expected list and is
-- intentionally visible for review. Exact duplicate identifiers must be
-- resolved before any CREATE TABLE statement can be enabled.
WITH expected_columns (table_name, column_name) AS (
    SELECT 'jawaban_psikotes', 'jawaban_psikotes_id' FROM dual UNION ALL
    SELECT 'jawaban_psikotes', 'pertanyaan_psikotes_id' FROM dual UNION ALL
    SELECT 'jawaban_psikotes', 'apakah_benar' FROM dual UNION ALL
    SELECT 'jawaban_psikotes', 'urutan_tampilan' FROM dual UNION ALL
    SELECT 'jawaban_psikotes', 'jawaban_teks' FROM dual UNION ALL
    SELECT 'jawaban_psikotes', 'nilai' FROM dual UNION ALL
    SELECT 'jawaban_psikotes', 'dijawab_pada' FROM dual UNION ALL
    SELECT 'Persyaratan_Lowongan', 'persyaratan_lowongan_id' FROM dual UNION ALL
    SELECT 'Persyaratan_Lowongan', 'judul_pekerjaan' FROM dual UNION ALL
    SELECT 'Persyaratan_Lowongan', 'nama_Persyaratan' FROM dual UNION ALL
    SELECT 'Persyaratan_Lowongan', 'jenis_Persyaratan' FROM dual UNION ALL
    SELECT 'Persyaratan_Lowongan', 'created_at' FROM dual UNION ALL
    SELECT 'Persyaratan_Lowongan', '- updated_at' FROM dual UNION ALL
    SELECT 'Persyaratan_Lowongan', 'kategori_Persyaratan' FROM dual UNION ALL
    SELECT 'Persyaratan_Lowongan', 'jenis_Persyaratan' FROM dual UNION ALL
    SELECT 'Persyaratan_Lowongan', 'hr_id' FROM dual UNION ALL
    SELECT 'status_request_lowongan', 'status_lowongan_id' FROM dual UNION ALL
    SELECT 'status_request_lowongan', 'status_id' FROM dual UNION ALL
    SELECT 'status_request_lowongan', 'permintaan_id' FROM dual UNION ALL
    SELECT 'status_request_lowongan', 'status_pengajuan' FROM dual UNION ALL
    SELECT 'status_request_lowongan', 'catatan_review' FROM dual UNION ALL
    SELECT 'status_request_lowongan', 'tanggal_dibuat' FROM dual UNION ALL
    SELECT 'status_request_lowongan', 'hr_id' FROM dual UNION ALL
    SELECT 'bagian_pendaftaran', 'bagian_pendaftaran_id' FROM dual UNION ALL
    SELECT 'bagian_pendaftaran', 'nama_Section' FROM dual UNION ALL
    SELECT 'bagian_pendaftaran', 'deskripsi' FROM dual UNION ALL
    SELECT 'bagian_pendaftaran', 'display_order' FROM dual UNION ALL
    SELECT 'bagian_pendaftaran', 'created_at' FROM dual UNION ALL
    SELECT 'bagian_pendaftaran', 'update_at' FROM dual UNION ALL
    SELECT 'bagian_pendaftaran', 'hr_id' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'jadwal_psikotes_id' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'metode_psikotes' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'tanggal' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'lokasi' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'waktu' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'lokasi' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'alamat_lengkap' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'kontak_perorang' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'dress_code' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'maks_peserta' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'perlu_konfirmasi_kehadiran' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'dokumen_yang_dibawa' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'informasi_tambah' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'create_by' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'created_at' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'update_at' FROM dual UNION ALL
    SELECT 'jadwal_psikotes', 'hr_id' FROM dual UNION ALL
    SELECT 'template_email_rekrutment', 'template_email_id' FROM dual UNION ALL
    SELECT 'template_email_rekrutment', 'email_rekruitmen_id' FROM dual UNION ALL
    SELECT 'template_email_rekrutment', 'kategori_template' FROM dual UNION ALL
    SELECT 'template_email_rekrutment', 'subjek_email' FROM dual UNION ALL
    SELECT 'template_email_rekrutment', 'isi_email' FROM dual UNION ALL
    SELECT 'template_email_rekrutment', 'is_active' FROM dual UNION ALL
    SELECT 'template_email_rekrutment', 'created_by' FROM dual UNION ALL
    SELECT 'template_email_rekrutment', 'created_at' FROM dual UNION ALL
    SELECT 'template_email_rekrutment', 'updated_at' FROM dual UNION ALL
    SELECT 'email_rekrutment', 'email_rekrutmen_id' FROM dual UNION ALL
    SELECT 'email_rekrutment', 'template_email_id' FROM dual UNION ALL
    SELECT 'email_rekrutment', 'kandidat_id' FROM dual UNION ALL
    SELECT 'email_rekrutment', 'lamaran_id' FROM dual UNION ALL
    SELECT 'email_rekrutment', 'email_penerima' FROM dual UNION ALL
    SELECT 'email_rekrutment', 'subject_email' FROM dual UNION ALL
    SELECT 'email_rekrutment', 'isi_email' FROM dual UNION ALL
    SELECT 'email_rekrutment', 'status_pengiriman' FROM dual UNION ALL
    SELECT 'email_rekrutment', 'terkirim_pada' FROM dual UNION ALL
    SELECT 'email_rekrutment', 'dibaca_pada' FROM dual UNION ALL
    SELECT 'email_rekrutment', 'created_by' FROM dual UNION ALL
    SELECT 'email_rekrutment', 'hr_id' FROM dual UNION ALL
    SELECT 'rekapan_keputusan', 'rekapan_keputusan_id' FROM dual UNION ALL
    SELECT 'rekapan_keputusan', 'kandidat_id' FROM dual UNION ALL
    SELECT 'rekapan_keputusan', 'nama_kandidat' FROM dual UNION ALL
    SELECT 'rekapan_keputusan', 'nama_departement' FROM dual UNION ALL
    SELECT 'rekapan_keputusan', 'tanggal_wawancara' FROM dual UNION ALL
    SELECT 'rekapan_keputusan', 'total_nilai' FROM dual UNION ALL
    SELECT 'rekapan_keputusan', 'rekomendasi_user' FROM dual UNION ALL
    SELECT 'rekapan_keputusan', 'keputusan_akhir' FROM dual UNION ALL
    SELECT 'rekapan_keputusan', 'total_status_rekomterima' FROM dual UNION ALL
    SELECT 'rekapan_keputusan', 'diterima_hr' FROM dual UNION ALL
    SELECT 'rekapan_keputusan', 'menunggu_keputusan' FROM dual UNION ALL
    SELECT 'rekapan_keputusan', 'hr_id' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'hasil_wawancara_user_id' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'kandidat_id' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'nama_kandidat' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'nama_pewawancara' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'departemen_id' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'nama_departemen' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'nilai_penampilan' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'nilai_komunikasi' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'nilai_motivasi' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'nilai_pengalaman' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'nilai_keterampilan' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'nilai_kerjasama' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'nilai_problemsolving' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'nilai_adaptasi' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'nilai_total' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'rekomendasi' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'catatan_wawancara' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'tanggal_wawancara' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'dikumpulkan_pada' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'status_kandidat_id' FROM dual UNION ALL
    SELECT 'Hasil_wawancara_user', 'status_kandidat' FROM dual UNION ALL
    SELECT 'jadwal_kandidat', 'jadwal_kandidat_id' FROM dual UNION ALL
    SELECT 'jadwal_kandidat', 'jadwal_psikotes_id' FROM dual UNION ALL
    SELECT 'jadwal_kandidat', 'nama_kandidat' FROM dual UNION ALL
    SELECT 'jadwal_kandidat', 'tanggal_wawancara' FROM dual UNION ALL
    SELECT 'jadwal_kandidat', 'lokasi_wawancara' FROM dual UNION ALL
    SELECT 'jadwal_kandidat', 'metode_wawancara' FROM dual UNION ALL
    SELECT 'jadwal_kandidat', 'hasil_hr' FROM dual UNION ALL
    SELECT 'jadwal_kandidat', 'status_wawancara' FROM dual UNION ALL
    SELECT 'tes_psikologi', 'tes_psikologi_id' FROM dual UNION ALL
    SELECT 'tes_psikologi', 'judul' FROM dual UNION ALL
    SELECT 'tes_psikologi', 'petunjuk_pengerjaan' FROM dual UNION ALL
    SELECT 'tes_psikologi', 'durasi_pengerjaan' FROM dual UNION ALL
    SELECT 'tes_psikologi', 'updated_at' FROM dual UNION ALL
    SELECT 'tes_psikologi', 'jumlah soal' FROM dual UNION ALL
    SELECT 'tes_psikologi', 'nilai_kelulusan' FROM dual UNION ALL
    SELECT 'tes_psikologi', 'kamera_wajib_aktif' FROM dual UNION ALL
    SELECT 'tes_psikologi', 'is_active' FROM dual UNION ALL
    SELECT 'tes_psikologi', 'urutan_tampilan' FROM dual UNION ALL
    SELECT 'tes_psikologi', 'created_at' FROM dual UNION ALL
    SELECT 'pertanyaan_psikotes', 'peertanyaan_psikotes_id' FROM dual UNION ALL
    SELECT 'pertanyaan_psikotes', 'tes_psikologi_id' FROM dual UNION ALL
    SELECT 'pertanyaan_psikotes', 'teks_pertanyaan' FROM dual UNION ALL
    SELECT 'pertanyaan_psikotes', 'jenis_pertanyaan' FROM dual UNION ALL
    SELECT 'pertanyaan_psikotes', 'nilai_pertanyaan' FROM dual UNION ALL
    SELECT 'pertanyaan_psikotes', 'display_order' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'detail_lowongan_id' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'request_lowongan_id' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'kolom_pendaftaran_id' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'judul_pekerjaan' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'deskripsi_pekerjaan' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'tanggung_jawab' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'no_fptk' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'request_at' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'departement_id' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'nama_departement' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'level_jabatan' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'kategori_KJ' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'jumlah_kebutuhan' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'prioritas' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'batas_lamaran' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'lokasi_penempatan' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'pendidikan_minimum' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'pengalaman_minimum' FROM dual UNION ALL
    SELECT 'detail_info_lowongan', 'sertifikasi_wajib' FROM dual UNION ALL
    SELECT 'request_lowongan', 'request_lowongan_id' FROM dual UNION ALL
    SELECT 'request_lowongan', 'judul_pekerjaan' FROM dual UNION ALL
    SELECT 'request_lowongan', 'deskripsi_pekerjaan' FROM dual UNION ALL
    SELECT 'request_lowongan', 'tanggung_jawab' FROM dual UNION ALL
    SELECT 'request_lowongan', 'Hiring_user_id' FROM dual UNION ALL
    SELECT 'request_lowongan', 'Hiring_user pic' FROM dual UNION ALL
    SELECT 'request_lowongan', 'Hiring_user pic' FROM dual UNION ALL
    SELECT 'request_lowongan', 'no_fptk' FROM dual UNION ALL
    SELECT 'request_lowongan', 'request_at' FROM dual UNION ALL
    SELECT 'request_lowongan', 'departement_id' FROM dual UNION ALL
    SELECT 'request_lowongan', 'nama_departement' FROM dual UNION ALL
    SELECT 'request_lowongan', 'kebutuhan_posisi' FROM dual UNION ALL
    SELECT 'request_lowongan', 'level_jabatan' FROM dual UNION ALL
    SELECT 'request_lowongan', 'kategori_KJ' FROM dual UNION ALL
    SELECT 'request_lowongan', 'jumlah_kebutuhan' FROM dual UNION ALL
    SELECT 'request_lowongan', 'status_karyawan' FROM dual UNION ALL
    SELECT 'request_lowongan', 'status_karyawan' FROM dual UNION ALL
    SELECT 'request_lowongan', 'prioritas' FROM dual UNION ALL
    SELECT 'request_lowongan', 'batas_lamaran' FROM dual UNION ALL
    SELECT 'request_lowongan', 'lokasi_penempatan' FROM dual UNION ALL
    SELECT 'request_lowongan', 'alasan_kebutuhan' FROM dual UNION ALL
    SELECT 'request_lowongan', 'tujuan_utama_posisi' FROM dual UNION ALL
    SELECT 'request_lowongan', 'tanggung_jawab_utama' FROM dual UNION ALL
    SELECT 'request_lowongan', 'output_harapan' FROM dual UNION ALL
    SELECT 'request_lowongan', 'dampak_kekosongan' FROM dual UNION ALL
    SELECT 'request_lowongan', 'pendidikan_minimum' FROM dual UNION ALL
    SELECT 'request_lowongan', 'pengalaman_minimum' FROM dual UNION ALL
    SELECT 'request_lowongan', 'sertifikasi_wajib' FROM dual UNION ALL
    SELECT 'request_lowongan', 'hr_id' FROM dual UNION ALL
    SELECT 'kolom_landing_pendaftaran', 'kolom_pendaftaran_id' FROM dual UNION ALL
    SELECT 'kolom_landing_pendaftaran', 'judul_pekerjaan' FROM dual UNION ALL
    SELECT 'kolom_landing_pendaftaran', 'departemen_id' FROM dual UNION ALL
    SELECT 'kolom_landing_pendaftaran', 'nama_departemen' FROM dual UNION ALL
    SELECT 'kolom_landing_pendaftaran', 'nama_dealer' FROM dual UNION ALL
    SELECT 'kolom_landing_pendaftaran', 'kategori_kj' FROM dual UNION ALL
    SELECT 'kolom_landing_pendaftaran', 'deskripsi_pekerjaan' FROM dual UNION ALL
    SELECT 'kolom_landing_pendaftaran', 'persyaratan_utama' FROM dual UNION ALL
    SELECT 'kolom_landing_pendaftaran', 'batas_lamaran' FROM dual UNION ALL
    SELECT 'kolom_landing_pendaftaran', 'diposting_pada' FROM dual UNION ALL
    SELECT 'kolom_landing_pendaftaran', 'status_kolom' FROM dual UNION ALL
    SELECT 'kolom_landing_pendaftaran', 'urutan_tampil' FROM dual UNION ALL
    SELECT 'konfiguasi_global_psikotes', 'konfigurasi_pisikotes' FROM dual UNION ALL
    SELECT 'konfiguasi_global_psikotes', 'kamera_wajib_global' FROM dual UNION ALL
    SELECT 'konfiguasi_global_psikotes', 'acak_soal' FROM dual UNION ALL
    SELECT 'konfiguasi_global_psikotes', 'status_tes' FROM dual UNION ALL
    SELECT 'konfiguasi_global_psikotes', 'updated_by' FROM dual UNION ALL
    SELECT 'konfiguasi_global_psikotes', 'updated_at' FROM dual UNION ALL
    SELECT 'konfiguasi_global_psikotes', 'hr_id' FROM dual UNION ALL
    SELECT 'hr_user', 'hr_id' FROM dual UNION ALL
    SELECT 'hr_user', 'nama_pengguna' FROM dual UNION ALL
    SELECT 'hr_user', 'password' FROM dual UNION ALL
    SELECT 'slot_wawancara', 'slot_wawancara_id' FROM dual UNION ALL
    SELECT 'slot_wawancara', 'lowongan_id' FROM dual UNION ALL
    SELECT 'slot_wawancara', 'start_at' FROM dual UNION ALL
    SELECT 'slot_wawancara', 'ends_at' FROM dual UNION ALL
    SELECT 'slot_wawancara', 'zona_waktu' FROM dual UNION ALL
    SELECT 'slot_wawancara', 'metode_wawancara' FROM dual UNION ALL
    SELECT 'slot_wawancara', 'lokasi' FROM dual UNION ALL
    SELECT 'slot_wawancara', 'kapasitas' FROM dual UNION ALL
    SELECT 'slot_wawancara', 'nama_pewawancara' FROM dual UNION ALL
    SELECT 'slot_wawancara', 'kandidat_id' FROM dual UNION ALL
    SELECT 'slot_wawancara', 'nama_kandidat' FROM dual UNION ALL
    SELECT 'slot_wawancara', 'meeting_url' FROM dual UNION ALL
    SELECT 'pilihan_fields_pendaftaran', 'pilihan_pendaftaran_id' FROM dual UNION ALL
    SELECT 'pilihan_fields_pendaftaran', 'field_id' FROM dual UNION ALL
    SELECT 'pilihan_fields_pendaftaran', 'nilai' FROM dual UNION ALL
    SELECT 'pilihan_fields_pendaftaran', 'label' FROM dual UNION ALL
    SELECT 'pilihan_fields_pendaftaran', 'urutan_tampilan' FROM dual UNION ALL
    SELECT 'pilihan_fields_pendaftaran', 'bagian_pendaftaran_id' FROM dual
)
SELECT e.table_name AS erd_table_name,
       e.column_name AS erd_column_name,
       q.table_name AS quoted_exact_table_candidate,
       q.column_name AS quoted_exact_column_candidate,
       q.data_type AS quoted_data_type,
       q.data_length AS quoted_data_length,
       q.char_length AS quoted_char_length,
       q.char_used AS quoted_char_used,
       q.data_precision AS quoted_data_precision,
       q.data_scale AS quoted_data_scale,
       q.nullable AS quoted_nullable,
       u.table_name AS unquoted_uppercase_table_candidate,
       u.column_name AS unquoted_uppercase_column_candidate,
       u.data_type AS unquoted_data_type,
       u.data_length AS unquoted_data_length,
       u.char_length AS unquoted_char_length,
       u.char_used AS unquoted_char_used,
       u.data_precision AS unquoted_data_precision,
       u.data_scale AS unquoted_data_scale,
       u.nullable AS unquoted_nullable,
       'POLICY UNDECIDED - candidate matches are not a final verification'
           AS review_status
FROM expected_columns e
LEFT JOIN user_tab_columns q
       ON q.table_name = e.table_name
      AND q.column_name = e.column_name
LEFT JOIN user_tab_columns u
       ON u.table_name = UPPER(e.table_name)
      AND u.column_name = UPPER(e.column_name)
ORDER BY e.table_name, e.column_name;

-- 3. Actual PK, FK, unique and check constraints, with referenced columns.
SELECT c.table_name,
       tn.naming_candidate,
       c.constraint_name,
       c.constraint_type,
       c.status,
       cc.column_name,
       cc.position,
       rc.table_name AS referenced_table_name,
       rcc.column_name AS referenced_column_name,
       c.search_condition
FROM user_constraints c
JOIN user_cons_columns cc
  ON cc.constraint_name = c.constraint_name
 AND cc.table_name = c.table_name
LEFT JOIN user_constraints rc
  ON rc.constraint_name = c.r_constraint_name
LEFT JOIN user_cons_columns rcc
  ON rcc.constraint_name = rc.constraint_name
 AND rcc.position = cc.position
JOIN (
    SELECT table_name AS actual_table_name, 'QUOTED_EXACT' AS naming_candidate
    FROM (
        SELECT 'jawaban_psikotes' table_name FROM dual UNION ALL
        SELECT 'Persyaratan_Lowongan' FROM dual UNION ALL
        SELECT 'status_request_lowongan' FROM dual UNION ALL
        SELECT 'bagian_pendaftaran' FROM dual UNION ALL
        SELECT 'jadwal_psikotes' FROM dual UNION ALL
        SELECT 'template_email_rekrutment' FROM dual UNION ALL
        SELECT 'email_rekrutment' FROM dual UNION ALL
        SELECT 'rekapan_keputusan' FROM dual UNION ALL
        SELECT 'Hasil_wawancara_user' FROM dual UNION ALL
        SELECT 'jadwal_kandidat' FROM dual UNION ALL
        SELECT 'tes_psikologi' FROM dual UNION ALL
        SELECT 'pertanyaan_psikotes' FROM dual UNION ALL
        SELECT 'detail_info_lowongan' FROM dual UNION ALL
        SELECT 'request_lowongan' FROM dual UNION ALL
        SELECT 'kolom_landing_pendaftaran' FROM dual UNION ALL
        SELECT 'konfiguasi_global_psikotes' FROM dual UNION ALL
        SELECT 'hr_user' FROM dual UNION ALL
        SELECT 'slot_wawancara' FROM dual UNION ALL
        SELECT 'pilihan_fields_pendaftaran' FROM dual
    )
    UNION ALL
    SELECT UPPER(table_name), 'UNQUOTED_UPPERCASE'
    FROM (
        SELECT 'jawaban_psikotes' table_name FROM dual UNION ALL
        SELECT 'Persyaratan_Lowongan' FROM dual UNION ALL
        SELECT 'status_request_lowongan' FROM dual UNION ALL
        SELECT 'bagian_pendaftaran' FROM dual UNION ALL
        SELECT 'jadwal_psikotes' FROM dual UNION ALL
        SELECT 'template_email_rekrutment' FROM dual UNION ALL
        SELECT 'email_rekrutment' FROM dual UNION ALL
        SELECT 'rekapan_keputusan' FROM dual UNION ALL
        SELECT 'Hasil_wawancara_user' FROM dual UNION ALL
        SELECT 'jadwal_kandidat' FROM dual UNION ALL
        SELECT 'tes_psikologi' FROM dual UNION ALL
        SELECT 'pertanyaan_psikotes' FROM dual UNION ALL
        SELECT 'detail_info_lowongan' FROM dual UNION ALL
        SELECT 'request_lowongan' FROM dual UNION ALL
        SELECT 'kolom_landing_pendaftaran' FROM dual UNION ALL
        SELECT 'konfiguasi_global_psikotes' FROM dual UNION ALL
        SELECT 'hr_user' FROM dual UNION ALL
        SELECT 'slot_wawancara' FROM dual UNION ALL
        SELECT 'pilihan_fields_pendaftaran' FROM dual
    )
) tn
  ON tn.actual_table_name = c.table_name
WHERE c.constraint_type IN ('P', 'R', 'U', 'C')
ORDER BY c.table_name, c.constraint_name, cc.position;
