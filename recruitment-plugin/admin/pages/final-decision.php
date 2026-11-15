<?php
$section = sanitize_key( $_GET['section'] ?? 'decision' );
$allowed_sections = [ 'decision', 'email', 'analytics' ];
if ( ! in_array( $section, $allowed_sections, true ) ) {
	$section = 'decision';
}

$filters = [
	'search' => sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) ),
	'batch' => sanitize_text_field( wp_unslash( $_GET['batch'] ?? '' ) ),
	'year' => sanitize_text_field( wp_unslash( $_GET['year'] ?? '' ) ),
	'dealer' => sanitize_text_field( wp_unslash( $_GET['dealer'] ?? '' ) ),
	'department' => sanitize_text_field( wp_unslash( $_GET['department'] ?? '' ) ),
	'position' => sanitize_text_field( wp_unslash( $_GET['position'] ?? '' ) ),
	'user_result' => sanitize_text_field( wp_unslash( $_GET['user_result'] ?? '' ) ),
	'decision' => sanitize_text_field( wp_unslash( $_GET['decision'] ?? '' ) ),
	'email_status' => sanitize_text_field( wp_unslash( $_GET['email_status'] ?? '' ) ),
	'period' => sanitize_text_field( wp_unslash( $_GET['period'] ?? '' ) ),
];
$selected_candidate_id = absint( $_GET['candidate_id'] ?? 0 );
$mail_action = sanitize_key( $_GET['mail_action'] ?? '' );
$analytics_view = 'table' === sanitize_key( $_GET['view'] ?? 'pipeline' ) ? 'table' : 'pipeline';
$page_url = admin_url( 'admin.php' );
$page_base_args = [ 'page' => 'recruitment-final-decision' ];
$tab_url = static function ( string $tab ) use ( $page_url, $page_base_args ): string {
	return add_query_arg( array_merge( $page_base_args, [ 'section' => $tab ] ), $page_url );
};

$database = new Recruitment_Database();
$applications = $database->get_applications();

$decision_notice = '';
if ( '1' === sanitize_text_field( wp_unslash( $_GET['decision_saved'] ?? '' ) ) ) {
	$decision_notice = 'Keputusan berhasil disimpan ke Oracle Database.';
}
if ( '1' === sanitize_text_field( wp_unslash( $_GET['vacancy_close_warning'] ?? '' ) ) ) {
	$decision_notice = 'Keputusan tersimpan, tetapi status lowongan WordPress belum berhasil ditutup. Periksa lowongan tersebut sebelum melanjutkan.';
}
if ( isset( $GLOBALS['daw_final_decision_error'] ) ) {
	$decision_notice = (string) $GLOBALS['daw_final_decision_error'];
}

