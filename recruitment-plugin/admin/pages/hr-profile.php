<?php
$current_user = wp_get_current_user();
$oracle_account_id = (string) get_user_meta( $current_user->ID ?? 0, 'daw_oracle_account_id', true );
if (
    ! current_user_can( 'manage_recruitment' )
    || ! $current_user instanceof WP_User
    || ! in_array( 'daw_hr', (array) $current_user->roles, true )
    || 'hr' !== get_user_meta( $current_user->ID, 'daw_oracle_account_type', true )
    || ! ctype_digit( $oracle_account_id )
    || '0' === $oracle_account_id
) {
    wp_die( esc_html__( 'Hanya Admin HR yang dapat mengakses profil ini.', 'recruitment-plugin' ) );
}

$profile_notice  = sanitize_key( wp_unslash( $_GET['profile_notice'] ?? '' ) );
$password_notice = sanitize_key( wp_unslash( $_GET['password_notice'] ?? '' ) );
$general_notice  = sanitize_key( wp_unslash( $_GET['notice'] ?? '' ) );
$notice          = '' !== $profile_notice ? $profile_notice : $general_notice;
$messages = [
    'profile_updated' => [ 'Profil berhasil diperbarui dan tersimpan ke Oracle Database.', 'success' ],
    'email_sent' => [ 'Link konfirmasi perubahan email telah dikirim ke alamat baru.', 'success' ],
    'old_code_sent' => [ 'Kode verifikasi 5 angka telah dikirim ke email saat ini. Masukkan kode di bawah untuk langsung mengonfirmasi perubahan email.', 'success' ],
    'new_code_sent' => [ 'Kode verifikasi 5 angka telah dikirim. Masukkan kode di bawah untuk langsung mengonfirmasi perubahan email.', 'success' ],
    'code_invalid' => [ 'Kode verifikasi tidak valid atau sudah kedaluwarsa.', 'error' ],
    'email_updated' => [ 'Email berhasil diperbarui dan disinkronkan ke Oracle Database.', 'success' ],
    'duplicate' => [ 'Username atau email sudah digunakan oleh akun lain.', 'error' ],
    'profile_failed' => [ 'Profil tidak dapat diperbarui. Silakan periksa kembali data Anda.', 'error' ],
    'email_invalid' => [ 'Password saat ini salah atau format email baru tidak valid.', 'error' ],
    'email_failed' => [ 'Email konfirmasi tidak dapat dikirim. Pastikan pengaturan SMTP WordPress aktif.', 'error' ],
    'password_updated' => [ 'Password berhasil diperbarui dan tersinkronkan ke Oracle Database.', 'success' ],
    'current_password' => [ 'Password saat ini tidak sesuai.', 'error' ],
    'invalid' => [ 'Pastikan password baru minimal 8 karakter dan konfirmasi password cocok.', 'error' ],
    'unavailable' => [ 'Password tidak dapat diperbarui karena layanan database Oracle tidak merespons.', 'error' ],
];

$email_notices = [ 'old_code_sent', 'new_code_sent', 'code_invalid', 'email_updated', 'email_invalid', 'email_failed', 'duplicate' ];
$profile_notices = [ 'profile_updated', 'profile_failed' ];
$password_notices = [ 'password_updated', 'current_password', 'invalid', 'unavailable' ];

$database = new Recruitment_Database();
$hr_profile = $database->get_hr_profile( $oracle_account_id );
$email = ! empty( $hr_profile['email'] ) ? (string) $hr_profile['email'] : (string) get_user_meta( $current_user->ID, 'daw_oracle_email', true );
if ( '' === $email && ! empty( $current_user->user_email ) ) {
    $email = $current_user->user_email;
}
$username = ! empty( $hr_profile['username'] ) ? (string) $hr_profile['username'] : (string) get_user_meta( $current_user->ID, 'daw_oracle_username', true );
if ( '' === $username ) {
    $username = (string) $current_user->display_name;
}

$pending_data = get_transient( 'daw_hr_email_pending_' . $current_user->ID );
if ( ! is_array( $pending_data ) ) {
    $pending_data = get_transient( 'daw_hr_email_old_' . $current_user->ID );
}
if ( ! is_array( $pending_data ) ) {
    $pending_data = get_transient( 'daw_hr_email_new_' . $current_user->ID );
}
$pending_email = ( is_array( $pending_data ) && 'email_updated' !== $notice )
    ? (string) ( $pending_data['new_email'] ?? $pending_data['email'] ?? '' )
    : '';

