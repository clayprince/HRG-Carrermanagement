<?php
$errors = [
    'invalid_request' => 'Sesi tidak valid. Muat ulang halaman lalu coba kembali.',
    'invalid_registration' => 'Masukkan nama lengkap dan alamat email yang valid.',
    'email_exists' => 'Email ini sudah terdaftar. Silakan masuk ke akun Anda.',
    'password_mismatch' => 'Kata sandi harus minimal 10 karakter dan kedua isian harus sama.',
    'registration_failed' => 'Akun belum dapat dibuat. Silakan coba kembali.',
    'account_unavailable' => 'Pembuatan akun Oracle dinonaktifkan karena skema ERD saat ini belum menyediakan penyimpanan akun yang aman.',
];
$error = $errors[ sanitize_key( wp_unslash( $_GET['account_error'] ?? '' ) ) ] ?? '';
$preview = recruitment_is_ui_preview();
?>
<div class="daw-recruitment daw-account-auth">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account-auth__main daw-account-auth__main--center">
        <section class="daw-account-auth__card">
            <span class="daw-recruitment__eyebrow">AKUN PELAMAR</span><h2>Pembuatan akun belum tersedia</h2><p>Struktur ERD Oracle saat ini belum menyediakan penyimpanan akun pelamar yang aman.</p>
            <?php if ( $preview ) : ?><div class="daw-account__preview-note">Mode preview: pengiriman formulir dinonaktifkan.</div><?php endif; ?>
            <?php if ( $error ) : ?><div class="daw-account__alert" role="alert"><?= esc_html( $error ) ?></div><?php endif; ?>
            <div class="daw-account__alert" role="note">Anda tetap dapat mengisi dan mengirim lamaran tanpa membuat akun.</div>
            <a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Lihat lowongan</a>
        </section>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
