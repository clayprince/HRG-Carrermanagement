<?php
$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
$display_name = $user && ! empty( $user->display_name ) ? $user->display_name : 'Admin HR';
$user_email = $user && ! empty( $user->user_email ) ? $user->user_email : 'hr@daw.co.id';
$logout_form = recruitment_logout_form( 'Keluar', home_url( '/' ), 'daw-user-interview__logout' );
$view = sanitize_key( $_GET['view'] ?? '' );
$show_notes = 'notes' === $view;
$show_queue = ! $show_notes && 'results' !== $view;
$show_results = ! $show_notes && ! $show_queue;
$list_url = recruitment_get_admin_url( 'user-interview' );
$queue_url = add_query_arg( 'view', 'queue', $list_url );
$results_url = add_query_arg( 'view', 'results', $list_url );
$notes_url = add_query_arg( 'view', 'notes', $list_url );
$result_search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
$result_department = sanitize_text_field( wp_unslash( $_GET['department'] ?? '' ) );
$result_rating = sanitize_text_field( wp_unslash( $_GET['rating'] ?? '' ) );

$database = new Recruitment_Database();
$applications = $database->get_applications();
$database_error = $database->has_error();
$active_candidate_count = $database->get_active_applicant_count();

$candidates = [];
$stats = [
    'waiting'     => 0,
    'interviewed' => 0,
    'passed'      => 0,
    'failed'      => 0,
];
$unverified_interview_count = 0;

foreach ( $applications as $app ) {
    $raw_status = strtoupper( (string) ( $app['status'] ?? 'SUBMITTED' ) );
    $selection_status = trim( (string) ( $app['user_selection_status'] ?? '' ) );
    $has_result = '' !== $selection_status;
    $is_passed = in_array( $selection_status, [ 'Lolos Wawancara User', 'Lolos' ], true );
    $is_failed = in_array( $selection_status, [ 'Tidak Lolos Wawancara User', 'Tidak Lolos' ], true );
    $hr_passed = 'LOLOS' === strtoupper( trim( (string) ( $app['hr_result'] ?? '' ) ) );
    $has_department_assignment = (int) ( $app['department_account_id'] ?? 0 ) > 0;
    $is_waiting = 'INTERVIEW_USER' === $raw_status && ! $has_result && $hr_passed && $has_department_assignment;

    if ( ! $has_result && ! $is_waiting ) {
        if ( 'INTERVIEW_USER' === $raw_status ) {
            $unverified_interview_count++;
        }
        continue;
    }

    if ( $is_waiting ) {
        $stats['waiting']++;
    } elseif ( $has_result ) {
        $stats['interviewed']++;
        if ( $is_passed ) {
            $stats['passed']++;
        } elseif ( $is_failed ) {
            $stats['failed']++;
        }
    }

    $created_at = (string) ( $app['created_at'] ?? '' );
    $year = $created_at ? date( 'Y', strtotime( $created_at ) ) : date( 'Y' );
    $cand_id = (int) ( $app['applicant_id'] ?? 0 );
    $final_decision = strtoupper( trim( (string) ( $app['hr_final_decision'] ?? '' ) ) );
    if ( '' === $final_decision ) {
        $final_decision = strtoupper( trim( (string) ( $app['final_status'] ?? '' ) ) );
    }

    $candidates[] = [
        'id'             => $cand_id,
        'application_id' => (int) $app['id'],
        'name'           => (string) ( $app['applicant_name'] ?: 'Pelamar #' . $cand_id ),
        'code'           => 'DAW-' . $year . '-' . str_pad( (string) $cand_id, 4, '0', STR_PAD_LEFT ),
        'position'       => (string) ( $app['title'] ?: 'Posisi belum ditentukan' ),
        'dealer'         => (string) ( $app['dealer'] ?: '-' ),
        'department'     => (string) ( $app['department'] ?: '-' ),
        'region'         => (string) ( $app['location'] ?: $app['province'] ?: '-' ),
        'hr_result'      => trim( (string) ( $app['hr_result'] ?? '' ) ) ?: 'Belum tersedia',
        'interview_date' => trim( (string) ( $app['user_scheduled_interview_date'] ?? '' ) ) ?: ( trim( (string) ( $app['user_interview_date'] ?? '' ) ) ?: '—' ),
        'interview_time' => trim( (string) ( $app['user_scheduled_interview_time'] ?? '' ) ),
        'interview_method' => [
            'ONLINE' => 'Online',
            'OFFLINE' => 'Tatap muka',
        ][ strtoupper( trim( (string) ( $app['user_scheduled_interview_method'] ?? '' ) ) ) ] ?? '',
        'interview_location' => trim( (string) ( $app['user_scheduled_interview_location'] ?? '' ) ),
        'interview_link' => trim( (string) ( $app['user_scheduled_interview_link'] ?? '' ) ),
        'status'         => $has_result ? $selection_status : 'Menunggu Wawancara',
        'status_class'   => $is_passed ? 'is-passed' : ( $is_failed ? 'is-failed' : 'is-waiting' ),
        'score'          => (string) ( $app['user_score'] ?? '' ),
        'recommendation' => trim( (string) ( $app['user_recommendation'] ?? '' ) ),
        'interviewer'    => trim( (string) ( $app['user_interviewer'] ?? '' ) ) ?: trim( (string) ( $app['user_scheduled_interviewer'] ?? '' ) ),
        'notes'          => trim( (string) ( $app['user_interview_notes'] ?? '' ) ),
        'has_result'     => $has_result,
        'final_decision' => [
            'DITERIMA' => 'Diterima',
            'ACCEPTED' => 'Diterima',
            'LOLOS' => 'Diterima',
            'DITOLAK' => 'Ditolak',
            'REJECTED' => 'Ditolak',
            'TIDAK DITERIMA' => 'Ditolak',
            'TIDAK_LOLOS' => 'Ditolak',
        ][ $final_decision ] ?? 'Pending',
    ];
}

