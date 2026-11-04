<?php
if ( ! function_exists( 'recruitment_demo_candidates' ) ) {
    function recruitment_demo_candidates(): array {
        $candidates = [
            [ 'id' => 1, 'name' => 'Rafi Kurniawan', 'email' => 'rafi.kurniawan@example.com', 'phone' => '081234560001', 'created_at' => '2026-01-15 09:00:00', 'position' => 'Sales Consultant', 'dealer' => 'DAW Bandung', 'region' => 'Jawa Barat', 'status' => 'diterima', 'year' => '2026' ],
            [ 'id' => 2, 'name' => 'Sari Dewi Lestari', 'email' => 'sari.dewi@example.com', 'phone' => '081234560002', 'created_at' => '2026-01-21 10:30:00', 'position' => 'Staff Administrasi', 'dealer' => 'DAW Jakarta Selatan', 'region' => 'DKI Jakarta', 'status' => 'dalam_proses', 'year' => '2026' ],
            [ 'id' => 3, 'name' => 'Budi Santoso', 'email' => 'budi.santoso@example.com', 'phone' => '081234560003', 'created_at' => '2026-02-03 08:15:00', 'position' => 'Mekanik / Teknisi', 'dealer' => 'DAW Surabaya', 'region' => 'Jawa Timur', 'status' => 'tidak_diterima', 'year' => '2026' ],
            [ 'id' => 4, 'name' => 'Anisa Putri Rahma', 'email' => 'anisa.putri@example.com', 'phone' => '081234560004', 'created_at' => '2026-02-12 14:10:00', 'position' => 'Marketing Coordinator', 'dealer' => 'DAW Medan', 'region' => 'Sumatera Utara', 'status' => 'dipertimbangkan_kembali', 'year' => '2026' ],
            [ 'id' => 5, 'name' => 'Kevin Alexander', 'email' => 'kevin.alexander@example.com', 'phone' => '081234560005', 'created_at' => '2026-02-18 11:00:00', 'position' => 'Sales Executive', 'dealer' => 'DAW Airmadidi', 'region' => 'Sulawesi Utara', 'status' => 'dalam_proses', 'year' => '2026' ],
            [ 'id' => 6, 'name' => 'Maria Natalia', 'email' => 'maria.natalia@example.com', 'phone' => '081234560006', 'created_at' => '2026-03-02 15:45:00', 'position' => 'Finance & Accounting Staff', 'dealer' => 'DAW Manado', 'region' => 'Sulawesi Utara', 'status' => 'diterima', 'year' => '2026' ],
            [ 'id' => 7, 'name' => 'Daniel Pratama', 'email' => 'daniel.pratama@example.com', 'phone' => '081234560007', 'created_at' => '2026-03-11 09:35:00', 'position' => 'Service Advisor', 'dealer' => 'DAW Bitung', 'region' => 'Sulawesi Utara', 'status' => 'dalam_proses', 'year' => '2026' ],
            [ 'id' => 8, 'name' => 'Jessica Amanda', 'email' => 'jessica.amanda@example.com', 'phone' => '081234560008', 'created_at' => '2025-01-16 10:15:00', 'position' => 'Staff Administrasi', 'dealer' => 'DAW Ternate', 'region' => 'Maluku Utara', 'status' => 'diterima', 'year' => '2025' ],
            [ 'id' => 9, 'name' => 'Michael Christian', 'email' => 'michael.christian@example.com', 'phone' => '081234560009', 'created_at' => '2025-01-27 12:00:00', 'position' => 'Sales Executive', 'dealer' => 'DAW Bandung', 'region' => 'Jawa Barat', 'status' => 'tidak_diterima', 'year' => '2025' ],
            [ 'id' => 10, 'name' => 'Grace Olivia', 'email' => 'grace.olivia@example.com', 'phone' => '081234560010', 'created_at' => '2025-02-09 13:25:00', 'position' => 'Marketing Coordinator', 'dealer' => 'DAW Manado', 'region' => 'Sulawesi Utara', 'status' => 'dalam_proses', 'year' => '2025' ],
            [ 'id' => 11, 'name' => 'Jonathan Wijaya', 'email' => 'jonathan.wijaya@example.com', 'phone' => '081234560011', 'created_at' => '2025-02-20 08:50:00', 'position' => 'Mekanik / Teknisi', 'dealer' => 'DAW Surabaya', 'region' => 'Jawa Timur', 'status' => 'dalam_proses', 'year' => '2025' ],
            [ 'id' => 12, 'name' => 'Felicia Putri', 'email' => 'felicia.putri@example.com', 'phone' => '081234560012', 'created_at' => '2025-03-10 11:05:00', 'position' => 'Sales Consultant', 'dealer' => 'DAW Airmadidi', 'region' => 'Sulawesi Utara', 'status' => 'diterima', 'year' => '2025' ],
            [ 'id' => 13, 'name' => 'Andreas Samuel', 'email' => 'andreas.samuel@example.com', 'phone' => '081234560013', 'created_at' => '2024-02-01 09:30:00', 'position' => 'Service Advisor', 'dealer' => 'DAW Bitung', 'region' => 'Sulawesi Utara', 'status' => 'tidak_diterima', 'year' => '2024' ],
            [ 'id' => 14, 'name' => 'Claudia Angelica', 'email' => 'claudia.angelica@example.com', 'phone' => '081234560014', 'created_at' => '2024-02-12 14:00:00', 'position' => 'Staff Administrasi', 'dealer' => 'DAW Ternate', 'region' => 'Maluku Utara', 'status' => 'diterima', 'year' => '2024' ],
            [ 'id' => 15, 'name' => 'Reza Mahendra', 'email' => 'reza.mahendra@example.com', 'phone' => '081234560015', 'created_at' => '2024-03-08 07:45:00', 'position' => 'Sales Executive', 'dealer' => 'DAW Jakarta Selatan', 'region' => 'DKI Jakarta', 'status' => 'dalam_proses', 'year' => '2024' ],
            [ 'id' => 16, 'name' => 'Nathania Grace', 'email' => 'nathania.grace@example.com', 'phone' => '081234560016', 'created_at' => '2024-03-19 16:10:00', 'position' => 'Finance & Accounting Staff', 'dealer' => 'DAW Bandung', 'region' => 'Jawa Barat', 'status' => 'dipertimbangkan_kembali', 'year' => '2024' ],
        ];

        $vacancies = [
            [ 'id' => 1, 'title' => 'Sales Executive', 'location' => 'DAW Airmadidi', 'dealer' => 'DAW Airmadidi', 'region' => 'Sulawesi Utara' ],
            [ 'id' => 2, 'title' => 'Sales Consultant', 'location' => 'DAW Bandung', 'dealer' => 'DAW Bandung', 'region' => 'Jawa Barat' ],
            [ 'id' => 3, 'title' => 'Marketing Coordinator', 'location' => 'DAW Medan', 'dealer' => 'DAW Medan', 'region' => 'Sumatera Utara' ],
            [ 'id' => 4, 'title' => 'Staff Administrasi', 'location' => 'DAW Jakarta Selatan', 'dealer' => 'DAW Jakarta Selatan', 'region' => 'DKI Jakarta' ],
            [ 'id' => 5, 'title' => 'Finance & Accounting Staff', 'location' => 'DAW Manado', 'dealer' => 'DAW Manado', 'region' => 'Sulawesi Utara' ],
            [ 'id' => 6, 'title' => 'Mekanik / Teknisi', 'location' => 'DAW Surabaya', 'dealer' => 'DAW Surabaya', 'region' => 'Jawa Timur' ],
            [ 'id' => 7, 'title' => 'Service Advisor', 'location' => 'DAW Bitung', 'dealer' => 'DAW Bitung', 'region' => 'Sulawesi Utara' ],
        ];

        $applications = [];
        foreach ( $candidates as $index => $candidate ) {
            $applications[] = [
                'id' => $index + 1,
                'applicant_id' => $candidate['id'],
                'vacancy_id' => ( $index % 7 ) + 1,
                'status' => $candidate['status'],
                'created_at' => $candidate['created_at'],
            ];
        }

        return [ 'applicants' => $candidates, 'applications' => $applications, 'vacancies' => $vacancies ];
    }
}

