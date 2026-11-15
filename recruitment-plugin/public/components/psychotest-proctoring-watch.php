<?php
$proctoring_application_id = recruitment_get_psychotest_application_id();
$proctoring_service = new Recruitment_Psychotest_Proctoring();
$proctoring_session = $proctoring_service->get_current_session( $proctoring_application_id );
$proctoring_violation_config = wp_json_encode( $proctoring_service->get_client_violation_config() );
$manual_camera_path = is_array( $proctoring_session ) && in_array( (string) ( $proctoring_session['identity_status'] ?? '' ), [ 'MANUAL_REVIEW', 'MANUAL_VERIFIED' ], true );
?>
<aside class="daw-proctoring-watch" data-psychotest-proctoring-watch data-application-id="<?= esc_attr( (string) $proctoring_application_id ) ?>" data-camera-mode="<?= esc_attr( $manual_camera_path ? 'manual' : 'camera' ) ?>" data-ajax-url="<?= esc_url( admin_url( 'admin-ajax.php' ) ) ?>" data-nonce="<?= esc_attr( wp_create_nonce( 'recruitment_proctoring_event' ) ) ?>" data-face-runtime-url="<?= esc_url( recruitment_get_plugin_url( 'assets/vendor/mediapipe/face_detection.js' ) ) ?>" data-face-assets-root="<?= esc_url( recruitment_get_plugin_url( 'assets/vendor/mediapipe/' ) ) ?>" data-violation-config="<?= esc_attr( is_string( $proctoring_violation_config ) ? $proctoring_violation_config : '{}' ) ?>" tabindex="0" aria-label="Pratinjau kamera psikotes. Seret untuk memindahkan jendela.">
    <video class="daw-proctoring-watch__video" data-proctoring-watch-video autoplay muted playsinline aria-label="Pratinjau kamera selama psikotes"></video>
    <div class="daw-proctoring-watch__announcements" aria-live="polite">
        <span role="status" data-proctoring-watch-status><?= $manual_camera_path ? 'Verifikasi identitas manual dipilih. Perpindahan tab tetap dapat dicatat; HR akan meninjau identitas.' : 'Kamera sedang disiapkan. Tidak ada audio yang direkam.' ?></span>
        <?php if ( ! $manual_camera_path ) : ?>
            <span role="status" data-proctoring-face-status>Deteksi wajah lokal sedang menunggu kamera aktif.</span>
        <?php endif; ?>
    </div>
    <?php if ( ! $manual_camera_path ) : ?>
        <button type="button" class="daw-proctoring-watch__restart" data-proctoring-restart-camera hidden>Aktifkan kembali kamera</button>
    <?php endif; ?>
    <div class="daw-proctoring-watch__warning" data-proctoring-watch-warning role="alert" aria-live="assertive" hidden></div>
</aside>
