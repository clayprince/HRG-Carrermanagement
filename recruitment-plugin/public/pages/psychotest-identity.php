<?php
$requested_application_id = isset( $_GET['application_id'] ) && is_scalar( $_GET['application_id'] )
    ? absint( wp_unslash( (string) $_GET['application_id'] ) )
    : 0;
if ( $requested_application_id < 1 ) {
    wp_die( esc_html__( 'Lamaran psikotes tidak valid.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
}
$stage_status = recruitment_applicant_psychotest_stage_status( (string) $requested_application_id );
if ( null === $stage_status ) {
    wp_die( esc_html__( 'Status lamaran tidak dapat diverifikasi. Silakan coba kembali nanti.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
}
if ( ! $stage_status ) {
    wp_die( esc_html__( 'Verifikasi identitas hanya tersedia untuk lamaran yang sedang berada pada tahap psikotes.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
}

$test_key = 'iq';
$proctoring = new Recruitment_Psychotest_Proctoring();
$session = $proctoring->get_current_session( $requested_application_id );
if ( $proctoring->get_current_session_error() ) {
    wp_die( esc_html__( 'Status persetujuan tidak dapat dibaca dari Oracle.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
}
if ( ! is_array( $session ) || '' === (string) ( $session['consent_at'] ?? '' ) ) {
    wp_die( esc_html__( 'Selesaikan persetujuan tes sebelum verifikasi identitas.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
}
if ( Recruitment_Psychotest_Proctoring::CONSENT_VERSION !== (string) ( $session['consent_version'] ?? '' ) ) {
    wp_safe_redirect(
        recruitment_get_public_url(
            'psychotest-consent',
            [ 'test' => 'iq', 'application_id' => $requested_application_id ]
        )
    );
    exit;
}

$identity_status = (string) ( $session['identity_status'] ?? '' );
$identity_processed = in_array( $identity_status, [ 'CAPTURED', 'MANUAL_REVIEW', 'MANUAL_VERIFIED' ], true );
$back_url = recruitment_get_public_url( 'psychotest-preparation', [ 'application_id' => $requested_application_id ] );
$next_subtest_url = recruitment_get_public_url(
    'psych-iq',
    [ 'start' => '1', 'application_id' => $requested_application_id ]
);
if ( ! recruitment_grant_psychotest_consent( $test_key, $requested_application_id ) ) {
    wp_die( esc_html__( 'Sesi persetujuan tidak dapat disiapkan. Muat ulang halaman dan coba kembali.', 'recruitment-plugin' ), '', [ 'response' => 500 ] );
}
?>
<div class="daw-recruitment daw-recruitment--psychotest-identity">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__psychotest-main">
        <div class="daw-recruitment__psychotest-shell daw-recruitment__psychotest-shell--wide">
            <a class="daw-recruitment__back-link" href="<?= esc_url( $back_url ) ?>">&larr; Kembali ke persiapan</a>

            <div class="daw-recruitment__psychotest-stepper" aria-label="Tahap pemeriksaan identitas">
                <div class="daw-recruitment__psychotest-step"><span>1</span><small>Persiapan</small></div>
                <div class="daw-recruitment__psychotest-step"><span>2</span><small>Perangkat</small></div>
                <div class="daw-recruitment__psychotest-step"><span>3</span><small>Kamera</small></div>
                <div class="daw-recruitment__psychotest-step"><span>4</span><small>Persetujuan</small></div>
                <div class="daw-recruitment__psychotest-step is-active"><span>5</span><small>Foto &amp; KTP</small></div>
            </div>

            <?php if ( $identity_processed ) : ?>
                <section class="daw-recruitment__psychotest-card daw-identity-card daw-identity-card--complete">
                    <span class="daw-recruitment__eyebrow">VERIFIKASI SELESAI</span>
                    <h1>Identitas sudah tersimpan</h1>
                    <p>Anda dapat melanjutkan tanpa mengambil foto baru.</p>
                    <a href="<?= esc_url( $next_subtest_url ) ?>" class="daw-recruitment__button daw-identity-btn-skip-direct">
                        Lanjut ke Tes IQ
                    </a>
                </section>
            <?php else : ?>
            <section class="daw-recruitment__psychotest-card daw-identity-card">
                <header class="daw-identity-header">
                    <span class="daw-recruitment__eyebrow">VERIFIKASI IDENTITAS</span>
                    <h1>Foto bersama KTP</h1>
                    <p>Pastikan wajah dan sisi depan KTP terlihat jelas di kamera.</p>
                </header>

                <div class="daw-identity-grid">
                    <div class="daw-identity-camera-panel" data-proctoring-identity data-next-url="<?= esc_url( $next_subtest_url ) ?>">
                        <div class="daw-identity-viewport" data-proctoring-viewport>
                            <video class="daw-identity-video" data-proctoring-identity-video autoplay muted playsinline hidden></video>
                            <canvas data-proctoring-identity-canvas hidden></canvas>
                            <img class="daw-identity-preview-img" data-proctoring-identity-preview hidden alt="Pratinjau foto identitas" />
                            <div class="daw-identity-frame-overlay" data-proctoring-overlay hidden>
                                <div class="daw-identity-frame-face"><span>Wajah</span></div>
                                <div class="daw-identity-frame-card"><span>KTP</span></div>
                            </div>
                            <div class="daw-identity-placeholder" data-proctoring-placeholder>
                                <div class="daw-identity-placeholder-icon" aria-hidden="true">&#128247;</div>
                                <strong>Kamera siap digunakan</strong>
                                <small>Posisikan wajah dan KTP di dalam bingkai.</small>
                            </div>
                        </div>

                        <div class="daw-identity-status-box" role="status" data-proctoring-identity-status>
                            Kamera belum aktif.
                        </div>

                        <div class="daw-identity-actions">
                            <button type="button" class="daw-recruitment__button daw-recruitment__button--full" data-proctoring-identity-start>
                                Buka kamera
                            </button>
                            <button type="button" class="daw-recruitment__button daw-recruitment__button--full daw-identity-btn-capture" data-proctoring-identity-capture style="display:none;">
                                Ambil foto
                            </button>
                            <div class="daw-identity-confirm-group" data-proctoring-identity-confirm-group style="display:none;">
                                <button type="button" class="daw-recruitment__button daw-recruitment__button--ghost" data-proctoring-identity-retake>
                                    Foto ulang
                                </button>
                                <button type="button" class="daw-recruitment__button daw-recruitment__button--full daw-identity-btn-confirm" data-proctoring-identity-confirm-submit data-next-url="<?= esc_url( $next_subtest_url ) ?>">
                                    Konfirmasi &amp; lanjut ke Tes IQ
                                </button>
                            </div>
                        </div>

                        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" enctype="multipart/form-data" data-proctoring-identity-form>
                            <?php wp_nonce_field( 'daw_proctoring_identity' ); ?>
                            <input type="hidden" name="action" value="daw_proctoring_identity">
                            <input type="hidden" name="test" value="<?= esc_attr( $test_key ) ?>">
                            <input type="hidden" name="application_id" value="<?= esc_attr( (string) $requested_application_id ) ?>">
                            <input type="hidden" name="proctoring_action" value="capture" data-proctoring-identity-action>
                            <p role="alert" class="daw-identity-error-msg" data-proctoring-identity-error hidden></p>
                            <div class="daw-identity-manual-fallback">
                                <span>Kamera bermasalah?</span>
                                <button type="submit" class="daw-identity-manual-btn" data-proctoring-identity-manual>
                                    Minta verifikasi manual HR
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <footer class="daw-identity-footer-notice">
                    Foto digunakan untuk verifikasi identitas oleh tim HR.
                </footer>
            </section>
            <?php endif; ?>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