$result_candidates = array_values(
    array_filter(
        $candidates,
        static fn ( array $candidate ): bool => ! empty( $candidate['has_result'] )
    )
);
$filtered_result_candidates = array_values(
    array_filter(
        $result_candidates,
        static function ( array $candidate ) use ( $result_search, $result_department, $result_rating ): bool {
            $searchable = strtolower( implode( ' ', [ $candidate['name'], $candidate['code'], $candidate['position'], $candidate['dealer'], $candidate['department'] ?? '' ] ) );
            return ( '' === $result_search || false !== strpos( $searchable, strtolower( $result_search ) ) )
                && ( '' === $result_department || $candidate['department'] === $result_department )
                && ( '' === $result_rating || $candidate['recommendation'] === $result_rating );
        }
    )
);
$result_departments = array_values( array_unique( array_filter( array_column( $result_candidates, 'department' ) ) ) );
$result_ratings = array_values( array_unique( array_filter( array_column( $result_candidates, 'recommendation' ) ) ) );
sort( $result_departments );
sort( $result_ratings );
$result_filter_url = static function ( array $args = [] ) use ( $list_url, $result_search, $result_department, $result_rating ): string {
    $current_filters = array_filter(
        [
            'search' => $result_search,
            'department' => $result_department,
            'rating' => $result_rating,
        ],
        static fn ( string $value ): bool => '' !== $value
    );
    return add_query_arg( array_merge( [ 'view' => 'results' ], $current_filters, $args ), $list_url );
};
$result_stats = [
    'total' => count( $result_candidates ),
    'good' => count(
        array_filter(
            $result_candidates,
            static fn ( array $candidate ): bool => in_array( $candidate['recommendation'], [ 'Sangat Baik', 'Baik' ], true )
        )
    ),
    'accepted' => count( array_filter( $result_candidates, static fn ( array $candidate ): bool => 'Diterima' === $candidate['final_decision'] ) ),
    'pending' => count( array_filter( $result_candidates, static fn ( array $candidate ): bool => 'Pending' === $candidate['final_decision'] ) ),
];

