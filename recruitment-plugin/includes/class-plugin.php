<?php

/**
 * Recruitment_Plugin — main plugin bootstrap / controller.
 *
 * Tanggung jawab:
 * - Initialize public module
 * - Initialize admin module
 * - Register hooks
 */
class Recruitment_Plugin {

    /**
     * Plugin info used across the system.
     *
     * @return array<string, string>
     */
    public function get_info(): array {
        return [
            'name'      => 'Recruitment Plugin',
            'status'    => 'Active',
            'version'   => RECRUITMENT_PLUGIN_VERSION,
            'site_url'  => recruitment_get_plugin_url(),
            'shortcode' => '[recruitment_careers]',
        ];
    }

    /**
     * Bootstrap the plugin (hooks, loaders, etc.).
     * Called from recruitment-plugin.php on init.
     */
    public function init(): void {
        add_action( 'init', [ $this, 'register_content' ] );
        add_action( 'init', [ $this, 'register_portal_pages' ] );
        add_action( 'init', [ $this, 'register_user_department_role' ] );
        add_action( 'init', [ $this, 'register_applicant_role' ] );
        add_action( 'template_redirect', [ $this, 'handle_applicant_account' ] );
        add_action( 'template_redirect', [ $this, 'handle_portal_login' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_assets' ] );
        add_filter( 'render_block', [ $this, 'remove_theme_chrome' ], 10, 2 );
        add_filter( 'the_content', [ $this, 'render_front_page' ] );
        add_filter( 'login_url', [ $this, 'filter_admin_login_url' ], 10, 3 );

        add_shortcode( 'recruitment_careers', [ $this, 'render_careers_shortcode' ] );

        ( new Recruitment_Admin() )->init();
    }

    public function register_user_department_role(): void {
        $department_role = get_role( 'daw_user_department' );
        if ( ! $department_role ) {
            $department_role = add_role( 'daw_user_department', 'User Department', [ 'read' => true ] );
        }

        if ( $department_role && ! $department_role->has_cap( 'manage_user_department' ) ) {
            $department_role->add_cap( 'manage_user_department' );
        }

        $administrator = get_role( 'administrator' );
        if ( $administrator && ! $administrator->has_cap( 'manage_user_department' ) ) {
            $administrator->add_cap( 'manage_user_department' );
        }
    }

    public function register_applicant_role(): void {
        if ( ! get_role( 'daw_applicant' ) ) {
            add_role( 'daw_applicant', 'Applicant', [ 'read' => true ] );
        }
    }

    public function handle_applicant_account(): void {
        $screen = sanitize_key( wp_unslash( $_GET['recruitment_page'] ?? '' ) );
        if ( ! in_array( $screen, [ 'applicant-login', 'applicant-register', 'account-profile' ], true ) || 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
            return;
        }

        $preview_submission = recruitment_is_ui_preview()
            || '1' === sanitize_text_field( wp_unslash( $_POST['daw_ui_preview'] ?? '' ) );
        if ( $preview_submission ) {
            wp_safe_redirect( recruitment_get_public_url( $screen, [ 'preview_notice' => 'read_only' ] ) );
            exit;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['applicant_account_nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'applicant_account_' . $screen ) ) {
            $this->redirect_to_applicant_screen( 'applicant-register' === $screen ? 'applicant-register' : $screen, 'invalid_request' );
        }

        if ( 'applicant-register' === $screen ) {
            $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
            $name = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
            $password = (string) wp_unslash( $_POST['password'] ?? '' );
            $password_confirmation = (string) wp_unslash( $_POST['password_confirmation'] ?? '' );

            if ( '' === $name || ! is_email( $email ) ) {
                $this->redirect_to_applicant_screen( 'applicant-register', 'invalid_registration' );
            }
            if ( email_exists( $email ) ) {
                $this->redirect_to_applicant_screen( 'applicant-register', 'email_exists' );
            }
            if ( strlen( $password ) < 10 || $password !== $password_confirmation ) {
                $this->redirect_to_applicant_screen( 'applicant-register', 'password_mismatch' );
            }

            $user_login = sanitize_user( strstr( $email, '@', true ), true );
            if ( '' === $user_login ) {
                $user_login = 'applicant';
            }
            $base_login = $user_login;
            while ( username_exists( $user_login ) ) {
                $user_login = $base_login . '-' . strtolower( wp_generate_password( 5, false, false ) );
            }

            $user_id = wp_insert_user(
                [
                    'user_login'   => $user_login,
                    'user_email'   => $email,
                    'user_pass'    => $password,
                    'display_name' => $name,
                    'role'         => 'daw_applicant',
                ]
            );
            if ( is_wp_error( $user_id ) ) {
                error_log( 'Applicant account creation failed: ' . $user_id->get_error_message() );
                $this->redirect_to_applicant_screen( 'applicant-register', 'registration_failed' );
            }

            wp_set_current_user( $user_id );
            wp_set_auth_cookie( $user_id, is_ssl() );
            wp_safe_redirect( recruitment_get_public_url( 'careers' ) );
            exit;
        }

        if ( 'applicant-login' === $screen ) {
            $login = sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) );
            $password = (string) wp_unslash( $_POST['password'] ?? '' );
            $candidate_user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
            if ( $candidate_user && ! in_array( 'daw_applicant', (array) $candidate_user->roles, true ) ) {
                $this->redirect_to_applicant_screen( 'applicant-login', 'invalid_credentials' );
            }
            $user = wp_signon(
                [
                    'user_login'    => $candidate_user ? $candidate_user->user_login : $login,
                    'user_password' => $password,
                    'remember'      => ! empty( $_POST['remember'] ),
                ],
                is_ssl()
            );

            if ( is_wp_error( $user ) ) {
                $this->redirect_to_applicant_screen( 'applicant-login', 'invalid_credentials' );
            }
            if ( ! in_array( 'daw_applicant', (array) $user->roles, true ) ) {
                $this->redirect_to_applicant_screen( 'applicant-login', 'applicant_account_required' );
            }

            wp_safe_redirect( recruitment_get_public_url( 'account-confirmation', [ 'login_success' => '1' ] ) );
            exit;
        }

        if ( ! recruitment_is_applicant_user() ) {
            $this->redirect_to_applicant_screen( 'applicant-login', 'applicant_account_required' );
        }

        $profile_fields = [ 'phone', 'birth_date', 'birth_place', 'address', 'gender', 'city', 'province', 'education_level', 'institution', 'major', 'graduation_year', 'gpa', 'certificate', 'company', 'position', 'work_period' ];
        $display_name = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
        if ( '' !== $display_name ) {
            $updated = wp_update_user( [ 'ID' => get_current_user_id(), 'display_name' => $display_name ] );
            if ( is_wp_error( $updated ) ) {
                error_log( 'Applicant profile update failed: ' . $updated->get_error_message() );
                $this->redirect_to_applicant_screen( 'account-profile', 'profile_update_failed' );
            }
        }
        foreach ( $profile_fields as $field ) {
            update_user_meta( get_current_user_id(), 'daw_applicant_' . $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ?? '' ) ) );
        }

