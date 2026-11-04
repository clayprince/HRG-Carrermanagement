<?php

// Backward-compatible entry point. New code must use routes/class-router.php.
require_once dirname( __DIR__ ) . '/routes/class-router.php';
if ( false ) {
// Legacy class body retained for source compatibility.

/**
 * Recruitment_Router — handles URL routing and page rendering.
 *
 * Maps page slugs to their template files under public/pages/ or admin/pages/,
 * passes data to templates, and guards protected admin pages.
 *
 * Contoh URL yang didukung:
 *   /?page=careers      → public/pages/careers.php
 *   /?page=login        → admin/pages/login.php
 *   /?page=dashboard    → admin/pages/dashboard.php
 *   /?page=applicants   → admin/pages/applicants.php
 */
class Recruitment_Router {

    /**
     * Map of page slugs to template paths (relative to plugin root).
     */
    private const ROUTES = [
        // Public area
        'careers'            => 'public/pages/careers.php',
        'job-detail'         => 'public/pages/job-detail.php',
        'application-form'   => 'public/pages/application-form.php',
        'application-status' => 'public/pages/application-status.php',
        // Admin area
        'login'              => 'admin/pages/login.php',
        'dashboard'          => 'admin/pages/dashboard.php',
        'vacancies'          => 'admin/pages/vacancies.php',
        'applicants'         => 'admin/pages/applicants.php',
        'applications'       => 'admin/pages/applications.php',
        'settings'           => 'admin/pages/settings.php',
    ];

    /**
     * Pages that require an authenticated session.
     */
    private const PROTECTED = [ 'dashboard', 'vacancies', 'applicants', 'applications', 'settings' ];

    /**
     * Render a page by slug.
     *
     * @param string               $page Requested page slug.
     * @param array<string, mixed> $data Data to extract into the template scope.
     */
    public function render( string $page, array $data = [] ): void {
        if ( ! defined( 'ABSPATH' ) ) {
            http_response_code( 403 );
            exit;
        }

        $slug = array_key_exists( $page, self::ROUTES ) ? $page : 'careers';

        // Guard protected admin pages.
        if ( in_array( $slug, self::PROTECTED, true ) && ! is_user_logged_in() ) {
            header( 'Location: ?page=login' );
            exit;
        }

        // Inject plugin info for admin pages.
        if ( $slug === 'applicants' ) {
            $data['plugin_info'] = ( new Recruitment_Plugin() )->get_info();
        }

        $data['page'] = $slug;
        extract( $data, EXTR_SKIP );

        $template = __DIR__ . '/../' . self::ROUTES[ $slug ];
        require $template;
    }
}
}
