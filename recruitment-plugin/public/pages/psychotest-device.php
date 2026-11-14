<?php
$test_key = sanitize_key( wp_unslash( $_GET['test'] ?? 'iq' ) );
$test_key = in_array( $test_key, [ 'iq', 'pauli', 'disc', 'papi' ], true ) ? $test_key : 'iq';
$requested_application_id = isset( $_GET['application_id'] ) && is_scalar( $_GET['application_id'] )
    ? absint( wp_unslash( (string) $_GET['application_id'] ) )
    : 0;
$stage_status = recruitment_applicant_psychotest_stage_status(
    $requested_application_id > 0 ? (string) $requested_application_id : null
);
if ( null === $stage_status ) {
    wp_die( esc_html__( 'Status lamaran tidak dapat diverifikasi. Silakan coba kembali nanti.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
}
if ( ! $stage_status || ! recruitment_is_psychotest_test_enabled( $test_key ) ) {
    wp_die( esc_html__( 'Pemeriksaan perangkat hanya tersedia saat tahap psikotes aktif dan tes dibuka oleh HR.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
}
$global_settings = (array) get_option( 'recruitment_psychotest_settings', [] );
$test_settings = (array) get_option( 'recruitment_psychotest_tests', [] );
$camera_required = isset( $test_settings[ $test_key ]['camera_required'] )
    ? ! empty( $test_settings[ $test_key ]['camera_required'] )
    : ! empty( $global_settings['camera_required'] ?? true );
$device_arguments = [ 'test' => $test_key ];
if ( $requested_application_id > 0 ) {
    $device_arguments['application_id'] = $requested_application_id;
}
$back_url = recruitment_get_public_url( 'psychotest-preparation', $device_arguments );
$next_url = recruitment_get_public_url( 'psychotest-consent', $device_arguments );
?>
<div class="daw-recruitment daw-recruitment--psychotest-device">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__psychotest-main">
        <div class="daw-recruitment__psychotest-shell daw-recruitment__psychotest-shell--narrow">
            <a class="daw-recruitment__back-link" href="<?= esc_url( $back_url ) ?>">&larr; Kembali</a>

            <div class="daw-recruitment__psychotest-stepper" aria-label="Tahap pemeriksaan perangkat">
                <div class="daw-recruitment__psychotest-step">
                    <span>1</span>
                    <small>Persiapan</small>
                </div>
                <div class="daw-recruitment__psychotest-step">
                    <span>2</span>
                    <small>Perangkat</small>
                </div>
                <div class="daw-recruitment__psychotest-step is-active">
                    <span>3</span>
                    <small>Kamera</small>
                </div>
                <div class="daw-recruitment__psychotest-step">
                    <span>4</span>
                    <small>Persetujuan</small>
                </div>
                <div class="daw-recruitment__psychotest-step">
                    <span>5</span>
                    <small>Foto &amp; KTP</small>
                </div>
            </div>

            <section class="daw-recruitment__psychotest-card daw-recruitment__device-check-card" data-psychotest-device-check data-camera-required="<?= esc_attr( $camera_required ? '1' : '0' ) ?>">
                <div class="daw-recruitment__device-layout">
                    <div class="daw-recruitment__device-preview">
                        <div class="daw-recruitment__device-camera is-empty" data-device-camera-shell>
                            <video class="daw-recruitment__device-video" data-device-video autoplay muted playsinline></video>
                            <div class="daw-recruitment__device-empty" data-device-empty>
                                <span aria-hidden="true">◉</span>
                                <strong>Belum ada video</strong>
                                <small>Tekan tombol di bawah untuk mengizinkan akses kamera.</small>
                            </div>
                        </div>
                        <button type="button" class="daw-recruitment__button daw-recruitment__button--full" data-device-permission>
                            Izinkan Kamera
                        </button>
                        <div class="daw-recruitment__device-unsupported" data-device-browser-banner hidden>Browser Tidak Didukung</div>
                    </div>

                    <div class="daw-recruitment__device-status-panel">
                        <div class="daw-recruitment__device-status-item is-warning" data-device-status data-device-camera-status>
                            <span class="daw-recruitment__device-status-icon" data-device-status-icon aria-hidden="true">•</span>
                            <div>
                                <strong data-device-status-title>Kamera belum aktif</strong>
                                <small data-device-status-copy>Klik “Izinkan Kamera” untuk memulai pengecekan.</small>
                            </div>
                        </div>
                        <div class="daw-recruitment__device-status-item is-warning" data-device-status data-device-browser-status>
                            <span class="daw-recruitment__device-status-icon" data-device-status-icon aria-hidden="true">•</span>
                            <div>
                                <strong data-device-status-title>Browser sedang mengecek</strong>
                                <small data-device-status-copy>Memeriksa dukungan browser untuk kamera.</small>
                            </div>
                        </div>
                        <div class="daw-recruitment__device-status-item is-warning" data-device-status data-device-connection-status>
                            <span class="daw-recruitment__device-status-icon" data-device-status-icon aria-hidden="true">•</span>
                            <div>
                                <strong data-device-status-title>Jaringan sedang diperiksa</strong>
                                <small data-device-status-copy>Memantau kestabilan koneksi internet Anda.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="daw-recruitment__psychotest-footer daw-recruitment__psychotest-footer--split">
                <button type="button" class="daw-recruitment__button daw-recruitment__button--ghost" data-device-retry>
                    Cek Ulang
                </button>
                <button type="button" class="daw-recruitment__button daw-recruitment__button--full" data-device-continue data-next-url="<?= esc_url( $next_url ) ?>" disabled aria-disabled="true">
                    Lanjut ke Persetujuan
                </button>
            </div>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
