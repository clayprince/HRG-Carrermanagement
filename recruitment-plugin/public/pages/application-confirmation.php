<?php
$preview = recruitment_is_ui_preview();
$confirmation_pending = '1' === (string) get_user_meta( get_current_user_id(), 'daw_application_confirmation_pending', true );
if ( ! $preview && ! $confirmation_pending ) {
    wp_safe_redirect( recruitment_get_public_url( 'application-history' ) );
    exit;
}
if ( $confirmation_pending ) {
    delete_user_meta( get_current_user_id(), 'daw_application_confirmation_pending' );
}
?>
<div class="daw-recruitment daw-recruitment--application daw-recruitment--confirmation">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__section daw-recruitment__application-main">
        <div class="daw-recruitment__container daw-recruitment__form-container">
            <section class="daw-recruitment__panel daw-recruitment__success">
                <div class="daw-recruitment__success-icon" aria-hidden="true">&#10003;</div>
                <h1>Lamaran Berhasil Dikirim</h1>
                <p>Terima kasih, <?= esc_html( wp_get_current_user()->display_name ) ?>. Lamaran Anda berhasil dikirim.</p>
                <div class="daw-recruitment__success-notice"><strong>Langkah selanjutnya</strong><p>Tim rekrutmen akan meninjau data Anda. Status dan riwayat akun akan ditampilkan setelah integrasi data aplikasi selesai.</p></div>
                <div class="daw-recruitment__form-actions"><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'application-history' ) ) ?>">Kembali ke Akun</a><a class="daw-recruitment__button daw-recruitment__button--muted" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Lihat Lowongan</a></div>
            </section>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>