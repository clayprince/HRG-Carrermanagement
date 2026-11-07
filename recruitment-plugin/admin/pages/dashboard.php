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
    'psychological_test' => 'psychological_test',
    'psych_test' => 'psychological_test',
    'psikotes' => 'psychological_test',
    'interview' => 'hr_interview',
    'interview_hr' => 'hr_interview',
    'wawancara_hr' => 'hr_interview',
    'interview_user' => 'user_interview',
    'wawancara_user' => 'user_interview',
    'accepted' => 'final',
    'rejected' => 'final',
    'final' => 'final',
];
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
    $stage        = $status_stages[ $status ] ?? 'other';
    $name         = (string) ( $applicant['name'] ?? 'Pelamar #' . ( $applicant_id ?: $application['id'] ) );
    $vacancy_name = (string) ( $vacancy['title'] ?? ( $vacancy_id ? 'Lowongan #' . $vacancy_id : 'Lowongan tidak tersedia' ) );
    $dealer       = (string) ( $vacancy['dealer'] ?? '' );
    $date_value   = (string) ( $application['created_at'] ?? $applicant['created_at'] ?? '' );
    $timestamp    = '' !== $date_value ? strtotime( $date_value ) : false;
    $date_label   = false !== $timestamp ? date_i18n( 'j F Y', $timestamp ) : 'Tanggal tidak tersedia';
    $name_parts   = preg_split( '/\s+/', trim( $name ) ) ?: [];
    $initials     = strtoupper( substr( $name_parts[0] ?? 'P', 0, 1 ) . substr( $name_parts[1] ?? '', 0, 1 ) );
    $stage_label  = $stages[ $stage ] ?? ucwords( str_replace( '_', ' ', $status ) );
    $state_label  = [ 'submitted' => 'Menunggu', 'accepted' => 'Diterima', 'rejected' => 'Tidak Lolos' ][ $status ] ?? 'Berlangsung';

    if ( isset( $stage_counts[ $stage ] ) ) {
        $stage_counts[ $stage ]++;
    }
    if ( isset( $status_counts[ $status ] ) ) {
        $status_counts[ $status ]++;
    }
    if ( ! in_array( $status, [ 'accepted', 'rejected' ], true ) ) {
        $active_count++;
    }
    if ( '' !== $dealer ) {
        $dealer_names[ $dealer ] = $dealer;
    }
    $vacancy_names[ $vacancy_name ] = $vacancy_name;

    $candidates[] = [
        'name' => $name,
        'initials' => $initials,
        'token' => (string) ( $application['application_token'] ?? 'Lamaran #' . ( $application['id'] ?? '' ) ),
        'date' => $date_label,
        'vacancy' => $vacancy_name,
        'dealer' => $dealer,
        'location' => (string) ( $vacancy['location'] ?? '' ),
        'stage' => $stage,
        'stage_label' => $stage_label,
        'state_label' => $state_label,
        'search' => strtolower( $name . ' ' . $vacancy_name . ' ' . $dealer . ' ' . (string) ( $applicant['email'] ?? '' ) ),
    ];
}