$database = new Recruitment_Database();
$applications = $database->get_applications();
$applicants = $database->get_applicants();
$vacancies = $database->get_vacancies();

if ( empty( $applications ) && empty( $applicants ) ) {
    $demo_data = recruitment_demo_candidates();
    $applications = $demo_data['applications'];
    $applicants = $demo_data['applicants'];
    $vacancies = $demo_data['vacancies'];
}

$search = trim( (string) ( $_GET['search'] ?? '' ) );
$year_filter = sanitize_text_field( (string) ( $_GET['year'] ?? '' ) );
$position_filter = sanitize_text_field( (string) ( $_GET['position'] ?? '' ) );
$dealer_filter = sanitize_text_field( (string) ( $_GET['dealer'] ?? '' ) );
$status_filter = sanitize_text_field( (string) ( $_GET['status'] ?? '' ) );
$selected_candidate_id = absint( $_GET['candidate_id'] ?? 0 );

$all_statuses = [
    'all' => 'Semua Status',
    'diterima' => 'Diterima',
    'dalam_proses' => 'Dalam Proses',
    'tidak_diterima' => 'Tidak Diterima',
    'dipertimbangkan_kembali' => 'Dipertimbangkan Kembali',
];

$normalize_stage = static function ( $raw_status ): string {
    $status_key = strtolower( (string) $raw_status );
    $status_key = str_replace( [ ' ', '-', '/', '_' ], '_', $status_key );
    $status_key = preg_replace( '/_+/', '_', $status_key );

    if ( in_array( $status_key, [ 'accepted', 'diterima', 'lolos', 'final', 'completed' ], true ) ) {
        return 'final';
    }

    if ( in_array( $status_key, [ 'rejected', 'tidak_diterima', 'tidak_lolos', 'tolak' ], true ) ) {
        return 'final';
    }

    if ( in_array( $status_key, [ 'administrasi', 'administration', 'screening', 'submitted', 'dalam_proses' ], true ) ) {
        return 'administration';
    }

    if ( in_array( $status_key, [ 'psychological_test', 'psych_test', 'psikotes' ], true ) ) {
        return 'psychological_test';
    }

    if ( in_array( $status_key, [ 'interview', 'interview_hr', 'wawancara_hr', 'hr_interview' ], true ) ) {
        return 'hr_interview';
    }

    if ( in_array( $status_key, [ 'interview_user', 'wawancara_user', 'user_interview' ], true ) ) {
        return 'user_interview';
    }

    return 'administration';
};