$candidates = [];
foreach ( $applications as $app ) {
	$cand_id = (int) $app['applicant_id'];
	$app_id = (int) $app['id'];
	$created_at = (string) ( $app['created_at'] ?? '' );
	$year = $created_at ? date( 'Y', strtotime( $created_at ) ) : date( 'Y' );
	$batch = $created_at ? date( 'Y-m', strtotime( $created_at ) ) : date( 'Y-m' );
	$raw_status = strtoupper( (string) ( $app['status'] ?? 'SUBMITTED' ) );

	$final_decision = strtoupper( trim( (string) ( $app['hr_final_decision'] ?? '' ) ) );
	if ( '' === $final_decision ) {
		$final_decision = strtoupper( trim( (string) ( $app['final_status'] ?? '' ) ) );
	}
	if ( '' === $final_decision && in_array( $raw_status, [ 'DITERIMA', 'DITOLAK' ], true ) ) {
		$final_decision = $raw_status;
	}
	$decision = 'Menunggu Keputusan';
	if ( in_array( $final_decision, [ 'DITERIMA', 'ACCEPTED', 'LOLOS', 'Diterima' ], true ) ) {
		$decision = 'Diterima';
	} elseif ( in_array( $final_decision, [ 'DITOLAK', 'REJECTED', 'TIDAK_LOLOS', 'Tidak Diterima' ], true ) ) {
		$decision = 'Tidak Diterima';
	}
	$user_selection_status = trim( (string) ( $app['user_selection_status'] ?? '' ) );
	$user_recommendation = trim( (string) ( $app['user_recommendation'] ?? '' ) );
	$user_result = '' !== $user_selection_status
		? $user_selection_status . ( '' !== $user_recommendation ? ' · ' . $user_recommendation : '' )
		: 'Belum dinilai';

	$candidates[] = [
		'id'            => $cand_id,
		'application_id'=> $app_id,
		'name'          => (string) ( $app['applicant_name'] ?: 'Pelamar #' . $cand_id ),
		'code'          => 'DW-' . $year . '-' . str_pad( (string) $cand_id, 4, '0', STR_PAD_LEFT ),
		'position'      => (string) ( $app['title'] ?: 'Posisi belum ditentukan' ),
		'dealer'        => (string) ( $app['dealer'] ?: '-' ),
		'department'    => (string) ( $app['department'] ?: '-' ),
		'batch'         => $batch,
		'year'          => $year,
		'stage'          => (string) ( $app['stage'] ?? '' ),
		'hr_result'      => (string) ( $app['hr_result'] ?? '—' ),
		'user_result'   => $user_result,
		'decision'      => $decision,
		'decision_date' => $created_at ? date( 'Y-m-d', strtotime( $created_at ) ) : '',
		'email_status'  => 'Belum Dikirim',
		'email_date'    => '',
		'email'         => (string) ( $app['applicant_email'] ?: '' ),
		'user_interview_ready' => 'Lolos Wawancara User' === $user_selection_status,
	];
}

$analytics_records = [];
foreach ( $candidates as $cand ) {
	$analytics_records[] = [
		'name'          => $cand['name'],
		'code'          => $cand['code'],
		'batch'         => $cand['batch'],
		'year'          => $cand['year'],
		'dealer'        => $cand['dealer'],
		'department'    => $cand['department'],
		'position'      => $cand['position'],
		'period'        => 'Semester ' . ( (int) date( 'n', strtotime( $cand['decision_date'] ?: 'now' ) ) <= 6 ? '1' : '2' ),
		'source'        => 'Website Karier DAW',
		'stage'         => 'Final Decision',
		'status'        => $cand['decision'],
		'admin_pass'    => in_array( strtoupper( (string) ( $cand['stage'] ?? '' ) ), [ 'ADMINISTRASI', 'PSIKOTES', 'INTERVIEW_HR', 'INTERVIEW_USER', 'FINAL' ], true ),
		'psych_active'  => in_array( strtoupper( (string) ( $cand['stage'] ?? '' ) ), [ 'PSIKOTES', 'INTERVIEW_HR', 'INTERVIEW_USER', 'FINAL' ], true ),
		'psych_pass'    => in_array( strtoupper( (string) ( $cand['stage'] ?? '' ) ), [ 'INTERVIEW_HR', 'INTERVIEW_USER', 'FINAL' ], true ),
		'hr_interview'  => in_array( strtoupper( (string) ( $cand['stage'] ?? '' ) ), [ 'INTERVIEW_USER', 'FINAL' ], true ),
		'user_interview'=> $cand['user_interview_ready'],
	];
}

$options = [ 'batch' => [], 'year' => [], 'dealer' => [], 'department' => [], 'position' => [], 'user_result' => [], 'decision' => [], 'email_status' => [], 'period' => [] ];
foreach ( $candidates as $candidate ) {
	foreach ( $options as $key => $values ) {
		if ( isset( $candidate[ $key ] ) ) {
			$options[ $key ][ $candidate[ $key ] ] = $candidate[ $key ];
		}
	}
}
foreach ( $options as &$values ) {
	ksort( $values );
}
unset( $values );

