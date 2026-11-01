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
$sample_requests = [
	'FPTK-2026-032' => [
		'code' => 'FPTK-2026-032', 'department' => 'Sales', 'position' => 'Sales Executive', 'hiring_user' => 'Andi Wirawan', 'count' => 2, 'priority' => 'Tinggi', 'date' => '22 Sep 2026', 'status' => 'Review HR',
		'fields' => [ 'job_level' => 'Staff', 'employment_type' => 'Pegawai Tetap', 'employee_status' => 'Baru', 'target_date' => '31 Oktober 2026', 'location' => 'Manado, Sulawesi Utara', 'reason' => 'Adanya peningkatan target penjualan Q4 2026 sebesar 30% dan ekspansi ke wilayah Depok & Tangerang. Posisi ini diperlukan untuk mendukung coverage area yang lebih luas.', 'role_summary' => 'Bertanggung jawab atas pencapaian target penjualan unit kendaraan, membangun dan memelihara hubungan pelanggan, serta melakukan prospecting di wilayah yang ditentukan.', 'kpi' => 'Penjualan minimum 5 unit/bulan, customer satisfaction score ≥ 85, follow-up leads dalam 24 jam.', 'minimum_education' => 'S1 (semua jurusan)', 'minimum_experience' => 'Min. 1 tahun di bidang sales', 'age_range' => '22–35 tahun', 'certifications' => 'SIM A (wajib)', 'required_skills' => 'Komunikasi dan negosiasi baik, target-oriented, menguasai wilayah Jakarta Selatan & sekitarnya.', 'salary_range' => 'Rp 7.000.000 – Rp 10.000.000', 'recruitment_budget' => 'Tersedia', 'headcount_confirmation' => 'Sesuai Rencana' ],
	],
	'FPTK-2026-030' => [
		'code' => 'FPTK-2026-030', 'department' => 'After Sales', 'position' => 'Service Advisor', 'hiring_user' => 'Benny Kurnia', 'count' => 1, 'priority' => 'Urgent', 'date' => '20 Sep 2026', 'status' => 'Review HR',
		'fields' => [ 'job_level' => 'Staff', 'employment_type' => 'Pegawai Tetap', 'employee_status' => 'Baru', 'target_date' => '31 Oktober 2026', 'location' => 'Manado, Sulawesi Utara', 'reason' => 'Kebutuhan tenaga kerja untuk meningkatkan pelayanan pelanggan bengkel.', 'role_summary' => 'Melayani pelanggan dan mengoordinasikan kebutuhan servis kendaraan.', 'kpi' => 'Mencapai target kepuasan pelanggan dan waktu layanan.', 'minimum_education' => 'D3 Otomotif', 'minimum_experience' => 'Min. 1 tahun di bidang otomotif', 'age_range' => '22–35 tahun', 'certifications' => 'SIM A', 'required_skills' => 'Komunikasi dan pemahaman teknis otomotif.', 'salary_range' => 'Rp 6.000.000 – Rp 8.000.000', 'recruitment_budget' => 'Tersedia', 'headcount_confirmation' => 'Sesuai Rencana' ],
	],
	'FPTK-2026-029' => [
		'code' => 'FPTK-2026-029', 'department' => 'Sales', 'position' => 'Sales Admin', 'hiring_user' => 'Andi Wirawan', 'count' => 1, 'priority' => 'Sedang', 'date' => '17 Sep 2026', 'status' => 'Perlu Revisi',
		'fields' => [ 'job_level' => 'Staff', 'employment_type' => 'Pegawai Tetap', 'employee_status' => 'Pengganti', 'target_date' => '30 Oktober 2026', 'location' => 'Manado, Sulawesi Utara', 'reason' => 'Penggantian tenaga administrasi penjualan.', 'role_summary' => 'Mengelola administrasi dan dokumentasi proses penjualan.', 'kpi' => 'Akurasi dan ketepatan waktu laporan penjualan.', 'minimum_education' => 'D3 Administrasi', 'minimum_experience' => 'Min. 1 tahun', 'age_range' => '22–35 tahun', 'certifications' => '—', 'required_skills' => 'Teliti, mampu mengoperasikan aplikasi perkantoran.', 'salary_range' => 'Rp 5.000.000 – Rp 7.000.000', 'recruitment_budget' => 'Tersedia', 'headcount_confirmation' => 'Sesuai Rencana' ],
	],
	'FPTK-2026-027' => [
		'code' => 'FPTK-2026-027', 'department' => 'Finance', 'position' => 'Finance Staff', 'hiring_user' => 'Dewi Susanti', 'count' => 2, 'priority' => 'Sedang', 'date' => '14 Sep 2026', 'status' => 'Disetujui',
		'fields' => [ 'job_level' => 'Staff', 'employment_type' => 'Pegawai Tetap', 'employee_status' => 'Baru', 'target_date' => '30 Oktober 2026', 'location' => 'Manado, Sulawesi Utara', 'reason' => 'Penambahan kapasitas tim keuangan.', 'role_summary' => 'Membantu pencatatan transaksi serta penyusunan laporan keuangan.', 'kpi' => 'Laporan bulanan akurat dan tepat waktu.', 'minimum_education' => 'S1 Akuntansi', 'minimum_experience' => 'Min. 1 tahun', 'age_range' => '22–35 tahun', 'certifications' => '—', 'required_skills' => 'Memahami akuntansi dan pengolahan data.', 'salary_range' => 'Rp 6.000.000 – Rp 8.000.000', 'recruitment_budget' => 'Tersedia', 'headcount_confirmation' => 'Sesuai Rencana' ],
	],
	'FPTK-2026-025' => [
		'code' => 'FPTK-2026-025', 'department' => 'Sales', 'position' => 'Customer Service', 'hiring_user' => 'Andi Wirawan', 'count' => 3, 'priority' => 'Urgent', 'date' => '10 Sep 2026', 'status' => 'Ready for Recruitment',
		'fields' => [ 'job_level' => 'Staff', 'employment_type' => 'Pegawai Tetap', 'employee_status' => 'Baru', 'target_date' => '31 Oktober 2026', 'location' => 'Manado, Sulawesi Utara', 'reason' => 'Penambahan cakupan pelayanan pelanggan.', 'role_summary' => 'Memberikan layanan dan informasi kepada pelanggan.', 'kpi' => 'Customer satisfaction score minimal 85.', 'minimum_education' => 'SMA/SMK', 'minimum_experience' => 'Pengalaman layanan pelanggan diutamakan', 'age_range' => '20–35 tahun', 'certifications' => '—', 'required_skills' => 'Komunikasi yang baik dan berorientasi pada pelanggan.', 'salary_range' => 'Rp 5.000.000 – Rp 7.000.000', 'recruitment_budget' => 'Tersedia', 'headcount_confirmation' => 'Sesuai Rencana' ],
	],
	'FPTK-2026-022' => [
		'code' => 'FPTK-2026-022', 'department' => 'Body Repair', 'position' => 'Body Repair Advisor', 'hiring_user' => 'Hendra Saputra', 'count' => 1, 'priority' => 'Rendah', 'date' => '5 Sep 2026', 'status' => 'Ditolak',
		'fields' => [ 'job_level' => 'Staff', 'employment_type' => 'Kontrak', 'employee_status' => 'Baru', 'target_date' => '30 November 2026', 'location' => 'Manado, Sulawesi Utara', 'reason' => 'Permintaan penambahan tenaga untuk area body repair.', 'role_summary' => 'Mengoordinasikan kebutuhan pelanggan dan proses perbaikan bodi kendaraan.', 'kpi' => 'Ketepatan waktu dan kepuasan pelanggan.', 'minimum_education' => 'D3 Otomotif', 'minimum_experience' => 'Min. 1 tahun', 'age_range' => '22–35 tahun', 'certifications' => '—', 'required_skills' => 'Pemahaman dasar perbaikan bodi kendaraan.', 'salary_range' => 'Rp 5.000.000 – Rp 7.000.000', 'recruitment_budget' => 'Menunggu Persetujuan', 'headcount_confirmation' => 'Perlu Persetujuan' ],
	],
];