$normalize_status_bucket = static function ( $raw_status ): string {
    $status_key = strtolower( (string) $raw_status );
    $status_key = str_replace( [ ' ', '-', '/', '_' ], '_', $status_key );
    $status_key = preg_replace( '/_+/', '_', $status_key );

    if ( in_array( $status_key, [ 'accepted', 'diterima', 'lolos', 'final', 'completed' ], true ) ) {
        return 'diterima';
    }

    if ( in_array( $status_key, [ 'rejected', 'tidak_diterima', 'tidak_lolos', 'tolak' ], true ) ) {
        return 'tidak_diterima';
    }

    if ( in_array( $status_key, [ 'considered_again', 'dipertimbangkan_kembali', 'reconsider', 'reconsideration' ], true ) ) {
        return 'dipertimbangkan_kembali';
    }

    return 'dalam_proses';
};

$applicant_map = [];
foreach ( $applicants as $applicant ) {
    $applicant = array_change_key_case( (array) $applicant, CASE_LOWER );
    $applicant_map[ (string) $applicant['id'] ] = $applicant;
}

$vacancy_map = [];
foreach ( $vacancies as $vacancy ) {
    $vacancy = array_change_key_case( (array) $vacancy, CASE_LOWER );
    $vacancy_map[ (string) $vacancy['id'] ] = $vacancy;
}

