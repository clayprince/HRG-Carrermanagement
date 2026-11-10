<?php
$token = trim( sanitize_text_field( wp_unslash( $_GET['token'] ?? ( $_POST['token'] ?? '' ) ) ) );
$token_checked = '' !== $token;
$token_is_valid = false;
$account_id = null;

if ( $token_checked && 43 === strlen( $token ) ) {
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
    'invalid_token'       => 'Token reset tidak valid atau telah kedaluwarsa. Periksa kembali token dari email Anda.',
    'invalid_password'    => 'Kata sandi minimal 8 karakter dan harus sama dengan konfirmasi kata sandi.',
    'invalid_request'     => 'Sesi form tidak valid. Silakan coba kembali.',
    'service_unavailable' => 'Layanan database sedang tidak tersedia. Silakan coba beberapa saat lagi.',
];
?>
<div class="daw-recruitment daw-account-auth">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account-auth__main daw-account-auth__main--center">
        <section class="daw-account-auth__card" style="max-width:500px;width:100%;box-sizing:border-box;">
            <span class="daw-recruitment__eyebrow" style="color:#c8102e;font-weight:700;letter-spacing:0.06em;">KEAMANAN AKUN</span>
            <h2 style="font-size:24px;font-weight:700;margin:8px 0 10px;color:#0f172a;">Atur Ulang Kata Sandi Pelamar</h2>

            <?php if ( isset( $notice_messages[ $notice ] ) ) : ?>
                <div class="daw-account__alert" role="alert" style="margin-bottom:16px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 14px;border-radius:10px;font-size:13.5px;line-height:1.45;">
                    <?= esc_html( $notice_messages[ $notice ] ) ?>
                </div>
            <?php endif; ?>

            <?php if ( $token_checked && ! $token_is_valid ) : ?>
                <div class="daw-account__alert" role="alert" style="margin-bottom:16px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 14px;border-radius:10px;font-size:13.5px;line-height:1.45;">
                    Token reset tidak valid atau telah kedaluwarsa (berlaku 30 menit). Pastikan Anda menyalin seluruh token 43 karakter dari email, atau ajukan permintaan baru.
                </div>
            <?php endif; ?>

            <p style="margin-bottom:22px;font-size:14px;color:#64748b;line-height:1.5;">
                Masukkan kode token verifikasi dari email Anda dan buat kata sandi baru (minimal 8 karakter).
            </p>

            <form method="post" action="<?= esc_url( recruitment_get_public_url( 'applicant-reset-password' ) ) ?>" style="display:flex;flex-direction:column;gap:18px;">
                <?php wp_nonce_field( 'daw_applicant_password_reset' ); ?>
                <input type="hidden" name="applicant_auth_action" value="execute_password_reset">

                <?php if ( $token_is_valid ) : ?>
                    <input type="hidden" name="token" value="<?= esc_attr( $token ) ?>">
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:12px 16px;border-radius:12px;font-size:13.5px;display:flex;align-items:center;gap:10px;">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="20" height="20" style="flex-shrink:0;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                        <div>
                            <strong>Token Terverifikasi:</strong> <code style="font-family:ui-monospace, monospace;background:rgba(0,0,0,0.06);padding:2px 6px;border-radius:4px;"><?= esc_html( substr( $token, 0, 8 ) . '...' . substr( $token, -6 ) ) ?></code>
                        </div>
                    </div>
                <?php else : ?>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        <label for="applicant-token" style="font-size:13.5px;font-weight:600;color:#334155;">
                            Token / Kode Verifikasi Reset <span style="color:#c8102e;">*</span>
                        </label>
                        <input id="applicant-token" type="text" name="token" value="<?= esc_attr( $token ) ?>" placeholder="Tempel token 43 karakter dari email Anda" required style="padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;font-size:13.5px;font-family:ui-monospace, monospace;width:100%;box-sizing:border-box;">
                        <span style="font-size:12px;color:#64748b;">Salin seluruh 43 karakter token yang dikirimkan ke email Anda.</span>
                    </div>
                <?php endif; ?>

                <div style="display:flex;flex-direction:column;gap:6px;">
                    <label for="applicant-new-password" style="font-size:13.5px;font-weight:600;color:#334155;">
                        Kata Sandi Baru <span style="color:#c8102e;">*</span>
                    </label>
                    <div style="position:relative;display:flex;align-items:center;">
                        <input id="applicant-new-password" type="password" name="new_password" minlength="8" autocomplete="new-password" required placeholder="Minimal 8 karakter" style="padding:12px 42px 12px 14px;border:1px solid #cbd5e1;border-radius:10px;font-size:14.5px;width:100%;box-sizing:border-box;">
                        <button type="button" class="daw-applicant-pwd-toggle" data-target="applicant-new-password" style="position:absolute;right:8px;background:none;border:none;color:#94a3b8;cursor:pointer;padding:6px;display:flex;align-items:center;">
                            <svg class="daw-show-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="daw-hide-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        </button>
                    </div>
                    <span style="font-size:12px;color:#64748b;">Gunakan minimal 8 karakter.</span>
                </div>

                <div style="display:flex;flex-direction:column;gap:6px;">
                    <label for="applicant-confirm-password" style="font-size:13.5px;font-weight:600;color:#334155;">
                        Konfirmasi Kata Sandi Baru <span style="color:#c8102e;">*</span>
                    </label>
                    <div style="position:relative;display:flex;align-items:center;">
                        <input id="applicant-confirm-password" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required placeholder="Ulangi kata sandi baru" style="padding:12px 42px 12px 14px;border:1px solid #cbd5e1;border-radius:10px;font-size:14.5px;width:100%;box-sizing:border-box;">
                        <button type="button" class="daw-applicant-pwd-toggle" data-target="applicant-confirm-password" style="position:absolute;right:8px;background:none;border:none;color:#94a3b8;cursor:pointer;padding:6px;display:flex;align-items:center;">
                            <svg class="daw-show-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="daw-hide-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        </button>
                    </div>
                    <div id="applicant-pwd-match-badge" style="display:none;font-size:12.5px;font-weight:600;margin-top:4px;"></div>
                </div>

                <button class="daw-recruitment__button" type="submit" style="width:100%;min-height:48px;border-radius:10px;font-size:15px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;background:#c8102e;color:#fff;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(200,16,46,0.28);margin-top:6px;">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                    <span>Simpan Kata Sandi Baru</span>
                </button>
            </form>

            <a class="daw-account-auth__back" href="<?= esc_url( recruitment_get_public_url( 'applicant-login' ) ) ?>" style="margin-top:24px;display:inline-flex;align-items:center;gap:6px;color:#c8102e;text-decoration:none;font-size:13.5px;font-weight:600;">
                <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" /></svg>
                <span>Batal dan kembali ke halaman masuk</span>
            </a>
        </section>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.daw-applicant-pwd-toggle').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var targetId = this.getAttribute('data-target');
            var input = document.getElementById(targetId);
            if (!input) return;
            var isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            var showEye = this.querySelector('.daw-show-eye');
            var hideEye = this.querySelector('.daw-hide-eye');
            if (showEye && hideEye) {
                showEye.style.display = isPass ? 'none' : 'block';
                hideEye.style.display = isPass ? 'block' : 'none';
            }
        });
    });

    var p1 = document.getElementById('applicant-new-password');
    var p2 = document.getElementById('applicant-confirm-password');
    var b = document.getElementById('applicant-pwd-match-badge');

    if (p1 && p2 && b) {
        function checkAppPwdMatch() {
            if (p2.value.length === 0) {
                b.style.display = 'none';
                p2.setCustomValidity('');
                return;
            }
            if (p1.value === p2.value) {
                b.style.display = 'block';
                b.style.color = '#16a34a';
                b.innerHTML = '&#10003; Konfirmasi kata sandi cocok';
                p2.setCustomValidity('');
            } else {
                b.style.display = 'block';
                b.style.color = '#dc2626';
                b.innerHTML = '&#10007; Konfirmasi kata sandi belum cocok';
                p2.setCustomValidity('Konfirmasi kata sandi tidak cocok');
            }
        }
        p1.addEventListener('input', checkAppPwdMatch);
        p2.addEventListener('input', checkAppPwdMatch);
    }
});
</script>