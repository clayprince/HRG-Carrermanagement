<?php
$hr_identity = recruitment_get_current_hr_identity();
$display_name = $hr_identity['name'];
$user_email = $hr_identity['email'] ?: 'hr@daw.co.id';
$logout_form = recruitment_logout_form( 'Keluar', home_url( '/' ), 'daw-user-interview__logout' );
$list_url = recruitment_get_admin_url( 'hr-interview' );

$database = new Recruitment_Database();
$candidates = $database->get_hr_interview_candidates();
$load_error = $database->has_error();
$departments = $database->get_departments();
$load_error = $load_error || $database->has_error();
$department_accounts = array_values(
	array_filter(
		$database->get_department_accounts(),
		static fn ( array $account ): bool => 'ACTIVE' === strtoupper( trim( (string) ( $account['status'] ?? '' ) ) )
	)
);
$load_error = $load_error || $database->has_error();

$application_id = absint( $_GET['application_id'] ?? 0 );
$selected_candidate = null;
foreach ( $candidates as $candidate ) {
	if ( $application_id === absint( $candidate['application_id'] ?? 0 ) ) {
		$selected_candidate = $candidate;
		break;
	}
}

$history = [];
if ( $selected_candidate ) {
	$history = $database->get_hr_interview_history( (string) $application_id );
	$load_error = $load_error || $database->has_error();
}

$latest_note = $history[0] ?? [];
$notice = sanitize_key( wp_unslash( $_GET['hr_interview'] ?? '' ) );
$selected_department = trim( (string) ( $latest_note['department'] ?? '' ) );
foreach ( $departments as $department ) {
	if ( 0 === strcasecmp( trim( (string) ( $department['name'] ?? '' ) ), $selected_department ) ) {
		$selected_department = (string) ( $department['name'] ?? $selected_department );
		break;
	}
}
$selected_account_id = (string) ( $latest_note['assigned_account_id'] ?? '' );
$interview_date = (string) ( $latest_note['interview_date'] ?? $selected_candidate['scheduled_interview_date'] ?? current_time( 'Y-m-d' ) );
$scheduled_date_timestamp = ! empty( $selected_candidate['scheduled_interview_date'] )
	? strtotime( (string) $selected_candidate['scheduled_interview_date'] )
	: false;