$selected_candidate_id = absint( $_GET['candidate_id'] ?? 0 );
$selected_application_id = absint( $_GET['application_id'] ?? 0 );
$selected_cand = null;
if ( $selected_application_id > 0 || $selected_candidate_id > 0 ) {
    foreach ( $candidates as $c ) {
        if ( ( $selected_application_id > 0 && $c['application_id'] === $selected_application_id )
            || ( 0 === $selected_application_id && $c['id'] === $selected_candidate_id ) ) {
            $selected_cand = $c;
            break;
        }
    }
}
if ( ! $selected_cand && 0 === $selected_application_id && 0 === $selected_candidate_id && ! empty( $candidates ) ) {
    $selected_cand = $candidates[0];
}
?>
<div class="daw-user-interview<?= $show_notes || $show_results ? ' daw-user-interview--notes daw-user-interview--results' : '' ?>">
    <?php if ( $show_notes || $show_results ) : ?>
        <aside class="daw-user-interview__sidebar">
            <a class="daw-user-interview__brand" href="<?= esc_url( $queue_url ) ?>"><span>DAW</span><strong>Admin</strong></a>
            <nav aria-label="Navigasi admin">
                <span class="daw-user-interview__nav-item">Dashboard</span>
                <span class="daw-user-interview__nav-heading">Recruitment</span>
                <span class="daw-user-interview__nav-item">Semua Kandidat Aktif <b><?= esc_html( number_format_i18n( $active_candidate_count ) ) ?></b></span>
                <span class="daw-user-interview__nav-item">Seleksi Administrasi</span>
                <span class="daw-user-interview__nav-item">Psikotes</span>
                <span class="daw-user-interview__nav-item">Wawancara HR</span>
                <a class="daw-user-interview__nav-item" href="<?= esc_url( $queue_url ) ?>">Wawancara User</a>
                <a class="daw-user-interview__nav-item daw-user-interview__nav-subitem <?= $show_results ? 'is-active' : '' ?>" href="<?= esc_url( $results_url ) ?>">Detail Hasil Wawancara User</a>
                <a class="daw-user-interview__nav-item" href="<?= esc_url( recruitment_get_admin_url( 'final-decision' ) ) ?>">Final Decision</a>
                <span class="daw-user-interview__nav-item">Database Pelamar</span>
                <details class="daw-user-interview__nav-group" open>
                    <summary class="daw-user-interview__nav-item">Lowongan</summary>
                    <a class="daw-user-interview__nav-item daw-user-interview__nav-subitem" href="<?= esc_url( recruitment_get_admin_url( 'vacancies' ) ) ?>">Daftar Lowongan</a>
                    <a class="daw-user-interview__nav-item daw-user-interview__nav-subitem" href="<?= esc_url( recruitment_get_admin_url( 'applications' ) ) ?>">Pengajuan Lowongan</a>
                </details>
                <a class="daw-user-interview__nav-item" href="<?= esc_url( recruitment_get_admin_url( 'interview-schedule' ) ) ?>">Jadwal Wawancara HR</a>
                <a class="daw-user-interview__nav-item" href="<?= esc_url( recruitment_get_admin_url( 'interview-schedule-user' ) ) ?>">Jadwal Wawancara User</a>
                <span class="daw-user-interview__nav-item">Email Recruitment</span>
                <span class="daw-user-interview__nav-item">Akun User Dept</span>
            </nav>
        </aside>
    <?php endif; ?>
    <header class="daw-user-interview__header">
        <nav class="daw-user-interview__breadcrumb" aria-label="Breadcrumb">
            <span class="daw-user-interview__breadcrumb-root">DAW Admin</span>
            <span aria-hidden="true">/</span>
            <strong><?= $show_notes ? 'Hasil Wawancara User' : ( $show_results ? 'Detail Hasil Wawancara User' : 'Wawancara User' ) ?></strong>
        </nav>
        <div class="daw-user-interview__account">
            <span class="daw-user-interview__avatar" aria-hidden="true">HR</span>
            <span class="daw-user-interview__account-name">
                <strong><?= esc_html( $display_name ) ?></strong>
                <small><?= esc_html( $user_email ) ?></small>
            </span>
            <?= $logout_form ?>
        </div>
    </header>

    <main class="daw-user-interview__main">
        <?php if ( $show_notes ) : ?>
            <?php if ( $database_error ) : ?><div class="notice notice-error"><p>Data hasil wawancara User belum dapat dimuat dari Oracle. Silakan coba kembali.</p></div><?php endif; ?>
            <a class="daw-user-interview__back" href="<?= esc_url( $list_url ) ?>"><span aria-hidden="true">&#8249;</span> Kembali ke Daftar Wawancara</a>
            <?php if ( $selected_cand ) : ?>
                <div class="daw-user-interview__detail-grid">
                    <section class="daw-user-interview__card daw-user-interview__candidate-card" aria-labelledby="candidate-card-title">
                        <h1 id="candidate-card-title">Kandidat</h1>
                        <div class="daw-user-interview__candidate-profile">
                            <span class="daw-user-interview__candidate-avatar" aria-hidden="true"><?= esc_html( strtoupper( substr( $selected_cand['name'], 0, 2 ) ) ) ?></span>
                            <span><strong><?= esc_html( $selected_cand['name'] ) ?></strong><small><?= esc_html( $selected_cand['code'] ) ?></small></span>
                        </div>
                        <dl class="daw-user-interview__candidate-facts">
                            <div><dt>Posisi</dt><dd><?= esc_html( $selected_cand['position'] ) ?></dd></div>
                            <div><dt>Dealer</dt><dd><?= esc_html( $selected_cand['dealer'] ) ?></dd></div>
                            <div><dt>Wilayah</dt><dd><?= esc_html( $selected_cand['region'] ) ?></dd></div>
                            <div><dt>Hasil HR</dt><dd><?= esc_html( $selected_cand['hr_result'] ) ?></dd></div>
                        </dl>
                        <details class="daw-user-interview__profile-details">
                            <summary>Lihat Profil Lengkap</summary>
                            <p><?= esc_html( $selected_cand['name'] . ' · ' . $selected_cand['position'] . ' · ' . $selected_cand['dealer'] ) ?></p>
                            <a class="button button-small" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-applicants', 'candidate_id' => $selected_cand['id'], 'application_id' => $selected_cand['application_id'] ], admin_url( 'admin.php' ) ) ) ?>">Buka Database Pelamar</a>
                        </details>
                    </section>

                    <section class="daw-user-interview__card daw-user-interview__notes-card" aria-labelledby="notes-title">
                        <h1 id="notes-title">Hasil Wawancara User</h1>
                        <dl class="daw-user-interview__candidate-facts">
                            <div><dt>Status</dt><dd><?= esc_html( $selected_cand['status'] ) ?></dd></div>
                            <div><dt>Jadwal Wawancara</dt><dd><?= esc_html( trim( $selected_cand['interview_date'] . ' ' . $selected_cand['interview_time'] ) ) ?></dd></div>
                            <?php if ( '' !== $selected_cand['interview_method'] ) : ?><div><dt>Metode</dt><dd><?= esc_html( $selected_cand['interview_method'] ) ?></dd></div><?php endif; ?>
                            <?php if ( '' !== $selected_cand['interview_location'] ) : ?><div><dt>Lokasi/Platform</dt><dd><?= esc_html( $selected_cand['interview_location'] ) ?></dd></div><?php endif; ?>
                            <?php if ( '' !== $selected_cand['interview_link'] ) : ?><div><dt>Link Wawancara</dt><dd><a href="<?= esc_url( $selected_cand['interview_link'] ) ?>" target="_blank" rel="noopener noreferrer"><?= esc_html( $selected_cand['interview_link'] ) ?></a></dd></div><?php endif; ?>
                            <div><dt>Interviewer</dt><dd><?= esc_html( $selected_cand['interviewer'] ?: '—' ) ?></dd></div>
                            <div><dt>Skor</dt><dd><?= '' !== $selected_cand['score'] ? esc_html( $selected_cand['score'] . '/100' ) : '—' ?></dd></div>
                            <div><dt>Rekomendasi</dt><dd><?= esc_html( $selected_cand['recommendation'] ?: 'Belum tersedia' ) ?></dd></div>
                            <div><dt>Keputusan Final HR</dt><dd><?= esc_html( $selected_cand['final_decision'] ?: 'Menunggu Keputusan' ) ?></dd></div>
                        </dl>
                        <p><?= '' !== $selected_cand['notes'] ? nl2br( esc_html( $selected_cand['notes'] ) ) : 'Catatan wawancara belum tersedia.' ?></p>
                        <a class="button button-primary" href="<?= esc_url( recruitment_get_admin_url( 'final-decision' ) ) ?>">Buka Final Decision</a>
                    </section>
                </div>
            <?php else : ?>
                <div class="daw-empty-state" style="padding:40px; text-align:center; background:#fff; border-radius:8px;">Belum ada kandidat yang tersedia untuk wawancara user.</div>
            <?php endif; ?>
        <?php elseif ( $show_results ) : ?>
            <?php if ( $database_error ) : ?><div class="notice notice-error"><p>Data hasil wawancara User belum dapat dimuat dari Oracle. Silakan coba kembali.</p></div><?php endif; ?>
            <div class="daw-user-results__heading">
                <div><h1>Detail Hasil Wawancara User</h1><p>Rekap hasil wawancara user yang telah disampaikan oleh Kepala Departemen. Data ini hanya untuk review HR — bukan tempat HR melakukan wawancara user.</p></div>
            </div>
            <section class="daw-user-results__stats" aria-label="Ringkasan hasil wawancara User">
                <article><span>Total Diwawancarai</span><strong><?= esc_html( (string) $result_stats['total'] ) ?></strong></article>
                <article><span>Sangat Baik / Baik</span><strong class="is-green"><?= esc_html( (string) $result_stats['good'] ) ?></strong></article>
                <article><span>Diterima</span><strong class="is-green"><?= esc_html( (string) $result_stats['accepted'] ) ?></strong></article>
                <article><span>Menunggu Keputusan</span><strong class="is-amber"><?= esc_html( (string) $result_stats['pending'] ) ?></strong></article>
            </section>
            <div class="daw-user-results__filter-bar">
                <nav class="daw-user-results__filters daw-user-results__filters--departments" aria-label="Filter departemen">
                    <a class="<?= '' === $result_department ? 'is-active' : '' ?>" href="<?= esc_url( $result_filter_url( [ 'department' => '' ] ) ) ?>">Semua</a>
                    <?php foreach ( $result_departments as $department_name ) : ?>
                        <a class="<?= $result_department === $department_name ? 'is-active' : '' ?>" href="<?= esc_url( $result_filter_url( [ 'department' => $department_name ] ) ) ?>"><?= esc_html( $department_name ) ?></a>
                    <?php endforeach; ?>
                </nav>
                <nav class="daw-user-results__filters daw-user-results__filters--ratings" aria-label="Filter hasil wawancara">
                    <a class="<?= '' === $result_rating ? 'is-active' : '' ?>" href="<?= esc_url( $result_filter_url( [ 'rating' => '' ] ) ) ?>">Semua</a>
                    <?php foreach ( $result_ratings as $rating_name ) : ?>
                        <a class="<?= $result_rating === $rating_name ? 'is-active' : '' ?>" href="<?= esc_url( $result_filter_url( [ 'rating' => $rating_name ] ) ) ?>"><?= esc_html( $rating_name ) ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <section class="daw-user-results__list" aria-label="Daftar hasil wawancara User">
                <?php if ( empty( $filtered_result_candidates ) ) : ?>
                    <div class="daw-user-results__empty">Belum ada hasil wawancara yang sesuai dengan filter.</div>
                <?php else : ?>
                    <?php foreach ( $filtered_result_candidates as $candidate ) :
                        $final_status_class = 'Diterima' === $candidate['final_decision'] ? 'is-accepted' : ( 'Ditolak' === $candidate['final_decision'] ? 'is-rejected' : 'is-pending' );
                        $rating_class = 'Sangat Baik' === $candidate['recommendation'] ? 'is-excellent' : ( 'Baik' === $candidate['recommendation'] ? 'is-good' : ( 'Dipertimbangkan' === $candidate['recommendation'] ? 'is-consider' : 'is-not-recommended' ) );
                        ?>
                        <details class="daw-user-results__item" <?= (int) ( $_GET['application_id'] ?? 0 ) === $candidate['application_id'] ? 'open' : '' ?>>
                            <summary>
                                <span class="daw-user-results__avatar" aria-hidden="true"><?= esc_html( strtoupper( substr( $candidate['name'], 0, 2 ) ) ) ?></span>
                                <span class="daw-user-results__identity"><strong><?= esc_html( $candidate['name'] ) ?></strong><small><?= esc_html( $candidate['code'] . ' · ' . $candidate['position'] . ' · ' . $candidate['department'] . ' · ' . $candidate['dealer'] ) ?></small></span>
                                <span class="daw-user-results__badges"><span class="daw-user-results__badge <?= esc_attr( $rating_class ) ?>"><?= esc_html( $candidate['recommendation'] ?: 'Belum dinilai' ) ?></span><span class="daw-user-results__badge <?= esc_attr( $final_status_class ) ?>">Final: <?= esc_html( $candidate['final_decision'] ) ?></span></span>
                                <span class="daw-user-results__interviewer"><?= esc_html( $candidate['interviewer'] ?: 'Interviewer belum tercatat' ) ?><small><?= esc_html( $candidate['interview_date'] ) ?></small></span>
                                <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                            </summary>
                            <div class="daw-user-results__details">
                                <dl><div><dt>Departemen</dt><dd><?= esc_html( $candidate['department'] ) ?></dd></div><div><dt>Interviewer</dt><dd><?= esc_html( $candidate['interviewer'] ?: '—' ) ?></dd></div><div><dt>Tanggal Wawancara</dt><dd><?= esc_html( $candidate['interview_date'] ) ?></dd></div><div><dt>Hasil User</dt><dd><?= esc_html( $candidate['recommendation'] ?: 'Belum tersedia' ) ?><?= '' !== $candidate['score'] ? ' · ' . esc_html( $candidate['score'] . '/100' ) : '' ?></dd></div></dl>
                                <div class="daw-user-results__notes"><strong>Catatan Wawancara User</strong><p><?= '' !== $candidate['notes'] ? nl2br( esc_html( $candidate['notes'] ) ) : 'Tidak ada catatan wawancara.' ?></p></div>
                                <?php if ( 'Pending' === $candidate['final_decision'] ) : ?><a class="daw-user-results__final-link" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-final-decision', 'section' => 'decision', 'application_id' => $candidate['application_id'] ], admin_url( 'admin.php' ) ) ) ?>">Lanjut ke Final Decision</a><?php endif; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php else : ?>
            <?php if ( $database_error ) : ?><div class="notice notice-error"><p>Data wawancara User belum dapat dimuat dari Oracle. Silakan coba kembali.</p></div><?php endif; ?>
            <div class="daw-interview-screen__heading">
                <div><h1>Wawancara User</h1><p>Kelola wawancara user (hiring manager) untuk kandidat yang lolos wawancara HR.</p></div>
                <a class="daw-user-results__queue-link" href="<?= esc_url( $results_url ) ?>">Detail Hasil Wawancara User</a>
            </div>
            <?php if ( $unverified_interview_count > 0 ) : ?>
                <div class="notice notice-warning"><p><?= esc_html( number_format_i18n( $unverified_interview_count ) ) ?> lamaran berstatus Wawancara User tidak ditampilkan karena hasil HR belum lolos atau akun User Department belum ditetapkan. Tinjau lamaran tersebut sebelum melanjutkan; status Oracle tidak diubah otomatis.</p></div>
            <?php endif; ?>
            <section class="daw-interview-screen__stats" aria-label="Ringkasan wawancara user">
                <article class="is-waiting"><strong><?= esc_html( (string) $stats['waiting'] ) ?></strong><span>Menunggu Wawancara</span></article>
                <article class="is-scheduled"><strong><?= esc_html( (string) $stats['interviewed'] ) ?></strong><span>Sudah Diwawancarai</span></article>
                <article class="is-passed"><strong><?= esc_html( (string) $stats['passed'] ) ?></strong><span>Lolos</span></article>
                <article class="is-failed"><strong><?= esc_html( (string) $stats['failed'] ) ?></strong><span>Tidak Lolos</span></article>
            </section>
            <section class="daw-user-interview__panel daw-interview-screen__panel" aria-labelledby="user-interview-title">
                <div class="daw-interview-screen__panel-heading"><h2 id="user-interview-title">Daftar Kandidat</h2><label for="user-interview-status">Filter status</label><select id="user-interview-status"><option>Semua Status</option><option>Menunggu</option><option>Selesai</option></select></div>
                <div class="daw-user-interview__table-wrap">
                    <table class="daw-interview-screen__table daw-interview-screen__table--user">
                        <caption class="screen-reader-text">Daftar kandidat wawancara user</caption>
                        <thead>
                            <tr>
                                <th scope="col">Kandidat</th>
                                <th scope="col">Posisi</th>
                                <th scope="col">Dealer</th>
                                <th scope="col">Wilayah</th>
                                <th scope="col">Hasil Wawancara HR</th>
                                <th scope="col">Tgl Wawancara User</th>
                                <th scope="col">Status</th>
                                <th scope="col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $candidates ) ) : ?>
                                <?php foreach ( $candidates as $candidate ) : ?>
                                    <tr>
                                        <td>
                                            <span class="daw-user-interview__candidate"><?= esc_html( $candidate['name'] ) ?></span>
                                            <small class="daw-user-interview__code"><?= esc_html( $candidate['code'] ) ?></small>
                                        </td>
                                        <td><?= esc_html( $candidate['position'] ) ?></td>
                                        <td><?= esc_html( $candidate['dealer'] ) ?></td>
                                        <td><?= esc_html( $candidate['region'] ) ?></td>
                                        <td><span class="daw-interview-screen__result"><?= esc_html( $candidate['hr_result'] ) ?></span></td>
                                        <td><?= esc_html( $candidate['interview_date'] ) ?></td>
                                        <td><span class="daw-interview-screen__status <?= esc_attr( $candidate['status_class'] ) ?>"><?= esc_html( $candidate['status'] ) ?></span></td>
                                        <td><a class="daw-interview-screen__action" href="<?= esc_url( add_query_arg( [ 'view' => 'notes', 'candidate_id' => $candidate['id'], 'application_id' => $candidate['application_id'] ], $list_url ) ) ?>">Lihat Hasil</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr><td colspan="8" style="text-align:center; padding:32px 16px; color:#6b7280;">Belum ada kandidat pada tahap Wawancara User.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php if ( defined( 'RECRUITMENT_SANDBOX' ) ) : ?>
    <?php require recruitment_get_plugin_path( 'public/components/screen-explorer.php' ); ?>
<?php endif; ?>
