<?php
$proctoring_session = ( new Recruitment_Psychotest_Proctoring() )->get_current_session();
$manual_camera_path = is_array( $proctoring_session ) && in_array( (string) ( $proctoring_session['identity_status'] ?? '' ), [ 'MANUAL_REVIEW', 'MANUAL_VERIFIED' ], true );
?>
<aside class="daw-proctoring-watch" data-psychotest-proctoring-watch data-camera-mode="<?= esc_attr( $manual_camera_path ? 'manual' : 'camera' ) ?>" data-ajax-url="<?= esc_url( admin_url( 'admin-ajax.php' ) ) ?>" data-nonce="<?= esc_attr( wp_create_nonce( 'recruitment_proctoring_event' ) ) ?>">
    <video class="daw-proctoring-watch__video" data-proctoring-watch-video autoplay muted playsinline aria-label="Pratinjau kamera selama psikotes"></video>
    <p role="status" data-proctoring-watch-status><?= $manual_camera_path ? 'Verifikasi identitas manual dipilih. Perpindahan tab tetap dapat dicatat; HR akan meninjau identitas.' : 'Kamera sedang disiapkan. Tidak ada audio yang direkam.' ?></p>
    <div>
        <button type="button" class="daw-recruitment__button daw-recruitment__button--ghost" data-proctoring-microphone-check>Periksa Mikrofon</button>
        <p role="status" data-proctoring-microphone-status>Periksa izin dan ketersediaan mikrofon. Hanya status yang dicatat; audio tidak direkam.</p>
    </div>
    <small>Sistem mencatat perpindahan tab dan kamera terputus. Maksimal lima snapshot disimpan untuk seluruh sesi tes.</small>
</aside>