$latest_candidates = [];
foreach ( $applications as $application ) {
    $application = array_change_key_case( (array) $application, CASE_LOWER );
    $applicant_id = (int) ( $application['applicant_id'] ?? 0 );
    $candidate = $applicant_map[ (string) $applicant_id ] ?? [];
    $vacancy = $vacancy_map[ (string) ( $application['vacancy_id'] ?? 0 ) ] ?? [];

    if ( empty( $candidate ) ) {
        continue;
    }

    $created_at = (string) ( $application['created_at'] ?? $candidate['created_at'] ?? current_time( 'mysql' ) );
    $year = (string) date_i18n( 'Y', strtotime( $created_at ) );
    $position = (string) ( $vacancy['title'] ?? $vacancy['job_title'] ?? $candidate['position'] ?? 'Posisi belum ditentukan' );
    $dealer = (string) ( $vacancy['dealer'] ?? $vacancy['location'] ?? $candidate['dealer'] ?? '-' );
    $region = (string) ( $vacancy['region'] ?? $vacancy['location'] ?? $candidate['region'] ?? '' );
    $status_bucket = $normalize_status_bucket( $application['status'] ?? $candidate['status'] ?? 'submitted' );
    $stage_key = $normalize_stage( $application['status'] ?? $candidate['status'] ?? 'submitted' );
    $stage_label = [
        'administration' => 'Seleksi Administrasi',
        'psychological_test' => 'Psikotes',
        'hr_interview' => 'Wawancara HR',
        'user_interview' => 'Wawancara User',
        'final' => 'Keputusan Akhir',
    ][ $stage_key ] ?? 'Seleksi Administrasi';

    $code = 'DAW-' . $year . '-' . str_pad( (string) $applicant_id, 3, '0', STR_PAD_LEFT );
    $search_text = strtolower( trim( (string) ( $candidate['name'] ?? '' ) . ' ' . $code . ' ' . $position . ' ' . $dealer . ' ' . ( $candidate['email'] ?? '' ) ) );
    $latest_candidates[ (string) $applicant_id ] = [
        'id' => $applicant_id,
        'name' => (string) ( $candidate['name'] ?? 'Pelamar #' . $applicant_id ),
        'code' => $code,
        'email' => (string) ( $candidate['email'] ?? '' ),
        'phone' => (string) ( $candidate['phone'] ?? '' ),
        'position' => $position,
        'dealer' => $dealer,
        'region' => $region,
        'year' => $year,
        'stage' => $stage_label,
        'status_key' => $status_bucket,
        'status_label' => $all_statuses[ $status_bucket ] ?? 'Dalam Proses',
        'created_at' => $created_at,
        'search' => $search_text,
        'notes' => 'Kandidat memiliki komunikasi yang baik dan pengalaman kerja yang relevan.',
        'social' => [
            'instagram' => '',
            'linkedin' => '',
        ],
    ];
}

$all_candidates = array_values( $latest_candidates );
$filtered_candidates = [];
foreach ( $all_candidates as $candidate ) {
    $matches_search = '' === $search || false !== strpos( $candidate['search'], strtolower( $search ) );
    $matches_year = '' === $year_filter || (string) $candidate['year'] === $year_filter;
    $matches_position = '' === $position_filter || strtolower( $candidate['position'] ) === strtolower( $position_filter );
    $matches_dealer = '' === $dealer_filter || strtolower( $candidate['dealer'] ) === strtolower( $dealer_filter );
    $matches_status = '' === $status_filter || 'all' === $status_filter || $candidate['status_key'] === $status_filter;

    if ( $matches_search && $matches_year && $matches_position && $matches_dealer && $matches_status ) {
        $filtered_candidates[] = $candidate;
    }
}

$years = [];
foreach ( $all_candidates as $candidate ) {
    $years[ (string) $candidate['year'] ] = (string) $candidate['year'];
}
ksort( $years );
$years = array_reverse( $years, true );

$positions = [];
foreach ( $all_candidates as $candidate ) {
    if ( '' !== $candidate['position'] ) {
        $positions[ strtolower( $candidate['position'] ) ] = $candidate['position'];
    }
}
ksort( $positions );

$dealers = [];
foreach ( $all_candidates as $candidate ) {
    if ( '-' !== $candidate['dealer'] && '' !== $candidate['dealer'] ) {
        $dealers[ strtolower( $candidate['dealer'] ) ] = $candidate['dealer'];
    }
}
ksort( $dealers );

$stats = [
    'total' => count( $filtered_candidates ),
    'diterima' => 0,
    'dalam_proses' => 0,
    'tidak_diterima' => 0,
    'dipertimbangkan_kembali' => 0,
];
foreach ( $filtered_candidates as $candidate ) {
    if ( isset( $stats[ $candidate['status_key'] ] ) ) {
        $stats[ $candidate['status_key'] ]++;
    }
}

