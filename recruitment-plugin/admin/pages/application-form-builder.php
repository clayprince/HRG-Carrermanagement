<?php
$form_types = [
    'short'         => 'Jawaban Singkat',
    'paragraph'     => 'Paragraf / Jawaban Panjang',
    'number'        => 'Angka',
    'date'          => 'Tanggal',
    'email'         => 'Email',
    'phone'         => 'Nomor Telepon',
    'file'          => 'Upload File',
    'dropdown'      => 'Pilihan Dropdown',
    'single_choice' => 'Pilihan Satu Jawaban',
];

$question = static function ( string $key, string $title, string $type = 'short', bool $required = false, array $options = [] ): array {
    return [ 'key' => $key, 'title' => $title, 'type' => $type, 'required' => $required, 'options' => $options ];
};
$repeat_questions = static function ( string $prefix, int $count, array $fields ) use ( $question ): array {
    $questions = [];
    for ( $row = 1; $row <= $count; $row++ ) {
        foreach ( $fields as $field ) {
            [ $suffix, $label, $type, $required, $options ] = $field;
            $questions[] = $question(
                $prefix . '_' . $row . '_' . $suffix,
                $label . ' — ' . $row,
                $type,
                $required,
                $options
            );
        }
    }
    return $questions;
};
$labeled_row_questions = static function ( string $prefix, array $rows, array $fields ) use ( $question ): array {
    $questions = [];
    foreach ( $rows as $row_key => $row_label ) {
        foreach ( $fields as $field ) {
            [ $suffix, $label, $type, $required, $options ] = $field;
            $questions[] = $question(
                $prefix . '_' . $row_key . '_' . $suffix,
                $row_label . ' — ' . $label,
                $type,
                $required,
                $options
            );
        }
    }
    return $questions;
};

