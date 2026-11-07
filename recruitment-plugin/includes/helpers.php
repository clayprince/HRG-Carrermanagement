<?php

/**
 * helpers.php — helper functions and WordPress mock (sandbox only).
 *
 * Berisi:
 * 1. Helper functions yang dipakai lintas module.
 * 2. Mock WordPress functions agar sandbox dapat berjalan tanpa WordPress.
 *    Hapus bagian mock ini saat dijalankan di WordPress asli.
 */

// ---------------------------------------------------------------------------
// Plugin helper functions
// ---------------------------------------------------------------------------

if ( ! function_exists( 'get_posts' ) ) {
    function get_posts( array $args = [] ): array {
        return [];
    }
}

if ( ! function_exists( 'get_post' ) ) {
    function get_post( $post_id = null ) {
        return null;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $value ): string {
        return trim( strip_tags( (string) $value ) );
    }
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $value ): string {
        return trim( strip_tags( (string) $value ) );
    }
}

if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $value ): string {
        return filter_var( trim( (string) $value ), FILTER_SANITIZE_EMAIL ) ?: '';
    }
}

if ( ! function_exists( 'is_email' ) ) {
    function is_email( $value ): bool {
        return false !== filter_var( (string) $value, FILTER_VALIDATE_EMAIL );
    }
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
    function sanitize_file_name( $value ): string {
        return preg_replace( '/[^A-Za-z0-9._-]/', '-', basename( (string) $value ) );
    }
}

if ( ! function_exists( 'sanitize_mime_type' ) ) {
    function sanitize_mime_type( $value ): string {
        return preg_replace( '/[^A-Za-z0-9.+-]/', '', (string) $value );
    }
}

if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) {
        return is_string( $value ) ? stripslashes( $value ) : $value;
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( string $type = 'mysql' ): string {
        return 'mysql' === $type ? date( 'Y-m-d H:i:s' ) : (string) time();
    }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( string $url ): string {
        return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $value ): string {
        return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $value ): string {
        return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_textarea' ) ) {
    function esc_textarea( $value ): string {
        return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'wp_get_current_user' ) && ! defined( 'WPINC' ) ) {
    function wp_get_current_user(): object {
        return (object) [ 'ID' => 0, 'display_name' => 'Preview Applicant', 'user_email' => 'applicant@example.com', 'roles' => [] ];
    }
}

if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id(): int {
        return (int) ( wp_get_current_user()->ID ?? 0 );
    }
}

if ( ! function_exists( 'get_user_meta' ) ) {
    function get_user_meta( int $user_id, string $key = '', bool $single = false ) {
        return $single ? '' : [];
    }
}

if ( ! function_exists( 'delete_user_meta' ) ) {
    function delete_user_meta( int $user_id, string $meta_key ): bool {
        return false;
    }
}

if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $value ): string {
        return strip_tags( (string) $value, '<a><br><em><strong><p><ul><ol><li>' );
    }
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
    function wp_nonce_field( string $action = '-1', string $name = '_wpnonce' ): void {
        echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="sandbox-nonce">';
    }
}

if ( ! function_exists( 'wp_verify_nonce' ) && ! defined( 'WPINC' ) ) {
    function wp_verify_nonce( $nonce, string $action = '-1' ): bool {
        return (string) $nonce === 'sandbox-nonce';
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $value ): string {
        return (string) json_encode( $value );
    }
}

if ( ! function_exists( 'wp_safe_redirect' ) && ! defined( 'WPINC' ) ) {
    function wp_safe_redirect( string $location, int $status = 302 ): void {
        if ( ob_get_level() ) {
            ob_end_clean();
        }
        header( 'Location: ' . $location, true, $status );
    }
}

if ( ! function_exists( 'absint' ) ) {
    function absint( $value ): int {
        return abs( (int) $value );
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $value ): string {
        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
    }
}

if ( ! function_exists( 'sanitize_title' ) ) {
    function sanitize_title( $value ): string {
        return sanitize_key( str_replace( ' ', '-', (string) $value ) );
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( string $option, $default = false ) {
        return $default;
    }
}

if ( ! function_exists( 'get_page_by_path' ) ) {
    function get_page_by_path( string $path ) {
        return null;
    }
}

if ( ! function_exists( 'get_permalink' ) ) {
    function get_permalink( $post = null ): string {
        return home_url( '/' );
    }
}

if ( ! function_exists( 'home_url' ) ) {
    function home_url( string $path = '/' ): string {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
        $base_path = preg_replace( '#/[^/]+$#', '', parse_url( $request_uri, PHP_URL_PATH ) ?: '/' );
        $base_path = '/' === $base_path ? '' : $base_path;
        return 'http://' . $host . $base_path . '/' . ltrim( $path, '/' );
    }
}

if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( string $path = '' ): string {
        return home_url( $path );
    }
}

