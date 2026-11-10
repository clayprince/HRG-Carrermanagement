<?php
$current_user = wp_get_current_user();
$filter = sanitize_key( wp_unslash( $_GET['status'] ?? 'all' ) );
$filters = [
	'all' => 'Semua',
	'review' => 'Review HR',
	'revision' => 'Perlu Revisi',
	'approved' => 'Disetujui',
	'ready' => 'Ready for Recruitment',
];
if ( ! isset( $filters[ $filter ] ) ) {
	$filter = 'all';
}
$requests = [];
$department_users = get_users(
	[
		'meta_key' => 'daw_department_job_requests',
		'meta_compare' => 'EXISTS',
		'fields' => 'all',
	]
);
foreach ( $department_users as $department_user ) {
	$saved_requests = get_user_meta( $department_user->ID, 'daw_department_job_requests', true );
	if ( ! is_array( $saved_requests ) ) {
		continue;
	}
	foreach ( $saved_requests as $code => $saved_request ) {
		if ( ! is_array( $saved_request ) ) {
			continue;
		}
		$request_status = (string) ( $saved_request['status'] ?? 'Draft' );
		if ( 'Draft' === $request_status ) {
			continue;
		}
		$fields = is_array( $saved_request['fields'] ?? null ) ? $saved_request['fields'] : [];
		$requests[ $code ] = [
			'code' => (string) $code,
			'department' => (string) ( $fields['department'] ?? get_user_meta( $department_user->ID, 'department', true ) ?: '—' ),
			'position' => (string) ( $fields['position'] ?? 'Posisi belum diisi' ),
			'hiring_user' => (string) ( $fields['hiring_user'] ?? $department_user->display_name ),
			'count' => (int) ( $fields['headcount'] ?? 1 ),
			'priority' => (string) ( $fields['priority'] ?? 'Sedang' ),
			'date' => ! empty( $saved_request['created_at'] ) ? date_i18n( 'd M Y', strtotime( $saved_request['created_at'] ) ) : '—',
			'status' => $request_status,
			'fields' => $fields,
			'hr_note' => (string) ( $saved_request['hr_note'] ?? '' ),
			'owner_id' => (int) $department_user->ID,
			'vacancy_id' => absint( $saved_request['vacancy_id'] ?? 0 ),
			'publication_location' => (string) ( $saved_request['publication_location'] ?? $fields['location'] ?? '' ),
			'dealer' => (string) ( $saved_request['dealer'] ?? '' ),
			'deadline' => (string) ( $saved_request['deadline'] ?? '' ),
		];
	}
}

