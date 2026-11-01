<?php
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengelola akun pengguna.', 'recruitment-plugin' ) );
}

$page_url = admin_url( 'admin.php?page=recruitment-user-management' );
$departments = [ 'HR', 'Sales', 'After Sales', 'Finance', 'Body Repair' ];
$notice_code = sanitize_key( wp_unslash( $_GET['notice'] ?? '' ) );
$notice_messages = [
	'created' => 'Akun berhasil ditambahkan.',
	'updated' => 'Informasi akun berhasil diperbarui.',
	'deleted' => 'Akun berhasil dihapus.',
	'deactivated' => 'Akun berhasil dinonaktifkan. Sesi yang sedang berjalan telah dihentikan.',
	'activated' => 'Akun berhasil diaktifkan kembali.',
	'invalid' => 'Data akun belum lengkap atau tidak valid.',
	'password' => 'Password minimal 8 karakter (10 karakter untuk pelamar) dan konfirmasi harus sama.',
	'failed' => 'Akun tidak dapat diproses. Periksa email atau username yang dimasukkan.',
];

if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['daw_department_user_action'] ) ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengelola akun pengguna.', 'recruitment-plugin' ) );
	}

	$action = sanitize_key( wp_unslash( $_POST['daw_department_user_action'] ) );
	$notice = 'failed';

	if ( in_array( $action, [ 'delete', 'toggle' ], true ) ) {
		$user_id = absint( $_POST['user_id'] ?? 0 );
		check_admin_referer( 'daw_account_user_' . $action . '_' . $user_id );
		$target_user = $user_id ? get_user_by( 'id', $user_id ) : false;
		$is_managed_user = $target_user
			&& (
				'daw_applicant' === ( $target_user->roles[0] ?? '' )
				|| '1' === get_user_meta( $user_id, 'daw_department_user', true )
			)
			&& ! user_can( $target_user, 'manage_options' )
			&& $user_id !== get_current_user_id();

		if ( $is_managed_user && 'toggle' === $action ) {
			if ( '1' === get_user_meta( $user_id, 'daw_account_inactive', true ) ) {
				delete_user_meta( $user_id, 'daw_account_inactive' );
				$notice = 'activated';
			} else {
				update_user_meta( $user_id, 'daw_account_inactive', '1' );
				WP_Session_Tokens::get_instance( $user_id )->destroy_all();
				$notice = 'deactivated';
			}
		} elseif (
			$is_managed_user
			&& current_user_can( 'delete_user', $user_id )
		) {
			if ( ! function_exists( 'wp_delete_user' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}
			if ( wp_delete_user( $user_id, get_current_user_id() ) ) {
				$notice = 'deleted';
			}
		}
	} elseif ( 'save' === $action ) {
		check_admin_referer( 'daw_department_user_save' );

		$user_id = absint( $_POST['user_id'] ?? 0 );
		$username = sanitize_user( wp_unslash( $_POST['user_login'] ?? '' ), true );
		$display_name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );
		$department = sanitize_text_field( wp_unslash( $_POST['department'] ?? '' ) );
		$password = (string) wp_unslash( $_POST['user_pass'] ?? '' );
		$password_confirmation = (string) wp_unslash( $_POST['user_pass_confirmation'] ?? '' );
		$target_user = $user_id ? get_user_by( 'id', $user_id ) : false;
		$is_department_user = $target_user && '1' === get_user_meta( $user_id, 'daw_department_user', true );
		$is_applicant = $target_user && in_array( 'daw_applicant', (array) $target_user->roles, true );
		$account_type = sanitize_key( wp_unslash( $_POST['account_type'] ?? 'department' ) );
		$is_managed_user = $target_user
			&& ( $is_department_user || $is_applicant )
			&& ! user_can( $target_user, 'manage_options' );
		$is_valid_type = $user_id
			? $is_managed_user
			: in_array( $account_type, [ 'department', 'applicant' ], true );
		$is_applicant_account = $user_id ? $is_applicant : 'applicant' === $account_type;
		$valid_data = '' !== $display_name && is_email( $email );
		$valid_data = $valid_data && ( $user_id ? $is_managed_user : ( '' !== $username && validate_username( $username ) ) );
		$valid_data = $valid_data && $is_valid_type;
		$valid_data = $valid_data && ( $is_applicant_account ? '' === trim( $department ) : in_array( $department, $departments, true ) );
		$valid_password = $user_id && '' === $password
			? '' === $password_confirmation
			: strlen( $password ) >= ( $is_applicant_account ? 10 : 8 ) && $password === $password_confirmation;

		if ( ! $valid_data ) {
			$notice = 'invalid';
		} elseif ( ! $valid_password ) {
			$notice = 'password';
		} else {
			$user_data = [
				'user_email' => $email,
				'display_name' => $display_name,
			];
			if ( $user_id ) {
				$user_data['ID'] = $user_id;
			} else {
				$user_data['user_login'] = $username;
				$user_data['role'] = 'applicant' === $account_type ? 'daw_applicant' : 'subscriber';
			}
			if ( '' !== $password ) {
				$user_data['user_pass'] = $password;
			}

			$saved_user = $user_id ? wp_update_user( $user_data ) : wp_insert_user( $user_data );
			if ( ! is_wp_error( $saved_user ) ) {
				if ( $user_id ? $is_department_user : 'department' === $account_type ) {
					update_user_meta( $saved_user, 'daw_department_user', '1' );
					update_user_meta( $saved_user, 'daw_department', $department );
				}
				$notice = $user_id ? 'updated' : 'created';
			} else {
				error_log( 'Recruitment account save failed: ' . $saved_user->get_error_message() );
			}
		}
	}

	wp_safe_redirect( add_query_arg( 'notice', $notice, $page_url ) );
	exit;
}

