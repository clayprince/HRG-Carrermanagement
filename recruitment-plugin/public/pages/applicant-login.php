<?php
$errors = [
    'invalid_request' => 'Sesi tidak valid. Muat ulang halaman lalu coba kembali.',
    'invalid_credentials' => 'Email atau kata sandi tidak sesuai.',
    'applicant_account_required' => 'Gunakan akun pelamar. Akun HR tidak dapat digunakan untuk portal pelamar.',
    'account_unavailable' => 'Layanan akun belum tersedia. Silakan coba kembali nanti.',
];
$error = $errors[ sanitize_key( wp_unslash( $_GET['account_error'] ?? '' ) ) ] ?? '';
$preview = recruitment_is_ui_preview();
?>
<div class="daw-recruitment daw-account-auth">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account-auth__main daw-account-auth__main--center">
        <section class="daw-account-auth__card">
            <span class="daw-recruitment__eyebrow">SELAMAT DATANG KEMBALI</span><h2>Masuk ke akun pelamar</h2><p>Gunakan email dan kata sandi akun Anda.</p>
            <?php if ( $preview ) : ?><div class="daw-account__preview-note">Mode preview: pengiriman formulir dinonaktifkan.</div><?php endif; ?>
            <?php if ( $error ) : ?><div class="daw-account__alert" role="alert"><?= esc_html( $error ) ?></div><?php endif; ?>
            <form method="post" action="<?= esc_url( recruitment_get_public_url( 'applicant-login' ) ) ?>">
                <?php wp_nonce_field( 'applicant_account_applicant-login', 'applicant_account_nonce' ); ?>
                <?php if ( $preview ) : ?><input type="hidden" name="daw_ui_preview" value="1"><?php endif; ?>
                <label>Email<input type="email" name="email" autocomplete="username" required></label>
                <label>Kata sandi<input type="password" name="password" autocomplete="current-password" required></label>
                <label class="daw-account-auth__remember"><input type="checkbox" name="remember" value="1"> Tetap masuk</label>
                <button class="daw-recruitment__button" type="submit" <?= $preview ? 'disabled' : '' ?>>Masuk</button>
            </form>
            <p class="daw-account-auth__switch">Belum punya akun? <a href="<?= esc_url( recruitment_get_public_url( 'applicant-register' ) ) ?>">Buat akun</a></p>
            <a class="daw-account-auth__back" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">&larr; Kembali ke lowongan</a>
        </section>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
