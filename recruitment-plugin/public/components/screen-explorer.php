<?php
$screen_explorer_preview = '1' === sanitize_text_field( wp_unslash( $_GET['daw_ui_preview'] ?? '' ) ) || ( function_exists( 'current_user_can' ) && ( current_user_can( 'manage_options' ) || current_user_can( 'manage_recruitment' ) ) );
if ( ! $screen_explorer_preview ) {
    return;
}

$screen_explorer_items = [
    'Publik' => [
        [ 'label' => 'Career Landing', 'screen' => 'careers' ],
        [ 'label' => 'Detail Lowongan', 'screen' => 'job-detail', 'args' => [ 'job_id' => 5 ] ],
        [ 'label' => 'Form Lamaran', 'screen' => 'application', 'args' => [ 'job_id' => 5 ] ],
    ],
    'Akun Pelamar' => [
        [ 'label' => 'Preview Menu Profil Pelamar', 'screen' => 'careers', 'args' => [ 'daw_ui_preview' => '1' ] ],
        [ 'label' => 'Masuk Akun Pelamar', 'screen' => 'applicant-login' ],
        [ 'label' => 'Daftar Akun Pelamar', 'screen' => 'applicant-register' ],
        [ 'label' => 'Konfirmasi Akun', 'screen' => 'account-confirmation', 'args' => [ 'daw_ui_preview' => '1' ] ],
        [ 'label' => 'Profil Akun', 'screen' => 'account-profile', 'args' => [ 'daw_ui_preview' => '1' ] ],
        [ 'label' => 'Riwayat Lamaran', 'screen' => 'application-history', 'args' => [ 'daw_ui_preview' => '1' ] ],
        [ 'label' => 'Detail Lamaran', 'screen' => 'application-detail', 'args' => [ 'daw_ui_preview' => '1' ] ],
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
        [ 'label' => 'Form Lamaran Admin', 'screen' => 'application-form-builder' ],
        [ 'label' => 'Lamaran', 'screen' => 'applications' ],
        [ 'label' => 'Jadwal Wawancara', 'screen' => 'interview-schedule' ],
        [ 'label' => 'Pengaturan Psikotes', 'screen' => 'psychotest-settings' ],
        [ 'label' => 'Bank Soal', 'screen' => 'question-bank' ],
        [ 'label' => 'Final Decision', 'screen' => 'final-decision' ],
        [ 'label' => 'Wawancara User', 'screen' => 'user-interview' ],
        [ 'label' => 'Catatan Wawancara User', 'screen' => 'user-interview', 'args' => [ 'view' => 'notes' ] ],
        [ 'label' => 'Pengaturan', 'screen' => 'settings' ],
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
                    $is_admin = 'Admin' === $group && 'login' !== $item['screen'];
                    $args = $item['args'] ?? [];
                    if ( $is_admin ) {
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
