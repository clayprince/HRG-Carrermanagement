<?php
$current_page = sanitize_key( $_GET['page'] ?? '' );
$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
$current_post_type = sanitize_key( $_GET['post_type'] ?? ( $screen->post_type ?? '' ) );
$current_stage = sanitize_key( $_GET['stage'] ?? '' );
$settings_section = sanitize_key( $_GET['section'] ?? 'email' );
$dashboard_url = recruitment_get_admin_url( 'dashboard' );
$settings_url = recruitment_get_admin_url( 'settings' );
$hr_identity = recruitment_get_current_hr_identity();
$vacancy_view = 'create' === sanitize_key( $_GET['view'] ?? '' ) ? 'create' : ( 'recruitment-applications' === $current_page ? 'requests' : 'list' );
$active_candidate_count = ( new Recruitment_Database() )->get_active_applicant_count();
$stage_url = static function ( string $stage ) use ( $dashboard_url ): string {
    return add_query_arg( 'stage', $stage, $dashboard_url );
};
$stage_links = [
    [ 'label' => 'Semua Kandidat Aktif', 'page' => 'recruitment-applicants', 'url' => recruitment_get_admin_url( 'applicants' ) ],
    [ 'label' => 'Seleksi Administrasi', 'stage' => 'administration' ],
    [ 'label' => 'Psikotes', 'stage' => 'psychological_test' ],
    [ 'label' => 'Wawancara HR', 'page' => 'recruitment-hr-interview', 'url' => admin_url( 'admin.php?page=recruitment-hr-interview' ) ],
    [ 'label' => 'Wawancara User', 'page' => 'recruitment-user-interview', 'url' => admin_url( 'admin.php?page=recruitment-user-interview' ) ],
    [ 'label' => 'Keputusan Akhir', 'page' => 'recruitment-final-decision', 'url' => admin_url( 'admin.php?page=recruitment-final-decision' ) ],
];
$root_links = [
    [ 'label' => 'Database Pelamar', 'icon' => 'dashicons-database', 'page' => 'recruitment-applicants', 'url' => recruitment_get_admin_url( 'applicants' ) ],
    [ 'label' => 'Lowongan', 'icon' => 'dashicons-portfolio', 'page' => 'recruitment-vacancies', 'post_type' => 'daw_vacancy', 'url' => recruitment_get_admin_url( 'vacancies' ), 'children' => [
        [ 'label' => 'Semua Lowongan', 'view' => 'list', 'url' => recruitment_get_admin_url( 'vacancies' ) ],
        [ 'label' => 'Tambah Lowongan', 'view' => 'create', 'url' => add_query_arg( 'view', 'create', recruitment_get_admin_url( 'vacancies' ) ) ],
        [ 'label' => 'Pengajuan Lowongan', 'view' => 'requests', 'url' => recruitment_get_admin_url( 'applications' ) ],
    ] ],
];
?>
<aside class="recruitment-dashboard__sidebar daw-recruitment-sidebar" data-recruitment-sidebar aria-label="Navigasi Recruitment">
    <a class="recruitment-dashboard__brand" href="<?= esc_url( $dashboard_url ) ?>" aria-label="DAW Admin, Dashboard">
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
    <nav class="recruitment-dashboard__navigation" aria-label="Menu Recruitment">
        <a href="<?= esc_url( $dashboard_url ) ?>" class="<?= 'recruitment-dashboard' === $current_page && '' === $current_stage ? 'is-active' : '' ?>" title="Dashboard" <?= 'recruitment-dashboard' === $current_page && '' === $current_stage ? 'aria-current="page"' : '' ?>>
            <span class="dashicons dashicons-dashboard" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Dashboard</span>
        </a>

        <div class="recruitment-dashboard__nav-group">
            <button type="button" class="recruitment-dashboard__nav-group-toggle" data-nav-group-toggle aria-expanded="true" aria-controls="recruitment-stage-links">
                <span class="dashicons dashicons-groups" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Recruitment</span><span class="dashicons dashicons-arrow-down-alt2 recruitment-dashboard__nav-chevron" aria-hidden="true"></span>
            </button>
            <div class="recruitment-dashboard__nav-group-items" id="recruitment-stage-links" data-nav-group-panel>
                <?php foreach ( $stage_links as $item ) :
                    $is_active = isset( $item['stage'] )
                        ? 'recruitment-dashboard' === $current_page && $current_stage === $item['stage']
                        : $current_page === $item['page'];
                    $url = $item['url'] ?? $stage_url( $item['stage'] );
                    ?>
                    <a href="<?= esc_url( $url ) ?>" class="<?= $is_active ? 'is-active' : '' ?>" <?= $is_active ? 'aria-current="page"' : '' ?>><span class="recruitment-dashboard__nav-label"><?= esc_html( $item['label'] ) ?></span><?php if ( 'Semua Kandidat Aktif' === $item['label'] ) : ?><span class="recruitment-dashboard__nav-count"><?= esc_html( number_format_i18n( $active_candidate_count ) ) ?></span><?php endif; ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php foreach ( $root_links as $item ) :
            $is_active = ( isset( $item['page'] ) && $current_page === $item['page'] )
                || ( isset( $item['post_type'] ) && $current_post_type === $item['post_type'] )
                || ( 'Lowongan' === $item['label'] && 'recruitment-applications' === $current_page );
            ?>
            <?php if ( ! empty( $item['children'] ) ) : ?>
                <div class="recruitment-dashboard__nav-group">
                    <button type="button" class="recruitment-dashboard__nav-group-toggle <?= $is_active ? 'is-active' : '' ?>" data-nav-group-toggle aria-expanded="<?= $is_active ? 'true' : 'false' ?>" aria-controls="recruitment-vacancy-links">
                        <span class="dashicons <?= esc_attr( $item['icon'] ) ?>" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label"><?= esc_html( $item['label'] ) ?></span><span class="dashicons dashicons-arrow-down-alt2 recruitment-dashboard__nav-chevron" aria-hidden="true"></span>
                    </button>
                    <div class="recruitment-dashboard__nav-group-items" id="recruitment-vacancy-links" data-nav-group-panel <?= $is_active ? '' : 'hidden' ?>>
                        <?php foreach ( $item['children'] as $child ) :
                            $child_is_active = ( $is_active && $vacancy_view === $child['view'] )
                                || ( 'requests' === $child['view'] && 'recruitment-applications' === $current_page );
                            ?>
                            <a href="<?= esc_url( $child['url'] ) ?>" class="<?= $child_is_active ? 'is-active' : '' ?>" <?= $child_is_active ? 'aria-current="page"' : '' ?>><span class="recruitment-dashboard__nav-label"><?= esc_html( $child['label'] ) ?></span></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else : ?>
                <a href="<?= esc_url( $item['url'] ) ?>" class="<?= $is_active ? 'is-active' : '' ?>" title="<?= esc_attr( $item['label'] ) ?>" <?= $is_active ? 'aria-current="page"' : '' ?>><span class="dashicons <?= esc_attr( $item['icon'] ) ?>" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label"><?= esc_html( $item['label'] ) ?></span></a>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="recruitment-dashboard__nav-group">
            <button type="button" class="recruitment-dashboard__nav-group-toggle" data-nav-group-toggle aria-expanded="true" aria-controls="recruitment-psychotest-links">
                <span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Psikotes</span><span class="dashicons dashicons-arrow-down-alt2 recruitment-dashboard__nav-chevron" aria-hidden="true"></span>
            </button>
            <div class="recruitment-dashboard__nav-group-items" id="recruitment-psychotest-links" data-nav-group-panel>
                <a href="<?= esc_url( recruitment_get_admin_url( 'psychotest-settings' ) ) ?>" class="<?= 'recruitment-psychotest-settings' === $current_page ? 'is-active' : '' ?>" <?= 'recruitment-psychotest-settings' === $current_page ? 'aria-current="page"' : '' ?>><span class="recruitment-dashboard__nav-label">Setting Psikotes</span></a>
                <a href="<?= esc_url( recruitment_get_admin_url( 'psychotest-results' ) ) ?>" class="<?= 'recruitment-psychotest-results' === $current_page ? 'is-active' : '' ?>" <?= 'recruitment-psychotest-results' === $current_page ? 'aria-current="page"' : '' ?>><span class="recruitment-dashboard__nav-label">Hasil Psikotes</span></a>
                <a href="<?= esc_url( recruitment_get_admin_url( 'question-bank' ) ) ?>" class="<?= 'recruitment-question-bank' === $current_page ? 'is-active' : '' ?>" <?= 'recruitment-question-bank' === $current_page ? 'aria-current="page"' : '' ?>><span class="recruitment-dashboard__nav-label">Bank Soal</span></a>
            </div>
        </div>

        <a href="<?= esc_url( admin_url( 'admin.php?page=recruitment-interview-schedule' ) ) ?>" class="<?= 'recruitment-interview-schedule' === $current_page ? 'is-active' : '' ?>" title="Jadwal Wawancara" <?= 'recruitment-interview-schedule' === $current_page ? 'aria-current="page"' : '' ?>><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Jadwal Wawancara</span></a>
        <a href="<?= esc_url( admin_url( 'admin.php?page=recruitment-user-management' ) ) ?>" class="<?= 'recruitment-user-management' === $current_page ? 'is-active' : '' ?>" title="Pengelolaan Akun User" <?= 'recruitment-user-management' === $current_page ? 'aria-current="page"' : '' ?>><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Pengelolaan Akun User</span></a>
        <a href="<?= esc_url( admin_url( 'admin.php?page=recruitment-hr-profile' ) ) ?>" class="<?= 'recruitment-hr-profile' === $current_page ? 'is-active' : '' ?>" title="Profil Admin HR" <?= 'recruitment-hr-profile' === $current_page ? 'aria-current="page"' : '' ?>><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Profil Admin HR</span></a>
        <a href="<?= esc_url( recruitment_get_admin_url( 'settings' ) ) ?>" class="<?= 'recruitment-settings' === $current_page ? 'is-active' : '' ?>" title="Email Recruitment" <?= 'recruitment-settings' === $current_page ? 'aria-current="page"' : '' ?>><span class="dashicons dashicons-email-alt" aria-hidden="true"></span><span class="recruitment-dashboard__nav-label">Email Recruitment</span></a>
    </nav>
    <div class="recruitment-dashboard__sidebar-footer">
        <button type="button" data-sidebar-collapse aria-expanded="true" title="Ciutkan navigasi">
            <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
            <span class="recruitment-dashboard__nav-label">Ciutkan</span>
        </button>
    </div>
</aside>
