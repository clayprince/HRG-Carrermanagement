<?php
$database    = new Recruitment_Database();
$applications = $database->get_applications();
$applicants   = $database->get_applicants();
$vacancies    = $database->get_vacancies();
$applicants_by_id = [];
$vacancies_by_id  = [];

foreach ( $applicants as $applicant ) {
    $applicant = array_change_key_case( $applicant, CASE_LOWER );
    $applicants_by_id[ (string) $applicant['id'] ] = $applicant;
}
foreach ( $vacancies as $vacancy ) {
    $vacancy = array_change_key_case( $vacancy, CASE_LOWER );
    $vacancies_by_id[ (string) $vacancy['id'] ] = $vacancy;
}

$stages = [
    'administration' => 'Administrasi',
    'psychological_test' => 'Psikotes',
    'hr_interview' => 'Wawancara HR',
    'user_interview' => 'Wawancara User',
    'final' => 'Final',
];
$status_stages = [
    'submitted' => 'administration',
    'screening' => 'administration',
    'administrasi' => 'administration',
    'seleksi_administrasi' => 'administration',
    'administration' => 'administration',
    'administrative' => 'administration',
    'dalam_proses' => 'administration',
    'psychological_test' => 'psychological_test',
    'psych_test' => 'psychological_test',
    'psikotes' => 'psychological_test',
    'tes_psikologi' => 'psychological_test',
    'tes_psikotes' => 'psychological_test',
    'interview' => 'hr_interview',
    'interview_hr' => 'hr_interview',
    'wawancara_hr' => 'hr_interview',
    'hr_interview' => 'hr_interview',
    'interview_user' => 'user_interview',
    'wawancara_user' => 'user_interview',
    'user_interview' => 'user_interview',
    'accepted' => 'final',
    'diterima' => 'final',
    'lolos' => 'final',
    'rejected' => 'final',
    'ditolak' => 'final',
    'tidak_lolos' => 'final',
    'tidak_diterima' => 'final',
    'final' => 'final',
    'final_decision' => 'final',
    'keputusan_akhir' => 'final',
];
$normalize_stage = static function ( $value ) use ( $status_stages ): string {
    $key = strtolower( trim( (string) $value ) );
    $key = str_replace( [ ' ', '-', '/', '\\' ], '_', $key );
    $key = preg_replace( '/_+/', '_', $key );
    return $status_stages[ $key ] ?? 'other';
};
$final_statuses = [ 'accepted', 'diterima', 'lolos', 'rejected', 'ditolak', 'tidak_lolos', 'tidak_diterima', 'tolak' ];
$tracked_statuses = [ 'submitted', 'screening', 'accepted', 'rejected' ];
$status_counts = array_fill_keys( $tracked_statuses, 0 );
$stage_counts = array_fill_keys( array_keys( $stages ), 0 );
$candidates   = [];
$dealer_names = [];
$vacancy_names = [];
$active_count = 0;

foreach ( $applications as $application ) {
    $application = array_change_key_case( $application, CASE_LOWER );
    $applicant_id = (string) ( $application['applicant_id'] ?? '' );
    $vacancy_id   = (string) ( $application['vacancy_id'] ?? '' );
    $applicant    = $applicants_by_id[ $applicant_id ] ?? [];
    $vacancy      = $vacancies_by_id[ $vacancy_id ] ?? [];
    $status       = strtolower( str_replace( [ ' ', '-' ], '_', (string) ( $application['status'] ?? 'submitted' ) ) );
    $decision_status = strtolower( str_replace( [ ' ', '-' ], '_', (string) ( $application['final_status'] ?? '' ) ) );
    if ( '' === $decision_status ) {
        $decision_status = $status;
    }
    $is_final_decision = in_array( $decision_status, $final_statuses, true );
    $stage_source = trim( (string) ( $application['stage'] ?? '' ) );
    $history_stage = $normalize_stage( '' !== $stage_source ? $stage_source : $status );
    $stage        = $is_final_decision
        ? 'final'
        : $history_stage;
    $name         = (string) ( $applicant['name'] ?? 'Pelamar #' . ( $applicant_id ?: $application['id'] ) );
    $vacancy_name = (string) ( $vacancy['title'] ?? $application['title'] ?? ( $vacancy_id ? 'Lowongan #' . $vacancy_id : 'Lowongan tidak tersedia' ) );
    $dealer       = (string) ( $vacancy['dealer'] ?? $application['dealer'] ?? '' );
    $date_value   = (string) ( $application['created_at'] ?? $applicant['created_at'] ?? '' );
    $timestamp    = '' !== $date_value ? strtotime( $date_value ) : false;
    $date_label   = false !== $timestamp ? date_i18n( 'j F Y', $timestamp ) : 'Tanggal tidak tersedia';
    $application_year = false !== $timestamp ? date_i18n( 'Y', $timestamp ) : current_time( 'Y' );
    $name_parts   = preg_split( '/\s+/', trim( $name ) ) ?: [];
    $initials     = strtoupper( substr( $name_parts[0] ?? 'P', 0, 1 ) . substr( $name_parts[1] ?? '', 0, 1 ) );
    $stage_label  = $stages[ $stage ] ?? ucwords( str_replace( '_', ' ', $status ) );
    $state_label  = [ 'submitted' => 'Menunggu', 'accepted' => 'Diterima', 'rejected' => 'Tidak Lolos' ][ $status ] ?? 'Berlangsung';

    if ( isset( $status_counts[ $status ] ) ) {
        $status_counts[ $status ]++;
    }
    if ( ! $is_final_decision ) {
        $active_count++;
        if ( isset( $stage_counts[ $stage ] ) ) {
            $stage_counts[ $stage ]++;
        }
    }
    if ( '' !== $dealer ) {
        $dealer_names[ $dealer ] = $dealer;
    }
    $vacancy_names[ $vacancy_name ] = $vacancy_name;

    $candidates[] = [
        'name' => $name,
        'initials' => $initials,
        'token' => 'DAW-' . $application_year . '-' . str_pad( $applicant_id, 6, '0', STR_PAD_LEFT ),
        'application_id' => absint( $application['id'] ?? 0 ),
        'applicant_id' => absint( $applicant_id ),
        'date' => $date_label,
        'vacancy' => $vacancy_name,
        'dealer' => $dealer,
        'location' => (string) ( $vacancy['location'] ?? '' ),
        'stage' => $stage,
        'admin_review_status' => $is_final_decision
            ? ( in_array( $decision_status, [ 'rejected', 'ditolak', 'tidak_lolos', 'tidak_diterima', 'tolak' ], true ) && 'administration' === $history_stage ? 'rejected' : 'completed' )
            : ( 'administration' === $stage ? 'review' : 'passed' ),
        'stage_status' => [
            'administration' => 'ADMINISTRASI',
            'psychological_test' => 'PSIKOTES',
            'hr_interview' => 'INTERVIEW_HR',
            'user_interview' => 'INTERVIEW_USER',
            'final' => 'FINAL',
        ][ $normalize_stage( $status ) ] ?? 'ADMINISTRASI',
        'stage_label' => $stage_label,
        'state_label' => $state_label,
        'active' => ! $is_final_decision,
        'search' => strtolower( $name . ' ' . $vacancy_name . ' ' . $dealer . ' ' . (string) ( $applicant['email'] ?? '' ) ),
    ];
}
$administration_waiting = array_values(
    array_filter(
        $candidates,
        static fn ( array $candidate ): bool => $candidate['active'] && 'administration' === $candidate['stage']
    )
);
$administration_passed_count = count(
    array_filter(
        $candidates,
        static fn ( array $candidate ): bool => $candidate['active'] && 'administration' !== $candidate['stage'] && 'other' !== $candidate['stage']
    )
);
$administration_rejected_count = count(
    array_filter(
        $candidates,
        static fn ( array $candidate ): bool => 'rejected' === $candidate['admin_review_status']
    )
);
$initial_stage = sanitize_key( $_GET['stage'] ?? 'all' );
if ( ! in_array( $initial_stage, array_merge( [ 'all' ], array_keys( $stages ) ), true ) ) {
    $initial_stage = 'all';
}
$initial_visible_count = count(
    array_filter(
        $candidates,
        static fn ( array $candidate ): bool => $candidate['active']
            && ( 'all' === $initial_stage || $initial_stage === $candidate['stage'] )
    )
);

