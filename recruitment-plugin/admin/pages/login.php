<?php
$target = 'user_department' === ( $target ?? '' ) ? 'user_department' : 'hr';
$mode = 'reset-password' === ( $page ?? '' ) ? 'reset-password' : ( 'forgot-password' === ( $page ?? '' ) ? 'forgot-password' : 'login' );
$is_department_login = 'user_department' === $target;
$public_url = recruitment_get_public_url( 'careers' );
$forgot_hr_url = recruitment_get_public_url( 'forgot-password', [ 'target' => 'hr', 'forgot' => '1' ] );
$forgot_department_url = recruitment_get_department_login_url(
    [ 'recruitment_page' => 'forgot-password', 'target' => 'user_department', 'forgot' => '1' ]
);
$token = trim( sanitize_text_field( wp_unslash( $_GET['token'] ?? ( $_POST['token'] ?? '' ) ) ) );
$error = (string) ( $error ?? '' );
$forgot_notice = (string) ( $forgot_notice ?? '' );
$reset_notice = (string) ( $reset_notice ?? '' );
$open_forgot = '1' === sanitize_key( wp_unslash( $_GET['forgot'] ?? '' ) );

// Check token status for reset-password
$token_checked = '' !== $token;
$token_is_valid = false;
if ( 'reset-password' === $mode && $token_checked && 43 === strlen( $token ) ) {
    $token_hash = hash( 'sha256', $token );
    $account_id = get_transient( 'daw_hr_reset_' . $token_hash );
    if ( false !== $account_id ) {
        $account_token_hash = (string) get_transient( 'daw_hr_reset_account_' . (string) $account_id );
        if ( '' !== $account_token_hash && hash_equals( $account_token_hash, $token_hash ) ) {
            $token_is_valid = true;
        }
    }
}
?>
<style>
/* DAW Authentication & Reset Password Modern Styles */
body:has(.daw-auth-page),
body:has(.daw-admin-login) {
    background: #f8fafc !important;
    margin: 0 !important;
    padding: 0 !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
    color: #1e293b;
    min-height: 100vh;
}
body:has(.daw-auth-page) #wpadminbar,
body:has(.daw-auth-page) .wp-site-blocks > header,
body:has(.daw-auth-page) .wp-site-blocks > footer,
body:has(.daw-admin-login) #wpadminbar,
body:has(.daw-admin-login) .wp-site-blocks > header,
body:has(.daw-admin-login) .wp-site-blocks > footer {
    display: none !important;
}
body:has(.daw-auth-page) main,
body:has(.daw-admin-login) main {
    margin: 0 !important;
    max-width: none !important;
    padding: 0 !important;
}

.daw-auth-wrapper {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    box-sizing: border-box;
    background: radial-gradient(circle at 50% 0%, #ffffff 0%, #f1f5f9 100%);
}
.daw-auth-brand {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 28px;
    text-decoration: none;
}
.daw-auth-logo {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #e11d48 0%, #c8102e 100%);
    color: #ffffff;
    font-size: 22px;
    font-weight: 800;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 16px rgba(200, 16, 46, 0.25);
    letter-spacing: -0.5px;
    flex-shrink: 0;
}
.daw-auth-brand-info {
    text-align: left;
}
.daw-auth-brand-name {
    display: block;
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: 0.04em;
    line-height: 1.2;
}
.daw-auth-brand-sub {
    display: block;
    font-size: 12px;
    font-weight: 500;
    color: #64748b;
    letter-spacing: 0.02em;
}

.daw-auth-card {
    width: 100%;
    max-width: 480px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.06), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
    padding: 36px 32px;
    box-sizing: border-box;
    text-align: left;
}
@media (max-width: 520px) {
    .daw-auth-card {
        padding: 28px 20px;
        border-radius: 16px;
    }
}

.daw-auth-eyebrow {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    background: #fef2f2;
    color: #c8102e;
    border: 1px solid #fee2e2;
    margin-bottom: 12px;
}
.daw-auth-title {
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 8px;
    line-height: 1.25;
}
.daw-auth-desc {
    font-size: 14px;
    color: #64748b;
    line-height: 1.5;
    margin: 0 0 24px;
}

