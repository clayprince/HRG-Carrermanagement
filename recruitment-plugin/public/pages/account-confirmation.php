<?php
$preview = recruitment_is_ui_preview();
$login_success = '1' === sanitize_text_field( wp_unslash( $_GET['login_success'] ?? '' ) );
$profile_url = recruitment_get_public_url( 'account-profile' );
if ( ! $preview && ! $login_success ) {
    wp_safe_redirect( $profile_url );
    exit;
}
?>
<div class="daw-recruitment daw-account-auth">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account-auth__main daw-account-auth__main--center">
        <section class="daw-account-auth__card daw-account-auth__card--success">
            <span class="daw-account__success-icon" aria-hidden="true">&#10003;</span>
            <h1>Berhasil masuk</h1>
            <p>Anda berhasil masuk ke akun pelamar.</p>
            <?php if ( ! $preview ) : ?>
                <p role="status" data-account-confirmation-redirect="<?= esc_url( $profile_url ) ?>">Mengalihkan ke profil akun...</p>
            <?php endif; ?>
            <a class="daw-recruitment__button" href="<?= esc_url( $profile_url ) ?>">Lanjut ke Profil</a>
        </section>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