$filtered_candidates = [];
foreach ( $candidates as $candidate ) {
	$searchable = strtolower( $candidate['name'] . ' ' . $candidate['code'] . ' ' . $candidate['position'] . ' ' . $candidate['dealer'] );
	$matches_search = '' === $filters['search'] || false !== strpos( $searchable, strtolower( $filters['search'] ) );
	$matches = $matches_search;
	foreach ( [ 'batch', 'year', 'dealer', 'department', 'position', 'user_result' ] as $key ) {
		if ( '' !== $filters[ $key ] && $candidate[ $key ] !== $filters[ $key ] ) {
			$matches = false;
		}
	}
	if ( 'decision' === $section && '' !== $filters['decision'] && $candidate['decision'] !== $filters['decision'] ) {
		$matches = false;
	}
	if ( 'email' === $section && '' !== $filters['decision'] && $candidate['decision'] !== $filters['decision'] ) {
		$matches = false;
	}
	if ( 'email' === $section && '' !== $filters['email_status'] && $candidate['email_status'] !== $filters['email_status'] ) {
		$matches = false;
	}
	if ( 'email' === $section && 'Menunggu Keputusan' === $candidate['decision'] ) {
		$matches = false;
	}
	if ( 'decision' === $section && 'Menunggu Keputusan' !== $candidate['decision'] ) {
		$matches = false;
	}
	if ( $matches ) {
		$filtered_candidates[] = $candidate;
	}
}

$selected_candidate = null;
foreach ( $candidates as $candidate ) {
	if ( (int) $candidate['id'] === $selected_candidate_id ) {
		$selected_candidate = $candidate;
		break;
	}
}

$analytics_filters = [ 'batch', 'year', 'dealer', 'department', 'position', 'period' ];
$filtered_analytics = [];
foreach ( $analytics_records as $record ) {
	$matches = true;
	foreach ( $analytics_filters as $key ) {
		if ( '' !== $filters[ $key ] && $record[ $key ] !== $filters[ $key ] ) {
			$matches = false;
		}
	}
	if ( '' !== $filters['search'] && false === strpos( strtolower( $record['name'] . ' ' . $record['code'] ), strtolower( $filters['search'] ) ) ) {
		$matches = false;
	}
	if ( $matches ) {
		$filtered_analytics[] = $record;
	}
}

$email_stats = [ 'total' => count( $filtered_candidates ), 'sent' => 0, 'read' => 0, 'pending' => 0, 'failed' => 0 ];
$decision_stats = [ 'total' => count( $filtered_candidates ), 'waiting' => 0, 'accepted' => 0, 'rejected' => 0 ];
foreach ( $filtered_candidates as $candidate ) {
	if ( 'Terkirim' === $candidate['email_status'] ) {
		$email_stats['sent']++;
	} elseif ( 'Terbaca' === $candidate['email_status'] ) {
		$email_stats['read']++;
	} elseif ( 'Gagal' === $candidate['email_status'] ) {
		$email_stats['failed']++;
	} else {
		$email_stats['pending']++;
	}
	if ( 'Diterima' === $candidate['decision'] ) {
		$decision_stats['accepted']++;
	} elseif ( 'Tidak Diterima' === $candidate['decision'] ) {
		$decision_stats['rejected']++;
	} else {
		$decision_stats['waiting']++;
	}
}

$analytics_stats = [ 'total' => count( $filtered_analytics ), 'admin' => 0, 'psych_active' => 0, 'psych_pass' => 0, 'hr' => 0, 'user' => 0, 'accepted' => 0, 'rejected' => 0 ];
$source_counts = [];
$accepted_sources = [];
foreach ( $filtered_analytics as $record ) {
	foreach ( [ 'admin_pass' => 'admin', 'psych_active' => 'psych_active', 'psych_pass' => 'psych_pass', 'hr_interview' => 'hr', 'user_interview' => 'user' ] as $source_key => $stat_key ) {
		if ( $record[ $source_key ] ) {
			$analytics_stats[ $stat_key ]++;
		}
	}
	if ( 'Diterima' === $record['status'] ) {
		$analytics_stats['accepted']++;
		$accepted_sources[ $record['source'] ] = ( $accepted_sources[ $record['source'] ] ?? 0 ) + 1;
	} elseif ( 'Tidak Diterima' === $record['status'] ) {
		$analytics_stats['rejected']++;
	}
	$source_counts[ $record['source'] ] = ( $source_counts[ $record['source'] ] ?? 0 ) + 1;
}
arsort( $source_counts );
arsort( $accepted_sources );
$pipeline = [
	[ 'label' => 'Lamaran Masuk', 'value' => $analytics_stats['total'] ],
	[ 'label' => 'Seleksi Administrasi', 'value' => $analytics_stats['admin'] ],
	[ 'label' => 'Psikotes', 'value' => $analytics_stats['psych_active'] ],
	[ 'label' => 'Wawancara HR', 'value' => $analytics_stats['hr'] ],
	[ 'label' => 'Wawancara User', 'value' => $analytics_stats['user'] ],
	[ 'label' => 'Final Decision', 'value' => ( $analytics_stats['accepted'] + $analytics_stats['rejected'] ) ],
];

