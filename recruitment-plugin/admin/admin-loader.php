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
		add_action( 'admin_menu', [ $this, 'remove_vacancy_post_type_submenu' ], 99 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'in_admin_header', [ $this, 'render_admin_sidebar' ] );
		add_action( 'admin_notices', [ $this, 'render_vacancy_overview' ] );
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
		add_submenu_page( 'recruitment-dashboard', 'Pengaturan', 'Pengaturan', 'manage_recruitment', 'recruitment-settings', [ $this, 'render_settings' ] );
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
		$status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );
		$stage = sanitize_text_field( wp_unslash( $_POST['stage'] ?? '' ) );
		$note = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
		$database = new Recruitment_Database();
		$normalized_status = strtoupper( trim( $status ) );
		if ( in_array( $normalized_status, [ 'DITERIMA', 'DITOLAK' ], true ) ) {
			$role_access_id = absint( get_user_meta( get_current_user_id(), 'daw_oracle_account_id', true ) );
			$result = $database->save_final_decision(
				(string) $application_id,
				$normalized_status,
				$role_access_id > 0 ? $role_access_id : null
			);
		} else {
			$result = $database->update_application_status( (string) $application_id, $status, $stage, $note );
		}
		if ( empty( $result['success'] ) ) {
			wp_die( esc_html( $result['error'] ?? 'Status lamaran gagal disimpan.' ) );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page' => 'recruitment-applicants',
					'candidate_id' => absint( $_POST['candidate_id'] ?? 0 ),
					'status_updated' => '1',
				],
				admin_url( 'admin.php' )
			)
		);
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