$scheduled_interview_date = $scheduled_date_timestamp ? date_i18n( 'j F Y', $scheduled_date_timestamp ) : '—';
?>
<div class="daw-user-interview daw-interview-screen">
	<header class="daw-user-interview__header">
		<nav class="daw-user-interview__breadcrumb" aria-label="Breadcrumb">
			<span class="daw-user-interview__breadcrumb-root">DAW Admin</span>
			<span aria-hidden="true">/</span>
			<?php if ( $selected_candidate ) : ?>
				<strong>Catatan Wawancara HR</strong>
			<?php else : ?>
				<strong>Wawancara HR</strong>
			<?php endif; ?>
		</nav>
		<div class="daw-user-interview__account">
			<span class="daw-user-interview__avatar" aria-hidden="true"><?= esc_html( $hr_identity['avatar'] ) ?></span>
			<span class="daw-user-interview__account-name"><strong><?= esc_html( $display_name ) ?></strong><small><?= esc_html( $user_email ) ?></small></span>
			<?= $logout_form ?>
		</div>
	</header>

	<main class="daw-user-interview__main">
		<?php if ( 'note_saved' === $notice ) : ?><div class="notice notice-success inline"><p>Catatan wawancara HR berhasil disimpan.</p></div><?php elseif ( 'saved' === $notice ) : ?><div class="notice notice-success inline"><p>Hasil tersimpan. Kandidat telah diteruskan ke Wawancara User dan tidak lagi muncul di daftar Wawancara HR.</p></div><?php elseif ( 'failed' === $notice ) : ?><div class="notice notice-success inline"><p>Hasil tersimpan. Kandidat tidak dilanjutkan dan tidak lagi muncul di daftar Wawancara HR.</p></div><?php elseif ( 'save_error' === $notice ) : ?><div class="notice notice-error inline" role="alert"><p>Penyimpanan gagal ke Oracle. Log server menunjukkan objek database belum ditemukan; minta pemilik Oracle meninjau dan menerapkan migrasi 017 sebelum mencoba lagi.</p></div><?php endif; ?>
		<?php if ( $load_error ) : ?><div class="notice notice-error inline" role="alert"><p>Data wawancara HR, akun tujuan, atau riwayat catatan tidak dapat dimuat dari Oracle. Periksa penerapan migrasi database yang diperlukan.</p></div><?php endif; ?>

		<?php if ( $selected_candidate ) : ?>
			<a class="daw-user-interview__back" href="<?= esc_url( $list_url ) ?>"><span aria-hidden="true">&#8249;</span> Kembali ke Daftar Wawancara</a>

			<div class="daw-hr-interview__detail-grid">
				<section class="daw-hr-interview__card" aria-labelledby="hr-candidate-title">
					<h2 id="hr-candidate-title">Kandidat</h2>
					<div class="daw-hr-interview__candidate-profile">
						<span class="daw-hr-interview__candidate-avatar" aria-hidden="true"><?= esc_html( strtoupper( substr( (string) ( $selected_candidate['candidate_name'] ?? 'PL' ), 0, 2 ) ) ) ?></span>
						<span><strong><?= esc_html( (string) ( $selected_candidate['candidate_name'] ?? 'Pelamar' ) ) ?></strong><small>DAW-<?= esc_html( gmdate( 'Y', strtotime( (string) ( $selected_candidate['created_at'] ?? 'now' ) ) ?: time() ) ) ?>-<?= esc_html( str_pad( (string) ( $selected_candidate['candidate_id'] ?? $application_id ), 6, '0', STR_PAD_LEFT ) ) ?></small></span>
					</div>
					<dl class="daw-hr-interview__candidate-facts">
						<div><dt>Posisi</dt><dd><?= esc_html( (string) ( $selected_candidate['position'] ?? 'Posisi belum ditentukan' ) ) ?></dd></div>
						<div><dt>Dealer</dt><dd><?= esc_html( (string) ( $selected_candidate['dealer'] ?? '—' ) ) ?></dd></div>
						<div><dt>Tgl Wawancara</dt><dd><?= esc_html( $scheduled_interview_date ) ?></dd></div>
						<div><dt>Waktu</dt><dd><?= esc_html( (string) ( $selected_candidate['scheduled_interview_time'] ?? '—' ) ) ?></dd></div>
					</dl>
					<a class="daw-hr-interview__profile-link" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-applicants', 'application_id' => $application_id ], admin_url( 'admin.php' ) ) ) ?>">Lihat Profil Lengkap</a>
				</section>

				<div class="daw-hr-interview__detail-content">
					<section class="daw-hr-interview__card" aria-labelledby="hr-notes-title">
						<h2 id="hr-notes-title">Catatan Wawancara HR</h2>
						<form class="daw-hr-interview__form" method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" data-hr-interview-form>
							<input type="hidden" name="action" value="daw_save_hr_interview">
							<input type="hidden" name="application_id" value="<?= esc_attr( (string) $application_id ) ?>">
							<?php wp_nonce_field( 'daw_save_hr_interview_' . $application_id ); ?>
							<input type="hidden" name="intent" value="save_note" data-hr-intent>

							<div class="daw-hr-interview__form-grid">
								<label>Interviewer
									<input type="text" value="<?= esc_attr( $display_name ) ?>" readonly aria-label="Interviewer">
								</label>
								<label>Tanggal Wawancara
									<input type="date" name="interview_date" value="<?= esc_attr( $interview_date ) ?>" required>
								</label>
							</div>

							<label>Catatan Wawancara
								<textarea name="notes" rows="3" maxlength="2000" placeholder="Catat observasi, poin diskusi, dan kesan umum kandidat..."><?= esc_textarea( (string) ( $latest_note['notes'] ?? '' ) ) ?></textarea>
							</label>

							<fieldset class="daw-hr-interview__choice-group">
								<legend>Penilaian</legend>
								<div class="daw-hr-interview__rating-options">
									<?php foreach ( [ 'Sangat Baik', 'Baik', 'Cukup', 'Kurang' ] as $index => $rating ) : ?>
										<label><input type="radio" name="rating" value="<?= esc_attr( $rating ) ?>" <?= checked( (string) ( $latest_note['rating'] ?? '' ), $rating, false ) ?> <?= 0 === $index ? 'required' : '' ?>><span><?= esc_html( $rating ) ?></span></label>
									<?php endforeach; ?>
								</div>
							</fieldset>
							<fieldset class="daw-hr-interview__choice-group">
								<legend>Rekomendasi</legend>
								<div class="daw-hr-interview__recommendation-options">
									<?php foreach ( [ 'Lanjut ke Tahap Berikutnya', 'Tidak Dilanjutkan' ] as $index => $recommendation ) : ?>
										<label><input type="radio" name="recommendation" value="<?= esc_attr( $recommendation ) ?>" <?= checked( (string) ( $latest_note['recommendation'] ?? '' ), $recommendation, false ) ?> <?= 0 === $index ? 'required' : '' ?>><span><?= esc_html( $recommendation ) ?></span></label>
									<?php endforeach; ?>
								</div>
							</fieldset>
							<button type="submit" value="save_note" class="daw-hr-interview__save-note" data-hr-save-note>Simpan Catatan</button>

							<div class="daw-hr-interview__decision" aria-labelledby="hr-decision-title">
								<h3 id="hr-decision-title">KEPUTUSAN WAWANCARA HR</h3>
								<div class="daw-hr-interview__decision-options">
									<label class="daw-hr-interview__decision-pass"><input type="radio" name="decision" value="pass"><span aria-hidden="true">✓</span> ACC / Lolos</label>
									<label class="daw-hr-interview__decision-fail"><input type="radio" name="decision" value="fail"><span aria-hidden="true">×</span> Tidak Lolos</label>
								</div>
							</div>

							<section class="daw-hr-interview__history-section" aria-labelledby="hr-history-title">
								<h3 id="hr-history-title">Riwayat Catatan Sebelumnya</h3>
								<?php if ( ! empty( $history ) ) : ?>
									<ol class="daw-hr-interview__history">
										<?php foreach ( $history as $entry ) : ?>
											<li>
												<blockquote><?= nl2br( esc_html( (string) ( $entry['notes'] ?? '' ) ) ) ?></blockquote>
												<p><?= esc_html( (string) ( $entry['created_at'] ?? $entry['interview_date'] ?? '' ) ) ?> · <?= esc_html( (string) ( $entry['interviewer'] ?? 'Catatan wawancara' ) ) ?></p>
											</li>
										<?php endforeach; ?>
									</ol>
								<?php else : ?>
									<p class="daw-hr-interview__empty-history">Belum ada catatan wawancara sebelumnya.</p>
								<?php endif; ?>
							</section>

							<fieldset class="daw-hr-interview__department">
								<legend>REKOMENDASI DEPARTEMEN</legend>
								<p>Tentukan departemen dan User yang akan mewawancarai kandidat ini.</p>
								<div class="daw-hr-interview__form-grid">
									<label>Departemen Tujuan
										<select name="department_name" data-hr-department>
											<option value="">Pilih departemen...</option>
											<?php foreach ( $departments as $department ) : ?>
												<option value="<?= esc_attr( (string) $department['name'] ) ?>" data-department-id="<?= esc_attr( (string) $department['id'] ) ?>" <?= selected( $selected_department, (string) $department['name'], false ) ?>><?= esc_html( (string) $department['name'] ) ?></option>
											<?php endforeach; ?>
										</select>
									</label>
									<label>User Department
										<select name="department_account_id" data-hr-account>
											<option value="0">Pilih user...</option>
											<?php foreach ( $department_accounts as $account ) : ?>
												<option value="<?= esc_attr( (string) $account['id'] ) ?>" data-department-id="<?= esc_attr( (string) ( $account['department_id'] ?? '' ) ) ?>" <?= selected( $selected_account_id, (string) $account['id'], false ) ?>><?= esc_html( (string) $account['username'] . ' (' . (string) $account['email'] . ')' ) ?></option>
											<?php endforeach; ?>
										</select>
									</label>
								</div>
								<label>Catatan Rekomendasi
									<textarea name="department_notes" rows="2" maxlength="1000" placeholder="Alasan rekomendasi ke departemen ini..."><?= esc_textarea( (string) ( $latest_note['department_notes'] ?? '' ) ) ?></textarea>
								</label>
							</fieldset>
							<button type="submit" class="daw-hr-interview__forward" data-hr-forward disabled>Simpan Keputusan &amp; Arahkan ke Departemen</button>
						</form>
					</section>
				</div>
			</div>
		<?php else : ?>
			<div class="daw-interview-screen__heading">
				<div><h1>Wawancara HR</h1><p>Kelola catatan, penilaian, dan keputusan kandidat pada tahap wawancara HR.</p></div>
			</div>
			<section class="daw-interview-screen__stats" aria-label="Ringkasan wawancara HR">
				<article class="is-waiting"><strong><?= esc_html( (string) count( $candidates ) ) ?></strong><span>Menunggu Wawancara</span></article>
				<article class="is-scheduled"><strong><?= esc_html( (string) count( $department_accounts ) ) ?></strong><span>Akun User Department Aktif</span></article>
			</section>
			<section class="daw-user-interview__panel daw-interview-screen__panel" aria-labelledby="hr-interview-candidates-title">
				<div class="daw-interview-screen__panel-heading"><h2 id="hr-interview-candidates-title">Kandidat Tahap Wawancara HR</h2></div>
				<div class="daw-user-interview__table-wrap">
					<table class="daw-interview-screen__table daw-hr-interview__table">
						<caption class="screen-reader-text">Kandidat menunggu keputusan wawancara HR</caption>
						<thead><tr><th scope="col">Kandidat</th><th scope="col">Posisi / Departemen</th><th scope="col">Dealer</th><th scope="col">Nilai Psikotes</th><th scope="col">Tanggal Wawancara</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
						<tbody>
							<?php if ( ! empty( $candidates ) ) : ?>
								<?php foreach ( $candidates as $candidate ) :
									$candidate_application_id = absint( $candidate['application_id'] ?? 0 );
									?>
									<tr>
										<td><span class="daw-user-interview__candidate"><?= esc_html( (string) ( $candidate['candidate_name'] ?? 'Pelamar' ) ) ?></span><small class="daw-user-interview__code">Lamaran #<?= esc_html( (string) $candidate_application_id ) ?></small></td>
										<td><?= esc_html( (string) ( $candidate['position'] ?? 'Posisi belum ditentukan' ) ) ?><small><?= esc_html( (string) ( $candidate['department'] ?? '' ) ) ?></small></td>
										<td><?= esc_html( (string) ( $candidate['dealer'] ?? '-' ) ) ?></td>
										<td><strong class="daw-interview-screen__score"><?= esc_html( (string) ( $candidate['psychotest_score'] ?? '—' ) ) ?></strong></td>
										<td><?= esc_html( (string) ( $candidate['scheduled_interview_date'] ?? '—' ) ) ?></td>
										<td><span class="daw-interview-screen__status is-waiting">Menunggu Keputusan HR</span></td>
										<td><a class="daw-interview-screen__action" href="<?= esc_url( add_query_arg( 'application_id', $candidate_application_id, $list_url ) ) ?>">Detail / Catatan</a></td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr><td colspan="7" style="text-align:center; padding:32px 16px; color:#6b7280;">Belum ada kandidat pada tahap Wawancara HR.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>
	</main>
