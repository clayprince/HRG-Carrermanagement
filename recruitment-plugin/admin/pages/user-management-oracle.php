<?php
if ( ! current_user_can( 'manage_recruitment' ) ) {
    wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengelola akun department.', 'recruitment-plugin' ) );
}

$database = new Recruitment_Database();
$departments = $database->get_departments();
$department_error = $database->has_error();
$notice = sanitize_key( wp_unslash( $_GET['notice'] ?? '' ) );
$messages = [
    'created' => [ 'Akun department berhasil dibuat.', 'success' ],
    'duplicate' => [ 'Username atau email sudah digunakan.', 'error' ],
    'department' => [ 'Departemen yang dipilih tidak ditemukan di Oracle.', 'error' ],
    'unavailable' => [ 'Database Oracle belum tersedia. Silakan coba kembali nanti.', 'error' ],
    'invalid' => [ 'Periksa kembali data akun dan pastikan password minimal 8 karakter serta konfirmasinya cocok.', 'error' ],
];
?>
<div class="daw-account-settings">
    <header class="daw-account-settings__heading">
        <div>
            <p class="daw-account-settings__breadcrumb">DAW Admin <span>/</span> Pengelolaan Akun User</p>
            <h1>Buat Akun Department</h1>
            <p>Akun disimpan di Oracle dan digunakan untuk masuk ke portal User Department.</p>
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
            <footer><button type="submit" class="daw-account-settings__save" <?= $departments ? '' : 'disabled' ?>>Simpan Akun</button></footer>
        </form>
    </section>
</div>