$filter_options = static function ( string $key, array $values ) use ( $filters ): void {
	foreach ( $values as $value ) {
		printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( (string) $value ), selected( $filters[ $key ], (string) $value, false ), esc_html( (string) $value ) );
	}
};
$filter_form = static function ( string $tab, array $fields, string $search_placeholder = 'Cari nama, kode lamaran, atau posisi...' ) use ( $options, $filters, $page_url, $page_base_args, $filter_options ): void {
	?>
	<form method="get" class="daw-fd-filter daw-fd-filter--<?= esc_attr( $tab ) ?>" action="<?= esc_url( $page_url ) ?>">
		<input type="hidden" name="page" value="<?= esc_attr( $page_base_args['page'] ) ?>" />
		<input type="hidden" name="section" value="<?= esc_attr( $tab ) ?>" />
		<div class="daw-fd-filter__search"><input type="search" name="search" value="<?= esc_attr( $filters['search'] ) ?>" placeholder="<?= esc_attr( $search_placeholder ) ?>" /><button class="button" type="submit">Cari</button></div>
		<div class="daw-fd-filter__fields">
			<?php foreach ( $fields as $field => $label ) : ?>
				<label><span><?= esc_html( $label ) ?></span><select name="<?= esc_attr( $field ) ?>"><option value="">Semua</option><?php $filter_options( $field, $options[ $field ] ?? [] ); ?></select></label>
			<?php endforeach; ?>
			<button class="button button-primary" type="submit">Terapkan</button>
			<a class="button" href="<?= esc_url( add_query_arg( array_merge( $page_base_args, [ 'section' => $tab ] ), $page_url ) ) ?>">Reset</a>
		</div>
	</form>
	<?php
};
?>
<div class="wrap recruitment-admin daw-final-decision-page">
	<header class="daw-fd-heading">
		<p class="daw-fd-heading__breadcrumb">Recruitment <span>/</span> Keputusan Akhir</p>
		<h1>Keputusan Akhir</h1>
		<p>Buat keputusan rekrutmen final untuk kandidat yang telah menyelesaikan seluruh tahap seleksi.</p>
	</header>

	<nav class="daw-fd-tabs" aria-label="Final Decision">
		<a href="<?= esc_url( $tab_url( 'decision' ) ) ?>" class="<?= 'decision' === $section ? 'is-active' : '' ?>" <?= 'decision' === $section ? 'aria-current="page"' : '' ?>>Keputusan Akhir</a>
		<a href="<?= esc_url( $tab_url( 'email' ) ) ?>" class="<?= 'email' === $section ? 'is-active' : '' ?>" <?= 'email' === $section ? 'aria-current="page"' : '' ?>>Email &amp; Pemberitahuan</a>
		<a href="<?= esc_url( $tab_url( 'analytics' ) ) ?>" class="<?= 'analytics' === $section ? 'is-active' : '' ?>" <?= 'analytics' === $section ? 'aria-current="page"' : '' ?>>Statistik Rekrutmen</a>
	</nav>

	<?php if ( 'decision' === $section ) : ?>
		<?php if ( '' !== $decision_notice ) : ?><p class="daw-fd-notice"><?= esc_html( $decision_notice ) ?></p><?php endif; ?>
		<?php $filter_form( 'decision', [ 'position' => 'Posisi', 'dealer' => 'Dealer', 'decision' => 'Keputusan Akhir' ], 'Cari nama atau kode...' ); ?>
		<div class="daw-fd-table-card daw-fd-ready-card">
			<div class="daw-fd-ready-card__heading"><h2>Kandidat Siap Keputusan</h2></div>
			<div class="daw-fd-table-scroll"><table class="daw-fd-table daw-fd-ready-table">
				<thead><tr><th>Kandidat</th><th>Posisi</th><th>Dealer</th><th>Departemen</th><th>Wawancara HR</th><th>Wawancara User</th><th>Status Akhir</th><th>Aksi</th></tr></thead>
				<tbody>
				<?php if ( empty( $filtered_candidates ) ) : ?>
					<tr><td class="daw-fd-empty" colspan="8">Belum ada kandidat pada tahap ini.</td></tr>
				<?php else : ?>
					<?php foreach ( $filtered_candidates as $candidate ) : ?>
						<tr>
							<td><strong><?= esc_html( $candidate['name'] ) ?></strong><small><?= esc_html( $candidate['code'] ) ?></small></td>
							<td><?= esc_html( $candidate['position'] ) ?></td>
							<td><?= esc_html( $candidate['dealer'] ) ?></td>
							<td><?= esc_html( $candidate['department'] ?: '-' ) ?></td>
							<td><?= esc_html( $candidate['hr_result'] ?? '—' ) ?></td>
							<td><span class="daw-fd-badge <?= $candidate['user_interview_ready'] ? 'is-green' : 'is-amber' ?>"><?= esc_html( $candidate['user_result'] ) ?></span></td>
							<td><span class="daw-fd-badge <?= esc_attr( 'Diterima' === $candidate['decision'] ? 'is-green' : ( 'Tidak Diterima' === $candidate['decision'] ? 'is-red' : 'is-amber' ) ) ?>"><?= esc_html( $candidate['decision'] ) ?></span></td>
							<td>
								<?php if ( $candidate['user_interview_ready'] ) : ?>
									<form method="post" action="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-final-decision', 'section' => 'decision' ], $page_url ) ) ?>" class="daw-fd-decision-actions">
										<?php wp_nonce_field( 'daw_save_final_decision' ); ?>
										<input type="hidden" name="daw_final_decision_action" value="save_final_decision" />
										<input type="hidden" name="application_id" value="<?= esc_attr( (string) $candidate['application_id'] ) ?>" />
										<button class="button button-small <?= 'Diterima' === $candidate['decision'] ? 'button-primary' : '' ?>" type="submit" name="decision" value="accept">Terima</button>
										<button class="button button-small <?= 'Tidak Diterima' === $candidate['decision'] ? 'button-primary' : '' ?>" type="submit" name="decision" value="reject">Tolak</button>
									</form>
								<?php else : ?>
									<span class="daw-fd-muted-link">Menunggu hasil Wawancara User</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table></div>
		</div>

	<?php elseif ( 'email' === $section ) : ?>
		<section class="daw-fd-stats daw-fd-stats--five" aria-label="Ringkasan email">
			<article><span>Total Email</span><strong><?= esc_html( number_format_i18n( $email_stats['total'] ) ) ?></strong></article>
			<article><span>Terkirim</span><strong class="is-blue"><?= esc_html( number_format_i18n( $email_stats['sent'] ) ) ?></strong></article>
			<article><span>Terbaca</span><strong class="is-green"><?= esc_html( number_format_i18n( $email_stats['read'] ) ) ?></strong></article>
			<article><span>Belum Terbaca</span><strong class="is-amber"><?= esc_html( number_format_i18n( $email_stats['pending'] ) ) ?></strong></article>
			<article><span>Gagal Dikirim</span><strong class="is-red"><?= esc_html( number_format_i18n( $email_stats['failed'] ) ) ?></strong></article>
		</section>
		<?php $filter_form( 'email', [ 'batch' => 'Batch', 'position' => 'Posisi', 'dealer' => 'Dealer', 'decision' => 'Keputusan Akhir', 'email_status' => 'Status Email' ], 'Nama / kode...' ); ?>

		<?php if ( $selected_candidate && 'email' === $section ) : ?>
			<section class="daw-fd-mail-preview">
				<div><p class="daw-fd-eyebrow">Pratinjau Email</p><h2><?= esc_html( $selected_candidate['name'] ) ?></h2><p>Kepada: <?= esc_html( $selected_candidate['email'] ) ?></p></div>
				<p><strong>Subjek:</strong> Hasil akhir rekrutmen DAW</p>
				<p>Halo <?= esc_html( $selected_candidate['name'] ) ?>, status keputusan akhir Anda saat ini: <strong><?= esc_html( $selected_candidate['decision'] ) ?></strong>.</p>
				<?php if ( 'simulate' === $mail_action ) : ?><p class="daw-fd-notice">Simulasi pengiriman ditampilkan. Email nyata belum dikirim.</p><?php endif; ?>
				<div class="daw-fd-actions"><a class="button" href="<?= esc_url( $tab_url( 'email' ) ) ?>">Tutup</a><a class="button button-primary" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-final-decision', 'section' => 'email', 'candidate_id' => $selected_candidate['id'], 'mail_action' => 'simulate' ], $page_url ) ) ?>">Simulasikan Pengiriman</a></div>
			</section>
		<?php endif; ?>

		<div class="daw-fd-table-card">
			<div class="daw-fd-table-scroll"><table class="daw-fd-table daw-fd-email-table">
				<thead><tr><th></th><th>Nama Pelamar</th><th>Posisi / Dealer</th><th>Keputusan Akhir</th><th>Status Email</th><th>Tanggal Pengiriman</th><th>Aksi</th></tr></thead>
				<tbody>
				<?php if ( empty( $filtered_candidates ) ) : ?><tr><td class="daw-fd-empty" colspan="7">Tidak ada email yang sesuai dengan filter.</td></tr><?php endif; ?>
				<?php foreach ( $filtered_candidates as $candidate ) : ?>
					<tr>
						<td><input type="checkbox" aria-label="Pilih email untuk <?= esc_attr( $candidate['name'] ) ?>" /></td>
						<td><strong><?= esc_html( $candidate['name'] ) ?></strong><small><?= esc_html( $candidate['code'] ) ?></small></td>
						<td><?= esc_html( $candidate['position'] ) ?><small><?= esc_html( $candidate['dealer'] ) ?></small></td>
						<td><span class="daw-fd-badge <?= esc_attr( 'Diterima' === $candidate['decision'] ? 'is-green' : ( 'Tidak Diterima' === $candidate['decision'] ? 'is-red' : 'is-amber' ) ) ?>"><?= esc_html( $candidate['decision'] ) ?></span></td>
						<td><span class="daw-fd-badge <?= esc_attr( 'Terbaca' === $candidate['email_status'] ? 'is-green' : ( 'Terkirim' === $candidate['email_status'] ? 'is-blue' : ( 'Gagal' === $candidate['email_status'] ? 'is-red' : 'is-amber' ) ) ) ?>"><?= esc_html( $candidate['email_status'] ) ?></span></td>
						<td><?= '' !== $candidate['email_date'] ? esc_html( $candidate['email_date'] ) : '—' ?></td>
						<td><a class="daw-fd-text-link" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-final-decision', 'section' => 'email', 'candidate_id' => $candidate['id'] ], $page_url ) ) ?>">Lihat Email</a><?php if ( 'Belum Dikirim' !== $candidate['email_status'] ) : ?> <a class="daw-fd-muted-link" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-final-decision', 'section' => 'email', 'candidate_id' => $candidate['id'], 'mail_action' => 'simulate' ], $page_url ) ) ?>">Kirim Ulang</a><?php else : ?> <a class="daw-fd-text-link" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-final-decision', 'section' => 'email', 'candidate_id' => $candidate['id'], 'mail_action' => 'simulate' ], $page_url ) ) ?>">Kirim Email</a><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table></div>
		</div>
		<p class="daw-fd-footnote">Status email dan simulasi pengiriman untuk kandidat dari database Oracle DAW.</p>

	<?php else : ?>
		<?php
		$analytics_options = [
			'batch' => [ '1-2026', '2-2026', '3-2026' ],
			'year' => [ '2026' ],
			'dealer' => array_values( array_unique( array_column( $analytics_records, 'dealer' ) ) ),
			'department' => array_values( array_unique( array_column( $analytics_records, 'department' ) ) ),
			'position' => array_values( array_unique( array_column( $analytics_records, 'position' ) ) ),
			'period' => [ 'Semester 1', 'Semester 2' ],
		];
		?>
		<form method="get" class="daw-fd-analytics-filter" action="<?= esc_url( $page_url ) ?>">
			<input type="hidden" name="page" value="recruitment-final-decision" /><input type="hidden" name="section" value="analytics" />
			<div class="daw-fd-analytics-filter__heading"><div><strong>Filter Statistik</strong><small>Data pelamar bersumber langsung dari Oracle Database.</small></div><div class="daw-fd-view-toggle"><a class="<?= 'pipeline' === $analytics_view ? 'is-active' : '' ?>" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-final-decision', 'section' => 'analytics', 'view' => 'pipeline' ], $page_url ) ) ?>">Pipeline View</a><a class="<?= 'table' === $analytics_view ? 'is-active' : '' ?>" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-final-decision', 'section' => 'analytics', 'view' => 'table' ], $page_url ) ) ?>">Table View</a></div></div>
			<div class="daw-fd-analytics-filter__fields">
				<?php foreach ( [ 'batch' => 'Batch Rekrutmen', 'year' => 'Tahun', 'dealer' => 'Dealer', 'department' => 'Departemen', 'position' => 'Posisi', 'period' => 'Periode' ] as $field => $label ) : ?>
					<label><span><?= esc_html( $label ) ?></span><select name="<?= esc_attr( $field ) ?>"><option value="">Semua</option><?php foreach ( $analytics_options[ $field ] as $value ) : ?><option value="<?= esc_attr( $value ) ?>" <?= selected( $filters[ $field ], $value, false ) ?>><?= esc_html( $value ) ?></option><?php endforeach; ?></select></label>
				<?php endforeach; ?><button class="button button-primary" type="submit">Terapkan</button><a class="button" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-final-decision', 'section' => 'analytics', 'view' => $analytics_view ], $page_url ) ) ?>">Reset</a>
			</div>
		</form>

		<section class="daw-fd-stats daw-fd-stats--analytics" aria-label="Statistik rekrutmen">
			<article><span>Total Pelamar</span><strong><?= esc_html( number_format_i18n( $analytics_stats['total'] ) ) ?></strong></article>
			<article><span>Lolos Administrasi</span><strong><?= esc_html( number_format_i18n( $analytics_stats['admin'] ) ) ?></strong></article>
			<article><span>Mengikuti Psikotes</span><strong><?= esc_html( number_format_i18n( $analytics_stats['psych_active'] ) ) ?></strong></article>
			<article><span>Lolos Psikotes</span><strong><?= esc_html( number_format_i18n( $analytics_stats['psych_pass'] ) ) ?></strong></article>
			<article><span>Lolos Wawancara HR</span><strong><?= esc_html( number_format_i18n( $analytics_stats['hr'] ) ) ?></strong></article>
			<article><span>Wawancara User</span><strong><?= esc_html( number_format_i18n( $analytics_stats['user'] ) ) ?></strong></article>
			<article><span>Diterima</span><strong class="is-green"><?= esc_html( number_format_i18n( $analytics_stats['accepted'] ) ) ?></strong></article>
			<article><span>Tidak Diterima</span><strong class="is-red"><?= esc_html( number_format_i18n( $analytics_stats['rejected'] ) ) ?></strong></article>
		</section>

		<?php if ( 'pipeline' === $analytics_view ) : ?>
			<section class="daw-fd-panel">
				<h2>Recruitment Pipeline</h2><p>Pilih tahap untuk melihat banyak kandidat yang mencapai tahapan tersebut.</p>
				<div class="daw-fd-pipeline-cards">
					<?php foreach ( $pipeline as $step ) : ?><article><span><?= esc_html( $step['label'] ) ?></span><strong><?= esc_html( number_format_i18n( $step['value'] ) ) ?></strong><small>Dalam proses: <?= esc_html( number_format_i18n( max( 0, $step['value'] - $analytics_stats['accepted'] - $analytics_stats['rejected'] ) ) ) ?></small><div class="daw-fd-progress"><span style="width: <?= esc_attr( (string) ( $analytics_stats['total'] > 0 ? round( $step['value'] / $analytics_stats['total'] * 100 ) : 0 ) ) ?>%"></span></div></article><?php endforeach; ?>
				</div>
			</section>
			<div class="daw-fd-analytics-grid">
				<section class="daw-fd-panel"><h2>Sumber Informasi Lowongan</h2><p>Berdasarkan sumber informasi kandidat pada periode aktif.</p>
					<div class="daw-fd-sources"><?php foreach ( $source_counts as $source => $count ) : ?><div class="daw-fd-source"><span><?= esc_html( $source ) ?></span><div class="daw-fd-progress"><span style="width: <?= esc_attr( (string) ( $analytics_stats['total'] > 0 ? round( $count / $analytics_stats['total'] * 100 ) : 0 ) ) ?>%"></span></div><small><?= esc_html( (string) $count ) ?> · <?= esc_html( (string) ( $analytics_stats['total'] > 0 ? round( $count / $analytics_stats['total'] * 100 ) : 0 ) ) ?>%</small></div><?php endforeach; ?></div>
					<div class="daw-fd-mini-table"><strong>Sumber</strong><strong>Pelamar</strong><strong>Lolos Admin</strong><strong>Diterima</strong><?php foreach ( $source_counts as $source => $count ) : ?><span><?= esc_html( $source ) ?></span><span><?= esc_html( (string) $count ) ?></span><span><?= esc_html( (string) min( $count, max( 0, $count - 1 ) ) ) ?></span><span><?= esc_html( (string) ( $accepted_sources[ $source ] ?? 0 ) ) ?></span><?php endforeach; ?></div>
				</section>
				<section class="daw-fd-panel"><h2>Sumber Pelamar Diterima</h2><p>Distribusi kontribusi sumber berdasarkan kandidat yang diterima.</p>
					<ul class="daw-fd-accepted-sources"><?php foreach ( $accepted_sources as $source => $count ) : ?><li><span><?= esc_html( $source ) ?></span><strong><?= esc_html( (string) $count ) ?></strong></li><?php endforeach; ?></ul>
					<div class="daw-fd-acceptance"><span>Acceptance Rate</span><strong><?= esc_html( (string) ( $analytics_stats['total'] > 0 ? round( $analytics_stats['accepted'] / $analytics_stats['total'] * 100 ) : 0 ) ) ?>%</strong><small>Diterima dibanding total pelamar pada filter aktif.</small></div>
				</section>
			</div>
		<?php else : ?>
			<section class="daw-fd-table-card"><div class="daw-fd-table-scroll"><table class="daw-fd-table"><thead><tr><th>Kode Lamaran</th><th>Nama</th><th>Dealer / Departemen</th><th>Posisi</th><th>Sumber</th><th>Tahap</th><th>Status</th></tr></thead><tbody>
				<?php if ( empty( $filtered_analytics ) ) : ?><tr><td colspan="7" class="daw-fd-empty">Tidak ada data untuk filter ini.</td></tr><?php endif; ?>
				<?php foreach ( $filtered_analytics as $record ) : ?><tr><td><?= esc_html( $record['code'] ) ?></td><td><strong><?= esc_html( $record['name'] ) ?></strong></td><td><?= esc_html( $record['dealer'] ) ?><small><?= esc_html( $record['department'] ) ?></small></td><td><?= esc_html( $record['position'] ) ?></td><td><?= esc_html( $record['source'] ) ?></td><td><?= esc_html( $record['stage'] ) ?></td><td><span class="daw-fd-badge <?= esc_attr( 'Diterima' === $record['status'] ? 'is-green' : ( 'Tidak Diterima' === $record['status'] ? 'is-red' : 'is-amber' ) ) ?>"><?= esc_html( $record['status'] ) ?></span></td></tr><?php endforeach; ?>
				</tbody></table></div></section>
		<?php endif; ?>
		<p class="daw-fd-footnote">Statistik dan pipeline dihitung langsung dari data pelamar di Oracle Database DAW.</p>
	<?php endif; ?>
</div>