if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( $key, $value = '', string $url = '' ): string {
        if ( is_array( $key ) ) {
            $args = $key;
            $url = (string) $value;
        } else {
            $args = [ $key => $value ];
        }

        $separator = str_contains( $url, '?' ) ? '&' : '?';
        return $url . $separator . http_build_query( $args );
    }
}

if ( ! function_exists( 'selected' ) ) {
    function selected( $selected, $current, bool $echo = true ): string {
        $attribute = (string) $selected === (string) $current ? 'selected="selected"' : '';
        if ( $echo ) {
            echo $attribute;
        }
        return $attribute;
    }
}

function recruitment_get_plugin_url( string $path = '' ): string {
    $base_url = defined( 'RECRUITMENT_STANDALONE_PLUGIN_URL' )
        ? RECRUITMENT_STANDALONE_PLUGIN_URL
        : RECRUITMENT_PLUGIN_URL;
    return $base_url . ltrim( $path, '/' );
}

function recruitment_get_plugin_path( string $path = '' ): string {
    return RECRUITMENT_PLUGIN_PATH . ltrim( $path, '/' );
}

/**
 * Get the URL to a specific public application page.
 *
 * @param string $slug E.g. 'careers', 'job-detail', 'application-form'.
 */
function recruitment_get_application_url( string $slug = 'careers', array $args = [] ): string {
    $page = get_page_by_path( 'recruitment-' . sanitize_title( $slug ) );
    $url  = $page ? get_permalink( $page ) : home_url( '/' );

    return add_query_arg( $args, $url );
}

function recruitment_get_public_url( string $screen = 'careers', array $args = [] ): string {
    if ( defined( 'RECRUITMENT_STANDALONE_FRONT_CONTROLLER' ) ) {
        if ( recruitment_is_ui_preview() ) {
            $args = array_merge( [ 'daw_ui_preview' => '1' ], $args );
        }

        return add_query_arg(
            array_merge( [ 'recruitment_page' => sanitize_key( $screen ) ], $args ),
            RECRUITMENT_STANDALONE_FRONT_CONTROLLER
        );
    }

    $page_id = absint( get_option( 'recruitment_public_page_id', 0 ) );
    if ( ! $page_id && function_exists( 'get_queried_object_id' ) ) {
        $page_id = absint( get_queried_object_id() );
    }
    $base_url = $page_id ? get_permalink( $page_id ) : home_url( '/' );
    if ( recruitment_is_ui_preview() ) {
        $args = array_merge( [ 'daw_ui_preview' => '1' ], $args );
    }

    return add_query_arg( array_merge( [ 'recruitment_page' => sanitize_key( $screen ) ], $args ), $base_url );
}

function recruitment_get_department_login_url( array $args = [] ): string {
    $page_id = absint( get_option( 'recruitment_department_login_page_id', 0 ) );
    $base_url = $page_id ? get_permalink( $page_id ) : recruitment_get_public_url( 'portal' );

    return add_query_arg(
        array_merge(
            [
                'recruitment_page' => 'login',
                'target' => 'user_department',
            ],
            $args
        ),
        $base_url
    );
}

function recruitment_is_ui_preview(): bool {
    if ( '1' !== sanitize_text_field( wp_unslash( $_GET['daw_ui_preview'] ?? '' ) ) ) {
        return false;
    }

    if ( ( defined( 'RECRUITMENT_SANDBOX' ) && RECRUITMENT_SANDBOX )
        || ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) ) {
        return true;
    }

    return false;
}

function recruitment_logout_form( string $label, string $redirect_url, string $class = '' ): string {
    if ( ! function_exists( 'admin_url' ) || ! function_exists( 'wp_nonce_field' ) ) {
        return '';
    }

    if ( recruitment_is_applicant_user() ) {
        $redirect_url = recruitment_get_public_url( 'applicant-login' );
    } elseif ( home_url( '/' ) === untrailingslashit( $redirect_url ) . '/' ) {
        $account_type = is_user_logged_in() ? (string) get_user_meta( get_current_user_id(), 'daw_oracle_account_type', true ) : '';
        $redirect_url = 'user_department' === $account_type
            ? recruitment_get_department_login_url()
            : ( 'hr' === $account_type
                ? recruitment_get_public_url( 'login', [ 'target' => 'hr' ] )
                : recruitment_get_public_url( 'applicant-login' ) );
    }

    ob_start();
    ?>
    <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" class="<?= esc_attr( $class ) ?>">
        <input type="hidden" name="action" value="<?= esc_attr( recruitment_is_applicant_user() ? 'daw_applicant_logout' : 'daw_logout' ) ?>">
        <input type="hidden" name="redirect_to" value="<?= esc_attr( $redirect_url ) ?>">
        <?php wp_nonce_field( recruitment_is_applicant_user() ? 'daw_applicant_logout' : 'daw_logout' ); ?>
        <button type="submit" class="<?= esc_attr( $class ) ?>"><?= esc_html( $label ) ?></button>
    </form>
    <?php
    return (string) ob_get_clean();
}

