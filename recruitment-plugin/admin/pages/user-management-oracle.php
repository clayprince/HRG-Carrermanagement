<?php
if ( ! current_user_can( 'manage_recruitment' ) ) {
    wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengelola akun department.', 'recruitment-plugin' ) );
}

$database = new Recruitment_Database();
$departments = $database->get_departments();
$department_error = $database->has_error();
$accounts = $database->get_portal_accounts();
$account_error = $database->has_error();
$notice = sanitize_key( wp_unslash( $_GET['notice'] ?? '' ) );
$hr_account_notice = sanitize_key( wp_unslash( $_GET['hr_account_notice'] ?? '' ) );
$messages = [
    'created' => [ 'Akun department berhasil dibuat.', 'success' ],
    'updated' => [ 'Akun department berhasil diperbarui.', 'success' ],
    'deleted' => [ 'Akun department berhasil dihapus.', 'success' ],
    'status_updated' => [ 'Status akun department berhasil diperbarui.', 'success' ],
    'duplicate' => [ 'Username atau email sudah digunakan.', 'error' ],
    'department' => [ 'Departemen yang dipilih tidak ditemukan di Oracle.', 'error' ],
    'unavailable' => [ 'Database Oracle belum tersedia. Silakan coba kembali nanti.', 'error' ],
    'not_found' => [ 'Akun department tidak ditemukan di Oracle.', 'error' ],
    'invalid' => [ 'Periksa kembali data akun dan pastikan password minimal 8 karakter serta konfirmasinya cocok.', 'error' ],
];
$hr_account_messages = [
    'created' => [ 'Akun Admin HR berhasil dibuat di Oracle. Silakan login menggunakan email dan password yang baru dibuat.', 'success' ],
    'duplicate' => [ 'Email Admin HR sudah terdaftar di Oracle.', 'error' ],
    'unavailable' => [ 'Akun Admin HR belum dapat dibuat karena Oracle belum tersedia.', 'error' ],
    'invalid' => [ 'Masukkan email yang valid dan password minimal 8 karakter dengan konfirmasi yang sama.', 'error' ],
];
$can_create_account = ! $department_error && ! $account_error && ! empty( $departments );
$active_accounts = count(
    array_filter(
        $accounts,
        static function ( array $account ): bool {
            return 'ACTIVE' === strtoupper( (string) $account['status'] );
        }
    )
);
$inactive_accounts = count( $accounts ) - $active_accounts;
$can_create_hr_account = current_user_can( 'manage_options' );
?>
<div class="daw-account-settings">
    <header class="daw-account-settings__heading">
        <div>
            <p class="daw-account-settings__breadcrumb">DAW Admin <span>/</span> Pengelolaan Akun User</p>
            <h1>Pengelolaan Akun User</h1>
            <p>Kelola akun HR Admin dan User Department yang tersimpan di Oracle.</p>
        </div>
        <button class="daw-account-settings__add" type="button" data-open-account-dialog <?= $can_create_account || $can_create_hr_account ? '' : 'disabled' ?>>
            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> Tambah Akun
        </button>
    </header>

    <?php if ( isset( $messages[ $notice ] ) ) : ?>
        <div class="daw-account-settings__notice <?= esc_attr( 'error' === $messages[ $notice ][1] ? 'is-error' : '' ) ?>" role="status"><?= esc_html( $messages[ $notice ][0] ) ?></div>
    <?php endif; ?>

    <?php if ( $department_error ) : ?>
        <div class="daw-account-settings__notice is-error" role="alert">Daftar departemen tidak dapat dimuat dari Oracle.</div>
    <?php elseif ( ! $departments ) : ?>
        <div class="daw-account-settings__notice" role="status">Belum ada departemen di Oracle. Akun belum dapat dibuat sampai tabel HRS_DEPARTEMEN diisi.</div>
    <?php endif; ?>

    <?php if ( $account_error ) : ?>
        <div class="daw-account-settings__notice is-error" role="alert">Status akun tidak dapat dibaca dari Oracle. Pastikan migrasi status akun telah diterapkan dan koneksi Oracle tersedia.</div>
    <?php else : ?>
        <section class="daw-account-settings__stats" aria-label="Ringkasan akun">
            <article><span>Total Akun</span><strong><?= esc_html( number_format_i18n( count( $accounts ) ) ) ?></strong></article>
            <article><span>Akun Aktif</span><strong><?= esc_html( number_format_i18n( $active_accounts ) ) ?></strong></article>
            <article><span>Akun Nonaktif</span><strong><?= esc_html( number_format_i18n( $inactive_accounts ) ) ?></strong></article>
        </section>

        <section class="daw-account-settings__panel" aria-label="Daftar akun portal">
            <div class="daw-account-settings__table-wrap">
                <table class="daw-account-settings__table">
                    <thead><tr><th scope="col">Nama</th><th scope="col">Email</th><th scope="col">Departemen</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach ( $accounts as $account ) :
                            $status = strtoupper( (string) $account['status'] );
                            $is_active = 'ACTIVE' === $status;
                            $is_hr = 'ADMIN_HR' === strtoupper( (string) $account['role'] );
                            $role_label = $is_hr ? 'HR Admin' : ( 'KEPALA_DEPARTEMEN' === strtoupper( (string) $account['role'] ) ? 'User Departemen' : 'Tidak valid' );
                            ?>
                            <tr>
                                <td data-label="Akun">
                                    <span class="daw-account-settings__avatar" aria-hidden="true"><?= esc_html( strtoupper( substr( (string) $account['username'], 0, 2 ) ) ) ?></span>
                                    <strong><?= esc_html( $account['username'] ) ?></strong>
                                </td>
                                <td data-label="Email"><a href="mailto:<?= esc_attr( $account['email'] ) ?>"><?= esc_html( $account['email'] ) ?></a></td>
                                <td data-label="Departemen"><?= esc_html( $account['department'] ?: '—' ) ?></td>
                                <td data-label="Role"><span class="daw-account-settings__role <?= $is_hr ? 'is-admin' : 'is-department' ?>"><?= esc_html( $role_label ) ?></span></td>
                                <td data-label="Status"><span class="daw-account-settings__status <?= $is_active ? 'is-active' : 'is-inactive' ?>"><?= esc_html( 'ACTIVE' === $status ? 'Aktif' : ( 'INACTIVE' === $status ? 'Nonaktif' : 'Tidak valid' ) ) ?></span></td>
                                <td data-label="Aksi">
                                    <?php if ( ! $is_hr && in_array( $status, [ 'ACTIVE', 'INACTIVE' ], true ) ) : ?>
                                        <div class="daw-account-settings__actions">
                                        <button type="button" class="daw-account-settings__text-action" data-edit-oracle-account
                                            data-account-id="<?= esc_attr( $account['id'] ) ?>"
                                            data-account-username="<?= esc_attr( $account['username'] ) ?>"
                                            data-account-full-name="<?= esc_attr( $account['full_name'] ?: $account['username'] ) ?>"
                                            data-account-email="<?= esc_attr( $account['email'] ) ?>"
                                            data-account-department="<?= esc_attr( $account['department'] ) ?>">Edit</button>
                                        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" onsubmit="return confirm('Hapus akun ini? Tindakan ini tidak dapat dibatalkan.');">
                                            <input type="hidden" name="action" value="daw_delete_department_account">
                                            <input type="hidden" name="account_id" value="<?= esc_attr( $account['id'] ) ?>">
                                            <?php wp_nonce_field( 'daw_delete_department_account_' . absint( $account['id'] ) ); ?>
                                            <button type="submit" class="daw-account-settings__text-action is-danger">Hapus</button>
                                        </form>
                                        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
                                            <input type="hidden" name="action" value="daw_set_department_account_status">
                                            <input type="hidden" name="account_id" value="<?= esc_attr( $account['id'] ) ?>">
                                            <input type="hidden" name="status" value="<?= esc_attr( $is_active ? 'INACTIVE' : 'ACTIVE' ) ?>">
                                            <?php wp_nonce_field( 'daw_set_department_account_status_' . absint( $account['id'] ) ); ?>
                                            <button type="submit" class="daw-account-settings__text-action"><?= esc_html( $is_active ? 'Nonaktifkan' : 'Aktifkan' ) ?></button>
                                        </form>
                                        </div>
                                    <?php else : ?>
                                        <span class="daw-account-settings__protected">Status tidak dikenali</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ( ! $accounts ) : ?>
                            <tr><td class="daw-account-settings__empty" colspan="6">Belum ada akun portal di Oracle.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <dialog class="daw-account-settings__dialog" data-account-dialog data-oracle-account-dialog aria-labelledby="daw-account-dialog-title">
        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
            <input type="hidden" name="action" value="daw_create_hr_account" data-account-action>
            <input type="hidden" name="_wpnonce" value="<?= esc_attr( wp_create_nonce( 'daw_create_hr_account' ) ) ?>" data-account-nonce data-department-nonce="<?= esc_attr( wp_create_nonce( 'daw_create_department_account' ) ) ?>">
            <header>
                <div>
                    <span class="daw-account-settings__dialog-eyebrow">AKUN PORTAL</span>
                    <h2 id="daw-account-dialog-title">Tambah Akun User</h2>
                </div>
                <button type="button" data-close-account-dialog aria-label="Tutup">&times;</button>
            </header>
            <div class="daw-account-settings__form-grid">
                <fieldset class="daw-account-settings__role-switch">
                    <legend>Role <span aria-hidden="true">*</span></legend>
                    <label class="is-selected"><input type="radio" name="account_type" value="hr" <?= $can_create_hr_account ? 'checked' : 'disabled' ?> data-account-type> HR Admin</label>
                    <label><input type="radio" name="account_type" value="department" <?= $can_create_hr_account ? '' : 'checked' ?> data-account-type> User Departemen</label>
                </fieldset>
                <div data-account-form="hr">
                    <label>Nama Lengkap<input type="text" name="display_name" maxlength="100" placeholder="contoh: Andi Wirawan" required></label>
                    <label>Email<input type="email" name="email" maxlength="254" autocomplete="email" required></label>
                    <label>Password<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
                    <label>Konfirmasi password<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>
                </div>
                <div data-account-form="department" hidden>
                    <label>Nama Lengkap<input type="text" name="display_name" maxlength="100" placeholder="contoh: Andi Wirawan" disabled required></label>
                    <input type="hidden" name="username" data-generated-username disabled>
                    <label>Email<input type="email" name="email" maxlength="50" autocomplete="email" disabled></label>
                    <label>Departemen<select name="department_id" <?= $departments ? '' : 'disabled' ?> required>
                        <option value="">Pilih departemen</option>
                        <?php foreach ( $departments as $department ) : ?>
                            <option value="<?= esc_attr( $department['id'] ) ?>"><?= esc_html( $department['name'] ) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label>Jabatan<input type="text" name="position" maxlength="100" placeholder="contoh: Kepala Departemen" disabled></label>
                    <fieldset class="daw-account-settings__permissions">
                        <legend>Hak Akses Fitur</legend>
                        <label><input type="checkbox" name="permissions[]" value="request_lowongan" checked><span><strong>Request Lowongan (FPTK)</strong><small>Membuat dan mengedit permintaan rekrutmen.</small></span></label>
                        <label><input type="checkbox" name="permissions[]" value="user_interview" checked><span><strong>Kandidat User Interview</strong><small>Melihat kandidat dan menginput penilaian.</small></span></label>
                        <label><input type="checkbox" name="permissions[]" value="interview_history" checked><span><strong>Riwayat Hasil Wawancara</strong><small>Melihat rekap penilaian yang telah dikirim.</small></span></label>
                        <label><input type="checkbox" name="permissions[]" value="recruitment_report"><span><strong>Laporan Recruitment</strong><small>Melihat laporan statistik recruitment departemen.</small></span></label>
                    </fieldset>
                    <p class="daw-account-settings__invitation">Email undangan login akan dikirim ke alamat yang didaftarkan bersama password sementara. User harus segera mengganti password setelah login.</p>
                </div>
            </div>
            <footer>
                <button type="button" class="daw-account-settings__cancel" data-close-account-dialog>Batal</button>
                <button type="submit" class="daw-account-settings__save">Simpan Akun</button>
            </footer>
        </form>
    </dialog>
    <dialog class="daw-account-settings__dialog" data-edit-oracle-dialog aria-labelledby="daw-edit-account-title">
        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
            <input type="hidden" name="action" value="daw_update_department_account">
            <input type="hidden" name="account_id" data-edit-account-id>
            <header><div><span class="daw-account-settings__dialog-eyebrow">AKUN DEPARTMENT</span><h2 id="daw-edit-account-title">Edit Akun</h2></div><button type="button" data-close-edit-account aria-label="Tutup">&times;</button></header>
            <div class="daw-account-settings__form-grid">
                <label>Username<input type="text" name="username" data-edit-account-username required></label>
                <label>Nama Lengkap<input type="text" name="full_name" data-edit-account-full-name required></label>
                <label>Email<input type="email" name="email" data-edit-account-email required></label>
                <label>Departemen<select name="department_id" data-edit-account-department required><?php foreach ( $departments as $department ) : ?><option value="<?= esc_attr( $department['id'] ) ?>"><?= esc_html( $department['name'] ) ?></option><?php endforeach; ?></select></label>
                <label>Password baru <small>(kosongkan jika tidak diubah)</small><input type="password" name="password" minlength="8" autocomplete="new-password"></label>
                <fieldset class="daw-account-settings__permissions"><legend>Hak Akses Fitur</legend>
                    <label><input type="checkbox" name="permissions[]" value="request_lowongan" checked><span><strong>Request Lowongan (FPTK)</strong><small>Membuat dan mengedit permintaan rekrutmen.</small></span></label>
                    <label><input type="checkbox" name="permissions[]" value="user_interview" checked><span><strong>Kandidat User Interview</strong><small>Melihat kandidat dan menginput penilaian.</small></span></label>
                    <label><input type="checkbox" name="permissions[]" value="interview_history" checked><span><strong>Riwayat Hasil Wawancara</strong><small>Melihat rekap penilaian yang telah dikirim.</small></span></label>
                    <label><input type="checkbox" name="permissions[]" value="recruitment_report"><span><strong>Laporan Recruitment</strong><small>Melihat laporan statistik recruitment departemen.</small></span></label>
                </fieldset>
            </div>
            <footer><button type="button" class="daw-account-settings__cancel" data-close-edit-account>Batal</button><button type="submit" class="daw-account-settings__save">Simpan Perubahan</button></footer>
            <?php wp_nonce_field( 'daw_update_department_account' ); ?>
        </form>
    </dialog>
    <script>
        (function () {
            var screen = document.querySelector('.daw-account-settings');
            var dialog = screen ? screen.querySelector('[data-oracle-account-dialog]') : null;
            var trigger = screen ? screen.querySelector('[data-open-account-dialog]') : null;
            if (!dialog || !trigger || trigger.dataset.accountDialogBound) {
                return;
            }
            trigger.dataset.accountDialogBound = '1';
            trigger.addEventListener('click', function () {
                if (typeof dialog.showModal === 'function' && !dialog.open) {
                    dialog.showModal();
                } else {
                    dialog.setAttribute('open', 'open');
                }
            });
            screen.querySelectorAll('[data-close-account-dialog]').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (typeof dialog.close === 'function') {
                        dialog.close();
                    } else {
                        dialog.removeAttribute('open');
                    }
                });
            });
        }());
    </script>

    <?php if ( current_user_can( 'manage_options' ) ) : ?>
        <section class="daw-account-settings__panel" aria-label="Buat akun Admin HR">
            <header>
                <h2>Buat akun Admin HR awal</h2>
                <p>Akun ini disimpan di Oracle. Password tidak disimpan dalam bentuk plaintext dan dapat diubah melalui fitur lupa password.</p>
            </header>
            <?php if ( isset( $hr_account_messages[ $hr_account_notice ] ) ) : ?>
                <div class="daw-account-settings__notice <?= esc_attr( 'error' === $hr_account_messages[ $hr_account_notice ][1] ? 'is-error' : '' ) ?>" role="status"><?= esc_html( $hr_account_messages[ $hr_account_notice ][0] ) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
                <input type="hidden" name="action" value="daw_create_hr_account">
                <?php wp_nonce_field( 'daw_create_hr_account' ); ?>
                <div class="daw-account-settings__form-grid">
                    <label>Email Admin HR<input type="email" name="email" maxlength="254" autocomplete="email" required></label>
                    <label>Password<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
                    <label>Konfirmasi password<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>
                </div>
                <footer><button type="submit" class="daw-account-settings__save">Buat akun Admin HR</button></footer>
            </form>
        </section>
    <?php endif; ?>
</div>
