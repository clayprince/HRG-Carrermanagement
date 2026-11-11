<?php
/** Register and render the WordPress-native Recruitment admin screens. */
class Recruitment_Admin {

	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 9 );
		add_action( 'admin_post_daw_save_department_request', [ $this, 'handle_department_request_post' ] );
		add_action( 'admin_post_daw_review_department_request', [ $this, 'handle_department_request_review' ] );
		add_action( 'admin_post_daw_create_department_account', [ $this, 'handle_department_account_creation' ] );
		add_action( 'admin_post_daw_set_department_account_status', [ $this, 'handle_department_account_status' ] );
		add_action( 'admin_post_daw_update_department_account', [ $this, 'handle_department_account_update' ] );
		add_action( 'admin_post_daw_delete_department_account', [ $this, 'handle_department_account_delete' ] );
		add_action( 'admin_post_daw_delete_hr_account', [ $this, 'handle_hr_account_delete' ] );
		add_action( 'admin_post_daw_create_hr_account', [ $this, 'handle_hr_account_creation' ] );
		add_action( 'admin_post_daw_change_hr_password', [ $this, 'handle_hr_password_change' ] );
		add_action( 'admin_post_daw_change_department_password', [ $this, 'handle_department_password_change' ] );
		add_action( 'admin_post_daw_save_vacancy', [ $this, 'handle_vacancy_save' ] );
		add_action( 'admin_post_daw_set_vacancy_status', [ $this, 'handle_vacancy_status' ] );
		add_action( 'admin_post_daw_update_application_status', [ $this, 'handle_application_status' ] );
		add_action( 'admin_post_daw_save_hr_interview', [ $this, 'handle_hr_interview_save' ] );
		add_action( 'admin_post_daw_send_application_status_email', [ $this, 'handle_application_status_email' ] );
		add_action( 'admin_post_daw_update_psychotest_attendance_time', [ $this, 'handle_psychotest_attendance_time_update' ] );
		add_action( 'admin_post_daw_update_applicant_email', [ $this, 'handle_applicant_email_update' ] );
		add_action( 'admin_post_daw_save_email_settings', [ $this, 'handle_save_email_settings' ] );
		add_action( 'admin_post_daw_save_email_template', [ $this, 'handle_save_email_template' ] );
		add_action( 'admin_post_daw_delete_email_template', [ $this, 'handle_delete_email_template' ] );
		add_action( 'admin_post_daw_send_test_email', [ $this, 'handle_send_test_email' ] );
		add_action( 'admin_post_daw_send_composed_email', [ $this, 'handle_send_composed_email' ] );
		add_action( 'admin_post_daw_download_applicant_profile', [ $this, 'handle_applicant_profile_download' ] );
		add_action( 'admin_post_daw_download_department_profile', [ $this, 'handle_department_profile_download' ] );
		add_action( 'admin_post_daw_download_department_document', [ $this, 'handle_department_document_download' ] );
		add_action( 'admin_post_daw_save_interview_schedule', [ $this, 'handle_interview_schedule_save' ] );
		add_action( 'admin_post_daw_delete_interview_slot', [ $this, 'handle_interview_slot_delete' ] );
		add_action( 'admin_menu', [ $this, 'remove_vacancy_post_type_submenu' ], 99 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'in_admin_header', [ $this, 'render_admin_sidebar' ] );
		add_action( 'admin_notices', [ $this, 'render_vacancy_overview' ] );
		add_action( 'admin_footer', [ $this, 'render_screen_explorer' ] );
		add_filter( 'admin_body_class', [ $this, 'add_admin_body_class' ] );
	}

	public function handle_interview_schedule_save(): void {
		$context = $this->get_interview_schedule_context();
		check_admin_referer( 'daw_save_interview_schedule' );
		$database = new Recruitment_Database();
		$slot_id = isset( $_POST['slot_id'] ) && is_scalar( $_POST['slot_id'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['slot_id'] ) )
			: '';
		if ( '' !== $slot_id ) {
			$slot = [];
			foreach ( [ 'date', 'start', 'end', 'mode', 'capacity', 'platform', 'meeting_url', 'location', 'interviewer' ] as $key ) {
				$slot[ $key ] = isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] )
					? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) )
					: '';
			}
			$result = $database->update_interview_slot( $slot_id, $context['type'], $context['department_id'], $slot );
			$success_message = 'Jadwal berhasil diperbarui.';
		} else {
			$dates_json = isset( $_POST['dates_json'] ) && is_string( $_POST['dates_json'] )
				? wp_unslash( $_POST['dates_json'] )
				: '';
			$slots_json = isset( $_POST['slots_json'] ) && is_string( $_POST['slots_json'] )
				? wp_unslash( $_POST['slots_json'] )
				: '';
			$dates = json_decode( $dates_json, true );
			$slots = json_decode( $slots_json, true );
			if ( ! is_array( $dates ) || ! is_array( $slots ) ) {
				$result = [ 'success' => false, 'error' => 'Tanggal atau data slot tidak valid.' ];
			} else {
				$dates = array_values( array_filter( $dates, 'is_string' ) );
				$slots = array_values( array_filter( $slots, 'is_array' ) );
				$result = $database->create_interview_slots( $context['type'], $context['department_id'], $dates, $slots, wp_timezone_string() );
			}
			$success_message = 'Jadwal berhasil disimpan.';
		}

		$this->store_interview_schedule_notice(
			(bool) ( $result['success'] ?? false ),
			(bool) ( $result['success'] ?? false ) ? $success_message : (string) ( $result['error'] ?? 'Jadwal gagal disimpan.' )
		);
		$this->redirect_to_interview_schedule( $context['return_url'], $this->get_interview_schedule_month() );
	}

	public function handle_interview_slot_delete(): void {
		$context = $this->get_interview_schedule_context();
		$slot_id = isset( $_POST['slot_id'] ) && is_scalar( $_POST['slot_id'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['slot_id'] ) )
			: '';
		check_admin_referer( 'daw_delete_interview_slot_' . $slot_id );
		$result = ( new Recruitment_Database() )->delete_interview_slot( $slot_id, $context['type'], $context['department_id'] );
		$this->store_interview_schedule_notice(
			(bool) ( $result['success'] ?? false ),
			(bool) ( $result['success'] ?? false ) ? 'Jadwal berhasil dihapus.' : (string) ( $result['error'] ?? 'Jadwal gagal dihapus.' )
		);
		$this->redirect_to_interview_schedule( $context['return_url'], $this->get_interview_schedule_month() );
	}

	/**
	 * @return array{type: string, department_id: int|null, return_url: string}
	 */
	private function get_interview_schedule_context(): array {
		$type = isset( $_POST['schedule_type'] ) && is_scalar( $_POST['schedule_type'] )
			? strtoupper( sanitize_key( wp_unslash( (string) $_POST['schedule_type'] ) ) )
			: '';
		if ( 'INTERVIEW_HR' === $type && current_user_can( 'manage_recruitment' ) ) {
			return [
				'type' => 'INTERVIEW_HR',
				'department_id' => null,
				'return_url' => add_query_arg( 'page', 'recruitment-interview-schedule', admin_url( 'admin.php' ) ),
			];
		}
		if ( 'INTERVIEW_USER' === $type && current_user_can( 'manage_user_department' ) ) {
			$department_id = absint( get_user_meta( get_current_user_id(), 'daw_department_id', true ) );
			if ( 0 >= $department_id ) {
				wp_die( esc_html__( 'Akun User Department belum terhubung ke ID departemen Oracle.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
			}
			return [
				'type' => 'INTERVIEW_USER',
				'department_id' => $department_id,
				'return_url' => add_query_arg(
					[ 'page' => 'daw-user-department', 'view' => 'interview-schedule' ],
					admin_url( 'admin.php' )
				),
			];
		}

		wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengelola jenis jadwal wawancara ini.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
	}

	private function get_interview_schedule_month(): string {
		$month = isset( $_POST['month'] ) && is_scalar( $_POST['month'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['month'] ) )
			: wp_date( 'Y-m' );
		return preg_match( '/\A\d{4}-(0[1-9]|1[0-2])\z/', $month ) ? $month : wp_date( 'Y-m' );
	}

	private function store_interview_schedule_notice( bool $success, string $message ): void {
		set_transient(
			'daw_interview_schedule_notice_' . get_current_user_id(),
			[ 'success' => $success, 'message' => $message ],
			MINUTE_IN_SECONDS
		);
	}

	private function redirect_to_interview_schedule( string $return_url, string $month ): void {
		wp_safe_redirect( add_query_arg( 'month', $month, $return_url ) );
		exit;
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

	public function render_vacancy_overview(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base || 'daw_vacancy' !== $screen->post_type || ! current_user_can( 'manage_recruitment' ) ) {
			return;
		}

		$counts = wp_count_posts( 'daw_vacancy' );
		$published = (int) ( $counts->publish ?? 0 );
		$draft = (int) ( $counts->draft ?? 0 ) + (int) ( $counts->pending ?? 0 ) + (int) ( $counts->future ?? 0 );
		$archived = (int) ( $counts->trash ?? 0 );
		$total = $published + $draft + (int) ( $counts->private ?? 0 );
		?>
		<section class="daw-vacancy-overview" aria-labelledby="daw-vacancy-overview-title">
			<div class="daw-vacancy-overview__heading">
				<div>
					<p class="daw-vacancy-overview__breadcrumb">DAW Admin <span>/</span> Recruitment</p>
					<h1 id="daw-vacancy-overview-title">Lowongan</h1>
					<p class="daw-vacancy-overview__description">Kelola posisi dan publikasi lowongan kerja.</p>
				</div>
				<a class="daw-vacancy-overview__create" href="<?= esc_url( admin_url( 'post-new.php?post_type=daw_vacancy' ) ) ?>"><span aria-hidden="true">+</span> Buat Lowongan</a>
			</div>
			<div class="daw-vacancy-overview__stats" aria-label="Ringkasan lowongan">
				<div class="daw-vacancy-overview__stat daw-vacancy-overview__stat--total"><span>Total Lowongan</span><strong><?= esc_html( number_format_i18n( $total ) ) ?></strong></div>
				<div class="daw-vacancy-overview__stat daw-vacancy-overview__stat--published"><span>Dipublikasikan</span><strong><?= esc_html( number_format_i18n( $published ) ) ?></strong></div>
				<div class="daw-vacancy-overview__stat daw-vacancy-overview__stat--draft"><span>Draft / Terjadwal</span><strong><?= esc_html( number_format_i18n( $draft ) ) ?></strong></div>
				<div class="daw-vacancy-overview__stat daw-vacancy-overview__stat--archived"><span>Arsip</span><strong><?= esc_html( number_format_i18n( $archived ) ) ?></strong></div>
			</div>
		</section>
		<?php
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
		add_submenu_page( 'recruitment-dashboard', 'Setting Psikotes', 'Setting Psikotes', 'manage_recruitment', 'recruitment-psychotest-settings', [ $this, 'render_psychotest_settings' ] );
		add_submenu_page( 'recruitment-dashboard', 'Hasil Psikotes', 'Hasil Psikotes', 'manage_recruitment', 'recruitment-psychotest-results', [ $this, 'render_psychotest_results' ] );
		add_submenu_page( 'recruitment-dashboard', 'Bank Soal', 'Bank Soal', 'manage_recruitment', 'recruitment-question-bank', [ $this, 'render_question_bank' ] );
		add_submenu_page( 'recruitment-dashboard', 'Pengaturan Email', 'Pengaturan Email', 'manage_recruitment', 'recruitment-settings', [ $this, 'render_settings' ] );
		add_options_page( 'Email Recruitment DAW', 'Email Recruitment', 'manage_recruitment', 'recruitment-settings', [ $this, 'render_settings' ] );
	}

	public function remove_vacancy_post_type_submenu(): void {
		remove_submenu_page( 'recruitment-dashboard', 'edit.php?post_type=daw_vacancy' );
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

	public function handle_vacancy_save(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengelola lowongan.', 'recruitment-plugin' ) );
		}
		check_admin_referer( 'daw_save_vacancy' );

		$vacancy_id = absint( $_POST['vacancy_id'] ?? 0 );
		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$responsibilities = sanitize_textarea_field( wp_unslash( $_POST['responsibilities'] ?? '' ) );
		$requirements = sanitize_textarea_field( wp_unslash( $_POST['requirements'] ?? '' ) );
		$type = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
		$region = sanitize_text_field( wp_unslash( $_POST['region'] ?? '' ) );
		$department_id = absint( $_POST['department_id'] ?? 0 );
		$dealer = sanitize_text_field( wp_unslash( $_POST['dealer'] ?? '' ) );
		$location = sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) );
		$deadline = sanitize_text_field( wp_unslash( $_POST['deadline'] ?? '' ) );
		$status = sanitize_key( wp_unslash( $_POST['status'] ?? 'draft' ) );
		$database = new Recruitment_Database();
		$departments = $database->get_departments();
		if ( $database->has_error() ) {
			$this->redirect_to_vacancies( 'department_unavailable', $vacancy_id );
		}
		$department_name = '';
		foreach ( $departments as $department ) {
			if ( (int) $department['id'] === $department_id ) {
				$department_name = (string) $department['name'];
				break;
			}
		}
		$valid_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $deadline );
		$date_errors = DateTimeImmutable::getLastErrors();
		$is_valid_date = $valid_date && ( false === $date_errors || ( 0 === $date_errors['warning_count'] && 0 === $date_errors['error_count'] ) )
			&& $valid_date->format( 'Y-m-d' ) === $deadline
			&& $deadline >= current_time( 'Y-m-d' );

		if ( '' === $title || '' === $dealer || '' === $department_name || ! $is_valid_date || ! in_array( $status, [ 'draft', 'published' ], true ) ) {
			$this->redirect_to_vacancies( 'invalid_data', $vacancy_id );
		}
		if ( $vacancy_id && ( 'daw_vacancy' !== get_post_type( $vacancy_id ) || ! current_user_can( 'edit_post', $vacancy_id ) ) ) {
			wp_die( esc_html__( 'Lowongan yang akan diubah tidak ditemukan atau tidak dapat diakses.', 'recruitment-plugin' ) );
		}

		$post_data = [
			'post_type' => 'daw_vacancy',
			'post_status' => 'published' === $status ? 'publish' : 'draft',
			'post_title' => $title,
			'post_content' => $description,
			'post_excerpt' => wp_trim_words( $description, 24 ),
		];
		if ( $vacancy_id ) {
			$post_data['ID'] = $vacancy_id;
		}
		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			error_log( 'Recruitment vacancy could not be saved: ' . $post_id->get_error_message() );
			$this->redirect_to_vacancies( 'save_failed', $vacancy_id );
		}

		foreach (
			[
				'_daw_responsibilities' => $responsibilities,
				'_daw_requirements' => $requirements,
				'_daw_type' => $type,
				'_daw_region' => $region,
				'_daw_department_id' => $department_id,
				'_daw_department' => $department_name,
				'_daw_dealer' => $dealer,
				'_daw_location' => $location,
				'_daw_deadline' => $deadline,
				'_daw_vacancy_status' => $status,
			] as $meta_key => $meta_value
		) {
			update_post_meta( $post_id, $meta_key, $meta_value );
		}

		$this->redirect_to_vacancies( 'saved' );
	}

	public function handle_vacancy_status(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengelola lowongan.', 'recruitment-plugin' ) );
		}

		$vacancy_id = absint( $_POST['vacancy_id'] ?? 0 );
		$status = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );
		check_admin_referer( 'daw_set_vacancy_status_' . $vacancy_id );
		if ( ! $vacancy_id || 'daw_vacancy' !== get_post_type( $vacancy_id ) || ! current_user_can( 'edit_post', $vacancy_id ) || ! in_array( $status, [ 'published', 'draft', 'closed', 'archived' ], true ) ) {
			wp_die( esc_html__( 'Data status lowongan tidak valid.', 'recruitment-plugin' ) );
		}

		$post_status = in_array( $status, [ 'published', 'draft' ], true ) ? ( 'published' === $status ? 'publish' : 'draft' ) : 'private';
		$updated = wp_update_post( [ 'ID' => $vacancy_id, 'post_status' => $post_status ], true );
		if ( is_wp_error( $updated ) || ! $updated ) {
			$error = is_wp_error( $updated ) ? $updated->get_error_message() : 'WordPress did not update the post.';
			error_log( 'Recruitment vacancy status could not be updated: ' . $error );
			$this->redirect_to_vacancies( 'save_failed' );
		}
		update_post_meta( $vacancy_id, '_daw_vacancy_status', $status );
		$this->redirect_to_vacancies( 'status_updated' );
	}

	private function redirect_to_vacancies( string $notice, int $edit_id = 0 ): void {
		$args = [ 'page' => 'recruitment-vacancies', 'vacancy_notice' => $notice ];
		if ( $edit_id ) {
			$args['view'] = 'create';
			$args['edit_vacancy'] = $edit_id;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
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
		$decision = sanitize_key( wp_unslash( $_POST['decision'] ?? '' ) );
		$note = sanitize_textarea_field( wp_unslash( $_POST['hr_note'] ?? '' ) );
		$decisions = [
			'approve' => 'Disetujui',
			'revise' => 'Perlu Revisi',
			'reject' => 'Ditolak',
		];

		if ( '' === $request_code || ! isset( $decisions[ $decision ] ) ) {
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

		$status = $decisions[ $decision ];
		if ( $owner_id ) {
			$requests = get_user_meta( $owner_id, 'daw_department_job_requests', true );
			if ( ! is_array( $requests ) || ! isset( $requests[ $request_code ] ) ) {
				wp_die( esc_html__( 'Pengajuan tidak ditemukan.', 'recruitment-plugin' ) );
			}

			$request = $requests[ $request_code ];
			if ( ! in_array( $request['status'] ?? '', [ 'Review HR', 'Menunggu Review HR' ], true ) ) {
				wp_die( esc_html__( 'Pengajuan ini sudah tidak menunggu review.', 'recruitment-plugin' ) );
			}
			$fields = is_array( $request['fields'] ?? null ) ? $request['fields'] : [];
			if ( 'approve' === $decision && empty( $request['vacancy_id'] ) ) {
				$department_name = trim( sanitize_text_field( (string) ( $fields['department'] ?? '' ) ) );
				$department_id = 0;
				$database = new Recruitment_Database();
				$departments = $database->get_departments();
				if ( $database->has_error() ) {
					wp_die( esc_html__( 'Daftar departemen Oracle tidak tersedia. Lowongan belum dapat dibuat.', 'recruitment-plugin' ) );
				}
				foreach ( $departments as $department ) {
					if ( 0 === strcasecmp( trim( (string) $department['name'] ), $department_name ) ) {
						$department_id = (int) $department['id'];
						$department_name = (string) $department['name'];
						break;
					}
				}
				if ( ! $department_id ) {
					wp_die( esc_html__( 'Departemen pada pengajuan tidak cocok dengan daftar departemen Oracle. Perbarui data departemen sebelum menyetujui.', 'recruitment-plugin' ) );
				}
				$vacancy_id = wp_insert_post(
					[
						'post_type' => 'daw_vacancy',
						'post_status' => 'draft',
						'post_title' => sanitize_text_field( $fields['position'] ?? $request_code ),
						'post_content' => sanitize_textarea_field( $fields['role_summary'] ?? '' ),
						'meta_input' => [
							'_daw_dealer' => sanitize_text_field( $fields['dealer'] ?? '' ),
							'_daw_region' => sanitize_text_field( $fields['region'] ?? '' ),
							'_daw_department_id' => $department_id,
							'_daw_department' => $department_name,
							'_daw_type' => sanitize_text_field( $fields['employment_type'] ?? '' ),
							'_daw_location' => sanitize_text_field( $fields['location'] ?? '' ),
							'_daw_fptk_code' => $request_code,
						],
					],
					true
				);
				if ( is_wp_error( $vacancy_id ) ) {
					wp_die( esc_html__( 'Lowongan belum dapat dibuat: ', 'recruitment-plugin' ) . esc_html( $vacancy_id->get_error_message() ) );
				}
				$request['vacancy_id'] = $vacancy_id;
			}

			$request['status'] = $status;
			$request['hr_note'] = $note;
			$request['updated_at'] = current_time( 'mysql' );
			$requests[ $request_code ] = $request;
			update_user_meta( $owner_id, 'daw_department_job_requests', $requests );
		} else {
			$demo_statuses = get_option( 'daw_demo_department_request_statuses', [] );
			$demo_statuses = is_array( $demo_statuses ) ? $demo_statuses : [];
			$demo_status = $demo_statuses[ $request_code ] ?? [];
			$demo_status = is_array( $demo_status ) ? $demo_status : [];
			$demo_pending_codes = [ 'FPTK-2026-032', 'FPTK-2026-030' ];
			if ( ! in_array( $request_code, $demo_pending_codes, true ) || ( isset( $demo_status['status'] ) && ! in_array( $demo_status['status'], [ 'Review HR', 'Menunggu Review HR' ], true ) ) ) {
				wp_die( esc_html__( 'Pengajuan contoh ini sudah tidak menunggu review.', 'recruitment-plugin' ) );
			}
			if ( 'approve' === $decision && empty( $demo_status['vacancy_id'] ) ) {
				$vacancy_id = wp_insert_post(
					[
						'post_type' => 'daw_vacancy',
						'post_status' => 'draft',
						'post_title' => sanitize_text_field( wp_unslash( $_POST['vacancy_title'] ?? '' ) ),
						'post_content' => sanitize_textarea_field( wp_unslash( $_POST['vacancy_content'] ?? '' ) ),
						'meta_input' => [
							'_daw_location' => sanitize_text_field( wp_unslash( $_POST['vacancy_location'] ?? '' ) ),
							'_daw_type' => sanitize_text_field( wp_unslash( $_POST['vacancy_type'] ?? '' ) ),
							'_daw_fptk_code' => $request_code,
						],
					],
					true
				);
				if ( is_wp_error( $vacancy_id ) ) {
					wp_die( esc_html__( 'Lowongan belum dapat dibuat: ', 'recruitment-plugin' ) . esc_html( $vacancy_id->get_error_message() ) );
				}
				$demo_status['vacancy_id'] = $vacancy_id;
			}
			$demo_statuses[ $request_code ] = [
				'status' => $status,
				'hr_note' => $note,
				'updated_at' => current_time( 'mysql' ),
				'vacancy_id' => absint( $demo_status['vacancy_id'] ?? 0 ),
			];
			update_option( 'daw_demo_department_request_statuses', $demo_statuses, false );
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

	public function handle_application_status(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk memperbarui status lamaran.', 'recruitment-plugin' ) );
		}

		$application_id = absint( $_POST['application_id'] ?? 0 );
		check_admin_referer( 'daw_update_application_status_' . $application_id );
		$candidate_id = absint( $_POST['candidate_id'] ?? 0 );
		$current_stage = strtoupper( sanitize_key( wp_unslash( $_POST['current_stage'] ?? '' ) ) );
		$decision = sanitize_key( wp_unslash( $_POST['selection_decision'] ?? $_POST['administrative_result'] ?? '' ) );
		$next_stage = strtoupper( sanitize_key( wp_unslash( $_POST['next_stage'] ?? '' ) ) );
		$note = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
		$template_id = sanitize_key( wp_unslash( $_POST['template_id'] ?? '' ) );

		if ( 'FINAL' === $current_stage || 'DITERIMA' === $next_stage ) {
			wp_safe_redirect(
				add_query_arg(
					[
						'page'    => 'recruitment-final-decision',
						'section' => 'decision',
					],
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( in_array( $decision, [ 'failed', 'rejected', 'ditolak', 'tidak_lolos' ], true ) ) {
			$status = 'DITOLAK';
			$stage = ! empty( $current_stage ) ? $current_stage : 'ADMINISTRASI';
			$note = trim( 'Hasil seleksi: tidak lolos. ' . $note );
		} elseif ( in_array( $decision, [ 'passed', 'accepted', 'lolos', 'diterima' ], true ) ) {
			if ( in_array( $next_stage, [ 'PSIKOTES', 'INTERVIEW_HR', 'INTERVIEW_USER', 'FINAL' ], true ) ) {
				$status = $next_stage;
				$stage = $next_stage;
				$note = trim( 'Lolos ke tahap ' . $next_stage . '. ' . $note );
			} else {
				$stage_transitions = [
					'ADMINISTRASI'   => 'PSIKOTES',
					'SCREENING'      => 'PSIKOTES',
					'SUBMITTED'      => 'PSIKOTES',
					'PSIKOTES'       => 'INTERVIEW_HR',
					'INTERVIEW_HR'   => 'INTERVIEW_USER',
				];
				$target = $stage_transitions[ $current_stage ] ?? 'PSIKOTES';
				$status = $target;
				$stage = $target;
				$note = trim( 'Lolos ke tahap ' . $stage . '. ' . $note );
			}
		} else {
			wp_die( esc_html__( 'Pilih hasil keputusan seleksi yang valid.', 'recruitment-plugin' ) );
		}

		$result = ( new Recruitment_Database() )->update_application_status( (string) $application_id, $status, $stage, $note );
		if ( empty( $result['success'] ) ) {
			wp_die( esc_html( $result['error'] ?? 'Status lamaran gagal disimpan.' ) );
		}

		$pending_key = $this->application_status_email_pending_key( get_current_user_id(), $application_id );
		set_transient( $pending_key, $status, 30 * MINUTE_IN_SECONDS );
		if ( '' !== $template_id ) {
			set_transient( $pending_key . '_tpl', $template_id, 30 * MINUTE_IN_SECONDS );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page'           => 'recruitment-applicants',
					'candidate_id'   => $candidate_id,
					'application_id' => $application_id,
					'status_updated' => '1',
					'email_preview'  => '1',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_hr_interview_save(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk menyimpan hasil wawancara HR.', 'recruitment-plugin' ) );
		}

		$application_id = absint( $_POST['application_id'] ?? 0 );
		check_admin_referer( 'daw_save_hr_interview_' . $application_id );
		$intent = isset( $_POST['intent'] ) && is_scalar( $_POST['intent'] )
			? sanitize_key( wp_unslash( (string) $_POST['intent'] ) )
			: '';
		$result = isset( $_POST['result'] ) && is_scalar( $_POST['result'] )
			? strtoupper( sanitize_key( wp_unslash( (string) $_POST['result'] ) ) )
			: '';
		if ( 'pass' === $intent ) {
			$result = 'LOLOS';
		} elseif ( 'fail' === $intent ) {
			$result = 'TIDAK_LOLOS';
		}
		$department_account_id = isset( $_POST['department_account_id'] ) && is_scalar( $_POST['department_account_id'] )
			? absint( wp_unslash( (string) $_POST['department_account_id'] ) )
			: 0;
		if ( 'fail' === $intent ) {
			$department_account_id = 0;
		}
		$interview_date = isset( $_POST['interview_date'] ) && is_scalar( $_POST['interview_date'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['interview_date'] ) )
			: '';
		$rating = isset( $_POST['rating'] ) && is_scalar( $_POST['rating'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['rating'] ) )
			: '';
		$recommendation = isset( $_POST['recommendation'] ) && is_scalar( $_POST['recommendation'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['recommendation'] ) )
			: '';
		$department_name = isset( $_POST['department_name'] ) && is_scalar( $_POST['department_name'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['department_name'] ) )
			: '';
		$notes = isset( $_POST['notes'] ) && is_scalar( $_POST['notes'] )
			? sanitize_textarea_field( wp_unslash( (string) $_POST['notes'] ) )
			: '';
		$department_notes = isset( $_POST['department_notes'] ) && is_scalar( $_POST['department_notes'] )
			? sanitize_textarea_field( wp_unslash( (string) $_POST['department_notes'] ) )
			: '';
		$user = wp_get_current_user();
		$interviewer = sanitize_text_field( (string) ( $user->display_name ?: $user->user_login ) );
		$database = new Recruitment_Database();
		if ( 'save_note' === $intent ) {
			$save_result = $database->save_hr_interview_note(
				(string) $application_id,
				$interview_date,
				$rating,
				$recommendation,
				$interviewer,
				$notes,
				$department_name,
				$department_account_id,
				$department_notes
			);
		} elseif ( in_array( $intent, [ 'pass', 'fail' ], true ) ) {
			$save_result = $database->save_hr_interview_result(
				(string) $application_id,
				$result,
				$department_account_id,
				$interviewer,
				$notes,
				$interview_date,
				$rating,
				$recommendation,
				$department_name,
				$department_notes
			);
		} else {
			wp_die( esc_html__( 'Aksi wawancara HR tidak valid.', 'recruitment-plugin' ) );
		}
		if ( empty( $save_result['success'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					[
						'page' => 'recruitment-hr-interview',
						'application_id' => $application_id,
						'hr_interview' => 'save_error',
					],
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$redirect_args = [ 'page' => 'recruitment-hr-interview' ];
		if ( 'save_note' === $intent ) {
			$redirect_args['application_id'] = $application_id;
			$redirect_args['hr_interview'] = 'note_saved';
		} else {
			$redirect_args['hr_interview'] = 'LOLOS' === $result ? 'saved' : 'failed';
		}
		wp_safe_redirect(
			add_query_arg( $redirect_args, admin_url( 'admin.php' ) )
		);
		exit;
	}

	public function handle_psychotest_attendance_time_update(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk memperbarui waktu konfirmasi psikotes.', 'recruitment-plugin' ) );
		}

		$application_id = absint( $_POST['application_id'] ?? 0 );
		$candidate_id = absint( $_POST['candidate_id'] ?? 0 );
		check_admin_referer( 'daw_update_psychotest_attendance_time_' . $application_id . '_' . $candidate_id );
		$confirmed_date = isset( $_POST['confirmed_date'] ) && is_scalar( $_POST['confirmed_date'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['confirmed_date'] ) )
			: '';
		$confirmed_time = isset( $_POST['confirmed_time'] ) && is_scalar( $_POST['confirmed_time'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['confirmed_time'] ) )
			: '';
		$local_datetime = $confirmed_date . 'T' . $confirmed_time;
		$parsed_datetime = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $local_datetime, wp_timezone() );
		if (
			0 >= $application_id
			|| 0 >= $candidate_id
			|| ! ( $parsed_datetime instanceof DateTimeImmutable )
			|| $parsed_datetime->format( 'Y-m-d\TH:i' ) !== $local_datetime
			|| ! recruitment_is_psychotest_attendance_time_in_window(
				(array) get_option( 'recruitment_psychotest_settings', [] ),
				$parsed_datetime
			)
		) {
			$result = [ 'success' => false, 'error' => 'invalid_time' ];
		} else {
			$result = ( new Recruitment_Database() )->update_psychotest_attendance_time(
				(string) $candidate_id,
				(string) $application_id,
				$local_datetime
			);
			if ( ! empty( $result['success'] ) ) {
				$plugin = new Recruitment_Plugin();
				$plugin->schedule_attendance_reminders( $application_id, $local_datetime );
				$plugin->send_attendance_confirmation_email( $application_id, $local_datetime );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page' => 'recruitment-applicants',
					'candidate_id' => $candidate_id,
					'application_id' => $application_id,
					'attendance_time_result' => ! empty( $result['success'] ) ? 'updated' : ( 'invalid_time' === ( $result['error'] ?? '' ) ? 'invalid' : 'error' ),
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_application_status_email(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengirim pembaruan status lamaran.', 'recruitment-plugin' ) );
		}

		$application_id = absint( $_POST['application_id'] ?? 0 );
		$candidate_id = absint( $_POST['candidate_id'] ?? 0 );
		check_admin_referer( 'daw_send_application_status_email_' . $application_id . '_' . $candidate_id );

		$pending_key = $this->application_status_email_pending_key( get_current_user_id(), $application_id );
		$pending_status = get_transient( $pending_key );
		$database = new Recruitment_Database();

		$application = null;
		foreach ( $database->get_applications() as $row ) {
			if ( (int) ( $row['id'] ?? 0 ) === $application_id ) {
				$application = $row;
				break;
			}
		}

		$current_status = strtoupper( trim( (string) ( $application['status'] ?? ( is_string( $pending_status ) ? $pending_status : '' ) ) ) );

		$posted_recipient = sanitize_email( wp_unslash( $_POST['recipient_email'] ?? '' ) );
		if ( is_email( $posted_recipient ) ) {
			$recipient = $posted_recipient;
			if ( $candidate_id > 0 ) {
				$database->update_candidate_email( $candidate_id, $recipient );
			}
		} else {
			$recipient = sanitize_email( (string) ( $application['applicant_email'] ?? '' ) );
			if ( ! is_email( $recipient ) && $candidate_id > 0 ) {
				$profile = $database->get_admin_applicant( (string) $candidate_id );
				if ( ! empty( $profile['email'] ) && is_email( $profile['email'] ) ) {
					$recipient = sanitize_email( $profile['email'] );
				}
			}
		}

		if ( ! is_email( $recipient ) ) {
			wp_safe_redirect( add_query_arg( [
				'page'               => 'recruitment-applicants',
				'candidate_id'       => $candidate_id,
				'application_id'     => $application_id,
				'status_email_error' => '1',
				'mail_error_msg'     => rawurlencode( 'Alamat email pelamar tidak valid atau tidak ditemukan.' ),
			], admin_url( 'admin.php' ) ) );
			exit;
		}

		$applicant_name = (string) ( $application['applicant_name'] ?? 'Pelamar' );
		$position       = (string) ( $application['title'] ?? 'Posisi yang Dilamar' );
		$dealer         = (string) ( $application['dealer'] ?? $application['location'] ?? 'PT Daya Adicipta Wisesa' );

		$subject = sanitize_text_field( wp_unslash( $_POST['email_subject'] ?? '' ) );
		$body = sanitize_textarea_field( wp_unslash( $_POST['email_body'] ?? '' ) );

		$placeholders = [
			'nama'    => $applicant_name,
			'posisi'  => $position,
			'dealer'  => $dealer,
			'tahap'   => $current_status,
			'tanggal' => wp_date( 'j F Y' ),
		];

		if ( '' === $subject || '' === $body ) {
			$saved_tpl = get_transient( $pending_key . '_tpl' );
			$template_id = sanitize_key( wp_unslash( $_POST['selected_template_id'] ?? ( is_string( $saved_tpl ) ? $saved_tpl : '' ) ) );
			$templates = recruitment_get_email_templates();
			$template = $templates[ $template_id ] ?? null;

			if ( ! $template ) {
				if ( 'DITOLAK' === $current_status ) {
					$template = $templates['tidak_lolos_administrasi'] ?? $templates['pengumuman_ditolak'] ?? null;
				} elseif ( 'DITERIMA' === $current_status ) {
					$template = $templates['pengumuman_diterima'] ?? null;
				} elseif ( 'PSIKOTES' === $current_status ) {
					$template = $templates['undangan_psikotes'] ?? $templates['lolos_administrasi'] ?? null;
				} elseif ( 'INTERVIEW_HR' === $current_status ) {
					$template = $templates['undangan_wawancara_hr'] ?? $templates['lolos_psikotes'] ?? null;
				} elseif ( 'INTERVIEW_USER' === $current_status ) {
					$template = $templates['undangan_wawancara_user'] ?? $templates['lolos_wawancara_hr'] ?? null;
				}
			}

			if ( '' === $subject ) {
				$subject = $template ? $template['subject'] : ( 'Pembaruan Status Lamaran: ' . $position . ' - PT Daya Adicipta Wisesa' );
			}
			if ( '' === $body ) {
				$body = $template ? $template['body'] : ( "Halo {{nama}},\n\nStatus lamaran Anda untuk posisi {{posisi}} telah diperbarui menjadi: " . $current_status . ".\n\nSilakan cek portal rekrutmen DAW untuk melihat informasi terkini.\n\nSalam,\nTim Rekrutmen DAW" );
			}
		}

		$subject = recruitment_replace_template_placeholders( $subject, $placeholders );
		$body = recruitment_replace_template_placeholders( $body, $placeholders );
		$html_message = recruitment_build_status_email_html( $subject, $body, $current_status );

		$email_settings = ( new Recruitment_Plugin() )->get_email_settings();
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		if ( ! empty( $email_settings['from_email'] ) && is_email( $email_settings['from_email'] ) ) {
			$from_name = ! empty( $email_settings['from_name'] ) ? $email_settings['from_name'] : 'Rekrutmen PT Daya Adicipta Wisesa';
			$headers[] = 'From: ' . $from_name . ' <' . $email_settings['from_email'] . '>';
			$headers[] = 'Reply-To: ' . $email_settings['from_email'];
		}

		$mail_error_msg = '';
		$error_catcher = static function( $wp_error ) use ( &$mail_error_msg ) {
			if ( is_wp_error( $wp_error ) ) {
				$mail_error_msg = $wp_error->get_error_message();
			}
		};
		add_action( 'wp_mail_failed', $error_catcher );

		$sent = wp_mail(
			$recipient,
			$subject,
			$html_message,
			$headers
		);

		remove_action( 'wp_mail_failed', $error_catcher );

		$redirect_args = [
			'page'           => 'recruitment-applicants',
			'candidate_id'   => $candidate_id,
			'application_id' => $application_id,
		];
		if ( $sent ) {
			delete_transient( $pending_key );
			delete_transient( $pending_key . '_tpl' );
			$redirect_args['status_email_sent'] = '1';
			$redirect_args['sent_to'] = rawurlencode( $recipient );
		} else {
			$redirect_args['status_email_error'] = '1';
			if ( '' !== $mail_error_msg ) {
				$redirect_args['mail_error_msg'] = rawurlencode( $mail_error_msg );
			}
		}
		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_save_email_template(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengelola template email.', 'recruitment-plugin' ) );
		}
		check_admin_referer( 'daw_save_email_template' );

		$id = sanitize_key( wp_unslash( $_POST['template_id'] ?? '' ) );
		$title = sanitize_text_field( wp_unslash( $_POST['template_title'] ?? '' ) );
		$category = sanitize_text_field( wp_unslash( $_POST['template_category'] ?? 'lamaran' ) );
		$stage = strtoupper( sanitize_key( wp_unslash( $_POST['template_stage'] ?? 'ADMINISTRASI' ) ) );
		$subject = sanitize_text_field( wp_unslash( $_POST['template_subject'] ?? '' ) );
		$body = sanitize_textarea_field( wp_unslash( $_POST['template_body'] ?? '' ) );

		if ( '' === $title || '' === $subject || '' === $body ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'recruitment-settings', 'tpl_result' => 'empty_fields' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		$saved = recruitment_save_email_template( [
			'id'       => $id,
			'title'    => $title,
			'category' => $category,
			'stage'    => $stage,
			'subject'  => $subject,
			'body'     => $body,
		] );

		wp_safe_redirect( add_query_arg( [ 'page' => 'recruitment-settings', 'tpl_result' => $saved ? 'saved' : 'error' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_delete_email_template(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk menghapus template email.', 'recruitment-plugin' ) );
		}
		check_admin_referer( 'daw_delete_email_template' );

		$id = sanitize_key( wp_unslash( $_POST['template_id'] ?? '' ) );
		$deleted = recruitment_delete_email_template( $id );

		wp_safe_redirect( add_query_arg( [ 'page' => 'recruitment-settings', 'tpl_result' => $deleted ? 'deleted' : 'error' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	private function application_status_email_pending_key( int $user_id, int $application_id ): string {
		return 'daw_app_status_email_' . $user_id . '_' . $application_id;
	}

	public function handle_applicant_email_update(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengubah email akun pelamar.', 'recruitment-plugin' ) );
		}
		check_admin_referer( 'daw_update_applicant_email' );

		$full_name = sanitize_text_field( wp_unslash( $_POST['applicant_full_name'] ?? '' ) );
		$old_email = sanitize_email( wp_unslash( $_POST['applicant_old_email'] ?? '' ) );
		$new_email = sanitize_email( wp_unslash( $_POST['applicant_new_email'] ?? '' ) );
		$result = [ 'success' => false, 'error' => 'invalid_email_change' ];

		if ( ! empty( $_POST['confirm_applicant_email_change'] ) && is_email( $old_email ) && is_email( $new_email ) ) {
			$result = ( new Recruitment_Database() )->update_applicant_account_email( $full_name, $old_email, $new_email );
		}

		$result_code = ! empty( $result['success'] )
			? 'success'
			: sanitize_key( (string) ( $result['error'] ?? 'service_unavailable' ) );
		wp_safe_redirect(
			add_query_arg(
				[
					'page' => 'recruitment-settings',
					'applicant_email_result' => $result_code,
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_save_email_settings(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengelola pengaturan email.', 'recruitment-plugin' ) );
		}
		check_admin_referer( 'daw_save_email_settings' );

		$settings = [
			'is_enabled'      => ! empty( $_POST['is_enabled'] ) ? '1' : '0',
			'smtp_host'       => sanitize_text_field( wp_unslash( $_POST['smtp_host'] ?? 'smtp.gmail.com' ) ),
			'smtp_port'       => absint( $_POST['smtp_port'] ?? 587 ),
			'smtp_encryption' => sanitize_key( wp_unslash( $_POST['smtp_encryption'] ?? 'tls' ) ),
			'smtp_auth'       => ! empty( $_POST['smtp_auth'] ) ? '1' : '0',
			'smtp_user'       => sanitize_text_field( wp_unslash( $_POST['smtp_user'] ?? '' ) ),
			'smtp_pass'       => (string) wp_unslash( $_POST['smtp_pass'] ?? '' ),
			'from_email'      => sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) ),
			'from_name'       => sanitize_text_field( wp_unslash( $_POST['from_name'] ?? 'Rekrutmen PT Daya Adicipta Wisesa' ) ),
		];

		if ( '' === $settings['smtp_pass'] ) {
			$existing = (array) get_option( 'recruitment_email_settings', [] );
			$settings['smtp_pass'] = (string) ( $existing['smtp_pass'] ?? '' );
		}

		update_option( 'recruitment_email_settings', $settings, false );

		wp_safe_redirect( add_query_arg( [ 'page' => 'recruitment-settings', 'section' => 'email', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_send_test_email(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengirim email uji coba.', 'recruitment-plugin' ) );
		}
		check_admin_referer( 'daw_send_test_email' );

		$recipient = sanitize_email( wp_unslash( $_POST['test_email_recipient'] ?? '' ) );
		if ( ! is_email( $recipient ) ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'recruitment-settings', 'section' => 'email', 'test_result' => 'invalid_email' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( '' !== ( new Recruitment_Plugin() )->get_email_configuration_error() ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'recruitment-settings', 'section' => 'email', 'test_result' => 'configuration' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		delete_transient( 'daw_last_mail_error' );

		$subject = 'Uji Coba Pengiriman Email Recruitment DAW - Berhasil';
		$message = '<div style="font-family:Arial,sans-serif;padding:24px;border:1px solid #e5e7eb;border-radius:10px;max-width:560px;">'
			. '<h2 style="color:#065f46;margin-top:0;">Konfigurasi Email Berhasil!</h2>'
			. '<p style="color:#374151;line-height:1.6;">Email ini dikirim dari portal <strong>Rekrutmen PT Daya Adicipta Wisesa</strong> untuk memverifikasi bahwa konfigurasi SMTP/API email sistem rekrutmen Anda telah terhubung dan aktif dengan sukses.</p>'
			. '<hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0;">'
			. '<p style="color:#6b7280;font-size:12px;">Waktu pengiriman: ' . esc_html( current_time( 'mysql' ) ) . ' WIB</p>'
			. '</div>';

		$sent = wp_mail(
			$recipient,
			$subject,
			$message,
			[ 'Content-Type: text/html; charset=UTF-8' ]
		);

		if ( $sent ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'recruitment-settings', 'section' => 'email', 'test_result' => 'success' ], admin_url( 'admin.php' ) ) );
		} else {
			wp_safe_redirect( add_query_arg( [ 'page' => 'recruitment-settings', 'section' => 'email', 'test_result' => 'failed' ], admin_url( 'admin.php' ) ) );
		}
		exit;
	}

	public function handle_send_composed_email(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengirim email recruitment.', 'recruitment-plugin' ) );
		}
		check_admin_referer( 'daw_send_composed_email' );

		$recipients_str = sanitize_text_field( wp_unslash( $_POST['to_recipients'] ?? '' ) );
		$cc_email = sanitize_email( wp_unslash( $_POST['cc_email'] ?? '' ) );
		$bcc_email = sanitize_email( wp_unslash( $_POST['bcc_email'] ?? '' ) );
		$subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$message = wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) );

		if ( '' === $subject || '' === $message ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'recruitment-settings', 'send_result' => 'empty_fields' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		$recipients = array_filter( array_map( 'trim', explode( ',', $recipients_str ) ) );
		if ( empty( $recipients ) && ! empty( $_POST['selected_recipients'] ) && is_array( $_POST['selected_recipients'] ) ) {
			$recipients = array_filter( array_map( 'sanitize_email', $_POST['selected_recipients'] ) );
		}

		if ( empty( $recipients ) ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'recruitment-settings', 'send_result' => 'no_recipients' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		if ( is_email( $cc_email ) ) {
			$headers[] = 'Cc: ' . $cc_email;
		}
		if ( is_email( $bcc_email ) ) {
			$headers[] = 'Bcc: ' . $bcc_email;
		}

		$html_body = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937;max-width:600px;margin:0 auto;padding:20px;border:1px solid #e5e7eb;border-radius:8px;">'
			. nl2br( esc_html( $message ) )
			. '</div>';

		$sent_count = 0;
		foreach ( $recipients as $recipient ) {
			if ( is_email( $recipient ) ) {
				$sent = wp_mail( $recipient, $subject, $html_body, $headers );
				if ( $sent ) {
					$sent_count++;
				}
			}
		}

		wp_safe_redirect( add_query_arg( [
			'page' => 'recruitment-settings',
			'send_result' => $sent_count > 0 ? 'success' : 'failed',
			'sent_count' => $sent_count,
		], admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_applicant_profile_download(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengunduh profil pelamar.', 'recruitment-plugin' ) );
		}

		$candidate_id = absint( $_POST['candidate_id'] ?? 0 );
		$application_id = absint( $_POST['application_id'] ?? 0 );
		check_admin_referer( 'daw_download_applicant_profile_' . $candidate_id );
		$database = new Recruitment_Database();
		$report = $database->get_admin_application_profile( (string) $application_id );
		if ( ! $report || (int) ( $report['application']['applicant_id'] ?? 0 ) !== $candidate_id ) {
			wp_die( esc_html__( 'Lamaran tidak ditemukan atau tidak sesuai dengan pelamar.', 'recruitment-plugin' ) );
		}

		$this->stream_applicant_profile_pdf( $report, 'profil-pelamar-' . $application_id . '.pdf' );
	}

	public function handle_department_profile_download(): void {
		if ( ! current_user_can( 'manage_user_department' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengunduh profil pelamar.', 'recruitment-plugin' ) );
		}

		$application_id = absint( $_GET['application_id'] ?? 0 );
		$account_id = absint( get_user_meta( get_current_user_id(), 'daw_oracle_account_id', true ) );
		check_admin_referer( 'daw_download_department_profile_' . $application_id );
		$report = ( new Recruitment_Database() )->get_user_department_candidate_detail( (string) $application_id, $account_id );
		if ( ! $report ) {
			wp_die( esc_html__( 'Profil tidak ditemukan atau lamaran ini tidak ditugaskan kepada akun Anda.', 'recruitment-plugin' ) );
		}

		$this->stream_applicant_profile_pdf( $report, 'profil-pelamar-' . $application_id . '.pdf', true );
	}

	public function handle_department_document_download(): void {
		if ( ! current_user_can( 'manage_user_department' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengunduh dokumen pelamar.', 'recruitment-plugin' ) );
		}

		$application_id = absint( $_GET['application_id'] ?? 0 );
		$field_key = isset( $_GET['field_key'] ) && is_scalar( $_GET['field_key'] )
			? sanitize_text_field( wp_unslash( (string) $_GET['field_key'] ) )
			: '';
		check_admin_referer( 'daw_download_department_document_' . $application_id . '_' . $field_key );
		$account_id = absint( get_user_meta( get_current_user_id(), 'daw_oracle_account_id', true ) );
		$document = ( new Recruitment_Database() )->get_user_department_application_document( (string) $application_id, $account_id, $field_key );
		if ( ! $document || '' === $document['file_content'] ) {
			wp_die( esc_html__( 'Dokumen tidak ditemukan atau lamaran ini tidak ditugaskan kepada akun Anda.', 'recruitment-plugin' ) );
		}

		$allowed_mime_types = [
			'application/pdf',
			'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'image/jpeg',
			'image/png',
		];
		$content = $document['file_content'];
		$mime_type = (string) $document['mime_type'];
		$file_info = finfo_open( FILEINFO_MIME_TYPE );
		if (
			! $file_info
			|| ! in_array( $mime_type, $allowed_mime_types, true )
			|| finfo_buffer( $file_info, $content ) !== $mime_type
			|| 0 === strlen( $content )
			|| 5242880 < strlen( $content )
		) {
			if ( $file_info ) {
				finfo_close( $file_info );
			}
			wp_die( esc_html__( 'Format atau ukuran dokumen tidak valid.', 'recruitment-plugin' ) );
		}
		finfo_close( $file_info );

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Length: ' . strlen( $content ) );
		header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', sanitize_file_name( $document['file_name'] ) ) . '"' );
		header( 'Cache-Control: private, no-store, max-age=0' );
		echo $content;
		exit;
	}

	/**
	 * @param array<string, mixed> $report
	 */
	private function stream_applicant_profile_pdf( array $report, string $filename, bool $department_scope = false ): void {
		if ( ! class_exists( \Dompdf\Dompdf::class ) ) {
			wp_die( esc_html__( 'PDF belum dapat dibuat karena pustaka PDF tidak tersedia.', 'recruitment-plugin' ) );
		}

		$application = is_array( $report['application'] ?? null ) ? $report['application'] : [];
		$profile = is_array( $report['profile'] ?? null ) ? $report['profile'] : [];
		$details = is_array( $profile['profile_details'] ?? null ) ? $profile['profile_details'] : [];
		$escape = static function ( $value ): string {
			return htmlspecialchars( is_scalar( $value ) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		};
		$rows = static function ( array $values ) use ( $escape ): string {
			$html = '';
			foreach ( $values as $label => $value ) {
				$html .= '<tr><th>' . $escape( $label ) . '</th><td>' . ( '' !== (string) $value ? nl2br( $escape( $value ) ) : '—' ) . '</td></tr>';
			}
			return $html;
		};
		$identity = [
			'Nama Lengkap' => $profile['name'] ?? '',
			'Email' => $profile['email'] ?? '',
			'Nomor Telepon' => $profile['phone'] ?? '',
			'Jenis Kelamin' => $profile['gender'] ?? '',
			'Tempat Lahir' => $profile['birth_place'] ?? '',
			'Tanggal Lahir' => $profile['birth_date'] ?? '',
			'Alamat' => $profile['address'] ?? '',
			'Kota' => $profile['city'] ?? '',
			'Provinsi' => $profile['province'] ?? '',
		];
		$education = [
			'Jenjang' => $details['education_level'] ?? $profile['education'] ?? '',
			'Institusi' => $details['institution'] ?? '',
			'Jurusan' => $details['major'] ?? '',
			'IPK / Nilai' => $details['gpa'] ?? '',
			'Tahun Lulus' => $details['graduation_year'] ?? '',
		];

		$html = '<!doctype html><html lang="id"><meta charset="utf-8"><style>
			body{font:11px/1.5 DejaVu Sans,sans-serif;color:#172033}
			h1{font-size:20px;margin:0 0 4px}h2{font-size:14px;color:#17416b;margin:22px 0 8px}
			p.meta{color:#56657a;margin:0 0 16px}
			table{border-collapse:collapse;width:100%;margin:8px 0 18px;page-break-inside:auto}
			tr{page-break-inside:avoid}th,td{border:1px solid #d5dde7;padding:7px;text-align:left;vertical-align:top}
			th{width:28%;background:#f2f5f9}
			</style><body><h1>Profil Pelamar</h1><p class="meta">Lamaran #' . $escape( $application['application_id'] ?? '' ) . ' · ' . $escape( $application['position'] ?? '' ) . ' · ' . $escape( $application['department'] ?? '' ) . '</p>';
		$html .= '<h2>Identitas / Data Diri</h2><table>' . $rows( $identity ) . '</table>';
		$html .= '<h2>Data Lamaran</h2><table>' . $rows(
			[
				'Tanggal Lamaran' => $application['application_date'] ?? '',
				'Posisi' => $application['position'] ?? '',
				'Dealer' => $application['dealer'] ?? '',
				'Departemen' => $application['department'] ?? '',
				'Status' => $application['application_status'] ?? '',
			]
		) . '</table>';
		$html .= '<h2>Pendidikan</h2><table>' . $rows( $education ) . '</table>';

		$experiences = is_array( $details['work_experiences'] ?? null ) ? $details['work_experiences'] : [];
		$html .= '<h2>Pengalaman Kerja</h2><table><thead><tr><th>Perusahaan</th><th>Posisi</th><th>Periode</th><th>Deskripsi</th></tr></thead><tbody>';
		if ( empty( $experiences ) ) {
			$html .= '<tr><td colspan="4">Belum ada data pengalaman kerja.</td></tr>';
		} else {
			foreach ( $experiences as $experience ) {
				if ( is_array( $experience ) ) {
					$html .= '<tr><td>' . $escape( $experience['company'] ?? '' ) . '</td><td>' . $escape( $experience['position'] ?? '' ) . '</td><td>' . $escape( $experience['period'] ?? '' ) . '</td><td>' . $escape( $experience['description'] ?? '' ) . '</td></tr>';
				}
			}
		}
		$html .= '</tbody></table>';
		$html .= '<h2>Hasil Tahapan</h2><table>' . $rows(
			[
				'Nilai Psikotes' => $application['psychotest_score'] ?? '',
				'Hasil Wawancara HR' => $application['hr_interview_result'] ?? '',
				'Rekomendasi HR' => $application['hr_recommendation'] ?? '',
				'Catatan HR' => $application['hr_interview_notes'] ?? '',
				'Hasil Wawancara User' => $application['user_interview_result'] ?? '',
				'Nilai Wawancara User' => $application['user_interview_score'] ?? '',
				'Rekomendasi User' => $application['user_recommendation'] ?? '',
				'Catatan User' => $application['user_interview_notes'] ?? '',
			]
		) . '</table>';

		$answer_rows = '';
		$document_rows = '';
		$profile_documents = is_array( $details['documents'] ?? null ) ? $details['documents'] : [];
		foreach ( $profile_documents as $document_key => $document ) {
			if ( ! is_array( $document ) || '' === (string) ( $document['name'] ?? '' ) || ( $department_scope && 'resume' !== $document_key ) ) {
				continue;
			}
			$document_rows .= '<tr><th>' . $escape( $document_key ) . '</th><td>' . $escape( $document['name'] ) . '</td></tr>';
		}
		foreach ( (array) ( $report['answers'] ?? [] ) as $answer ) {
			if ( is_array( $answer ) ) {
				$value = trim( (string) ( $answer['file_name'] ?? '' ) ) ?: (string) ( $answer['answer_value'] ?? '' );
				$title = (string) ( $answer['field_title'] ?? $answer['field_key'] ?? '' );
				if ( $department_scope && 'file' === strtolower( trim( (string) ( $answer['field_type'] ?? '' ) ) ) ) {
					$document_rows .= '<tr><th>' . $escape( $title ) . '</th><td>' . $escape( $value ) . '</td></tr>';
				} elseif ( ! $department_scope ) {
					$answer_rows .= '<tr><th>' . $escape( $title ) . '</th><td>' . ( '' !== $value ? nl2br( $escape( $value ) ) : '—' ) . '</td></tr>';
				}
			}
		}
		$html .= '<h2>Dokumen Lamaran</h2><table>' . ( '' !== $document_rows ? $document_rows : '<tr><td>Dokumen belum tersedia.</td></tr>' ) . '</table>';
		if ( ! $department_scope ) {
			$html .= '<h2>Jawaban Formulir Lamaran</h2><table>' . ( '' !== $answer_rows ? $answer_rows : '<tr><td>Belum ada jawaban tambahan.</td></tr>' ) . '</table>';
		}
		$html .= '<h2>Riwayat Seleksi</h2><table><thead><tr><th>Tanggal</th><th>Tahap</th><th>Status</th><th>Catatan</th></tr></thead><tbody>';
		$history = (array) ( $report['history'] ?? [] );
		if ( empty( $history ) ) {
			$html .= '<tr><td colspan="4">Riwayat seleksi belum tersedia.</td></tr>';
		} else {
			foreach ( array_reverse( $history ) as $event ) {
				if ( is_array( $event ) ) {
					$html .= '<tr><td>' . $escape( $event['date'] ?? '' ) . '</td><td>' . $escape( $event['stage'] ?? '' ) . '</td><td>' . $escape( $event['status'] ?? '' ) . '</td><td>' . $escape( $event['note'] ?? '' ) . '</td></tr>';
				}
			}
		}
		$html .= '</tbody></table></body></html>';

		$options = new \Dompdf\Options();
		$options->setIsRemoteEnabled( false );
		$dompdf = new \Dompdf\Dompdf( $options );
		$dompdf->loadHtml( $html, 'UTF-8' );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();
		nocache_headers();
		$dompdf->stream( sanitize_file_name( $filename ), [ 'Attachment' => true ] );
		exit;
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

		if ( strlen( $password ) >= 8 && $password === $password_confirmation ) {
			$result = ( new Recruitment_Database() )->create_department_account( $username, $email, $department_id, $password );
			if ( ! empty( $result['success'] ) ) {
				$notice = 'created';
				$mail_sent = wp_mail(
					$email,
					'Undangan akun Recruitment DAW',
					"Akun User Department Anda telah dibuat.\n\n"
						. "Username: " . $username . "\n"
						. "Login: " . recruitment_get_department_login_url() . "\n\n"
						. "Password telah ditetapkan oleh Admin HR. Hubungi Admin HR melalui jalur yang aman untuk mendapatkannya."
				);
				if ( ! $mail_sent ) {
					$notice = 'created_mail_failed';
				}
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
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Hanya Admin HR yang dapat membuat akun HR.', 'recruitment-plugin' ) );
		}

		check_admin_referer( 'daw_create_hr_account' );
		$username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ), true );
		$full_name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$department = sanitize_text_field( wp_unslash( $_POST['hr_department'] ?? '' ) );
		$password = (string) wp_unslash( $_POST['password'] ?? '' );
		$password_confirmation = (string) wp_unslash( $_POST['password_confirmation'] ?? '' );
		$notice = 'invalid';

		if ( '' !== $username && '' !== $full_name && is_email( $email ) && '' !== $department && strlen( $password ) >= 8 && $password === $password_confirmation ) {
			$result = ( new Recruitment_Database() )->create_hr_account( $username, $full_name, $email, $department, $password );
			if ( ! empty( $result['success'] ) ) {
				$notice = 'created';
			} else {
				$error = (string) ( $result['error'] ?? '' );
				$notice = [
					'email_exists' => 'duplicate',
					'schema_not_ready' => 'schema',
					'service_unavailable' => 'unavailable',
				][ $error ] ?? 'invalid';
			}
		}

		wp_safe_redirect( add_query_arg( 'hr_account_notice', $notice, admin_url( 'admin.php?page=recruitment-user-management' ) ) );
		exit;
	}

	public function handle_hr_account_delete(): void {
		if ( ! current_user_can( 'manage_recruitment' ) ) {
			wp_die( esc_html__( 'Hanya Admin HR yang dapat menghapus akun HR.', 'recruitment-plugin' ) );
		}
		$account_id = absint( $_POST['account_id'] ?? 0 );
		check_admin_referer( 'daw_delete_hr_account_' . $account_id );
		$result = ( new Recruitment_Database() )->delete_hr_account( $account_id );
		wp_safe_redirect( add_query_arg( 'notice', ! empty( $result['success'] ) ? 'deleted_hr' : 'not_found', admin_url( 'admin.php?page=recruitment-user-management' ) ) );
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
				wp_set_password( $new_password, get_current_user_id() );
				wp_set_current_user( get_current_user_id() );
				wp_set_auth_cookie( get_current_user_id(), true );
				$notice = 'password_updated';
			} else {
				$error = (string) ( $result['error'] ?? '' );
				$notice = [
					'invalid_current_password' => 'current_password',
					'service_unavailable' => 'unavailable',
				][ $error ] ?? 'invalid';
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=recruitment-hr-profile&password_notice=' . urlencode( $notice ) . '#ganti-password' ) );
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

	public function render_psychotest_settings(): void {
		$this->render_page( 'psychotest-settings' );
	}

	public function render_psychotest_results(): void {
		$this->render_page( 'psychotest-results' );
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
			'recruitment-psychotest-settings',
			'recruitment-psychotest-results',
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
