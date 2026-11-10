<?php

/**
 * Central route registry and page renderer.
 */
class Recruitment_Router {

    /** @var array<string, string> */
    private const ROUTES = [
        'careers'            => 'public/pages/careers.php',
        'job-detail'         => 'public/pages/job-detail.php',
        'application-form'   => 'public/pages/application-form.php',
        'application-confirmation' => 'public/pages/application-confirmation.php',
        'applicant-login'    => 'public/pages/applicant-login.php',
        'applicant-register' => 'public/pages/applicant-register.php',
        'applicant-dashboard' => 'public/pages/applicant-dashboard.php',
        'account-confirmation' => 'public/pages/account-confirmation.php',
        'account-profile'    => 'public/pages/account-profile.php',
        'applicant-file'     => 'public/pages/account-profile.php',
        'application-process' => 'public/pages/application-process.php',
        'psych-test'         => 'public/pages/psychological-test.php',
        'interview-booking'  => 'public/pages/interview-booking.php',
        'psychotest'         => 'public/pages/psychotest-home.php',
        'psychotest-preparation' => 'public/pages/psychotest-preparation.php',
        'psychotest-device'  => 'public/pages/psychotest-device.php',
        'psychotest-consent' => 'public/pages/psychotest-consent.php',
        'psychotest-identity' => 'public/pages/psychotest-identity.php',
        'psych-iq'           => 'public/pages/iq-assessment.php',
        'psych-pauli'        => 'public/pages/pauli-assessment.php',
        'psych-personality'  => 'public/pages/personality-assessment.php',
        'tracking-detail'    => 'public/pages/application-process.php',
        'login'              => 'admin/pages/login.php',
        'forgot-password'    => 'admin/pages/login.php',
        'reset-password'     => 'admin/pages/login.php',
        'dashboard'          => 'admin/pages/dashboard.php',
        'vacancies'          => 'admin/pages/vacancies.php',
        'applicants'         => 'admin/pages/applicants.php',
        'applications'       => 'admin/pages/applications.php',
        'hr-interview'       => 'admin/pages/hr-interview.php',
        'final-decision'     => 'admin/pages/final-decision.php',
        'user-interview'     => 'admin/pages/user-interview.php',
        'user-department'    => 'admin/pages/user-department.php',
        'interview-schedule' => 'admin/pages/interview-schedule.php',
        'user-management'    => 'admin/pages/user-management-oracle.php',
        'hr-profile'         => 'admin/pages/hr-profile.php',
        'psychotest-settings' => 'admin/pages/psychotest-settings.php',
        'psychotest-results' => 'admin/pages/psychotest-results.php',
        'question-bank'      => 'admin/pages/question-bank.php',
        'settings'           => 'admin/pages/settings.php',
    ];

    /** @var string[] */
    private const PROTECTED = [
        'dashboard',
        'vacancies',
        'applicants',
        'applications',
        'hr-interview',
        'final-decision',
        'user-interview',
        'user-department',
        'interview-schedule',
        'user-management',
        'hr-profile',
        'psychotest-settings',
        'psychotest-results',
        'question-bank',
        'settings',
    ];

    /** @var string[] */
    private const APPLICANT_PROTECTED = [
        'applicant-dashboard',
        'account-confirmation',
        'account-profile',
        'applicant-file',
        'application-form',
        'application-confirmation',
        'application-process',
        'tracking-detail',
        'interview-booking',
        'psych-test',
        'psychotest',
        'psychotest-preparation',
        'psychotest-device',
        'psychotest-consent',
        'psychotest-identity',
        'psych-iq',
        'psych-pauli',
        'psych-personality',
    ];

    /**
     * @param array<string, mixed> $data
     */
    public function render( string $page, array $data = [] ): void {
        $slug = array_key_exists( $page, self::ROUTES ) ? $page : 'careers';

        $sandbox_preview = in_array( $slug, [ 'user-interview', 'interview-schedule', 'vacancies' ], true )
            && defined( 'RECRUITMENT_SANDBOX' )
            && RECRUITMENT_SANDBOX
            && '1' === sanitize_text_field( wp_unslash( $_GET['daw_ui_preview'] ?? '' ) );

        $applicant_preview = recruitment_is_ui_preview();

        if ( in_array( $slug, self::APPLICANT_PROTECTED, true ) && ! $applicant_preview && ! recruitment_is_applicant_user() ) {
            $is_logged_in = function_exists( 'is_user_logged_in' ) && is_user_logged_in();
            $redirect_args = [
                'account_error' => $is_logged_in ? 'applicant_account_required' : '',
                'redirect_to' => in_array( $slug, [ 'application-form', 'account-profile' ], true ) ? $slug : 'account-profile',
            ];
            if ( 'application-form' === $slug ) {
                $redirect_args['job_id'] = absint( $_GET['job_id'] ?? 0 );
            }
            wp_safe_redirect( recruitment_get_public_url( 'applicant-login', $redirect_args ) );
            exit;
        }

        if ( in_array( $slug, self::PROTECTED, true ) && ! $sandbox_preview ) {
            if ( ! is_user_logged_in() ) {
                $redirect_url = 'user-department' === $slug
                    ? admin_url( 'admin.php?page=daw-user-department' )
                    : recruitment_get_admin_url( $slug );
                wp_safe_redirect( wp_login_url( $redirect_url ) );
                exit;
            }

            $capability = 'user-department' === $slug ? 'manage_user_department' : 'manage_recruitment';
            if ( ! current_user_can( $capability ) ) {
                wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengakses halaman ini.', 'recruitment-plugin' ) );
            }
        }

        if ( 'applicants' === $slug ) {
            $data['plugin_info'] = ( new Recruitment_Plugin() )->get_info();
        }

        $data['page'] = $slug;
        extract( $data, EXTR_SKIP );
        require recruitment_get_plugin_path( self::ROUTES[ $slug ] );
    }
}