        wp_safe_redirect( recruitment_get_public_url( 'account-profile', [ 'profile_saved' => '1' ] ) );
        exit;
    }

    private function redirect_to_applicant_screen( string $screen, string $error ): void {
        wp_safe_redirect( recruitment_get_public_url( $screen, [ 'account_error' => $error ] ) );
        exit;
    }

    public function handle_portal_login(): void {
        $requested_screen = sanitize_key( wp_unslash( $_GET['recruitment_page'] ?? '' ) );
        if ( ! in_array( $requested_screen, [ 'portal', 'login' ], true ) ) {
            return;
        }

        $submitted_target = 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) )
            ? ( $_POST['target'] ?? '' )
            : ( $_GET['target'] ?? '' );
        $target = 'user_department' === sanitize_key( wp_unslash( $submitted_target ) ) ? 'user_department' : 'hr';
        $capability = 'user_department' === $target ? 'manage_user_department' : 'manage_recruitment';
        $dashboard_url = 'user_department' === $target
            ? admin_url( 'admin.php?page=daw-user-department' )
            : admin_url( 'admin.php?page=recruitment-dashboard' );

        if ( is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            if ( user_can( $current_user, $capability ) ) {
                wp_safe_redirect( $dashboard_url );
                exit;
            }
        }

        if ( 'login' !== $requested_screen || 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'daw_admin_portal_login' ) ) {
            $this->redirect_to_portal_login( $target, 'invalid_request' );
        }

        $user = wp_signon(
            [
                'user_login'    => sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) ),
                'user_password' => (string) wp_unslash( $_POST['password'] ?? '' ),
                'remember'      => true,
            ],
            is_ssl()
        );

        if ( is_wp_error( $user ) ) {
            $this->redirect_to_portal_login( $target, 'invalid_credentials' );
        }

        if ( ! user_can( $user, $capability ) ) {
            wp_logout();
            $this->redirect_to_portal_login( $target, 'no_access' );
        }

        wp_safe_redirect( $dashboard_url );
        exit;
    }

    private function redirect_to_portal_login( string $target, string $error ): void {
		$login_url = recruitment_get_public_url( 'portal' );
		if ( 'user_department' === $target ) {
			$department_page_id = absint( get_option( 'recruitment_department_login_page_id', 0 ) );
			if ( $department_page_id ) {
				$login_url = get_permalink( $department_page_id );
			}
		}

        wp_safe_redirect(
            add_query_arg(
				[
                    'recruitment_page' => 'login',
                    'target' => $target,
                    'login_error' => $error,
                ],
				$login_url
            )
        );
        exit;
    }

    public function filter_admin_login_url( string $login_url, string $redirect = '', bool $force_reauth = false ): string {
        $redirect_query = (string) parse_url( $redirect, PHP_URL_QUERY );
        parse_str( $redirect_query, $query_args );
        $page = sanitize_key( $query_args['page'] ?? '' );
        $hr_pages = [ 'recruitment-dashboard', 'recruitment-vacancies', 'recruitment-applicants', 'recruitment-applications', 'recruitment-user-interview', 'recruitment-form-builder', 'recruitment-psychotest-settings', 'recruitment-question-bank', 'recruitment-settings' ];

        if ( 'daw-user-department' === $page ) {
            return recruitment_get_public_url( 'portal', [ 'recruitment_page' => 'login', 'target' => 'user_department' ] );
        }

        if ( in_array( $page, $hr_pages, true ) ) {
            return recruitment_get_public_url( 'portal', [ 'recruitment_page' => 'login', 'target' => 'hr' ] );
        }

        return $login_url;
    }

    public function remove_theme_chrome( string $block_content, array $block ): string {
        if ( is_admin() || ! is_page() ) {
            return $block_content;
        }

        if ( ! has_shortcode( get_post_field( 'post_content', get_queried_object_id() ), 'recruitment_careers' ) ) {
            return $block_content;
        }

        if (
            'core/paragraph' === ( $block['blockName'] ?? '' ) &&
            '' === trim( wp_strip_all_tags( $block_content ) )
        ) {
            return '';
        }

        if (
            'core/post-title' === ( $block['blockName'] ?? '' ) ||
            (
                'core/template-part' === ( $block['blockName'] ?? '' ) &&
                in_array( $block['attrs']['slug'] ?? '', [ 'header', 'footer' ], true )
            )
        ) {
            return '';
        }

        return $block_content;
    }

    public function render_front_page( string $content ): string {
        if ( is_admin() || ! is_front_page() || ! is_main_query() || ! in_the_loop() ) {
            return $content;
        }

        return do_shortcode( '[recruitment_careers]' );
    }

    public function register_content(): void {
        register_post_type(
            'daw_vacancy',
            [
                'labels'       => [ 'name' => 'Lowongan', 'singular_name' => 'Lowongan' ],
                'public'       => true,
                'show_in_rest' => true,
                'supports'     => [ 'title', 'editor', 'excerpt' ],
                'menu_icon'    => 'dashicons-businessperson',
                'show_in_menu' => 'recruitment-dashboard',
                'rewrite'      => [ 'slug' => 'lowongan' ],
            ]
        );
    }

    public function register_portal_pages(): void {
        $this->ensure_portal_page( 'recruitment_public_page_id', 'public-site', 'Public Site' );
        $this->ensure_portal_page( 'recruitment_admin_page_id', 'admin-site', 'Admin Site' );
		$this->ensure_portal_page( 'recruitment_department_login_page_id', 'user-department', 'User Department' );
    }

    private function ensure_portal_page( string $option_name, string $slug, string $title ): void {
        $page_id = absint( get_option( $option_name, 0 ) );
        $page = $page_id ? get_post( $page_id ) : null;

        if ( $page && 'page' === $page->post_type ) {
            return;
        }

        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( $page ) {
            update_option( $option_name, absint( $page->ID ) );
            return;
        }

        $page_id = wp_insert_post(
            [
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => $title,
                'post_name' => $slug,
                'post_content' => '[recruitment_careers]',
            ],
            true
        );

        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( $option_name, absint( $page_id ) );
        }
    }

    public function enqueue_public_assets(): void {
        $public_css = recruitment_get_plugin_path( 'assets/css/public.css' );
        $public_js  = recruitment_get_plugin_path( 'assets/js/public.js' );
        wp_enqueue_style( 'recruitment-public', recruitment_get_plugin_url( 'assets/css/public.css' ), [], file_exists( $public_css ) ? filemtime( $public_css ) : RECRUITMENT_PLUGIN_VERSION );
        wp_enqueue_script( 'recruitment-public', recruitment_get_plugin_url( 'assets/js/public.js' ), [], file_exists( $public_js ) ? filemtime( $public_js ) : RECRUITMENT_PLUGIN_VERSION, true );

        $department_login_page_id = absint( get_option( 'recruitment_department_login_page_id', 0 ) );
        $is_department_login_page = $department_login_page_id && is_page( $department_login_page_id );
        if ( in_array( sanitize_key( $_GET['recruitment_page'] ?? '' ), [ 'login', 'portal' ], true ) || $is_department_login_page ) {
            $admin_css = recruitment_get_plugin_path( 'assets/css/admin.css' );
            wp_enqueue_style( 'recruitment-admin-login', recruitment_get_plugin_url( 'assets/css/admin.css' ), [ 'recruitment-public' ], file_exists( $admin_css ) ? filemtime( $admin_css ) : RECRUITMENT_PLUGIN_VERSION );
        }
    }

    public function render_careers_shortcode(): string {
        $page_id = get_queried_object_id();
        $public_page_id = absint( get_option( 'recruitment_public_page_id', 0 ) );
        $admin_page_id = absint( get_option( 'recruitment_admin_page_id', 0 ) );
		$department_login_page_id = absint( get_option( 'recruitment_department_login_page_id', 0 ) );
        if ( $page_id && ! $public_page_id && $page_id !== $admin_page_id ) {
            update_option( 'recruitment_public_page_id', absint( $page_id ) );
        }

        $requested_screen = sanitize_key( $_GET['recruitment_page'] ?? 'careers' );
        if ( $page_id && $page_id === $admin_page_id && ! isset( $_GET['recruitment_page'] ) ) {
            $requested_screen = 'portal';
        }
		if ( $page_id && $page_id === $department_login_page_id && ! isset( $_GET['recruitment_page'] ) ) {
			$requested_screen = 'login';
		}
        $screen = [
            'application' => 'application-form',
            'tracking' => 'application-history',
        ][ $requested_screen ] ?? $requested_screen;
        if ( in_array( $screen, [ 'applicant-login', 'applicant-register', 'account-confirmation', 'account-profile', 'application-confirmation', 'application-history', 'application-detail', 'application-process', 'application-status', 'tracking-detail', 'interview-booking' ], true ) && function_exists( 'nocache_headers' ) ) {
            nocache_headers();
        }
        $page_data = [];
        if ( 'login' === $screen ) {
            $error_messages = [
                'invalid_request' => 'Sesi login tidak valid. Silakan coba kembali.',
                'invalid_credentials' => 'Email/username atau password tidak sesuai.',
                'no_access' => 'Akun ini belum memiliki akses ke portal yang dipilih.',
            ];
            $error_key = sanitize_key( wp_unslash( $_GET['login_error'] ?? '' ) );
            $page_data['error'] = $error_messages[ $error_key ] ?? '';
            $default_target = $page_id && $page_id === $department_login_page_id ? 'user_department' : 'hr';
            $page_data['target'] = 'user_department' === sanitize_key( wp_unslash( $_GET['target'] ?? $default_target ) ) ? 'user_department' : 'hr';
        }
        if ( 'application-form' === $screen && function_exists( 'nocache_headers' ) ) {
            nocache_headers();
        }

        ob_start();
        ( new Recruitment_Router() )->render( $screen, $page_data );
        return ob_get_clean();
    }

}
