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
    'profile_updated' => [ 'Profil berhasil diperbarui.', 'success' ],
    'email_sent' => [ 'Link konfirmasi perubahan email telah dikirim ke alamat baru.', 'success' ],
    'email_updated' => [ 'Email berhasil diperbarui setelah konfirmasi token.', 'success' ],
    'duplicate' => [ 'Username atau email sudah digunakan akun lain.', 'error' ],
    'profile_failed' => [ 'Profil tidak dapat diperbarui. Silakan coba lagi.', 'error' ],
    'email_invalid' => [ 'Password saat ini atau data email tidak valid.', 'error' ],
    'email_failed' => [ 'Email konfirmasi tidak dapat dikirim. Pastikan SMTP WordPress sudah dikonfigurasi.', 'error' ],
    'password_updated' => [ 'Password berhasil diperbarui di Oracle.', 'success' ],
    'current_password' => [ 'Password saat ini tidak sesuai.', 'error' ],
    'invalid' => [ 'Pastikan password baru minimal 8 karakter dan konfirmasinya sesuai.', 'error' ],
    'unavailable' => [ 'Password tidak dapat diperbarui karena layanan Oracle belum tersedia.', 'error' ],
];
$email = (string) get_user_meta( $current_user->ID, 'daw_oracle_email', true );
$username = (string) get_user_meta( $current_user->ID, 'daw_oracle_username', true );
?>
<div class="daw-account-settings">
    <header class="daw-account-settings__heading">
        <div>
            <p class="daw-account-settings__breadcrumb">DAW Admin <span>/</span> Profil Admin HR</p>
            <h1>Profil Admin HR</h1>
            <p>Perbarui identitas akun tanpa mengubah password secara langsung.</p>
        </div>
    </header>

    <?php if ( isset( $messages[ $notice ] ) ) : ?>
        <div class="daw-account-settings__notice <?= esc_attr( 'error' === $messages[ $notice ][1] ? 'is-error' : 'is-success' ) ?>" role="status"><?= esc_html( $messages[ $notice ][0] ) ?></div>
    <?php endif; ?>

    <section class="daw-account-settings__panel" aria-label="Identitas akun Admin HR">
        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
            <input type="hidden" name="action" value="daw_update_hr_profile">
            <?php wp_nonce_field( 'daw_update_hr_profile' ); ?>
            <div class="daw-account-settings__form-grid">
                <label>Username<input type="text" name="username" value="<?= esc_attr( $username ) ?>" maxlength="100" required></label>
                <label>Email saat ini<input type="email" value="<?= esc_attr( $email ) ?>" readonly></label>
            </div>
            <footer><button type="submit" class="daw-account-settings__save">Simpan Profil</button></footer>
        </form>
    </section>

    <section class="daw-account-settings__panel" aria-label="Konfirmasi perubahan email">
        <header><h2>Ganti Email dengan Verifikasi</h2><p>Masukkan password saat ini. Link konfirmasi akan dikirim ke email baru.</p></header>
        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
            <input type="hidden" name="action" value="daw_request_hr_email_change">
            <?php wp_nonce_field( 'daw_request_hr_email_change' ); ?>
            <div class="daw-account-settings__form-grid">
                <label>Email baru<input type="email" name="new_email" maxlength="254" required></label>
                <label>Password saat ini<input type="password" name="current_password" autocomplete="current-password" required></label>
            </div>
            <footer><button type="submit" class="daw-account-settings__save">Kirim Link Verifikasi</button></footer>
        </form>
    </section>

    <section class="daw-account-settings__panel" aria-label="Ubah password Admin HR">
        <header><h2>Ganti Password</h2><p>Untuk keamanan, gunakan link reset password yang dikirim ke email akun.</p></header>
        <p style="padding: 0 18px 14px"><a href="<?= esc_url( recruitment_get_public_url( 'login', [ 'target' => 'hr', 'forgot' => '1' ] ) ) ?>">Kirim link reset password ke <?= esc_html( $email ) ?></a></p>
    </section>
</div>