function recruitment_is_applicant_user(): bool {
    return null !== recruitment_get_current_applicant();
}

/**
 * Validate and return the candidate associated with the isolated applicant cookie.
 *
 * @return array<string, mixed>|null
 */
function recruitment_get_current_applicant(): ?array {
    static $cached_token = null;
    static $cached_applicant = null;
    $token = isset( $_COOKIE['daw_applicant_session'] ) && is_string( $_COOKIE['daw_applicant_session'] )
        ? $_COOKIE['daw_applicant_session']
        : '';
    if ( $token === $cached_token ) {
        return $cached_applicant;
    }
    $cached_token = $token;
    $cached_applicant = null;
    if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
        return null;
    }
    $session_key = 'daw_applicant_session_' . hash( 'sha256', $token );
    $session = get_transient( $session_key );
    if ( ! is_array( $session ) || empty( $session['login_id'] ) ) {
        return null;
    }
    $result = ( new Recruitment_Database() )->get_applicant_account( (string) $session['login_id'] );
    if ( empty( $result['success'] ) ) {
        delete_transient( $session_key );
        recruitment_clear_applicant_session_cookie();
        return null;
    }
    $cached_applicant = $result['account'];
    $cached_applicant['session_token'] = $token;
    return $cached_applicant;
}

/**
 * Start an isolated candidate session without changing WordPress administrator cookies.
 *
 * @param array{id: string} $account
 */