$default_sections = [
    [
        'title' => 'A. Identitas',
        'description' => 'Data identitas sesuai Form Lamaran Kerja DAW. Nama dan email akun akan terisi otomatis.',
        'questions' => [
            $question( 'foto_terkini', 'Foto Terkini', 'file' ),
            $question( 'cv_resume', 'CV / Resume', 'file' ),
            $question( 'portofolio', 'Portofolio', 'file' ),
            $question( 'nama_lengkap', 'Nama Lengkap', 'short', true ),
            $question( 'tempat_lahir', 'Tempat Lahir', 'short', true ),
            $question( 'tanggal_lahir', 'Tanggal Lahir', 'date', true ),
            $question( 'jenis_kelamin', 'Jenis Kelamin', 'single_choice', true, [ 'Laki-laki', 'Perempuan' ] ),
            $question( 'alamat_saat_ini', 'Alamat Saat Ini', 'paragraph', true ),
            $question( 'alamat_ktp', 'Alamat Sesuai KTP', 'paragraph' ),
            $question( 'nomor_telepon', 'Nomor Telepon', 'phone' ),
            $question( 'nomor_hp', 'Nomor HP / WhatsApp', 'phone', true ),
            $question( 'email_aktif', 'Email Aktif', 'email', true ),
            $question( 'agama_kepercayaan', 'Agama / Kepercayaan' ),
            $question( 'kewarganegaraan', 'Kewarganegaraan' ),
            $question( 'nomor_ktp', 'Nomor KTP' ),
            $question( 'jenis_sim', 'SIM yang Dimiliki', 'dropdown', false, [ 'Tidak ada', 'A', 'B', 'C', 'A dan C', 'B dan C', 'A, B, dan C' ] ),
            $question( 'sumber_informasi_lowongan', 'Dari mana Anda mendapatkan informasi mengenai posisi ini?', 'short' ),
            ...$repeat_questions(
                'media_sosial',
                10,
                [
                    [ 'platform', 'Platform Media Sosial', 'dropdown', false, [ 'Instagram', 'Facebook', 'TikTok', 'X / Twitter', 'YouTube', 'LinkedIn', 'Lainnya' ] ],
                    [ 'username', 'Username / URL Profil', 'short', false, [] ],
                ]
            ),
        ],
    ],
    [
        'title' => 'B. Pendidikan',
        'description' => 'Pendidikan formal, kursus, dan kemampuan bahasa seperti pada Form Lamaran Kerja DAW.',
        'questions' => array_merge(
            $repeat_questions(
                'pendidikan_formal',
                5,
                [
                    [ 'tingkat', 'Tingkat Pendidikan Formal', 'dropdown', false, [ 'SD', 'SMP', 'SMA/SMK', 'D1', 'D2', 'D3', 'D4/S1', 'S2', 'S3' ] ],
                    [ 'nama_sekolah', 'Nama Sekolah / Institusi', 'short', false, [] ],
                    [ 'fakultas', 'Fakultas', 'short', false, [] ],
                    [ 'program_studi', 'Program Studi', 'short', false, [] ],
                    [ 'ipk', 'IPK / Nilai', 'number', false, [] ],
                    [ 'tahun_lulus', 'Tahun Lulus', 'number', false, [] ],
                ]
            ),
            $repeat_questions(
                'pendidikan_nonformal',
                3,
                [
                    [ 'jenis_kursus', 'Jenis Kursus', 'short', false, [] ],
                    [ 'nama_lembaga', 'Nama Lembaga', 'short', false, [] ],
                    [ 'program_studi', 'Program Studi', 'short', false, [] ],
                    [ 'lama_kursus', 'Lama Kursus', 'short', false, [] ],
                    [ 'tahun_kursus', 'Tahun Kursus', 'number', false, [] ],
                ]
            ),
            $repeat_questions(
                'bahasa',
                3,
                [
                    [ 'nama', 'Nama Bahasa', 'short', false, [] ],
                    [ 'tertulis', 'Kemampuan Tertulis', 'dropdown', false, [ 'Sedang', 'Cukup Baik', 'Baik' ] ],
                    [ 'lisan', 'Kemampuan Lisan', 'dropdown', false, [ 'Sedang', 'Cukup Baik', 'Baik' ] ],
                    [ 'keterangan', 'Keterangan Kemampuan Bahasa', 'short', false, [] ],
                ]
            )
        ),
    ],
    [
        'title' => 'C. Riwayat dan Evaluasi Pekerjaan',
        'description' => 'Riwayat pekerjaan, atasan, tanggung jawab, dan pengalaman perubahan positif.',
        'questions' => array_merge(
            $repeat_questions(
                'riwayat_pekerjaan',
                5,
                [
                    [ 'nama_perusahaan', 'Nama Perusahaan', 'short', false, [] ],
                    [ 'periode_kerja', 'Tahun Kerja (Dari–Sampai)', 'short', false, [] ],
                    [ 'jabatan', 'Jabatan', 'short', false, [] ],
                    [ 'gaji_pokok', 'Gaji Pokok', 'number', false, [] ],
                    [ 'alasan_pindah', 'Alasan Pindah', 'paragraph', false, [] ],
                ]
            ),
            $repeat_questions(
                'atasan_dan_bawahan',
                5,
                [
                    [ 'nama_perusahaan', 'Perusahaan', 'short', false, [] ],
                    [ 'jumlah_bawahan', 'Jumlah Bawahan', 'number', false, [] ],
                    [ 'nama_atasan', 'Nama Atasan Langsung', 'short', false, [] ],
                    [ 'jabatan_atasan', 'Jabatan Atasan', 'short', false, [] ],
                    [ 'telepon_atasan', 'Nomor Telepon Atasan', 'phone', false, [] ],
                ]
            ),
            [
                $question( 'perubahan_di_perusahaan_terdahulu', 'Perubahan apa yang pernah Anda lakukan di perusahaan terdahulu dan bagaimana hasilnya?', 'paragraph' ),
                $question( 'pengalaman_perubahan_positif', 'Pengalaman yang memberikan perubahan positif bagi diri Anda', 'paragraph' ),
                $question( 'kesediaan_kontak_atasan', 'Apakah Anda bersedia jika DAW menghubungi atasan sebelumnya?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
            ]
        ),
    ],
    [
        'title' => 'D. Keluarga dan Lingkungan',
        'description' => 'Status pernikahan, keluarga, tempat tinggal, dan informasi kesehatan yang diminta pada acuan.',
        'questions' => array_merge(
            [
            $question( 'status_pernikahan', 'Status Pernikahan', 'single_choice', true, [ 'Belum menikah', 'Bertunangan', 'Menikah', 'Janda / Duda' ] ),
            $question( 'tanggal_pertunangan_atau_pernikahan', 'Tanggal Pertunangan / Pernikahan (jika ada)', 'date' ),
            $question( 'hubungan_keluarga_karyawan_daw', 'Apakah Anda memiliki hubungan keluarga dengan karyawan DAW / Daya Group?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
            $question( 'nama_keluarga_karyawan_daw', 'Nama dan Jabatan Karyawan yang Memiliki Hubungan Keluarga', 'paragraph' ),
            $question( 'hubungan_pribadi_karyawan_daw', 'Apakah Anda memiliki hubungan pribadi dengan karyawan DAW / Daya Group?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
            $question( 'nama_hubungan_pribadi_karyawan_daw', 'Nama dan Jabatan Karyawan yang Memiliki Hubungan Pribadi', 'paragraph' ),
            $question( 'susunan_keluarga_inti', 'Susunan Keluarga Inti (pasangan dan anak)', 'paragraph' ),
            $question( 'urutan_dalam_saudara', 'Urutan Anda dan Jumlah Saudara', 'short' ),
            $question( 'susunan_keluarga_asal', 'Susunan Keluarga (orang tua dan saudara kandung)', 'paragraph' ),
            $question( 'status_tempat_tinggal', 'Status Rumah yang Ditempati', 'dropdown', false, [ 'Rumah pribadi', 'Rumah keluarga suami/istri', 'Rumah kost', 'Rumah orang tua', 'Rumah kontrakan', 'Lainnya' ] ),
            $question( 'lama_kost_atau_kontrak', 'Lama Kost / Kontrak (jika ada)', 'short' ),
            $question( 'riwayat_diabetes', 'Pernah menderita diabetes?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
            $question( 'riwayat_darah_tinggi', 'Pernah menderita darah tinggi?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
            $question( 'riwayat_buta_warna', 'Pernah mengalami buta warna?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
            $question( 'riwayat_wasir', 'Pernah menderita ambeien / wasir?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
            $question( 'riwayat_alergi', 'Memiliki alergi? Jika ya, sebutkan.', 'paragraph' ),
            $question( 'riwayat_tbc', 'Pernah menderita TBC?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
            $question( 'riwayat_sakit_lain', 'Riwayat sakit lainnya dan kapan dialami', 'paragraph' ),
            $question( 'riwayat_penyakit_keluarga', 'Riwayat penyakit keluarga dan anggota keluarga yang mengalaminya', 'paragraph' ),
            ],
            $labeled_row_questions(
                'keluarga_inti',
                [ 'pasangan' => 'Pasangan', 'anak_1' => 'Anak ke-1', 'anak_2' => 'Anak ke-2', 'anak_3' => 'Anak ke-3', 'anak_4' => 'Anak ke-4', 'anak_5' => 'Anak ke-5' ],
                [
                    [ 'nama', 'Nama', 'short', false, [] ],
                    [ 'jenis_kelamin', 'Jenis Kelamin', 'dropdown', false, [ 'Laki-laki', 'Perempuan' ] ],
                    [ 'tempat_tanggal_lahir', 'Tempat dan Tanggal Lahir', 'short', false, [] ],
                    [ 'pendidikan', 'Pendidikan', 'short', false, [] ],
                    [ 'pekerjaan', 'Pekerjaan', 'short', false, [] ],
                ]
            ),
            $labeled_row_questions(
                'keluarga_asal',
                [ 'ayah' => 'Ayah', 'ibu' => 'Ibu', 'saudara_1' => 'Saudara kandung ke-1', 'saudara_2' => 'Saudara kandung ke-2', 'saudara_3' => 'Saudara kandung ke-3', 'saudara_4' => 'Saudara kandung ke-4', 'saudara_5' => 'Saudara kandung ke-5', 'saudara_6' => 'Saudara kandung ke-6', 'saudara_7' => 'Saudara kandung ke-7' ],
                [
                    [ 'nama', 'Nama', 'short', false, [] ],
                    [ 'jenis_kelamin', 'Jenis Kelamin', 'dropdown', false, [ 'Laki-laki', 'Perempuan' ] ],
                    [ 'tempat_tanggal_lahir', 'Tempat dan Tanggal Lahir', 'short', false, [] ],
                    [ 'pendidikan', 'Pendidikan', 'short', false, [] ],
                    [ 'pekerjaan', 'Pekerjaan', 'short', false, [] ],
                ]
            )
        ),
    ],
    [
        'title' => 'E. Minat dan Konsep Pribadi',
        'description' => 'Motivasi, ekspektasi, preferensi kerja, dan informasi pendukung.',
        'questions' => [
            $question( 'motivasi_bergabung', 'Mengapa Anda ingin bekerja di perusahaan kami?', 'paragraph', true ),
            $question( 'pengetahuan_perusahaan', 'Apa yang Anda ketahui mengenai perusahaan kami?', 'paragraph' ),
            $question( 'harapan_bekerja', 'Apa yang Anda harapkan dari bekerja di perusahaan kami?', 'paragraph' ),
            $question( 'tunjangan_transportasi_saat_ini', 'Tunjangan Transportasi Saat Ini', 'short' ),
            $question( 'tunjangan_makan_saat_ini', 'Tunjangan Makan Saat Ini', 'short' ),
            $question( 'tunjangan_komunikasi_saat_ini', 'Tunjangan Komunikasi Saat Ini', 'short' ),
            $question( 'tunjangan_kehadiran_saat_ini', 'Tunjangan Kehadiran Saat Ini', 'short' ),
            $question( 'car_ownership_program_saat_ini', 'Car Ownership Program Saat Ini', 'short' ),
            $question( 'house_ownership_program_saat_ini', 'House Ownership Program Saat Ini', 'short' ),
            $question( 'asuransi_rawat_jalan_saat_ini', 'Asuransi Rawat Jalan (siapa yang di-cover)', 'short' ),
            $question( 'asuransi_rawat_inap_saat_ini', 'Asuransi Rawat Inap (siapa yang di-cover)', 'short' ),
            $question( 'insentif_saat_ini', 'Insentif Saat Ini', 'short' ),
            $question( 'bonus_tahunan_saat_ini', 'Bonus Tahunan Saat Ini', 'short' ),
            $question( 'laptop_ownership_program_saat_ini', 'Laptop Ownership Program Saat Ini', 'short' ),
            $question( 'fasilitas_lain_saat_ini', 'Fasilitas Lain Saat Ini', 'short' ),
            $question( 'gaji_yang_diharapkan', 'Berapa gaji yang Anda inginkan?', 'short' ),
            $question( 'fasilitas_yang_diharapkan', 'Fasilitas yang diinginkan selain gaji', 'paragraph' ),
            $question( 'bersedia_background_check', 'Bersedia menjalani background checking pengalaman kerja?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
            $question( 'kapan_mulai_bekerja', 'Kapan Anda dapat mulai bekerja?', 'short' ),
            $question( 'minat_jenis_pekerjaan_1', 'Prioritas 1 — Jenis Pekerjaan yang Disenangi', 'short' ),
            $question( 'minat_jenis_pekerjaan_2', 'Prioritas 2 — Jenis Pekerjaan yang Disenangi', 'short' ),
            $question( 'minat_jenis_pekerjaan_3', 'Prioritas 3 — Jenis Pekerjaan yang Disenangi', 'short' ),
            $question( 'minat_bidang_industri_1', 'Prioritas 1 — Bidang Industri yang Disenangi', 'short' ),
            $question( 'minat_bidang_industri_2', 'Prioritas 2 — Bidang Industri yang Disenangi', 'short' ),
            $question( 'minat_bidang_industri_3', 'Prioritas 3 — Bidang Industri yang Disenangi', 'short' ),
            $question( 'jabatan_cita_cita', 'Jabatan / Posisi yang Dicita-citakan', 'paragraph' ),
            $question( 'upaya_mencapai_jabatan', 'Apa yang akan Anda lakukan untuk mencapai posisi tersebut?', 'paragraph' ),
            $question( 'bersedia_penempatan_luar_kota', 'Bersedia ditempatkan di luar kota / area Daya Group?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
            $question( 'lingkungan_kerja_disukai', 'Lingkungan kerja yang disenangi dan alasannya', 'paragraph' ),
            $question( 'lingkungan_kerja_tidak_disukai', 'Pekerjaan / lingkungan kerja yang tidak disenangi dan alasannya', 'paragraph' ),
            $question( 'tipe_orang_disukai', 'Tipe orang yang paling disenangi dan alasannya', 'paragraph' ),
            $question( 'tipe_orang_tidak_disukai', 'Tipe orang yang tidak disenangi dan alasannya', 'paragraph' ),
            $question( 'kelebihan_diri', 'Tiga kelebihan (strong points) diri Anda', 'paragraph' ),
            $question( 'kelemahan_diri', 'Tiga kelemahan diri Anda', 'paragraph' ),
            $question( 'kesulitan_mengambil_keputusan', 'Hal yang paling sulit Anda putuskan', 'paragraph' ),
            $question( 'mengenal_karyawan_daw', 'Mengenal karyawan DAW? Cantumkan nama, hubungan, telepon, dan jabatan.', 'paragraph' ),
        ],
    ],
    [
        'title' => 'F. Aktivitas Sosial dan Kegiatan Lain',
        'description' => 'Hobi, kegiatan waktu luang, dan pengalaman organisasi.',
        'questions' => [
            $question( 'hobi', 'Apa hobi Anda?', 'paragraph' ),
            $question( 'keahlian_bahasa_sertifikasi_skill', 'Keahlian Bahasa, Sertifikasi, Skill, dan Minat Lainnya', 'paragraph' ),
            $question( 'kegiatan_waktu_luang', 'Bagaimana cara Anda mengisi waktu luang?', 'paragraph' ),
            $question( 'pengalaman_organisasi', 'Organisasi yang pernah diikuti, jabatan, dan lama keanggotaan', 'paragraph' ),
        ],
    ],
];

$notice = '';
$warning = '';
$error = '';
$database = new Recruitment_Database();
$form_result = $database->get_application_form();
$form_ready = ! empty( $form_result['success'] );
$sections = [];

if ( $form_ready ) {
    $sections = $form_result['sections'];
    $has_questions = (bool) array_sum( array_map( static function ( $section ) {
        return count( $section['questions'] ?? [] );
    }, $sections ) );
    $load_reference_template = '1' === sanitize_text_field( wp_unslash( $_GET['load_reference'] ?? '' ) );
    if ( $load_reference_template ) {
        $sections = $default_sections;
        $warning = 'Template Form Lamaran Kerja DAW dari file acuan dimuat sebagai draf. Tinjau field, lalu tekan Simpan Perubahan untuk menerapkannya ke Oracle.';
    } elseif ( ! $has_questions ) {
        $legacy_sections = get_option( 'recruitment_application_form', null );
        if ( is_array( $legacy_sections ) ) {
            $legacy_form = ( new Recruitment_Application() )->normalize_form_configuration( $legacy_sections );
            $sections = $legacy_form['sections'];
            $warning = 'Konfigurasi WordPress lama ditampilkan sebagai draf. Simpan perubahan untuk memindahkannya ke Oracle.';
        } else {
            $sections = $default_sections;
            $warning = 'Form Oracle belum memiliki data. Simpan form awal ini agar dapat ditampilkan kepada pelamar.';
        }
    }
} else {
    $legacy_sections = get_option( 'recruitment_application_form', null );
    if ( is_array( $legacy_sections ) ) {
        $legacy_form = ( new Recruitment_Application() )->normalize_form_configuration( $legacy_sections );
        $sections = $legacy_form['sections'];
    } else {
        $sections = $default_sections;
    }
    $error = 'Form Oracle belum tersedia. Pastikan migrasi 003_dynamic_application_forms.sql telah ditinjau dan dijalankan oleh pemilik Oracle.';
    $warning = 'Template acuan ditampilkan agar field dapat ditinjau, tetapi penyimpanan dan pengiriman lamaran tetap menunggu koneksi Oracle.';
}

if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['recruitment_form_builder_save'] ) ) {
    check_admin_referer( 'recruitment_form_builder_save' );
    if ( ! current_user_can( 'manage_recruitment' ) ) {
        wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengubah formulir ini.', 'recruitment-plugin' ) );
    }

    if ( ! $form_ready ) {
        $error = 'Form belum dapat disimpan karena schema Oracle belum tersedia.';
    } else {
        $submitted_sections = isset( $_POST['form_sections'] ) ? wp_unslash( $_POST['form_sections'] ) : [];
        $prepared_form = ( new Recruitment_Application() )->normalize_form_configuration( $submitted_sections );
        $sections = $prepared_form['sections'];
        if ( $prepared_form['errors'] ) {
            $error = implode( ' ', $prepared_form['errors'] );
        } else {
            $saved = $database->save_application_form( $sections );
            if ( ! empty( $saved['success'] ) ) {
                $form_result = $database->get_application_form();
                if ( ! empty( $form_result['success'] ) ) {
                    $sections = $form_result['sections'];
                    $notice = 'Perubahan formulir berhasil disimpan ke Oracle.';
                    $warning = '';
                } else {
                    $error = 'Form tersimpan, tetapi tidak dapat dimuat ulang dari Oracle.';
                }
            } else {
                $error = (string) ( $saved['error'] ?? 'Form lamaran gagal disimpan ke Oracle.' );
            }
        }
    }
}

$total_questions = array_sum( array_map( static function ( $section ) {
    return count( $section['questions'] ?? [] );
}, $sections ) );
?>
<div class="wrap recruitment-form-builder">
    <form method="post" data-form-builder>
        <?php wp_nonce_field( 'recruitment_form_builder_save' ); ?>
        <input type="hidden" name="recruitment_form_builder_save" value="1">
        <header class="recruitment-form-builder__topbar">
            <div>
                <p class="recruitment-form-builder__breadcrumb">Recruitment <span>/</span> Form Lamaran</p>
                <h1>Form Lamaran Kerja DAW <span class="recruitment-form-builder__draft">Draft</span></h1>
                <p class="recruitment-form-builder__summary"><span data-section-count><?= esc_html( count( $sections ) ) ?></span> section · <span data-question-count><?= esc_html( $total_questions ) ?></span> pertanyaan · dapat disesuaikan dengan kebutuhan lowongan</p>
            </div>
            <div class="recruitment-form-builder__actions">
                <a class="button" href="<?= esc_url( add_query_arg( 'load_reference', '1', recruitment_get_admin_url( 'application-form-builder' ) ) ) ?>" onclick="return confirm('Muat template acuan DAW ke editor? Perubahan baru diterapkan setelah Anda menekan Simpan Perubahan.');">Muat Template Acuan DAW</a>
                <button type="button" class="button" data-preview-form>Pratinjau</button>
                <button type="button" class="button" data-add-section>+ Tambah Section</button>
                <button type="submit" class="button button-primary" <?= $form_ready ? '' : 'disabled' ?>>Simpan Perubahan</button>
            </div>
        </header>

        <?php if ( $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p><?= esc_html( $notice ) ?></p></div>
        <?php endif; ?>
        <?php if ( $warning ) : ?>
            <div class="notice notice-warning"><p><?= esc_html( $warning ) ?></p></div>
        <?php endif; ?>
        <?php if ( $error ) : ?>
            <div class="notice notice-error"><p><?= esc_html( $error ) ?></p></div>
        <?php endif; ?>

        <div class="recruitment-form-builder__sections" data-form-sections>
            <?php foreach ( $sections as $section_index => $section ) : ?>
                <section class="recruitment-form-section" data-form-section>
                    <input type="hidden" name="form_sections[<?= esc_attr( $section_index ) ?>][section_id]" value="<?= esc_attr( $section['id'] ?? '' ) ?>" data-section-id>
                    <header class="recruitment-form-section__header">
                        <span class="recruitment-form-section__number" data-section-number><?= esc_html( sprintf( '%02d', $section_index + 1 ) ) ?></span>
                        <div class="recruitment-form-section__heading">
                            <label class="screen-reader-text" for="section-title-<?= esc_attr( $section_index ) ?>">Nama section</label>
                            <input id="section-title-<?= esc_attr( $section_index ) ?>" class="recruitment-form-section__title" name="form_sections[<?= esc_attr( $section_index ) ?>][title]" value="<?= esc_attr( $section['title'] ?? '' ) ?>" data-section-title required>
                            <input class="recruitment-form-section__description" name="form_sections[<?= esc_attr( $section_index ) ?>][description]" value="<?= esc_attr( $section['description'] ?? '' ) ?>" placeholder="Deskripsi section" data-section-description>
                        </div>
                        <span class="recruitment-form-section__count"><span data-section-question-count><?= esc_html( count( $section['questions'] ?? [] ) ) ?></span> pertanyaan</span>
                        <div class="recruitment-form-section__actions">
                            <button type="button" class="button button-small" data-move-section="up" aria-label="Pindahkan section ke atas">↑</button>
                            <button type="button" class="button button-small" data-move-section="down" aria-label="Pindahkan section ke bawah">↓</button>
                            <button type="button" class="button button-small button-link-delete" data-remove-section>Hapus</button>
                        </div>
                    </header>
                    <div class="recruitment-form-section__questions" data-section-questions>
                        <?php foreach ( (array) ( $section['questions'] ?? [] ) as $question_index => $question ) : ?>
                            <article class="recruitment-form-question" data-form-question>
                                <div class="recruitment-form-question__row">
                                    <span class="recruitment-form-question__number" data-question-number><?= esc_html( $question_index + 1 ) ?></span>
                                    <span class="recruitment-form-question__icon" aria-hidden="true">≡</span>
                                    <div class="recruitment-form-question__identity">
                                        <strong data-question-label><?= esc_html( $question['title'] ?? '' ) ?></strong>
                                        <span class="recruitment-form-question__meta"><span data-question-type-label><?= esc_html( $form_types[ $question['type'] ?? 'short' ] ?? 'Jawaban Singkat' ) ?></span><span data-question-required><?= ! empty( $question['required'] ) ? 'Wajib diisi' : 'Opsional' ?></span></span>
                                    </div>
                                    <div class="recruitment-form-question__actions">
                                        <button type="button" class="button button-small" data-move-question="up" aria-label="Pindahkan pertanyaan ke atas">↑</button>
                                        <button type="button" class="button button-small" data-move-question="down" aria-label="Pindahkan pertanyaan ke bawah">↓</button>
                                        <details class="recruitment-form-question__edit"><summary class="button button-small">Edit</summary>
                                            <div class="recruitment-form-question__editor">
                                                <input type="hidden" name="form_sections[<?= esc_attr( $section_index ) ?>][questions][<?= esc_attr( $question_index ) ?>][field_id]" value="<?= esc_attr( $question['id'] ?? '' ) ?>" data-question-id>
                                                <input type="hidden" name="form_sections[<?= esc_attr( $section_index ) ?>][questions][<?= esc_attr( $question_index ) ?>][key]" value="<?= esc_attr( $question['key'] ?? '' ) ?>" data-question-key>
                                                <label>Pertanyaan<input name="form_sections[<?= esc_attr( $section_index ) ?>][questions][<?= esc_attr( $question_index ) ?>][title]" value="<?= esc_attr( $question['title'] ?? '' ) ?>" data-question-title required></label>
                                                <label>Jenis jawaban<select name="form_sections[<?= esc_attr( $section_index ) ?>][questions][<?= esc_attr( $question_index ) ?>][type]" data-question-type><?php foreach ( $form_types as $type => $label ) : ?><option value="<?= esc_attr( $type ) ?>" <?= selected( $question['type'] ?? 'short', $type, false ) ?>><?= esc_html( $label ) ?></option><?php endforeach; ?></select></label>
                                                <label class="recruitment-form-question__options" data-question-options>Opsi jawaban<textarea name="form_sections[<?= esc_attr( $section_index ) ?>][questions][<?= esc_attr( $question_index ) ?>][options]" rows="3" data-question-options-value><?= esc_textarea( implode( "\n", array_map( static function ( $option ) { return is_array( $option ) ? (string) ( $option['label'] ?? $option['value'] ?? '' ) : (string) $option; }, (array) ( $question['options'] ?? [] ) ) ) ) ?></textarea><small>Satu opsi untuk setiap baris.</small></label>
                                                <label class="recruitment-form-question__required"><input type="checkbox" name="form_sections[<?= esc_attr( $section_index ) ?>][questions][<?= esc_attr( $question_index ) ?>][required]" value="1" data-question-required-input <?= checked( ! empty( $question['required'] ), true, false ) ?>> Wajib diisi</label>
                                                <button type="button" class="button button-link-delete" data-remove-question>Hapus pertanyaan</button>
                                            </div>
                                        </details>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="recruitment-form-section__add-question" data-add-question>+ Tambah Pertanyaan</button>
                </section>
            <?php endforeach; ?>
        </div>
    </form>
    <dialog class="recruitment-form-preview" data-form-preview>
        <div class="recruitment-form-preview__header"><h2>Pratinjau Form Lamaran</h2><button type="button" class="button" data-close-preview>Tutup</button></div>
        <div class="recruitment-form-preview__body" data-preview-content></div>
    </dialog>
    <template data-question-template>
        <article class="recruitment-form-question" data-form-question>
            <div class="recruitment-form-question__row">
                <span class="recruitment-form-question__number" data-question-number></span><span class="recruitment-form-question__icon" aria-hidden="true">≡</span>
                <div class="recruitment-form-question__identity"><strong data-question-label>Pertanyaan baru</strong><span class="recruitment-form-question__meta"><span data-question-type-label>Jawaban Singkat</span><span data-question-required>Opsional</span></span></div>
                <div class="recruitment-form-question__actions"><button type="button" class="button button-small" data-move-question="up" aria-label="Pindahkan pertanyaan ke atas">↑</button><button type="button" class="button button-small" data-move-question="down" aria-label="Pindahkan pertanyaan ke bawah">↓</button>
                    <details class="recruitment-form-question__edit" open><summary class="button button-small">Edit</summary><div class="recruitment-form-question__editor">
                        <input type="hidden" data-question-id>
                        <input type="hidden" data-question-key>
                        <label>Pertanyaan<input value="Pertanyaan baru" data-question-title required></label>
                        <label>Jenis jawaban<select data-question-type><?php foreach ( $form_types as $type => $label ) : ?><option value="<?= esc_attr( $type ) ?>"><?= esc_html( $label ) ?></option><?php endforeach; ?></select></label>
                        <label class="recruitment-form-question__options" data-question-options>Opsi jawaban<textarea rows="3" data-question-options-value></textarea><small>Satu opsi untuk setiap baris.</small></label>
                        <label class="recruitment-form-question__required"><input type="checkbox" value="1" data-question-required-input> Wajib diisi</label><button type="button" class="button button-link-delete" data-remove-question>Hapus pertanyaan</button>
                    </div></details>
                </div>
            </div>
        </article>
    </template>
    <template data-section-template>
        <section class="recruitment-form-section" data-form-section>
            <input type="hidden" data-section-id>
            <header class="recruitment-form-section__header"><span class="recruitment-form-section__number" data-section-number></span><div class="recruitment-form-section__heading"><label class="screen-reader-text">Nama section</label><input class="recruitment-form-section__title" value="Section baru" data-section-title required><input class="recruitment-form-section__description" placeholder="Deskripsi section" data-section-description></div><span class="recruitment-form-section__count"><span data-section-question-count>0</span> pertanyaan</span><div class="recruitment-form-section__actions"><button type="button" class="button button-small" data-move-section="up" aria-label="Pindahkan section ke atas">↑</button><button type="button" class="button button-small" data-move-section="down" aria-label="Pindahkan section ke bawah">↓</button><button type="button" class="button button-small button-link-delete" data-remove-section>Hapus</button></div></header><div class="recruitment-form-section__questions" data-section-questions></div><button type="button" class="recruitment-form-section__add-question" data-add-question>+ Tambah Pertanyaan</button>
        </section>
    </template>
</div>
