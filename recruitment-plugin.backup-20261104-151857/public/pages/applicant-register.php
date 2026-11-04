<?php
$errors = [
    'invalid_request' => 'Sesi tidak valid. Muat ulang halaman lalu coba kembali.',
    'invalid_registration' => 'Masukkan nama lengkap dan alamat email yang valid.',
    'email_exists' => 'Email ini sudah terdaftar. Silakan masuk ke akun Anda.',
    'password_mismatch' => 'Kata sandi harus minimal 10 karakter dan kedua isian harus sama.',
    'registration_failed' => 'Akun belum dapat dibuat. Silakan coba kembali.',
];
$error = $errors[ sanitize_key( wp_unslash( $_GET['account_error'] ?? '' ) ) ] ?? '';
$preview = recruitment_is_ui_preview();
?>
<div class="daw-recruitment daw-account-auth">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account-auth__main daw-account-auth__main--center">
        <section class="daw-account-auth__card">
            <span class="daw-recruitment__eyebrow">MEMULAI</span><h2>Buat akun pelamar</h2><p>Lengkapi informasi berikut untuk mendaftar.</p>
            <?php if ( $preview ) : ?><div class="daw-account__preview-note">Mode preview: pengiriman formulir dinonaktifkan.</div><?php endif; ?>
            <?php if ( $error ) : ?><div class="daw-account__alert" role="alert"><?= esc_html( $error ) ?></div><?php endif; ?>
            <form method="post" action="<?= esc_url( recruitment_get_public_url( 'applicant-register' ) ) ?>">
                <?php wp_nonce_field( 'applicant_account_applicant-register', 'applicant_account_nonce' ); ?>
                <?php if ( $preview ) : ?><input type="hidden" name="daw_ui_preview" value="1"><?php endif; ?>
                <label>Nama lengkap<input type="text" name="full_name" autocomplete="name" required></label>
                <label>Email<input type="email" name="email" autocomplete="email" required></label>
                <label>Kata sandi<input type="password" name="password" autocomplete="new-password" minlength="10" required><small>Gunakan minimal 10 karakter.</small></label>
                <label>Konfirmasi kata sandi<input type="password" name="password_confirmation" autocomplete="new-password" minlength="10" required></label>
                <button class="daw-recruitment__button" type="submit" <?= $preview ? 'disabled' : '' ?>>Buat Akun</button>
            </form>
            <p class="daw-account-auth__switch">Sudah punya akun? <a href="<?= esc_url( recruitment_get_public_url( 'applicant-login' ) ) ?>">Masuk</a></p>
        </section>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
