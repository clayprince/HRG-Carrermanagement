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
    'old_code_sent' => [ 'Kode 5 angka telah dikirim ke email lama.', 'success' ],
    'new_code_sent' => [ 'Kode 5 angka telah dikirim ke email baru.', 'success' ],
    'code_invalid' => [ 'Kode tidak valid atau sudah kedaluwarsa.', 'error' ],
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
        <header><h2>Ganti Email dengan Verifikasi</h2><p>Password saat ini diperlukan. Kode 5 angka akan dikirim ke email lama lalu email baru.</p></header>
        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
            <input type="hidden" name="action" value="daw_request_hr_email_change">
            <?php wp_nonce_field( 'daw_request_hr_email_change' ); ?>
            <div class="daw-account-settings__form-grid">
                <label>Email baru<input type="email" name="new_email" maxlength="254" required></label>
                <label>Password saat ini<input type="password" name="current_password" autocomplete="current-password" required></label>
            </div>
            <footer><button type="submit" class="daw-account-settings__save">Kirim Kode ke Email Lama</button></footer>
        </form>
        <?php if ( in_array( $notice, [ 'old_code_sent', 'new_code_sent' ], true ) ) : ?>
            <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" style="padding: 0 18px 18px">
                <input type="hidden" name="action" value="<?= esc_attr( 'old_code_sent' === $notice ? 'daw_confirm_hr_email_old' : 'daw_confirm_hr_email_new' ) ?>">
                <?php wp_nonce_field( 'old_code_sent' === $notice ? 'daw_confirm_hr_email_old' : 'daw_confirm_hr_email_new' ); ?>
                <label>Kode verifikasi 5 angka<input type="text" name="verification_code" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" required></label>
                <button type="submit" class="daw-account-settings__save">Konfirmasi Kode</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="daw-account-settings__panel" aria-label="Ubah password Admin HR">
        <header><h2>Ganti Password</h2><p>Masukkan password saat ini dan password baru.</p></header>
        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" style="padding: 0 18px 18px">
            <input type="hidden" name="action" value="daw_change_hr_password">
            <?php wp_nonce_field( 'daw_change_hr_password' ); ?>
            <label>Password saat ini<input type="password" name="current_password" required></label>
            <label>Password baru<input type="password" name="new_password" minlength="8" required></label>
            <label>Konfirmasi password baru<input type="password" name="password_confirmation" minlength="8" required></label>
            <button type="submit" class="daw-account-settings__save">Ubah Password</button>
        </form>
        <p style="padding: 0 18px 14px"><a href="<?= esc_url( recruitment_get_public_url( 'login', [ 'target' => 'hr', 'forgot' => '1' ] ) ) ?>">Lupa password? Kirim reset ke <?= esc_html( $email ) ?></a></p>
    </section>
</div>
