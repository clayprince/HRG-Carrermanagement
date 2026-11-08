<?php
$preview = recruitment_is_ui_preview();
$applicant = $preview ? [ 'full_name' => 'Preview Pelamar' ] : recruitment_get_current_applicant();
$confirmation_key = $applicant && ! empty( $applicant['session_token'] )
    ? 'daw_applicant_confirmation_' . hash( 'sha256', (string) $applicant['session_token'] )
    : '';
$confirmation_pending = $preview || ( '' !== $confirmation_key && false !== get_transient( $confirmation_key ) );
if ( ! $preview && ! $confirmation_pending ) {
    wp_safe_redirect( recruitment_get_public_url( 'applicant-dashboard' ) );
    exit;
}
$confirmation_data = $preview ? [] : get_transient( $confirmation_key );
$application_token = is_array( $confirmation_data ) ? (string) ( $confirmation_data['application_token'] ?? '' ) : '';
$application_id = is_array( $confirmation_data ) ? (string) ( $confirmation_data['application_id'] ?? '' ) : '';
if ( ! $preview ) {
    delete_transient( $confirmation_key );
}
?>
<div class="daw-recruitment daw-recruitment--application daw-recruitment--confirmation">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__section daw-recruitment__application-main">
        <div class="daw-recruitment__container daw-recruitment__form-container">
            <section class="daw-recruitment__panel daw-recruitment__success">
                <div class="daw-recruitment__success-icon" aria-hidden="true">&#10003;</div>
                <h1>Lamaran Berhasil Dikirim</h1>
                <p>Terima kasih, <?= esc_html( $applicant['full_name'] ?? '' ) ?>. Lamaran Anda berhasil dikirim dan tercatat pada akun Oracle.</p>
                <?php if ( '' !== $application_token ) : ?><div class="daw-recruitment__success-notice"><strong>Simpan Token Tracking Anda</strong><p>Gunakan token ini untuk mencari lamaran pada halaman Tracking Lamaran.</p><code><?= esc_html( $application_token ) ?></code><?php if ( '' !== $application_id ) : ?><p>No. Lamaran: <?= esc_html( $application_id ) ?></p><?php endif; ?></div><?php endif; ?>
                <div class="daw-recruitment__success-notice"><strong>Langkah selanjutnya</strong><p>Tim rekrutmen akan meninjau data Anda. Pantau status lamaran melalui menu Tracking Lamaran di akun Anda.</p></div>
                <div class="daw-recruitment__form-actions"><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'application-process', '' !== $application_token ? [ 'token' => $application_token ] : ( $preview ? [ 'daw_ui_preview' => '1' ] : [] ) ) ) ?>">Lacak Lamaran</a><a class="daw-recruitment__button daw-recruitment__button--muted" href="<?= esc_url( recruitment_get_public_url( 'applicant-dashboard', $preview ? [ 'daw_ui_preview' => '1' ] : [] ) ) ?>">Kembali ke Akun</a></div>
            </section>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
