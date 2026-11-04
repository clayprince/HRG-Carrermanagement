<?php
/** Register and render the WordPress-native Recruitment admin screens. */
class Recruitment_Admin {

	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 9 );
		add_action( 'admin_post_daw_create_vacancy', [ $this, 'handle_vacancy_creation' ] );
		add_action( 'admin_post_daw_set_vacancy_status', [ $this, 'handle_vacancy_status_change' ] );
		add_action( 'admin_post_daw_save_application_form', [ $this, 'handle_application_form_save' ] );
		add_action( 'admin_post_daw_save_department_request', [ $this, 'handle_department_request_post' ] );
		add_action( 'admin_post_daw_review_department_request', [ $this, 'handle_department_request_review' ] );
		add_action( 'admin_post_daw_create_department_account', [ $this, 'handle_department_account_creation' ] );
		add_action( 'admin_post_daw_set_department_account_status', [ $this, 'handle_department_account_status' ] );
		add_action( 'admin_post_daw_update_department_account', [ $this, 'handle_department_account_update' ] );
		add_action( 'admin_post_daw_delete_department_account', [ $this, 'handle_department_account_delete' ] );
		add_action( 'admin_post_daw_create_hr_account', [ $this, 'handle_hr_account_creation' ] );
		add_action( 'admin_post_daw_change_hr_password', [ $this, 'handle_hr_password_change' ] );
		add_action( 'admin_post_daw_change_department_password', [ $this, 'handle_department_password_change' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'in_admin_header', [ $this, 'render_admin_sidebar' ] );
		add_action( 'admin_footer', [ $this, 'render_screen_explorer' ] );
		add_filter( 'admin_body_class', [ $this, 'add_admin_body_class' ] );
	}

	public function add_admin_body_class( string $classes ): string {
		$page = sanitize_key( $_GET['page'] ?? '' );
		$can_access_screen = 'daw-user-department' === $page
			? current_user_can( 'manage_user_department' )
			: current_user_can( 'manage_recruitment' );
		if ( $this->is_recruitment_screen() && $can_access_screen ) {
			$classes .= ' daw-recruitment-admin';
		}

		return $classes;
	}

	public function render_admin_sidebar(): void {
		if ( 'daw-user-department' === sanitize_key( $_GET['page'] ?? '' ) || ! $this->is_recruitment_screen() || ! current_user_can( 'manage_recruitment' ) ) {
			return;
		}

		require recruitment_get_plugin_path( 'admin/components/sidebar.php' );
	}

	public function register_admin_menu(): void {
		add_menu_page(
			'Recruitment',
			'Recruitment',
			'manage_recruitment',
			'recruitment-dashboard',
			[ $this, 'render_dashboard' ],
			'dashicons-groups',
			26
		);

		add_menu_page(
			'Admin User Department',
			'User Department',
			'manage_user_department',
			'daw-user-department',
			[ $this, 'render_user_department' ],
			'dashicons-groups',
			27
		);

		remove_submenu_page( 'recruitment-dashboard', 'recruitment-dashboard' );
		add_submenu_page( 'recruitment-dashboard', 'Dashboard', 'Dashboard', 'manage_recruitment', 'recruitment-dashboard', [ $this, 'render_dashboard' ] );
		add_submenu_page( 'recruitment-dashboard', 'Lowongan', 'Lowongan', 'manage_recruitment', 'recruitment-vacancies', [ $this, 'render_vacancies' ] );
		add_submenu_page( 'recruitment-dashboard', 'Pelamar', 'Pelamar', 'manage_recruitment', 'recruitment-applicants', [ $this, 'render_applicants' ] );
		add_submenu_page( 'recruitment-dashboard', 'Pengajuan Lowongan', 'Pengajuan Lowongan', 'manage_recruitment', 'recruitment-applications', [ $this, 'render_applications' ] );
		add_submenu_page( 'recruitment-dashboard', 'Wawancara HR', 'Wawancara HR', 'manage_recruitment', 'recruitment-hr-interview', [ $this, 'render_hr_interview' ] );
		add_submenu_page( 'recruitment-dashboard', 'Final Decision', 'Final Decision', 'manage_recruitment', 'recruitment-final-decision', [ $this, 'render_final_decision' ] );
		add_submenu_page( 'recruitment-dashboard', 'Wawancara User', 'Wawancara User', 'manage_recruitment', 'recruitment-user-interview', [ $this, 'render_user_interview' ] );
		add_submenu_page( 'recruitment-dashboard', 'Jadwal Wawancara', 'Jadwal Wawancara', 'manage_recruitment', 'recruitment-interview-schedule', [ $this, 'render_interview_schedule' ] );
		add_submenu_page( 'recruitment-dashboard', 'Pengelolaan Akun User', 'Pengelolaan Akun User', 'manage_recruitment', 'recruitment-user-management', [ $this, 'render_user_management' ] );
		add_submenu_page( 'recruitment-dashboard', 'Profil Admin HR', 'Profil Admin HR', 'manage_recruitment', 'recruitment-hr-profile', [ $this, 'render_hr_profile' ] );
		add_submenu_page( 'recruitment-dashboard', 'Form Lamaran', 'Form Lamaran', 'manage_recruitment', 'recruitment-form-builder', [ $this, 'render_form_builder' ] );
		add_submenu_page( 'recruitment-dashboard', 'Setting Psikotes', 'Setting Psikotes', 'manage_recruitment', 'recruitment-psychotest-settings', [ $this, 'render_psychotest_settings' ] );
		add_submenu_page( 'recruitment-dashboard', 'Hasil Kepribadian', 'Hasil Kepribadian', 'manage_recruitment', 'recruitment-personality-results', [ $this, 'render_personality_results' ] );
		add_submenu_page( 'recruitment-dashboard', 'Bank Soal', 'Bank Soal', 'manage_recruitment', 'recruitment-question-bank', [ $this, 'render_question_bank' ] );
		add_submenu_page( 'recruitment-dashboard', 'Pengaturan', 'Pengaturan', 'manage_recruitment', 'recruitment-settings', [ $this, 'render_settings' ] );
	}

	public function enqueue_admin_assets(): void {
		if ( ! $this->is_recruitment_screen() ) {
			return;
		}

		$css_path = recruitment_get_plugin_path( 'assets/css/admin.css' );
		$js_path  = recruitment_get_plugin_path( 'assets/js/admin.js' );
		$public_css_path = recruitment_get_plugin_path( 'assets/css/public.css' );
		$public_js_path  = recruitment_get_plugin_path( 'assets/js/public.js' );
		wp_enqueue_style( 'recruitment-public', recruitment_get_plugin_url( 'assets/css/public.css' ), [], file_exists( $public_css_path ) ? filemtime( $public_css_path ) : RECRUITMENT_PLUGIN_VERSION );
		wp_enqueue_script( 'recruitment-public', recruitment_get_plugin_url( 'assets/js/public.js' ), [], file_exists( $public_js_path ) ? filemtime( $public_js_path ) : RECRUITMENT_PLUGIN_VERSION, true );
		wp_enqueue_style( 'recruitment-admin', recruitment_get_plugin_url( 'assets/css/admin.css' ), [], file_exists( $css_path ) ? filemtime( $css_path ) : RECRUITMENT_PLUGIN_VERSION );
		wp_enqueue_script( 'recruitment-admin', recruitment_get_plugin_url( 'assets/js/admin.js' ), [], file_exists( $js_path ) ? filemtime( $js_path ) : RECRUITMENT_PLUGIN_VERSION, true );
	}

	public function render_screen_explorer(): void {
		$page = sanitize_key( $_GET['page'] ?? '' );
		$is_department_screen = 'daw-user-department' === $page;
		$can_access_explorer = $is_department_screen
			? current_user_can( 'manage_user_department' )
			: current_user_can( 'manage_recruitment' );
		if ( ! $can_access_explorer ) {
			return;
		}

		if ( ! $this->is_recruitment_screen() ) {
			return;
		}

		require recruitment_get_plugin_path( 'public/components/screen-explorer.php' );
	}

	public function render_dashboard(): void {
		$this->render_page( 'dashboard' );
	}

	public function render_vacancies(): void {
		$this->render_page( 'vacancies' );
	}

	public function render_applicants(): void {
		$this->render_page( 'applicants' );
	}

	public function render_applications(): void {
		$this->render_page( 'applications' );
	}

	public function render_final_decision(): void {
		$this->render_page( 'final-decision' );
	}

	public function render_hr_interview(): void {
		$this->render_page( 'hr-interview' );
	}

	public function render_user_department(): void {
		$this->render_page( 'user-department' );
	}

	public function handle_vacancy_creation(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk membuat lowongan.', 'recruitment-plugin' ) );
		}

		check_admin_referer( 'daw_create_vacancy' );
		$length = static function ( string $value ): int {
			return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
		};
		$submitted = isset( $_POST['vacancy'] ) && is_array( $_POST['vacancy'] ) ? wp_unslash( $_POST['vacancy'] ) : [];
		$fields = [];
		foreach ( $submitted as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( 'sourcing_channels' === $key ) {
				$fields[ $key ] = array_values(
					array_filter(
						array_map( 'sanitize_text_field', is_array( $value ) ? $value : [] )
					)
				);
			} elseif ( ! is_array( $value ) ) {
				$fields[ $key ] = in_array( $key, [ 'reason', 'role_summary', 'kpi', 'strategic_contribution', 'main_challenges', 'required_skills', 'preferred_skills', 'salary_range', 'priority_competencies', 'validation_notes', 'sourcing_timeline', 'special_notes' ], true )
					? sanitize_textarea_field( $value )
					: sanitize_text_field( $value );
			}
		}
		$title = (string) ( $fields['position'] ?? '' );
		$description = (string) ( $fields['role_summary'] ?? '' );
		$responsibilities = implode(
			"\n",
			array_filter(
				[
					(string) ( $fields['kpi'] ?? '' ),
					(string) ( $fields['strategic_contribution'] ?? '' ),
					(string) ( $fields['main_challenges'] ?? '' ),
				],
				static function ( string $value ): bool {
					return '' !== trim( $value );
				}
			)
		);
		$education = (string) ( $fields['minimum_education'] ?? '' );
		$experience = (string) ( $fields['minimum_experience'] ?? '' );
		$certification = (string) ( $fields['certifications'] ?? '' );
		$type_map = [ 'Pegawai Tetap' => 'Full-time', 'Kontrak' => 'Kontrak', 'Magang' => 'Magang' ];
		$type = $type_map[ (string) ( $fields['employment_type'] ?? '' ) ] ?? '';
		$location = (string) ( $fields['location'] ?? '' );
		$dealer = (string) ( $fields['dealer'] ?? '' );
		$deadline = (string) ( $fields['deadline'] ?? '' );
		$target_date = (string) ( $fields['target_date'] ?? '' );
		$status = sanitize_key( wp_unslash( $_POST['submit_status'] ?? '' ) );
		$deadline_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $deadline );
		$valid_deadline = $deadline_date instanceof DateTimeImmutable && $deadline_date->format( 'Y-m-d' ) === $deadline;
		$target_date_value = '' === $target_date ? null : DateTimeImmutable::createFromFormat( '!Y-m-d', $target_date );
		$valid_target_date = null === $target_date_value || ( $target_date_value instanceof DateTimeImmutable && $target_date_value->format( 'Y-m-d' ) === $target_date );
		$allowed_types = [ 'Full-time', 'Part-time', 'Kontrak', 'Magang' ];
		$allowed_priorities = [ 'Tinggi', 'Sedang', 'Rendah', 'Urgent' ];
		$allowed_education = [ 'SMA/SMK', 'D3', 'S1', 'S2' ];
		$allowed_recruitment_budgets = [ 'Tersedia', 'Menunggu Persetujuan', 'Belum Tersedia' ];
		$allowed_headcount_confirmations = [ 'Sesuai Rencana', 'Perlu Persetujuan' ];
		$allowed_selection_stages = [
			'Standar (Administrasi → Psikotes → Wawancara HR → Wawancara User)',
			'Administrasi → Wawancara HR → Wawancara User',
			'Administrasi → Tes Teknis → Wawancara User',
		];
		$headcount = absint( $fields['headcount'] ?? 0 );
		$is_valid = '' !== $title
			&& $length( $title ) <= 100
			&& '' !== trim( (string) ( $fields['hiring_user'] ?? '' ) )
			&& $length( (string) ( $fields['hiring_user'] ?? '' ) ) <= 255
			&& '' !== $dealer
			&& $length( $dealer ) <= 100
			&& in_array( $type, $allowed_types, true )
			&& in_array( (string) ( $fields['priority'] ?? '' ), $allowed_priorities, true )
			&& in_array( $education, $allowed_education, true )
			&& in_array( (string) ( $fields['recruitment_budget'] ?? '' ), $allowed_recruitment_budgets, true )
			&& in_array( (string) ( $fields['headcount_confirmation'] ?? '' ), $allowed_headcount_confirmations, true )
			&& in_array( (string) ( $fields['selection_stages'] ?? '' ), $allowed_selection_stages, true )
			&& $headcount >= 1
			&& $headcount <= 1000
			&& '' !== $location
			&& $length( $location ) <= 100
			&& '' !== (string) ( $fields['department'] ?? '' )
			&& $length( (string) ( $fields['department'] ?? '' ) ) <= 100
			&& '' !== trim( $description )
			&& $length( $description ) <= 5000
			&& $length( $responsibilities ) <= 10000
			&& '' !== trim( (string) ( $fields['required_skills'] ?? '' ) )
			&& $length( (string) ( $fields['required_skills'] ?? '' ) ) <= 5000
			&& $length( $experience ) <= 500
			&& $length( $certification ) <= 500
			&& '' !== trim( (string) ( $fields['salary_range'] ?? '' ) )
			&& $length( (string) ( $fields['salary_range'] ?? '' ) ) <= 100
			&& '' !== trim( (string) ( $fields['sourcing_timeline'] ?? '' ) )
			&& $length( (string) ( $fields['sourcing_timeline'] ?? '' ) ) <= 100
			&& $valid_deadline
			&& $valid_target_date
			&& 'draft' === $status
			&& ! empty( $fields['approval'] )
			&& '' !== trim( (string) ( $fields['signature'] ?? '' ) )
			&& $length( (string) ( $fields['signature'] ?? '' ) ) <= 255;
		$notice = 'invalid';
		$result = [];

		if ( $is_valid ) {
			$result = ( new Recruitment_Database() )->create_vacancy(
				[
					'title' => $title,
					'description' => $description,
					'responsibilities' => $responsibilities,
					'education_requirement' => $education,
					'experience_requirement' => $experience,
					'certification_requirement' => $certification,
					'type' => $type,
					'location' => $location,
					'dealer' => $dealer,
					'deadline' => $deadline,
					'status' => $status,
					'target_date' => $target_date,
					'request_details' => $fields,
				]
			);
			$notice = ! empty( $result['success'] ) ? 'created' : 'unavailable';
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'view' => ! empty( $result['success'] ) ? 'list' : 'create',
					'notice' => $notice,
				],
				recruitment_get_admin_url( 'vacancies' )
			)
		);
		exit;
	}

	public function handle_vacancy_status_change(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengubah status lowongan.', 'recruitment-plugin' ) );
		}

		$vacancy_id = absint( $_POST['vacancy_id'] ?? 0 );
		check_admin_referer( 'daw_set_vacancy_status_' . $vacancy_id );
		$status = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );
		$notice = 'invalid';

		if ( $vacancy_id > 0 && in_array( $status, [ 'draft', 'published' ], true ) ) {
			$result = ( new Recruitment_Database() )->set_vacancy_status( $vacancy_id, $status );
			$notice = ! empty( $result['success'] )
				? 'status_updated'
				: ( 'vacancy_not_found' === ( $result['error'] ?? '' ) ? 'not_found' : 'unavailable' );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'notice' => $notice,
				],
				recruitment_get_admin_url( 'vacancies' )
			)
		);
		exit;
	}

	public function handle_department_request_post(): void {
		if ( ! current_user_can( 'manage_user_department' ) || 'daw-user-department' !== sanitize_key( $_GET['page'] ?? '' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengirim request ini.', 'recruitment-plugin' ) );
		}

		require recruitment_get_plugin_path( 'admin/pages/user-department.php' );
	}

	public function handle_department_request_review(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk meninjau pengajuan lowongan.', 'recruitment-plugin' ) );
		}

		check_admin_referer( 'daw_review_department_request' );
		$request_code = sanitize_text_field( wp_unslash( $_POST['request_code'] ?? '' ) );
		$owner_id = absint( $_POST['owner_id'] ?? 0 );
		if ( ! $owner_id ) {
			wp_die( esc_html__( 'Pengajuan tidak ditemukan.', 'recruitment-plugin' ) );
		}
		$decision = sanitize_key( wp_unslash( $_POST['decision'] ?? '' ) );
		$note = sanitize_textarea_field( wp_unslash( $_POST['hr_note'] ?? '' ) );
		$decisions = [
			'revise' => 'Perlu Revisi',
			'reject' => 'Ditolak',
		];

		if ( '' === $request_code || ( 'approve' !== $decision && ! isset( $decisions[ $decision ] ) ) ) {
			wp_die( esc_html__( 'Data keputusan pengajuan tidak valid.', 'recruitment-plugin' ) );
		}

		if ( 'approve' !== $decision && '' === $note ) {
			wp_safe_redirect(
				add_query_arg(
					[
						'page' => 'recruitment-applications',
						'request_code' => $request_code,
						'review_error' => 'note_required',
					],
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$requests = get_user_meta( $owner_id, 'daw_department_job_requests', true );
		if ( ! is_array( $requests ) || ! isset( $requests[ $request_code ] ) ) {
			wp_die( esc_html__( 'Pengajuan tidak ditemukan.', 'recruitment-plugin' ) );
		}

		$request = $requests[ $request_code ];
		$request_status = (string) ( $request['status'] ?? '' );
		$is_reviewable = in_array( $request_status, [ 'Review HR', 'Menunggu Review HR' ], true );
		$is_approved_but_unpublished = 'Disetujui' === $request_status && empty( $request['vacancy_id'] );
		if (
			( 'approve' === $decision && ! $is_reviewable && ! $is_approved_but_unpublished )
			|| ( 'approve' !== $decision && ! $is_reviewable )
		) {
			wp_die( esc_html__( 'Pengajuan ini sudah tidak menunggu review.', 'recruitment-plugin' ) );
		}

		if ( 'approve' === $decision ) {
			$fields = is_array( $request['fields'] ?? null ) ? $request['fields'] : [];
			$title = trim( (string) ( $fields['position'] ?? '' ) );
			$location = sanitize_text_field( wp_unslash( $_POST['location'] ?? ( $request['publication_location'] ?? ( $fields['location'] ?? '' ) ) ) );
			$dealer = sanitize_text_field( wp_unslash( $_POST['dealer'] ?? '' ) );
			$deadline = sanitize_text_field( wp_unslash( $_POST['deadline'] ?? '' ) );
			$deadline_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $deadline );
			$employment_types = [
				'Pegawai Tetap' => 'Full-time',
				'Kontrak' => 'Kontrak',
				'Magang' => 'Magang',
			];
			$type = $employment_types[ (string) ( $fields['employment_type'] ?? '' ) ] ?? '';
			$length = static function ( string $value ): int {
				return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
			};
			if (
				'' === $title
				|| $length( $title ) > 100
				|| '' === $location
				|| $length( $location ) > 100
				|| '' === $dealer
				|| $length( $dealer ) > 100
				|| '' === $type
				|| ! $deadline_date instanceof DateTimeImmutable
				|| $deadline_date->format( 'Y-m-d' ) !== $deadline
			) {
				wp_safe_redirect(
					add_query_arg(
						[
							'page' => 'recruitment-applications',
							'request_code' => $request_code,
							'owner_id' => $owner_id,
							'review_error' => 'publication_details',
						],
						admin_url( 'admin.php' )
					)
				);
				exit;
			}

			$responsibilities = implode(
				"\n",
				array_filter(
					[
						(string) ( $fields['kpi'] ?? '' ),
						(string) ( $fields['strategic_contribution'] ?? '' ),
						(string) ( $fields['main_challenges'] ?? '' ),
					],
					static function ( string $value ): bool {
						return '' !== trim( $value );
					}
				)
			);
			$vacancy_result = ( new Recruitment_Database() )->create_vacancy(
				[
					'title' => $title,
					'description' => (string) ( $fields['role_summary'] ?? '' ),
					'responsibilities' => $responsibilities,
					'education_requirement' => (string) ( $fields['minimum_education'] ?? '' ),
					'experience_requirement' => (string) ( $fields['minimum_experience'] ?? '' ),
					'certification_requirement' => (string) ( $fields['certifications'] ?? '' ),
					'type' => $type,
					'location' => $location,
					'dealer' => $dealer,
					'deadline' => $deadline,
					'target_date' => (string) ( $fields['target_date'] ?? '' ),
					'status' => 'published',
					'request_code' => $request_code,
					'request_details' => array_merge( $fields, [
						'approved_by' => wp_get_current_user()->display_name,
						'approved_at' => current_time( 'mysql' ),
						'location' => $location,
						'dealer' => $dealer,
						'deadline' => $deadline,
					] ),
				]
			);
			if ( empty( $vacancy_result['success'] ) || empty( $vacancy_result['vacancy_id'] ) ) {
				wp_safe_redirect(
					add_query_arg(
						[
							'page' => 'recruitment-applications',
							'request_code' => $request_code,
							'owner_id' => $owner_id,
							'review_error' => 'publication_failed',
						],
						admin_url( 'admin.php' )
					)
				);
				exit;
			}

			$request['status'] = 'Lowongan Dibuka';
			$request['hr_note'] = $note;
			$request['publication_location'] = $location;
			$request['dealer'] = $dealer;
			$request['deadline'] = $deadline;
			$request['fields'] = $fields;
			$request['fields']['location'] = $location;
			$request['vacancy_id'] = absint( $vacancy_result['vacancy_id'] );
			$request['vacancy_status'] = 'published';
			$request['updated_at'] = current_time( 'mysql' );
			$requests[ $request_code ] = $request;
			update_user_meta( $owner_id, 'daw_department_job_requests', $requests );
			$saved_requests = get_user_meta( $owner_id, 'daw_department_job_requests', true );
			if (
				! is_array( $saved_requests )
				|| 'Lowongan Dibuka' !== ( $saved_requests[ $request_code ]['status'] ?? '' )
				|| absint( $saved_requests[ $request_code ]['vacancy_id'] ?? 0 ) !== absint( $vacancy_result['vacancy_id'] )
			) {
				error_log( 'Published vacancy could not be linked to its approved department request.' );
				wp_safe_redirect(
					add_query_arg(
						[
							'page' => 'recruitment-applications',
							'request_code' => $request_code,
							'owner_id' => $owner_id,
							'review_error' => 'link_failed',
						],
						admin_url( 'admin.php' )
					)
				);
				exit;
			}
		} else {
			$request['status'] = $decisions[ $decision ];
			$request['hr_note'] = $note;
			$request['updated_at'] = current_time( 'mysql' );
			$requests[ $request_code ] = $request;
			update_user_meta( $owner_id, 'daw_department_job_requests', $requests );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page' => 'recruitment-applications',
					'request_code' => $request_code,
					'reviewed' => '1',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function render_user_interview(): void {
		$this->render_page( 'user-interview' );
	}

	public function render_interview_schedule(): void {
		$this->render_page( 'interview-schedule' );
	}

	public function render_user_management(): void {
		$this->render_page( 'user-management' );
	}

	public function render_hr_profile(): void {
		$this->render_page( 'hr-profile' );
	}

	public function handle_department_account_creation(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk membuat akun department.', 'recruitment-plugin' ) );
		}

		check_admin_referer( 'daw_create_department_account' );
		$username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ), true );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$department_id = absint( $_POST['department_id'] ?? 0 );
		$password = (string) wp_unslash( $_POST['password'] ?? '' );
		$password_confirmation = (string) wp_unslash( $_POST['password_confirmation'] ?? '' );
		$notice = 'invalid';

		if ( '' === $password && '' === $password_confirmation ) {
			$password = wp_generate_password( 16, true, true );
			$password_confirmation = $password;
		}

		if ( strlen( $password ) >= 8 && $password === $password_confirmation ) {
			$result = ( new Recruitment_Database() )->create_department_account( $username, $email, $department_id, $password );
			if ( ! empty( $result['success'] ) ) {
				$notice = 'created';
				wp_mail(
					$email,
					'Undangan akun Recruitment DAW',
					"Akun User Department Anda telah dibuat.\n\n"
						. "Username: " . $username . "\n"
						. "Password sementara: " . $password . "\n\n"
						. "Segera masuk dan ubah password Anda."
				);
			} else {
				$error = (string) ( $result['error'] ?? '' );
				$notice = [
					'duplicate_account' => 'duplicate',
					'department_not_found' => 'department',
					'service_unavailable' => 'unavailable',
				][ $error ] ?? 'invalid';
			}
		}

		wp_safe_redirect( add_query_arg( 'notice', $notice, admin_url( 'admin.php?page=recruitment-user-management' ) ) );
		exit;
	}

	public function handle_department_account_status(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengubah status akun department.', 'recruitment-plugin' ) );
		}

		$account_id = absint( $_POST['account_id'] ?? 0 );
		check_admin_referer( 'daw_set_department_account_status_' . $account_id );
		$status = strtoupper( sanitize_key( wp_unslash( $_POST['status'] ?? '' ) ) );
		$notice = 'invalid';

		if ( $account_id > 0 && in_array( $status, [ 'ACTIVE', 'INACTIVE' ], true ) ) {
			$result = ( new Recruitment_Database() )->set_department_account_status( $account_id, $status );
			if ( ! empty( $result['success'] ) ) {
				if ( 'INACTIVE' === $status ) {
					$this->revoke_department_account_sessions( $account_id );
				}
				$notice = 'status_updated';
			} else {
				$error = (string) ( $result['error'] ?? '' );
				$notice = [
					'account_not_found' => 'not_found',
					'service_unavailable' => 'unavailable',
				][ $error ] ?? 'invalid';
			}
		}

		wp_safe_redirect( add_query_arg( 'notice', $notice, admin_url( 'admin.php?page=recruitment-user-management' ) ) );
		exit;
	}

	public function handle_department_account_update(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengubah akun department.', 'recruitment-plugin' ) );
		}
		$account_id = absint( $_POST['account_id'] ?? 0 );
		check_admin_referer( 'daw_update_department_account' );
		$password = (string) wp_unslash( $_POST['password'] ?? '' );
		$result = ( new Recruitment_Database() )->update_department_account(
			$account_id,
			sanitize_user( wp_unslash( $_POST['username'] ?? '' ), true ),
			sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) ),
			sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
			absint( $_POST['department_id'] ?? 0 ),
			'' === $password ? null : $password
		);
		if ( ! empty( $result['success'] ) ) {
			( new Recruitment_Database() )->save_account_permissions( $account_id, array_map( 'sanitize_key', (array) ( $_POST['permissions'] ?? [] ) ) );
		}
		$notice = ! empty( $result['success'] ) ? 'updated' : ( 'department_not_found' === ( $result['error'] ?? '' ) ? 'department' : 'invalid' );
		wp_safe_redirect( add_query_arg( 'notice', $notice, admin_url( 'admin.php?page=recruitment-user-management' ) ) );
		exit;
	}

	public function handle_department_account_delete(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk menghapus akun department.', 'recruitment-plugin' ) );
		}
		$account_id = absint( $_POST['account_id'] ?? 0 );
		check_admin_referer( 'daw_delete_department_account_' . $account_id );
		$result = ( new Recruitment_Database() )->delete_department_account( $account_id );
		wp_safe_redirect( add_query_arg( 'notice', ! empty( $result['success'] ) ? 'deleted' : 'not_found', admin_url( 'admin.php?page=recruitment-user-management' ) ) );
		exit;
	}

	public function handle_hr_account_creation(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Hanya administrator WordPress yang dapat membuat akun HR awal.', 'recruitment-plugin' ) );
		}

		check_admin_referer( 'daw_create_hr_account' );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$password = (string) wp_unslash( $_POST['password'] ?? '' );
		$password_confirmation = (string) wp_unslash( $_POST['password_confirmation'] ?? '' );
		$notice = 'invalid';

		if ( is_email( $email ) && strlen( $password ) >= 8 && $password === $password_confirmation ) {
			$result = ( new Recruitment_Database() )->create_hr_account( $email, $password );
			if ( ! empty( $result['success'] ) ) {
				$notice = 'created';
			} else {
				$error = (string) ( $result['error'] ?? '' );
				$notice = [
					'email_exists' => 'duplicate',
					'service_unavailable' => 'unavailable',
				][ $error ] ?? 'invalid';
			}
		}

		wp_safe_redirect( add_query_arg( 'hr_account_notice', $notice, admin_url( 'admin.php?page=recruitment-user-management' ) ) );
		exit;
	}

	public function handle_hr_password_change(): void {
		if ( ! current_user_can( 'manage_recruitment' ) || ! $this->is_hr_portal_user() ) {
			wp_die( esc_html__( 'Hanya Admin HR yang dapat mengubah password ini.', 'recruitment-plugin' ) );
		}

		check_admin_referer( 'daw_change_hr_password' );
		$current_password = (string) wp_unslash( $_POST['current_password'] ?? '' );
		$new_password = (string) wp_unslash( $_POST['new_password'] ?? '' );
		$password_confirmation = (string) wp_unslash( $_POST['password_confirmation'] ?? '' );
		$notice = 'invalid';

		if ( '' !== $current_password && strlen( $new_password ) >= 8 && $new_password === $password_confirmation ) {
			$result = ( new Recruitment_Database() )->change_hr_password(
				(string) get_user_meta( get_current_user_id(), 'daw_oracle_account_id', true ),
				$current_password,
				$new_password
			);
			if ( ! empty( $result['success'] ) ) {
				WP_Session_Tokens::get_instance( get_current_user_id() )->destroy_others( wp_get_session_token() );
				$notice = 'password_updated';
			} else {
				$error = (string) ( $result['error'] ?? '' );
				$notice = [
					'invalid_current_password' => 'current_password',
					'service_unavailable' => 'unavailable',
				][ $error ] ?? 'invalid';
			}
		}

		wp_safe_redirect( add_query_arg( 'password_notice', $notice, admin_url( 'admin.php?page=recruitment-hr-profile' ) ) );
		exit;
	}

	public function handle_department_password_change(): void {
		if ( ! current_user_can( 'manage_user_department' ) || ! $this->is_department_portal_user() ) {
			wp_die( esc_html__( 'Hanya Admin Department yang dapat mengubah password ini.', 'recruitment-plugin' ) );
		}

		check_admin_referer( 'daw_change_department_password' );
		$current_password = (string) wp_unslash( $_POST['current_password'] ?? '' );
		$new_password = (string) wp_unslash( $_POST['new_password'] ?? '' );
		$password_confirmation = (string) wp_unslash( $_POST['password_confirmation'] ?? '' );
		$notice = 'invalid';

		if ( '' !== $current_password && strlen( $new_password ) >= 8 && $new_password === $password_confirmation ) {
			$result = ( new Recruitment_Database() )->change_department_password(
				(string) get_user_meta( get_current_user_id(), 'daw_oracle_account_id', true ),
				$current_password,
				$new_password
			);
			if ( ! empty( $result['success'] ) ) {
				$this->revoke_department_account_sessions( absint( get_user_meta( get_current_user_id(), 'daw_oracle_account_id', true ) ) );
				wp_logout();
				wp_safe_redirect( recruitment_get_department_login_url( [ 'password_notice' => 'password_updated' ] ) );
				exit;
			}

			$error = (string) ( $result['error'] ?? '' );
			$notice = [
				'invalid_current_password' => 'current_password',
				'service_unavailable' => 'unavailable',
			][ $error ] ?? 'invalid';
		}

		wp_safe_redirect( add_query_arg( [ 'view' => 'account-settings', 'password_notice' => $notice ], admin_url( 'admin.php?page=daw-user-department' ) ) );
		exit;
	}

	public function render_form_builder(): void {
		$this->render_page( 'application-form-builder' );
	}

	public function handle_application_form_save(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengubah form lamaran.', 'recruitment-plugin' ) );
		}

		check_admin_referer( 'daw_save_application_form' );
		$submitted = isset( $_POST['form_sections'] ) && is_array( $_POST['form_sections'] )
			? wp_unslash( $_POST['form_sections'] )
			: null;
		$normalized = ( new Recruitment_Application() )->normalize_form_configuration( $submitted );
		if ( $normalized['errors'] ) {
			wp_die(
				esc_html( implode( ' ', $normalized['errors'] ) ),
				esc_html__( 'Form lamaran tidak valid', 'recruitment-plugin' ),
				[ 'response' => 400 ]
			);
		}

		$result = ( new Recruitment_Database() )->save_application_form( $normalized['sections'] );
		$notice = ! empty( $result['success'] ) ? 'saved' : 'save_failed';
		wp_safe_redirect( add_query_arg( 'form_notice', $notice, admin_url( 'admin.php?page=recruitment-form-builder' ) ) );
		exit;
	}

	public function render_psychotest_settings(): void {
		$this->render_page( 'psychotest-settings' );
	}

	public function render_personality_results(): void {
		$this->render_page( 'personality-results' );
	}

	public function render_question_bank(): void {
		$this->render_page( 'question-bank' );
	}

	public function render_settings(): void {
		$this->render_page( 'settings' );
	}

	private function render_page( string $page ): void {
		$capability = 'user-department' === $page ? 'manage_user_department' : 'manage_recruitment';
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengakses halaman ini.', 'recruitment-plugin' ) );
		}

		( new Recruitment_Router() )->render( $page );
	}

	private function is_recruitment_screen(): bool {
		$page = sanitize_key( $_GET['page'] ?? '' );
		$admin_pages = [
			'recruitment-dashboard',
			'recruitment-vacancies',
			'recruitment-applicants',
			'recruitment-applications',
			'recruitment-hr-interview',
			'recruitment-final-decision',
			'recruitment-user-interview',
			'recruitment-interview-schedule',
			'recruitment-user-management',
			'recruitment-hr-profile',
			'recruitment-form-builder',
			'recruitment-psychotest-settings',
			'recruitment-personality-results',
			'recruitment-question-bank',
			'recruitment-settings',
			'daw-user-department',
		];

		if ( in_array( $page, $admin_pages, true ) ) {
			return true;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = sanitize_key( $_GET['post_type'] ?? ( $screen->post_type ?? '' ) );
		if ( 'daw_vacancy' === $post_type ) {
			return true;
		}

		return $screen
			&& in_array( $screen->id, [ 'users', 'users-network' ], true )
			&& current_user_can( 'manage_recruitment' )
			&& current_user_can( 'list_users' );
	}

	private function is_hr_portal_user(): bool {
		$user = wp_get_current_user();
		$account_id = (string) get_user_meta( get_current_user_id(), 'daw_oracle_account_id', true );

		return $user instanceof WP_User
			&& in_array( 'daw_hr', (array) $user->roles, true )
			&& 'hr' === get_user_meta( $user->ID, 'daw_oracle_account_type', true )
			&& ctype_digit( $account_id )
			&& '0' !== $account_id;
	}

	private function is_department_portal_user(): bool {
		$user = wp_get_current_user();
		$account_id = (string) get_user_meta( get_current_user_id(), 'daw_oracle_account_id', true );

		return $user instanceof WP_User
			&& in_array( 'daw_user_department', (array) $user->roles, true )
			&& 'user_department' === get_user_meta( $user->ID, 'daw_oracle_account_type', true )
			&& ctype_digit( $account_id )
			&& '0' !== $account_id;
	}

	private function revoke_department_account_sessions( int $account_id ): void {
		$bridge_user_ids = get_users(
			[
				'fields' => 'ID',
				'meta_query' => [
					[
						'key' => 'daw_oracle_account_id',
						'value' => (string) $account_id,
					],
					[
						'key' => 'daw_oracle_account_type',
						'value' => 'user_department',
					],
				],
			]
		);

		foreach ( $bridge_user_ids as $bridge_user_id ) {
			WP_Session_Tokens::get_instance( absint( $bridge_user_id ) )->destroy_all();
		}
	}
}
