<?php
$errors = [
    'invalid_request' => 'Sesi tidak valid. Muat ulang halaman lalu coba kembali.',
    'invalid_credentials' => 'Email atau kata sandi tidak sesuai.',
    'applicant_account_required' => 'Gunakan akun pelamar. Akun HR tidak dapat digunakan untuk portal pelamar.',
    'account_unavailable' => 'Login akun Oracle belum tersedia untuk skema ERD saat ini. Lamaran dapat dikirim tanpa membuat akun.',
];
$error = $errors[ sanitize_key( wp_unslash( $_GET['account_error'] ?? '' ) ) ] ?? '';
$preview = recruitment_is_ui_preview();
?>
<div class="daw-recruitment daw-account-auth">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account-auth__main daw-account-auth__main--center">
        <section class="daw-account-auth__card">
            <span class="daw-recruitment__eyebrow">AKUN PELAMAR</span><h2>Login belum tersedia</h2><p>Struktur ERD Oracle saat ini belum menyediakan penyimpanan akun pelamar yang aman.</p>
            <?php if ( $preview ) : ?><div class="daw-account__preview-note">Mode preview: pengiriman formulir dinonaktifkan.</div><?php endif; ?>
            <?php if ( $error ) : ?><div class="daw-account__alert" role="alert"><?= esc_html( $error ) ?></div><?php endif; ?>
            <div class="daw-account__alert" role="note">Anda tetap dapat mengisi dan mengirim lamaran tanpa login.</div>
            <a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Lihat lowongan</a>
            <a class="daw-account-auth__back" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">&larr; Kembali ke lowongan</a>
        </section>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