$database_ready = $database->is_configured() && class_exists( 'PDO' ) && in_array( 'oci', PDO::getAvailableDrivers(), true );
$application_count = count( $applications );
$final_count       = $stage_counts['final'];
$recent_candidates = array_slice( $candidates, 0, 5 );
$is_recruitment_list = isset( $_GET['stage'] );
$list_stage_label = 'all' === $initial_stage ? 'Kandidat Aktif' : ( $stages[ $initial_stage ] ?? 'Kandidat Aktif' );
$is_admin_selection = $is_recruitment_list && 'administration' === $initial_stage;
$is_psychological_test = $is_recruitment_list && 'psychological_test' === $initial_stage;
$psychotest_results = [];
$psychotest_results_error = false;
$offline_attendances = [];
$offline_attendance_error = false;
$offline_candidate_ids = [];
$psychotest_completed_count = 0;
$psychotest_active_count = 0;
$psychotest_candidate_ids = [];
if ( $is_psychological_test ) {
    $psychotest_results = $database->get_psychotest_results();
    $psychotest_results_error = $database->has_error();
    $offline_attendances = $database->get_confirmed_psychotest_attendances();
    $offline_attendance_error = $database->has_error();
    $offline_candidate_ids = array_unique(
        array_filter(
            array_map( 'absint', array_column( $offline_attendances, 'applicant_id' ) )
        )
    );
    foreach ( $psychotest_results as $psychotest_result ) {
        $psychotest_candidate_ids[] = absint( $psychotest_result['candidate_id'] ?? 0 );
        if ( 'complete' === ( $psychotest_result['status'] ?? '' ) ) {
            $psychotest_completed_count++;
        } else {
            $psychotest_active_count++;
        }
    }
    $psychotest_candidate_ids = array_unique( array_filter( $psychotest_candidate_ids ) );
}
$pipeline_rows = [
    [ 'label' => 'Total Lamaran', 'count' => $application_count, 'color' => 'total' ],
    [ 'label' => 'Administrasi', 'count' => $stage_counts['administration'], 'color' => 'administration' ],
    [ 'label' => 'Psikotes', 'count' => $stage_counts['psychological_test'], 'color' => 'psychological_test' ],
    [ 'label' => 'Wawancara HR', 'count' => $stage_counts['hr_interview'], 'color' => 'hr_interview' ],
    [ 'label' => 'Wawancara User', 'count' => $stage_counts['user_interview'], 'color' => 'user_interview' ],
    [ 'label' => 'Keputusan Akhir', 'count' => $final_count, 'color' => 'final' ],
];
?>
<div class="wrap recruitment-admin recruitment-dashboard" data-recruitment-dashboard>
    <?php
    $admin_user = wp_get_current_user();
    $hr_identity = recruitment_get_current_hr_identity();
    ?>
    <aside class="recruitment-dashboard__sidebar" aria-label="Navigasi dashboard">
        <a class="recruitment-dashboard__brand" href="<?= esc_url( recruitment_get_admin_url( 'dashboard' ) ) ?>" aria-label="DAW Admin, Dashboard">
            <span class="recruitment-dashboard__brand-mark" aria-hidden="true">D</span>
            <span class="recruitment-dashboard__brand-name">DAW Admin</span>
        </a>
        <div class="recruitment-dashboard__sidebar-user daw-sidebar-user-card" style="border-bottom:1px solid rgba(255,255,255,0.08);padding:10px 12px 12px;">
            <a href="<?= esc_url( admin_url( 'admin.php?page=recruitment-hr-profile' ) ) ?>" class="daw-sidebar-user-link" title="Buka Profil Admin HR" style="display:flex;align-items:center;gap:10px;text-decoration:none;padding:6px 8px;border-radius:8px;transition:background 0.15s ease;">
                <span class="recruitment-dashboard__sidebar-avatar" style="width:32px;height:32px;border-radius:50%;background:#c8102e;color:#fff;font-size:11px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;"><?= esc_html( $hr_identity['avatar'] ) ?></span>
                <div class="recruitment-dashboard__sidebar-user-info" style="min-width:0;flex:1;overflow:hidden;">
                    <strong class="recruitment-dashboard__sidebar-user-name" style="display:block;font-size:12px;font-weight:700;color:#f3f4f6;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= esc_html( $hr_identity['name'] ) ?></strong>
                    <span class="recruitment-dashboard__sidebar-user-email" style="display:block;font-size:11px;color:#9ca3af;line-height:1.3;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= esc_html( $hr_identity['email'] ?: $hr_identity['role'] ) ?></span>
                </div>
            </a>
        </div>
        <nav class="recruitment-dashboard__navigation">
            <a class="<?= ! $is_recruitment_list ? 'is-active' : '' ?>" href="<?= esc_url( recruitment_get_admin_url( 'dashboard' ) ) ?>" <?= ! $is_recruitment_list ? 'aria-current="page"' : '' ?> title="Dashboard"><span class="dashicons dashicons-dashboard" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Dashboard</span></a>
            <a class="<?= $is_recruitment_list && 'all' === $initial_stage ? 'is-active' : '' ?>" href="<?= esc_url( add_query_arg( 'stage', 'all', recruitment_get_admin_url( 'dashboard' ) ) ) ?>" data-dashboard-stage="all" title="Semua Kandidat Aktif"><span class="dashicons dashicons-groups" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Semua Kandidat Aktif</span></a>
            <a class="<?= $is_recruitment_list && 'administration' === $initial_stage ? 'is-active' : '' ?>" href="<?= esc_url( add_query_arg( 'stage', 'administration', recruitment_get_admin_url( 'dashboard' ) ) ) ?>" data-dashboard-stage="administration" title="Seleksi Administrasi"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Seleksi Administrasi</span></a>
            <a href="<?= esc_url( recruitment_get_admin_url( 'applicants' ) ) ?>" title="Database Pelamar"><span class="dashicons dashicons-database" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Database Pelamar</span></a>
            <div class="recruitment-dashboard__nav-group">
                <a href="<?= esc_url( recruitment_get_admin_url( 'vacancies' ) ) ?>" title="Lowongan"><span class="dashicons dashicons-portfolio" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Lowongan</span></a>
                <div class="recruitment-dashboard__nav-group-items">
                    <a href="<?= esc_url( recruitment_get_admin_url( 'vacancies' ) ) ?>"><span class="recruitment-dashboard__nav-label">Daftar Lowongan</span></a>
                    <a href="<?= esc_url( recruitment_get_admin_url( 'applications' ) ) ?>"><span class="recruitment-dashboard__nav-label">Pengajuan Lowongan</span></a>
                </div>
            </div>
            <a href="#candidate-list-title" data-dashboard-stage="psychological_test" title="Psikotes"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Psikotes</span></a>
            <a href="<?= esc_url( recruitment_get_admin_url( 'interview-schedule' ) ) ?>" title="Jadwal Wawancara HR"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Jadwal Wawancara HR</span></a>
            <a href="<?= esc_url( recruitment_get_admin_url( 'interview-schedule-user' ) ) ?>" title="Jadwal Wawancara User"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Jadwal Wawancara User</span></a>
            <a href="<?= esc_url( recruitment_get_admin_url( 'settings' ) ) ?>" title="Email Recruitment"><span class="dashicons dashicons-email" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Email Recruitment</span></a>
            <a href="<?= esc_url( admin_url( 'admin.php?page=recruitment-user-management' ) ) ?>" title="Pengelolaan Akun User"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Pengelolaan Akun User</span></a>
        </nav>
        <div class="recruitment-dashboard__sidebar-footer">
            <button type="button" data-sidebar-collapse aria-expanded="true" title="Ciutkan navigasi"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Ciutkan</span></button>
        </div>
    </aside>
    <main class="recruitment-dashboard__content">
        <header class="recruitment-dashboard__topbar">
            <p class="recruitment-dashboard__breadcrumb">DAW Admin <span>/</span> <?= $is_recruitment_list ? 'Recruitment — ' . esc_html( $list_stage_label ) : 'Dashboard' ?></p>
            <div class="recruitment-dashboard__profile">
                <span class="recruitment-dashboard__profile-avatar" aria-hidden="true"><?= esc_html( $hr_identity['avatar'] ) ?></span>
                <span class="recruitment-dashboard__profile-copy"><strong><?= esc_html( $hr_identity['name'] ) ?></strong><small><?= esc_html( $hr_identity['email'] ) ?></small></span>
                <?= recruitment_logout_form( 'Keluar', home_url( '/' ), 'recruitment-dashboard__logout' ) ?>
            </div>
        </header>

    <?php if ( ! $is_recruitment_list ) : ?>
    <section class="recruitment-dashboard__quick-actions" aria-label="Akses cepat">
        <a class="recruitment-dashboard__quick-card recruitment-dashboard__quick-card--orange" href="<?= esc_url( recruitment_get_admin_url( 'applications' ) ) ?>">
            <span class="recruitment-dashboard__icon dashicons dashicons-clipboard" aria-hidden="true"></span>
            <span class="recruitment-dashboard__quick-title">Pelamar Baru <b><?= esc_html( (string) $status_counts['submitted'] ) ?></b></span>
            <span class="recruitment-dashboard__quick-description">Menunggu seleksi administrasi</span>
        </a>
        <a class="recruitment-dashboard__quick-card recruitment-dashboard__quick-card--blue" href="<?= esc_url( recruitment_get_admin_url( 'applications' ) ) ?>">
            <span class="recruitment-dashboard__icon dashicons dashicons-welcome-learn-more" aria-hidden="true"></span>
            <span class="recruitment-dashboard__quick-title">Psikotes <b><?= esc_html( (string) $stage_counts['psychological_test'] ) ?></b></span>
            <span class="recruitment-dashboard__quick-description">Kandidat pada tahap psikotes</span>
        </a>
        <a class="recruitment-dashboard__quick-card recruitment-dashboard__quick-card--purple" href="<?= esc_url( recruitment_get_admin_url( 'applicants' ) ) ?>">
            <span class="recruitment-dashboard__icon dashicons dashicons-database" aria-hidden="true"></span>
            <span class="recruitment-dashboard__quick-title">Database Pelamar</span>
            <span class="recruitment-dashboard__quick-description">Arsip dan riwayat lamaran</span>
        </a>
        <a class="recruitment-dashboard__quick-card recruitment-dashboard__quick-card--red" href="<?= esc_url( add_query_arg( 'view', 'create', recruitment_get_admin_url( 'vacancies' ) ) ) ?>">
            <span class="recruitment-dashboard__icon dashicons dashicons-plus-alt2" aria-hidden="true"></span>
            <span class="recruitment-dashboard__quick-title">Buat Lowongan</span>
            <span class="recruitment-dashboard__quick-description">Tambah posisi baru</span>
        </a>
    </section>

    <section class="recruitment-dashboard__stats" aria-label="Statistik rekrutmen">
        <?php
        $stat_cards = [
            [ 'label' => 'Total Pelamar', 'value' => count( $applicants ), 'note' => number_format_i18n( $application_count ) . ' lamaran terdaftar', 'icon' => 'dashicons-groups', 'color' => 'slate' ],
            [ 'label' => 'Pelamar Baru', 'value' => $status_counts['submitted'], 'note' => 'Menunggu review', 'icon' => 'dashicons-admin-users', 'color' => 'blue' ],
            [ 'label' => 'Seleksi Administrasi', 'value' => $stage_counts['administration'], 'note' => 'Kandidat di tahap ini', 'icon' => 'dashicons-clipboard', 'color' => 'orange' ],
            [ 'label' => 'Psikotes', 'value' => $stage_counts['psychological_test'], 'note' => 'Kandidat di tahap ini', 'icon' => 'dashicons-welcome-learn-more', 'color' => 'red' ],
            [ 'label' => 'Wawancara HR', 'value' => $stage_counts['hr_interview'], 'note' => 'Kandidat di tahap ini', 'icon' => 'dashicons-businessperson', 'color' => 'green' ],
            [ 'label' => 'Wawancara User', 'value' => $stage_counts['user_interview'], 'note' => 'Kandidat di tahap ini', 'icon' => 'dashicons-admin-home', 'color' => 'purple' ],
            [ 'label' => 'Lolos', 'value' => $status_counts['accepted'], 'note' => 'Total diterima', 'icon' => 'dashicons-yes-alt', 'color' => 'green' ],
            [ 'label' => 'Tidak Lolos', 'value' => $status_counts['rejected'], 'note' => 'Total ditolak', 'icon' => 'dashicons-dismiss', 'color' => 'slate' ],
        ];
        foreach ( $stat_cards as $stat_card ) :
            ?>
            <article class="recruitment-dashboard__stat-card recruitment-dashboard__stat-card--<?= esc_attr( $stat_card['color'] ) ?>">
                <span class="recruitment-dashboard__icon dashicons <?= esc_attr( $stat_card['icon'] ) ?>" aria-hidden="true"></span>
                <strong class="recruitment-dashboard__stat-value"><?= esc_html( number_format_i18n( (int) $stat_card['value'] ) ) ?></strong>
                <span class="recruitment-dashboard__stat-label"><?= esc_html( $stat_card['label'] ) ?></span>
                <small><?= esc_html( $stat_card['note'] ) ?></small>
            </article>
        <?php endforeach; ?>
    </section>

    <div class="recruitment-dashboard__workspace">
        <section class="recruitment-dashboard__panel recruitment-dashboard__recruitment-pipeline" aria-labelledby="recruitment-pipeline-title">
            <h2 id="recruitment-pipeline-title">Pipeline Rekrutmen</h2>
            <div class="recruitment-dashboard__pipeline-rows">
                <?php foreach ( $pipeline_rows as $pipeline_row ) :
                    $percentage = $application_count > 0 ? (int) round( $pipeline_row['count'] / $application_count * 100 ) : 0;
                    ?>
                    <div class="recruitment-dashboard__pipeline-row recruitment-dashboard__pipeline-row--<?= esc_attr( $pipeline_row['color'] ) ?>">
                        <div class="recruitment-dashboard__pipeline-label"><span><?= esc_html( $pipeline_row['label'] ) ?></span><strong><?= esc_html( number_format_i18n( $pipeline_row['count'] ) ) ?></strong><span><?= esc_html( (string) $percentage ) ?>%</span></div>
                        <div class="recruitment-dashboard__progress" role="progressbar" aria-label="<?= esc_attr( $pipeline_row['label'] ) ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= esc_attr( (string) $percentage ) ?>"><span style="width: <?= esc_attr( (string) $percentage ) ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="recruitment-dashboard__panel recruitment-dashboard__recent" aria-labelledby="recent-applications-title">
            <div class="recruitment-dashboard__panel-heading"><h2 id="recent-applications-title">Lamaran Terbaru</h2><a href="<?= esc_url( recruitment_get_admin_url( 'applications' ) ) ?>">Lihat Semua <span aria-hidden="true">→</span></a></div>
            <div class="recruitment-dashboard__table-scroll">
                <table class="recruitment-dashboard__applications-table">
                    <thead><tr><th>Pelamar</th><th>Posisi</th><th>Tahap</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach ( $recent_candidates as $candidate ) : ?>
                            <tr>
                                <td><strong><?= esc_html( $candidate['name'] ) ?></strong><small><?= esc_html( $candidate['token'] ) ?></small></td>
                                <td><?= esc_html( $candidate['vacancy'] ) ?><small><?= esc_html( $candidate['dealer'] ) ?></small></td>
                                <td><span class="recruitment-candidate__stage recruitment-candidate__stage--<?= esc_attr( $candidate['stage'] ) ?>"><?= esc_html( $candidate['stage_label'] ) ?></span></td>
                                <td><a href="<?= esc_url( recruitment_get_admin_url( 'applicants' ) ) ?>">Lihat</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ( ! $recent_candidates ) : ?>
                            <tr><td class="recruitment-dashboard__table-empty" colspan="4"><?= $database_ready ? 'Belum ada lamaran.' : 'Koneksi database belum siap.' ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <?php endif; ?>

    <?php if ( $is_admin_selection ) : ?>
        <section class="recruitment-admin-selection__stats" aria-label="Ringkasan seleksi administrasi">
            <article><strong><?= esc_html( number_format_i18n( count( $administration_waiting ) ) ) ?></strong><span>Menunggu Review</span></article>
            <article><strong><?= esc_html( number_format_i18n( $administration_passed_count ) ) ?></strong><span>Lolos Administrasi</span></article>
            <article><strong><?= esc_html( number_format_i18n( $administration_rejected_count ) ) ?></strong><span>Tidak Lolos</span></article>
            <article><strong>0</strong><span>Disarankan Sistem</span></article>
        </section>
    <?php endif; ?>

    <?php if ( $is_psychological_test ) : ?>
        <section class="recruitment-psychotest-stage" data-psychotest-stage>
            <div class="recruitment-psychotest-stage__stats" aria-label="Ringkasan psikotes">
                <article><span>Online</span><strong><?= esc_html( $psychotest_results_error ? '—' : number_format_i18n( count( $psychotest_candidate_ids ) ) ) ?></strong><small>peserta tes online</small></article>
                <article><span>Offline</span><strong><?= esc_html( $offline_attendance_error ? '—' : number_format_i18n( count( $offline_candidate_ids ) ) ) ?></strong><small>peserta hadir</small></article>
                <article><span>Tes Berlangsung</span><strong><?= esc_html( $psychotest_results_error ? '—' : number_format_i18n( $psychotest_active_count ) ) ?></strong><small>belum diselesaikan</small></article>
                <article><span>Hasil Tersedia</span><strong><?= esc_html( $psychotest_results_error ? '—' : number_format_i18n( $psychotest_completed_count ) ) ?></strong><small>tes selesai</small></article>
            </div>
            <div class="recruitment-psychotest-stage__filters" role="group" aria-label="Filter metode psikotes">
                <button type="button" class="is-active" data-psychotest-mode-filter="all" aria-pressed="true">Semua</button>
                <button type="button" data-psychotest-mode-filter="online" aria-pressed="false">Online</button>
                <button type="button" data-psychotest-mode-filter="offline" aria-pressed="false">Offline</button>
            </div>
            <section class="recruitment-psychotest-stage__panel" data-psychotest-mode-panel="online" aria-labelledby="psychotest-online-results-title">
                <header class="recruitment-psychotest-stage__panel-heading">
                    <div><span class="recruitment-psychotest-stage__mode recruitment-psychotest-stage__mode--online">ONLINE</span><div><h2 id="psychotest-online-results-title">Hasil Psikotes Online</h2><p>IQ · Pauli · Kepribadian · DISC · PAPI Kostick</p></div></div>
                    <a class="button" href="<?= esc_url( recruitment_get_admin_url( 'psychotest-settings' ) ) ?>">Konfigurasi Tes</a>
                </header>
                <?php if ( $psychotest_results_error ) : ?>
                    <p class="recruitment-psychotest-stage__empty">Hasil tes online belum dapat dimuat.</p>
                <?php else : ?>
                    <div class="recruitment-psychotest-stage__table-wrap">
                        <table class="recruitment-psychotest-stage__table">
                            <thead><tr><th>Pelamar</th><th>Tes</th><th>Nilai</th><th>Jawaban</th><th>Status</th><th>Mulai</th><th>Selesai</th><th>Aksi</th></tr></thead>
                            <tbody>
                                <?php foreach ( $psychotest_results as $result ) :
                                    $test_title = (string) ( $result['test_title'] ?? '' );
                                    $test_title_upper = strtoupper( $test_title );
                                    $has_numeric_score = false !== strpos( $test_title_upper, 'IQ' ) || false !== strpos( $test_title_upper, 'PAULI' );
                                    $result_status = 'complete' === ( $result['status'] ?? '' ) ? 'Selesai' : 'Berlangsung';
                                    ?>
                                    <tr>
                                        <td><strong><?= esc_html( (string) ( $result['candidate_name'] ?? 'Kandidat' ) ) ?></strong><small>ID <?= esc_html( (string) ( $result['candidate_id'] ?? '' ) ) ?><?= ! empty( $result['candidate_email'] ) ? ' · ' . esc_html( (string) $result['candidate_email'] ) : '' ?></small></td>
                                        <td><?= esc_html( $test_title ) ?></td>
                                        <td><?= $has_numeric_score ? esc_html( (string) ( $result['score'] ?? 0 ) ) : '—' ?></td>
                                        <td><?= esc_html( (string) ( $result['response_count'] ?? 0 ) ) ?> / <?= esc_html( (string) ( $result['question_count'] ?? 0 ) ) ?></td>
                                        <td><span class="recruitment-psychotest-stage__status <?= 'complete' === ( $result['status'] ?? '' ) ? 'is-complete' : 'is-progress' ?>"><?= esc_html( $result_status ) ?></span></td>
                                        <td><?= esc_html( (string) ( $result['started_at'] ?? '—' ) ) ?></td>
                                        <td><?= esc_html( (string) ( $result['completed_at'] ?? '—' ) ) ?></td>
                                        <td>
                                            <div class="recruitment-psychotest-stage__actions">
                                                <button type="button" class="button" disabled title="Tampilan UI saja">Detail</button>
                                                <button type="button" class="button" disabled title="Tampilan UI saja">PDF</button>
                                                <button type="button" class="button recruitment-psychotest-stage__action-accept" disabled title="Tampilan UI saja">ACC</button>
                                                <button type="button" class="button recruitment-psychotest-stage__action-reject" disabled title="Tampilan UI saja">Tolak</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ( ! $psychotest_results ) : ?>
                                    <tr><td colspan="8">Belum ada hasil psikotes online.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
            <section class="recruitment-psychotest-stage__panel" data-psychotest-mode-panel="offline" aria-labelledby="psychotest-offline-results-title">
                <header class="recruitment-psychotest-stage__panel-heading">
                    <div><span class="recruitment-psychotest-stage__mode recruitment-psychotest-stage__mode--offline">OFFLINE</span><div><h2 id="psychotest-offline-results-title">Peserta Psikotes Offline</h2><p>Kehadiran terkonfirmasi untuk pelaksanaan psikotes</p></div></div>
                    <span class="recruitment-psychotest-stage__pending-count"><?= esc_html( number_format_i18n( count( $offline_attendances ) ) ) ?> kehadiran</span>
                </header>
                <?php if ( $offline_attendance_error ) : ?>
                    <p class="recruitment-psychotest-stage__empty">Data kehadiran offline belum dapat dimuat.</p>
                <?php else : ?>
                    <div class="recruitment-psychotest-stage__table-wrap">
                        <table class="recruitment-psychotest-stage__table recruitment-psychotest-stage__table--offline">
                            <thead><tr><th>Kandidat</th><th>Posisi</th><th>Lokasi</th><th>Kehadiran</th><th>Waktu Konfirmasi</th><th>Hasil</th><th>Aksi</th></tr></thead>
                            <tbody>
                                <?php foreach ( $offline_attendances as $attendance ) : ?>
                                    <tr>
                                        <td><strong><?= esc_html( (string) ( $attendance['applicant_name'] ?? 'Kandidat' ) ) ?></strong><small>ID <?= esc_html( (string) ( $attendance['applicant_id'] ?? '' ) ) ?></small></td>
                                        <td><?= esc_html( (string) ( $attendance['title'] ?? 'Posisi tidak tersedia' ) ) ?></td>
                                        <td><?= esc_html( (string) ( $attendance['location'] ?? '—' ) ) ?></td>
                                        <td><span class="recruitment-psychotest-stage__status is-complete">Hadir</span></td>
                                        <td><?= esc_html( (string) ( $attendance['confirmed_at'] ?? '—' ) ) ?></td>
                                        <td>—</td>
                                        <td><div class="recruitment-psychotest-stage__actions"><button type="button" class="button" disabled title="Tampilan UI saja">Kehadiran</button><button type="button" class="button recruitment-psychotest-stage__action-accept" disabled title="Input hasil belum tersedia">Input Hasil</button></div></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ( ! $offline_attendances ) : ?>
                                    <tr><td colspan="7">Belum ada kehadiran psikotes offline yang terkonfirmasi.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    <?php endif; ?>

    <?php if ( ! $is_psychological_test || 0 < $stage_counts['psychological_test'] ) : ?>
    <section class="recruitment-dashboard__panel recruitment-dashboard__candidate-panel<?= $is_recruitment_list ? ' recruitment-dashboard__candidate-panel--active-list' : '' ?><?= $is_admin_selection ? ' recruitment-dashboard__candidate-panel--administration' : '' ?><?= $is_psychological_test ? ' recruitment-dashboard__candidate-panel--psychotest' : '' ?>" aria-labelledby="candidate-list-title" <?= $is_admin_selection ? 'data-admin-selection' : '' ?>>
        <div class="recruitment-dashboard__candidate-heading"><div><h2 id="candidate-list-title"><?= $is_admin_selection ? 'Pelamar — Menunggu Seleksi Administrasi' : ( $is_psychological_test ? 'Kandidat Tahap Psikotes' : ( $is_recruitment_list ? 'Rekrutmen Aktif' : 'Semua Kandidat' ) ) ?></h2><p><?= $is_admin_selection ? 'Tinjau kelengkapan profil dan putuskan kandidat untuk tahap berikutnya.' : ( $is_psychological_test ? 'Tinjau status kandidat dan lanjutkan keputusan seleksi psikotes.' : ( $is_recruitment_list ? 'Kelola kandidat di semua tahap seleksi yang sedang berjalan' : 'Pantau kandidat berdasarkan tahapan rekrutmen.' ) ) ?></p></div><span class="recruitment-dashboard__active-count"><span class="dashicons dashicons-groups" aria-hidden="true"></span><strong><?= esc_html( (string) ( $is_admin_selection ? count( $administration_waiting ) : ( $is_psychological_test ? $stage_counts['psychological_test'] : $active_count ) ) ) ?></strong><span><?= $is_admin_selection ? 'Menunggu' : ( $is_psychological_test ? 'Psikotes' : 'Aktif' ) ?></span></span></div>
        <?php if ( $is_admin_selection ) : ?>
            <section class="recruitment-admin-selection__recommendation" aria-labelledby="admin-recommendation-title">
                <span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
                <div><h3 id="admin-recommendation-title">Rekomendasi Otomatis Sistem</h3><p>Rekomendasi otomatis belum tersedia untuk data persyaratan saat ini. HR tetap perlu meninjau profil kandidat sebelum mengambil keputusan.</p></div>
            </section>
            <div class="recruitment-admin-selection__filters" role="group" aria-label="Filter rekomendasi administrasi">
                <span>Filter:</span>
                <button type="button" class="is-active" data-admin-recommendation-filter="all" aria-pressed="true">Semua</button>
                <button type="button" data-admin-recommendation-filter="recommended" aria-pressed="false">Disarankan Lolos (0)</button>
                <button type="button" data-admin-recommendation-filter="review" aria-pressed="false">Perlu Review (<?= esc_html( number_format_i18n( count( $administration_waiting ) ) ) ?>)</button>
                <button type="button" data-admin-recommendation-filter="not-recommended" aria-pressed="false">Tidak Disarankan (0)</button>
            </div>
            <div class="recruitment-admin-selection__list-heading">Pelamar — Menunggu Seleksi Administrasi</div>
            <div class="recruitment-admin-selection__candidate-list" data-admin-selection-list>
                <?php foreach ( $administration_waiting as $candidate ) : ?>
                    <article class="recruitment-admin-selection__candidate" data-admin-selection-candidate data-recommendation="review" data-search="<?= esc_attr( $candidate['search'] ) ?>" data-dealer="<?= esc_attr( $candidate['dealer'] ) ?>" data-vacancy="<?= esc_attr( $candidate['vacancy'] ) ?>">
                        <span class="recruitment-candidate__avatar" aria-hidden="true"><?= esc_html( $candidate['initials'] ) ?></span>
                        <div class="recruitment-candidate__identity">
                            <h3><?= esc_html( $candidate['name'] ) ?></h3>
                            <p><?= esc_html( $candidate['token'] ) ?> <span>·</span> Daftar <?= esc_html( $candidate['date'] ) ?></p>
                            <p class="recruitment-candidate__position"><strong><?= esc_html( $candidate['vacancy'] ) ?></strong><?php if ( '' !== $candidate['dealer'] || '' !== $candidate['location'] ) : ?> <span>·</span> <?= esc_html( implode( ' · ', array_filter( [ $candidate['dealer'], $candidate['location'] ] ) ) ) ?><?php endif; ?></p>
                            <span class="recruitment-admin-selection__review-badge">Perlu Review</span>
                        </div>
                        <div class="recruitment-admin-selection__actions">
                            <a class="button" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-applicants', 'candidate_id' => $candidate['applicant_id'], 'application_id' => $candidate['application_id'] ], admin_url( 'admin.php' ) ) ) ?>">Detail</a>
                            <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" class="recruitment-candidate__decision-form" onsubmit="return confirm('Loloskan kandidat ini ke tahap Psikotes?');">
                                <input type="hidden" name="action" value="daw_update_application_status">
                                <input type="hidden" name="application_id" value="<?= esc_attr( (string) $candidate['application_id'] ) ?>">
                                <input type="hidden" name="candidate_id" value="<?= esc_attr( (string) $candidate['applicant_id'] ) ?>">
                                <input type="hidden" name="current_stage" value="ADMINISTRASI">
                                <?php wp_nonce_field( 'daw_update_application_status_' . (int) $candidate['application_id'] ); ?>
                                <button type="submit" name="selection_decision" value="passed" class="button recruitment-candidate__accept">✓ ACC / Lolos</button>
                            </form>
                            <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" class="recruitment-candidate__decision-form" onsubmit="return confirm('Tandai kandidat ini tidak lolos seleksi administrasi?');">
                                <input type="hidden" name="action" value="daw_update_application_status">
                                <input type="hidden" name="application_id" value="<?= esc_attr( (string) $candidate['application_id'] ) ?>">
                                <input type="hidden" name="candidate_id" value="<?= esc_attr( (string) $candidate['applicant_id'] ) ?>">
                                <input type="hidden" name="current_stage" value="ADMINISTRASI">
                                <?php wp_nonce_field( 'daw_update_application_status_' . (int) $candidate['application_id'] ); ?>
                                <button type="submit" name="selection_decision" value="failed" class="button recruitment-candidate__reject">× Tidak Lolos</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
                <div class="recruitment-dashboard__empty" data-admin-selection-empty <?= $administration_waiting ? 'hidden' : '' ?>>Belum ada pelamar yang sesuai dengan filter ini.</div>
            </div>
        <?php else : ?>
        <p class="recruitment-dashboard__section-label">Filter Tahap</p>
        <div class="recruitment-dashboard__stage-filters" role="group" aria-label="Pilih tahap kandidat">
            <button class="<?= 'all' === $initial_stage ? 'is-active' : '' ?>" type="button" data-stage-filter="all" aria-pressed="<?= 'all' === $initial_stage ? 'true' : 'false' ?>">Semua <span><?= esc_html( (string) $active_count ) ?></span></button>
            <?php foreach ( $stages as $stage_key => $stage_name ) : ?>
                <button class="<?= $stage_key === $initial_stage ? 'is-active' : '' ?>" type="button" data-stage-filter="<?= esc_attr( $stage_key ) ?>" aria-pressed="<?= $stage_key === $initial_stage ? 'true' : 'false' ?>"><?= esc_html( $stage_name ) ?> <span><?= esc_html( (string) $stage_counts[ $stage_key ] ) ?></span></button>
            <?php endforeach; ?>
        </div>

        <div class="recruitment-dashboard__filters">
            <span class="recruitment-dashboard__filter-label"><span class="dashicons dashicons-filter" aria-hidden="true"></span> Filter</span>
            <label class="recruitment-dashboard__search"><span class="dashicons dashicons-search" aria-hidden="true"></span><span class="screen-reader-text">Cari kandidat</span><input type="search" placeholder="Cari nama, kode, atau posisi..." data-candidate-search></label>
            <label><span class="screen-reader-text">Filter dealer</span><select data-candidate-dealer><option value="">Semua Dealer</option><?php foreach ( $dealer_names as $dealer_name ) : ?><option value="<?= esc_attr( $dealer_name ) ?>"><?= esc_html( $dealer_name ) ?></option><?php endforeach; ?></select></label>
            <label><span class="screen-reader-text">Filter posisi</span><select data-candidate-vacancy><option value="">Semua Posisi</option><?php foreach ( $vacancy_names as $vacancy_name ) : ?><option value="<?= esc_attr( $vacancy_name ) ?>"><?= esc_html( $vacancy_name ) ?></option><?php endforeach; ?></select></label>
            <button class="button" type="button" data-candidate-reset>Reset</button>
        </div>

        <p class="recruitment-dashboard__result-count" aria-live="polite">Menampilkan <span data-candidate-count><?= esc_html( (string) $initial_visible_count ) ?></span> kandidat</p>
        <div class="recruitment-dashboard__candidate-list" data-candidate-list>
            <?php foreach ( $candidates as $candidate ) : ?>
                <article class="recruitment-candidate" data-candidate-card data-stage="<?= esc_attr( $candidate['stage'] ) ?>" data-active="<?= $candidate['active'] ? 'true' : 'false' ?>" data-dealer="<?= esc_attr( $candidate['dealer'] ) ?>" data-vacancy="<?= esc_attr( $candidate['vacancy'] ) ?>" data-search="<?= esc_attr( $candidate['search'] ) ?>" <?= ! $candidate['active'] || ( 'all' !== $initial_stage && $initial_stage !== $candidate['stage'] ) ? 'hidden' : '' ?>>
                    <span class="recruitment-candidate__avatar" aria-hidden="true"><?= esc_html( $candidate['initials'] ) ?></span>
                    <div class="recruitment-candidate__identity">
                        <h2><?= esc_html( $candidate['name'] ) ?></h2>
                        <p><?= esc_html( $candidate['token'] ) ?> <span>·</span> Daftar <?= esc_html( $candidate['date'] ) ?></p>
                        <p class="recruitment-candidate__position"><strong><?= esc_html( $candidate['vacancy'] ) ?></strong><?php if ( '' !== $candidate['dealer'] || '' !== $candidate['location'] ) : ?> <span>·</span> <?= esc_html( implode( ' · ', array_filter( [ $candidate['dealer'], $candidate['location'] ] ) ) ) ?><?php endif; ?></p>
                    </div>
                    <div class="recruitment-candidate__actions">
                        <span class="recruitment-candidate__stage recruitment-candidate__stage--<?= esc_attr( $candidate['stage'] ) ?>"><?= esc_html( $candidate['stage_label'] ) ?></span>
                        <span class="recruitment-candidate__state"><?= esc_html( $candidate['state_label'] ) ?></span>
                        <?php if ( $candidate['active'] && 'final' !== $candidate['stage'] ) : ?>
                            <?php if ( in_array( $candidate['stage'], [ 'administration', 'psychological_test' ], true ) ) : ?>
                                <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" class="recruitment-candidate__decision-form" onsubmit="return confirm('Simpan keputusan seleksi untuk kandidat ini?');">
                                    <input type="hidden" name="action" value="daw_update_application_status">
                                    <input type="hidden" name="application_id" value="<?= esc_attr( (string) $candidate['application_id'] ) ?>">
                                    <input type="hidden" name="candidate_id" value="<?= esc_attr( (string) $candidate['applicant_id'] ) ?>">
                                    <input type="hidden" name="current_stage" value="<?= esc_attr( $candidate['stage_status'] ) ?>">
                                    <?php wp_nonce_field( 'daw_update_application_status_' . (int) $candidate['application_id'] ); ?>
                                    <button type="submit" name="selection_decision" value="passed" class="button recruitment-candidate__accept">✓ ACC</button>
                                    <button type="submit" name="selection_decision" value="failed" class="button recruitment-candidate__reject">× Tidak Lolos</button>
                                </form>
                            <?php else : ?>
                                <?php
                                $interview_page_args = 'hr_interview' === $candidate['stage']
                                    ? [ 'page' => 'recruitment-hr-interview', 'application_id' => $candidate['application_id'] ]
                                    : [ 'page' => 'recruitment-user-interview', 'view' => 'results', 'application_id' => $candidate['application_id'] ];
                                $interview_action_label = 'hr_interview' === $candidate['stage'] ? 'Proses Wawancara HR' : 'Lihat Hasil User';
                                ?>
                                <a class="button recruitment-candidate__accept" href="<?= esc_url( add_query_arg( $interview_page_args, admin_url( 'admin.php' ) ) ) ?>"><?= esc_html( $interview_action_label ) ?></a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ( 'final' === $candidate['stage'] ) : ?>
                            <a class="recruitment-candidate__detail" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-final-decision', 'section' => 'decision', 'application_id' => $candidate['application_id'] ], admin_url( 'admin.php' ) ) ) ?>">Detail <span aria-hidden="true">→</span></a>
                        <?php else : ?>
                            <a class="recruitment-candidate__detail" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-applicants', 'candidate_id' => $candidate['applicant_id'], 'application_id' => $candidate['application_id'], 'from' => 'active' ], admin_url( 'admin.php' ) ) ) ?>">Detail <span aria-hidden="true">→</span></a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            <div class="recruitment-dashboard__empty" data-candidate-empty <?= $initial_visible_count > 0 ? 'hidden' : '' ?>>
                <?php if ( ! $database_ready ) : ?>
                    Koneksi Oracle belum siap. Periksa konfigurasi DSN, user, password, dan driver PDO OCI.
                <?php else : ?>
                    Belum ada kandidat yang sesuai dengan filter ini.
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    </main>
</div>