$database_ready = $database->is_configured() && class_exists( 'PDO' ) && in_array( 'oci', PDO::getAvailableDrivers(), true );
$application_count = count( $applications );
$final_count       = $stage_counts['final'];
$recent_candidates = array_slice( $candidates, 0, 5 );
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
    <?php $admin_user = wp_get_current_user(); ?>
    <aside class="recruitment-dashboard__sidebar" aria-label="Navigasi dashboard">
        <a class="recruitment-dashboard__brand" href="<?= esc_url( recruitment_get_admin_url( 'dashboard' ) ) ?>" aria-label="DAW Admin, Dashboard">
            <span class="recruitment-dashboard__brand-mark" aria-hidden="true">D</span>
            <span class="recruitment-dashboard__brand-name">DAW Admin</span>
        </a>
        <nav class="recruitment-dashboard__navigation">
            <a class="is-active" href="<?= esc_url( recruitment_get_admin_url( 'dashboard' ) ) ?>" aria-current="page" title="Dashboard"><span class="dashicons dashicons-dashboard" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Dashboard</span></a>
            <a href="#candidate-list-title" data-dashboard-stage="all" title="Recruitment"><span class="dashicons dashicons-groups" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Recruitment</span></a>
            <a href="<?= esc_url( recruitment_get_admin_url( 'applicants' ) ) ?>" title="Database Pelamar"><span class="dashicons dashicons-database" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Database Pelamar</span></a>
            <div class="recruitment-dashboard__nav-group">
                <a href="<?= esc_url( recruitment_get_admin_url( 'vacancies' ) ) ?>" title="Lowongan"><span class="dashicons dashicons-portfolio" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Lowongan</span></a>
                <div class="recruitment-dashboard__nav-group-items">
                    <a href="<?= esc_url( recruitment_get_admin_url( 'vacancies' ) ) ?>"><span class="recruitment-dashboard__nav-label">Daftar Lowongan</span></a>
                    <a href="<?= esc_url( recruitment_get_admin_url( 'applications' ) ) ?>"><span class="recruitment-dashboard__nav-label">Pengajuan Lowongan</span></a>
                </div>
            </div>
            <a href="<?= esc_url( admin_url( 'admin.php?page=recruitment-form-builder' ) ) ?>" title="Form Lamaran"><span class="dashicons dashicons-forms" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Form Lamaran</span></a>
            <a href="#candidate-list-title" data-dashboard-stage="psychological_test" title="Psikotes"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Psikotes</span></a>
            <a href="<?= esc_url( admin_url( 'admin.php?page=recruitment-interview-schedule' ) ) ?>" title="Jadwal Wawancara"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Jadwal Wawancara</span></a>
            <a href="<?= esc_url( recruitment_get_admin_url( 'settings' ) ) ?>" title="Email Recruitment"><span class="dashicons dashicons-email" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Email Recruitment</span></a>
            <a href="<?= esc_url( admin_url( 'admin.php?page=recruitment-user-management' ) ) ?>" title="Pengelolaan Akun User"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Pengelolaan Akun User</span></a>
        </nav>
        <div class="recruitment-dashboard__sidebar-footer">
            <button type="button" data-sidebar-collapse aria-expanded="true" title="Ciutkan navigasi"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Ciutkan</span></button>
        </div>
    </aside>
    <main class="recruitment-dashboard__content">
        <header class="recruitment-dashboard__topbar">
            <p class="recruitment-dashboard__breadcrumb">DAW Admin <span>/</span> Dashboard</p>
            <div class="recruitment-dashboard__profile">
                <span class="recruitment-dashboard__profile-avatar" aria-hidden="true">HR</span>
                <span class="recruitment-dashboard__profile-copy"><strong><?= esc_html( $admin_user->display_name ) ?></strong><small><?= esc_html( $admin_user->user_email ) ?></small></span>
                <?= recruitment_logout_form( 'Keluar', home_url( '/' ), 'recruitment-dashboard__logout' ) ?>
            </div>
        </header>

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

    <section class="recruitment-dashboard__panel recruitment-dashboard__candidate-panel" aria-labelledby="candidate-list-title">
        <div class="recruitment-dashboard__candidate-heading"><h2 id="candidate-list-title">Semua Kandidat</h2><span class="recruitment-dashboard__active-count"><span class="dashicons dashicons-groups" aria-hidden="true"></span><strong><?= esc_html( (string) $active_count ) ?></strong><span>aktif</span></span></div>
        <p class="recruitment-dashboard__section-label">Filter tahap</p>
        <div class="recruitment-dashboard__stage-filters" role="group" aria-label="Pilih tahap kandidat">
            <button class="is-active" type="button" data-stage-filter="all" aria-pressed="true">Semua <span><?= esc_html( (string) count( $candidates ) ) ?></span></button>
            <?php foreach ( $stages as $stage_key => $stage_name ) : ?>
                <button type="button" data-stage-filter="<?= esc_attr( $stage_key ) ?>" aria-pressed="false"><?= esc_html( $stage_name ) ?> <span><?= esc_html( (string) $stage_counts[ $stage_key ] ) ?></span></button>
            <?php endforeach; ?>
        </div>

        <div class="recruitment-dashboard__filters">
            <label class="recruitment-dashboard__search"><span class="dashicons dashicons-search" aria-hidden="true"></span><span class="screen-reader-text">Cari kandidat</span><input type="search" placeholder="Cari nama, kode, atau posisi..." data-candidate-search></label>
            <label><span class="screen-reader-text">Filter dealer</span><select data-candidate-dealer><option value="">Semua Dealer</option><?php foreach ( $dealer_names as $dealer_name ) : ?><option value="<?= esc_attr( $dealer_name ) ?>"><?= esc_html( $dealer_name ) ?></option><?php endforeach; ?></select></label>
            <label><span class="screen-reader-text">Filter posisi</span><select data-candidate-vacancy><option value="">Semua Posisi</option><?php foreach ( $vacancy_names as $vacancy_name ) : ?><option value="<?= esc_attr( $vacancy_name ) ?>"><?= esc_html( $vacancy_name ) ?></option><?php endforeach; ?></select></label>
            <button class="button" type="button" data-candidate-reset>Reset</button>
        </div>

        <p class="recruitment-dashboard__result-count" aria-live="polite"><span data-candidate-count><?= esc_html( (string) count( $candidates ) ) ?></span> kandidat ditampilkan</p>
        <div class="recruitment-dashboard__candidate-list" data-candidate-list>
            <?php foreach ( $candidates as $candidate ) : ?>
                <article class="recruitment-candidate" data-candidate-card data-stage="<?= esc_attr( $candidate['stage'] ) ?>" data-dealer="<?= esc_attr( $candidate['dealer'] ) ?>" data-vacancy="<?= esc_attr( $candidate['vacancy'] ) ?>" data-search="<?= esc_attr( $candidate['search'] ) ?>">
                    <span class="recruitment-candidate__avatar" aria-hidden="true"><?= esc_html( $candidate['initials'] ) ?></span>
                    <div class="recruitment-candidate__identity">
                        <h2><?= esc_html( $candidate['name'] ) ?></h2>
                        <p><?= esc_html( $candidate['token'] ) ?> <span>·</span> Daftar <?= esc_html( $candidate['date'] ) ?></p>
                        <p class="recruitment-candidate__position"><?= esc_html( $candidate['vacancy'] ) ?><?php if ( '' !== $candidate['dealer'] || '' !== $candidate['location'] ) : ?> <span>·</span> <?= esc_html( implode( ' · ', array_filter( [ $candidate['dealer'], $candidate['location'] ] ) ) ) ?><?php endif; ?></p>
                    </div>
                    <div class="recruitment-candidate__status">
                        <span class="recruitment-candidate__stage recruitment-candidate__stage--<?= esc_attr( $candidate['stage'] ) ?>"><?= esc_html( $candidate['stage_label'] ) ?></span>
                        <span class="recruitment-candidate__state"><?= esc_html( $candidate['state_label'] ) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
            <div class="recruitment-dashboard__empty" data-candidate-empty <?= $candidates ? 'hidden' : '' ?>>
                <?php if ( ! $database_ready ) : ?>
                    Koneksi Oracle belum siap. Periksa konfigurasi DSN, user, password, dan driver PDO OCI.
                <?php else : ?>
                    Belum ada kandidat yang sesuai dengan filter ini.
                <?php endif; ?>
            </div>
        </div>
    </section>
    </main>
</div>