/* Alerts */
.daw-auth-alert {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 13.5px;
    line-height: 1.45;
    margin-bottom: 20px;
}
.daw-auth-alert--error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}
.daw-auth-alert--success {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
}
.daw-auth-alert-icon {
    flex-shrink: 0;
    margin-top: 1px;
}

/* Token Status Box */
.daw-token-status {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
}
.daw-token-status--valid {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
}
.daw-token-status--invalid {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}
.daw-token-status-content strong {
    display: block;
    font-size: 13.5px;
    margin-bottom: 2px;
}
.daw-token-status-content p {
    margin: 0;
    font-size: 13px;
    opacity: 0.9;
}
.daw-token-code-badge {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    background: rgba(0, 0, 0, 0.06);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
    letter-spacing: 0.02em;
}

/* Form controls */
.daw-auth-form {
    display: flex;
    flex-direction: column;
    gap: 18px;
}
.daw-form-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.daw-form-label {
    font-size: 13.5px;
    font-weight: 600;
    color: #334155;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.daw-form-label .daw-req {
    color: #c8102e;
    margin-left: 2px;
}
.daw-input-group {
    position: relative;
    display: flex;
    align-items: center;
}
.daw-input-group input[type="text"],
.daw-input-group input[type="password"],
.daw-input-group input[type="email"],
.daw-input-group select {
    width: 100%;
    height: 48px;
    padding: 0 14px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    font-size: 14.5px;
    color: #0f172a;
    background: #ffffff;
    box-sizing: border-box;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.daw-input-group input:focus,
.daw-input-group select:focus {
    border-color: #c8102e;
    box-shadow: 0 0 0 3px rgba(200, 16, 46, 0.12);
    outline: none;
}
.daw-input-group input.daw-input--mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 13.5px;
    letter-spacing: 0.03em;
}
.daw-input-group--has-toggle input {
    padding-right: 44px;
}
.daw-pwd-toggle-btn {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    padding: 6px;
    color: #94a3b8;
    cursor: pointer;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.daw-pwd-toggle-btn:hover {
    color: #475569;
}
.daw-field-hint {
    font-size: 12px;
    color: #64748b;
    line-height: 1.4;
}

/* Match indicator */
.daw-pwd-match-indicator {
    font-size: 12.5px;
    font-weight: 600;
    margin-top: 4px;
    display: none;
}
.daw-pwd-match-indicator.is-matched {
    color: #16a34a;
    display: block;
}
.daw-pwd-match-indicator.is-mismatched {
    color: #dc2626;
    display: block;
}

/* Submit button */
.daw-auth-submit-btn {
    width: 100%;
    height: 48px;
    background: #c8102e;
    color: #ffffff;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 4px 14px rgba(200, 16, 46, 0.28);
    transition: background 0.15s ease, transform 0.1s ease;
    margin-top: 6px;
}
.daw-auth-submit-btn:hover {
    background: #a90d27;
}
.daw-auth-submit-btn:active {
    transform: translateY(1px);
}

/* Footer & Navigation */
.daw-auth-footer-nav {
    margin-top: 24px;
    text-align: center;
}
.daw-auth-back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #c8102e;
    font-size: 13.5px;
    font-weight: 600;
    text-decoration: none;
    transition: color 0.15s ease;
}
.daw-auth-back-link:hover {
    color: #9e0c24;
    text-decoration: underline;
}
.daw-auth-divider {
    height: 1px;
    background: #e2e8f0;
    margin: 20px 0 16px;
}
.daw-auth-meta-links {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    font-size: 12.5px;
    color: #94a3b8;
}
.daw-auth-meta-links a {
    color: #64748b;
    text-decoration: none;
}
.daw-auth-meta-links a:hover {
    color: #0f172a;
    text-decoration: underline;
}