$search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
$department_filter = sanitize_text_field( wp_unslash( $_GET['department'] ?? '' ) );
$role_filter = sanitize_key( wp_unslash( $_GET['role'] ?? '' ) );
$status_filter = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) );
if ( ! in_array( $department_filter, $departments, true ) ) {
	$department_filter = '';
}
if ( ! in_array( $role_filter, [ '', 'administrator', 'department', 'daw_applicant', 'other' ], true ) ) {
	$role_filter = '';
}
if ( ! in_array( $status_filter, [ '', 'active', 'inactive' ], true ) ) {
	$status_filter = '';
}
$all_users = get_users( [ 'orderby' => 'display_name', 'order' => 'ASC' ] );
$active_user_count = 0;
$inactive_user_count = 0;
$visible_users = [];

foreach ( $all_users as $account_user ) {
	$is_department_user = '1' === get_user_meta( $account_user->ID, 'daw_department_user', true )
		&& ! user_can( $account_user, 'manage_options' );
	$is_applicant = in_array( 'daw_applicant', (array) $account_user->roles, true );
	$is_admin = user_can( $account_user, 'manage_options' );
	$role_slug = $account_user->roles[0] ?? '';
	$account_role = $is_admin ? 'administrator' : ( $is_department_user ? 'department' : ( $is_applicant ? 'daw_applicant' : 'other' ) );
	$account_department = $is_department_user
		? (string) get_user_meta( $account_user->ID, 'daw_department', true )
		: (string) get_user_meta( $account_user->ID, 'department', true );
	$is_inactive = '1' === get_user_meta( $account_user->ID, 'daw_account_inactive', true );
	if ( $is_inactive ) {
		++$inactive_user_count;
	} else {
		++$active_user_count;
	}

	$searchable = strtolower( $account_user->display_name . ' ' . $account_user->user_login . ' ' . $account_user->user_email );
	$matches_search = '' === $search || false !== strpos( $searchable, strtolower( $search ) );
	$matches_department = '' === $department_filter || $account_department === $department_filter;
	$matches_role = '' === $role_filter || $account_role === $role_filter;
	$matches_status = '' === $status_filter || ( 'inactive' === $status_filter ) === $is_inactive;
	if ( $matches_search && $matches_department && $matches_role && $matches_status ) {
		$visible_users[] = [
			'user' => $account_user,
			'is_department_user' => $is_department_user,
			'is_applicant' => $is_applicant,
			'is_admin' => $is_admin,
			'department' => $account_department,
			'role' => $account_role,
			'role_slug' => $role_slug,
			'is_inactive' => $is_inactive,
		];
	}
}
?>
<div class="daw-account-settings">
	<header class="daw-account-settings__heading">
		<div>
			<p class="daw-account-settings__breadcrumb">DAW Admin <span>/</span> Pengelolaan Akun User</p>
			<h1>Pengelolaan Akun User</h1>
			<p>Lihat dan kelola akun yang terdaftar melalui Beranda serta akun pengguna internal.</p>
		</div>
		<button class="daw-account-settings__add" type="button" data-open-account-dialog>
			<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> Tambah Akun
		</button>
	</header>

	<?php if ( isset( $notice_messages[ $notice_code ] ) ) : ?>
		<div class="daw-account-settings__notice <?= in_array( $notice_code, [ 'invalid', 'password', 'failed' ], true ) ? 'is-error' : 'is-success' ?>" role="status"><?= esc_html( $notice_messages[ $notice_code ] ) ?></div>
	<?php endif; ?>

	<section class="daw-account-settings__stats" aria-label="Ringkasan akun">
		<article><span>Total Akun</span><strong><?= esc_html( number_format_i18n( count( $all_users ) ) ) ?></strong></article>
		<article><span>Akun Aktif</span><strong><?= esc_html( number_format_i18n( $active_user_count ) ) ?></strong></article>
		<article><span>Akun Nonaktif</span><strong><?= esc_html( number_format_i18n( $inactive_user_count ) ) ?></strong></article>
	</section>

	<section class="daw-account-settings__panel" aria-label="Daftar akun pengguna">
		<form class="daw-account-settings__filters" method="get" action="<?= esc_url( admin_url( 'admin.php' ) ) ?>">
			<input type="hidden" name="page" value="recruitment-user-management" />
			<label class="daw-account-settings__search"><span class="screen-reader-text">Cari akun</span><span class="dashicons dashicons-search" aria-hidden="true"></span><input type="search" name="search" value="<?= esc_attr( $search ) ?>" placeholder="Cari nama, username, atau email" /></label>
			<label class="screen-reader-text" for="daw-account-department-filter">Filter departemen</label>
			<select id="daw-account-department-filter" name="department">
				<option value="">Semua departemen</option>
				<?php foreach ( $departments as $department ) : ?>
					<option value="<?= esc_attr( $department ) ?>" <?= selected( $department_filter, $department, false ) ?>><?= esc_html( $department ) ?></option>
				<?php endforeach; ?>
			</select>
			<label class="screen-reader-text" for="daw-account-role-filter">Filter role</label>
			<select id="daw-account-role-filter" name="role">
				<option value="">Semua role</option>
				<option value="administrator" <?= selected( $role_filter, 'administrator', false ) ?>>HR Admin</option>
				<option value="department" <?= selected( $role_filter, 'department', false ) ?>>User Departemen</option>
				<option value="daw_applicant" <?= selected( $role_filter, 'daw_applicant', false ) ?>>Pelamar</option>
				<option value="other" <?= selected( $role_filter, 'other', false ) ?>>Lainnya</option>
			</select>
			<label class="screen-reader-text" for="daw-account-status-filter">Filter status akun</label>
			<select id="daw-account-status-filter" name="status">
				<option value="">Semua status</option>
				<option value="active" <?= selected( $status_filter, 'active', false ) ?>>Aktif</option>
				<option value="inactive" <?= selected( $status_filter, 'inactive', false ) ?>>Nonaktif</option>
			</select>
			<button type="submit" class="daw-account-settings__filter-button">Terapkan</button>
			<a class="daw-account-settings__reset" href="<?= esc_url( $page_url ) ?>" aria-label="Reset filter">Reset</a>
		</form>

		<div class="daw-account-settings__table-wrap">
			<table class="daw-account-settings__table">
				<thead><tr><th scope="col">Nama</th><th scope="col">Email</th><th scope="col">Departemen</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Last Login</th><th scope="col">Aksi</th></tr></thead>
				<tbody>
					<?php foreach ( $visible_users as $row ) :
						$account_user = $row['user'];
						$role_name = $row['is_applicant']
							? 'Pelamar'
							: ( $row['is_department_user']
								? 'User Departemen'
								: ( $row['is_admin']
									? 'HR Admin'
									: ( $row['role_slug'] && isset( wp_roles()->roles[ $row['role_slug'] ]['name'] ) ? translate_user_role( wp_roles()->roles[ $row['role_slug'] ]['name'] ) : '—' ) ) );
						$is_managed_user = $row['is_applicant'] || $row['is_department_user'];
						$last_login = (string) get_user_meta( $account_user->ID, 'daw_last_login', true );
						?>
						<tr>
							<td data-label="Nama"><span class="daw-account-settings__avatar" aria-hidden="true"><?= esc_html( strtoupper( substr( $account_user->display_name ?: $account_user->user_login, 0, 2 ) ) ) ?></span><strong><?= esc_html( $account_user->display_name ?: $account_user->user_login ) ?></strong></td>
							<td data-label="Email"><a href="mailto:<?= esc_attr( $account_user->user_email ) ?>"><?= esc_html( $account_user->user_email ) ?></a></td>
							<td data-label="Departemen"><?= esc_html( $row['department'] ?: '—' ) ?></td>
							<td data-label="Role"><span class="daw-account-settings__role <?= $row['is_admin'] ? 'is-admin' : ( $row['is_department_user'] ? 'is-department' : ( $row['is_applicant'] ? 'is-applicant' : '' ) ) ?>"><?= esc_html( $role_name ) ?></span></td>
							<td data-label="Status"><span class="daw-account-settings__status <?= $row['is_inactive'] ? 'is-inactive' : 'is-active' ?>"><?= $row['is_inactive'] ? 'Nonaktif' : 'Aktif' ?></span></td>
							<td data-label="Last Login"><?= '' !== $last_login ? esc_html( mysql2date( 'd M Y', $last_login ) ) : '—' ?></td>
							<td data-label="Aksi">
								<?php if ( $is_managed_user && $account_user->ID !== get_current_user_id() ) : ?>
									<div class="daw-account-settings__actions daw-account-settings__actions--text">
										<button type="button" class="daw-account-settings__text-action" data-edit-account data-account-id="<?= esc_attr( $account_user->ID ) ?>" data-account-login="<?= esc_attr( $account_user->user_login ) ?>" data-account-name="<?= esc_attr( $account_user->display_name ) ?>" data-account-email="<?= esc_attr( $account_user->user_email ) ?>" data-account-department="<?= esc_attr( $row['department'] ) ?>" data-account-kind="<?= $row['is_applicant'] ? 'applicant' : 'department' ?>">Edit</button>
										<span aria-hidden="true">|</span>
										<form method="post" action="<?= esc_url( $page_url ) ?>" onsubmit="return confirm('<?= $row['is_inactive'] ? 'Aktifkan' : 'Nonaktifkan' ?> akun <?= esc_js( $account_user->user_login ) ?>?');">
											<input type="hidden" name="daw_department_user_action" value="toggle" />
											<input type="hidden" name="user_id" value="<?= esc_attr( $account_user->ID ) ?>" />
											<?php wp_nonce_field( 'daw_account_user_toggle_' . $account_user->ID ); ?>
											<button type="submit" class="daw-account-settings__text-action"><?= $row['is_inactive'] ? 'Aktifkan' : 'Nonaktifkan' ?></button>
										</form>
										<span aria-hidden="true">|</span>
										<form method="post" action="<?= esc_url( $page_url ) ?>" onsubmit="return confirm('Hapus akun <?= esc_js( $account_user->user_login ) ?>? Tindakan ini tidak dapat dibatalkan.');">
											<input type="hidden" name="daw_department_user_action" value="delete" />
											<input type="hidden" name="user_id" value="<?= esc_attr( $account_user->ID ) ?>" />
											<?php wp_nonce_field( 'daw_account_user_delete_' . $account_user->ID ); ?>
											<button type="submit" class="daw-account-settings__text-action is-danger">Hapus</button>
										</form>
									</div>
								<?php else : ?>
									<span class="daw-account-settings__protected"><?= $row['is_admin'] ? 'Akun admin dilindungi' : 'Tidak dapat dikelola di sini' ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $visible_users ) ) : ?>
						<tr><td class="daw-account-settings__empty" colspan="7"><?= $search || $department_filter || $role_filter || $status_filter ? 'Tidak ada akun yang sesuai dengan filter.' : 'Belum ada akun pengguna.' ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<footer class="daw-account-settings__table-footer"><span><?= esc_html( number_format_i18n( count( $visible_users ) ) ) ?> akun ditampilkan</span></footer>
	</section>

	<dialog class="daw-account-settings__dialog" data-account-dialog aria-labelledby="daw-account-dialog-title">
		<form method="post" action="<?= esc_url( $page_url ) ?>" data-account-form>
			<input type="hidden" name="daw_department_user_action" value="save" />
			<input type="hidden" name="user_id" value="" data-account-id />
			<?php wp_nonce_field( 'daw_department_user_save' ); ?>
			<header><div><span class="daw-account-settings__dialog-eyebrow">AKUN PENGGUNA</span><h2 id="daw-account-dialog-title" data-account-dialog-title>Tambah Akun</h2></div><button type="button" data-close-account-dialog aria-label="Tutup"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></header>
			<div class="daw-account-settings__form-grid">
				<label>Nama Lengkap<input type="text" name="display_name" required maxlength="100" data-account-name /></label>
				<label>Username<input type="text" name="user_login" required maxlength="60" autocomplete="username" data-account-login /></label>
				<label>Email<input type="email" name="user_email" required autocomplete="email" data-account-email /></label>
				<label data-account-type-field>Jenis Akun<select name="account_type" data-account-type><option value="department">User Departemen</option><option value="applicant">Pelamar</option></select></label>
				<label data-account-department-field>Departemen<select name="department" data-account-department><option value="">Pilih departemen</option><?php foreach ( $departments as $department ) : ?><option value="<?= esc_attr( $department ) ?>"><?= esc_html( $department ) ?></option><?php endforeach; ?></select></label>
				<label>Password Baru<input type="password" name="user_pass" minlength="8" autocomplete="new-password" data-account-password /><small data-password-help>Minimal 8 karakter untuk User Departemen; 10 karakter untuk Pelamar.</small></label>
				<label>Konfirmasi Password<input type="password" name="user_pass_confirmation" minlength="8" autocomplete="new-password" data-account-password-confirm /></label>
			</div>
			<footer><button type="button" class="daw-account-settings__cancel" data-close-account-dialog>Batal</button><button type="submit" class="daw-account-settings__save"><span class="dashicons dashicons-saved" aria-hidden="true"></span> Simpan Akun</button></footer>
		</form>
	</dialog>
</div>