$show_verification_box = ( is_array( $pending_data ) || in_array( $notice, [ 'old_code_sent', 'new_code_sent', 'code_invalid' ], true ) ) && 'email_updated' !== $notice;
?>
<div class="wrap recruitment-admin daw-hr-profile">
    <header class="daw-hr-profile__header">
        <div class="daw-hr-profile__header-left">
            <p class="daw-hr-profile__breadcrumb">DAW Admin <span>/</span> Profil Admin HR</p>
            <h1 class="daw-hr-profile__title">Profil Admin HR</h1>
            <p class="daw-hr-profile__subtitle">Kelola informasi identitas akun, alamat email, dan keamanan kata sandi Anda.</p>
        </div>
        <div class="daw-hr-profile__header-badge">
            <span class="daw-hr-profile__role-pill"><span class="dashicons dashicons-shield"></span> Administrator HR</span>
        </div>
    </header>

    <?php if ( isset( $messages[ $notice ] ) && ! in_array( $notice, array_merge( $email_notices, $profile_notices, $password_notices ), true ) ) : ?>
        <div class="daw-hr-profile__alert daw-hr-profile__alert--<?= esc_attr( $messages[ $notice ][1] ) ?>" role="status">
            <span class="dashicons <?= 'error' === $messages[ $notice ][1] ? 'dashicons-warning' : 'dashicons-yes-alt' ?>"></span>
            <div class="daw-hr-profile__alert-content">
                <strong><?= 'error' === $messages[ $notice ][1] ? 'Pemberitahuan' : 'Berhasil' ?>:</strong>
                <?= esc_html( $messages[ $notice ][0] ) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- SECTION 1: INFORMASI PROFIL & AKUN -->
    <section class="daw-hr-profile__card" aria-label="Identitas akun Admin HR">
        <div class="daw-hr-profile__card-header">
            <div class="daw-hr-profile__card-icon is-blue">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <div class="daw-hr-profile__card-title-group">
                <h2>Informasi Akun</h2>
                <p>Perbarui identitas akun dan nama pengguna yang ditampilkan pada portal DAW.</p>
            </div>
        </div>
        <?php if ( in_array( $notice, $profile_notices, true ) && isset( $messages[ $notice ] ) ) : ?>
            <div class="daw-hr-profile__alert daw-hr-profile__alert--<?= esc_attr( $messages[ $notice ][1] ) ?>" role="status" style="margin:16px 20px 0;">
                <span class="dashicons <?= 'error' === $messages[ $notice ][1] ? 'dashicons-warning' : 'dashicons-yes-alt' ?>"></span>
                <div class="daw-hr-profile__alert-content">
                    <strong><?= 'error' === $messages[ $notice ][1] ? 'Pemberitahuan' : 'Berhasil' ?>:</strong>
                    <?= esc_html( $messages[ $notice ][0] ) ?>
                </div>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
            <input type="hidden" name="action" value="daw_update_hr_profile">
            <?php wp_nonce_field( 'daw_update_hr_profile' ); ?>
            <div class="daw-hr-profile__card-body">
                <div class="daw-hr-profile__grid daw-hr-profile__grid--2">
                    <div class="daw-hr-profile__field">
                        <label for="hr-username">Username / Nama Pengguna <span class="daw-hr-profile__required">*</span></label>
                        <input type="text" id="hr-username" name="username" value="<?= esc_attr( $username ) ?>" maxlength="100" placeholder="Masukkan username" required>
                        <span class="daw-hr-profile__hint">Digunakan sebagai identitas dan nama tampilan Admin HR.</span>
                    </div>
                    <div class="daw-hr-profile__field">
                        <label for="hr-current-email">Email Saat Ini <span class="daw-hr-profile__tag">Aktif</span></label>
                        <input type="email" id="hr-current-email" value="<?= esc_attr( $email ) ?>" readonly class="is-readonly">
                        <span class="daw-hr-profile__hint">Email terdaftar di Oracle Database. Untuk mengganti, gunakan formulir di bawah.</span>
                    </div>
                </div>
            </div>
            <div class="daw-hr-profile__card-footer">
                <button type="submit" class="daw-hr-profile__btn daw-hr-profile__btn--primary">
                    <span class="dashicons dashicons-saved"></span> Simpan Profil
                </button>
            </div>
        </form>
    </section>

    <!-- SECTION 2: GANTI EMAIL DENGAN VERIFIKASI -->
    <section class="daw-hr-profile__card" aria-label="Konfirmasi perubahan email">
        <div class="daw-hr-profile__card-header">
            <div class="daw-hr-profile__card-icon is-amber">
                <span class="dashicons dashicons-email-alt"></span>
            </div>
            <div class="daw-hr-profile__card-title-group">
                <h2>Ganti Email dengan Verifikasi</h2>
                <p>Password saat ini diperlukan untuk verifikasi keamanan sebelum kode konfirmasi dikirimkan.</p>
            </div>
        </div>
        <div class="daw-hr-profile__card-body">
            <?php if ( in_array( $notice, $email_notices, true ) && isset( $messages[ $notice ] ) ) : ?>
                <div class="daw-hr-profile__alert daw-hr-profile__alert--<?= esc_attr( $messages[ $notice ][1] ) ?>" role="status" style="margin:0 0 20px;">
                    <span class="dashicons <?= 'error' === $messages[ $notice ][1] ? 'dashicons-warning' : 'dashicons-yes-alt' ?>"></span>
                    <div class="daw-hr-profile__alert-content">
                        <strong><?= 'error' === $messages[ $notice ][1] ? 'Pemberitahuan' : 'Berhasil' ?>:</strong>
                        <?= esc_html( $messages[ $notice ][0] ) ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="daw-hr-profile__info-box">
                <span class="dashicons dashicons-info"></span>
                <div>
                    <strong>Prosedur Verifikasi Email:</strong>
                    <p>Sistem akan mengirimkan kode verifikasi 5 angka ke email saat ini (<strong><?= esc_html( $email ) ?></strong>). Masukkan kode verifikasi tersebut pada kotak konfirmasi di bawah untuk langsung mengonfirmasi dan memperbarui alamat email Anda.</p>
                </div>
            </div>

            <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
                <input type="hidden" name="action" value="daw_request_hr_email_change">
                <?php wp_nonce_field( 'daw_request_hr_email_change' ); ?>
                <div class="daw-hr-profile__grid daw-hr-profile__grid--2">
                    <div class="daw-hr-profile__field">
                        <label for="hr-new-email">Email Baru <span class="daw-hr-profile__required">*</span></label>
                        <input type="email" id="hr-new-email" name="new_email" value="<?= esc_attr( $pending_email ) ?>" maxlength="254" placeholder="contoh: hr.admin@daw.co.id" required>
                        <span class="daw-hr-profile__hint">Pastikan email baru aktif dan dapat menerima email.</span>
                    </div>
                    <div class="daw-hr-profile__field">
                        <label for="hr-email-current-password">Password Saat Ini <span class="daw-hr-profile__required">*</span></label>
                        <input type="password" id="hr-email-current-password" name="current_password" autocomplete="current-password" placeholder="Masukkan password saat ini" required>
                        <span class="daw-hr-profile__hint">Dibutuhkan untuk memastikan otentikasi pemilik akun.</span>
                    </div>
                </div>
                <div class="daw-hr-profile__form-action">
                    <button type="submit" class="daw-hr-profile__btn daw-hr-profile__btn--primary">
                        <span class="dashicons dashicons-arrow-right-alt"></span> <?= $show_verification_box ? 'Kirim Ulang Kode Verifikasi' : 'Kirim Kode Verifikasi' ?>
                    </button>
                </div>
            </form>

            <?php if ( $show_verification_box ) : ?>
                <div class="daw-hr-profile__verification-box" style="margin-top:20px;">
                    <div class="daw-hr-profile__verification-header">
                        <span class="dashicons dashicons-lock"></span>
                        <div>
                            <h3>Konfirmasi Kode Verifikasi</h3>
                            <p>Masukkan kode 5 angka yang telah dikirim ke email <strong><?= esc_html( $email ) ?></strong> untuk menyelesaikan pembaruan email<?php if ( '' !== $pending_email ) : ?> ke <strong><?= esc_html( $pending_email ) ?></strong><?php endif; ?>.</p>
                        </div>
                    </div>
                    <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
                        <input type="hidden" name="action" value="daw_confirm_hr_email_old">
                        <?php wp_nonce_field( 'daw_confirm_hr_email_old' ); ?>
                        <div class="daw-hr-profile__verification-row">
                            <div class="daw-hr-profile__field daw-hr-profile__field--inline">
                                <label for="hr-verification-code">Kode 5 Angka:</label>
                                <input type="text" id="hr-verification-code" name="verification_code" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" placeholder="12345" class="daw-hr-profile__code-input" required autofocus>
                            </div>
                            <button type="submit" class="daw-hr-profile__btn daw-hr-profile__btn--secondary">
                                <span class="dashicons dashicons-yes"></span> Konfirmasi & Perbarui Email
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- SECTION 3: KEAMANAN & GANTI PASSWORD -->
    <section class="daw-hr-profile__card" id="ganti-password" aria-label="Ubah password Admin HR">
        <div class="daw-hr-profile__card-header">
            <div class="daw-hr-profile__card-icon is-red">
                <span class="dashicons dashicons-privacy"></span>
            </div>
            <div class="daw-hr-profile__card-title-group">
                <h2>Ganti Password</h2>
                <p>Perbarui password akun Admin HR Anda untuk menjaga keamanan akses portal.</p>
            </div>
        </div>
        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" id="daw-hr-password-form">
            <input type="hidden" name="action" value="daw_change_hr_password">
            <?php wp_nonce_field( 'daw_change_hr_password' ); ?>
            <div class="daw-hr-profile__card-body">
                <?php if ( '' !== $password_notice && isset( $messages[ $password_notice ] ) ) : ?>
                    <div class="daw-hr-profile__alert daw-hr-profile__alert--<?= esc_attr( $messages[ $password_notice ][1] ) ?>" role="status" style="margin:0 0 20px;">
                        <span class="dashicons <?= 'error' === $messages[ $password_notice ][1] ? 'dashicons-warning' : 'dashicons-yes-alt' ?>"></span>
                        <div class="daw-hr-profile__alert-content">
                            <strong><?= 'error' === $messages[ $password_notice ][1] ? 'Pemberitahuan' : 'Berhasil' ?>:</strong>
                            <?= esc_html( $messages[ $password_notice ][0] ) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="daw-hr-profile__grid daw-hr-profile__grid--3">
                    <div class="daw-hr-profile__field">
                        <label for="hr-pwd-current">Password Saat Ini <span class="daw-hr-profile__required">*</span></label>
                        <input type="password" id="hr-pwd-current" name="current_password" placeholder="Password saat ini" autocomplete="current-password" required>
                        <span class="daw-hr-profile__hint">Password yang saat ini digunakan.</span>
                    </div>
                    <div class="daw-hr-profile__field">
                        <label for="hr-pwd-new">Password Baru <span class="daw-hr-profile__required">*</span></label>
                        <input type="password" id="hr-pwd-new" name="new_password" minlength="8" placeholder="Minimal 8 karakter" autocomplete="new-password" required>
                        <span class="daw-hr-profile__hint">Gunakan minimal 8 karakter.</span>
                    </div>
                    <div class="daw-hr-profile__field">
                        <label for="hr-pwd-confirm">Konfirmasi Password Baru <span class="daw-hr-profile__required">*</span></label>
                        <input type="password" id="hr-pwd-confirm" name="password_confirmation" minlength="8" placeholder="Ulangi password baru" autocomplete="new-password" required>
                        <span class="daw-hr-profile__hint">Harus sama persis dengan password baru.</span>
                    </div>
                </div>

                <div id="daw-pwd-match-error" style="display:none;margin-top:12px;padding:8px 12px;background:#fef2f2;border-left:4px solid #dc2626;color:#991b1b;border-radius:4px;font-size:13px;font-weight:600;">
                    <span class="dashicons dashicons-warning" style="font-size:16px;line-height:1.2;vertical-align:middle;margin-right:4px;"></span> Konfirmasi password baru tidak cocok.
                </div>

                <div class="daw-hr-profile__forgot-link" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:16px;">
                    <button type="submit" form="daw-hr-forgot-form" style="background:none;border:none;color:#c8102e;padding:0;font:inherit;cursor:pointer;text-decoration:underline;font-weight:600;display:inline-flex;align-items:center;gap:4px;">
                        <span class="dashicons dashicons-email-alt" style="color:#c8102e;"></span>
                        Lupa password? Kirim tautan & token reset ke <?= esc_html( $email ) ?>
                    </button>
                    <span style="color:#9ca3af;">|</span>
                    <a href="<?= esc_url( recruitment_get_public_url( 'reset-password', [ 'target' => 'hr' ] ) ) ?>" style="color:#4b5563;font-size:13px;text-decoration:underline;">
                        Sudah punya token reset? Masukkan token di sini
                    </a>
                </div>
            </div>
            <div class="daw-hr-profile__card-footer">
                <button type="submit" class="daw-hr-profile__btn daw-hr-profile__btn--primary">
                    <span class="dashicons dashicons-lock"></span> Ubah Password
                </button>
            </div>
        </form>

        <form id="daw-hr-forgot-form" method="post" action="<?= esc_url( recruitment_get_public_url( 'forgot-password', [ 'target' => 'hr' ] ) ) ?>" style="display:none;">
            <?php wp_nonce_field( 'daw_hr_password_reset_request' ); ?>
            <input type="hidden" name="target" value="hr">
            <input type="hidden" name="email" value="<?= esc_attr( $email ) ?>">
        </form>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var newPwd = document.getElementById('hr-pwd-new');
        var confirmPwd = document.getElementById('hr-pwd-confirm');
        var errDiv = document.getElementById('daw-pwd-match-error');
        if (newPwd && confirmPwd) {
            function validateMatch() {
                if (confirmPwd.value.length > 0 && newPwd.value !== confirmPwd.value) {
                    confirmPwd.setCustomValidity('Konfirmasi password baru tidak cocok.');
                    if (errDiv) errDiv.style.display = 'block';
                } else {
                    confirmPwd.setCustomValidity('');
                    if (errDiv) errDiv.style.display = 'none';
                }
            }
            newPwd.addEventListener('input', validateMatch);
            confirmPwd.addEventListener('input', validateMatch);
        }
    });
    </script>
</div>
