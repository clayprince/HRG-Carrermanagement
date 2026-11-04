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
        add_action( 'init', [ $this, 'register_hr_role' ] );
        add_action( 'init', [ $this, 'register_user_department_role' ] );
        add_action( 'init', [ $this, 'register_applicant_role' ] );
        add_action( 'wp_login', [ $this, 'record_account_login' ], 10, 2 );
        add_filter( 'wp_authenticate_user', [ $this, 'reject_inactive_account' ] );
        add_filter( 'wp_authenticate_user', [ $this, 'reject_direct_applicant_login' ], 20 );
        add_filter( 'wp_authenticate_user', [ $this, 'reject_direct_employee_login' ], 20 );
        add_action( 'template_redirect', [ $this, 'handle_applicant_account' ] );
        add_action( 'template_redirect', [ $this, 'handle_portal_login' ] );
        add_action( 'admin_init', [ $this, 'validate_oracle_portal_session' ] );
        add_action( 'admin_post_daw_logout', [ $this, 'handle_logout' ] );
        add_action( 'admin_post_nopriv_daw_logout', [ $this, 'handle_logout' ] );
        add_action( 'admin_post_daw_update_hr_profile', [ $this, 'handle_hr_profile_update' ] );
        add_action( 'admin_post_daw_request_hr_email_change', [ $this, 'handle_hr_email_change_request' ] );
        add_action( 'admin_post_daw_confirm_hr_email_change', [ $this, 'handle_hr_email_change_confirmation' ] );
        add_filter( 'logout_redirect', [ $this, 'redirect_portal_logout' ], 10, 3 );
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

    public function register_hr_role(): void {
        $hr_role = get_role( 'daw_hr' );
        if ( ! $hr_role ) {
            $hr_role = add_role( 'daw_hr', 'HR Recruitment', [ 'read' => true ] );
        }

        if ( $hr_role && ! $hr_role->has_cap( 'manage_recruitment' ) ) {
            $hr_role->add_cap( 'manage_recruitment' );
        }

        $administrator = get_role( 'administrator' );
        if ( $administrator && ! $administrator->has_cap( 'manage_recruitment' ) ) {
            $administrator->add_cap( 'manage_recruitment' );
        }
    }

    public function register_applicant_role(): void {
        if ( ! get_role( 'daw_applicant' ) ) {
            add_role( 'daw_applicant', 'Applicant', [ 'read' => true ] );
        }
    }

    public function record_account_login( string $user_login, WP_User $user ): void {
        update_user_meta( $user->ID, 'daw_last_login', current_time( 'mysql' ) );
    }

    public function validate_oracle_portal_session(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user = wp_get_current_user();
        $account_type = (string) get_user_meta( $user->ID, 'daw_oracle_account_type', true );
        $account_id = (string) get_user_meta( $user->ID, 'daw_oracle_account_id', true );
        if ( '' === $account_type || '' === $account_id ) {
            $portal_roles = [ 'daw_hr', 'daw_user_department' ];
            if ( array_intersect( $portal_roles, (array) $user->roles ) ) {
                wp_logout();
                wp_safe_redirect( recruitment_get_public_url( 'login', [ 'login_error' => 'no_access' ] ) );
                exit;
            }
            return;
        }

        $status = ( new Recruitment_Database() )->get_portal_account_status( $account_type, $account_id );
        if ( ! empty( $status['success'] ) ) {
            $expected_role = 'hr' === $account_type ? 'daw_hr' : ( 'user_department' === $account_type ? 'daw_user_department' : '' );
            $expected_capability = 'hr' === $account_type ? 'manage_recruitment' : ( 'user_department' === $account_type ? 'manage_user_department' : '' );
            if ( '' === $expected_role || ! in_array( $expected_role, (array) $user->roles, true ) || ! user_can( $user, $expected_capability ) ) {
                wp_logout();
                wp_safe_redirect( recruitment_get_public_url( 'login', [ 'login_error' => 'no_access' ] ) );
                exit;
            }
            if ( 'ACTIVE' !== (string) $status['status'] ) {
                wp_logout();
                $login_url = 'user_department' === $account_type
                    ? recruitment_get_department_login_url( [ 'login_error' => 'inactive_account' ] )
                    : recruitment_get_public_url( 'login', [ 'target' => 'hr', 'login_error' => 'inactive_account' ] );
                wp_safe_redirect( $login_url );
                exit;
            }
            return;
        }

        wp_logout();
        $login_error = 'account_not_found' === ( $status['error'] ?? '' ) || 'invalid_account' === ( $status['error'] ?? '' )
            ? 'no_access'
            : 'service_unavailable';
        $login_url = 'user_department' === $account_type
            ? recruitment_get_department_login_url( [ 'login_error' => $login_error ] )
            : recruitment_get_public_url( 'login', [ 'target' => 'hr', 'login_error' => $login_error ] );
        wp_safe_redirect( $login_url );
        exit;
    }

    public function handle_logout(): void {
        if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
            wp_die( esc_html__( 'Permintaan logout tidak valid.', 'recruitment-plugin' ), '', [ 'response' => 405 ] );
        }

        check_admin_referer( 'daw_logout' );
        $user = wp_get_current_user();
        $account_type = $user instanceof WP_User
            ? (string) get_user_meta( $user->ID, 'daw_oracle_account_type', true )
            : '';
        $redirect_to = ( 'hr' === $account_type || 'user_department' === $account_type )
            ? recruitment_get_public_url( 'login', [ 'target' => 'hr' ] )
            : wp_validate_redirect(
                esc_url_raw( wp_unslash( $_POST['redirect_to'] ?? '' ) ),
                recruitment_get_public_url( 'login', [ 'target' => 'hr' ] )
            );
        wp_logout();
        wp_safe_redirect( $redirect_to );
        exit;
    }

    public function handle_hr_profile_update(): void {
        $user = wp_get_current_user();
        if ( ! $user instanceof WP_User || 'hr' !== get_user_meta( $user->ID, 'daw_oracle_account_type', true ) ) {
            wp_die( esc_html__( 'Profil tidak dapat diubah.', 'recruitment-plugin' ) );
        }
        check_admin_referer( 'daw_update_hr_profile' );
        $account_id = (string) get_user_meta( $user->ID, 'daw_oracle_account_id', true );
        $username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ), true );
        $email = (string) get_user_meta( $user->ID, 'daw_oracle_email', true );
        $result = ( new Recruitment_Database() )->update_hr_profile( $account_id, $username, $email );
        $notice = ! empty( $result['success'] ) ? 'profile_updated' : ( 'duplicate_account' === ( $result['error'] ?? '' ) ? 'duplicate' : 'profile_failed' );
        if ( ! empty( $result['success'] ) ) {
            wp_update_user( [ 'ID' => $user->ID, 'display_name' => $username ] );
            update_user_meta( $user->ID, 'daw_oracle_username', $username );
        }
        wp_safe_redirect( add_query_arg( 'profile_notice', $notice, recruitment_get_admin_url( 'hr-profile' ) ) );
        exit;
    }

    public function handle_hr_email_change_request(): void {
        $user = wp_get_current_user();
        if ( ! $user instanceof WP_User || 'hr' !== get_user_meta( $user->ID, 'daw_oracle_account_type', true ) ) {
            wp_die( esc_html__( 'Profil tidak dapat diubah.', 'recruitment-plugin' ) );
        }
        check_admin_referer( 'daw_request_hr_email_change' );
        $new_email = strtolower( sanitize_email( wp_unslash( $_POST['new_email'] ?? '' ) ) );
        $password = (string) wp_unslash( $_POST['current_password'] ?? '' );
        $account_id = (string) get_user_meta( $user->ID, 'daw_oracle_account_id', true );
        $verification = ( new Recruitment_Database() )->verify_portal_password( $account_id, 'ADMIN_HR', $password );
        if ( ! is_email( $new_email ) || empty( $verification['success'] ) ) {
            wp_safe_redirect( add_query_arg( 'profile_notice', 'email_invalid', recruitment_get_admin_url( 'hr-profile' ) ) );
            exit;
        }
        $token = bin2hex( random_bytes( 32 ) );
        set_transient( 'daw_hr_email_change_' . hash( 'sha256', $token ), [ 'user_id' => $user->ID, 'account_id' => $account_id, 'email' => $new_email ], 30 * MINUTE_IN_SECONDS );
        $url = add_query_arg( [ 'action' => 'daw_confirm_hr_email_change', 'token' => $token ], admin_url( 'admin-post.php' ) );
        $sent = wp_mail( $new_email, 'Konfirmasi perubahan email akun HR', "Konfirmasi perubahan email Anda melalui tautan berikut dalam 30 menit:\n\n" . $url );
        wp_safe_redirect( add_query_arg( 'profile_notice', $sent ? 'email_sent' : 'email_failed', recruitment_get_admin_url( 'hr-profile' ) ) );
        exit;
    }

    public function handle_hr_email_change_confirmation(): void {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( recruitment_get_public_url( 'login', [ 'target' => 'hr' ] ) );
            exit;
        }
        $token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
        $pending = get_transient( 'daw_hr_email_change_' . hash( 'sha256', $token ) );
        $user = wp_get_current_user();
        if ( ! is_array( $pending ) || ! $user instanceof WP_User || (int) ( $pending['user_id'] ?? 0 ) !== $user->ID ) {
            wp_safe_redirect( add_query_arg( 'profile_notice', 'email_invalid', recruitment_get_admin_url( 'hr-profile' ) ) );
            exit;
        }
        $result = ( new Recruitment_Database() )->update_hr_profile(
            (string) $pending['account_id'],
            (string) get_user_meta( $user->ID, 'daw_oracle_username', true ),
            (string) $pending['email']
        );
        delete_transient( 'daw_hr_email_change_' . hash( 'sha256', $token ) );
        if ( ! empty( $result['success'] ) ) {
            update_user_meta( $user->ID, 'daw_oracle_email', $pending['email'] );
            wp_update_user( [ 'ID' => $user->ID, 'user_email' => $pending['email'] ] );
        }
        wp_safe_redirect( add_query_arg( 'profile_notice', ! empty( $result['success'] ) ? 'email_updated' : 'profile_failed', recruitment_get_admin_url( 'hr-profile' ) ) );
        exit;
    }

    public function reject_inactive_account( $user ) {
        if ( $user instanceof WP_User && '1' === get_user_meta( $user->ID, 'daw_account_inactive', true ) ) {
            return new WP_Error( 'daw_account_inactive', __( 'Akun ini sedang dinonaktifkan. Hubungi administrator.', 'recruitment-plugin' ) );
        }

        return $user;
    }

    public function reject_direct_applicant_login( $user ) {
        if ( $user instanceof WP_User && in_array( 'daw_applicant', (array) $user->roles, true ) ) {
            return new WP_Error( 'daw_applicant_oracle_login_required', __( 'Pelamar harus masuk melalui portal rekrutmen.', 'recruitment-plugin' ) );
        }

        return $user;
    }

    public function reject_direct_employee_login( $user ) {
        if (
            $user instanceof WP_User
            && (
                in_array( 'daw_hr', (array) $user->roles, true )
                || in_array( 'daw_user_department', (array) $user->roles, true )
                || '1' === get_user_meta( $user->ID, 'daw_department_user', true )
                || '' !== (string) get_user_meta( $user->ID, 'daw_oracle_account_type', true )
            )
        ) {
            return new WP_Error( 'daw_oracle_employee_login_required', __( 'Akun karyawan harus masuk melalui portal rekrutmen.', 'recruitment-plugin' ) );
        }

        return $user;
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

            $existing_user_id = email_exists( $email );
            if ( $existing_user_id ) {
                $existing_user = get_user_by( 'id', $existing_user_id );
                if ( ! $existing_user || ! in_array( 'daw_applicant', (array) $existing_user->roles, true ) ) {
                    $this->redirect_to_applicant_screen( 'applicant-register', 'email_exists' );
                }
            }

            $created = ( new Recruitment_Database() )->create_applicant_account( $email, $name, $password );
            if ( empty( $created['success'] ) ) {
                $error = (string) ( $created['error'] ?? 'registration_failed' );
                $this->redirect_to_applicant_screen( 'applicant-register', 'email_exists' === $error ? 'email_exists' : 'registration_failed' );
            }

            if ( ! $this->establish_applicant_session( $email, $name, false ) ) {
                $this->redirect_to_applicant_screen( 'applicant-register', 'registration_failed' );
            }
            wp_safe_redirect( recruitment_get_public_url( 'careers' ) );
            exit;
        }

        if ( 'applicant-login' === $screen ) {
            $login = sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) );
            $password = (string) wp_unslash( $_POST['password'] ?? '' );
            $result = ( new Recruitment_Database() )->authenticate_applicant_account( $login, $password );
            if ( empty( $result['success'] ) ) {
                $error = (string) ( $result['error'] ?? 'invalid_credentials' );
                $this->redirect_to_applicant_screen( 'applicant-login', 'service_unavailable' === $error ? 'account_unavailable' : 'invalid_credentials' );
            }
            $account = $result['account'];
            if ( ! $this->establish_applicant_session( $account['email'], $account['full_name'], ! empty( $_POST['remember'] ) ) ) {
                $this->redirect_to_applicant_screen( 'applicant-login', 'invalid_credentials' );
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

    private function establish_applicant_session( string $email, string $display_name, bool $remember ): bool {
        $user = get_user_by( 'email', $email );

        if ( $user && ! in_array( 'daw_applicant', (array) $user->roles, true ) ) {
            return false;
        }

        if ( ! $user ) {
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
                    'user_pass'    => wp_generate_password( 64, true, true ),
                    'display_name' => $display_name,
                    'role'         => 'daw_applicant',
                ]
            );
            if ( is_wp_error( $user_id ) ) {
                error_log( 'Applicant WordPress session account creation failed: ' . $user_id->get_error_message() );
                return false;
            }
            $user = get_user_by( 'id', $user_id );
        } elseif ( '1' !== get_user_meta( $user->ID, 'daw_oracle_applicant_account', true ) ) {
            $updated = wp_update_user(
                [
                    'ID'        => $user->ID,
                    'user_pass' => wp_generate_password( 64, true, true ),
                ]
            );
            if ( is_wp_error( $updated ) ) {
                error_log( 'Applicant WordPress bridge password rotation failed: ' . $updated->get_error_message() );
                return false;
            }
        }

        if ( ! ( $user instanceof WP_User ) ) {
            error_log( 'Applicant WordPress session account could not be loaded.' );
            return false;
        }

        update_user_meta( $user->ID, 'daw_oracle_applicant_account', '1' );
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, $remember, is_ssl() );
        do_action( 'wp_login', $user->user_login, $user );

        return true;
    }

    private function redirect_to_applicant_screen( string $screen, string $error ): void {
        wp_safe_redirect( recruitment_get_public_url( $screen, [ 'account_error' => $error ] ) );
        exit;
    }

    public function handle_portal_login(): void {
        $requested_screen = sanitize_key( wp_unslash( $_GET['recruitment_page'] ?? '' ) );
        if ( ! in_array( $requested_screen, [ 'portal', 'login', 'forgot-password', 'reset-password' ], true ) ) {
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
            $expected_account_type = 'user_department' === $target ? 'user_department' : 'hr';
            $account_type = (string) get_user_meta( $current_user->ID, 'daw_oracle_account_type', true );
            if ( user_can( $current_user, $capability ) && $expected_account_type === $account_type ) {
                wp_safe_redirect( $dashboard_url );
                exit;
            }
        }

        if ( 'forgot-password' === $requested_screen && 'hr' === $target && 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
            $this->handle_hr_password_reset_request();
            return;
        }

        if ( 'reset-password' === $requested_screen && 'hr' === $target && 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
            $this->handle_hr_password_reset();
            return;
        }

        if ( 'login' !== $requested_screen || 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'daw_admin_portal_login' ) ) {
            $this->redirect_to_portal_login( $target, 'invalid_request' );
        }

        $identifier = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
        $password = (string) wp_unslash( $_POST['password'] ?? '' );
        $throttle_key = $this->get_login_throttle_key( $target, $identifier );
        if ( $this->is_login_throttled( $throttle_key ) ) {
            $this->redirect_to_portal_login( $target, 'login_throttled' );
        }

        $authentication = ( new Recruitment_Database() )->authenticate_portal_account( $target, $identifier, $password );
        if ( empty( $authentication['success'] ) ) {
            $this->record_failed_login( $throttle_key );
            $error = (string) ( $authentication['error'] ?? 'invalid_credentials' );
            $this->redirect_to_portal_login( $target, 'service_unavailable' === $error ? 'service_unavailable' : 'invalid_credentials' );
        }
        delete_transient( $throttle_key );

        if ( ! $this->establish_portal_session( $authentication['account'] ) ) {
            $this->redirect_to_portal_login( $target, 'no_access' );
        }
        if ( ! current_user_can( $capability ) ) {
            wp_logout();
            $this->redirect_to_portal_login( $target, 'no_access' );
        }

        wp_safe_redirect( $dashboard_url );
        exit;
    }

    private function get_login_throttle_key( string $target, string $identifier ): string {
        $ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        return 'daw_login_throttle_' . hash( 'sha256', $target . '|' . strtolower( trim( $identifier ) ) . '|' . $ip_address );
    }

    private function is_login_throttled( string $key ): bool {
        $state = get_transient( $key );
        return is_array( $state ) && ! empty( $state['blocked_until'] ) && time() < (int) $state['blocked_until'];
    }

    private function record_failed_login( string $key ): void {
        $state = get_transient( $key );
        $attempts = is_array( $state ) ? (int) ( $state['attempts'] ?? 0 ) : 0;
        ++$attempts;
        $blocked_until = $attempts >= 5 ? time() + ( 15 * MINUTE_IN_SECONDS ) : 0;
        set_transient( $key, [ 'attempts' => $attempts, 'blocked_until' => $blocked_until ], 15 * MINUTE_IN_SECONDS );
    }

    private function handle_hr_password_reset_request(): void {
        $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'daw_hr_password_reset_request' ) ) {
            wp_safe_redirect( recruitment_get_public_url( 'login', [ 'target' => 'hr', 'forgot_notice' => 'invalid_request' ] ) );
            exit;
        }

        $email = strtolower( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) );
        if ( is_email( $email ) ) {
            $ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
            $rate_key = 'daw_hr_reset_rate_' . hash( 'sha256', $ip_address );
            $attempts = (int) get_transient( $rate_key );

            if ( $attempts < 5 ) {
                set_transient( $rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
                $database = new Recruitment_Database();
                $account = $database->get_active_hr_account_by_email( $email );
                if ( ! empty( $account['success'] ) ) {
                    $token = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
                    $token_hash = hash( 'sha256', $token );
                    $token_key = 'daw_hr_reset_' . $token_hash;
                    $account_token_key = 'daw_hr_reset_account_' . (string) $account['account']['id'];
                    $previous_token_hash = (string) get_transient( $account_token_key );
                    if ( '' !== $previous_token_hash ) {
                        delete_transient( 'daw_hr_reset_' . $previous_token_hash );
                    }
                    set_transient( $token_key, (string) $account['account']['id'], 30 * MINUTE_IN_SECONDS );
                    set_transient( $account_token_key, $token_hash, 30 * MINUTE_IN_SECONDS );
                    $reset_url = recruitment_get_public_url(
                        'reset-password',
                        [ 'target' => 'hr', 'token' => $token ]
                    );
                    $sent = wp_mail(
                        $account['account']['email'],
                        'Permintaan reset password HR',
                        "Kami menerima permintaan untuk mengatur ulang password akun HR Anda.\n\n"
                            . "Gunakan tautan berikut dalam 30 menit. Tautan hanya dapat digunakan satu kali:\n"
                            . $reset_url
                            . "\n\nJika Anda tidak meminta reset, abaikan email ini."
                    );
                    if ( ! $sent ) {
                        delete_transient( $token_key );
                        delete_transient( $account_token_key );
                        error_log( 'HR password reset email could not be sent.' );
                    }
                }
            }
        }

        wp_safe_redirect( recruitment_get_public_url( 'login', [ 'target' => 'hr', 'forgot_notice' => 'submitted' ] ) );
        exit;
    }

    private function handle_hr_password_reset(): void {
        $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
        $notice = 'invalid_token';
        $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
        $password = (string) wp_unslash( $_POST['new_password'] ?? '' );
        $confirmation = (string) wp_unslash( $_POST['password_confirmation'] ?? '' );

        if ( wp_verify_nonce( $nonce, 'daw_hr_password_reset' ) && 8 <= strlen( $password ) && $password === $confirmation && 43 === strlen( $token ) ) {
            $token_hash = hash( 'sha256', $token );
            $token_key = 'daw_hr_reset_' . $token_hash;
            $account_id = get_transient( $token_key );
            $account_token_hash = false !== $account_id
                ? (string) get_transient( 'daw_hr_reset_account_' . (string) $account_id )
                : '';
            if ( false !== $account_id && '' !== $account_token_hash && hash_equals( $account_token_hash, $token_hash ) ) {
                $result = ( new Recruitment_Database() )->reset_hr_password( (string) $account_id, $password );
                if ( ! empty( $result['success'] ) ) {
                    delete_transient( $token_key );
                    delete_transient( 'daw_hr_reset_account_' . (string) $account_id );
                    $this->revoke_oracle_account_sessions( 'hr', (string) $account_id );
                    $notice = 'password_updated';
                } elseif ( 'service_unavailable' === ( $result['error'] ?? '' ) ) {
                    $notice = 'service_unavailable';
                }
            }
        } elseif ( ! wp_verify_nonce( $nonce, 'daw_hr_password_reset' ) ) {
            $notice = 'invalid_request';
        } elseif ( 8 > strlen( $password ) || $password !== $confirmation ) {
            $notice = 'invalid_password';
        }

        wp_safe_redirect( recruitment_get_public_url( 'login', [ 'target' => 'hr', 'reset_notice' => $notice ] ) );
        exit;
    }

    public function redirect_portal_logout( string $redirect_to, string $requested_redirect_to, $user ): string {
        if ( ! $user instanceof WP_User ) {
            return $redirect_to;
        }

        $account_type = (string) get_user_meta( $user->ID, 'daw_oracle_account_type', true );
        if ( 'user_department' === $account_type ) {
            return recruitment_get_department_login_url();
        }
        if ( 'hr' === $account_type ) {
            return recruitment_get_public_url( 'login', [ 'target' => 'hr' ] );
        }

        return $redirect_to;
    }

    private function revoke_oracle_account_sessions( string $account_type, string $account_id ): void {
        $bridge_user_ids = get_users(
            [
                'fields' => 'ID',
                'meta_query' => [
                    [
                        'key' => 'daw_oracle_account_id',
                        'value' => $account_id,
                    ],
                    [
                        'key' => 'daw_oracle_account_type',
                        'value' => $account_type,
                    ],
                ],
            ]
        );

        foreach ( $bridge_user_ids as $bridge_user_id ) {
            WP_Session_Tokens::get_instance( absint( $bridge_user_id ) )->destroy_all();
        }
    }

    private function redirect_to_portal_login( string $target, string $error ): void {
        $login_url = 'user_department' === $target
            ? recruitment_get_department_login_url( [ 'login_error' => $error ] )
            : recruitment_get_public_url( 'login', [ 'target' => 'hr', 'login_error' => $error ] );

        wp_safe_redirect( $login_url );
        exit;
    }

    /**
     * Establish a restricted WordPress session for a verified Oracle employee.
     *
     * @param array{type: string, id: string, email: string, username: string, display_name: string, department_id: string, department: string} $account
     */
    private function establish_portal_session( array $account ): bool {
        $is_hr = 'hr' === $account['type'];
        $role = $is_hr ? 'daw_hr' : 'daw_user_department';
        $prefix = $is_hr ? 'hr' : 'department';
        $user_login = 'daw-oracle-' . $prefix . '-' . sanitize_key( $account['id'] );
        $bridge_email = $prefix . '-' . sanitize_key( $account['id'] ) . '@oracle-bridge.invalid';
        $user = get_user_by( 'login', $user_login );

        if ( $user ) {
            $mapped_type = (string) get_user_meta( $user->ID, 'daw_oracle_account_type', true );
            $mapped_id = (string) get_user_meta( $user->ID, 'daw_oracle_account_id', true );
            if ( $mapped_type !== $account['type'] || $mapped_id !== $account['id'] ) {
                error_log( 'Oracle portal account conflicts with an existing WordPress login.' );
                return false;
            }
            $user->set_role( $role );
        } else {
            $user_id = wp_insert_user(
                [
                    'user_login'   => $user_login,
                    'user_email'   => $bridge_email,
                    'user_pass'    => wp_generate_password( 64, true, true ),
                    'display_name' => $account['display_name'],
                    'role'         => $role,
                ]
            );
            if ( is_wp_error( $user_id ) ) {
                error_log( 'Oracle portal session account creation failed: ' . $user_id->get_error_message() );
                return false;
            }
            $user = get_user_by( 'id', $user_id );
        }

        if ( ! ( $user instanceof WP_User ) ) {
            error_log( 'Oracle portal session account could not be loaded.' );
            return false;
        }

        update_user_meta( $user->ID, 'daw_oracle_account_type', $account['type'] );
        update_user_meta( $user->ID, 'daw_oracle_account_id', $account['id'] );
        update_user_meta( $user->ID, 'daw_oracle_email', $account['email'] );
        update_user_meta( $user->ID, 'daw_oracle_username', $account['username'] );
        if ( 'user_department' === $account['type'] ) {
            update_user_meta( $user->ID, 'daw_department_user', '1' );
            update_user_meta( $user->ID, 'daw_department', $account['department'] );
            update_user_meta( $user->ID, 'daw_department_id', $account['department_id'] );
        }

        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true, is_ssl() );
        do_action( 'wp_login', $user->user_login, $user );

        return true;
    }

    public function filter_admin_login_url( string $login_url, string $redirect = '', bool $force_reauth = false ): string {
        $redirect_query = (string) parse_url( $redirect, PHP_URL_QUERY );
        parse_str( $redirect_query, $query_args );
        $page = sanitize_key( $query_args['page'] ?? '' );
        $hr_pages = [ 'recruitment-dashboard', 'recruitment-vacancies', 'recruitment-applicants', 'recruitment-applications', 'recruitment-hr-interview', 'recruitment-final-decision', 'recruitment-user-interview', 'recruitment-interview-schedule', 'recruitment-user-management', 'recruitment-hr-profile', 'recruitment-form-builder', 'recruitment-psychotest-settings', 'recruitment-question-bank', 'recruitment-settings' ];

        if ( 'daw-user-department' === $page ) {
            return recruitment_get_department_login_url();
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
        if ( in_array( $screen, [ 'applicant-login', 'applicant-register', 'account-confirmation', 'account-profile', 'application-confirmation', 'application-history', 'application-detail', 'application-process', 'application-status', 'tracking-detail', 'interview-booking', 'login', 'forgot-password', 'reset-password' ], true ) && function_exists( 'nocache_headers' ) ) {
            nocache_headers();
        }
        $page_data = [];
        if ( in_array( $screen, [ 'login', 'forgot-password', 'reset-password' ], true ) ) {
            $error_messages = [
                'invalid_request' => 'Sesi login tidak valid. Silakan coba kembali.',
                'invalid_credentials' => 'Email/username atau password tidak sesuai.',
                'login_throttled' => 'Terlalu banyak percobaan login. Silakan coba kembali beberapa menit lagi.',
                'no_access' => 'Akun ini belum memiliki akses ke portal yang dipilih.',
                'inactive_account' => 'Akun ini sedang nonaktif. Hubungi administrator.',
                'service_unavailable' => 'Database akun belum tersedia. Silakan coba kembali nanti.',
            ];
            $error_key = sanitize_key( wp_unslash( $_GET['login_error'] ?? '' ) );
            $page_data['error'] = $error_messages[ $error_key ] ?? '';
            $default_target = $page_id && $page_id === $department_login_page_id ? 'user_department' : 'hr';
            $page_data['target'] = 'user_department' === sanitize_key( wp_unslash( $_GET['target'] ?? $default_target ) ) ? 'user_department' : 'hr';
            $page_data['forgot_notice'] = sanitize_key( wp_unslash( $_GET['forgot_notice'] ?? '' ) );
            $page_data['reset_notice'] = sanitize_key( wp_unslash( $_GET['reset_notice'] ?? '' ) );
        }
        if ( 'application-form' === $screen && function_exists( 'nocache_headers' ) ) {
            nocache_headers();
        }

        ob_start();
        ( new Recruitment_Router() )->render( $screen, $page_data );
        return ob_get_clean();
    }

}
