<?php
$current_user = wp_get_current_user();
$display_name = ! empty( $current_user->display_name ) ? $current_user->display_name : 'User Department';
$department_id = absint( get_user_meta( $current_user->ID, 'daw_department_id', true ) );
$department = trim( (string) get_user_meta( $current_user->ID, 'daw_department', true ) );
$department_account_id = absint( get_user_meta( $current_user->ID, 'daw_oracle_account_id', true ) );
$department_assigned = '' !== $department;
$department = $department_assigned ? $department : 'Belum diatur';
$view = sanitize_key( wp_unslash( $_GET['view'] ?? 'dashboard' ) );
$views = [ 'dashboard', 'requests', 'request-form', 'request-detail', 'interviews', 'interview-schedule', 'interview-detail', 'profile', 'applicants', 'results', 'account-settings' ];
if ( ! in_array( $view, $views, true ) ) {
	$view = 'dashboard';
}
$page_url = admin_url( 'admin.php?page=daw-user-department' );
$department_profile_pdf_url = static function ( int $application_id ): string {
	$url = add_query_arg(
		[
			'action' => 'daw_download_department_profile',
			'application_id' => $application_id,
		],
		admin_url( 'admin-post.php' )
	);
	return wp_nonce_url( $url, 'daw_download_department_profile_' . $application_id );
};
$department_document_url = static function ( int $application_id, string $field_key ): string {
	$url = add_query_arg(
		[
			'action' => 'daw_download_department_document',
			'application_id' => $application_id,
			'field_key' => $field_key,
		],
		admin_url( 'admin-post.php' )
	);
	return wp_nonce_url( $url, 'daw_download_department_document_' . $application_id . '_' . $field_key );
};
$view_url = static function ( string $requested_view ) use ( $page_url ): string {
	return add_query_arg( 'view', $requested_view, $page_url );
};
$view_titles = [
	'dashboard' => 'Dashboard',
	'requests' => 'Request Pembukaan Lowongan',
	'request-form' => 'Form Request Lowongan',
	'request-detail' => 'Detail Pengajuan',
	'interviews' => 'Kandidat Wawancara User',
	'interview-schedule' => 'Jadwal Wawancara User',
	'interview-detail' => 'Detail Kandidat',
	'profile' => 'Profil Lengkap Pelamar',
	'account-settings' => 'Pengaturan Akun',
	'applicants' => 'Data Pelamar',
	'results' => 'Hasil Wawancara User',
];
$request_filter = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
$interview_filter = sanitize_text_field( wp_unslash( $_GET['interview_status'] ?? '' ) );
$search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );

