<?php

/**
 * Application form normalization, validation, and submission handling.
 */
class Recruitment_Application {

    /** @var array<string, bool> */
    private const FIELD_TYPES = [
        'short'         => true,
        'paragraph'     => true,
        'number'        => true,
        'date'          => true,
        'email'         => true,
        'phone'         => true,
        'file'          => true,
        'dropdown'      => true,
        'single_choice' => true,
    ];

    /** @var array<string, string> */
    private const FIXED_FIELD_TYPES = [
        'foto_terkini'             => 'file',
        'nama_lengkap'             => 'short',
        'jenis_kelamin'            => 'single_choice',
        'tempat_lahir'             => 'short',
        'tanggal_lahir'            => 'date',
        'alamat_saat_ini'          => 'paragraph',
        'nomor_telepon'            => 'phone',
        'email_aktif'              => 'email',
        'nomor_ktp'                => 'short',
        'pendidikan_terakhir'      => 'dropdown',
        'nama_institusi'           => 'short',
        'jurusan_program_studi'    => 'short',
        'ipk'                      => 'number',
        'tahun_lulus'              => 'number',
        'nama_perusahaan'          => 'short',
        'jabatan_posisi'           => 'short',
        'tanggal_mulai_kerja'      => 'date',
        'tanggal_selesai_kerja'    => 'date',
        'alasan_berhenti'          => 'paragraph',
        'status_pernikahan'        => 'single_choice',
        'susunan_keluarga_inti'    => 'paragraph',
        'gaji_yang_diharapkan'     => 'short',
        'motivasi_bergabung'       => 'paragraph',
    ];

    /**
     * The form is fixed to the applicant fields available in the Oracle ERD.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_fixed_form_sections(): array {
        return [
            [
                'title' => 'Identitas',
                'description' => 'Informasi pribadi pelamar',
                'questions' => [
                    [ 'key' => 'foto_terkini', 'title' => 'Foto Terkini', 'type' => 'file', 'required' => true, 'options' => [] ],
                    [ 'key' => 'nama_lengkap', 'title' => 'Nama Lengkap', 'type' => 'short', 'required' => true, 'options' => [] ],
                    [ 'key' => 'jenis_kelamin', 'title' => 'Jenis Kelamin', 'type' => 'single_choice', 'required' => true, 'options' => [ 'Laki-laki', 'Perempuan' ] ],
                    [ 'key' => 'tempat_lahir', 'title' => 'Tempat Lahir', 'type' => 'short', 'required' => true, 'options' => [] ],
                    [ 'key' => 'tanggal_lahir', 'title' => 'Tanggal Lahir', 'type' => 'date', 'required' => true, 'options' => [] ],
                    [ 'key' => 'alamat_saat_ini', 'title' => 'Alamat Saat Ini', 'type' => 'paragraph', 'required' => true, 'options' => [] ],
                    [ 'key' => 'nomor_telepon', 'title' => 'Nomor Telepon / WhatsApp', 'type' => 'phone', 'required' => true, 'options' => [] ],
                    [ 'key' => 'email_aktif', 'title' => 'Email Aktif', 'type' => 'email', 'required' => true, 'options' => [] ],
                    [ 'key' => 'nomor_ktp', 'title' => 'Nomor KTP', 'type' => 'short', 'required' => true, 'options' => [] ],
                ],
            ],
            [
                'title' => 'Pendidikan',
                'description' => 'Riwayat pendidikan terakhir',
                'questions' => [
                    [ 'key' => 'pendidikan_terakhir', 'title' => 'Pendidikan Terakhir', 'type' => 'dropdown', 'required' => true, 'options' => [ 'SMA / SMK', 'Diploma (D1/D2/D3)', 'S1 (Sarjana)', 'S2 (Magister)' ] ],
                    [ 'key' => 'nama_institusi', 'title' => 'Nama Institusi', 'type' => 'short', 'required' => true, 'options' => [] ],
                    [ 'key' => 'jurusan_program_studi', 'title' => 'Jurusan / Program Studi', 'type' => 'short', 'required' => false, 'options' => [] ],
                    [ 'key' => 'ipk', 'title' => 'IPK', 'type' => 'number', 'required' => false, 'options' => [] ],
                    [ 'key' => 'tahun_lulus', 'title' => 'Tahun Lulus', 'type' => 'number', 'required' => true, 'options' => [] ],
                ],
            ],
            [
                'title' => 'Pengalaman Kerja',
                'description' => 'Riwayat pekerjaan sebelumnya',
                'questions' => [
                    [ 'key' => 'nama_perusahaan', 'title' => 'Nama Perusahaan', 'type' => 'short', 'required' => false, 'options' => [] ],
                    [ 'key' => 'jabatan_posisi', 'title' => 'Jabatan / Posisi', 'type' => 'short', 'required' => false, 'options' => [] ],
                    [ 'key' => 'tanggal_mulai_kerja', 'title' => 'Tanggal Mulai', 'type' => 'date', 'required' => false, 'options' => [] ],
                    [ 'key' => 'tanggal_selesai_kerja', 'title' => 'Tanggal Selesai', 'type' => 'date', 'required' => false, 'options' => [] ],
                    [ 'key' => 'alasan_berhenti', 'title' => 'Deskripsi / Alasan Berhenti', 'type' => 'paragraph', 'required' => false, 'options' => [] ],
                ],
            ],
            [
                'title' => 'Keluarga',
                'description' => 'Informasi status dan latar belakang keluarga',
                'questions' => [
                    [ 'key' => 'status_pernikahan', 'title' => 'Status Pernikahan', 'type' => 'single_choice', 'required' => true, 'options' => [ 'Belum Menikah', 'Menikah', 'Cerai' ] ],
                    [ 'key' => 'susunan_keluarga_inti', 'title' => 'Susunan Keluarga Inti', 'type' => 'paragraph', 'required' => false, 'options' => [] ],
                ],
            ],
            [
                'title' => 'Minat dan Konsep Pribadi',
                'description' => 'Ekspektasi dan motivasi pelamar',
                'questions' => [
                    [ 'key' => 'gaji_yang_diharapkan', 'title' => 'Gaji yang Diharapkan', 'type' => 'short', 'required' => true, 'options' => [] ],
                    [ 'key' => 'motivasi_bergabung', 'title' => 'Motivasi Bergabung', 'type' => 'paragraph', 'required' => true, 'options' => [] ],
                ],
            ],
        ];
    }

    /** @return array<string, string> */
    public function get_fixed_field_types(): array {
        return self::FIXED_FIELD_TYPES;
    }

