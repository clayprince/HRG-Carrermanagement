<?php
$errors = [
	'invalid_request' => 'Sesi tidak valid. Muat ulang halaman lalu coba kembali.',
	'invalid_credentials' => 'Email atau password tidak sesuai, atau akun bukan akun kandidat.',
	'no_access' => 'Akun yang sedang digunakan bukan akun kandidat. Keluar terlebih dahulu untuk masuk ke akun kandidat.',
	'invalid_registration' => 'Masukkan nama dan email yang valid serta password minimal 12 karakter.',
	'email_exists' => 'Email sudah terdaftar. Silakan masuk menggunakan akun Anda.',
	'registration_failed' => 'Akun belum dapat dibuat. Pastikan koneksi database tersedia lalu coba lagi.',
];
$error = $errors[ sanitize_key( wp_unslash( $_GET['account_error'] ?? '' ) ) ] ?? '';
$current_user = wp_get_current_user();
$logged_in_candidate = is_user_logged_in() && in_array( 'daw_candidate', (array) $current_user->roles, true );
$logged_in_other_role = is_user_logged_in() && ! $logged_in_candidate;
$redirect_to = wp_validate_redirect(
	(string) wp_unslash( $_GET['redirect_to'] ?? '' ),
	recruitment_get_public_url( 'tracking' )
);
?>
<div class="daw-recruitment daw-recruitment--candidate-account">
	<?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
	<main class="daw-recruitment__section">
		<div class="daw-recruitment__container">
			<section class="daw-recruitment__panel">
				<h1>Akun Kandidat</h1>
				<?php if ( $logged_in_candidate ) : ?>
					<p>Anda sudah masuk ke akun kandidat.</p>
					<a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'tracking' ) ) ?>">Lihat Status Lamaran</a>
				<?php elseif ( $logged_in_other_role ) : ?>
					<p>Akun yang aktif bukan akun kandidat. Keluar dari akun tersebut sebelum melanjutkan.</p>
					<a class="daw-recruitment__button" href="<?= esc_url( wp_logout_url( recruitment_get_public_url( 'account' ) ) ) ?>">Keluar</a>
				<?php else : ?>
					<p>Masuk atau buat akun untuk melihat perkembangan seleksi Anda.</p>
					<?php if ( $error ) : ?><div class="daw-recruitment__alert" role="alert"><?= esc_html( $error ) ?></div><?php endif; ?>
					<div class="daw-recruitment__form-fields">
						<form method="post" action="<?= esc_url( recruitment_get_public_url( 'account' ) ) ?>">
							<h2>Masuk</h2>
							<input type="hidden" name="account_action" value="login">
							<input type="hidden" name="redirect_to" value="<?= esc_url( $redirect_to ) ?>">
							<?php wp_nonce_field( 'daw_candidate_account' ); ?>
							<label>Email<input name="email" type="email" autocomplete="email" required></label>
							<label>Password<input name="password" type="password" autocomplete="current-password" required></label>
							<button class="daw-recruitment__button" type="submit">Masuk</button>
						</form>
						<form method="post" action="<?= esc_url( recruitment_get_public_url( 'account' ) ) ?>">
							<h2>Buat Akun</h2>
							<input type="hidden" name="account_action" value="register">
							<input type="hidden" name="redirect_to" value="<?= esc_url( $redirect_to ) ?>">
							<?php wp_nonce_field( 'daw_candidate_account' ); ?>
							<label>Nama lengkap<input name="full_name" autocomplete="name" required></label>
							<label>Email<input name="email" type="email" autocomplete="email" required></label>
							<label>Nomor telepon <span>(opsional)</span><input name="phone" type="tel" autocomplete="tel"></label>
							<label>Password<input name="password" type="password" autocomplete="new-password" minlength="12" required><small>Minimal 12 karakter.</small></label>
							<button class="daw-recruitment__button" type="submit">Daftar</button>
						</form>
					</div>
				<?php endif; ?>
			</section>
		</div>
	</main>
	<?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