</div>
<?php if ( $selected_candidate ) : ?>
<script>
document.querySelectorAll('[data-hr-interview-form]').forEach((form) => {
	const departmentSelect = form.querySelector('[data-hr-department]');
	const accountSelect = form.querySelector('[data-hr-account]');
	const decisionInputs = form.querySelectorAll('input[name="decision"]');
	const forwardButton = form.querySelector('[data-hr-forward]');
	const intentInput = form.querySelector('[data-hr-intent]');
	const saveNoteButton = form.querySelector('[data-hr-save-note]');
	if (!departmentSelect || !accountSelect || !forwardButton || !intentInput || !saveNoteButton) return;

	const syncAccounts = (resetSelection) => {
		const departmentId = departmentSelect.selectedOptions[0]?.dataset.departmentId || '';
		Array.from(accountSelect.options).forEach((option, index) => {
			if (index === 0) return;
			option.hidden = !departmentId || option.dataset.departmentId !== departmentId;
		});
		if (resetSelection || accountSelect.selectedOptions[0]?.hidden) accountSelect.value = '0';
	};

	const updateDecision = () => {
		const decision = form.querySelector('input[name="decision"]:checked')?.value || '';
		const recipientSelected = departmentSelect.value !== '' && accountSelect.value !== '0';
		forwardButton.disabled = !decision || ('pass' === decision && !recipientSelected);
		forwardButton.textContent = 'fail' === decision
			? 'Simpan Keputusan Wawancara HR'
			: 'Simpan Keputusan & Arahkan ke Departemen';
	};

	departmentSelect.addEventListener('change', () => syncAccounts(true));
	departmentSelect.addEventListener('change', updateDecision);
	accountSelect.addEventListener('change', updateDecision);
	decisionInputs.forEach((input) => input.addEventListener('change', updateDecision));
	form.addEventListener('submit', (event) => {
		const submitter = event.submitter;
		if (submitter === saveNoteButton) {
			intentInput.value = 'save_note';
			return;
		}

		const decision = form.querySelector('input[name="decision"]:checked')?.value || '';
		if (!decision || ('pass' === decision && (departmentSelect.value === '' || accountSelect.value === '0'))) {
			event.preventDefault();
			updateDecision();
			return;
		}
		intentInput.value = decision;
	});
	syncAccounts(false);
	updateDecision();
});
</script>
<?php endif; ?>