    /**
     * Build the application fields shown while the configurable Oracle form is unavailable.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_reference_form_sections(): array {
        $question = static function ( string $key, string $title, string $type = 'short', bool $required = false, array $options = [] ): array {
            return [
                'id' => '',
                'key' => $key,
                'title' => $title,
                'type' => $type,
                'required' => $required,
                'options' => array_map( static fn ( string $option ): array => [ 'value' => $option, 'label' => $option ], $options ),
            ];
        };
        $repeated_questions = static function ( string $prefix, int $rows, array $fields ) use ( $question ): array {
            $questions = [];
            for ( $row = 1; $row <= $rows; $row++ ) {
                foreach ( $fields as $field ) {
                    [ $suffix, $title, $type, $required, $options ] = $field;
                    $questions[] = $question( $prefix . '_' . $row . '_' . $suffix, $title . ' — ' . $row, $type, $required, $options );
                }
            }
            return $questions;
        };
        $family_questions = static function ( string $prefix, array $rows ) use ( $question ): array {
            $questions = [];
            foreach ( $rows as $row_key => $row_title ) {
                foreach (
                    [
                        [ 'nama', 'Nama', 'short', [] ],
                        [ 'jenis_kelamin', 'Jenis Kelamin', 'dropdown', [ 'Laki-laki', 'Perempuan' ] ],
                        [ 'tempat_tanggal_lahir', 'Tempat dan Tanggal Lahir', 'short', [] ],
                        [ 'pendidikan', 'Pendidikan', 'short', [] ],
                        [ 'pekerjaan', 'Pekerjaan', 'short', [] ],
                    ] as $field
                ) {
                    [ $suffix, $title, $type, $options ] = $field;
                    $questions[] = $question( $prefix . '_' . $row_key . '_' . $suffix, $row_title . ' — ' . $title, $type, false, $options );
                }
            }
            return $questions;
        };

        return [
            [
                'id' => '',
                'title' => 'A. Identitas',
                'description' => 'Identitas pelamar sesuai Form Lamaran Kerja DAW.',
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
                    $question( 'sumber_informasi_lowongan', 'Sumber Informasi Lowongan' ),
                    ...$repeated_questions(
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
                'id' => '',
                'title' => 'B. Pendidikan',
                'description' => 'Pendidikan formal, kursus, dan kemampuan bahasa.',
                'questions' => array_merge(
                    $repeated_questions(
                        'pendidikan_formal',
                        5,
                        [
                            [ 'tingkat', 'Tingkat Pendidikan', 'dropdown', false, [ 'SD', 'SMP', 'SMA/SMK', 'D1', 'D2', 'D3', 'D4/S1', 'S2', 'S3' ] ],
                            [ 'nama_sekolah', 'Nama Sekolah / Institusi', 'short', false, [] ],
                            [ 'fakultas', 'Fakultas', 'short', false, [] ],
                            [ 'program_studi', 'Program Studi', 'short', false, [] ],
                            [ 'ipk', 'IPK / Nilai', 'number', false, [] ],
                            [ 'tahun_lulus', 'Tahun Lulus', 'number', false, [] ],
                        ]
                    ),
                    $repeated_questions(
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
                    $repeated_questions(
                        'bahasa',
                        3,
                        [
                            [ 'nama', 'Nama Bahasa', 'short', false, [] ],
                            [ 'tertulis', 'Kemampuan Tertulis', 'dropdown', false, [ 'Sedang', 'Cukup Baik', 'Baik' ] ],
                            [ 'lisan', 'Kemampuan Lisan', 'dropdown', false, [ 'Sedang', 'Cukup Baik', 'Baik' ] ],
                            [ 'keterangan', 'Keterangan', 'short', false, [] ],
                        ]
                    )
                ),
            ],
            [
                'id' => '',
                'title' => 'C. Riwayat dan Evaluasi Pekerjaan',
                'description' => 'Riwayat pekerjaan, atasan, dan pengalaman kerja.',
                'questions' => array_merge(
                    $repeated_questions(
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
                    $repeated_questions(
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
                        $question( 'perubahan_di_perusahaan_terdahulu', 'Perubahan yang Pernah Dilakukan dan Hasilnya', 'paragraph' ),
                        $question( 'pengalaman_perubahan_positif', 'Pengalaman Perubahan Positif', 'paragraph' ),
                        $question( 'kesediaan_kontak_atasan', 'Bersedia Dihubungi Atasan Sebelumnya?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
                    ]
                ),
            ],
            [
                'id' => '',
                'title' => 'D. Keluarga dan Lingkungan',
                'description' => 'Status keluarga, tempat tinggal, dan informasi kesehatan.',
                'questions' => array_merge(
                    [
                    $question( 'status_pernikahan', 'Status Pernikahan', 'single_choice', true, [ 'Belum menikah', 'Bertunangan', 'Menikah', 'Janda / Duda' ] ),
                    $question( 'tanggal_pertunangan_atau_pernikahan', 'Tanggal Pertunangan / Pernikahan (jika ada)', 'date' ),
                    $question( 'hubungan_keluarga_karyawan_daw', 'Memiliki Hubungan Keluarga dengan Karyawan DAW / Daya Group?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
                    $question( 'nama_keluarga_karyawan_daw', 'Nama dan Jabatan Karyawan Terkait', 'paragraph' ),
                    $question( 'hubungan_pribadi_karyawan_daw', 'Memiliki Hubungan Pribadi dengan Karyawan DAW / Daya Group?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
                    $question( 'nama_hubungan_pribadi_karyawan_daw', 'Nama dan Jabatan Karyawan Terkait', 'paragraph' ),
                    $question( 'susunan_keluarga_inti', 'Susunan Keluarga Inti (Pasangan dan Anak)', 'paragraph' ),
                    $question( 'urutan_dalam_saudara', 'Urutan Anda dan Jumlah Saudara', 'short' ),
                    $question( 'susunan_keluarga_asal', 'Susunan Keluarga (Orang Tua dan Saudara Kandung)', 'paragraph' ),
                    $question( 'status_tempat_tinggal', 'Status Rumah yang Ditempati', 'dropdown', false, [ 'Rumah Pribadi', 'Rumah Keluarga Suami / Istri', 'Rumah Kost', 'Rumah Orang Tua', 'Rumah Kontrak', 'Lainnya' ] ),
                    $question( 'lama_kost_atau_kontrak', 'Lama Kost / Kontrak', 'short' ),
                    $question( 'riwayat_diabetes', 'Pernah Menderita Diabetes?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
                    $question( 'riwayat_darah_tinggi', 'Pernah Menderita Darah Tinggi?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
                    $question( 'riwayat_buta_warna', 'Pernah Mengalami Buta Warna?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
                    $question( 'riwayat_wasir', 'Pernah Menderita Ambeien / Wasir?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
                    $question( 'riwayat_alergi', 'Memiliki Alergi? Jika ya, sebutkan.', 'paragraph' ),
                    $question( 'riwayat_tbc', 'Pernah Menderita TBC?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
                    $question( 'riwayat_sakit_lain', 'Riwayat Sakit Lain dan Kapan Dialami', 'paragraph' ),
                    $question( 'riwayat_penyakit_keluarga', 'Riwayat Penyakit Keluarga', 'paragraph' ),
                    ],
                    $family_questions(
                        'keluarga_inti',
                        [ 'pasangan' => 'Pasangan', 'anak_1' => 'Anak ke-1', 'anak_2' => 'Anak ke-2', 'anak_3' => 'Anak ke-3', 'anak_4' => 'Anak ke-4', 'anak_5' => 'Anak ke-5' ]
                    ),
                    $family_questions(
                        'keluarga_asal',
                        [ 'ayah' => 'Ayah', 'ibu' => 'Ibu', 'saudara_1' => 'Saudara kandung ke-1', 'saudara_2' => 'Saudara kandung ke-2', 'saudara_3' => 'Saudara kandung ke-3', 'saudara_4' => 'Saudara kandung ke-4', 'saudara_5' => 'Saudara kandung ke-5', 'saudara_6' => 'Saudara kandung ke-6', 'saudara_7' => 'Saudara kandung ke-7' ]
                    )
                ),
            ],
            [
                'id' => '',
                'title' => 'E. Minat dan Konsep Pribadi',
                'description' => 'Motivasi, ekspektasi, dan preferensi kerja.',
                'questions' => [
                    $question( 'motivasi_bergabung', 'Mengapa Anda Ingin Bekerja di Perusahaan Kami?', 'paragraph', true ),
                    $question( 'pengetahuan_perusahaan', 'Apa yang Anda Ketahui Mengenai Perusahaan Kami?', 'paragraph' ),
                    $question( 'harapan_bekerja', 'Apa yang Anda Harapkan dari Bekerja di Perusahaan Kami?', 'paragraph' ),
                    $question( 'tunjangan_transportasi_saat_ini', 'Tunjangan Transportasi Saat Ini' ),
                    $question( 'tunjangan_makan_saat_ini', 'Tunjangan Makan Saat Ini' ),
                    $question( 'tunjangan_komunikasi_saat_ini', 'Tunjangan Komunikasi Saat Ini' ),
                    $question( 'tunjangan_kehadiran_saat_ini', 'Tunjangan Kehadiran Saat Ini' ),
                    $question( 'car_ownership_program_saat_ini', 'Car Ownership Program Saat Ini' ),
                    $question( 'house_ownership_program_saat_ini', 'House Ownership Program Saat Ini' ),
                    $question( 'asuransi_rawat_jalan_saat_ini', 'Asuransi Rawat Jalan (siapa yang di-cover)' ),
                    $question( 'asuransi_rawat_inap_saat_ini', 'Asuransi Rawat Inap (siapa yang di-cover)' ),
                    $question( 'insentif_saat_ini', 'Insentif Saat Ini' ),
                    $question( 'bonus_tahunan_saat_ini', 'Bonus Tahunan Saat Ini' ),
                    $question( 'laptop_ownership_program_saat_ini', 'Laptop Ownership Program Saat Ini' ),
                    $question( 'fasilitas_lain_saat_ini', 'Fasilitas Lain Saat Ini' ),
                    $question( 'gaji_yang_diharapkan', 'Berapa Gaji yang Anda Inginkan?', 'short' ),
                    $question( 'fasilitas_yang_diharapkan', 'Fasilitas yang Diinginkan Selain Gaji', 'paragraph' ),
                    $question( 'bersedia_background_check', 'Bersedia Menjalani Background Checking?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
                    $question( 'kapan_mulai_bekerja', 'Kapan Anda Dapat Mulai Bekerja?' ),
                    $question( 'minat_jenis_pekerjaan_1', 'Prioritas 1 — Jenis Pekerjaan' ),
                    $question( 'minat_jenis_pekerjaan_2', 'Prioritas 2 — Jenis Pekerjaan' ),
                    $question( 'minat_jenis_pekerjaan_3', 'Prioritas 3 — Jenis Pekerjaan' ),
                    $question( 'minat_bidang_industri_1', 'Prioritas 1 — Bidang Industri' ),
                    $question( 'minat_bidang_industri_2', 'Prioritas 2 — Bidang Industri' ),
                    $question( 'minat_bidang_industri_3', 'Prioritas 3 — Bidang Industri' ),
                    $question( 'jabatan_cita_cita', 'Jabatan / Posisi yang Dicita-citakan', 'paragraph' ),
                    $question( 'upaya_mencapai_jabatan', 'Upaya untuk Mencapai Posisi Tersebut', 'paragraph' ),
                    $question( 'bersedia_penempatan_luar_kota', 'Bersedia Ditempatkan di Luar Kota?', 'single_choice', false, [ 'Ya', 'Tidak' ] ),
                    $question( 'lingkungan_kerja_disukai', 'Lingkungan Kerja yang Disenangi dan Alasannya', 'paragraph' ),
                    $question( 'lingkungan_kerja_tidak_disukai', 'Lingkungan Kerja yang Tidak Disenangi dan Alasannya', 'paragraph' ),
                    $question( 'tipe_orang_disukai', 'Tipe Orang yang Paling Disenangi dan Alasannya', 'paragraph' ),
                    $question( 'tipe_orang_tidak_disukai', 'Tipe Orang yang Tidak Disenangi dan Alasannya', 'paragraph' ),
                    $question( 'kelebihan_diri', 'Tiga Kelebihan Diri', 'paragraph' ),
                    $question( 'kelemahan_diri', 'Tiga Kelemahan Diri', 'paragraph' ),
                    $question( 'kesulitan_mengambil_keputusan', 'Hal yang Paling Sulit Anda Putuskan', 'paragraph' ),
                    $question( 'mengenal_karyawan_daw', 'Mengenal Karyawan DAW? Cantumkan Nama, Hubungan, Telepon, dan Jabatan.', 'paragraph' ),
                ],
            ],
            [
                'id' => '',
                'title' => 'F. Aktivitas Sosial dan Kegiatan Lain',
                'description' => 'Hobi, kegiatan waktu luang, dan pengalaman organisasi.',
                'questions' => [
                    $question( 'hobi', 'Apa Hobi Anda?', 'paragraph' ),
                    $question( 'keahlian_bahasa_sertifikasi_skill', 'Keahlian Bahasa, Sertifikasi, Skill, dan Minat Lainnya', 'paragraph' ),
                    $question( 'kegiatan_waktu_luang', 'Bagaimana Cara Anda Mengisi Waktu Luang?', 'paragraph' ),
                    $question( 'pengalaman_organisasi', 'Organisasi yang Pernah Diikuti, Jabatan, dan Lama Keanggotaan', 'paragraph' ),
                ],
            ],
        ];
    }

    /**
     * Map saved applicant profile fields to matching application question keys.
     *
     * @param array<string, mixed> $applicant
     * @return array<string, string>
     */
    public function prefill_profile_answers( array $applicant ): array {
        $details = is_array( $applicant['profile_details'] ?? null ) ? $applicant['profile_details'] : [];
        $education_level = (string) ( $details['education_level'] ?? '' );
        $profile_answers = [
            'nama_lengkap' => (string) ( $applicant['full_name'] ?? '' ),
            'jenis_kelamin' => (string) ( $applicant['gender'] ?? '' ),
            'tempat_lahir' => (string) ( $applicant['birth_place'] ?? '' ),
            'tanggal_lahir' => (string) ( $applicant['birth_date'] ?? '' ),
            'alamat_saat_ini' => (string) ( $applicant['address'] ?? '' ),
            'alamat' => (string) ( $applicant['address'] ?? '' ),
            'nomor_telepon' => (string) ( $applicant['phone'] ?? '' ),
            'nomor_hp' => (string) ( $applicant['phone'] ?? '' ),
            'email_aktif' => (string) ( $applicant['email'] ?? '' ),
            'pendidikan_formal_1_tingkat' => $education_level,
            'education_level' => $education_level,
            'pendidikan_formal_1_nama_sekolah' => (string) ( $details['institution'] ?? '' ),
            'pendidikan_formal_1_program_studi' => (string) ( $details['major'] ?? '' ),
            'pendidikan_formal_1_ipk' => (string) ( $details['gpa'] ?? '' ),
            'pendidikan_formal_1_tahun_lulus' => (string) ( $details['graduation_year'] ?? '' ),
            'pendidikan_terakhir' => [
                'SMA/SMK' => 'SMA / SMK',
                'D1' => 'Diploma (D1/D2/D3)',
                'D2' => 'Diploma (D1/D2/D3)',
                'D3' => 'Diploma (D1/D2/D3)',
                'D4/S1' => 'S1 (Sarjana)',
                'S2' => 'S2 (Magister)',
            ][ $education_level ] ?? $education_level,
            'nama_institusi' => (string) ( $details['institution'] ?? '' ),
            'institution' => (string) ( $details['institution'] ?? '' ),
            'jurusan_program_studi' => (string) ( $details['major'] ?? '' ),
            'major' => (string) ( $details['major'] ?? '' ),
            'ipk' => (string) ( $details['gpa'] ?? '' ),
            'gpa' => (string) ( $details['gpa'] ?? '' ),
            'tahun_lulus' => (string) ( $details['graduation_year'] ?? '' ),
            'graduation_year' => (string) ( $details['graduation_year'] ?? '' ),
            'keahlian_bahasa_sertifikasi_skill' => (string) ( $details['interests'] ?? '' ),
            'minat_bakat' => (string) ( $details['interests'] ?? '' ),
            'interests' => (string) ( $details['interests'] ?? '' ),
        ];

        foreach ( array_slice( (array) ( $details['social_media'] ?? [] ), 0, 10 ) as $index => $social_entry ) {
            if ( ! is_array( $social_entry ) ) {
                continue;
            }
            $row = $index + 1;
            $profile_answers[ 'media_sosial_' . $row . '_platform' ] = (string) ( $social_entry['platform'] ?? '' );
            $profile_answers[ 'media_sosial_' . $row . '_username' ] = (string) ( $social_entry['username'] ?? '' );
        }

        $marital_status = (string) ( $details['marital_status'] ?? '' );
        if ( '' !== $marital_status ) {
            $profile_answers['status_pernikahan'] = 'Cerai' === $marital_status ? 'Janda / Duda' : $marital_status;
            $profile_answers['marital_status'] = $marital_status;
        }

        $work_experiences = array_slice( (array) ( $details['work_experiences'] ?? [] ), 0, 5 );
        foreach ( $work_experiences as $index => $experience ) {
            if ( ! is_array( $experience ) ) {
                continue;
            }
            $row = $index + 1;
            $company = (string) ( $experience['company'] ?? '' );
            $position = (string) ( $experience['position'] ?? '' );
            $period = (string) ( $experience['period'] ?? '' );
            $profile_answers[ 'riwayat_pekerjaan_' . $row . '_nama_perusahaan' ] = $company;
            $profile_answers[ 'riwayat_pekerjaan_' . $row . '_jabatan' ] = $position;
            $profile_answers[ 'riwayat_pekerjaan_' . $row . '_periode_kerja' ] = $period;
            if ( 0 === $index ) {
                $profile_answers['nama_perusahaan'] = $company;
                $profile_answers['jabatan_posisi'] = $position;
                $profile_answers['periode_kerja'] = $period;
            }
        }

        $family_lines = [];
        foreach ( (array) ( $details['family_members'] ?? [] ) as $family_member ) {
            if ( ! is_array( $family_member ) ) {
                continue;
            }
            $family_line = implode(
                ' · ',
                array_filter(
                    [
                        (string) ( $family_member['relationship'] ?? '' ),
                        (string) ( $family_member['name'] ?? '' ),
                        '' !== (string) ( $family_member['age'] ?? '' ) ? (string) $family_member['age'] . ' tahun' : '',
                        (string) ( $family_member['education'] ?? '' ),
                    ],
                    static fn ( string $value ): bool => '' !== trim( $value )
                )
            );
            if ( '' !== $family_line ) {
                $family_lines[] = $family_line;
            }
        }
        if ( $family_lines ) {
            $profile_answers['susunan_keluarga_inti'] = implode( "\n", $family_lines );
        }

        $saved_application_answers = is_array( $details['application_answers'] ?? null ) ? $details['application_answers'] : [];
        $saved_application_answers = array_filter(
            $saved_application_answers,
            static fn ( $value, $key ): bool => is_string( $key ) && is_scalar( $value ),
            ARRAY_FILTER_USE_BOTH
        );
        return array_merge( array_map( 'strval', $saved_application_answers ), $profile_answers );
    }