$requests = [];
$request_sections = [
	1 => [ 'title' => 'Informasi Permintaan', 'description' => 'Isi data dasar kebutuhan rekrutmen.', 'fields' => [
		[ 'key' => 'department', 'label' => 'Departemen', 'type' => 'text', 'required' => true, 'default' => $department ],
		[ 'key' => 'position', 'label' => 'Posisi yang Dibutuhkan', 'type' => 'text', 'required' => true, 'placeholder' => 'Contoh: Sales Executive' ],
		[ 'key' => 'job_level', 'label' => 'Level Jabatan', 'type' => 'select', 'options' => [ 'Staff', 'Supervisor', 'Manager', 'Head' ] ],
		[ 'key' => 'employment_type', 'label' => 'Kategori KJ', 'type' => 'select', 'options' => [ 'Pegawai Tetap', 'Kontrak', 'Magang' ] ],
		[ 'key' => 'hiring_user', 'label' => 'Hiring User', 'type' => 'text', 'required' => true, 'default' => $display_name ],
		[ 'key' => 'headcount', 'label' => 'Jumlah Kebutuhan', 'type' => 'number', 'required' => true, 'default' => '1' ],
		[ 'key' => 'employee_status', 'label' => 'Status Karyawan', 'type' => 'select', 'options' => [ 'Baru', 'Pengganti', 'Penambahan Tim' ] ],
		[ 'key' => 'priority', 'label' => 'Prioritas', 'type' => 'select', 'required' => true, 'options' => [ 'Tinggi', 'Sedang', 'Rendah', 'Urgent' ] ],
		[ 'key' => 'target_date', 'label' => 'Target Pemenuhan', 'type' => 'date' ],
		[ 'key' => 'location', 'label' => 'Lokasi Penempatan', 'type' => 'text', 'placeholder' => 'Contoh: Manado' ],
		[ 'key' => 'reason', 'label' => 'Alasan Kebutuhan', 'type' => 'textarea', 'placeholder' => 'Jelaskan alasan dibutuhkannya posisi ini...' ],
	] ],
	2 => [ 'title' => 'Tujuan Posisi', 'description' => 'Deskripsikan kontribusi dan peran yang diharapkan dari posisi ini.', 'fields' => [
		[ 'key' => 'role_summary', 'label' => 'Ringkasan Peran', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Deskripsikan secara singkat peran dan tanggung jawab utama...' ],
		[ 'key' => 'kpi', 'label' => 'KPI / Target Utama', 'type' => 'textarea', 'placeholder' => 'Contoh: Mencapai target penjualan bulanan...' ],
		[ 'key' => 'strategic_contribution', 'label' => 'Kontribusi Strategis', 'type' => 'textarea', 'placeholder' => 'Bagaimana posisi ini mendukung tujuan departemen...' ],
		[ 'key' => 'main_challenges', 'label' => 'Tantangan Utama', 'type' => 'textarea', 'placeholder' => 'Tantangan yang akan dihadapi pemegang posisi ini...' ],
	] ],
	3 => [ 'title' => 'Profil Kandidat', 'description' => 'Kualifikasi wajib (mandatory) dan yang diinginkan (preferred).', 'fields' => [
		[ 'key' => 'minimum_education', 'label' => 'Pendidikan Minimum', 'type' => 'select', 'required' => true, 'options' => [ 'SMA/SMK', 'D3', 'S1', 'S2' ] ],
		[ 'key' => 'major', 'label' => 'Jurusan', 'type' => 'text', 'placeholder' => 'Contoh: Manajemen, Teknik, semua jurusan' ],
		[ 'key' => 'minimum_experience', 'label' => 'Pengalaman Minimum', 'type' => 'text', 'placeholder' => 'Contoh: 2 tahun di bidang sales' ],
		[ 'key' => 'required_skills', 'label' => 'Keahlian Wajib', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Keahlian yang mutlak diperlukan...' ],
		[ 'key' => 'age_range', 'label' => 'Rentang Usia', 'type' => 'text', 'placeholder' => 'Contoh: 22–35 tahun' ],
		[ 'key' => 'certifications', 'label' => 'Sertifikasi', 'type' => 'text', 'placeholder' => 'Contoh: SIM A, sertifikasi profesional' ],
		[ 'key' => 'industry_experience', 'label' => 'Pengalaman Industri', 'type' => 'text', 'placeholder' => 'Contoh: Pernah bekerja di dealer otomotif' ],
		[ 'key' => 'preferred_skills', 'label' => 'Keahlian Tambahan', 'type' => 'textarea', 'placeholder' => 'Skill yang menjadi nilai plus...' ],
	] ],
	4 => [ 'title' => 'Validasi Requirement', 'description' => 'Konfirmasi ketersediaan budget dan keselarasan kompetensi.', 'fields' => [
		[ 'key' => 'salary_range', 'label' => 'Budget Gaji (Range)', 'type' => 'text', 'required' => true, 'placeholder' => 'Contoh: Rp 7.000.000 – Rp 10.000.000' ],
		[ 'key' => 'recruitment_budget', 'label' => 'Anggaran Rekrutmen', 'type' => 'select', 'required' => true, 'options' => [ 'Tersedia', 'Menunggu Persetujuan', 'Belum Tersedia' ] ],
		[ 'key' => 'headcount_confirmation', 'label' => 'Konfirmasi Headcount', 'type' => 'select', 'required' => true, 'options' => [ 'Sesuai Rencana', 'Perlu Persetujuan' ] ],
		[ 'key' => 'priority_competencies', 'label' => 'Kompetensi Utama yang Diprioritaskan', 'type' => 'textarea', 'placeholder' => 'Rangking kompetensi terpenting dari profil kandidat...' ],
		[ 'key' => 'validation_notes', 'label' => 'Catatan Validasi', 'type' => 'textarea', 'placeholder' => 'Catatan tambahan terkait requirement...' ],
	] ],
	5 => [ 'title' => 'Strategi Rekrutmen', 'description' => 'Channel sourcing dan timeline yang diusulkan.', 'fields' => [
		[ 'key' => 'sourcing_channels', 'label' => 'Channel Sourcing yang Disarankan', 'type' => 'checkboxes', 'options' => [ 'Job Portal (Jobstreet/LinkedIn)', 'Internal Referral', 'Walk-in / Karir.com', 'Social Media', 'Campus Recruitment' ] ],
		[ 'key' => 'sourcing_timeline', 'label' => 'Timeline Sourcing', 'type' => 'text', 'required' => true, 'placeholder' => 'Contoh: 2–3 minggu sejak posting' ],
		[ 'key' => 'selection_stages', 'label' => 'Tahap Seleksi', 'type' => 'select', 'required' => true, 'options' => [ 'Standar (Administrasi → Psikotes → Wawancara HR → Wawancara User)', 'Administrasi → Wawancara HR → Wawancara User', 'Administrasi → Tes Teknis → Wawancara User' ] ],
		[ 'key' => 'special_notes', 'label' => 'Catatan Khusus', 'type' => 'textarea', 'placeholder' => 'Ketentuan khusus dalam proses rekrutmen...' ],
	] ],
	6 => [ 'title' => 'Kesepakatan Akhir', 'description' => 'Tinjau seluruh informasi dan ajukan permintaan.', 'fields' => [
		[ 'key' => 'approval', 'label' => 'Saya menyatakan bahwa semua informasi yang diisi adalah benar dan telah mendapat persetujuan atasan langsung.', 'type' => 'checkbox', 'required' => true ],
		[ 'key' => 'signature', 'label' => 'Tanda Tangan Digital', 'type' => 'text', 'required' => true, 'placeholder' => 'Ketik nama lengkap sebagai tanda tangan' ],
	] ],
];
$stored_department_requests = get_user_meta( $current_user->ID, 'daw_department_job_requests', true );
$stored_department_requests = is_array( $stored_department_requests ) ? $stored_department_requests : [];
$request_code_numbers = [ absint( get_option( 'daw_department_request_sequence', 0 ) ) ];
foreach ( array_merge( array_column( $requests, 'code' ), array_keys( $stored_department_requests ) ) as $request_code ) {
	if ( preg_match( '/FPTK-\d{4}-(\d+)/', (string) $request_code, $matches ) ) {
		$request_code_numbers[] = (int) $matches[1];
	}
}
$new_request_code = sprintf( 'FPTK-%s-%03d', gmdate( 'Y' ), max( $request_code_numbers ) + 1 );
$request_code = sanitize_text_field( wp_unslash( $_GET['request_code'] ?? '' ) );
$request_step = min( 6, max( 1, absint( $_GET['step'] ?? 1 ) ) );
$request_form_error = 'required' === sanitize_key( wp_unslash( $_GET['request_error'] ?? '' ) ) ? 'Lengkapi field wajib dan pastikan Jumlah Kebutuhan berupa bilangan bulat positif. Isian sudah disimpan sebagai draft.' : '';
$request_form_notice = '';
$request_form_values = [];
$selected_request = null;

foreach ( $stored_department_requests as $saved_code => $saved_request ) {
	$fields = is_array( $saved_request['fields'] ?? null ) ? $saved_request['fields'] : [];
	$stored_headcount = filter_var( $fields['headcount'] ?? null, FILTER_VALIDATE_INT );
	$linked_vacancy_id = absint( $saved_request['vacancy_id'] ?? 0 );
	$linked_headcount = $linked_vacancy_id
		? filter_var( get_post_meta( $linked_vacancy_id, '_daw_headcount', true ), FILTER_VALIDATE_INT )
		: null;
	$linked_request_headcount = $linked_vacancy_id ? recruitment_get_vacancy_requested_headcount( $linked_vacancy_id ) : null;
	$vacancy_archived = $linked_vacancy_id && (
		'archived' === (string) get_post_meta( $linked_vacancy_id, '_daw_vacancy_status', true )
		|| ! recruitment_has_approved_vacancy_request( $linked_vacancy_id )
		|| ! is_int( $linked_headcount )
		|| 1 > $linked_headcount
		|| ( is_int( $linked_request_headcount ) && $linked_request_headcount !== $linked_headcount )
	);
	$request_summary = [
		'code' => $saved_code,
		'position' => (string) ( $fields['position'] ?? 'Posisi belum diisi' ),
		'count' => is_int( $stored_headcount ) && 1 <= $stored_headcount ? (string) $stored_headcount : 'Belum diatur',
		'priority' => (string) ( $fields['priority'] ?? 'Sedang' ),
		'date' => ! empty( $saved_request['updated_at'] ) ? date_i18n( 'd M Y', strtotime( $saved_request['updated_at'] ) ) : '—',
		'updated_at' => (string) ( $saved_request['updated_at'] ?? '' ),
		'status' => (string) ( $saved_request['status'] ?? 'Draft' ),
		'fields' => $fields,
		'hr_note' => (string) ( $saved_request['hr_note'] ?? '' ),
		'vacancy_archived' => $vacancy_archived,
	];
	$requests[] = $request_summary;
}

if ( 'request-form' === $view ) {
	if ( '' === $request_code || 'new' === $request_code || ( $request_code === $new_request_code && ! isset( $stored_department_requests[ $request_code ] ) ) ) {
		$request_code = $new_request_code;
		$request_form_values = [ 'department' => $department, 'headcount' => '1', 'hiring_user' => $display_name ];
	} elseif ( isset( $stored_department_requests[ $request_code ] ) ) {
		$request_form_values = (array) ( $stored_department_requests[ $request_code ]['fields'] ?? [] );
	} else {
		wp_die( esc_html__( 'Draft request tidak ditemukan atau bukan milik akun ini.', 'recruitment-plugin' ) );
	}

	if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) && 'save_department_request' === sanitize_key( wp_unslash( $_POST['daw_ud_action'] ?? '' ) ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_POST['daw_ud_request_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'daw_save_department_request' ) ) {
			wp_die( esc_html__( 'Sesi request tidak valid. Muat ulang halaman dan coba lagi.', 'recruitment-plugin' ) );
		}
		$posted_code = sanitize_text_field( wp_unslash( $_POST['request_code'] ?? '' ) );
		$posted_step = min( 6, max( 1, absint( $_POST['request_step'] ?? 1 ) ) );
		$action = sanitize_key( wp_unslash( $_POST['request_action'] ?? '' ) );
		$known_request = in_array( $posted_code, [ 'new', $new_request_code ], true ) || isset( $stored_department_requests[ $posted_code ] );
		if ( ! $known_request || ! isset( $request_sections[ $posted_step ] ) ) {
			wp_die( esc_html__( 'Request tidak valid.', 'recruitment-plugin' ) );
		}
		$is_new_request = 'new' === $posted_code || ( $posted_code === $new_request_code && ! isset( $stored_department_requests[ $posted_code ] ) );
		if ( 'new' === $posted_code ) {
			$posted_code = $new_request_code;
		}
		if ( $is_new_request && preg_match( '/FPTK-\d{4}-(\d+)/', $posted_code, $posted_code_parts ) ) {
			$last_sequence = absint( get_option( 'daw_department_request_sequence', 0 ) );
			if ( (int) $posted_code_parts[1] <= $last_sequence ) {
				$posted_code = sprintf( 'FPTK-%s-%03d', gmdate( 'Y' ), $last_sequence + 1 );
				$posted_code_parts[1] = (string) ( $last_sequence + 1 );
			}
			update_option( 'daw_department_request_sequence', (int) $posted_code_parts[1] );
		}
		$posted_fields = isset( $_POST['request_data'] ) && is_array( $_POST['request_data'] ) ? wp_unslash( $_POST['request_data'] ) : [];
		foreach ( $request_sections[ $posted_step ]['fields'] as $field ) {
			$key = $field['key'];
			if ( 'checkboxes' === $field['type'] ) {
				$allowed = $field['options'];
				$request_form_values[ $key ] = array_values( array_intersect( $allowed, array_map( 'sanitize_text_field', (array) ( $posted_fields[ $key ] ?? [] ) ) ) );
			} elseif ( 'checkbox' === $field['type'] ) {
				$request_form_values[ $key ] = isset( $posted_fields[ $key ] ) ? '1' : '';
			} elseif ( isset( $posted_fields[ $key ] ) && ! is_array( $posted_fields[ $key ] ) ) {
				$request_form_values[ $key ] = 'textarea' === $field['type']
					? sanitize_textarea_field( $posted_fields[ $key ] )
					: sanitize_text_field( $posted_fields[ $key ] );
			}
		}

		$missing_fields = [];
		if ( in_array( $action, [ 'next', 'submit' ], true ) ) {
			$steps_to_validate = 'submit' === $action ? array_keys( $request_sections ) : [ $posted_step ];
			foreach ( $steps_to_validate as $step_number ) {
				foreach ( $request_sections[ $step_number ]['fields'] as $field ) {
					if ( empty( $field['required'] ) ) {
						continue;
					}
					$value = $request_form_values[ $field['key'] ] ?? '';
					if ( ( 'checkbox' === $field['type'] && '1' !== $value ) || ( 'checkboxes' !== $field['type'] && 'checkbox' !== $field['type'] && '' === trim( (string) $value ) ) ) {
						$missing_fields[] = $field['label'];
					}
				}
			}
			if ( 'submit' === $action || ( 'next' === $action && 1 === $posted_step ) ) {
				$headcount = filter_var( $request_form_values['headcount'] ?? null, FILTER_VALIDATE_INT );
				if ( ! is_int( $headcount ) || 1 > $headcount ) {
					$missing_fields[] = 'Jumlah Kebutuhan';
				}
			}
		}
		if ( $missing_fields ) {
			$existing_request = $stored_department_requests[ $posted_code ] ?? [];
			$stored_department_requests[ $posted_code ] = [
				'code' => $posted_code,
				'status' => (string) ( $existing_request['status'] ?? 'Draft' ),
				'fields' => array_merge( (array) ( $existing_request['fields'] ?? [] ), $request_form_values ),
				'created_at' => $existing_request['created_at'] ?? current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
				'hr_note' => (string) ( $existing_request['hr_note'] ?? '' ),
			];
			update_user_meta( $current_user->ID, 'daw_department_job_requests', $stored_department_requests );
			wp_safe_redirect( add_query_arg( [ 'view' => 'request-form', 'request_code' => $posted_code, 'step' => $posted_step, 'request_error' => 'required' ], $page_url ) );
			exit;
		} else {
			$existing_request = $stored_department_requests[ $posted_code ] ?? [];
			$request_status = (string) ( $existing_request['status'] ?? 'Draft' );
			if ( 'submit' === $action ) {
				$request_status = 'Menunggu Review HR';
			} elseif ( 'save_draft' === $action ) {
				$request_status = 'Draft';
			}
			$stored_department_requests[ $posted_code ] = [
				'code' => $posted_code,
				'status' => $request_status,
				'fields' => array_merge( (array) ( $existing_request['fields'] ?? [] ), $request_form_values ),
				'created_at' => $existing_request['created_at'] ?? current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
				'hr_note' => (string) ( $existing_request['hr_note'] ?? '' ),
			];
			update_user_meta( $current_user->ID, 'daw_department_job_requests', $stored_department_requests );
			if ( 'save_draft' === $action || 'submit' === $action ) {
				wp_safe_redirect( add_query_arg( [ 'view' => 'request-detail', 'request_code' => $posted_code, 'saved' => '1' ], $page_url ) );
				exit;
			}
			$next_step = 'previous' === $action ? max( 1, $posted_step - 1 ) : min( 6, $posted_step + 1 );
			wp_safe_redirect( add_query_arg( [ 'view' => 'request-form', 'request_code' => $posted_code, 'step' => $next_step ], $page_url ) );
			exit;
		}
	}
}

if ( 'request-detail' === $view ) {
	$request_code = sanitize_text_field( wp_unslash( $_GET['request_code'] ?? '' ) );
	foreach ( $requests as $request ) {
		if ( $request['code'] === $request_code ) {
			$selected_request = $request;
			break;
		}
	}
}
$interview_database = new Recruitment_Database();
$interview_rows = $department_account_id > 0 ? $interview_database->get_user_interview_candidates( $department, $department_account_id ) : [];
$interview_load_error = $department_account_id < 1 || $interview_database->has_error()
	? 'Data wawancara belum dapat dimuat dari Oracle. Pastikan akun User Department telah ditautkan ke akun Oracle.'
	: '';
$interviews = [];
foreach ( $interview_rows as $row ) {
	$candidate_id = (int) ( $row['CANDIDATE_ID'] ?? 0 );
	$application_id = (int) ( $row['APPLICATION_ID'] ?? 0 );
	if ( $candidate_id < 1 || $application_id < 1 ) {
		continue;
	}
	if ( isset( $interview_booking_map[ (string) $candidate_id ] ) ) {
		$row['INTERVIEW_DATE'] = $interview_booking_map[ (string) $candidate_id ]['INTERVIEW_DATE'];
		$row['INTERVIEW_TIME'] = $interview_booking_map[ (string) $candidate_id ]['INTERVIEW_TIME'];
		$row['INTERVIEW_METHOD'] = $interview_booking_map[ (string) $candidate_id ]['INTERVIEW_METHOD'];
		$row['INTERVIEW_LOCATION'] = $interview_booking_map[ (string) $candidate_id ]['INTERVIEW_LOCATION'];
	}
	$scheduled = ! empty( $row['INTERVIEW_DATE'] );
	$has_result = '' !== trim( (string) ( $row['INTERVIEW_STATUS'] ?? '' ) );
	$interviews[] = [
		'id' => $application_id,
		'name' => (string) ( $row['CANDIDATE_NAME'] ?? '' ),
		'code' => (string) $application_id,
		'candidate_id' => $candidate_id,
		'position' => (string) ( $row['POSITION'] ?? '' ),
		'dealer' => (string) ( $row['DEALER'] ?? '' ),
		'assigned_department' => (string) ( $row['ASSIGNED_DEPARTMENT'] ?? '' ),
		'status' => $has_result ? 'Sudah Dinilai' : ( $scheduled ? 'Terjadwal' : 'Menunggu Jadwal' ),
		'selection_status' => (string) ( $row['INTERVIEW_STATUS'] ?? '' ),
		'final_decision' => (string) ( $row['FINAL_DECISION'] ?? '' ),
		'hr_result' => (string) ( $row['HR_INTERVIEW_RESULT'] ?? $row['HR_RESULT'] ?? 'Belum tersedia' ),
		'hr_recommendation' => (string) ( $row['HR_RECOMMENDATION'] ?? '' ),
		'hr_interviewer' => (string) ( $row['HR_INTERVIEWER'] ?? '' ),
		'hr_notes' => (string) ( $row['HR_INTERVIEW_NOTES'] ?? '' ),
		'scheduled_interviewer' => (string) ( $row['SCHEDULED_INTERVIEWER'] ?? '' ),
		'date' => trim( (string) ( $row['INTERVIEW_DATE'] ?? '' ) . ' ' . (string) ( $row['INTERVIEW_TIME'] ?? '' ) ),
		'method' => [
			'ONLINE' => 'Online',
			'OFFLINE' => 'Tatap muka',
		][ strtoupper( trim( (string) ( $row['INTERVIEW_METHOD'] ?? '' ) ) ) ] ?? ( trim( (string) ( $row['INTERVIEW_METHOD'] ?? '' ) ) ?: 'Belum ditentukan' ),
		'location' => (string) ( $row['INTERVIEW_LOCATION'] ?? 'Belum ditentukan' ),
		'meeting_link' => trim( (string) ( $row['INTERVIEW_LINK'] ?? '' ) ),
		'score' => (string) ( $row['SCORE'] ?? '' ),
		'recommendation' => (string) ( $row['REKOMENDASI'] ?? '' ),
		'notes' => (string) ( $row['INTERVIEW_NOTES'] ?? '' ),
		'interviewer' => (string) ( $row['INTERVIEWER'] ?? '' ),
		'saved_at' => (string) ( $row['RESULT_DATE'] ?? '' ),
		'scores' => [
			'presentation' => (int) ( $row['SCORE_PRESENTATION'] ?? 0 ),
			'communication' => (int) ( $row['SCORE_COMMUNICATION'] ?? 0 ),
			'motivation' => (int) ( $row['SCORE_MOTIVATION'] ?? 0 ),
			'experience' => (int) ( $row['SCORE_EXPERIENCE'] ?? 0 ),
			'knowledge' => (int) ( $row['SCORE_KNOWLEDGE'] ?? 0 ),
			'teamwork' => (int) ( $row['SCORE_TEAMWORK'] ?? 0 ),
			'problem_solving' => (int) ( $row['SCORE_PROBLEM_SOLVING'] ?? 0 ),
			'adaptability' => (int) ( $row['SCORE_ADAPTABILITY'] ?? 0 ),
		],
	];
}
$rating_criteria = [
	'presentation' => 'Penampilan & Kerapian',
	'communication' => 'Kemampuan Komunikasi',
	'motivation' => 'Motivasi & Antusiasme',
	'experience' => 'Relevansi Pengalaman',
	'knowledge' => 'Pengetahuan Teknis/Produk',
	'teamwork' => 'Kemampuan Kerjasama Tim',
	'problem_solving' => 'Problem Solving',
	'adaptability' => 'Kemampuan Adaptasi',
];
$stored_interview_results = [];
$interview_submission = $GLOBALS['daw_user_interview_submission'] ?? [];
$posted_scores = is_array( $interview_submission['scores'] ?? null ) ? $interview_submission['scores'] : [];
$posted_recommendation = (string) ( $interview_submission['recommendation'] ?? '' );
$posted_selection_status = (string) ( $interview_submission['selection_status'] ?? '' );
$posted_interviewer = (string) ( $interview_submission['interviewer'] ?? '' );
$posted_notes = (string) ( $interview_submission['notes'] ?? '' );
$interview_save_error = (string) ( $interview_submission['error'] ?? '' );
$interview_saved = '1' === sanitize_text_field( wp_unslash( $_GET['saved'] ?? '' ) );

$selected_interview = null;
$selected_interview_code = sanitize_text_field( wp_unslash( $_GET['candidate_code'] ?? $_POST['candidate_code'] ?? '' ) );
foreach ( $interviews as $interview ) {
	if ( $interview['code'] === $selected_interview_code ) {
		$selected_interview = $interview;
		break;
	}
}
$applicants = array_map(
	static function ( array $interview ): array {
		$status = (string) ( $interview['selection_status'] ?? '' );
		$final_decision = (string) ( $interview['final_decision'] ?? '' );

		return [
			'name' => $interview['name'],
			'code' => $interview['code'],
			'position' => $interview['position'],
			'dealer' => $interview['dealer'],
			'date' => $interview['date'],
			'hr_result' => $interview['hr_result'],
			'user_result' => $interview['recommendation'] ?: 'Belum dinilai',
			'status' => '' !== $final_decision ? $final_decision : ( '' !== $status ? $status : 'Dalam proses' ),
		];
	},
	$interviews
);
$interview_result_rows = $department_account_id > 0 ? $interview_database->get_user_interview_results( $department, $department_account_id ) : [];
if ( $interview_database->has_error() ) {
	$interview_load_error = 'Data hasil wawancara belum dapat dimuat dari Oracle. Silakan coba kembali.';
}
$results = array_map(
	static function ( array $row ): array {
		return [
			'name' => (string) ( $row['CANDIDATE_NAME'] ?? '' ),
			'code' => (string) ( $row['APPLICATION_ID'] ?? '' ),
			'position' => (string) ( $row['POSITION'] ?? '' ),
			'date' => (string) ( $row['INTERVIEW_RESULT_DATE'] ?? '' ),
			'score' => (int) ( $row['SCORE'] ?? 0 ),
			'recommendation' => (string) ( $row['REKOMENDASI'] ?? '' ),
			'decision' => (string) ( $row['FINAL_DECISION'] ?? '' ) ?: 'Pending',
		];
	},
	$interview_result_rows
);
$selected_department_profile = null;
$profile_load_error = '';
if ( in_array( $view, [ 'profile', 'interview-detail' ], true ) && $selected_interview && $department_account_id > 0 ) {
	$selected_department_profile = $interview_database->get_user_department_candidate_detail( $selected_interview_code, $department_account_id );
	if ( $interview_database->has_error() ) {
		$profile_load_error = 'Sebagian data profil dan lamaran belum dapat dimuat dari Oracle. Silakan coba kembali.';
	}
}

$request_statuses = array_fill_keys( [ 'Draft', 'Menunggu Review HR', 'Perlu Revisi', 'Disetujui', 'Lowongan Dibuka' ], 0 );
$active_requests = array_values( array_filter( $requests, static fn( array $request ): bool => empty( $request['vacancy_archived'] ) ) );
foreach ( $active_requests as $request ) {
	if ( isset( $request_statuses[ $request['status'] ] ) ) {
		++$request_statuses[ $request['status'] ];
	}
}
$recent_requests = $active_requests;
usort(
	$recent_requests,
	static function ( array $left, array $right ): int {
		return strtotime( $right['updated_at'] ?? '' ) <=> strtotime( $left['updated_at'] ?? '' );
	}
);
$recent_requests = array_slice( $recent_requests, 0, 4 );
$filtered_requests = [];
foreach ( $active_requests as $request ) {
	$matches_status = '' === $request_filter || $request['status'] === $request_filter || ( 'Review HR' === $request_filter && 'Menunggu Review HR' === $request['status'] );
	$matches_search = '' === $search || false !== strpos( strtolower( $request['code'] . ' ' . $request['position'] ), strtolower( $search ) );
	if ( $matches_status && $matches_search ) {
		$filtered_requests[] = $request;
	}
}
$filtered_interviews = [];
foreach ( $interviews as $interview ) {
	$matches_search = '' === $search || false !== strpos( strtolower( $interview['name'] . ' ' . $interview['code'] . ' ' . $interview['position'] ), strtolower( $search ) );
	$matches_status = '' === $interview_filter || $interview['status'] === $interview_filter;
	if ( $matches_search && $matches_status ) {
		$filtered_interviews[] = $interview;
	}
}
$filtered_applicants = [];
foreach ( $applicants as $applicant ) {
	$matches_search = '' === $search || false !== strpos( strtolower( $applicant['name'] . ' ' . $applicant['code'] . ' ' . $applicant['position'] ), strtolower( $search ) );
	if ( $matches_search ) {
		$filtered_applicants[] = $applicant;
	}
}
$interview_stats = [ 'waiting' => 0, 'scheduled' => 0, 'done' => 0 ];
foreach ( $interviews as $interview ) {
	$key = [ 'Menunggu Jadwal' => 'waiting', 'Terjadwal' => 'scheduled', 'Sudah Dinilai' => 'done' ][ $interview['status'] ];
	++$interview_stats[ $key ];
}
$applicant_stats = [ 'accepted' => 0, 'rejected' => 0, 'in_progress' => 0 ];
foreach ( $applicants as $applicant ) {
	if ( 'Diterima' === $applicant['status'] ) {
		++$applicant_stats['accepted'];
	} elseif ( 'Ditolak' === $applicant['status'] ) {
		++$applicant_stats['rejected'];
	} else {
		++$applicant_stats['in_progress'];
	}
}
$result_stats = [ 'recommended' => 0, 'accepted' => 0, 'pending' => 0 ];
foreach ( $results as $result ) {
	if ( 'Terima' === $result['recommendation'] ) {
		++$result_stats['recommended'];
	}
	if ( 'Diterima' === $result['decision'] ) {
		++$result_stats['accepted'];
	} elseif ( 'Pending' === $result['decision'] ) {
		++$result_stats['pending'];
	}
}
$greeting_name = trim( (string) preg_replace( '/\s+.*/', '', $display_name ) );
?>
<div class="daw-user-department">
	<aside class="daw-ud-sidebar" aria-label="Navigasi User Department">
		<a class="daw-ud-brand" href="<?= esc_url( $view_url( 'dashboard' ) ) ?>"><span class="daw-ud-brand__mark">D</span><span><strong>Daya Adicipta Wisesa</strong><small>User Department</small></span></a>
		<div class="daw-ud-identity"><span>User Department</span><strong><?= esc_html( $department ) ?> — <?= esc_html( $display_name ) ?></strong></div>
		<nav class="daw-ud-nav" aria-label="Menu User Department">
			<a class="<?= 'dashboard' === $view ? 'is-active' : '' ?>" href="<?= esc_url( $view_url( 'dashboard' ) ) ?>"><span class="dashicons dashicons-dashboard" aria-hidden="true"></span>Dashboard</a>
			<a class="<?= 'requests' === $view ? 'is-active' : '' ?>" href="<?= esc_url( $view_url( 'requests' ) ) ?>"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span>Request Lowongan</a>
			<a class="<?= 'interviews' === $view ? 'is-active' : '' ?>" href="<?= esc_url( $view_url( 'interviews' ) ) ?>"><span class="dashicons dashicons-groups" aria-hidden="true"></span><span>Kandidat Wawancara User</span><b><?= esc_html( (string) ( $interview_stats['waiting'] + $interview_stats['scheduled'] ) ) ?></b></a>
			<a class="<?= 'interview-schedule' === $view ? 'is-active' : '' ?>" href="<?= esc_url( $view_url( 'interview-schedule' ) ) ?>"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>Jadwal Wawancara</a>
			<a class="<?= 'applicants' === $view ? 'is-active' : '' ?>" href="<?= esc_url( $view_url( 'applicants' ) ) ?>"><span class="dashicons dashicons-database" aria-hidden="true"></span>Data Pelamar</a>
			<a class="<?= 'results' === $view ? 'is-active' : '' ?>" href="<?= esc_url( $view_url( 'results' ) ) ?>"><span class="dashicons dashicons-list-view" aria-hidden="true"></span>Hasil Wawancara User</a>
			<a class="<?= 'account-settings' === $view ? 'is-active' : '' ?>" href="<?= esc_url( $view_url( 'account-settings' ) ) ?>"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>Pengaturan Akun</a>
		</nav>
		<div class="daw-ud-sidebar__footer"><span class="dashicons dashicons-building" aria-hidden="true"></span><?= esc_html( $department ) ?> Department</div>
	</aside>

	<div class="daw-ud-workspace">
		<header class="daw-ud-topbar"><h1><?= esc_html( $view_titles[ $view ] ) ?></h1><div class="daw-ud-topbar__account"><div class="daw-ud-topbar__identity"><span class="daw-ud-user-avatar"><?= esc_html( strtoupper( substr( $greeting_name, 0, 1 ) ) ) ?></span><span><strong><?= esc_html( $display_name ) ?></strong><small><?= esc_html( $department ) ?> Department</small></span></div><?= recruitment_logout_form( 'Logout', recruitment_get_public_url( 'login', [ 'target' => 'hr' ] ), 'daw-ud-topbar__logout' ) ?></div></header>
		<main class="daw-ud-main">
			<p class="daw-ud-demo-note" role="note">Daftar kandidat dan hasil wawancara User Department menggunakan data Oracle. Form request lowongan pada halaman ini masih berupa prototipe.</p>
			<?php if ( '' !== $interview_load_error ) : ?><p class="daw-ud-alert" role="alert"><?= esc_html( $interview_load_error ) ?></p><?php endif; ?>
			<?php if ( 'account-settings' === $view ) : ?>
				<?php
				$password_notice = sanitize_key( wp_unslash( $_GET['password_notice'] ?? '' ) );
				$password_messages = [
					'password_updated' => [ 'Password berhasil diperbarui. Silakan login kembali.', 'success' ],
					'current_password' => [ 'Password saat ini tidak sesuai.', 'error' ],
					'invalid' => [ 'Pastikan password baru minimal 8 karakter dan konfirmasinya sesuai.', 'error' ],
					'unavailable' => [ 'Password tidak dapat diperbarui karena layanan Oracle belum tersedia.', 'error' ],
				];
				?>
				<section class="daw-account-settings">
					<header class="daw-account-settings__heading">
						<div>
							<p class="daw-account-settings__breadcrumb">User Department <span>/</span> Pengaturan Akun</p>
							<h2>Pengaturan Akun</h2>
							<p>Ubah password akun department yang sedang digunakan.</p>
						</div>
					</header>
					<?php if ( isset( $password_messages[ $password_notice ] ) ) : ?>
						<div class="daw-account-settings__notice <?= esc_attr( 'error' === $password_messages[ $password_notice ][1] ? 'is-error' : '' ) ?>" role="status"><?= esc_html( $password_messages[ $password_notice ][0] ) ?></div>
					<?php endif; ?>
					<section class="daw-account-settings__panel" aria-label="Ubah password User Department">
						<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
							<input type="hidden" name="action" value="daw_change_department_password">
							<?php wp_nonce_field( 'daw_change_department_password' ); ?>
							<div class="daw-account-settings__form-grid">
								<label>Password saat ini<input type="password" name="current_password" autocomplete="current-password" required></label>
								<label>Password baru<input type="password" name="new_password" minlength="8" autocomplete="new-password" required></label>
								<label>Konfirmasi password baru<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>
							</div>
							<footer><button type="submit" class="daw-account-settings__save">Simpan Password</button></footer>
						</form>
					</section>
				</section>
			<?php elseif ( 'interview-schedule' === $view ) : ?>
				<?php require recruitment_get_plugin_path( 'admin/components/interview-schedule-calendar.php' ); ?>
			<?php elseif ( 'profile' === $view ) : ?>
				<?php if ( ! $selected_interview || ! $selected_department_profile ) : ?>
					<section class="daw-ud-profile-overlay"><article class="daw-ud-profile-sheet"><p><?= esc_html( $profile_load_error ?: 'Profil pelamar tidak ditemukan atau tidak tersedia untuk departemen ini.' ) ?></p><a class="button" href="<?= esc_url( $view_url( 'applicants' ) ) ?>">Kembali</a></article></section>
				<?php else : ?>
					<?php
					$application = $selected_department_profile['application'];
					$profile = $selected_department_profile['profile'];
					$profile_details = is_array( $profile['profile_details'] ?? null ) ? $profile['profile_details'] : [];
					$answer_values = [];
					foreach ( (array) ( $profile_details['application_answers'] ?? [] ) as $answer_key => $answer_value ) {
						if ( is_scalar( $answer_value ) ) {
							$answer_values[ (string) $answer_key ] = (string) $answer_value;
						}
					}
					foreach ( (array) $selected_department_profile['answers'] as $answer ) {
						$key = (string) ( $answer['field_key'] ?? '' );
						$value = trim( (string) ( $answer['file_name'] ?? '' ) );
						if ( '' === $value ) {
							$value = trim( (string) ( $answer['answer_value'] ?? '' ) );
						}
						if ( '' !== $key && '' !== $value ) {
							$answer_values[ $key ] = $value;
						}
					}
					$education_level = (string) ( $profile_details['education_level'] ?? $answer_values['pendidikan_formal_1_tingkat'] ?? $profile['education'] ?? '' );
					$institution = (string) ( $profile_details['institution'] ?? $answer_values['pendidikan_formal_1_nama_sekolah'] ?? '' );
					$major = (string) ( $profile_details['major'] ?? $answer_values['pendidikan_formal_1_program_studi'] ?? '' );
					$gpa = (string) ( $profile_details['gpa'] ?? $answer_values['pendidikan_formal_1_ipk'] ?? '' );
					$graduation_year = (string) ( $profile_details['graduation_year'] ?? $answer_values['pendidikan_formal_1_tahun_lulus'] ?? '' );
					$education_rows = ( '' !== $education_level || '' !== $institution || '' !== $major || '' !== $gpa || '' !== $graduation_year )
						? [ [ trim( $education_level . ( '' !== $major ? ' ' . $major : '' ) ), $institution, $gpa, $graduation_year ] ]
						: [];
					$experience_rows = [];
					foreach ( (array) ( $profile_details['work_experiences'] ?? [] ) as $experience ) {
						if ( ! is_array( $experience ) ) {
							continue;
						}
						$experience_rows[] = [
							(string) ( $experience['position'] ?? '' ),
							(string) ( $experience['company'] ?? '' ),
							(string) ( $experience['period'] ?? '' ),
							(string) ( $experience['description'] ?? '' ),
						];
					}
					$application_id = (int) ( $application['application_id'] ?? 0 );
					$document_labels = [ 'resume' => 'CV / Resume' ];
					$document_rows = [];
					foreach ( $document_labels as $document_key => $document_label ) {
						$document = $profile_details['documents'][ $document_key ] ?? null;
						if ( is_array( $document ) && '' !== (string) ( $document['name'] ?? '' ) ) {
							$document_rows[] = [
								'label' => $document_label,
								'name' => (string) $document['name'],
								'url' => recruitment_get_public_url(
									'applicant-file',
									[
										'application_id' => $application_id,
										'candidate_id' => (int) ( $application['candidate_id'] ?? 0 ),
										'document' => $document_key,
									]
								),
							];
						}
					}
					foreach ( (array) $selected_department_profile['answers'] as $answer ) {
						$field_key = (string) ( $answer['field_key'] ?? '' );
						$file_name = trim( (string) ( $answer['file_name'] ?? '' ) );
						if ( '' !== $file_name && preg_match( '/\A[a-zA-Z0-9_]{1,100}\z/', $field_key ) ) {
							$document_rows[] = [
								'label' => (string) ( $answer['field_title'] ?? $field_key ),
								'name' => $file_name,
								'url' => $department_document_url( $application_id, $field_key ),
							];
						}
					}
					$history_rows = array_reverse( (array) $selected_department_profile['history'] );
					$selection_rows = [];
					$stage_labels = [
						'ADMINISTRASI' => 'Seleksi Administrasi',
						'PSIKOTES' => 'Psikotes',
						'INTERVIEW_HR' => 'Wawancara HR',
						'INTERVIEW_USER' => 'Wawancara User',
						'FINAL' => 'Keputusan Akhir',
					];
					$status_labels = [
						'SUBMITTED' => 'Lamaran diterima',
						'SCREENING' => 'Dalam seleksi',
						'PSIKOTES' => 'Dalam psikotes',
						'INTERVIEW_HR' => 'Lolos ke wawancara HR',
						'INTERVIEW_USER' => 'Diteruskan ke departemen',
						'DITERIMA' => 'Diterima',
						'DITOLAK' => 'Tidak lolos',
					];
					foreach ( $history_rows as $history ) {
						$stage_key = strtoupper( (string) ( $history['stage'] ?? '' ) );
						$status_key = strtoupper( (string) ( $history['status'] ?? '' ) );
						$history_time = strtotime( (string) ( $history['date'] ?? '' ) );
						$selection_rows[] = [
							$stage_labels[ $stage_key ] ?? ucwords( strtolower( str_replace( '_', ' ', $stage_key ) ) ),
							$status_labels[ $status_key ] ?? ucwords( strtolower( str_replace( '_', ' ', $status_key ) ) ),
							false !== $history_time ? date_i18n( 'j M Y', $history_time ) : '—',
							'DITERIMA' === $status_key ? 'is-green' : ( 'DITOLAK' === $status_key ? 'is-red' : 'is-amber' ),
						];
					}
					$application_time = strtotime( (string) ( $application['application_date'] ?? '' ) );
					$full_profile = [
						'application_date' => false !== $application_time ? date_i18n( 'j M Y', $application_time ) : '—',
						'application_id' => (string) ( $application['application_id'] ?? '—' ),
						'psychotest_score' => (string) ( $application['psychotest_score'] ?? 'Belum tersedia' ),
						'hr_result' => (string) ( $application['hr_interview_result'] ?? 'Belum tersedia' ),
						'hr_recommendation' => (string) ( $application['hr_recommendation'] ?? 'Belum tersedia' ),
						'hr_interviewer' => (string) ( $application['hr_interviewer'] ?? 'Belum tersedia' ),
						'hr_notes' => (string) ( $application['hr_interview_notes'] ?? '' ),
						'department_account' => trim( (string) ( $application['department_account_username'] ?? '' ) . ' · ' . (string) ( $application['department_account_email'] ?? '' ) ),
						'assigned_department' => (string) ( $application['assigned_department'] ?? $department ),
						'user_result' => (string) ( $application['user_interview_result'] ?? 'Belum dinilai' ),
						'user_score' => (string) ( $application['user_interview_score'] ?? '—' ),
						'user_recommendation' => (string) ( $application['user_recommendation'] ?? 'Belum tersedia' ),
						'user_interviewer' => (string) ( $application['user_interviewer'] ?? 'Belum tersedia' ),
						'user_notes' => (string) ( $application['user_interview_notes'] ?? '' ),
						'identity' => [
							'gender' => (string) ( $profile['gender'] ?: 'Belum tersedia' ),
							'birthplace' => (string) ( $profile['birth_place'] ?: 'Belum tersedia' ),
							'birthdate' => (string) ( $profile['birth_date'] ?: 'Belum tersedia' ),
							'email' => (string) ( $profile['email'] ?: 'Belum tersedia' ),
							'phone' => (string) ( $profile['phone'] ?: 'Belum tersedia' ),
							'province' => (string) ( $profile['province'] ?: 'Belum tersedia' ),
							'city' => (string) ( $profile['city'] ?: 'Belum tersedia' ),
							'address' => (string) ( $profile['address'] ?: 'Belum tersedia' ),
						],
						'education' => $education_rows,
						'experience' => $experience_rows,
						'documents' => $document_rows,
						'selection' => $selection_rows,
					];
					$return_view = sanitize_key( wp_unslash( $_GET['return'] ?? 'interviews' ) );
					if ( ! in_array( $return_view, [ 'interviews', 'interview-detail', 'applicants' ], true ) ) {
						$return_view = 'interviews';
					}
					$return_args = [ 'view' => $return_view ];
					if ( 'interview-detail' === $return_view ) {
						$return_args['candidate_code'] = $selected_interview['code'];
					}
					?>
					<?php if ( '' !== $profile_load_error ) : ?><p class="daw-ud-alert" role="alert"><?= esc_html( $profile_load_error ) ?></p><?php endif; ?>
					<section class="daw-ud-profile-overlay" aria-label="Profil lengkap pelamar">
						<article class="daw-ud-profile-sheet" data-candidate-profile data-candidate-code="<?= esc_attr( $selected_interview['code'] ) ?>">
							<header class="daw-ud-profile-sheet__header"><div><h2>Profil Lengkap Pelamar</h2><small><?= esc_html( $selected_interview['code'] ) ?></small></div><div><a class="daw-ud-profile-download" href="<?= esc_url( $department_profile_pdf_url( (int) $application_id ) ) ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span>Download Profil</a><a class="daw-ud-profile-close" href="<?= esc_url( add_query_arg( $return_args, $page_url ) ) ?>" aria-label="Tutup profil">&times;</a></div></header>
							<section class="daw-ud-profile-section"><h3>Identitas / Data Diri</h3><div class="daw-ud-profile-identity"><span class="daw-ud-candidate-avatar"><?= esc_html( strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $selected_interview['name'] ), 0, 2 ) ) ) ?></span><div class="daw-ud-profile-identity__fields">
									<div><small>Nama Lengkap</small><strong><?= esc_html( $selected_interview['name'] ) ?></strong></div><div><small>Jenis Kelamin</small><strong><?= esc_html( $full_profile['identity']['gender'] ) ?></strong></div>
									<div><small>Tempat Lahir</small><strong><?= esc_html( $full_profile['identity']['birthplace'] ) ?></strong></div><div><small>Tanggal Lahir</small><strong><?= esc_html( $full_profile['identity']['birthdate'] ) ?></strong></div>
									<div><small>Email</small><strong><?= esc_html( $full_profile['identity']['email'] ) ?></strong></div><div><small>No. Telepon</small><strong><?= esc_html( $full_profile['identity']['phone'] ) ?></strong></div>
									<div><small>Provinsi</small><strong><?= esc_html( $full_profile['identity']['province'] ) ?></strong></div><div><small>Kota</small><strong><?= esc_html( $full_profile['identity']['city'] ) ?></strong></div><div><small>Alamat</small><strong><?= esc_html( $full_profile['identity']['address'] ) ?></strong></div>
								</div></div></section>
							<section class="daw-ud-profile-section"><h3>Data Lamaran</h3><div class="daw-ud-profile-application"><div><small>Kode Lamaran</small><strong><?= esc_html( $full_profile['application_id'] ) ?></strong></div><div><small>Posisi Dilamar</small><strong><?= esc_html( $selected_interview['position'] ) ?></strong></div><div><small>Dealer</small><strong><?= esc_html( $selected_interview['dealer'] ) ?></strong></div><div><small>Tgl Lamaran</small><strong><?= esc_html( $full_profile['application_date'] ) ?></strong></div><div><small>Status</small><strong><?= esc_html( (string) ( $application['application_status'] ?? 'Belum tersedia' ) ) ?></strong></div></div></section>
							<section class="daw-ud-profile-section"><h3>Hasil Tahapan Seleksi</h3><div class="daw-ud-profile-application"><div><small>Nilai Psikotes</small><strong><?= esc_html( $full_profile['psychotest_score'] ) ?></strong></div><div><small>Hasil Wawancara HR</small><strong><?= esc_html( $full_profile['hr_result'] ) ?></strong></div><div><small>Rekomendasi HR</small><strong><?= esc_html( $full_profile['hr_recommendation'] ) ?></strong></div><div><small>Pewawancara HR</small><strong><?= esc_html( $full_profile['hr_interviewer'] ) ?></strong></div><div><small>Akun User Department</small><strong><?= esc_html( $full_profile['department_account'] ) ?></strong></div><div><small>Hasil Wawancara User</small><strong><?= esc_html( $full_profile['user_result'] ) ?></strong></div><div><small>Nilai Wawancara User</small><strong><?= esc_html( $full_profile['user_score'] ) ?></strong></div><div><small>Rekomendasi User</small><strong><?= esc_html( $full_profile['user_recommendation'] ) ?></strong></div></div><?php if ( '' !== $full_profile['hr_notes'] ) : ?><p><strong>Catatan HR:</strong> <?= nl2br( esc_html( $full_profile['hr_notes'] ) ) ?></p><?php endif; ?><?php if ( '' !== $full_profile['user_notes'] ) : ?><p><strong>Catatan User:</strong> <?= nl2br( esc_html( $full_profile['user_notes'] ) ) ?></p><?php endif; ?></section>
							<section class="daw-ud-profile-section"><h3>Pendidikan</h3><?php if ( empty( $full_profile['education'] ) ) : ?><p class="daw-ud-profile-empty">Data pendidikan belum tersedia.</p><?php endif; ?><?php foreach ( $full_profile['education'] as $education ) : ?><article class="daw-ud-profile-education"><div><strong><?= esc_html( $education[0] ?: 'Pendidikan' ) ?></strong><span><?= esc_html( $education[1] ?: 'Institusi belum tersedia' ) ?></span><small><?= esc_html( $education[3] ?: 'Tahun lulus belum tersedia' ) ?></small></div><b><?= esc_html( $education[2] ?: '—' ) ?></b></article><?php endforeach; ?></section>
							<section class="daw-ud-profile-section"><h3>Pengalaman Kerja</h3><?php if ( empty( $full_profile['experience'] ) ) : ?><p class="daw-ud-profile-empty">Data pengalaman kerja belum tersedia.</p><?php endif; ?><?php foreach ( $full_profile['experience'] as $experience ) : ?><article class="daw-ud-profile-experience"><div><strong><?= esc_html( $experience[0] ) ?></strong><small><?= esc_html( $experience[2] ) ?></small></div><span><?= esc_html( $experience[1] ) ?></span><p><?= esc_html( $experience[3] ) ?></p></article><?php endforeach; ?></section>
							<section class="daw-ud-profile-section"><h3>Dokumen Lamaran</h3><?php if ( empty( $full_profile['documents'] ) ) : ?><p class="daw-ud-profile-empty">Metadata dokumen belum tersedia.</p><?php else : ?><div class="daw-ud-profile-documents"><?php foreach ( $full_profile['documents'] as $document ) : ?><div><span class="dashicons dashicons-media-document" aria-hidden="true"></span><strong><?= esc_html( $document['label'] ) ?></strong><small><a href="<?= esc_url( $document['url'] ) ?>" download><?= esc_html( $document['name'] ) ?></a></small></div><?php endforeach; ?></div><?php endif; ?></section>
							<section class="daw-ud-profile-section"><h3>Riwayat Seleksi</h3><?php if ( empty( $full_profile['selection'] ) ) : ?><p class="daw-ud-profile-empty">Riwayat seleksi belum tersedia.</p><?php else : ?><div class="daw-ud-profile-history"><?php foreach ( $full_profile['selection'] as $stage ) : ?><div><i class="<?= esc_attr( $stage[3] ) ?>"></i><strong><?= esc_html( $stage[0] ) ?></strong><span><?= esc_html( $stage[1] ) ?></span><small><?= esc_html( $stage[2] ) ?></small></div><?php endforeach; ?></div><?php endif; ?><p class="daw-ud-profile-assignment">Direkomendasikan HR ke Departemen: <?= esc_html( $full_profile['assigned_department'] ) ?> · <?= esc_html( $full_profile['department_account'] ) ?></p></section>
						</article>
					</section>
				<?php endif; ?>
			<?php elseif ( 'dashboard' === $view ) : ?>
				<section class="daw-ud-welcome"><p>Selamat datang, <strong><?= esc_html( $display_name ) ?></strong></p><span>Departemen <?= esc_html( $department ) ?> — <?= esc_html( date_i18n( 'l, j F Y' ) ) ?></span></section>
				<section class="daw-ud-section"><div class="daw-ud-section__heading"><h2>Permintaan Lowongan</h2><a href="<?= esc_url( $view_url( 'requests' ) ) ?>">Lihat Semua</a></div><div class="daw-ud-stats daw-ud-stats--four"><article><span>Draft</span><strong><?= esc_html( (string) $request_statuses['Draft'] ) ?></strong></article><article><span>Menunggu Review HR</span><strong class="is-orange"><?= esc_html( (string) $request_statuses['Menunggu Review HR'] ) ?></strong></article><article><span>Perlu Revisi</span><strong class="is-red"><?= esc_html( (string) $request_statuses['Perlu Revisi'] ) ?></strong><small>Perlu tindakan</small></article><article><span>Disetujui</span><strong class="is-green"><?= esc_html( (string) $request_statuses['Disetujui'] ) ?></strong></article></div></section>
				<section class="daw-ud-section"><div class="daw-ud-section__heading"><h2>Kandidat User Interview</h2><a href="<?= esc_url( $view_url( 'interviews' ) ) ?>">Lihat Semua</a></div><div class="daw-ud-stats daw-ud-stats--three"><article><span>Menunggu Jadwal</span><strong class="is-orange"><?= esc_html( (string) $interview_stats['waiting'] ) ?></strong></article><article><span>Terjadwal</span><strong><?= esc_html( (string) $interview_stats['scheduled'] ) ?></strong></article><article><span>Sudah Dinilai</span><strong class="is-green"><?= esc_html( (string) $interview_stats['done'] ) ?></strong></article></div></section>
				<section class="daw-ud-section daw-ud-activity-section"><h2>Aktivitas Request Lowongan</h2><div class="daw-ud-activity"><?php if ( empty( $recent_requests ) ) : ?><p class="daw-ud-empty">Belum ada aktivitas request lowongan.</p><?php else : ?><?php foreach ( $recent_requests as $request ) : ?><div><i class="<?= in_array( $request['status'], [ 'Disetujui', 'Lowongan Dibuka' ], true ) ? 'is-green' : ( 'Perlu Revisi' === $request['status'] ? 'is-orange' : 'is-blue' ) ?>"></i><span><?= esc_html( $request['code'] . ' — ' . $request['position'] . ': ' . $request['status'] ) ?><small><?= esc_html( $request['date'] ) ?></small></span></div><?php endforeach; ?><?php endif; ?></div></section>

			<?php elseif ( 'request-form' === $view ) : ?>
				<?php
				$request_status = (string) ( $stored_department_requests[ $request_code ]['status'] ?? 'Draft' );
				$section_data = $request_sections[ $request_step ];
				?>
				<a class="daw-ud-back-link" href="<?= esc_url( $view_url( 'requests' ) ) ?>">&larr; Kembali</a>
				<div class="daw-ud-request-heading"><div><h2>Form Permintaan Tenaga Kerja</h2><span><?= esc_html( $request_status ) ?> — <?= 'Draft' === $request_status ? 'belum diajukan' : 'sedang diproses' ?></span></div><code><?= esc_html( $request_code ) ?></code></div>
				<?php if ( '' !== $request_form_error ) : ?><p class="daw-ud-request-error" role="alert"><?= esc_html( $request_form_error ) ?></p><?php endif; ?>
				<div class="daw-ud-request-wizard">
					<nav class="daw-ud-request-steps" aria-label="Tahapan request">
						<?php foreach ( $request_sections as $step_number => $section ) : ?><div class="<?= $request_step === $step_number ? 'is-active' : ( $request_step > $step_number ? 'is-complete' : '' ) ?>"><small>Seksi <?= esc_html( (string) $step_number ) ?></small><strong><?= esc_html( $section['title'] ) ?></strong></div><?php endforeach; ?>
					</nav>
					<form class="daw-ud-request-form" method="post" action="<?= esc_url( add_query_arg( [ 'page' => 'daw-user-department', 'view' => 'request-form', 'request_code' => $request_code, 'step' => $request_step ], admin_url( 'admin-post.php' ) ) ) ?>">
						<input type="hidden" name="action" value="daw_save_department_request" /><input type="hidden" name="daw_ud_action" value="save_department_request" /><input type="hidden" name="request_code" value="<?= esc_attr( $request_code ) ?>" /><input type="hidden" name="request_step" value="<?= esc_attr( (string) $request_step ) ?>" />
						<?php wp_nonce_field( 'daw_save_department_request', 'daw_ud_request_nonce' ); ?>
						<section class="daw-ud-request-card"><header><h3><?= esc_html( $section_data['title'] ) ?></h3><p><?= esc_html( $section_data['description'] ) ?></p></header>
							<?php if ( 6 === $request_step ) : ?><div class="daw-ud-request-review"><div><span>No. FPTK</span><strong><?= esc_html( $request_code ) ?></strong></div><div><span>Posisi</span><strong><?= esc_html( $request_form_values['position'] ?? '—' ) ?></strong></div><div><span>Departemen</span><strong><?= esc_html( $request_form_values['department'] ?? $department ) ?></strong></div><div><span>Jumlah Kebutuhan</span><strong><?= esc_html( $request_form_values['headcount'] ?? '—' ) ?></strong></div><div><span>Prioritas</span><strong><?= esc_html( $request_form_values['priority'] ?? '—' ) ?></strong></div><div><span>Target Pemenuhan</span><strong><?= esc_html( $request_form_values['target_date'] ?? '—' ) ?></strong></div></div><?php endif; ?>
							<div class="daw-ud-request-fields">
							<?php foreach ( $section_data['fields'] as $field ) : $key = $field['key']; $value = $request_form_values[ $key ] ?? ( $field['default'] ?? '' ); $required = ! empty( $field['required'] ); ?>
								<?php if ( 'checkboxes' === $field['type'] ) : ?><fieldset class="daw-ud-request-checkboxes"><legend><?= esc_html( $field['label'] ) ?></legend><?php foreach ( $field['options'] as $option ) : ?><label><input type="checkbox" name="request_data[<?= esc_attr( $key ) ?>][]" value="<?= esc_attr( $option ) ?>" <?= checked( in_array( $option, (array) $value, true ), true, false ) ?> /><span><?= esc_html( $option ) ?></span></label><?php endforeach; ?></fieldset>
								<?php elseif ( 'checkbox' === $field['type'] ) : ?><label class="daw-ud-request-approval"><input type="checkbox" name="request_data[<?= esc_attr( $key ) ?>]" value="1" <?= checked( '1', (string) $value, false ) ?> /><span><?= esc_html( $field['label'] ) ?><?php if ( $required ) : ?> <b>*</b><?php endif; ?></span></label>
								<?php else : ?><label class="daw-ud-request-field"><span><?= esc_html( $field['label'] ) ?><?php if ( $required ) : ?> <b>*</b><?php endif; ?></span>
									<?php if ( 'select' === $field['type'] ) : ?><select name="request_data[<?= esc_attr( $key ) ?>]" <?= $required ? 'required' : '' ?>><option value="">Pilih...</option><?php foreach ( $field['options'] as $option ) : ?><option value="<?= esc_attr( $option ) ?>" <?= selected( $value, $option, false ) ?>><?= esc_html( $option ) ?></option><?php endforeach; ?></select>
									<?php elseif ( 'textarea' === $field['type'] ) : ?><textarea name="request_data[<?= esc_attr( $key ) ?>]" rows="3" placeholder="<?= esc_attr( $field['placeholder'] ?? '' ) ?>" <?= $required ? 'required' : '' ?>><?= esc_textarea( (string) $value ) ?></textarea>
									<?php else : ?><input type="<?= esc_attr( $field['type'] ) ?>" name="request_data[<?= esc_attr( $key ) ?>]" value="<?= esc_attr( (string) $value ) ?>" placeholder="<?= esc_attr( $field['placeholder'] ?? '' ) ?>" <?= 'number' === $field['type'] ? 'min="1" step="1"' : '' ?> <?= $required ? 'required' : '' ?> /><?php endif; ?>
								</label><?php endif; ?>
							<?php endforeach; ?>
							</div>
							<footer class="daw-ud-request-actions"><?php if ( $request_step > 1 ) : ?><button class="daw-ud-request-previous" type="submit" name="request_action" value="previous">&larr; Sebelumnya</button><?php else : ?><span></span><?php endif; ?><div><button class="button" type="submit" name="request_action" value="save_draft">Simpan Draft</button><?php if ( 6 === $request_step ) : ?><button class="daw-ud-primary-button" type="submit" name="request_action" value="submit">Ajukan Request</button><?php else : ?><button class="daw-ud-primary-button" type="submit" name="request_action" value="next">Selanjutnya <span aria-hidden="true">&rarr;</span></button><?php endif; ?></div></footer>
						</section>
					</form>
				</div>

			<?php elseif ( 'request-detail' === $view ) : ?>
				<?php if ( ! $selected_request ) : ?>
					<a class="daw-ud-back-link" href="<?= esc_url( $view_url( 'requests' ) ) ?>">&larr; Kembali ke daftar</a><p class="daw-ud-empty">Pengajuan tidak ditemukan.</p>
				<?php else : ?>
					<?php $request_fields = (array) ( $selected_request['fields'] ?? [] ); ?>
					<a class="daw-ud-back-link" href="<?= esc_url( $view_url( 'requests' ) ) ?>">&larr; Kembali ke daftar</a>
					<section class="daw-ud-request-detail-heading"><div><p>Pengajuan Tenaga Kerja</p><h2><?= esc_html( $selected_request['position'] ) ?></h2><span><?= esc_html( $selected_request['code'] ) ?> · <?= esc_html( $request_fields['department'] ?? $department ) ?></span></div><span class="daw-ud-status <?= esc_attr( sanitize_title( $selected_request['status'] ) ) ?>"><?= esc_html( $selected_request['status'] ) ?></span></section>
					<section class="daw-ud-request-detail-grid">
						<article class="daw-ud-request-detail-card"><h3>Ringkasan Permintaan</h3><dl><?php foreach ( [ 'Departemen' => $request_fields['department'] ?? $department, 'Hiring User' => $request_fields['hiring_user'] ?? $display_name, 'Jumlah Kebutuhan' => $selected_request['count'], 'Level Jabatan' => $request_fields['job_level'] ?? '—', 'Kategori Karyawan' => $request_fields['employment_type'] ?? '—', 'Status Karyawan' => $request_fields['employee_status'] ?? '—', 'Prioritas' => $selected_request['priority'], 'Target Pemenuhan' => $request_fields['target_date'] ?? '—', 'Tanggal Pengajuan' => $selected_request['date'] ] as $label => $value ) : ?><div><dt><?= esc_html( $label ) ?></dt><dd><?= esc_html( (string) $value ) ?></dd></div><?php endforeach; ?></dl></article>
						<article class="daw-ud-request-detail-card"><h3>Tujuan Posisi</h3><div class="daw-ud-request-detail-copy"><strong>Ringkasan Peran</strong><p><?= esc_html( $request_fields['role_summary'] ?? 'Belum tersedia' ) ?></p><strong>KPI / Target Utama</strong><p><?= esc_html( $request_fields['kpi'] ?? 'Belum tersedia' ) ?></p><strong>Alasan Kebutuhan</strong><p><?= esc_html( $request_fields['reason'] ?? 'Belum tersedia' ) ?></p></div></article>
						<article class="daw-ud-request-detail-card"><h3>Profil Kandidat</h3><dl><?php foreach ( [ 'Pendidikan Minimum' => $request_fields['minimum_education'] ?? '—', 'Jurusan' => $request_fields['major'] ?? '—', 'Pengalaman Minimum' => $request_fields['minimum_experience'] ?? '—', 'Keahlian Wajib' => $request_fields['required_skills'] ?? '—', 'Rentang Gaji' => $request_fields['salary_range'] ?? '—' ] as $label => $value ) : ?><div><dt><?= esc_html( $label ) ?></dt><dd><?= esc_html( (string) $value ) ?></dd></div><?php endforeach; ?></dl></article>
						<article class="daw-ud-request-detail-card"><h3>Proses & Catatan</h3><div class="daw-ud-request-timeline"><div><i></i><span>Draft dibuat<small><?= esc_html( $selected_request['date'] ) ?></small></span></div><div><i></i><span><?= esc_html( $selected_request['status'] ) ?><small><?= esc_html( $selected_request['date'] ) ?></small></span></div></div><p class="daw-ud-request-hr-note"><strong>Catatan HR</strong><br><?= esc_html( ( $selected_request['hr_note'] ?? '' ) ?: 'Belum ada catatan dari HR.' ) ?></p></article>
					</section>
					<div class="daw-ud-request-detail-actions"><a class="button" href="<?= esc_url( $view_url( 'requests' ) ) ?>">Tutup</a><?php if ( isset( $stored_department_requests[ $selected_request['code'] ] ) && in_array( $selected_request['status'], [ 'Draft', 'Perlu Revisi' ], true ) ) : ?><a class="daw-ud-primary-button" href="<?= esc_url( add_query_arg( [ 'view' => 'request-form', 'request_code' => $selected_request['code'], 'step' => '1' ], $page_url ) ) ?>">Edit Pengajuan</a><?php endif; ?></div>
				<?php endif; ?>

			<?php elseif ( 'requests' === $view ) : ?>
				<div class="daw-ud-page-heading"><p>Total <?= esc_html( (string) count( $active_requests ) ) ?> permintaan</p><a class="daw-ud-primary-button" href="<?= esc_url( add_query_arg( [ 'view' => 'request-form', 'request_code' => 'new', 'step' => '1' ], $page_url ) ) ?>"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>Buat Request Baru</a></div>
				<nav class="daw-ud-pills" aria-label="Filter status request"><?php foreach ( [ 'Semua' => '', 'Draft' => 'Draft', 'Perlu Revisi' => 'Perlu Revisi', 'Review HR' => 'Review HR', 'Disetujui' => 'Disetujui', 'Lowongan Dibuka' => 'Lowongan Dibuka' ] as $label => $value ) : ?><a class="<?= $request_filter === $value ? 'is-active' : '' ?>" href="<?= esc_url( add_query_arg( [ 'status' => $value ], $view_url( 'requests' ) ) ) ?>"><?= esc_html( $label ) ?></a><?php endforeach; ?></nav>
				<?php $revision_requests = array_values( array_filter( $active_requests, static fn( array $request ): bool => 'Perlu Revisi' === $request['status'] ) ); ?>
				<?php if ( $revision_requests && ( '' === $request_filter || 'Perlu Revisi' === $request_filter ) ) : ?><div class="daw-ud-alert"><span class="dashicons dashicons-warning" aria-hidden="true"></span><span><strong><?= esc_html( (string) count( $revision_requests ) ) ?> permintaan perlu direvisi</strong><small><?= esc_html( $revision_requests[0]['code'] . ': ' . ( $revision_requests[0]['hr_note'] ?: 'Silakan periksa catatan HR.' ) ) ?></small></span></div><?php endif; ?>
				<form class="daw-ud-search" method="get" action="<?= esc_url( admin_url( 'admin.php' ) ) ?>"><input type="hidden" name="page" value="daw-user-department" /><input type="hidden" name="view" value="requests" /><input type="hidden" name="status" value="<?= esc_attr( $request_filter ) ?>" /><label><span class="screen-reader-text">Cari request</span><input type="search" name="search" value="<?= esc_attr( $search ) ?>" placeholder="Cari nomor FPTK atau posisi..." /></label><button class="button" type="submit">Cari</button></form>
				<div class="daw-ud-table-wrap"><table class="daw-ud-table"><thead><tr><th>No. FPTK</th><th>Posisi</th><th>Jml</th><th>Prioritas</th><th>Tgl Pengajuan</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php if ( empty( $filtered_requests ) ) : ?><tr><td colspan="7" class="daw-ud-empty">Tidak ada request yang cocok.</td></tr><?php endif; ?><?php foreach ( $filtered_requests as $request ) : ?><tr><td><strong><?= esc_html( $request['code'] ) ?></strong></td><td><?= esc_html( $request['position'] ) ?></td><td><?= esc_html( (string) $request['count'] ) ?></td><td class="priority-<?= esc_attr( strtolower( $request['priority'] ) ) ?>"><?= esc_html( $request['priority'] ) ?></td><td><?= esc_html( $request['date'] ) ?></td><td><span class="daw-ud-status <?= esc_attr( sanitize_title( $request['status'] ) ) ?>"><?= esc_html( $request['status'] ) ?></span></td><td class="daw-ud-request-row-actions"><a class="daw-ud-link" href="<?= esc_url( add_query_arg( [ 'view' => 'request-detail', 'request_code' => $request['code'] ], $page_url ) ) ?>">Lihat</a><?php if ( isset( $stored_department_requests[ $request['code'] ] ) && in_array( $request['status'], [ 'Draft', 'Perlu Revisi' ], true ) ) : ?><a href="<?= esc_url( add_query_arg( [ 'view' => 'request-form', 'request_code' => $request['code'], 'step' => '1' ], $page_url ) ) ?>">Edit</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>

			<?php elseif ( 'interviews' === $view ) : ?>
				<section class="daw-ud-intro"><h2>Kandidat Wawancara User</h2><p>Kandidat yang telah melewati HR Interview dan diarahkan HR ke departemen Anda.</p></section>
				<nav class="daw-ud-pills" aria-label="Filter status wawancara"><a class="<?= '' === $interview_filter ? 'is-active' : '' ?>" href="<?= esc_url( add_query_arg( [ 'interview_status' => '', 'search' => $search ], $view_url( 'interviews' ) ) ) ?>">Semua <span><?= esc_html( (string) count( $interviews ) ) ?></span></a><a class="<?= 'Menunggu Jadwal' === $interview_filter ? 'is-active' : '' ?>" href="<?= esc_url( add_query_arg( [ 'interview_status' => 'Menunggu Jadwal', 'search' => $search ], $view_url( 'interviews' ) ) ) ?>">Menunggu Jadwal <span><?= esc_html( (string) $interview_stats['waiting'] ) ?></span></a><a class="<?= 'Terjadwal' === $interview_filter ? 'is-active' : '' ?>" href="<?= esc_url( add_query_arg( [ 'interview_status' => 'Terjadwal', 'search' => $search ], $view_url( 'interviews' ) ) ) ?>">Terjadwal <span><?= esc_html( (string) $interview_stats['scheduled'] ) ?></span></a><a class="<?= 'Sudah Dinilai' === $interview_filter ? 'is-active' : '' ?>" href="<?= esc_url( add_query_arg( [ 'interview_status' => 'Sudah Dinilai', 'search' => $search ], $view_url( 'interviews' ) ) ) ?>">Sudah Dinilai <span><?= esc_html( (string) $interview_stats['done'] ) ?></span></a></nav>
				<form class="daw-ud-search" method="get" action="<?= esc_url( admin_url( 'admin.php' ) ) ?>"><input type="hidden" name="page" value="daw-user-department" /><input type="hidden" name="view" value="interviews" /><input type="hidden" name="interview_status" value="<?= esc_attr( $interview_filter ) ?>" /><label><span class="screen-reader-text">Cari kandidat wawancara</span><input type="search" name="search" value="<?= esc_attr( $search ) ?>" placeholder="Cari nama, kode, atau posisi..." /></label><button class="button" type="submit">Cari</button></form>
				<div class="daw-ud-interview-list"><?php foreach ( $filtered_interviews as $interview ) : ?><article class="daw-ud-interview-card"><span class="daw-ud-candidate-avatar"><?= esc_html( strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $interview['name'] ), 0, 2 ) ) ) ?></span><div class="daw-ud-interview-card__candidate"><strong><?= esc_html( $interview['name'] ) ?></strong><span class="daw-ud-status <?= esc_attr( sanitize_title( $interview['status'] ) ) ?>"><?= esc_html( $interview['status'] ) ?></span><small><?= esc_html( $interview['code'] ) ?> · <?= esc_html( $interview['position'] ) ?> · <?= esc_html( $interview['dealer'] ) ?></small></div><div class="daw-ud-interview-card__meta"><span>Hasil HR<strong><?= esc_html( $interview['hr_result'] ) ?></strong></span><?php if ( '' !== $interview['score'] ) : ?><span>Nilai<strong class="is-green"><?= esc_html( $interview['score'] ) ?></strong></span><?php endif; ?></div><div class="daw-ud-interview-card__date"><small>Jadwal</small><strong><?= esc_html( $interview['date'] ) ?></strong></div><?php if ( 'Terjadwal' === $interview['status'] ) : ?><a class="daw-ud-primary-button" href="<?= esc_url( add_query_arg( [ 'view' => 'interview-detail', 'candidate_code' => $interview['code'] ], $page_url ) ) ?>">Mulai Wawancara</a><?php elseif ( 'Sudah Dinilai' === $interview['status'] ) : ?><a class="button" href="<?= esc_url( add_query_arg( [ 'view' => 'interview-detail', 'candidate_code' => $interview['code'] ], $page_url ) ) ?>">Lihat Hasil</a><?php else : ?><a class="button" href="<?= esc_url( add_query_arg( [ 'view' => 'interview-detail', 'candidate_code' => $interview['code'] ], $page_url ) ) ?>">Lihat Detail</a><?php endif; ?></article><?php endforeach; ?><?php if ( empty( $filtered_interviews ) ) : ?><p class="daw-ud-empty">Tidak ada kandidat yang cocok.</p><?php endif; ?></div>

			<?php elseif ( 'interview-detail' === $view ) : ?>
				<?php if ( ! $selected_interview ) : ?>
					<a class="daw-ud-back-link" href="<?= esc_url( $view_url( 'interviews' ) ) ?>">&larr; Kembali</a><p class="daw-ud-empty">Kandidat wawancara tidak ditemukan.</p>
				<?php else : ?>
					<?php
					$profile = $selected_department_profile['profile'] ?? [];
					$profile_details = is_array( $profile['profile_details'] ?? null ) ? $profile['profile_details'] : [];
					$profile_answers = is_array( $profile_details['application_answers'] ?? null ) ? $profile_details['application_answers'] : [];
					$profile_education = (string) ( $profile_details['education_level'] ?? $profile_answers['pendidikan_formal_1_tingkat'] ?? $profile['education'] ?? '' );
					$profile_major = (string) ( $profile_details['major'] ?? $profile_answers['pendidikan_formal_1_program_studi'] ?? '' );
					$profile_gpa = (string) ( $profile_details['gpa'] ?? $profile_answers['pendidikan_formal_1_ipk'] ?? '' );
					$profile_year = (string) ( $profile_details['graduation_year'] ?? $profile_answers['pendidikan_formal_1_tahun_lulus'] ?? '' );
					$profile_experiences = [];
					foreach ( (array) ( $profile_details['work_experiences'] ?? [] ) as $experience ) {
						if ( is_array( $experience ) ) {
							$profile_experiences[] = [
								(string) ( $experience['position'] ?? '' ),
								(string) ( $experience['company'] ?? '' ),
								(string) ( $experience['period'] ?? '' ),
								(string) ( $experience['description'] ?? '' ),
							];
						}
					}
					$profile_documents = [];
					foreach ( [ 'profile_photo' => 'Foto Profil', 'resume' => 'CV / Resume', 'portfolio' => 'Portofolio' ] as $document_key => $document_label ) {
						$document = $profile_details['documents'][ $document_key ] ?? null;
						if ( is_array( $document ) && '' !== (string) ( $document['name'] ?? '' ) ) {
							$profile_documents[] = $document_label . ' — ' . (string) $document['name'];
						}
					}
					$profile['gender'] = (string) ( $profile['gender'] ?? 'Belum tersedia' );
					$profile['birth'] = trim( (string) ( $profile['birth_place'] ?? '' ) . ' ' . (string) ( $profile['birth_date'] ?? '' ) ) ?: 'Belum tersedia';
					$profile['email'] = (string) ( $profile['email'] ?? 'Belum tersedia' );
					$profile['phone'] = (string) ( $profile['phone'] ?? 'Belum tersedia' );
					$profile['city'] = trim( (string) ( $profile['city'] ?? '' ) . ( '' !== (string) ( $profile['province'] ?? '' ) ? ', ' . $profile['province'] : '' ) ) ?: 'Belum tersedia';
					$profile['education'] = [
						trim( $profile_education . ( '' !== $profile_major ? ' ' . $profile_major : '' ) ) ?: 'Data pendidikan belum tersedia',
						(string) ( $profile_details['institution'] ?? $profile_answers['pendidikan_formal_1_nama_sekolah'] ?? 'Institusi belum tersedia' ),
						trim( ( '' !== $profile_gpa ? 'IPK ' . $profile_gpa : '' ) . ( '' !== $profile_year ? ' · Lulus ' . $profile_year : '' ) ) ?: '—',
					];
					$is_saved = 'Sudah Dinilai' === $selected_interview['status'];
					$decision_locked = '' !== (string) ( $selected_interview['final_decision'] ?? '' );
					$is_editing_result = $is_saved && ! $decision_locked && '1' === sanitize_text_field( wp_unslash( $_GET['edit'] ?? '' ) );
					$show_rating_form = ! $decision_locked && ( in_array( $selected_interview['status'], [ 'Menunggu Jadwal', 'Terjadwal' ], true ) || $is_editing_result );
					$detail_scores = $posted_scores ?: ( $selected_interview['scores'] ?? [] );
					$detail_score_total = ! empty( $detail_scores ) ? (int) round( array_sum( array_map( 'absint', $detail_scores ) ) / ( count( $rating_criteria ) * 5 ) * 100 ) : 0;
					?>
					<a class="daw-ud-back-link" href="<?= esc_url( $view_url( 'interviews' ) ) ?>">&larr; Kembali</a>
					<section class="daw-ud-detail-heading"><div><strong><?= esc_html( $selected_interview['name'] ) ?></strong><span><?= esc_html( $selected_interview['position'] ) ?> · <?= esc_html( $selected_interview['dealer'] ) ?> · <?= esc_html( $selected_interview['code'] ) ?></span></div><nav aria-label="Tahap wawancara"><span class="<?= 'Menunggu Jadwal' === $selected_interview['status'] ? 'is-current' : '' ?>">Menunggu</span><span class="<?= 'Terjadwal' === $selected_interview['status'] ? 'is-current' : '' ?>">Terjadwal</span><span class="<?= $is_saved ? 'is-current' : '' ?>">Dinilai</span><span class="daw-ud-status <?= esc_attr( sanitize_title( $selected_interview['status'] ) ) ?>"><?= esc_html( $selected_interview['status'] ) ?></span></nav></section>
					<?php if ( '' !== $profile_load_error ) : ?><p class="daw-ud-alert" role="alert"><?= esc_html( $profile_load_error ) ?></p><?php endif; ?>
					<?php if ( $interview_saved ) : ?><p class="daw-ud-success" role="status">Hasil wawancara berhasil disimpan.</p><?php endif; ?>
					<?php if ( '' !== $interview_save_error ) : ?><p class="daw-ud-alert" role="alert"><?= esc_html( $interview_save_error ) ?></p><?php endif; ?>
					<div class="daw-ud-detail-layout">
						<section class="daw-ud-detail-main">
							<div class="daw-ud-interview-summary"><div><small>Jadwal</small><strong><?= esc_html( $selected_interview['date'] ) ?></strong></div><div><small>Metode</small><strong><?= esc_html( $selected_interview['method'] ) ?></strong></div><div><small>Lokasi/Platform</small><strong><?= esc_html( $selected_interview['location'] ) ?></strong></div><div><small>Hasil HR</small><strong><?= esc_html( $selected_interview['hr_result'] ) ?></strong></div><?php if ( '' !== $selected_interview['meeting_link'] ) : ?><div><small>Link Wawancara</small><strong><a href="<?= esc_url( $selected_interview['meeting_link'] ) ?>" target="_blank" rel="noopener noreferrer"><?= esc_html( $selected_interview['meeting_link'] ) ?></a></strong></div><?php endif; ?></div>
							<?php if ( 'Menunggu Jadwal' === $selected_interview['status'] && ! $show_rating_form ) : ?>
								<div class="daw-ud-detail-panel"><div class="daw-ud-alert"><span class="dashicons dashicons-clock" aria-hidden="true"></span><span><strong>Menunggu Jadwal Wawancara</strong><small>Jadwal wawancara user belum ditentukan. Hubungi HR untuk konfirmasi jadwal.</small></span></div><div class="daw-ud-summary-grid"><div><small>Hasil HR Interview</small><strong><?= esc_html( $selected_interview['hr_result'] ) ?></strong></div><div><small>Departemen</small><strong><?= esc_html( $department ) ?></strong></div></div><p class="daw-ud-muted-panel">Form penilaian wawancara akan tersedia setelah jadwal wawancara user dikonfirmasi.</p></div>
							<?php elseif ( $show_rating_form ) : ?>
								<form class="daw-ud-detail-panel daw-ud-rating-form" method="post" action="<?= esc_url( add_query_arg( [ 'page' => 'daw-user-department', 'view' => 'interview-detail', 'candidate_code' => $selected_interview['code'], 'edit' => $is_editing_result ? '1' : '' ], admin_url( 'admin.php' ) ) ) ?>" data-interview-rating>
									<div class="daw-ud-panel-heading"><div><h2>Penilaian Wawancara User</h2><p>Nilai 1–5 untuk setiap aspek. 5 = Sangat Baik.</p></div><?php if ( $is_editing_result ) : ?><span class="daw-ud-status terjadwal">Edit Hasil</span><?php endif; ?></div>
									<?php wp_nonce_field( 'daw_save_user_interview', 'daw_user_interview_nonce' ); ?><input type="hidden" name="daw_ud_action" value="save_user_interview" /><input type="hidden" name="candidate_code" value="<?= esc_attr( $selected_interview['code'] ) ?>" />
									<div class="daw-ud-rating-list">
										<?php foreach ( $rating_criteria as $key => $criterion ) : $selected_score = (int) ( $detail_scores[ $key ] ?? 0 ); ?>
											<fieldset class="daw-ud-rating-row"><legend><?= esc_html( $criterion ) ?></legend><div class="daw-ud-score-options" role="radiogroup" aria-label="<?= esc_attr( $criterion ) ?>"><?php for ( $score = 1; $score <= 5; $score++ ) : ?><label class="<?= $score === $selected_score ? 'is-selected' : '' ?>"><input type="radio" name="scores[<?= esc_attr( $key ) ?>]" value="<?= esc_attr( (string) $score ) ?>" <?= checked( $selected_score, $score, false ) ?> required /><span><?= esc_html( (string) $score ) ?></span></label><?php endfor; ?></div></fieldset>
										<?php endforeach; ?>
									</div>
									<div class="daw-ud-score-total"><span>Skor Total</span><strong><span data-score-total><?= esc_html( (string) $detail_score_total ) ?></span><small>/100</small></strong></div>
									<label class="daw-ud-notes-field"><span>Nama Pewawancara <b>*</b></span><input type="text" name="interviewer" value="<?= esc_attr( $posted_interviewer ?: ( $selected_interview['interviewer'] ?: $selected_interview['scheduled_interviewer'] ) ) ?>" maxlength="100" required /></label>
									<fieldset class="daw-ud-recommendation"><legend>Status Hasil Wawancara <b>*</b></legend><div><?php foreach ( [ 'Lolos Wawancara User', 'Tidak Lolos Wawancara User' ] as $selection_status ) : ?><label class="<?= $posted_selection_status === $selection_status || ( '' === $posted_selection_status && ( $selected_interview['selection_status'] ?? '' ) === $selection_status ) ? 'is-selected' : '' ?>"><input type="radio" name="selection_status" value="<?= esc_attr( $selection_status ) ?>" <?= checked( $posted_selection_status ?: ( $selected_interview['selection_status'] ?? '' ), $selection_status, false ) ?> required /><span><?= esc_html( $selection_status ) ?></span></label><?php endforeach; ?></div></fieldset>
									<fieldset class="daw-ud-recommendation"><legend>Rekomendasi <b>*</b></legend><div><?php foreach ( [ 'Sangat Baik', 'Baik', 'Dipertimbangkan', 'Tidak Dilanjutkan' ] as $recommendation ) : ?><label class="<?= $posted_recommendation === $recommendation || ( '' === $posted_recommendation && ( $selected_interview['recommendation'] ?? '' ) === $recommendation ) ? 'is-selected' : '' ?>"><input type="radio" name="recommendation" value="<?= esc_attr( $recommendation ) ?>" <?= checked( $posted_recommendation ?: ( $selected_interview['recommendation'] ?? '' ), $recommendation, false ) ?> required /><span><?= esc_html( $recommendation ) ?></span></label><?php endforeach; ?></div></fieldset>
									<label class="daw-ud-notes-field"><span>Catatan Wawancara</span><textarea name="interview_notes" rows="4" placeholder="Catat observasi dan kesan umum dari proses wawancara..."><?= esc_textarea( $posted_notes ?: ( $selected_interview['notes'] ?? '' ) ) ?></textarea></label>
									<div class="daw-ud-form-actions"><a class="button" href="<?= esc_url( $view_url( 'interviews' ) ) ?>">Batal</a><button class="daw-ud-primary-button" type="submit">Simpan Hasil Wawancara</button></div>
								</form>
							<?php else : ?>
								<section class="daw-ud-detail-panel daw-ud-result-panel"><div class="daw-ud-panel-heading"><div><h2>Hasil Wawancara User</h2><p>Penilaian telah disimpan<?php if ( $decision_locked ) : ?> · Terkunci oleh keputusan HR<?php else : ?> · Hanya baca<?php endif; ?></p></div><?php if ( ! $decision_locked ) : ?><a class="daw-ud-link" href="<?= esc_url( add_query_arg( [ 'view' => 'interview-detail', 'candidate_code' => $selected_interview['code'], 'edit' => '1' ], $page_url ) ) ?>">Edit Hasil</a><?php endif; ?></div><div class="daw-ud-saved-result"><div><small>Skor Total</small><strong><?= esc_html( $selected_interview['score'] ) ?><small>/100</small></strong></div><div class="daw-ud-result-meta"><div><small>Status</small><strong><?= esc_html( $selected_interview['selection_status'] ) ?></strong></div><div><small>Rekomendasi</small><strong><?= esc_html( $selected_interview['recommendation'] ?? '' ) ?></strong></div><div><small>Interviewer</small><strong><?= esc_html( $selected_interview['interviewer'] ?: $display_name ) ?></strong></div><div><small>Tgl Wawancara</small><strong><?= esc_html( $selected_interview['date'] ) ?></strong></div><div><small>Disimpan</small><strong><?= esc_html( $selected_interview['saved_at'] ?? '' ) ?></strong></div></div></div><h3>Penilaian per Aspek</h3><div class="daw-ud-readonly-ratings"><?php foreach ( $rating_criteria as $key => $criterion ) : $score = (int) ( $selected_interview['scores'][ $key ] ?? 0 ); ?><div><span><?= esc_html( $criterion ) ?></span><strong><?= esc_html( (string) $score ) ?>/5</strong></div><?php endforeach; ?></div><section class="daw-ud-saved-notes"><h3>Catatan Wawancara</h3><p><?= '' !== ( $selected_interview['notes'] ?? '' ) ? nl2br( esc_html( $selected_interview['notes'] ) ) : 'Tidak ada catatan.' ?></p></section></section>
							<?php endif; ?>
						</section>
						<aside class="daw-ud-profile-panel"><div class="daw-ud-profile-title"><span class="daw-ud-candidate-avatar"><?= esc_html( strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $selected_interview['name'] ), 0, 2 ) ) ) ?></span><div><strong><?= esc_html( $selected_interview['name'] ) ?></strong><small><?= esc_html( $selected_interview['code'] ) ?><br><?= esc_html( $selected_interview['position'] ) ?> · <?= esc_html( $selected_interview['dealer'] ) ?></small></div></div>
								<h3>Data Diri</h3><dl class="daw-ud-profile-facts"><div><dt>Jenis Kelamin</dt><dd><?= esc_html( $profile['gender'] ) ?></dd></div><div><dt>Tempat/Tgl Lahir</dt><dd><?= esc_html( $profile['birth'] ) ?></dd></div><div><dt>Email</dt><dd><?= esc_html( $profile['email'] ) ?></dd></div><div><dt>Telepon</dt><dd><?= esc_html( $profile['phone'] ) ?></dd></div><div><dt>Kota</dt><dd><?= esc_html( $profile['city'] ) ?></dd></div></dl>
								<h3>Pendidikan</h3><div class="daw-ud-profile-box"><strong><?= esc_html( $profile['education'][0] ) ?></strong><span><?= esc_html( $profile['education'][1] ) ?></span><small><?= esc_html( $profile['education'][2] ) ?></small></div>
								<h3>Pengalaman Kerja</h3><?php if ( empty( $profile_experiences ) ) : ?><p>Data pengalaman belum tersedia.</p><?php endif; ?><?php foreach ( $profile_experiences as $experience ) : ?><div class="daw-ud-profile-box"><strong><?= esc_html( $experience[0] ?: 'Posisi' ) ?></strong><span><?= esc_html( $experience[1] ?: 'Perusahaan belum tersedia' ) ?></span><small><?= esc_html( $experience[2] ?: 'Periode belum tersedia' ) ?></small><p><?= esc_html( $experience[3] ) ?></p></div><?php endforeach; ?>
								<h3>Dokumen</h3><?php if ( empty( $profile_documents ) ) : ?><p>Metadata dokumen belum tersedia.</p><?php else : ?><div class="daw-ud-doc-list"><?php foreach ( $profile_documents as $document ) : ?><div><span class="dashicons dashicons-media-document" aria-hidden="true"></span><?= esc_html( $document ) ?><small>File privat pada profil kandidat</small></div><?php endforeach; ?></div><?php endif; ?>
								<h3>Riwayat Seleksi</h3><?php if ( empty( $selected_department_profile['history'] ) ) : ?><p>Riwayat seleksi belum tersedia.</p><?php else : ?><ul class="daw-ud-selection-history"><?php foreach ( array_reverse( $selected_department_profile['history'] ) as $history ) : ?><li><span><?= esc_html( (string) ( $history['stage'] ?? '' ) ) ?></span><strong><?= esc_html( (string) ( $history['status'] ?? '' ) ) ?></strong></li><?php endforeach; ?></ul><?php endif; ?><div class="daw-ud-profile-callout">Direkomendasikan HR ke Departemen: <?= esc_html( $selected_interview['assigned_department'] ?: $department ) ?></div><a class="daw-ud-profile-action" href="<?= esc_url( add_query_arg( [ 'view' => 'profile', 'candidate_code' => $selected_interview['code'], 'return' => 'interview-detail' ], $page_url ) ) ?>">Lihat Profil Lengkap</a><a class="daw-ud-profile-action" href="<?= esc_url( $department_profile_pdf_url( (int) $selected_interview['code'] ) ) ?>">Download Profil Pelamar (PDF)</a>
							</aside>
					</div>
				<?php endif; ?>

			<?php elseif ( 'applicants' === $view ) : ?>
				<section class="daw-ud-intro"><h2>Data Pelamar</h2><p>Data kandidat yang telah diarahkan HR ke departemen Anda. Hanya kandidat yang secara eksplisit diarahkan oleh HR yang tampil di sini.</p></section>
				<section class="daw-ud-stats daw-ud-stats--four daw-ud-applicant-stats"><article><span>Total Diarahkan</span><strong><?= esc_html( (string) count( $applicants ) ) ?></strong></article><article><span>Diterima</span><strong class="is-green"><?= esc_html( (string) $applicant_stats['accepted'] ) ?></strong></article><article><span>Ditolak</span><strong class="is-red"><?= esc_html( (string) $applicant_stats['rejected'] ) ?></strong></article><article><span>Sedang Proses</span><strong class="is-orange"><?= esc_html( (string) $applicant_stats['in_progress'] ) ?></strong></article></section>
				<form class="daw-ud-search" method="get" action="<?= esc_url( admin_url( 'admin.php' ) ) ?>"><input type="hidden" name="page" value="daw-user-department" /><input type="hidden" name="view" value="applicants" /><label><span class="screen-reader-text">Cari pelamar</span><input type="search" name="search" value="<?= esc_attr( $search ) ?>" placeholder="Cari nama, kode, atau posisi..." /></label><button class="button" type="submit">Cari</button></form>
				<div class="daw-ud-table-wrap"><table class="daw-ud-table"><thead><tr><th>Nama Pelamar</th><th>Posisi</th><th>Dealer</th><th>Tgl Masuk</th><th>Hasil HR</th><th>Hasil User</th><th>Status Final</th><th>Aksi</th></tr></thead><tbody><?php foreach ( $filtered_applicants as $applicant ) : ?><tr><td><strong><?= esc_html( $applicant['name'] ) ?></strong><small><?= esc_html( $applicant['code'] ) ?></small></td><td><?= esc_html( $applicant['position'] ) ?></td><td><?= esc_html( $applicant['dealer'] ) ?></td><td><?= esc_html( $applicant['date'] ) ?></td><td><?= esc_html( $applicant['hr_result'] ) ?></td><td><?= esc_html( $applicant['user_result'] ) ?></td><td><span class="daw-ud-status <?= esc_attr( sanitize_title( $applicant['status'] ) ) ?>"><?= esc_html( $applicant['status'] ) ?></span></td><td><a class="daw-ud-link" href="<?= esc_url( add_query_arg( [ 'view' => 'profile', 'candidate_code' => $applicant['code'], 'return' => 'applicants' ], $page_url ) ) ?>">Profil</a> | <a href="<?= esc_url( $department_profile_pdf_url( (int) $applicant['code'] ) ) ?>">Download PDF</a></td></tr><?php endforeach; ?><?php if ( empty( $filtered_applicants ) ) : ?><tr><td colspan="8" class="daw-ud-empty">Tidak ada pelamar yang cocok.</td></tr><?php endif; ?></tbody></table></div>
				<p class="daw-ud-info"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span>Data pelamar hanya mencakup kandidat yang secara eksplisit diarahkan oleh HR. Catatan internal HR dan detail psikotes rahasia tidak ditampilkan.</p>

			<?php else : ?>
				<section class="daw-ud-intro"><h2>Hasil Wawancara User</h2><p>Rekap penilaian user interview yang telah Anda kirimkan.</p></section>
				<section class="daw-ud-stats daw-ud-stats--four daw-ud-result-stats"><article><span>Total Dinilai</span><strong><?= esc_html( (string) count( $results ) ) ?></strong></article><article><span>Direkomendasikan Terima</span><strong class="is-green"><?= esc_html( (string) $result_stats['recommended'] ) ?></strong></article><article><span>Diterima HR</span><strong class="is-green"><?= esc_html( (string) $result_stats['accepted'] ) ?></strong></article><article><span>Menunggu Keputusan</span><strong class="is-orange"><?= esc_html( (string) $result_stats['pending'] ) ?></strong></article></section>
				<div class="daw-ud-table-wrap"><table class="daw-ud-table"><thead><tr><th>Kandidat</th><th>Posisi</th><th>Tgl Wawancara</th><th>Skor</th><th>Rekomendasi Anda</th><th>Keputusan Final HR</th></tr></thead><tbody><?php foreach ( $results as $result ) : ?><tr><td><strong><?= esc_html( $result['name'] ) ?></strong><small><?= esc_html( $result['code'] ) ?></small></td><td><?= esc_html( $result['position'] ) ?></td><td><?= esc_html( $result['date'] ) ?></td><td class="score-<?= (int) $result['score'] >= 75 ? 'good' : 'low' ?>"><?= esc_html( (string) $result['score'] ) ?></td><td><span class="daw-ud-status <?= esc_attr( sanitize_title( $result['recommendation'] ) ) ?>"><?= esc_html( $result['recommendation'] ) ?></span></td><td class="decision-<?= esc_attr( sanitize_title( $result['decision'] ) ) ?>"><?= esc_html( $result['decision'] ) ?></td></tr><?php endforeach; ?></tbody></table></div>
			<?php endif; ?>
		</main>
	</div>
</div>