$status_filters = [
	'review' => [ 'Review HR', 'Menunggu Review HR' ],
	'revision' => [ 'Perlu Revisi' ],
	'approved' => [ 'Disetujui' ],
	'ready' => [ 'Ready for Recruitment', 'Lowongan Dibuka' ],
];
$filtered_requests = array_filter(
	$requests,
	static function ( array $request ) use ( $filter, $filters, $status_filters ): bool {
		return 'all' === $filter || in_array( $request['status'], $status_filters[ $filter ] ?? [ $filters[ $filter ] ], true );
	}
);
$status_counts = [ 'all' => count( $requests ), 'review' => 0, 'revision' => 0, 'approved' => 0, 'ready' => 0 ];
foreach ( $requests as $request ) {
	if ( in_array( $request['status'], $status_filters['review'], true ) ) {
		++$status_counts['review'];
	} elseif ( in_array( $request['status'], $status_filters['revision'], true ) ) {
		++$status_counts['revision'];
	} elseif ( in_array( $request['status'], $status_filters['approved'], true ) ) {
		++$status_counts['approved'];
	} elseif ( in_array( $request['status'], $status_filters['ready'], true ) ) {
		++$status_counts['ready'];
	}
}
$request_code = sanitize_text_field( wp_unslash( $_GET['request_code'] ?? '' ) );
$owner_id = absint( $_GET['owner_id'] ?? 0 );
$selected_request = $requests[ $request_code ] ?? null;
if ( $selected_request && (int) ( $selected_request['owner_id'] ?? 0 ) !== $owner_id ) {
	$selected_request = null;
}
$status_classes = [
	'Review HR' => 'review',
	'Menunggu Review HR' => 'review',
	'Perlu Revisi' => 'revision',
	'Disetujui' => 'approved',
	'Ready for Recruitment' => 'ready',
	'Lowongan Dibuka' => 'ready',
	'Ditolak' => 'rejected',
	'Draft' => 'draft',
];
$filter_url = static function ( string $requested_filter ): string {
	return add_query_arg(
		[ 'page' => 'recruitment-applications', 'status' => $requested_filter ],
		admin_url( 'admin.php' )
	);
};
$detail_url = static function ( array $request ): string {
	return add_query_arg(
		[
			'page' => 'recruitment-applications',
			'request_code' => $request['code'],
			'owner_id' => (int) ( $request['owner_id'] ?? 0 ),
			'status' => sanitize_key( wp_unslash( $_GET['status'] ?? 'all' ) ),
		],
		admin_url( 'admin.php' )
	);
};
$review_error = sanitize_key( wp_unslash( $_GET['review_error'] ?? '' ) );
$review_error_messages = [
	'note_required' => 'Tambahkan catatan untuk meminta revisi atau menolak pengajuan.',
	'publication_details' => 'Isi lokasi, Dealer/AHASS, dan deadline lamaran yang valid sebelum menyetujui.',
	'publication_failed' => 'Lowongan gagal diterbitkan di Oracle. Pengajuan tetap menunggu keputusan; periksa koneksi dan coba lagi.',
	'link_failed' => 'Lowongan sudah diterbitkan, tetapi status pengajuan tidak berhasil diperbarui. Periksa lowongan Oracle sebelum mencoba lagi.',
];
$reviewed = '1' === sanitize_text_field( wp_unslash( $_GET['reviewed'] ?? '' ) );
$hr_identity = recruitment_get_current_hr_identity();
?>
<div class="wrap daw-requests-page">
	<header class="daw-requests__header">
		<nav aria-label="Breadcrumb"><a href="<?= esc_url( recruitment_get_admin_url( 'dashboard' ) ) ?>">DAW Admin</a><span>/</span><strong><?= $selected_request ? 'Detail Pengajuan FPTK' : 'Pengajuan Lowongan' ?></strong></nav>
		<div class="daw-requests__account"><span class="daw-requests__avatar" aria-hidden="true"><?= esc_html( $hr_identity['avatar'] ) ?></span><span><strong><?= esc_html( $hr_identity['name'] ) ?></strong><small><?= esc_html( $hr_identity['email'] ) ?></small></span><?= recruitment_logout_form( 'Keluar', home_url( '/' ) ) ?></div>
	</header>
	<main class="daw-requests__main">
		<?php if ( $selected_request ) : ?>
			<?php
			$fields = is_array( $selected_request['fields'] ?? null ) ? $selected_request['fields'] : [];
			$status = $selected_request['status'];
			$display_status = [ 'Menunggu Review HR' => 'Review HR', 'Lowongan Dibuka' => 'Ready for Recruitment' ][ $status ] ?? $status;
			$detail_fields = [
				'No. FPTK' => $selected_request['code'],
				'Tanggal Pengajuan' => $selected_request['date'],
				'Departemen' => $selected_request['department'],
				'Posisi' => $selected_request['position'],
				'Level Jabatan' => $fields['job_level'] ?? '—',
				'Kategori KJ' => $fields['employment_type'] ?? '—',
				'Hiring User' => $selected_request['hiring_user'],
				'Jumlah Kebutuhan' => $selected_request['count'] . ' orang',
				'Status Karyawan' => $fields['employee_status'] ?? '—',
				'Prioritas' => $selected_request['priority'],
				'Target Pemenuhan' => $fields['target_date'] ?? '—',
				'Lokasi' => $fields['location'] ?? '—',
			];
			?>
			<a class="daw-requests__back" href="<?= esc_url( $filter_url( $filter ) ) ?>">&larr; Kembali</a>
			<div class="daw-requests__detail-heading"><div><p><?= esc_html( $selected_request['code'] ) ?></p><span>Diajukan oleh <?= esc_html( $selected_request['hiring_user'] ) ?> — <?= esc_html( $selected_request['date'] ) ?></span></div><span class="daw-requests__status daw-requests__status--<?= esc_attr( $status_classes[ $status ] ?? 'draft' ) ?>"><?= esc_html( $display_status ) ?></span></div>
			<?php if ( 'Disetujui' === $status && empty( $selected_request['vacancy_id'] ) ) : ?>
				<div class="daw-requests__info" role="note">Pengajuan ini sebelumnya disetujui tetapi belum diterbitkan. Lengkapi Dealer/AHASS dan deadline untuk langsung menerbitkannya.</div>
			<?php elseif ( 'Lowongan Dibuka' === $status ) : ?>
				<div class="daw-requests__info" role="status">Lowongan sudah diterbitkan<?php if ( ! empty( $selected_request['vacancy_id'] ) ) : ?> (ID lowongan Oracle: <?= esc_html( (string) $selected_request['vacancy_id'] ) ?>)<?php endif; ?>.</div>
			<?php endif; ?>
			<section class="daw-requests__card"><h2>Informasi Permintaan</h2><dl class="daw-requests__facts"><?php foreach ( $detail_fields as $label => $value ) : ?><div><dt><?= esc_html( $label ) ?></dt><dd><?= esc_html( (string) $value ) ?></dd></div><?php endforeach; ?></dl><div class="daw-requests__copy"><span>Alasan Kebutuhan</span><p><?= esc_html( $fields['reason'] ?? 'Belum tersedia' ) ?></p></div></section>
			<section class="daw-requests__card"><h2>Tujuan Posisi</h2><div class="daw-requests__copy"><span>Ringkasan Peran</span><p><?= esc_html( $fields['role_summary'] ?? 'Belum tersedia' ) ?></p><span>KPI / Target Utama</span><p><?= esc_html( $fields['kpi'] ?? 'Belum tersedia' ) ?></p></div></section>
			<section class="daw-requests__card"><h2>Profil Kandidat</h2><dl class="daw-requests__facts"><div><dt>Pendidikan Minimum</dt><dd><?= esc_html( $fields['minimum_education'] ?? '—' ) ?></dd></div><div><dt>Pengalaman</dt><dd><?= esc_html( $fields['minimum_experience'] ?? '—' ) ?></dd></div><div><dt>Usia</dt><dd><?= esc_html( $fields['age_range'] ?? '—' ) ?></dd></div><div><dt>SIM</dt><dd><?= esc_html( $fields['certifications'] ?? '—' ) ?></dd></div></dl><div class="daw-requests__copy"><span>Keahlian Wajib</span><p><?= esc_html( $fields['required_skills'] ?? '—' ) ?></p></div></section>
			<section class="daw-requests__card"><h2>Validasi Requirement</h2><dl class="daw-requests__facts"><div><dt>Budget Gaji</dt><dd><?= esc_html( $fields['salary_range'] ?? '—' ) ?></dd></div><div><dt>Anggaran Rekrutmen</dt><dd><?= esc_html( $fields['recruitment_budget'] ?? '—' ) ?></dd></div><div><dt>Konfirmasi Headcount</dt><dd><?= esc_html( $fields['headcount_confirmation'] ?? '—' ) ?></dd></div></dl></section>
			<section class="daw-requests__card daw-requests__decision"><h2>Keputusan HR</h2>
				<?php if ( isset( $review_error_messages[ $review_error ] ) ) : ?><p class="daw-requests__feedback daw-requests__feedback--error" role="alert"><?= esc_html( $review_error_messages[ $review_error ] ) ?></p><?php elseif ( $reviewed ) : ?><p class="daw-requests__feedback" role="status">Keputusan berhasil disimpan. Pengajuan yang disetujui langsung diterbitkan di portal lowongan.</p><?php endif; ?>
				<?php if ( in_array( $status, $status_filters['review'], true ) || ( 'Disetujui' === $status && empty( $selected_request['vacancy_id'] ) ) ) : ?>
					<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" data-department-review-form>
						<input type="hidden" name="action" value="daw_review_department_request"><input type="hidden" name="request_code" value="<?= esc_attr( $selected_request['code'] ) ?>"><input type="hidden" name="owner_id" value="<?= esc_attr( (string) ( $selected_request['owner_id'] ?? 0 ) ) ?>">
						<?php wp_nonce_field( 'daw_review_department_request' ); ?>
						<div class="daw-requests__decision-options">
							<?php if ( 'Disetujui' === $status ) : ?>
								<input type="hidden" name="decision" value="approve">
							<?php else : ?>
								<label><input type="radio" name="decision" value="approve" required><span>Setujui &amp; Terbitkan</span></label>
								<label><input type="radio" name="decision" value="revise" required><span>Perlu Revisi</span></label>
								<label><input type="radio" name="decision" value="reject" required><span>Tolak</span></label>
							<?php endif; ?>
						</div>
						<fieldset class="daw-requests__publish-fields" data-publish-request-fields <?= 'Disetujui' === $status ? '' : 'hidden' ?>>
							<legend>Data yang diperlukan untuk menerbitkan lowongan</legend>
							<label>Lokasi Penempatan<input name="location" maxlength="100" value="<?= esc_attr( (string) ( $selected_request['publication_location'] ?? '' ) ) ?>" data-publish-required="<?= 'Disetujui' === $status ? 'true' : 'false' ?>" <?= 'Disetujui' === $status ? 'required' : '' ?>></label>
							<label>Dealer / AHASS<input name="dealer" maxlength="100" value="<?= esc_attr( (string) ( $selected_request['dealer'] ?? '' ) ) ?>" data-publish-required="<?= 'Disetujui' === $status ? 'true' : 'false' ?>" <?= 'Disetujui' === $status ? 'required' : '' ?>></label>
							<label>Deadline Lamaran<input name="deadline" type="date" value="<?= esc_attr( (string) ( $selected_request['deadline'] ?? '' ) ) ?>" data-publish-required="<?= 'Disetujui' === $status ? 'true' : 'false' ?>" <?= 'Disetujui' === $status ? 'required' : '' ?>></label>
							<p>Lengkapi lokasi bila belum dicantumkan. Setelah disetujui, lowongan langsung diterbitkan di portal pelamar.</p>
						</fieldset>
						<label class="daw-requests__note">Catatan HR<textarea name="hr_note" rows="3" placeholder="Tambahkan catatan, terutama jika meminta revisi atau menolak..."><?= esc_textarea( $selected_request['hr_note'] ?? '' ) ?></textarea></label>
						<div class="daw-requests__decision-submit"><button type="submit"><?= 'Disetujui' === $status ? 'Terbitkan Lowongan' : 'Konfirmasi Keputusan' ?></button></div>
					</form>
				<?php else : ?>
					<p class="daw-requests__processed">Keputusan: <strong><?= esc_html( $status ) ?></strong></p><p class="daw-requests__processed">Catatan HR: <?= esc_html( $selected_request['hr_note'] ?? 'Belum ada catatan.' ) ?></p>
				<?php endif; ?>
			</section>
		<?php else : ?>
			<?php if ( $status_counts['review'] > 0 ) : ?><div class="daw-requests__alert"><span aria-hidden="true">◷</span><strong><?= esc_html( (string) $status_counts['review'] ) ?> permintaan menunggu review Anda</strong></div><?php endif; ?>
			<nav class="daw-requests__tabs" aria-label="Filter status pengajuan"><?php foreach ( $filters as $key => $label ) : ?><a class="<?= $filter === $key ? 'is-active' : '' ?>" href="<?= esc_url( $filter_url( $key ) ) ?>" <?= $filter === $key ? 'aria-current="page"' : '' ?>><?= esc_html( $label ) ?><?php if ( 'all' === $key ) : ?><span><?= esc_html( (string) $status_counts[ $key ] ) ?></span><?php endif; ?></a><?php endforeach; ?></nav>
			<div class="daw-requests__table-wrap"><table class="daw-requests__table"><caption class="screen-reader-text">Daftar pengajuan lowongan</caption><thead><tr><th>No. FPTK</th><th>Departemen</th><th>Posisi</th><th>Hiring User</th><th>Jml</th><th>Prioritas</th><th>Tgl Pengajuan</th><th>Status</th><th></th></tr></thead><tbody>
				<?php if ( empty( $filtered_requests ) ) : ?><tr><td class="daw-requests__empty" colspan="9">Belum ada pengajuan yang sesuai dengan filter ini.</td></tr><?php endif; ?>
				<?php foreach ( $filtered_requests as $request ) : ?>
					<?php $request_status = $request['status']; $is_review = in_array( $request_status, $status_filters['review'], true ); ?>
					<?php $display_status = [ 'Menunggu Review HR' => 'Review HR', 'Lowongan Dibuka' => 'Ready for Recruitment' ][ $request_status ] ?? $request_status; ?>
					<tr><td><strong><?= esc_html( $request['code'] ) ?></strong></td><td><?= esc_html( $request['department'] ) ?></td><td><?= esc_html( $request['position'] ) ?></td><td><?= esc_html( $request['hiring_user'] ) ?></td><td><?= esc_html( (string) $request['count'] ) ?></td><td class="daw-requests__priority daw-requests__priority--<?= esc_attr( sanitize_title( $request['priority'] ) ) ?>"><?= esc_html( $request['priority'] ) ?></td><td><?= esc_html( $request['date'] ) ?></td><td><span class="daw-requests__status daw-requests__status--<?= esc_attr( $status_classes[ $request_status ] ?? 'draft' ) ?>"><?= esc_html( $display_status ) ?></span></td><td><a class="daw-requests__action <?= $is_review ? 'is-review' : '' ?>" href="<?= esc_url( $detail_url( $request ) ) ?>"><?= $is_review ? 'Review' : 'Lihat' ?></a></td></tr>
				<?php endforeach; ?>
			</tbody></table></div>
		<?php endif; ?>
	</main>
</div>
