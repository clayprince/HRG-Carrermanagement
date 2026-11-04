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

$notice = sanitize_key( wp_unslash( $_GET['password_notice'] ?? '' ) );
$messages = [
    'password_updated' => [ 'Password berhasil diperbarui di Oracle.', 'success' ],
    'current_password' => [ 'Password saat ini tidak sesuai.', 'error' ],
    'invalid' => [ 'Pastikan password baru minimal 8 karakter dan konfirmasinya sesuai.', 'error' ],
    'unavailable' => [ 'Password tidak dapat diperbarui karena layanan Oracle belum tersedia.', 'error' ],
];
$email = (string) get_user_meta( $current_user->ID, 'daw_oracle_email', true );
?>
<div class="daw-account-settings">
    <header class="daw-account-settings__heading">
        <div>
            <p class="daw-account-settings__breadcrumb">DAW Admin <span>/</span> Profil Admin HR</p>
            <h1>Profil Admin HR</h1>
            <p><?= esc_html( $email ) ?></p>
        </div>
    </header>

    <?php if ( isset( $messages[ $notice ] ) ) : ?>
        <div class="daw-account-settings__notice <?= esc_attr( 'error' === $messages[ $notice ][1] ? 'is-error' : 'is-success' ) ?>" role="status"><?= esc_html( $messages[ $notice ][0] ) ?></div>
    <?php endif; ?>

    <section class="daw-account-settings__panel" aria-label="Ubah password Admin HR">
        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
            <input type="hidden" name="action" value="daw_change_hr_password">
            <?php wp_nonce_field( 'daw_change_hr_password' ); ?>
            <div class="daw-account-settings__form-grid">
                <label>Password saat ini<input type="password" name="current_password" autocomplete="current-password" required></label>
                <label>Password baru<input type="password" name="new_password" minlength="8" autocomplete="new-password" required></label>
                <label>Konfirmasi password baru<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>
            </div>
            <footer><button type="submit" class="daw-account-settings__save">Simpan Password</button></footer>
        </form>
    </section>
</div>
