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
                <div class="daw-recruitment__success-notice"><strong>Langkah selanjutnya</strong><p>Tim rekrutmen akan meninjau data Anda. Pantau status lamaran melalui menu Tracking Lamaran di akun Anda.</p></div>
                <div class="daw-recruitment__form-actions"><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'applicant-dashboard', $preview ? [ 'daw_ui_preview' => '1' ] : [] ) ) ?>">Kembali ke Akun</a><a class="daw-recruitment__button daw-recruitment__button--muted" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Lihat Lowongan</a></div>
            </section>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
