<?php
$proctoring_session = ( new Recruitment_Psychotest_Proctoring() )->get_current_session();
$manual_camera_path = is_array( $proctoring_session ) && in_array( (string) ( $proctoring_session['identity_status'] ?? '' ), [ 'MANUAL_REVIEW', 'MANUAL_VERIFIED' ], true );
?>
<aside class="daw-proctoring-watch" data-psychotest-proctoring-watch data-camera-mode="<?= esc_attr( $manual_camera_path ? 'manual' : 'camera' ) ?>" data-ajax-url="<?= esc_url( admin_url( 'admin-ajax.php' ) ) ?>" data-nonce="<?= esc_attr( wp_create_nonce( 'recruitment_proctoring_event' ) ) ?>" data-face-runtime-url="<?= esc_url( recruitment_get_plugin_url( 'assets/vendor/mediapipe/face_detection.js' ) ) ?>" data-face-assets-root="<?= esc_url( recruitment_get_plugin_url( 'assets/vendor/mediapipe/' ) ) ?>">
    <video class="daw-proctoring-watch__video" data-proctoring-watch-video autoplay muted playsinline aria-label="Pratinjau kamera selama psikotes"></video>
    <p role="status" data-proctoring-watch-status><?= $manual_camera_path ? 'Verifikasi identitas manual dipilih. Perpindahan tab tetap dapat dicatat; HR akan meninjau identitas.' : 'Kamera sedang disiapkan. Tidak ada audio yang direkam.' ?></p>
    <?php if ( ! $manual_camera_path ) : ?>
        <p role="status" data-proctoring-face-status>Deteksi wajah lokal sedang menunggu kamera aktif.</p>
    <?php endif; ?>
    <div>
        <button type="button" class="daw-recruitment__button daw-recruitment__button--ghost" data-proctoring-microphone-check>Periksa Mikrofon</button>
        <p role="status" data-proctoring-microphone-status>Periksa izin dan ketersediaan mikrofon. Hanya status yang dicatat; audio tidak direkam.</p>
    </div>
    <small>Sistem memeriksa secara lokal apakah wajah tidak terlihat atau lebih dari satu wajah berada di bingkai, serta mencatat perpindahan tab dan kamera terputus. Peringatan ditinjau HR dan tidak otomatis menggagalkan tes. Maksimal lima snapshot disimpan untuk seluruh sesi tes.</small>
</aside>