function recruitment_start_applicant_session( array $account, bool $remember = false ): bool {
    if ( empty( $account['id'] ) || ! ctype_digit( (string) $account['id'] ) ) {
        return false;
    }
    recruitment_clear_applicant_session_cookie();
    try {
        $token = bin2hex( random_bytes( 32 ) );
    } catch ( RuntimeException $exception ) {
        error_log( 'Applicant session token generation failed.' );
        return false;
    }
    $expiration = $remember ? 14 * DAY_IN_SECONDS : 2 * HOUR_IN_SECONDS;
    $session_key = 'daw_applicant_session_' . hash( 'sha256', $token );
    set_transient( $session_key, [ 'login_id' => (string) $account['id'] ], $expiration );
    $cookie_options = [
        'expires' => time() + $expiration,
        'path' => COOKIEPATH ?: '/',
        'domain' => COOKIE_DOMAIN,
        'secure' => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if ( ! setcookie( 'daw_applicant_session', $token, $cookie_options ) ) {
        delete_transient( $session_key );
        return false;
    }
    $_COOKIE['daw_applicant_session'] = $token;
    return true;
}

function recruitment_clear_applicant_session_cookie(): void {
    if ( isset( $_COOKIE['daw_applicant_session'] ) && is_string( $_COOKIE['daw_applicant_session'] ) && preg_match( '/^[a-f0-9]{64}$/', $_COOKIE['daw_applicant_session'] ) ) {
        delete_transient( 'daw_applicant_session_' . hash( 'sha256', $_COOKIE['daw_applicant_session'] ) );
    }
    setcookie(
        'daw_applicant_session',
        '',
        [
            'expires' => time() - HOUR_IN_SECONDS,
            'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
    unset( $_COOKIE['daw_applicant_session'] );
}

/**
 * Return the public vacancy list used by careers, detail, and application pages.
 *
 * @return array<int, array<string, mixed>>
 */
function recruitment_get_public_jobs(): array {
    return recruitment_get_public_jobs_result()['jobs'];
}

/**
 * @return array{success: bool, jobs: array<int, array<string, mixed>>}
 */
function recruitment_get_public_jobs_result(): array {
    $wordpress_jobs = [];
    foreach (
        get_posts(
            [
                'post_type' => 'daw_vacancy',
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
            ]
        ) as $vacancy_post
    ) {
            $status = (string) get_post_meta( $vacancy_post->ID, '_daw_vacancy_status', true );
            if ( '' !== $status && 'published' !== $status ) {
                continue;
            }
            $deadline = (string) get_post_meta( $vacancy_post->ID, '_daw_deadline', true );
        if ( '' !== $deadline && $deadline < current_time( 'Y-m-d' ) ) {
            continue;
        }
        $wordpress_jobs[] = recruitment_map_vacancy( $vacancy_post );
    }

    $database = new Recruitment_Database();
    $vacancies = $database->get_vacancies();
    $oracle_error = $database->has_error();
    $jobs = $wordpress_jobs;
    foreach ( $vacancies as $vacancy ) {
        $deadline = strtotime( (string) ( $vacancy['deadline'] ?? '' ) );
        $posted = strtotime( (string) ( $vacancy['posted_at'] ?? '' ) );
        $jobs[] = [
            'id' => (string) $vacancy['id'],
            'title' => (string) $vacancy['title'],
            'location' => (string) ( $vacancy['location'] ?? '' ),
            'dealer' => (string) ( $vacancy['dealer'] ?? '' ),
            'region' => '',
            'type' => (string) ( $vacancy['job_type'] ?? '' ),
            'description' => trim( strip_tags( (string) ( $vacancy['description'] ?? '' ) ) ),
            'deadline' => $deadline ? wp_date( 'j F Y', $deadline ) : '',
            'postedDate' => $posted ? wp_date( 'j F Y', $posted ) : '',
            'requirements' => '' !== (string) ( $vacancy['education'] ?? '' ) ? [ (string) $vacancy['education'] ] : [],
        ];
    }

    return [ 'success' => ! $oracle_error || ! empty( $wordpress_jobs ), 'jobs' => $jobs ];
}

/**
 * @return array<string, mixed>|null
 */
function recruitment_get_public_job( int $job_id ): ?array {
    foreach ( recruitment_get_public_jobs() as $job ) {
        if ( (int) $job['id'] === $job_id ) {
            return $job;
        }
    }

    return null;
}

/**
 * @param WP_Post|object $vacancy
 * @return array<string, mixed>
 */
function recruitment_map_vacancy( $vacancy ): array {
    $deadline = (string) get_post_meta( $vacancy->ID, '_daw_deadline', true );
    $responsibilities = (string) get_post_meta( $vacancy->ID, '_daw_responsibilities', true );
    $requirements_text = (string) get_post_meta( $vacancy->ID, '_daw_requirements', true );
    $description = wpautop( wp_kses_post( $vacancy->post_content ) );
    if ( '' !== trim( $responsibilities ) ) {
        $description .= '<h2>Tanggung Jawab</h2>' . wpautop( esc_html( $responsibilities ) );
    }
    $requirements = array_values(
        array_filter(
            array_map( 'trim', preg_split( '/\r\n|\r|\n/', $requirements_text ) ?: [] ),
            static fn ( string $requirement ): bool => '' !== $requirement
        )
    );
    return [
        'id' => (string) ( 1000000000000 + (int) $vacancy->ID ),
        'title' => get_the_title( $vacancy ),
        'location' => (string) get_post_meta( $vacancy->ID, '_daw_location', true ),
        'dealer' => (string) get_post_meta( $vacancy->ID, '_daw_dealer', true ),
        'region' => (string) get_post_meta( $vacancy->ID, '_daw_region', true ),
        'type' => (string) get_post_meta( $vacancy->ID, '_daw_type', true ),
        'description' => $description,
        'deadline' => $deadline ?: 'Belum ditentukan',
        'postedDate' => get_the_date( 'j F Y', $vacancy ),
        'requirements' => $requirements,
    ];
}

/**
 * Get the URL to a specific admin page.
 *
 * @param string $slug E.g. 'dashboard', 'vacancies', 'applicants'.
 */
function recruitment_get_admin_url( string $slug = 'dashboard' ): string {
    $slug = sanitize_key( $slug );

    if ( in_array( $slug, [ 'user-interview', 'vacancies' ], true ) && defined( 'RECRUITMENT_SANDBOX' ) && RECRUITMENT_SANDBOX ) {
        return add_query_arg( [ 'page' => $slug, 'daw_ui_preview' => '1' ], home_url( '/' ) );
    }

    if ( 'vacancies' === $slug ) {
        return add_query_arg( 'page', 'recruitment-vacancies', admin_url( 'admin.php' ) );
    }

    $pages = [
        'dashboard'    => 'recruitment-dashboard',
        'applicants'   => 'recruitment-applicants',
        'applications' => 'recruitment-applications',
        'user-interview' => 'recruitment-user-interview',
        'psychotest-settings' => 'recruitment-psychotest-settings',
        'question-bank' => 'recruitment-question-bank',
        'settings'     => 'recruitment-settings',
    ];

    return add_query_arg( 'page', $pages[ $slug ] ?? 'recruitment-dashboard', admin_url( 'admin.php' ) );
}
