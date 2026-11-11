<?php
$screen_explorer_job_id = 0;
foreach ( get_posts(
    [
        'post_type' => 'daw_vacancy',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
    ]
) as $vacancy_post ) {
    $vacancy_status = (string) get_post_meta( $vacancy_post->ID, '_daw_vacancy_status', true );
    $deadline = (string) get_post_meta( $vacancy_post->ID, '_daw_deadline', true );
    if ( ( '' !== $vacancy_status && 'published' !== $vacancy_status )
        || ( '' !== $deadline && $deadline < current_time( 'Y-m-d' ) ) ) {
        continue;
    }

    $screen_explorer_job_id = 1000000000000 + (int) $vacancy_post->ID;
    break;
}

$screen_explorer_items = [
    'Publik' => [
        [ 'label' => 'Career Landing', 'screen' => 'careers' ],
        [ 'label' => 'Detail Lowongan', 'screen' => 'job-detail', 'args' => [ 'job_id' => $screen_explorer_job_id ] ],
        [ 'label' => 'Form Lamaran', 'screen' => 'application-form', 'args' => [ 'job_id' => $screen_explorer_job_id ] ],
    ],
    'Akun Pelamar' => [
        [ 'label' => 'Preview Menu Profil Pelamar', 'screen' => 'careers', 'args' => [ 'daw_ui_preview' => '1' ] ],
        [ 'label' => 'Masuk Akun Pelamar', 'screen' => 'applicant-login' ],
        [ 'label' => 'Daftar Akun Pelamar', 'screen' => 'applicant-register' ],
        [ 'label' => 'Konfirmasi Akun', 'screen' => 'account-confirmation', 'args' => [ 'daw_ui_preview' => '1' ] ],
        [ 'label' => 'Profil Akun', 'screen' => 'account-profile', 'args' => [ 'daw_ui_preview' => '1' ] ],
        [ 'label' => 'Proses Lamaran', 'screen' => 'application-process', 'args' => [ 'daw_ui_preview' => '1' ] ],
        [ 'label' => 'Lamaran Berhasil', 'screen' => 'application-confirmation', 'args' => [ 'daw_ui_preview' => '1' ] ],
    ],
    'Psikotes' => [
        [ 'label' => 'Beranda Psikotes', 'screen' => 'psychotest' ],
        [ 'label' => 'Persiapan Assessment', 'screen' => 'psychotest-preparation' ],
        [ 'label' => 'Periksa Perangkat', 'screen' => 'psychotest-device' ],
        [ 'label' => 'Psikotes: Review & Kirim', 'screen' => 'psych-test', 'args' => [ 'state' => 'review' ] ],
        [ 'label' => 'Psikotes: Memproses Hasil', 'screen' => 'psych-test', 'args' => [ 'state' => 'processing' ] ],
        [ 'label' => 'Psikotes: Tes Selesai', 'screen' => 'psych-test', 'args' => [ 'state' => 'complete' ] ],
        [ 'label' => 'Booking Wawancara (menunggu integrasi)', 'screen' => 'interview-booking', 'args' => [ 'daw_ui_preview' => '1' ] ],
    ],
    'Admin' => [
        [ 'label' => 'Login Admin', 'screen' => 'login' ],
        [ 'label' => 'Dashboard', 'screen' => 'dashboard' ],
        [ 'label' => 'Rekrutmen Aktif', 'screen' => 'applicants' ],
        [ 'label' => 'Lowongan', 'screen' => 'vacancies' ],
        [ 'label' => 'Lamaran', 'screen' => 'applications' ],
        [ 'label' => 'Jadwal Wawancara', 'screen' => 'interview-schedule' ],
        [ 'label' => 'Pengaturan Psikotes', 'screen' => 'psychotest-settings' ],
        [ 'label' => 'Bank Soal', 'screen' => 'question-bank' ],
        [ 'label' => 'Final Decision', 'screen' => 'final-decision' ],
        [ 'label' => 'Wawancara User', 'screen' => 'user-interview' ],
        [ 'label' => 'Catatan Wawancara User', 'screen' => 'user-interview', 'args' => [ 'view' => 'notes' ] ],
        [ 'label' => 'Pengaturan', 'screen' => 'settings' ],
    ],
    'User Department' => [
        [ 'label' => 'Dashboard', 'screen' => 'user-department', 'args' => [ 'view' => 'dashboard' ] ],
        [ 'label' => 'Request Lowongan', 'screen' => 'user-department', 'args' => [ 'view' => 'requests' ] ],
        [ 'label' => 'Kandidat Wawancara User', 'screen' => 'user-department', 'args' => [ 'view' => 'interviews' ] ],
        [ 'label' => 'Data Pelamar', 'screen' => 'user-department', 'args' => [ 'view' => 'applicants' ] ],
        [ 'label' => 'Hasil Wawancara User', 'screen' => 'user-department', 'args' => [ 'view' => 'results' ] ],
    ],
];
?>
<aside class="daw-screen-explorer" data-screen-explorer aria-label="Screen Explorer">
    <button class="daw-screen-explorer__toggle" type="button" data-screen-explorer-toggle aria-expanded="false" aria-controls="daw-screen-explorer-panel">
        <span aria-hidden="true">D</span> Screen Explorer
    </button>
    <section class="daw-screen-explorer__panel" id="daw-screen-explorer-panel" hidden>
        <div class="daw-screen-explorer__heading">
            <div><strong>DAW Screen Explorer</strong><small>Preview halaman</small></div>
            <button type="button" data-screen-explorer-close aria-label="Tutup Screen Explorer">&times;</button>
        </div>
        <div class="daw-screen-explorer__content">
        <?php foreach ( $screen_explorer_items as $group => $items ) : ?>
            <div class="daw-screen-explorer__group daw-screen-explorer__group--collapsible is-open">
                <button class="daw-screen-explorer__group-toggle" type="button" data-screen-explorer-group-toggle aria-expanded="true">
                    <span><?= esc_html( $group ) ?></span>
                    <span class="daw-screen-explorer__chevron" aria-hidden="true">▾</span>
                </button>
                <div class="daw-screen-explorer__group-body">
                <?php foreach ( $items as $item ) : ?>
                    <?php
                    $is_admin = in_array( $group, [ 'Admin', 'User Department' ], true ) && 'login' !== $item['screen'];
                    $args = $item['args'] ?? [];
                    if ( 'User Department' === $group ) {
                        $url = add_query_arg(
                            array_merge( [ 'page' => 'daw-user-department' ], $args ),
                            admin_url( 'admin.php' )
                        );
                    } elseif ( $is_admin ) {
                        $url = recruitment_get_admin_url( $item['screen'] );
                        if ( $args ) {
                            $url = add_query_arg( $args, $url );
                        }
                    } else {
                        $url = recruitment_get_public_url( $item['screen'], array_merge( [ 'daw_ui_preview' => '1' ], $args ) );
                    }
                    ?>
                    <a href="<?= esc_url( $url ) ?>" <?= $is_admin ? 'target="_blank" rel="noopener"' : '' ?>>
                        <span><?= esc_html( $item['label'] ) ?></span><span aria-hidden="true">&rarr;</span>
                    </a>
                <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </section>
</aside>