$summary_total = count( $all_candidates );
$selected_candidate = null;
if ( $selected_candidate_id > 0 ) {
    foreach ( $all_candidates as $candidate ) {
        if ( (int) $candidate['id'] === $selected_candidate_id ) {
            $selected_candidate = $candidate;
            break;
        }
    }
}

$filter_query = array_filter(
    [
        'page' => 'recruitment-applicants',
        'search' => $search !== '' ? $search : null,
        'year' => $year_filter !== '' ? $year_filter : null,
        'position' => $position_filter !== '' ? $position_filter : null,
        'dealer' => $dealer_filter !== '' ? $dealer_filter : null,
        'status' => $status_filter !== '' ? $status_filter : null,
    ],
    static fn ( $value ) => null !== $value && '' !== $value
);
$back_url = add_query_arg( $filter_query, admin_url( 'admin.php' ) );

$detail_tabs = [
    [ 'id' => 'data-diri', 'label' => 'Data Diri' ],
    [ 'id' => 'pendidikan', 'label' => 'Pendidikan' ],
    [ 'id' => 'pengalaman', 'label' => 'Pengalaman' ],
    [ 'id' => 'dokumen', 'label' => 'Dokumen' ],
    [ 'id' => 'riwayat-seleksi', 'label' => 'Riwayat Seleksi' ],
];

$detail_fields = [
    [ 'label' => 'Nama Lengkap', 'value' => $selected_candidate['name'] ?? 'Belum diisi' ],
    [ 'label' => 'NIK', 'value' => 'Belum diisi' ],
    [ 'label' => 'Tempat Lahir', 'value' => 'Belum diisi' ],
    [ 'label' => 'Tanggal Lahir', 'value' => 'Belum diisi' ],
    [ 'label' => 'Jenis Kelamin', 'value' => 'Belum diisi' ],
    [ 'label' => 'Provinsi', 'value' => $selected_candidate['region'] ?? 'Belum diisi' ],
    [ 'label' => 'Kota', 'value' => 'Belum diisi' ],
    [ 'label' => 'Alamat', 'value' => 'Belum diisi' ],
    [ 'label' => 'Email', 'value' => $selected_candidate['email'] ?? 'Belum diisi' ],
    [ 'label' => 'Nomor Telepon', 'value' => $selected_candidate['phone'] ?? 'Belum diisi' ],
    [ 'label' => 'Status Pernikahan', 'value' => 'Belum diisi' ],
];

$timeline_steps = [
    [ 'key' => 'lamaran_diterima', 'label' => 'Lamaran Diterima', 'status' => 'Selesai' ],
    [ 'key' => 'seleksi_administrasi', 'label' => 'Seleksi Administrasi', 'status' => 'Selesai' ],
    [ 'key' => 'psikotes', 'label' => 'Psikotes', 'status' => 'Sedang Berlangsung' ],
    [ 'key' => 'wawancara_hr', 'label' => 'Wawancara HR', 'status' => 'Belum Dimulai' ],
    [ 'key' => 'wawancara_user', 'label' => 'Wawancara User', 'status' => 'Belum Dimulai' ],
    [ 'key' => 'keputusan_akhir', 'label' => 'Keputusan Akhir', 'status' => 'Belum Dimulai' ],
];

if ( 'diterima' === ( $selected_candidate['status_key'] ?? '' ) ) {
    $timeline_steps[0]['status'] = 'Selesai';
    $timeline_steps[1]['status'] = 'Selesai';
    $timeline_steps[2]['status'] = 'Selesai';
    $timeline_steps[3]['status'] = 'Selesai';
    $timeline_steps[4]['status'] = 'Selesai';
    $timeline_steps[5]['status'] = 'Selesai';
} elseif ( 'dalam_proses' === ( $selected_candidate['status_key'] ?? '' ) ) {
    $timeline_steps[0]['status'] = 'Selesai';
    $timeline_steps[1]['status'] = 'Sedang Berlangsung';
    $timeline_steps[2]['status'] = 'Belum Dimulai';
    $timeline_steps[3]['status'] = 'Belum Dimulai';
    $timeline_steps[4]['status'] = 'Belum Dimulai';
    $timeline_steps[5]['status'] = 'Belum Dimulai';
} elseif ( 'tidak_diterima' === ( $selected_candidate['status_key'] ?? '' ) ) {
    $timeline_steps[0]['status'] = 'Selesai';
    $timeline_steps[1]['status'] = 'Selesai';
    $timeline_steps[2]['status'] = 'Selesai';
    $timeline_steps[3]['status'] = 'Selesai';
    $timeline_steps[4]['status'] = 'Selesai';
    $timeline_steps[5]['status'] = 'Tidak Lolos';
} elseif ( 'dipertimbangkan_kembali' === ( $selected_candidate['status_key'] ?? '' ) ) {
    $timeline_steps[0]['status'] = 'Selesai';
    $timeline_steps[1]['status'] = 'Selesai';
    $timeline_steps[2]['status'] = 'Menunggu Review';
    $timeline_steps[3]['status'] = 'Belum Dimulai';
    $timeline_steps[4]['status'] = 'Belum Dimulai';
    $timeline_steps[5]['status'] = 'Belum Dimulai';
}

