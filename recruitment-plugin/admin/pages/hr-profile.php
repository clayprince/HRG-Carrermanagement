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

$notice = sanitize_key( wp_unslash( $_GET['profile_notice'] ?? $_GET['password_notice'] ?? '' ) );
$messages = [
    'profile_updated' => [ 'Profil berhasil diperbarui dan tersimpan ke Oracle Database.', 'success' ],
    'email_sent' => [ 'Link konfirmasi perubahan email telah dikirim ke alamat baru.', 'success' ],
    'old_code_sent' => [ 'Kode verifikasi 5 angka telah dikirim ke email lama.', 'success' ],
    'new_code_sent' => [ 'Kode verifikasi 5 angka telah dikirim ke email baru.', 'success' ],
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

    <?php if ( isset( $messages[ $notice ] ) ) : ?>
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
            <div class="daw-hr-profile__info-box">
                <span class="dashicons dashicons-info"></span>
                <div>
                    <strong>Prosedur Verifikasi Email:</strong>
                    <p>Sistem akan mengirimkan kode 5 angka ke email lama (<strong><?= esc_html( $email ) ?></strong>) terlebih dahulu, lalu kode konfirmasi ke email baru sebelum data disinkronkan.</p>
                </div>
            </div>

            <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
                <input type="hidden" name="action" value="daw_request_hr_email_change">
                <?php wp_nonce_field( 'daw_request_hr_email_change' ); ?>
                <div class="daw-hr-profile__grid daw-hr-profile__grid--2">
                    <div class="daw-hr-profile__field">
                        <label for="hr-new-email">Email Baru <span class="daw-hr-profile__required">*</span></label>
                        <input type="email" id="hr-new-email" name="new_email" maxlength="254" placeholder="contoh: hr.admin@daw.co.id" required>
                        <span class="daw-hr-profile__hint">Pastikan email baru aktif dan dapat menerima email verifikasi.</span>
                    </div>
                    <div class="daw-hr-profile__field">
                        <label for="hr-email-current-password">Password Saat Ini <span class="daw-hr-profile__required">*</span></label>
                        <input type="password" id="hr-email-current-password" name="current_password" autocomplete="current-password" placeholder="Masukkan password saat ini" required>
                        <span class="daw-hr-profile__hint">Dibutuhkan untuk memastikan otentikasi pemilik akun.</span>
                    </div>
                </div>
                <div class="daw-hr-profile__form-action">
                    <button type="submit" class="daw-hr-profile__btn daw-hr-profile__btn--primary">
                        <span class="dashicons dashicons-arrow-right-alt"></span> Kirim Kode ke Email Lama
                    </button>
                </div>
            </form>

            <?php if ( in_array( $notice, [ 'old_code_sent', 'new_code_sent' ], true ) ) : ?>
                <div class="daw-hr-profile__verification-box">
                    <div class="daw-hr-profile__verification-header">
                        <span class="dashicons dashicons-lock"></span>
                        <div>
                            <h3>Konfirmasi Kode Verifikasi</h3>
                            <p><?= 'old_code_sent' === $notice ? 'Masukkan kode 5 angka yang telah dikirim ke email lama (' . esc_html( $email ) . ').' : 'Masukkan kode 5 angka yang telah dikirim ke email baru Anda.' ?></p>
                        </div>
                    </div>
                    <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
                        <input type="hidden" name="action" value="<?= esc_attr( 'old_code_sent' === $notice ? 'daw_confirm_hr_email_old' : 'daw_confirm_hr_email_new' ) ?>">
                        <?php wp_nonce_field( 'old_code_sent' === $notice ? 'daw_confirm_hr_email_old' : 'daw_confirm_hr_email_new' ); ?>
                        <div class="daw-hr-profile__verification-row">
                            <div class="daw-hr-profile__field daw-hr-profile__field--inline">
                                <label for="hr-verification-code">Kode 5 Angka:</label>
                                <input type="text" id="hr-verification-code" name="verification_code" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" placeholder="12345" class="daw-hr-profile__code-input" required>
                            </div>
                            <button type="submit" class="daw-hr-profile__btn daw-hr-profile__btn--secondary">
                                <span class="dashicons dashicons-yes"></span> Konfirmasi Kode
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- SECTION 3: KEAMANAN & GANTI PASSWORD -->
    <section class="daw-hr-profile__card" aria-label="Ubah password Admin HR">
        <div class="daw-hr-profile__card-header">
            <div class="daw-hr-profile__card-icon is-red">
                <span class="dashicons dashicons-privacy"></span>
            </div>
            <div class="daw-hr-profile__card-title-group">
                <h2>Ganti Password</h2>
                <p>Perbarui password akun Admin HR Anda untuk menjaga keamanan akses portal.</p>
            </div>
        </div>
        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
            <input type="hidden" name="action" value="daw_change_hr_password">
            <?php wp_nonce_field( 'daw_change_hr_password' ); ?>
            <div class="daw-hr-profile__card-body">
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
                <div class="daw-hr-profile__forgot-link">
                    <span class="dashicons dashicons-editor-help"></span>
                    <a href="<?= esc_url( recruitment_get_public_url( 'login', [ 'target' => 'hr', 'forgot' => '1' ] ) ) ?>">Lupa password? Kirim link reset ke <?= esc_html( $email ) ?></a>
                </div>
            </div>
            <div class="daw-hr-profile__card-footer">
                <button type="submit" class="daw-hr-profile__btn daw-hr-profile__btn--primary">
                    <span class="dashicons dashicons-lock"></span> Ubah Password
                </button>
            </div>
        </form>
    </section>
</div>
