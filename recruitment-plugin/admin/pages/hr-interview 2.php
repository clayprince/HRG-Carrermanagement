<?php
$user = wp_get_current_user();
$hr_identity = recruitment_get_current_hr_identity();
$display_name = $hr_identity['name'];
$user_email = $hr_identity['email'] ?: 'hr@daw.co.id';
$logout_form = recruitment_logout_form( 'Keluar', home_url( '/' ), 'daw-user-interview__logout' );

$database = new Recruitment_Database();
$candidates = $database->get_hr_interview_candidates();
$load_error = $database->has_error();
$department_accounts = array_values(
	array_filter(
		$database->get_department_accounts(),
		static fn ( array $account ): bool => 'ACTIVE' === strtoupper( trim( (string) ( $account['status'] ?? '' ) ) )
	)
);
$load_error = $load_error || $database->has_error();
$notice = sanitize_key( wp_unslash( $_GET['hr_interview'] ?? '' ) );
?>
<div class="daw-user-interview daw-interview-screen">
    <header class="daw-user-interview__header">
        <nav class="daw-user-interview__breadcrumb" aria-label="Breadcrumb">
            <span class="daw-user-interview__breadcrumb-root">Recruitment</span>
            <span aria-hidden="true">/</span>
            <strong>Wawancara HR</strong>
        </nav>
        <div class="daw-user-interview__account">
            <span class="daw-user-interview__avatar" aria-hidden="true"><?= esc_html( $hr_identity['avatar'] ) ?></span>
            <span class="daw-user-interview__account-name"><strong><?= esc_html( $display_name ) ?></strong><small><?= esc_html( $user_email ) ?></small></span>
            <?= $logout_form ?>
        </div>
    </header>

	<main class="daw-user-interview__main">
		<div class="daw-interview-screen__heading">
			<div><h1>Wawancara HR</h1><p>Catat hasil wawancara kandidat yang berada pada tahap HR dan tentukan akun User Department penerima.</p></div>
		</div>
		<?php if ( 'saved' === $notice ) : ?><div class="notice notice-success inline"><p>Hasil wawancara HR tersimpan. Kandidat lolos sudah diteruskan ke akun User Department yang dipilih.</p></div><?php elseif ( 'failed' === $notice ) : ?><div class="notice notice-success inline"><p>Hasil wawancara tersimpan. Kandidat tidak dilanjutkan ke tahap berikutnya.</p></div><?php endif; ?>
		<?php if ( $load_error ) : ?><div class="notice notice-error inline" role="alert"><p>Data wawancara atau akun User Department tidak dapat dimuat dari Oracle.</p></div><?php endif; ?>
		<section class="daw-interview-screen__stats" aria-label="Ringkasan wawancara HR">
			<article class="is-waiting"><strong><?= esc_html( (string) count( $candidates ) ) ?></strong><span>Menunggu Wawancara</span></article>
			<article class="is-scheduled"><strong><?= esc_html( (string) count( $department_accounts ) ) ?></strong><span>Akun User Department Aktif</span></article>
		</section>
		<section class="daw-user-interview__panel daw-interview-screen__panel" aria-labelledby="hr-interview-candidates-title">
			<div class="daw-interview-screen__panel-heading"><h2 id="hr-interview-candidates-title">Kandidat Tahap Wawancara HR</h2></div>
			<div class="daw-user-interview__table-wrap">
				<table class="daw-interview-screen__table">
					<caption class="screen-reader-text">Kandidat menunggu keputusan wawancara HR</caption>
					<thead><tr><th scope="col">Kandidat</th><th scope="col">Posisi / Departemen</th><th scope="col">Dealer</th><th scope="col">Nilai Psikotes</th><th scope="col">Keputusan HR</th></tr></thead>
					<tbody>
						<?php if ( ! empty( $candidates ) ) : ?>
							<?php foreach ( $candidates as $candidate ) :
								$application_id = absint( $candidate['application_id'] ?? 0 );
								$department = (string) ( $candidate['department'] ?? '' );
								$matching_accounts = array_values(
									array_filter(
										$department_accounts,
										static fn ( array $account ): bool => 0 === strcasecmp( trim( (string) ( $account['department'] ?? '' ) ), trim( $department ) )
									)
								);
								?>
								<tr>
									<td><span class="daw-user-interview__candidate"><?= esc_html( (string) ( $candidate['candidate_name'] ?? 'Pelamar' ) ) ?></span><small class="daw-user-interview__code">Lamaran #<?= esc_html( (string) $application_id ) ?></small></td>
									<td><?= esc_html( (string) ( $candidate['position'] ?? 'Posisi belum ditentukan' ) ) ?><small><?= esc_html( $department ) ?></small></td>
									<td><?= esc_html( (string) ( $candidate['dealer'] ?? '-' ) ) ?></td>
									<td><strong class="daw-interview-screen__score"><?= esc_html( (string) ( $candidate['psychotest_score'] ?? '—' ) ) ?></strong></td>
									<td>
										<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
											<input type="hidden" name="action" value="daw_save_hr_interview">
											<input type="hidden" name="application_id" value="<?= esc_attr( (string) $application_id ) ?>">
											<?php wp_nonce_field( 'daw_save_hr_interview_' . $application_id ); ?>
											<label>Hasil
												<select name="result" required>
													<option value="">Pilih hasil</option>
													<option value="LOLOS">Lolos ke wawancara User</option>
													<option value="TIDAK_LOLOS">Tidak lolos</option>
												</select>
											</label>
											<label>Akun penerima
												<select name="department_account_id">
													<option value="0">Pilih akun departemen</option>
													<?php foreach ( $matching_accounts as $account ) : ?>
														<option value="<?= esc_attr( (string) $account['id'] ) ?>"><?= esc_html( (string) $account['username'] . ' (' . (string) $account['email'] . ')' ) ?></option>
													<?php endforeach; ?>
												</select>
											</label>
											<?php if ( empty( $matching_accounts ) ) : ?><p class="notice notice-error inline">Tidak ada akun User Department aktif untuk <?= esc_html( $department ) ?>; kandidat tidak dapat diteruskan ke departemen.</p><?php endif; ?>
											<label>Catatan HR<textarea name="notes" rows="2" maxlength="2000"></textarea></label>
											<button type="submit" class="button button-primary">Simpan Hasil HR</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr><td colspan="5" style="text-align:center; padding:32px 16px; color:#6b7280;">Belum ada kandidat pada tahap Wawancara HR.</td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</main>
</div>
