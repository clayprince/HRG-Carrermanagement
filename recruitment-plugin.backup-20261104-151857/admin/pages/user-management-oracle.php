<?php
if ( ! current_user_can( 'manage_recruitment' ) ) {
    wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengelola akun department.', 'recruitment-plugin' ) );
}

$database = new Recruitment_Database();
$departments = $database->get_departments();
$department_error = $database->has_error();
$accounts = $database->get_department_accounts();
$account_error = $database->has_error();
$notice = sanitize_key( wp_unslash( $_GET['notice'] ?? '' ) );
$messages = [
    'created' => [ 'Akun department berhasil dibuat.', 'success' ],
    'status_updated' => [ 'Status akun department berhasil diperbarui.', 'success' ],
    'duplicate' => [ 'Username atau email sudah digunakan.', 'error' ],
    'department' => [ 'Departemen yang dipilih tidak ditemukan di Oracle.', 'error' ],
    'unavailable' => [ 'Database Oracle belum tersedia. Silakan coba kembali nanti.', 'error' ],
    'not_found' => [ 'Akun department tidak ditemukan di Oracle.', 'error' ],
    'invalid' => [ 'Periksa kembali data akun dan pastikan password minimal 8 karakter serta konfirmasinya cocok.', 'error' ],
];
$can_create_account = ! $department_error && ! $account_error && ! empty( $departments );
?>
<div class="daw-account-settings">
    <header class="daw-account-settings__heading">
        <div>
            <p class="daw-account-settings__breadcrumb">DAW Admin <span>/</span> Pengelolaan Akun User</p>
            <h1>Pengelolaan Akun User</h1>
            <p>Akun User Department disimpan di Oracle dan digunakan untuk masuk ke portal departemen.</p>
        </div>
    </header>

    <?php if ( isset( $messages[ $notice ] ) ) : ?>
        <div class="daw-account-settings__notice <?= esc_attr( 'error' === $messages[ $notice ][1] ? 'is-error' : '' ) ?>" role="status"><?= esc_html( $messages[ $notice ][0] ) ?></div>
    <?php endif; ?>

    <?php if ( $department_error ) : ?>
        <div class="daw-account-settings__notice is-error" role="alert">Daftar departemen tidak dapat dimuat dari Oracle.</div>
    <?php elseif ( ! $departments ) : ?>
        <div class="daw-account-settings__notice" role="status">Belum ada departemen di Oracle. Akun belum dapat dibuat sampai tabel DEPARTEMEN diisi.</div>
    <?php endif; ?>

    <?php if ( $account_error ) : ?>
        <div class="daw-account-settings__notice is-error" role="alert">Status akun tidak dapat dibaca dari Oracle. Pastikan migrasi status akun telah diterapkan dan koneksi Oracle tersedia.</div>
    <?php else : ?>
        <section class="daw-account-settings__panel" aria-label="Daftar akun User Department">
            <div class="daw-account-settings__table-wrap">
                <table class="daw-account-settings__table">
                    <thead><tr><th scope="col">Akun</th><th scope="col">Email</th><th scope="col">Departemen</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach ( $accounts as $account ) :
                            $status = strtoupper( (string) $account['status'] );
                            $is_active = 'ACTIVE' === $status;
                            ?>
                            <tr>
                                <td data-label="Akun">
                                    <span class="daw-account-settings__avatar" aria-hidden="true"><?= esc_html( strtoupper( substr( (string) $account['username'], 0, 2 ) ) ) ?></span>
                                    <strong><?= esc_html( $account['username'] ) ?></strong>
                                </td>
                                <td data-label="Email"><a href="mailto:<?= esc_attr( $account['email'] ) ?>"><?= esc_html( $account['email'] ) ?></a></td>
                                <td data-label="Departemen"><?= esc_html( $account['department'] ?: '—' ) ?></td>
                                <td data-label="Status"><span class="daw-account-settings__status <?= $is_active ? 'is-active' : 'is-inactive' ?>"><?= esc_html( 'ACTIVE' === $status ? 'Aktif' : ( 'INACTIVE' === $status ? 'Nonaktif' : 'Tidak valid' ) ) ?></span></td>
                                <td data-label="Aksi">
                                    <?php if ( in_array( $status, [ 'ACTIVE', 'INACTIVE' ], true ) ) : ?>
                                        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
                                            <input type="hidden" name="action" value="daw_set_department_account_status">
                                            <input type="hidden" name="account_id" value="<?= esc_attr( $account['id'] ) ?>">
                                            <input type="hidden" name="status" value="<?= esc_attr( $is_active ? 'INACTIVE' : 'ACTIVE' ) ?>">
                                            <?php wp_nonce_field( 'daw_set_department_account_status_' . absint( $account['id'] ) ); ?>
                                            <button type="submit" class="daw-account-settings__text-action"><?= esc_html( $is_active ? 'Nonaktifkan' : 'Aktifkan' ) ?></button>
                                        </form>
                                    <?php else : ?>
                                        <span class="daw-account-settings__protected">Status tidak dikenali</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ( ! $accounts ) : ?>
                            <tr><td class="daw-account-settings__empty" colspan="5">Belum ada akun User Department di Oracle.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <section class="daw-account-settings__panel" aria-label="Form akun department">
        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
            <input type="hidden" name="action" value="daw_create_department_account">
            <?php wp_nonce_field( 'daw_create_department_account' ); ?>
            <div class="daw-account-settings__form-grid">
                <label>Username<input type="text" name="username" maxlength="100" autocomplete="username" required></label>
                <label>Email<input type="email" name="email" maxlength="50" autocomplete="email" required></label>
                <label>Departemen
                    <select name="department_id" required <?= $departments ? '' : 'disabled' ?>>
                        <option value="">Pilih departemen</option>
                        <?php foreach ( $departments as $department ) : ?>
                            <option value="<?= esc_attr( $department['id'] ) ?>"><?= esc_html( $department['name'] ) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Password<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
                <label>Konfirmasi password<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>
            </div>
            <footer><button type="submit" class="daw-account-settings__save" <?= $can_create_account ? '' : 'disabled' ?>>Simpan Akun</button></footer>
        </form>
    </section>
</div>
