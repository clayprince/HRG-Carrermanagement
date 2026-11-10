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

$forgot_notice = sanitize_key( wp_unslash( $_GET['forgot_notice'] ?? '' ) );
$reset_notice  = sanitize_key( wp_unslash( $_GET['reset_notice'] ?? '' ) );
$forgot_messages = [
    'submitted'       => 'Jika email terdaftar sebagai akun pelamar aktif, tautan untuk mengatur ulang kata sandi telah dikirim ke email Anda. Silakan periksa kotak masuk atau spam.',
    'invalid_request' => 'Permintaan reset tidak valid. Silakan coba kembali.',
    'throttled'       => 'Terlalu banyak permintaan. Silakan tunggu beberapa saat sebelum mencoba kembali.',
    'email_failed'    => 'Pengiriman email gagal. Pastikan konfigurasi SMTP sistem telah aktif atau hubungi HR.',
];
$reset_messages = [
    'password_updated' => 'Kata sandi berhasil diatur ulang! Silakan masuk dengan kata sandi baru Anda.',
    'invalid_token'    => 'Tautan reset kata sandi tidak valid atau telah kedaluwarsa. Silakan ajukan permintaan kembali.',
    'invalid_password' => 'Kata sandi baru minimal 8 karakter dan harus sama dengan konfirmasi.',
];

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
            <span class="daw-recruitment__eyebrow">SELAMAT DATANG KEMBALI</span>
            <h2>Masuk ke akun pelamar</h2>
            <p><?= 'application-form' === $redirect_to ? 'Masuk terlebih dahulu untuk melanjutkan lamaran. Setelah berhasil, Anda akan kembali ke formulir lowongan yang dipilih.' : 'Gunakan email dan kata sandi akun Anda.' ?></p>

            <?php if ( $preview ) : ?><div class="daw-account__preview-note">Mode preview: pengiriman formulir dinonaktifkan.</div><?php endif; ?>
            <?php if ( $error ) : ?><div class="daw-account__alert" role="alert"><?= esc_html( $error ) ?></div><?php endif; ?>
            <?php if ( isset( $forgot_messages[ $forgot_notice ] ) ) : ?>
                <div class="daw-account__alert <?= 'submitted' === $forgot_notice ? 'daw-account__alert--success' : '' ?>" style="<?= 'submitted' === $forgot_notice ? 'background:#ecfdf5;border-color:#a7f3d0;color:#065f46;' : '' ?>" role="alert">
                    <?= esc_html( $forgot_messages[ $forgot_notice ] ) ?>
                </div>
            <?php endif; ?>
            <?php if ( isset( $reset_messages[ $reset_notice ] ) ) : ?>
                <div class="daw-account__alert <?= 'password_updated' === $reset_notice ? 'daw-account__alert--success' : '' ?>" style="<?= 'password_updated' === $reset_notice ? 'background:#ecfdf5;border-color:#a7f3d0;color:#065f46;' : '' ?>" role="alert">
                    <?= esc_html( $reset_messages[ $reset_notice ] ) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= esc_url( recruitment_get_public_url( 'applicant-login', $redirect_args ) ) ?>">
                <?php wp_nonce_field( 'applicant_account_applicant-login', 'applicant_account_nonce' ); ?>
                <input type="hidden" name="applicant_auth_action" value="login">
                <?php if ( $redirect_args ) : ?>
                    <input type="hidden" name="redirect_to" value="<?= esc_attr( $redirect_to ) ?>">
                    <?php if ( $redirect_job_id ) : ?><input type="hidden" name="job_id" value="<?= esc_attr( $redirect_job_id ) ?>"><?php endif; ?>
                <?php endif; ?>
                <?php if ( $preview ) : ?><input type="hidden" name="daw_ui_preview" value="1"><?php endif; ?>
                <label>Email<input type="email" name="email" autocomplete="username" required></label>
                <label>Kata sandi<input type="password" name="password" autocomplete="current-password" required></label>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:-4px;margin-bottom:8px;">
                    <label class="daw-account-auth__remember" style="margin:0;"><input type="checkbox" name="remember" value="1"> Tetap masuk</label>
                    <a href="#daw-forgot-modal" id="daw-open-forgot-modal" class="daw-account-auth__lost-password" style="margin:0;cursor:pointer;">Lupa kata sandi?</a>
                </div>
                <button class="daw-recruitment__button" type="submit" <?= $preview ? 'disabled' : '' ?>>Masuk</button>
            </form>

            <p class="daw-account-auth__switch">Belum punya akun? <a href="<?= esc_url( recruitment_get_public_url( 'applicant-register', $redirect_args ) ) ?>">Buat akun</a></p>
            <a class="daw-account-auth__back" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">&larr; Kembali ke lowongan</a>
        </section>

        <!-- Modal / Dialog Lupa Kata Sandi Pelamar -->
        <dialog id="daw-forgot-dialog" style="border:none;border-radius:12px;box-shadow:0 12px 36px rgba(0,0,0,0.25);padding:0;max-width:440px;width:90%;">
            <div style="padding:28px 24px;background:#fff;position:relative;">
                <button type="button" id="daw-close-forgot-modal" aria-label="Tutup" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:24px;cursor:pointer;color:#6b7280;line-height:1;">&times;</button>
                <span class="daw-recruitment__eyebrow" style="color:#c8102e;font-size:11px;font-weight:700;display:block;margin-bottom:6px;">BANTUAN MASUK</span>
                <h3 style="margin:0 0 8px;font-size:20px;color:#111827;">Lupa Kata Sandi?</h3>
                <p style="margin:0 0 20px;color:#4b5563;font-size:13px;line-height:1.5;">Masukkan email yang terdaftar pada akun pelamar Anda. Kami akan mengirimkan tautan untuk mengatur ulang kata sandi.</p>
                <form method="post" action="<?= esc_url( recruitment_get_public_url( 'applicant-login', $redirect_args ) ) ?>">
                    <?php wp_nonce_field( 'daw_applicant_password_reset_request' ); ?>
                    <input type="hidden" name="applicant_auth_action" value="request_password_reset">
                    <label style="display:grid;gap:6px;font-size:13px;font-weight:600;margin-bottom:16px;">
                        Email Terdaftar
                        <input type="email" name="reset_email" autocomplete="email" placeholder="nama@email.com" required style="padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                    </label>
                    <button class="daw-recruitment__button" type="submit" style="width:100%;min-height:44px;">Kirim Tautan Reset</button>
                </form>
            </div>
        </dialog>
        <script>
            (function() {
                var openBtn = document.getElementById('daw-open-forgot-modal');
                var dialog = document.getElementById('daw-forgot-dialog');
                var closeBtn = document.getElementById('daw-close-forgot-modal');
                if (openBtn && dialog) {
                    openBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (typeof dialog.showModal === 'function') {
                            dialog.showModal();
                        } else {
                            dialog.setAttribute('open', '');
                        }
                    });
                }
                if (closeBtn && dialog) {
                    closeBtn.addEventListener('click', function() {
                        if (typeof dialog.close === 'function') {
                            dialog.close();
                        } else {
                            dialog.removeAttribute('open');
                        }
                    });
                }
            })();
        </script>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
