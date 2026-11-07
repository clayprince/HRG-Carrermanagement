<?php
$errors = [
    'invalid_request' => 'Sesi tidak valid. Muat ulang halaman lalu coba kembali.',
    'invalid_credentials' => 'Email atau kata sandi tidak sesuai.',
    'applicant_account_required' => 'Gunakan akun pelamar. Akun HR tidak dapat digunakan untuk portal pelamar.',
    'account_unavailable' => 'Layanan akun belum tersedia. Silakan coba kembali nanti.',
    'account_inactive' => 'Akun ini sedang nonaktif. Silakan hubungi tim HR.',
    'login_throttled' => 'Terlalu banyak percobaan masuk. Silakan coba kembali dalam 15 menit.',
];
$error = $errors[ sanitize_key( wp_unslash( $_GET['account_error'] ?? '' ) ) ] ?? '';
$preview = recruitment_is_ui_preview();
$redirect_to = isset( $_GET['redirect_to'] ) && is_scalar( $_GET['redirect_to'] )
    ? sanitize_key( wp_unslash( (string) $_GET['redirect_to'] ) )
    : '';
$redirect_job_id = isset( $_GET['job_id'] ) && is_scalar( $_GET['job_id'] )
    ? absint( wp_unslash( (string) $_GET['job_id'] ) )
    : 0;
$redirect_args = 'application-form' === $redirect_to && $redirect_job_id
    ? [ 'redirect_to' => $redirect_to, 'job_id' => $redirect_job_id ]
    : ( 'account-profile' === $redirect_to ? [ 'redirect_to' => $redirect_to ] : [] );
?>
<div class="daw-recruitment daw-account-auth">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account-auth__main daw-account-auth__main--center">
        <section class="daw-account-auth__card">
            <span class="daw-recruitment__eyebrow">SELAMAT DATANG KEMBALI</span><h2>Masuk ke akun pelamar</h2><p><?= 'application-form' === $redirect_to ? 'Masuk terlebih dahulu untuk melanjutkan lamaran. Setelah berhasil, Anda akan kembali ke formulir lowongan yang dipilih.' : 'Gunakan email dan kata sandi akun Anda.' ?></p>
            <?php if ( $preview ) : ?><div class="daw-account__preview-note">Mode preview: pengiriman formulir dinonaktifkan.</div><?php endif; ?>
            <?php if ( $error ) : ?><div class="daw-account__alert" role="alert"><?= esc_html( $error ) ?></div><?php endif; ?>
            <form method="post" action="<?= esc_url( recruitment_get_public_url( 'applicant-login', $redirect_args ) ) ?>">
                <?php wp_nonce_field( 'applicant_account_applicant-login', 'applicant_account_nonce' ); ?>
                <?php if ( $redirect_args ) : ?><input type="hidden" name="redirect_to" value="<?= esc_attr( $redirect_to ) ?>"><?php if ( $redirect_job_id ) : ?><input type="hidden" name="job_id" value="<?= esc_attr( $redirect_job_id ) ?>"><?php endif; ?><?php endif; ?>
                <?php if ( $preview ) : ?><input type="hidden" name="daw_ui_preview" value="1"><?php endif; ?>
                <label>Email<input type="email" name="email" autocomplete="username" required></label>
                <label>Kata sandi<input type="password" name="password" autocomplete="current-password" required></label>
                <label class="daw-account-auth__remember"><input type="checkbox" name="remember" value="1"> Tetap masuk</label>
                <button class="daw-recruitment__button" type="submit" <?= $preview ? 'disabled' : '' ?>>Masuk</button>
            </form>
            <p class="daw-account-auth__switch">Belum punya akun? <a href="<?= esc_url( recruitment_get_public_url( 'applicant-register', $redirect_args ) ) ?>">Buat akun</a></p>
            <a class="daw-account-auth__back" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">&larr; Kembali ke lowongan</a>
        </section>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