$demo_statuses = get_option( 'daw_demo_department_request_statuses', [] );
$demo_statuses = is_array( $demo_statuses ) ? $demo_statuses : [];
foreach ( $demo_statuses as $code => $saved_status ) {
	if ( isset( $sample_requests[ $code ] ) && is_array( $saved_status ) ) {
		$sample_requests[ $code ]['status'] = (string) ( $saved_status['status'] ?? $sample_requests[ $code ]['status'] );
		$sample_requests[ $code ]['hr_note'] = (string) ( $saved_status['hr_note'] ?? '' );
		$sample_requests[ $code ]['updated_at'] = (string) ( $saved_status['updated_at'] ?? '' );
		$sample_requests[ $code ]['vacancy_id'] = absint( $saved_status['vacancy_id'] ?? 0 );
	}
}

$requests = $sample_requests;
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
$review_error = 'note_required' === sanitize_key( wp_unslash( $_GET['review_error'] ?? '' ) );
$reviewed = '1' === sanitize_text_field( wp_unslash( $_GET['reviewed'] ?? '' ) );
?>
<div class="wrap daw-requests-page">
	<header class="daw-requests__header">
		<nav aria-label="Breadcrumb"><a href="<?= esc_url( recruitment_get_admin_url( 'dashboard' ) ) ?>">DAW Admin</a><span>/</span><strong><?= $selected_request ? 'Detail Pengajuan FPTK' : 'Pengajuan Lowongan' ?></strong></nav>
		<div class="daw-requests__account"><span class="daw-requests__avatar" aria-hidden="true">HR</span><span><strong><?= esc_html( $current_user->display_name ) ?></strong><small><?= esc_html( $current_user->user_email ) ?></small></span><a href="<?= esc_url( wp_logout_url( home_url( '/' ) ) ) ?>">Keluar</a></div>
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
			<div class="daw-requests__info" role="note">Jika disetujui, sistem akan membuat lowongan sebagai draft di Daftar Lowongan berdasarkan data FPTK ini. HR dapat meninjau dan memublikasikannya setelahnya.</div>
			<section class="daw-requests__card"><h2>Informasi Permintaan</h2><dl class="daw-requests__facts"><?php foreach ( $detail_fields as $label => $value ) : ?><div><dt><?= esc_html( $label ) ?></dt><dd><?= esc_html( (string) $value ) ?></dd></div><?php endforeach; ?></dl><div class="daw-requests__copy"><span>Alasan Kebutuhan</span><p><?= esc_html( $fields['reason'] ?? 'Belum tersedia' ) ?></p></div></section>
			<section class="daw-requests__card"><h2>Tujuan Posisi</h2><div class="daw-requests__copy"><span>Ringkasan Peran</span><p><?= esc_html( $fields['role_summary'] ?? 'Belum tersedia' ) ?></p><span>KPI / Target Utama</span><p><?= esc_html( $fields['kpi'] ?? 'Belum tersedia' ) ?></p></div></section>
			<section class="daw-requests__card"><h2>Profil Kandidat</h2><dl class="daw-requests__facts"><div><dt>Pendidikan Minimum</dt><dd><?= esc_html( $fields['minimum_education'] ?? '—' ) ?></dd></div><div><dt>Pengalaman</dt><dd><?= esc_html( $fields['minimum_experience'] ?? '—' ) ?></dd></div><div><dt>Usia</dt><dd><?= esc_html( $fields['age_range'] ?? '—' ) ?></dd></div><div><dt>SIM</dt><dd><?= esc_html( $fields['certifications'] ?? '—' ) ?></dd></div></dl><div class="daw-requests__copy"><span>Keahlian Wajib</span><p><?= esc_html( $fields['required_skills'] ?? '—' ) ?></p></div></section>
			<section class="daw-requests__card"><h2>Validasi Requirement</h2><dl class="daw-requests__facts"><div><dt>Budget Gaji</dt><dd><?= esc_html( $fields['salary_range'] ?? '—' ) ?></dd></div><div><dt>Anggaran Rekrutmen</dt><dd><?= esc_html( $fields['recruitment_budget'] ?? '—' ) ?></dd></div><div><dt>Konfirmasi Headcount</dt><dd><?= esc_html( $fields['headcount_confirmation'] ?? '—' ) ?></dd></div></dl></section>
			<section class="daw-requests__card daw-requests__decision"><h2>Keputusan HR</h2>
				<?php if ( $review_error ) : ?><p class="daw-requests__feedback daw-requests__feedback--error" role="alert">Tambahkan catatan untuk meminta revisi atau menolak pengajuan.</p><?php elseif ( $reviewed ) : ?><p class="daw-requests__feedback" role="status">Keputusan pengajuan berhasil disimpan.</p><?php endif; ?>
				<?php if ( in_array( $status, $status_filters['review'], true ) ) : ?>
					<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
						<input type="hidden" name="action" value="daw_review_department_request"><input type="hidden" name="request_code" value="<?= esc_attr( $selected_request['code'] ) ?>"><input type="hidden" name="owner_id" value="<?= esc_attr( (string) ( $selected_request['owner_id'] ?? 0 ) ) ?>">
						<?php if ( empty( $selected_request['owner_id'] ) ) : ?><input type="hidden" name="vacancy_title" value="<?= esc_attr( $selected_request['position'] ) ?>"><input type="hidden" name="vacancy_content" value="<?= esc_attr( $fields['role_summary'] ?? '' ) ?>"><input type="hidden" name="vacancy_location" value="<?= esc_attr( $fields['location'] ?? '' ) ?>"><input type="hidden" name="vacancy_type" value="<?= esc_attr( $fields['employment_type'] ?? '' ) ?>"><?php endif; ?>
						<?php wp_nonce_field( 'daw_review_department_request' ); ?>
						<div class="daw-requests__decision-options">
							<label><input type="radio" name="decision" value="approve" required><span>Setujui &amp; Buat Lowongan</span></label>
							<label><input type="radio" name="decision" value="revise" required><span>Perlu Revisi</span></label>
							<label><input type="radio" name="decision" value="reject" required><span>Tolak</span></label>
						</div>
						<label class="daw-requests__note">Catatan HR<textarea name="hr_note" rows="3" placeholder="Tambahkan catatan, terutama jika meminta revisi atau menolak..."><?= esc_textarea( $selected_request['hr_note'] ?? '' ) ?></textarea></label>
						<div class="daw-requests__decision-submit"><button type="submit">Konfirmasi Keputusan</button></div>
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