$view_mode = $selected_candidate ? 'detail' : 'list';
?>
<div class="wrap recruitment-admin daw-applicants-page" data-applicants-screen>
    <?php if ( 'detail' === $view_mode && $selected_candidate ) : ?>
        <div class="daw-applicants__header">
            <p class="daw-applicants__breadcrumb">DAW Admin / Detail Kandidat</p>
            <a class="daw-applicants__back" href="<?= esc_url( $back_url ) ?>">← Kembali ke Daftar Pelamar</a>
        </div>

        <section class="daw-applicant-profile-card">
            <div class="daw-applicant-profile-card__avatar">
                <?= esc_html( strtoupper( substr( $selected_candidate['name'], 0, 1 ) ) ) ?>
            </div>
            <div class="daw-applicant-profile-card__identity">
                <h1><?= esc_html( $selected_candidate['name'] ) ?></h1>
                <p class="daw-applicant-profile-card__code"><?= esc_html( $selected_candidate['code'] ) ?></p>
                <div class="daw-applicant-profile-card__meta">
                    <span><?= esc_html( $selected_candidate['position'] ) ?></span>
                    <span><?= esc_html( $selected_candidate['dealer'] ) ?></span>
                    <span><?= esc_html( $selected_candidate['region'] ?: 'Wilayah belum tersedia' ) ?></span>
                </div>
            </div>
            <div class="daw-applicant-profile-card__stage">
                <span class="daw-stage-badge daw-stage-badge--<?= esc_attr( strtolower( str_replace( [ ' ', '/' ], '-', $selected_candidate['stage'] ) ) ) ?>"><?= esc_html( $selected_candidate['stage'] ) ?></span>
            </div>
        </section>

        <section class="daw-detail-contact-row">
            <div class="daw-detail-contact-row__item">
                <span>Tanggal Daftar</span>
                <strong><?= esc_html( date_i18n( 'd F Y', strtotime( $selected_candidate['created_at'] ) ) ) ?></strong>
            </div>
            <div class="daw-detail-contact-row__item">
                <span>Email</span>
                <strong><?= $selected_candidate['email'] ? '<a href="mailto:' . esc_attr( $selected_candidate['email'] ) . '">' . esc_html( $selected_candidate['email'] ) . '</a>' : '-' ?></strong>
            </div>
            <div class="daw-detail-contact-row__item">
                <span>Telepon</span>
                <strong><?= $selected_candidate['phone'] ? '<a href="tel:' . esc_attr( $selected_candidate['phone'] ) . '">' . esc_html( $selected_candidate['phone'] ) . '</a>' : '-' ?></strong>
            </div>
        </section>

        <section class="daw-documents-panel">
            <h2>Unduh Dokumen</h2>
            <div class="daw-documents-panel__actions">
                <button type="button" class="daw-document-btn" disabled>Download Profil Pelamar</button>
                <button type="button" class="daw-document-btn daw-document-btn--muted" disabled>Download Laporan Psikotes</button>
            </div>
            <p class="daw-documents-panel__hint">Dokumen kandidat belum tersedia di sistem saat ini. Tombol akan aktif otomatis setelah file diunggah dan diverifikasi.</p>
        </section>

        <section class="daw-applicant-detail-tabs" data-applicant-tabs>
            <div class="daw-applicant-detail-tabs__nav" role="tablist" aria-label="Detail kandidat">
                <?php foreach ( $detail_tabs as $index => $tab ) : ?>
                    <button
                        type="button"
                        class="daw-applicant-detail-tabs__tab <?= 0 === $index ? 'is-active' : '' ?>"
                        data-applicant-tab="<?= esc_attr( $tab['id'] ) ?>"
                        role="tab"
                        aria-selected="<?= 0 === $index ? 'true' : 'false' ?>"
                    >
                        <?= esc_html( $tab['label'] ) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="daw-applicant-detail-tabs__content">
                <div class="daw-applicant-detail-panel is-active" data-applicant-panel="data-diri" role="tabpanel">
                    <div class="daw-detail-grid">
                        <?php foreach ( $detail_fields as $field ) : ?>
                            <div class="daw-detail-field">
                                <span><?= esc_html( $field['label'] ) ?></span>
                                <strong><?= esc_html( $field['value'] ) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="daw-applicant-detail-panel" data-applicant-panel="pendidikan" role="tabpanel" hidden>
                    <div class="daw-empty-state">Data pendidikan belum tersedia untuk kandidat ini.</div>
                </div>

                <div class="daw-applicant-detail-panel" data-applicant-panel="pengalaman" role="tabpanel" hidden>
                    <div class="daw-empty-state">Riwayat pengalaman belum diisi oleh kandidat.</div>
                </div>

                <div class="daw-applicant-detail-panel" data-applicant-panel="dokumen" role="tabpanel" hidden>
                    <div class="daw-empty-state">Belum ada dokumen yang diunggah untuk kandidat ini.</div>
                </div>

                <div class="daw-applicant-detail-panel" data-applicant-panel="riwayat-seleksi" role="tabpanel" hidden>
                    <div class="daw-selection-history">
                        <?php foreach ( $timeline_steps as $step ) : ?>
                            <div class="daw-selection-history__item">
                                <span class="daw-selection-history__marker daw-selection-history__marker--<?= esc_attr( strtolower( str_replace( ' ', '-', $step['status'] ) ) ) ?>"></span>
                                <div class="daw-selection-history__content">
                                    <div class="daw-selection-history__header">
                                        <strong><?= esc_html( $step['label'] ) ?></strong>
                                        <span class="daw-status-badge daw-status-badge--<?= esc_attr( strtolower( str_replace( ' ', '-', $step['status'] ) ) ) ?>"><?= esc_html( $step['status'] ) ?></span>
                                    </div>
                                    <p><?= esc_html( 'Tanggal proses belum tersedia untuk tahap ini.' ) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php else : ?>
        <div class="daw-applicants__header">
            <div>
                <p class="daw-applicants__breadcrumb">DAW Admin / Database Pelamar</p>
                <h1>Database Pelamar</h1>
                <p class="daw-applicants__subtitle">Arsip kandidat lintas tahun rekrutmen DAW Group</p>
            </div>
        </div>

        <form method="get" class="daw-applicant-filter-panel" action="<?= esc_url( admin_url( 'admin.php' ) ) ?>">
            <input type="hidden" name="page" value="recruitment-applicants" />

            <div class="daw-applicant-filter-panel__row">
                <label class="daw-applicant-search">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <input type="search" name="search" value="<?= esc_attr( $search ) ?>" placeholder="Cari nama atau kode..." />
                </label>

                <select name="year">
                    <option value="">Tahun</option>
                    <?php foreach ( $years as $year ) : ?>
                        <option value="<?= esc_attr( $year ) ?>" <?= selected( $year_filter, $year, false ) ?>><?= esc_html( $year ) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="position">
                    <option value="">Posisi</option>
                    <?php foreach ( $positions as $position ) : ?>
                        <option value="<?= esc_attr( $position ) ?>" <?= selected( $position_filter, $position, false ) ?>><?= esc_html( $position ) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="dealer">
                    <option value="">Dealer / Cabang</option>
                    <?php foreach ( $dealers as $dealer ) : ?>
                        <option value="<?= esc_attr( $dealer ) ?>" <?= selected( $dealer_filter, $dealer, false ) ?>><?= esc_html( $dealer ) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="status">
                    <option value="">Status</option>
                    <?php foreach ( $all_statuses as $status_key => $status_label ) : ?>
                        <option value="<?= esc_attr( $status_key ) ?>" <?= selected( $status_filter, $status_key, false ) ?>><?= esc_html( $status_label ) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="daw-applicant-filter-panel__submit">Terapkan</button>
                <a href="<?= esc_url( admin_url( 'admin.php?page=recruitment-applicants' ) ) ?>" class="daw-applicant-filter-panel__reset">Reset</a>
            </div>
        </form>

        <section class="daw-applicant-stats" aria-label="Statistik pelamar">
            <div class="daw-applicant-stat-card daw-applicant-stat-card--dark">
                <span>Total Pelamar</span>
                <strong><?= esc_html( number_format_i18n( $stats['total'] ) ) ?></strong>
            </div>
            <div class="daw-applicant-stat-card daw-applicant-stat-card--green">
                <span>Diterima</span>
                <strong><?= esc_html( number_format_i18n( $stats['diterima'] ) ) ?></strong>
            </div>
            <div class="daw-applicant-stat-card daw-applicant-stat-card--blue">
                <span>Dalam Proses</span>
                <strong><?= esc_html( number_format_i18n( $stats['dalam_proses'] ) ) ?></strong>
            </div>
            <div class="daw-applicant-stat-card daw-applicant-stat-card--red">
                <span>Tidak Diterima</span>
                <strong><?= esc_html( number_format_i18n( $stats['tidak_diterima'] ) ) ?></strong>
            </div>
        </section>

        <section class="daw-applicant-table-card">
            <div class="daw-applicant-table-card__top">
                <div>
                    <span class="daw-applicant-table-card__label">Jumlah Data</span>
                    <strong><?= esc_html( number_format_i18n( count( $filtered_candidates ) ) ) ?> dari <?= esc_html( number_format_i18n( $summary_total ) ) ?></strong>
                </div>
            </div>

            <?php if ( empty( $filtered_candidates ) ) : ?>
                <div class="daw-empty-state">Kandidat tidak ditemukan.</div>
            <?php else : ?>
                <div class="daw-applicant-table-wrap">
                    <table class="daw-applicant-table">
                        <thead>
                            <tr>
                                <th>Nama / Kode</th>
                                <th>Posisi / Dealer</th>
                                <th>Tahun</th>
                                <th>Tahap Terakhir</th>
                                <th>Status</th>
                                <th>Catatan</th>
                                <th>Sosmed</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $filtered_candidates as $candidate ) : ?>
                                <tr>
                                    <td>
                                        <div class="daw-applicant-name-cell">
                                            <span class="daw-applicant-name-cell__avatar"><?= esc_html( strtoupper( substr( $candidate['name'], 0, 1 ) ) ) ?></span>
                                            <div>
                                                <strong><?= esc_html( $candidate['name'] ) ?></strong>
                                                <small><?= esc_html( $candidate['code'] ) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="daw-applicant-position-cell">
                                            <strong><?= esc_html( $candidate['position'] ) ?></strong>
                                            <small><?= esc_html( $candidate['dealer'] ) ?></small>
                                        </div>
                                    </td>
                                    <td><span class="daw-year-badge"><?= esc_html( $candidate['year'] ) ?></span></td>
                                    <td><span class="daw-stage-badge daw-stage-badge--<?= esc_attr( strtolower( str_replace( ' ', '-', $candidate['stage'] ) ) ) ?>"><?= esc_html( $candidate['stage'] ) ?></span></td>
                                    <td><span class="daw-status-badge daw-status-badge--<?= esc_attr( $candidate['status_key'] ) ?>"><?= esc_html( $candidate['status_label'] ) ?></span></td>
                                    <td class="daw-note-cell"><?= esc_html( $candidate['notes'] ) ?></td>
                                    <td class="daw-social-cell">
                                        <?php if ( ! empty( $candidate['social']['instagram'] ) ) : ?>
                                            <a href="<?= esc_url( $candidate['social']['instagram'] ) ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram">📷</a>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $candidate['social']['linkedin'] ) ) : ?>
                                            <a href="<?= esc_url( $candidate['social']['linkedin'] ) ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">in</a>
                                        <?php endif; ?>
                                        <?php if ( empty( $candidate['social']['instagram'] ) && empty( $candidate['social']['linkedin'] ) ) : ?>
                                            <span>-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a class="daw-detail-button" href="<?= esc_url( add_query_arg( array_merge( $filter_query, [ 'candidate_id' => (int) $candidate['id'] ] ), admin_url( 'admin.php' ) ) ) ?>">Detail</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