/* Modal for login */
.daw-auth-modal {
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 20px 30px rgba(15, 23, 42, 0.2);
    color: #0f172a;
    max-width: min(440px, calc(100vw - 32px));
    padding: 28px 24px;
    position: fixed;
    width: 100%;
    box-sizing: border-box;
}
.daw-auth-modal::backdrop {
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(2px);
}
.daw-auth-modal-close {
    position: absolute;
    right: 14px;
    top: 14px;
    background: transparent;
    border: none;
    font-size: 24px;
    line-height: 1;
    color: #94a3b8;
    cursor: pointer;
    padding: 4px;
}
.daw-auth-modal-close:hover {
    color: #0f172a;
}
</style>

<main class="daw-auth-page daw-admin-login daw-auth-wrapper">
    <a href="<?= esc_url( $public_url ) ?>" class="daw-auth-brand" title="Portal Rekrutmen DAW">
        <div class="daw-auth-logo">D</div>
        <div class="daw-auth-brand-info">
            <span class="daw-auth-brand-name">DAW RECRUITMENT</span>
            <span class="daw-auth-brand-sub"><?= 'reset-password' === $mode ? 'Atur Ulang Password HR' : 'Portal Administrasi' ?></span>
        </div>
    </a>

    <section class="daw-auth-card" aria-labelledby="daw-auth-title">
        <?php if ( 'login' === $mode ) : ?>
            <span class="daw-auth-eyebrow">SELAMAT DATANG KEMBALI</span>
            <h1 id="daw-auth-title" class="daw-auth-title">Masuk Portal DAW</h1>
            <p class="daw-auth-desc">Silakan pilih peran dan masukkan kredensial akun Anda untuk mengakses sistem.</p>

            <?php if ( '' !== $error ) : ?>
                <div class="daw-auth-alert daw-auth-alert--error" role="alert">
                    <svg class="daw-auth-alert-icon" viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                    <div><?= esc_html( $error ) ?></div>
                </div>
            <?php endif; ?>

            <?php if ( 'password_updated' === $reset_notice ) : ?>
                <div class="daw-auth-alert daw-auth-alert--success" role="status">
                    <svg class="daw-auth-alert-icon" viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                    <div>Password berhasil diubah! Silakan masuk dengan password baru Anda.</div>
                </div>
            <?php elseif ( 'service_unavailable' === $reset_notice ) : ?>
                <div class="daw-auth-alert daw-auth-alert--error" role="alert">
                    <svg class="daw-auth-alert-icon" viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                    <div>Password belum dapat diperbarui. Silakan coba kembali beberapa saat lagi.</div>
                </div>
            <?php elseif ( in_array( $reset_notice, [ 'invalid_token', 'invalid_password', 'invalid_request' ], true ) ) : ?>
                <div class="daw-auth-alert daw-auth-alert--error" role="alert">
                    <svg class="daw-auth-alert-icon" viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                    <div>Tautan atau token reset tidak valid atau kedaluwarsa. Silakan ajukan permintaan reset kembali.</div>
                </div>
            <?php endif; ?>

            <form class="daw-auth-form" method="post" action="<?= esc_url( add_query_arg( [ 'recruitment_page' => 'login', 'target' => $target ], get_permalink() ) ) ?>">
                <?php wp_nonce_field( 'daw_admin_portal_login' ); ?>

                <div class="daw-form-field">
                    <label class="daw-form-label" for="daw-admin-role">Peran Akses <span class="daw-req">*</span></label>
                    <div class="daw-input-group">
                        <select id="daw-admin-role" name="target" data-portal-role data-forgot-hr="<?= esc_url( $forgot_hr_url ) ?>" data-forgot-department="<?= esc_url( $forgot_department_url ) ?>">
                            <option value="hr" <?= selected( $target, 'hr', false ) ?>>Admin HR</option>
                            <option value="user_department" <?= selected( $target, 'user_department', false ) ?>>Admin Kepala Departemen</option>
                        </select>
                    </div>
                </div>

                <div class="daw-form-field">
                    <label class="daw-form-label" for="daw-admin-username">Email atau Username <span class="daw-req">*</span></label>
                    <div class="daw-input-group">
                        <input id="daw-admin-username" name="username" type="text" autocomplete="username" placeholder="Masukkan username atau email" required>
                    </div>
                </div>

                <div class="daw-form-field">
                    <label class="daw-form-label" for="daw-admin-password">Kata Sandi <span class="daw-req">*</span></label>
                    <div class="daw-input-group daw-input-group--has-toggle">
                        <input id="daw-admin-password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan kata sandi" required>
                        <button type="button" class="daw-pwd-toggle-btn" data-target="daw-admin-password" title="Tampilkan kata sandi">
                            <svg class="daw-icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="daw-icon-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="daw-auth-submit-btn">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M3 3a1 1 0 011 1v12a1 1 0 11-2 0V4a1 1 0 011-1zm7.707 3.293a1 1 0 010 1.414L9.414 9H17a1 1 0 110 2H9.414l1.293 1.293a1 1 0 01-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                    <span>Masuk ke Portal</span>
                </button>
            </form>

            <div class="daw-auth-footer-nav">
                <a class="daw-auth-back-link" data-portal-forgot href="<?= esc_url( $is_department_login ? $forgot_department_url : $forgot_hr_url ) ?>" aria-haspopup="dialog">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 10-1-1zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
                    <span>Lupa password akun?</span>
                </a>
                <div class="daw-auth-divider"></div>
                <div class="daw-auth-meta-links">
                    <a href="<?= esc_url( $public_url ) ?>">Portal Pelamar DAW</a>
                    <span>&bull;</span>
                    <a href="<?= esc_url( wp_login_url( admin_url() ) ) ?>">Login via WordPress</a>
                </div>
            </div>

        <?php else : ?>
            <!-- RESET PASSWORD VIEW -->
            <span class="daw-auth-eyebrow">KEAMANAN AKUN</span>
            <h1 id="daw-auth-title" class="daw-auth-title">Atur Ulang Password HR</h1>
            <p class="daw-auth-desc">Gunakan token verifikasi dari email Anda untuk membuat kata sandi baru yang aman.</p>

            <?php if ( 'invalid_token' === $reset_notice ) : ?>
                <div class="daw-auth-alert daw-auth-alert--error" role="alert">
                    <svg class="daw-auth-alert-icon" viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                    <div>Token reset tidak valid atau telah kedaluwarsa. Silakan periksa kembali token 43 karakter dari email Anda atau ajukan permintaan baru.</div>
                </div>
            <?php elseif ( 'invalid_password' === $reset_notice ) : ?>
                <div class="daw-auth-alert daw-auth-alert--error" role="alert">
                    <svg class="daw-auth-alert-icon" viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                    <div>Password minimal 8 karakter dan konfirmasi password harus cocok.</div>
                </div>
            <?php elseif ( 'invalid_request' === $reset_notice ) : ?>
                <div class="daw-auth-alert daw-auth-alert--error" role="alert">
                    <svg class="daw-auth-alert-icon" viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                    <div>Sesi form tidak valid atau sudah kedaluwarsa. Silakan muat ulang halaman.</div>
                </div>
            <?php endif; ?>

            <?php if ( $token_is_valid ) : ?>
                <div class="daw-token-status daw-token-status--valid">
                    <svg class="daw-auth-alert-icon" viewBox="0 0 20 20" fill="currentColor" width="20" height="20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                    <div class="daw-token-status-content">
                        <strong>Token Verifikasi Terhubung</strong>
                        <p>Tautan reset valid untuk akun HR Anda: <code class="daw-token-code-badge"><?= esc_html( substr( $token, 0, 8 ) . '...' . substr( $token, -6 ) ) ?></code></p>
                    </div>
                </div>
            <?php elseif ( $token_checked && ! $token_is_valid ) : ?>
                <div class="daw-token-status daw-token-status--invalid">
                    <svg class="daw-auth-alert-icon" viewBox="0 0 20 20" fill="currentColor" width="20" height="20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                    <div class="daw-token-status-content">
                        <strong>Token Tidak Valid atau Kedaluwarsa</strong>
                        <p>Token dari tautan sudah tidak aktif (berlaku 30 menit). Tempel token terbaru dari email di bawah ini:</p>
                    </div>
                </div>
            <?php endif; ?>

            <form class="daw-auth-form" id="daw-reset-password-form" method="post" action="<?= esc_url( add_query_arg( [ 'recruitment_page' => 'reset-password', 'target' => 'hr' ], get_permalink() ) ) ?>">
                <?php wp_nonce_field( 'daw_hr_password_reset' ); ?>
                <input type="hidden" name="target" value="hr">

                <?php if ( $token_is_valid ) : ?>
                    <input type="hidden" name="token" value="<?= esc_attr( $token ) ?>">
                <?php else : ?>
                    <div class="daw-form-field">
                        <label class="daw-form-label" for="daw-token">
                            Token / Kode Verifikasi Reset <span class="daw-req">*</span>
                        </label>
                        <div class="daw-input-group">
                            <input id="daw-token" name="token" type="text" value="<?= esc_attr( $token ) ?>" placeholder="Tempel token 43 karakter dari email" required class="daw-input--mono" autocomplete="off" autofocus>
                        </div>
                        <span class="daw-field-hint">Salin seluruh karakter token dari email yang Anda terima.</span>
                    </div>
                <?php endif; ?>

                <div class="daw-form-field">
                    <label class="daw-form-label" for="daw-new-password">
                        Password Baru <span class="daw-req">*</span>
                    </label>
                    <div class="daw-input-group daw-input-group--has-toggle">
                        <input id="daw-new-password" name="new_password" type="password" minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter" required>
                        <button type="button" class="daw-pwd-toggle-btn" data-target="daw-new-password" title="Tampilkan password">
                            <svg class="daw-icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="daw-icon-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        </button>
                    </div>
                    <span class="daw-field-hint">Gunakan minimal 8 karakter (kombinasi huruf & angka disarankan).</span>
                </div>

                <div class="daw-form-field">
                    <label class="daw-form-label" for="daw-password-confirmation">
                        Konfirmasi Password Baru <span class="daw-req">*</span>
                    </label>
                    <div class="daw-input-group daw-input-group--has-toggle">
                        <input id="daw-password-confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" placeholder="Ulangi password baru" required>
                        <button type="button" class="daw-pwd-toggle-btn" data-target="daw-password-confirmation" title="Tampilkan password">
                            <svg class="daw-icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="daw-icon-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        </button>
                    </div>
                    <div id="daw-match-badge" class="daw-pwd-match-indicator"></div>
                </div>

                <button type="submit" class="daw-auth-submit-btn" id="daw-save-password-btn">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                    <span>Simpan Password Baru</span>
                </button>
            </form>

            <div class="daw-auth-footer-nav">
                <a class="daw-auth-back-link" href="<?= esc_url( recruitment_get_public_url( 'login', [ 'target' => 'hr' ] ) ) ?>">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" /></svg>
                    <span>Batal dan kembali ke halaman login HR</span>
                </a>
                <div class="daw-auth-divider"></div>
                <div class="daw-auth-meta-links">
                    <a href="<?= esc_url( $public_url ) ?>">Portal Pelamar DAW</a>
                    <span>&bull;</span>
                    <a href="<?= esc_url( wp_login_url( admin_url() ) ) ?>">Login via WordPress</a>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <?php if ( 'login' === $mode ) : ?>
        <dialog class="daw-admin-login__modal daw-auth-modal" data-portal-forgot-modal aria-label="Lupa password" data-auto-open="<?= $open_forgot || in_array( $forgot_notice, [ 'submitted', 'invalid_request' ], true ) ? 'true' : 'false' ?>">
            <button class="daw-admin-login__modal-close daw-auth-modal-close" type="button" data-portal-forgot-close aria-label="Tutup">&times;</button>
            <div data-portal-forgot-department hidden>
                <h2 style="font-size:18px;margin:0 0 10px;color:#0f172a;">Akses User Department</h2>
                <p style="font-size:14px;color:#64748b;line-height:1.5;">Untuk mengubah atau mereset password akun Kepala Departemen, silakan hubungi Administrator HR. Reset password mandiri saat ini hanya tersedia untuk akun Admin HR.</p>
            </div>
            <div data-portal-forgot-hr hidden>
                <h2 id="daw-forgot-modal-title" style="font-size:20px;font-weight:700;margin:0 0 6px;color:#0f172a;">Lupa Password Admin HR</h2>
                <p class="daw-auth-desc" style="margin-bottom:18px;">Masukkan email akun Admin HR Anda untuk menerima link dan token reset password.</p>
                <?php if ( 'submitted' === $forgot_notice ) : ?>
                    <div class="daw-auth-alert daw-auth-alert--success" role="status" style="margin-bottom:16px;">
                        <svg class="daw-auth-alert-icon" viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                        <div>
                            Permintaan telah diterima. Jika alamat cocok dengan akun Admin HR aktif, petunjuk reset akan dikirim ke email tersebut. Periksa juga folder Spam/Junk. Jika email tidak masuk, hubungi administrator HR.
                        </div>
                    </div>
                    <div style="margin:12px 0 16px;text-align:center;">
                        <a href="<?= esc_url( recruitment_get_public_url( 'reset-password', [ 'target' => 'hr' ] ) ) ?>" style="color:#c8102e;font-weight:700;font-size:13.5px;text-decoration:underline;">
                            Sudah menerima token di email? Buat password baru di sini &rarr;
                        </a>
                    </div>
                <?php elseif ( 'invalid_request' === $forgot_notice ) : ?>
                    <div class="daw-auth-alert daw-auth-alert--error" role="alert" style="margin-bottom:16px;">
                        <svg class="daw-auth-alert-icon" viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                        <div>Permintaan tidak valid. Silakan coba kembali.</div>
                    </div>
                <?php endif; ?>
                <form class="daw-auth-form" method="post" action="<?= esc_url( add_query_arg( [ 'recruitment_page' => 'forgot-password', 'target' => 'hr' ], get_permalink() ) ) ?>">
                    <?php wp_nonce_field( 'daw_hr_password_reset_request' ); ?>
                    <input type="hidden" name="target" value="hr">
                    <div class="daw-form-field">
                        <label class="daw-form-label" for="daw-reset-email">Email Terdaftar <span class="daw-req">*</span></label>
                        <div class="daw-input-group">
                            <input id="daw-reset-email" name="email" type="email" autocomplete="email" placeholder="contoh: hr.admin@daw.co.id" required>
                        </div>
                    </div>
                    <button type="submit" class="daw-auth-submit-btn">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" /><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" /></svg>
                        <span>Kirim Link Reset Password</span>
                    </button>
                </form>
            </div>
        </dialog>
    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password visibility toggle
    document.querySelectorAll('.daw-pwd-toggle-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var targetId = this.getAttribute('data-target');
            var input = document.getElementById(targetId);
            if (!input) return;
            var isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            var showIcon = this.querySelector('.daw-icon-show');
            var hideIcon = this.querySelector('.daw-icon-hide');
            if (showIcon && hideIcon) {
                showIcon.style.display = isPass ? 'none' : 'block';
                hideIcon.style.display = isPass ? 'block' : 'none';
            }
        });
    });

    // Real-time password confirmation match check for reset password
    var newPwd = document.getElementById('daw-new-password');
    var confirmPwd = document.getElementById('daw-password-confirmation');
    var badge = document.getElementById('daw-match-badge');

    if (newPwd && confirmPwd && badge) {
        function validatePasswordMatch() {
            var val1 = newPwd.value;
            var val2 = confirmPwd.value;

            if (val2.length === 0) {
                badge.className = 'daw-pwd-match-indicator';
                badge.style.display = 'none';
                confirmPwd.setCustomValidity('');
                return;
            }

            if (val1 === val2) {
                badge.className = 'daw-pwd-match-indicator is-matched';
                badge.innerHTML = '&#10003; Konfirmasi kata sandi cocok';
                confirmPwd.setCustomValidity('');
            } else {
                badge.className = 'daw-pwd-match-indicator is-mismatched';
                badge.innerHTML = '&#10007; Konfirmasi kata sandi belum cocok';
                confirmPwd.setCustomValidity('Konfirmasi password baru tidak cocok');
            }
        }

        newPwd.addEventListener('input', validatePasswordMatch);
        confirmPwd.addEventListener('input', validatePasswordMatch);
    }
});
</script>