    /**
     * @param mixed $submitted
     * @return array{sections: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public function normalize_form_configuration( $submitted ): array {
        if ( ! is_array( $submitted ) ) {
            return [ 'sections' => [], 'errors' => [ 'Data form tidak valid.' ] ];
        }

        $errors = [];
        $sections = [];
        $used_keys = [];
        $question_count = 0;
        $submitted = array_values( $submitted );
        if ( count( $submitted ) > 50 ) {
            $errors[] = 'Form tidak boleh memiliki lebih dari 50 section.';
        }

        foreach ( array_slice( $submitted, 0, 50 ) as $section ) {
            if ( ! is_array( $section ) ) {
                $errors[] = 'Data salah satu section tidak valid.';
                continue;
            }

            $raw_section_id = trim( $this->scalar( $section['section_id'] ?? '' ) );
            $section_id = $this->identifier( $raw_section_id );
            if ( '' !== $raw_section_id && '' === $section_id ) {
                $errors[] = 'Referensi section tidak valid.';
            }
            $title = sanitize_text_field( $this->scalar( $section['title'] ?? '' ) );
            $description = sanitize_textarea_field( $this->scalar( $section['description'] ?? '' ) );
            if ( '' === $title ) {
                $errors[] = 'Nama section wajib diisi.';
            } elseif ( $this->text_length( $title ) > 255 ) {
                $errors[] = 'Nama section maksimal 255 karakter.';
            }

            $raw_questions = $section['questions'] ?? [];
            if ( ! is_array( $raw_questions ) ) {
                $errors[] = 'Daftar pertanyaan pada section tidak valid.';
                $raw_questions = [];
            }
            if ( count( $raw_questions ) > 100 ) {
                $errors[] = 'Setiap section maksimal memiliki 100 pertanyaan.';
            }

            $questions = [];
            foreach ( array_slice( array_values( $raw_questions ), 0, 100 ) as $question ) {
                if ( ! is_array( $question ) ) {
                    $errors[] = 'Data salah satu pertanyaan tidak valid.';
                    continue;
                }

                $question_title = sanitize_text_field( $this->scalar( $question['title'] ?? '' ) );
                $question_type = sanitize_key( $this->scalar( $question['type'] ?? 'short' ) );
                if ( '' === $question_title ) {
                    $errors[] = 'Nama pertanyaan wajib diisi.';
                } elseif ( $this->text_length( $question_title ) > 255 ) {
                    $errors[] = 'Nama pertanyaan maksimal 255 karakter.';
                }
                if ( ! isset( self::FIELD_TYPES[ $question_type ] ) ) {
                    $errors[] = 'Jenis jawaban tidak dikenal.';
                    $question_type = 'short';
                }

                $raw_field_id = trim( $this->scalar( $question['field_id'] ?? '' ) );
                $field_id = $this->identifier( $raw_field_id );
                if ( '' !== $raw_field_id && '' === $field_id ) {
                    $errors[] = 'Referensi pertanyaan tidak valid.';
                }

                $key = sanitize_key( $this->scalar( $question['key'] ?? '' ) );
                if ( '' === $key ) {
                    $key = sanitize_title( $question_title );
                }
                $key = substr( $key ?: 'field', 0, 100 );
                $base_key = $key;
                $suffix = 2;
                while ( isset( $used_keys[ $key ] ) ) {
                    $suffix_text = '_' . $suffix;
                    $key = substr( $base_key, 0, 100 - strlen( $suffix_text ) ) . $suffix_text;
                    $suffix++;
                }
                $used_keys[ $key ] = true;
                if ( isset( self::FIXED_FIELD_TYPES[ $key ] ) && self::FIXED_FIELD_TYPES[ $key ] !== $question_type ) {
                    $errors[] = 'Jenis jawaban field inti "' . $key . '" tidak dapat diubah.';
                }

                $options = [];
                if ( in_array( $question_type, [ 'dropdown', 'single_choice' ], true ) ) {
                    $raw_options = $question['options'] ?? '';
                    $raw_options = is_array( $raw_options )
                        ? array_map(
                            function ( $option ) {
                                return $this->scalar( $option );
                            },
                            $raw_options
                        )
                        : ( preg_split( '/\r\n|\r|\n/', $this->scalar( $raw_options ) ) ?: [] );
                    foreach ( $raw_options as $raw_option ) {
                        $option = sanitize_text_field( $raw_option );
                        if ( '' === $option ) {
                            continue;
                        }
                        if ( $this->text_length( $option ) > 255 ) {
                            $errors[] = 'Setiap pilihan jawaban maksimal 255 karakter.';
                            continue;
                        }
                        if ( ! in_array( $option, array_column( $options, 'value' ), true ) ) {
                            $options[] = [ 'value' => $option, 'label' => $option ];
                        }
                    }
                    if ( ! $options ) {
                        $errors[] = 'Pertanyaan pilihan harus memiliki sedikitnya satu opsi.';
                    }
                }

                $questions[] = [
                    'id'       => $field_id,
                    'key'      => $key,
                    'title'    => $question_title,
                    'type'     => $question_type,
                    'required' => ! empty( $question['required'] ),
                    'options'  => $options,
                ];
                $question_count++;
            }

            $sections[] = [
                'id'          => $section_id,
                'title'       => $title,
                'description' => $description,
                'questions'   => $questions,
            ];
        }

        if ( ! $sections ) {
            $errors[] = 'Form harus memiliki sedikitnya satu section.';
        }
        if ( 0 === $question_count ) {
            $errors[] = 'Form harus memiliki sedikitnya satu pertanyaan.';
        }
        foreach ( self::FIXED_FIELD_TYPES as $key => $type ) {
            if ( ! isset( $used_keys[ $key ] ) ) {
                $errors[] = 'Field inti "' . $key . '" tidak dapat dihapus dari form.';
            }
        }
        foreach ( $sections as $section ) {
            foreach ( $section['questions'] as $question ) {
                if ( 'foto_terkini' === $question['key'] && empty( $question['required'] ) ) {
                    $errors[] = 'Foto Terkini harus tetap menjadi field wajib.';
                }
            }
        }

        return [ 'sections' => $sections, 'errors' => array_values( array_unique( $errors ) ) ];
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $files
     * @param array<int, array<string, mixed>> $sections
     * @param array<string, mixed> $vacancy
     * @return array{errors: array<int, string>, payload: array<string, mixed>}
     */
    public function prepare_submission(
        array $request,
        array $files,
        array $sections,
        string $account_name,
        string $account_email,
        array $vacancy
    ): array {
        $errors = [];
        $answers_input = $request['answers'] ?? [];
        if ( ! is_array( $answers_input ) ) {
            $answers_input = [];
            $errors[] = 'Data jawaban form tidak valid.';
        }

        $answers = [];
        $field_max_lengths = [
            'nama_lengkap' => 255,
            'jenis_kelamin' => 100,
            'tempat_lahir' => 100,
            'email_aktif' => 50,
            'nomor_telepon' => 255,
            'nomor_ktp' => 255,
            'pendidikan_terakhir' => 100,
            'nama_institusi' => 255,
            'jurusan_program_studi' => 255,
            'nama_perusahaan' => 255,
            'jabatan_posisi' => 255,
            'alasan_berhenti' => 200,
            'status_pernikahan' => 100,
            'susunan_keluarga_inti' => 255,
            'gaji_yang_diharapkan' => 255,
        ];
        foreach ( $sections as $section ) {
            foreach ( $section['questions'] as $question ) {
                $key = (string) $question['key'];
                $type = (string) $question['type'];
                $raw_value = $answers_input[ $key ] ?? '';

                if ( 'file' === $type ) {
                    $file = $this->uploaded_file( $files, $key );
                    if ( null === $file ) {
                        if ( ! empty( $question['required'] ) ) {
                            $errors[] = 'Unggah file untuk field "' . $question['title'] . '".';
                        }
                        continue;
                    }
                    $validated_file = $this->validate_upload( $file, (string) $question['title'], $errors );
                    if ( null !== $validated_file ) {
                        $answers[] = [
                            'field_id'    => (string) $question['id'],
                            'field_key'   => $key,
                            'field_title' => (string) $question['title'],
                            'field_type'  => $type,
                            'value'       => null,
                            'file'        => $validated_file,
                        ];
                    }
                    continue;
                }

                if ( ! is_scalar( $raw_value ) ) {
                    $errors[] = 'Jawaban untuk field "' . $question['title'] . '" tidak valid.';
                    continue;
                }
                $value = $this->normalize_answer( $type, (string) $raw_value );
                if ( '' !== trim( $value ) && $this->text_length( $value ) > 10000 ) {
                    $errors[] = 'Jawaban untuk field "' . $question['title'] . '" maksimal 10.000 karakter.';
                    continue;
                }
                if (
                    '' !== trim( $value )
                    && isset( $field_max_lengths[ $key ] )
                    && $this->text_length( $value ) > $field_max_lengths[ $key ]
                ) {
                    $errors[] = 'Jawaban untuk field "' . $question['title'] . '" maksimal ' . $field_max_lengths[ $key ] . ' karakter.';
                    continue;
                }
                if ( 'tahun_lulus' === $key && '' !== trim( $value ) && ( ! ctype_digit( $value ) || strlen( $value ) > 19 ) ) {
                    $errors[] = 'Tahun lulus harus berupa angka bulat maksimal 19 digit.';
                    continue;
                }
                if ( 'ipk' === $key && '' !== trim( $value ) && 1 !== preg_match( '/^\d{1,14}(?:\.\d{1,4})?$/D', $value ) ) {
                    $errors[] = 'IPK harus berupa angka dengan maksimal 14 digit sebelum dan 4 digit setelah titik desimal.';
                    continue;
                }
                if ( ! $this->valid_answer( $type, $value, $question['options'] ) ) {
                    $errors[] = 'Jawaban untuk field "' . $question['title'] . '" tidak sesuai format.';
                    continue;
                }
                if ( '' === trim( $value ) ) {
                    if ( ! empty( $question['required'] ) ) {
                        $errors[] = 'Field "' . $question['title'] . '" wajib diisi.';
                    }
                    continue;
                }

                $answers[] = [
                    'field_id'    => (string) $question['id'],
                    'field_key'   => $key,
                    'field_title' => (string) $question['title'],
                    'field_type'  => $type,
                    'value'       => $value,
                    'file'        => null,
                ];
            }
        }

        foreach ( [ 'pdp_accuracy', 'pdp_data', 'pdp_placement' ] as $consent ) {
            if ( ! isset( $request[ $consent ] ) || ! is_scalar( $request[ $consent ] ) || '1' !== (string) $request[ $consent ] ) {
                $errors[] = 'Centang seluruh pernyataan PDP sebelum mengirim lamaran.';
                break;
            }
        }

        $answer_by_key = [];
        foreach ( $answers as $answer ) {
            if ( null !== $answer['value'] ) {
                $answer_by_key[ $answer['field_key'] ] = $answer['value'];
            }
        }
        $applicant_name = sanitize_text_field( $answer_by_key['nama_lengkap'] ?? $account_name );
        $applicant_email = sanitize_email( $answer_by_key['email_aktif'] ?? $account_email );
        if ( '' === $applicant_name || ! is_email( $applicant_email ) ) {
            $errors[] = 'Nama dan email pelamar harus valid.';
        }
        if ( empty( $vacancy['id'] ) || ! ctype_digit( (string) $vacancy['id'] ) || (int) $vacancy['id'] < 1 ) {
            $errors[] = 'Lowongan yang dipilih tidak valid.';
        }
        if ( '' === trim( (string) ( $vacancy['title'] ?? '' ) ) ) {
            $errors[] = 'Judul lowongan tidak tersedia.';
        }

        return [
            'errors'  => array_values( array_unique( $errors ) ),
            'payload' => [
                'applicant_name'   => $applicant_name,
                'applicant_email'  => $applicant_email,
                'vacancy_source_id' => (string) ( $vacancy['id'] ?? '' ),
                'vacancy_title'    => sanitize_text_field( (string) ( $vacancy['title'] ?? '' ) ),
                'answers'          => $answers,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $files
     * @param array<int, array<string, mixed>> $sections
     * @param array<string, mixed> $vacancy
     * @return array{errors: array<int, string>, payload: array<string, mixed>, success: bool, application_id?: string, application_token?: string}
     */
    public function submit(
        array $request,
        array $files,
        array $sections,
        string $account_name,
        string $account_email,
        array $vacancy,
        string $candidate_login_id
    ): array {
        $prepared = $this->prepare_submission( $request, $files, $sections, $account_name, $account_email, $vacancy );
        if ( $prepared['errors'] ) {
            return [ 'errors' => $prepared['errors'], 'payload' => $prepared['payload'], 'success' => false ];
        }

        if ( ! ctype_digit( $candidate_login_id ) || '0' === $candidate_login_id ) {
            return [ 'errors' => [ 'Silakan masuk dengan akun pelamar untuk mengirim lamaran.' ], 'payload' => $prepared['payload'], 'success' => false ];
        }
        $prepared['payload']['candidate_login_id'] = $candidate_login_id;
        $prepared['payload']['applicant_name'] = $account_name;
        $prepared['payload']['applicant_email'] = $account_email;
        foreach ( $prepared['payload']['answers'] as &$answer ) {
            if ( 'nama_lengkap' === $answer['field_key'] ) {
                $answer['value'] = $account_name;
            } elseif ( 'email_aktif' === $answer['field_key'] ) {
                $answer['value'] = $account_email;
            }
        }
        unset( $answer );

        $saved = ( new Recruitment_Database() )->save_application_submission( $prepared['payload'] );
        if ( empty( $saved['success'] ) ) {
            return [
                'errors'  => [ (string) ( $saved['error'] ?? 'Data lamaran gagal disimpan. Silakan coba lagi.' ) ],
                'payload' => $prepared['payload'],
                'success' => false,
            ];
        }

        return [
            'errors'           => [],
            'payload'          => $prepared['payload'],
            'success'          => true,
            'application_id'    => (string) ( $saved['application_id'] ?? '' ),
            'application_token' => (string) ( $saved['application_token'] ?? '' ),
        ];
    }

    private function normalize_answer( string $type, string $value ): string {
        if ( 'paragraph' === $type ) {
            return sanitize_textarea_field( $value );
        }
        if ( 'email' === $type ) {
            return sanitize_email( $value );
        }
        return sanitize_text_field( $value );
    }

    /**
     * @param array<int, array{value: string, label: string}> $options
     */
    private function valid_answer( string $type, string $value, array $options ): bool {
        if ( '' === trim( $value ) ) {
            return true;
        }
        if ( 'email' === $type ) {
            return is_email( $value );
        }
        if ( 'number' === $type ) {
            return is_numeric( $value );
        }
        if ( 'date' === $type ) {
            $date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
            return $date instanceof DateTimeImmutable && $date->format( 'Y-m-d' ) === $value;
        }
        if ( in_array( $type, [ 'dropdown', 'single_choice' ], true ) ) {
            return in_array( $value, array_column( $options, 'value' ), true );
        }
        return true;
    }

    /**
     * @param array<string, mixed> $files
     * @return array<string, mixed>|null
     */
    private function uploaded_file( array $files, string $key ): ?array {
        $group = $files['answers'] ?? null;
        if ( ! is_array( $group ) ) {
            return null;
        }
        $error = (int) ( $group['error'][ $key ] ?? UPLOAD_ERR_NO_FILE );
        if ( UPLOAD_ERR_NO_FILE === $error ) {
            return null;
        }
        return [
            'error'    => $error,
            'name'     => $group['name'][ $key ] ?? '',
            'tmp_name' => $group['tmp_name'][ $key ] ?? '',
            'size'     => $group['size'][ $key ] ?? 0,
        ];
    }

    /**
     * @param array<string, mixed> $file
     * @param array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validate_upload( array $file, string $title, array &$errors ): ?array {
        if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
            $errors[] = 'Upload untuk field "' . $title . '" gagal. Pilih file PDF, JPG, atau PNG maksimal 5 MB.';
            return null;
        }

        $temporary_path = (string) ( $file['tmp_name'] ?? '' );
        if ( '' === $temporary_path || ! is_uploaded_file( $temporary_path ) || ! is_readable( $temporary_path ) ) {
            $errors[] = 'File untuk field "' . $title . '" tidak dapat diverifikasi.';
            return null;
        }

        $size = filesize( $temporary_path );
        if ( false === $size || 0 === $size || $size > 5 * 1024 * 1024 ) {
            $errors[] = 'File untuk field "' . $title . '" harus berukuran 1 byte sampai 5 MB.';
            return null;
        }
        if ( ! function_exists( 'finfo_open' ) ) {
            $errors[] = 'Validasi tipe file belum tersedia di server.';
            return null;
        }

        $file_info = finfo_open( FILEINFO_MIME_TYPE );
        if ( false === $file_info ) {
            $errors[] = 'Validasi tipe file gagal dijalankan.';
            return null;
        }
        $mime_type = finfo_file( $file_info, $temporary_path );
        finfo_close( $file_info );

        $allowed_extensions = [
            'application/pdf' => [ 'pdf' ],
            'image/jpeg'      => [ 'jpg', 'jpeg' ],
            'image/png'       => [ 'png' ],
        ];
        $name = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
        $extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
        if ( ! is_string( $mime_type ) || ! isset( $allowed_extensions[ $mime_type ] ) || ! in_array( $extension, $allowed_extensions[ $mime_type ], true ) ) {
            $errors[] = 'File untuk field "' . $title . '" harus berupa PDF, JPG, atau PNG.';
            return null;
        }

        return [
            'name'     => $name,
            'mime_type' => $mime_type,
            'size'     => $size,
            'tmp_name' => $temporary_path,
        ];
    }

    /** @param mixed $value */
    private function scalar( $value ): string {
        return is_scalar( $value ) ? (string) $value : '';
    }

    /** @param mixed $value */
    private function identifier( $value ): string {
        $value = trim( $this->scalar( $value ) );
        return '' === $value || ctype_digit( $value ) ? $value : '';
    }

    private function text_length( string $value ): int {
        return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
    }
}
