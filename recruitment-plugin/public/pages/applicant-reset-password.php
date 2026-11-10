<?php
$token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
$token_is_valid = false;
$account_id = null;

if ( '' !== $token && 43 === strlen( $token ) ) {
    $token_hash = hash( 'sha256', $token );
    $account_id = get_transient( 'daw_applicant_reset_' . $token_hash );
    if ( false !== $account_id ) {
        $account_token_hash = (string) get_transient( 'daw_applicant_reset_account_' . (string) $account_id );
        if ( '' !== $account_token_hash && hash_equals( $account_token_hash, $token_hash ) ) {
            $token_is_valid = true;
        }
    }
}

$notice = sanitize_key( wp_unslash( $_GET['reset_notice'] ?? '' ) );
$notice_messages = [
    'invalid_password' => 'Kata sandi minimal 8 karakter dan harus sama dengan konfirmasi kata sandi.',
    'invalid_request'  => 'Sesi form tidak valid. Silakan coba kembali.',
    'service_unavailable' => 'Layanan database sedang tidak tersedia. Silakan coba beberapa saat lagi.',
];
?>
<div class="daw-recruitment daw-account-auth">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account-auth__main daw-account-auth__main--center">
        <section class="daw-account-auth__card">
            <span class="daw-recruitment__eyebrow">KEAMANAN AKUN</span>
            <h2>Atur Ulang Kata Sandi</h2>

            <?php if ( isset( $notice_messages[ $notice ] ) ) : ?>
                <div class="daw-account__alert" role="alert"><?= esc_html( $notice_messages[ $notice ] ) ?></div>
            <?php endif; ?>

            <?php if ( ! $token_is_valid ) : ?>
                <div class="daw-account__alert" role="alert">
                    Tautan reset kata sandi tidak valid atau telah kedaluwarsa. Silakan ajukan permintaan reset baru dari halaman masuk.
                </div>
                <div style="margin-top:20px;">
                    <a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'applicant-login' ) ) ?>">Kembali ke Halaman Masuk</a>
                </div>
            <?php else : ?>
                <p>Masukkan kata sandi baru untuk akun pelamar Anda (minimal 8 karakter).</p>
                <form method="post" action="<?= esc_url( recruitment_get_public_url( 'applicant-reset-password' ) ) ?>">
                    <?php wp_nonce_field( 'daw_applicant_password_reset' ); ?>
                    <input type="hidden" name="applicant_auth_action" value="execute_password_reset">
                    <input type="hidden" name="token" value="<?= esc_attr( $token ) ?>">

                    <label>
                        Kata sandi baru
                        <input type="password" name="new_password" minlength="8" autocomplete="new-password" required placeholder="Minimal 8 karakter">
                    </label>

                    <label>
                        Konfirmasi kata sandi baru
                        <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required placeholder="Ulangi kata sandi baru">
                    </label>

                    <button class="daw-recruitment__button" type="submit" style="margin-top:8px;">Simpan Kata Sandi Baru</button>
                </form>
                <a class="daw-account-auth__back" href="<?= esc_url( recruitment_get_public_url( 'applicant-login' ) ) ?>">&larr; Batal dan kembali ke halaman masuk</a>
            <?php endif; ?>
        </section>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
