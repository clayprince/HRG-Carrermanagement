<?php
$proctoring_session = ( new Recruitment_Psychotest_Proctoring() )->get_current_session();
$manual_camera_path = is_array( $proctoring_session ) && in_array( (string) ( $proctoring_session['identity_status'] ?? '' ), [ 'MANUAL_REVIEW', 'MANUAL_VERIFIED' ], true );
?>
<aside class="daw-proctoring-watch" data-psychotest-proctoring-watch data-camera-mode="<?= esc_attr( $manual_camera_path ? 'manual' : 'camera' ) ?>" data-ajax-url="<?= esc_url( admin_url( 'admin-ajax.php' ) ) ?>" data-nonce="<?= esc_attr( wp_create_nonce( 'recruitment_proctoring_event' ) ) ?>" data-face-runtime-url="<?= esc_url( recruitment_get_plugin_url( 'assets/vendor/mediapipe/face_detection.js' ) ) ?>" data-face-assets-root="<?= esc_url( recruitment_get_plugin_url( 'assets/vendor/mediapipe/' ) ) ?>" tabindex="0" aria-label="Pratinjau kamera psikotes. Seret untuk memindahkan jendela.">
    <video class="daw-proctoring-watch__video" data-proctoring-watch-video autoplay muted playsinline aria-label="Pratinjau kamera selama psikotes"></video>
    <div class="daw-proctoring-watch__announcements" aria-live="polite">
        <span role="status" data-proctoring-watch-status><?= $manual_camera_path ? 'Verifikasi identitas manual dipilih. Perpindahan tab tetap dapat dicatat; HR akan meninjau identitas.' : 'Kamera sedang disiapkan. Tidak ada audio yang direkam.' ?></span>
        <?php if ( ! $manual_camera_path ) : ?>
            <span role="status" data-proctoring-face-status>Deteksi wajah lokal sedang menunggu kamera aktif.</span>
        <?php endif; ?>
    </div>
</aside>
