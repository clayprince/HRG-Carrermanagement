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

/**
 * Get the public announcement for a selection decision recorded in the timeline.
 *
 * @param array<string, mixed> $event
 * @param array<string, mixed>|null $previous_event
 * @return array{stage_label: string, outcome: string, next_page: string, next_label: string, awaiting_final_decision: bool, is_final: bool}|null
 */
function recruitment_get_application_stage_announcement( array $event, ?array $previous_event = null ): ?array {
    $normalize = static function ( $value ): string {
        return trim( strtoupper( (string) preg_replace( '/[^a-z0-9]+/i', '_', (string) $value ) ), '_' );
    };
    $stage_key = $normalize( $event['stage'] ?? '' );
    $status_key = $normalize( $event['status'] ?? '' );
    $note_key = $normalize( $event['note'] ?? '' );
    $is_final_stage = 'FINAL' === $stage_key
        || false !== strpos( $stage_key, 'FINAL_DECISION' )
        || false !== strpos( $stage_key, 'KEPUTUSAN_AKHIR' )
        || false !== strpos( $stage_key, 'KEPUTUSAN_FINAL' );

    if ( $is_final_stage ) {
        if ( ! in_array( $status_key, [ 'DITERIMA', 'ACCEPTED', 'DITOLAK', 'REJECTED' ], true ) ) {
            return null;
        }

        return [
            'stage_label' => 'Keputusan Akhir',
            'outcome' => in_array( $status_key, [ 'DITERIMA', 'ACCEPTED' ], true ) ? 'passed' : 'failed',
            'next_page' => '',
            'next_label' => '',
            'awaiting_final_decision' => false,
            'is_final' => true,
        ];
    }

    $stage_labels = [
        'ADMINISTRASI' => 'Seleksi Administrasi',
        'SCREENING' => 'Seleksi Administrasi',
        'SUBMITTED' => 'Seleksi Administrasi',
        'PSIKOTES' => 'Psikotes',
        'INTERVIEW_HR' => 'Wawancara HR',
        'INTERVIEW_USER' => 'Wawancara User',
    ];

    if ( 'TIDAK_LOLOS_WAWANCARA_USER' === $status_key ) {
        return [
            'stage_label' => 'Wawancara User',
            'outcome' => 'failed',
            'next_page' => '',
            'next_label' => '',
            'awaiting_final_decision' => false,
            'is_final' => false,
        ];
    }

    if ( 'LOLOS_WAWANCARA_USER' === $status_key ) {
        return [
            'stage_label' => 'Wawancara User',
            'outcome' => 'passed',
            'next_page' => '',
            'next_label' => '',
            'awaiting_final_decision' => true,
            'is_final' => false,
        ];
    }

    if (
        'INTERVIEW_USER' === $stage_key
        && 'INTERVIEW_USER' === $status_key
        && false !== strpos( $note_key, 'HASIL_WAWANCARA_HR_LOLOS' )
        && false === strpos( $note_key, 'TIDAK_LOLOS' )
    ) {
        return [
            'stage_label' => 'Wawancara HR',
            'outcome' => 'passed',
            'next_page' => 'interview-booking',
            'next_label' => 'Lanjut ke Jadwal Wawancara User',
            'awaiting_final_decision' => false,
            'is_final' => false,
        ];
    }

    if ( 'DITOLAK' === $status_key && false !== strpos( $note_key, 'HASIL_WAWANCARA_HR' ) ) {
        return [
            'stage_label' => 'Wawancara HR',
            'outcome' => 'failed',
            'next_page' => '',
            'next_label' => '',
            'awaiting_final_decision' => false,
            'is_final' => false,
        ];
    }

    if ( 'DITOLAK' === $status_key && false !== strpos( $note_key, 'HASIL_SELEKSI_TIDAK_LOLOS' ) ) {
        return [
            'stage_label' => $stage_labels[ $stage_key ] ?? 'Seleksi',
            'outcome' => 'failed',
            'next_page' => '',
            'next_label' => '',
            'awaiting_final_decision' => false,
            'is_final' => false,
        ];
    }

    if ( false === strpos( $note_key, 'LOLOS_KE_TAHAP' ) ) {
        return null;
    }

    $next_steps = [
        'PSIKOTES' => [ 'Seleksi Administrasi', 'psychotest-preparation', 'Lanjut ke Persiapan Psikotes' ],
        'INTERVIEW_HR' => [ 'Psikotes', 'interview-booking', 'Lanjut ke Jadwal Wawancara HR' ],
        'INTERVIEW_USER' => [ 'Wawancara HR', 'interview-booking', 'Lanjut ke Jadwal Wawancara User' ],
        'FINAL' => [ 'Wawancara User', '', '' ],
    ];
    if ( ! isset( $next_steps[ $status_key ] ) ) {
        return null;
    }

    $previous_stage_key = $normalize( $previous_event['stage'] ?? '' );

    return [
        'stage_label' => $stage_labels[ $previous_stage_key ] ?? $next_steps[ $status_key ][0],
        'outcome' => 'passed',
        'next_page' => $next_steps[ $status_key ][1],
        'next_label' => $next_steps[ $status_key ][2],
        'awaiting_final_decision' => 'FINAL' === $status_key,
        'is_final' => false,
    ];
}

/**
 * Check whether any timeline event records a completed User Department interview.
 *
 * @param array<int, array<string, mixed>> $timeline
 */
function recruitment_has_user_interview_result( array $timeline ): bool {
    foreach ( $timeline as $event ) {
        $status_key = strtoupper( trim( (string) preg_replace( '/[^a-z0-9]+/i', '_', (string) ( $event['status'] ?? '' ) ), '_' ) );
        if ( in_array( $status_key, [ 'LOLOS_WAWANCARA_USER', 'TIDAK_LOLOS_WAWANCARA_USER' ], true ) ) {
            return true;
        }
    }

    return false;
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

    $is_applicant = recruitment_is_applicant_user()
        || 'service_unavailable' === recruitment_get_applicant_session_error();

    if ( $is_applicant ) {
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
        <input type="hidden" name="action" value="<?= esc_attr( $is_applicant ? 'daw_applicant_logout' : 'daw_logout' ) ?>">
        <input type="hidden" name="redirect_to" value="<?= esc_attr( $redirect_url ) ?>">
        <?php wp_nonce_field( $is_applicant ? 'daw_applicant_logout' : 'daw_logout' ); ?>
        <button type="submit" class="<?= esc_attr( $class ) ?>"><?= esc_html( $label ) ?></button>
    </form>
    <?php
    return (string) ob_get_clean();
}

function recruitment_is_applicant_user(): bool {
    return null !== recruitment_get_current_applicant();
}

function recruitment_get_applicant_session_error(): string {
    return (string) ( $GLOBALS['daw_applicant_session_error'] ?? '' );
}

function recruitment_abort_if_applicant_session_unavailable(): void {
    if ( 'service_unavailable' !== recruitment_get_applicant_session_error() ) {
        return;
    }

    $message = __( 'Akun pelamar sedang tidak dapat diverifikasi. Sesi Anda tetap tersimpan; silakan coba muat ulang halaman beberapa saat lagi.', 'recruitment-plugin' );
    if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
        wp_send_json_error( [ 'message' => $message ], 503 );
    }

    wp_die( esc_html( $message ), esc_html__( 'Layanan akun sementara tidak tersedia', 'recruitment-plugin' ), [ 'response' => 503 ] );
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
    $GLOBALS['daw_applicant_session_error'] = '';
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
        if ( 'service_unavailable' === (string) ( $result['error'] ?? '' ) ) {
            $GLOBALS['daw_applicant_session_error'] = 'service_unavailable';
        } else {
            delete_transient( $session_key );
            recruitment_clear_applicant_session_cookie();
        }
        return null;
    }
    $cached_applicant = $result['account'];
    $cached_applicant['session_token'] = $token;
    return $cached_applicant;
}

function recruitment_applicant_psychotest_stage_status( ?string $application_id = null ): ?bool {
    $applicant = recruitment_get_current_applicant();
    if ( ! is_array( $applicant ) || empty( $applicant['id'] ) ) {
        return false;
    }

    $database = new Recruitment_Database();
    $applications = $database->get_applicant_applications( (string) $applicant['id'] );
    if ( $database->has_error() ) {
        return null;
    }

    foreach ( $applications as $application ) {
        if ( null !== $application_id && (string) ( $application['id'] ?? '' ) !== $application_id ) {
            continue;
        }

        $latest_stage = (string) ( $application['latest_stage'] ?? $application['status'] ?? '' );
        $stage_key = strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '_', $latest_stage ), '_' ) );
        $final_status = strtoupper( trim( (string) ( $application['final_status'] ?? '' ) ) );
        $is_psychotest_stage = (
            false !== strpos( $stage_key, 'psikotes' )
            || false !== strpos( $stage_key, 'psycholog' )
            || 'tes_psikologi' === $stage_key
        ) && ! in_array( $final_status, [ 'DITERIMA', 'DITOLAK', 'ACCEPTED', 'REJECTED' ], true );
        if ( $is_psychotest_stage ) {
            return true;
        }
    }

    return false;
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

function recruitment_get_assessment_user_id(): int {
    $applicant = recruitment_get_current_applicant();
    if ( is_array( $applicant ) && ctype_digit( (string) ( $applicant['profile_id'] ?? '' ) ) ) {
        return absint( $applicant['profile_id'] );
    }

    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();
        if ( in_array( 'daw_applicant', (array) $user->roles, true ) ) {
            return absint( $user->ID );
        }
    }

    return 0;
}

/**
 * Read assessment progress from the applicant's isolated session storage or legacy user meta.
 *
 * @return mixed
 */
function recruitment_get_assessment_state( int $user_id, string $key ) {
    $applicant = recruitment_get_current_applicant();
    if ( is_array( $applicant ) && $user_id === absint( $applicant['profile_id'] ?? 0 ) ) {
        $option_name = recruitment_assessment_option_name( $user_id, $key );
        return get_option( $option_name, null );
    }

    if ( is_user_logged_in() && $user_id === get_current_user_id() ) {
        $user = wp_get_current_user();
        if ( in_array( 'daw_applicant', (array) $user->roles, true ) ) {
            return get_user_meta( $user_id, $key, true );
        }
    }

    return null;
}

/**
 * Persist assessment progress without changing the applicant's isolated login cookie.
 *
 * @param mixed $value
 */
function recruitment_update_assessment_state( int $user_id, string $key, $value ): bool {
    $applicant = recruitment_get_current_applicant();
    if ( is_array( $applicant ) && $user_id === absint( $applicant['profile_id'] ?? 0 ) ) {
        $option_name = recruitment_assessment_option_name( $user_id, $key );
        $existing = get_option( $option_name, null );
        $saved = null === $existing
            ? add_option( $option_name, $value, '', false )
            : update_option( $option_name, $value, false );
        if ( $saved || get_option( $option_name, null ) === $value ) {
            return true;
        }
        wp_die( esc_html__( 'Kemajuan tes gagal disimpan. Jawaban tidak akan dianggap tersimpan; silakan coba kembali.', 'recruitment-plugin' ), '', [ 'response' => 500 ] );
        return false;
    }

    if ( is_user_logged_in() && $user_id === get_current_user_id() ) {
        $user = wp_get_current_user();
        if ( in_array( 'daw_applicant', (array) $user->roles, true ) ) {
            $saved = update_user_meta( $user_id, $key, $value );
            if ( $saved || get_user_meta( $user_id, $key, true ) === $value ) {
                return true;
            }
            wp_die( esc_html__( 'Kemajuan tes gagal disimpan. Jawaban tidak akan dianggap tersimpan; silakan coba kembali.', 'recruitment-plugin' ), '', [ 'response' => 500 ] );
            return false;
        }
    }

    wp_die( esc_html__( 'Sesi pelamar tidak valid. Kemajuan tes tidak disimpan.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
    return false;
}

function recruitment_assessment_option_name( int $user_id, string $key ): string {
    return 'daw_assessment_' . $user_id . '_' . substr( hash( 'sha256', $key ), 0, 20 );
}

function recruitment_psychotest_consent_key( string $test_key ): string {
    $user_id = recruitment_get_assessment_user_id();
    $applicant = recruitment_get_current_applicant();
    $session_id = is_array( $applicant )
        ? (string) ( $applicant['session_token'] ?? '' )
        : (string) ( function_exists( 'wp_get_session_token' ) ? wp_get_session_token() : '' );
    return 'daw_psychotest_consent_' . hash( 'sha256', $session_id . ':' . $user_id . ':' . $test_key );
}

function recruitment_grant_psychotest_consent( string $test_key ): bool {
    if ( recruitment_get_assessment_user_id() < 1 ) {
        return false;
    }
    $consent_key = recruitment_psychotest_consent_key( $test_key );
    return set_transient( $consent_key, true, 10 * MINUTE_IN_SECONDS )
        || true === get_transient( $consent_key );
}

function recruitment_consume_psychotest_consent( string $test_key ): bool {
    $consent_key = recruitment_psychotest_consent_key( $test_key );
    if ( get_transient( $consent_key ) ) {
        delete_transient( $consent_key );
        return true;
    }
    return ( new Recruitment_Psychotest_Proctoring() )->has_current_consent();
}

/**
 * Get the configured online test closing timestamp.
 *
 * @param array<string, mixed> $settings
 */
function recruitment_psychotest_closing_timestamp( array $settings ): ?int {
    $date = (string) ( $settings['closing_date'] ?? '' );
    $time = (string) ( $settings['closing_time'] ?? '' );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
        return null;
    }

    $closing_at = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, wp_timezone() );
    if ( ! $closing_at || $closing_at->format( 'Y-m-d H:i' ) !== $date . ' ' . $time ) {
        return null;
    }

    return $closing_at->getTimestamp();
}

/**
 * Get when an online psychotest opens in the site timezone.
 *
 * @param array<string, mixed> $settings
 */
function recruitment_psychotest_start_timestamp( array $settings ): ?int {
    $date = (string) ( $settings['test_date'] ?? '' );
    $time = (string) ( $settings['start_time'] ?? '' );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
        return null;
    }

    $start_at = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, wp_timezone() );
    if ( ! $start_at || $start_at->format( 'Y-m-d H:i' ) !== $date . ' ' . $time ) {
        return null;
    }

    return $start_at->getTimestamp();
}

/**
 * Get the date and daily time bounds for editing an attendance timestamp.
 *
 * @param array<string, mixed> $settings
 * @return array{start_date: string, end_date: string, start_time: string, end_time: string}|null
 */
function recruitment_psychotest_attendance_window( array $settings ): ?array {
    $start_date = (string) ( $settings['test_date'] ?? '' );
    $end_date = '' !== (string) ( $settings['closing_date'] ?? '' )
        ? (string) $settings['closing_date']
        : $start_date;
    $start_time = (string) ( $settings['start_time'] ?? '' );
    $end_time = (string) ( $settings['end_time'] ?? '' );
    $date_pattern = '/^\d{4}-\d{2}-\d{2}$/';
    $time_pattern = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';

    if (
        ! preg_match( $date_pattern, $start_date )
        || ! preg_match( $date_pattern, $end_date )
    ) {
        return null;
    }

    if ( ! preg_match( $time_pattern, $start_time ) ) {
        $start_time = '00:00';
    }
    if ( ! preg_match( $time_pattern, $end_time ) ) {
        $end_time = '23:59';
    }

    $start_day = DateTimeImmutable::createFromFormat( '!Y-m-d', $start_date, wp_timezone() );
    $end_day = DateTimeImmutable::createFromFormat( '!Y-m-d', $end_date, wp_timezone() );
    if (
        ! $start_day
        || ! $end_day
        || $start_day->format( 'Y-m-d' ) !== $start_date
        || $end_day->format( 'Y-m-d' ) !== $end_date
        || $end_date < $start_date
    ) {
        return null;
    }

    return [
        'start_date' => $start_date,
        'end_date' => $end_date,
        'start_time' => $start_time,
        'end_time' => $end_time,
    ];
}

function recruitment_is_psychotest_attendance_time_in_window( array $settings, DateTimeImmutable $datetime ): bool {
    $window = recruitment_psychotest_attendance_window( $settings );
    if ( null === $window ) {
        return false;
    }

    $date = $datetime->format( 'Y-m-d' );

    // Gausah pake limitasi untuk yang dijamnya aja (validasi hanya rentang tanggal)
    return $date >= $window['start_date']
        && $date <= $window['end_date'];
}

/**
 * Close an expired online test immediately when settings or applicant pages load.
 *
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function recruitment_sync_psychotest_closing( array $settings ): array {
    if ( 'online' === ( $settings['mode'] ?? '' ) && 'active' === ( $settings['status'] ?? '' ) ) {
        $closing_at = recruitment_psychotest_closing_timestamp( $settings );
        if ( null !== $closing_at && $closing_at <= time() ) {
            $settings['status'] = 'inactive';
            update_option( 'recruitment_psychotest_settings', $settings );
        }
    }

    return $settings;
}

/**
 * Schedule the automatic closure of an online psychotest.
 *
 * @param array<string, mixed> $settings
 */
function recruitment_schedule_psychotest_closing( array $settings ): void {
    wp_clear_scheduled_hook( 'recruitment_close_psychotest' );
    if ( 'online' !== ( $settings['mode'] ?? '' ) || 'active' !== ( $settings['status'] ?? '' ) ) {
        return;
    }

    $closing_at = recruitment_psychotest_closing_timestamp( $settings );
    if ( null !== $closing_at && $closing_at > time() ) {
        wp_schedule_single_event( $closing_at, 'recruitment_close_psychotest' );
    } elseif ( null !== $closing_at ) {
        recruitment_sync_psychotest_closing( $settings );
    }
}

function recruitment_ensure_psychotest_closing(): void {
    $settings = (array) get_option( 'recruitment_psychotest_settings', [] );
    $settings = recruitment_sync_psychotest_closing( $settings );
    $closing_at = recruitment_psychotest_closing_timestamp( $settings );
    if ( 'online' === ( $settings['mode'] ?? '' ) && 'active' === ( $settings['status'] ?? '' ) && null !== $closing_at && $closing_at > time() ) {
        if ( (int) wp_next_scheduled( 'recruitment_close_psychotest' ) !== $closing_at ) {
            recruitment_schedule_psychotest_closing( $settings );
        }
    } elseif ( wp_next_scheduled( 'recruitment_close_psychotest' ) ) {
        wp_clear_scheduled_hook( 'recruitment_close_psychotest' );
    }
}

function recruitment_close_psychotest_if_due(): void {
    $settings = (array) get_option( 'recruitment_psychotest_settings', [] );
    $settings = recruitment_sync_psychotest_closing( $settings );
    if ( 'active' === ( $settings['status'] ?? '' ) ) {
        recruitment_schedule_psychotest_closing( $settings );
    }
}

function recruitment_is_psychotest_test_enabled( string $test_key ): bool {
    $test_groups = [
        'iq' => 'iq',
        'pauli' => 'pauli',
        'disc' => 'personality',
        'papi' => 'personality',
    ];
    if ( ! isset( $test_groups[ $test_key ] ) ) {
        return false;
    }

    $settings = recruitment_sync_psychotest_closing( (array) get_option( 'recruitment_psychotest_settings', [] ) );
    if (
        'active' !== ( $settings['status'] ?? '' )
        || 'online' !== ( $settings['mode'] ?? '' )
        || null === ( $start_at = recruitment_psychotest_start_timestamp( $settings ) )
        || $start_at > time()
        || null === ( $closing_at = recruitment_psychotest_closing_timestamp( $settings ) )
        || $closing_at <= time()
    ) {
        return false;
    }

    $selected_tests = is_array( $settings['online_tests'] ?? null )
        ? array_map( static fn ( $test ): string => sanitize_key( (string) $test ), array_filter( $settings['online_tests'], 'is_scalar' ) )
        : [ 'iq', 'pauli', 'personality' ];
    $group = $test_groups[ $test_key ];
    if ( ! in_array( $group, $selected_tests, true ) ) {
        return false;
    }
    if ( 'personality' === $group && ( ! in_array( 'iq', $selected_tests, true ) || ! in_array( 'pauli', $selected_tests, true ) ) ) {
        return false;
    }
    if ( 'pauli' === $group && ! in_array( 'iq', $selected_tests, true ) ) {
        return false;
    }

    $individual_settings = (array) get_option( 'recruitment_psychotest_tests', [] );
    if ( isset( $individual_settings[ $test_key ]['active'] ) && empty( $individual_settings[ $test_key ]['active'] ) ) {
        return false;
    }

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
        $headcount = filter_var( get_post_meta( $vacancy_post->ID, '_daw_headcount', true ), FILTER_VALIDATE_INT );
        $requested_headcount = recruitment_get_vacancy_requested_headcount( (int) $vacancy_post->ID );
        if (
            ! is_int( $headcount )
            || 1 > $headcount
            || ( is_int( $requested_headcount ) && $requested_headcount !== $headcount )
            || ! recruitment_has_approved_vacancy_request( (int) $vacancy_post->ID )
        ) {
            continue;
        }
        $status = (string) get_post_meta( $vacancy_post->ID, '_daw_vacancy_status', true );
        if ( '' !== $status && 'published' !== $status ) {
            continue;
        }
        if ( '1' === (string) get_post_meta( $vacancy_post->ID, '_daw_vacancy_filled', true ) ) {
            continue;
        }
        $deadline = (string) get_post_meta( $vacancy_post->ID, '_daw_deadline', true );
        if ( '' !== $deadline && $deadline < current_time( 'Y-m-d' ) ) {
            continue;
        }
        $wordpress_jobs[] = recruitment_map_vacancy( $vacancy_post );
    }

    return [ 'success' => true, 'jobs' => $wordpress_jobs ];
}

function recruitment_has_approved_vacancy_request( int $post_id ): bool {
    $request = recruitment_get_approved_vacancy_request( $post_id );
    return is_array( $request ) && is_int( $request['headcount'] ) && 1 <= $request['headcount'];
}

/**
 * @return array{headcount: int|null}|null
 */
function recruitment_get_approved_vacancy_request( int $post_id ): ?array {
    if ( $post_id < 1 || ! function_exists( 'get_users' ) ) {
        return null;
    }

    $request_code = sanitize_text_field( (string) get_post_meta( $post_id, '_daw_fptk_code', true ) );
    if ( '' === $request_code ) {
        return null;
    }

    static $approved_requests = null;
    if ( null === $approved_requests ) {
        $approved_requests = [];
        foreach ( get_users( [ 'meta_key' => 'daw_department_job_requests', 'meta_compare' => 'EXISTS', 'fields' => 'all' ] ) as $department_user ) {
            $requests = get_user_meta( $department_user->ID, 'daw_department_job_requests', true );
            if ( ! is_array( $requests ) ) {
                continue;
            }

            foreach ( $requests as $code => $request ) {
                if (
                    ! is_array( $request )
                    || ! in_array( $request['status'] ?? '', [ 'Disetujui', 'Lowongan Dibuka', 'Ready for Recruitment' ], true )
                    || absint( $request['vacancy_id'] ?? 0 ) < 1
                ) {
                    continue;
                }

                $fields = is_array( $request['fields'] ?? null ) ? $request['fields'] : [];
                $headcount = filter_var( $fields['headcount'] ?? null, FILTER_VALIDATE_INT );
                $key = absint( $request['vacancy_id'] ) . ':' . (string) $code;
                $approved_requests[ $key ] = [
                    'headcount' => is_int( $headcount ) && 1 <= $headcount ? $headcount : null,
                ];
            }
        }
    }

    return $approved_requests[ $post_id . ':' . $request_code ] ?? null;
}

function recruitment_get_vacancy_requested_headcount( int $post_id ): ?int {
    $request = recruitment_get_approved_vacancy_request( $post_id );
    return is_array( $request ) ? $request['headcount'] : null;
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
        'dashboard'           => 'recruitment-dashboard',
        'applicants'          => 'recruitment-applicants',
        'applications'        => 'recruitment-applications',
        'user-interview'      => 'recruitment-user-interview',
        'hr-interview'        => 'recruitment-hr-interview',
        'final-decision'      => 'recruitment-final-decision',
        'interview-schedule'  => 'recruitment-interview-schedule',
        'interview-schedule-user' => 'recruitment-interview-schedule-user',
        'user-management'     => 'recruitment-user-management',
        'hr-profile'          => 'recruitment-hr-profile',
        'psychotest-settings' => 'recruitment-psychotest-settings',
        'psychotest-results'  => 'recruitment-psychotest-results',
        'question-bank'       => 'recruitment-question-bank',
        'settings'            => 'recruitment-settings',
    ];

    if ( 'user-department' === $slug ) {
        $slug = 'daw-user-department';
    }

    return add_query_arg( 'page', $pages[ $slug ] ?? $slug, admin_url( 'admin.php' ) );
}

/**
 * Retrieve the current active HR administrator's verified profile (name, email, role).
 *
 * @return array{name: string, email: string, role: string, avatar: string}
 */
function recruitment_get_current_hr_identity(): array {
    $current_user = wp_get_current_user();
    if ( ! $current_user instanceof WP_User || ! $current_user->exists() ) {
        return [
            'name'   => 'Admin HR',
            'email'  => '',
            'role'   => 'Administrator HR',
            'avatar' => 'HR',
        ];
    }

    $oracle_account_id = (string) get_user_meta( $current_user->ID, 'daw_oracle_account_id', true );
    $name = '';
    $email = '';

    // Priority 1: Query Oracle Database directly for live data
    if ( ctype_digit( $oracle_account_id ) && '0' !== $oracle_account_id ) {
        try {
            $database = new Recruitment_Database();
            $hr_profile = $database->get_hr_profile( $oracle_account_id );
            if ( ! empty( $hr_profile['email'] ) ) {
                $email = (string) $hr_profile['email'];
            }
            if ( ! empty( $hr_profile['username'] ) ) {
                $name = (string) $hr_profile['username'];
            }
        } catch ( Throwable $e ) {
            // Fallback to meta
        }
    }

    // Priority 2: Fallback to user meta
    if ( '' === $email ) {
        $email = (string) get_user_meta( $current_user->ID, 'daw_oracle_email', true );
    }
    if ( '' === $name ) {
        $name = (string) get_user_meta( $current_user->ID, 'daw_oracle_username', true );
    }

    // Priority 3: Fallback to WordPress user fields (exclude internal bridge email)
    if ( '' === $email ) {
        $user_email = (string) $current_user->user_email;
        if ( false === strpos( $user_email, '@oracle-bridge.invalid' ) ) {
            $email = $user_email;
        }
    }
    if ( '' === $name ) {
        $name = $current_user->display_name ?: $current_user->user_login;
    }

    // If name is an email address and differs from the active email, sync name to active email
    if ( is_email( $name ) && '' !== $email && strtolower( $name ) !== strtolower( $email ) ) {
        $name = $email;
    }

    // If name is an email address and email is empty, use name as email
    if ( '' === $email && is_email( $name ) ) {
        $email = $name;
    }

    // If name is empty, use email or 'Admin HR'
    if ( '' === $name ) {
        $name = '' !== $email ? $email : 'Admin HR';
    }

    // Generate avatar initials
    $avatar = 'HR';
    if ( '' !== $name ) {
        $clean_name = trim( explode( '@', $name )[0] );
        $parts = preg_split( '/[\s._-]+/', $clean_name );
        if ( ! empty( $parts[0] ) ) {
            $first = strtoupper( mb_substr( $parts[0], 0, 1 ) );
            $second = ! empty( $parts[1] ) ? strtoupper( mb_substr( $parts[1], 0, 1 ) ) : ( mb_strlen( $parts[0] ) > 1 ? strtoupper( mb_substr( $parts[0], 1, 1 ) ) : '' );
            if ( ctype_alnum( $first ) ) {
                $avatar = $first . $second;
            }
        }
    }

    return [
        'name'   => $name,
        'email'  => $email,
        'role'   => 'Administrator HR',
        'avatar' => $avatar ?: 'HR',
    ];
}

/**
 * Return default email templates for recruitment notifications.
 *
 * @return array<string, array{id: string, title: string, category: string, stage: string, subject: string, body: string, is_default: bool}>
 */
function recruitment_get_default_email_templates(): array {
    return [
        'konfirmasi_lamaran' => [
            'id'         => 'konfirmasi_lamaran',
            'title'      => 'Konfirmasi Lamaran',
            'category'   => 'lamaran',
            'stage'      => 'ADMINISTRASI',
            'subject'    => 'Konfirmasi Penerimaan Lamaran - {{posisi}} - PT Daya Adicipta Wisesa',
            'body'       => "Halo {{nama}},\n\nTerima kasih telah melamar posisi {{posisi}} di PT Daya Adicipta Wisesa ({{dealer}}). Lamaran Anda telah kami terima dan saat ini sedang dalam proses peninjauan oleh tim rekrutmen kami.\n\nSilakan masuk ke fitur Tracking Lamaran untuk melihat perkembangan proses seleksi Anda.\n\nSalam,\nTim Rekrutmen DAW",
            'is_default' => true,
        ],
        'lolos_administrasi' => [
            'id'         => 'lolos_administrasi',
            'title'      => 'Lolos Seleksi Administrasi',
            'category'   => 'administrasi',
            'stage'      => 'PSIKOTES',
            'subject'    => 'Selamat! Anda Lolos Seleksi Administrasi - {{posisi}} - PT Daya Adicipta Wisesa',
            'body'       => "Halo {{nama}},\n\nSelamat! Berdasarkan hasil seleksi berkas administrasi untuk posisi {{posisi}} di {{dealer}}, Anda dinyatakan LOLOS ke tahapan seleksi berikutnya (Psikotes).\n\nInformasi mengenai jadwal dan petunjuk tahapan selanjutnya akan kami sampaikan melalui portal karir rekrutmen DAW.\n\nSalam,\nTim Rekrutmen DAW",
            'is_default' => true,
        ],
        'tidak_lolos_administrasi' => [
            'id'         => 'tidak_lolos_administrasi',
            'title'      => 'Tidak Lolos Seleksi Administrasi',
            'category'   => 'administrasi hasil',
            'stage'      => 'ADMINISTRASI',
            'subject'    => 'Informasi Hasil Seleksi Administrasi - {{posisi}} - PT Daya Adicipta Wisesa',
            'body'       => "Halo {{nama}},\n\nTerima kasih atas partisipasi dan minat Anda melamar posisi {{posisi}} di PT Daya Adicipta Wisesa ({{dealer}}).\n\nSetelah melakukan peninjauan menyeluruh terhadap kualifikasi, saat ini kami belum dapat melanjutkan proses lamaran Anda ke tahapan berikutnya. Data profil Anda akan tetap tersimpan dalam database kami untuk kesempatan mendatang.\n\nSalam,\nTim Rekrutmen DAW",
            'is_default' => true,
        ],
        'undangan_psikotes' => [
            'id'         => 'undangan_psikotes',
            'title'      => 'Undangan Psikotes',
            'category'   => 'psikotes',
            'stage'      => 'PSIKOTES',
            'subject'    => 'Undangan Tes Psikologi - {{posisi}} - PT Daya Adicipta Wisesa',
            'body'       => "Halo {{nama}},\n\nAnda diundang untuk mengikuti tahapan Tes Psikologi untuk posisi {{posisi}} di PT Daya Adicipta Wisesa ({{dealer}}).\n\nSilakan masuk ke portal karir rekrutmen untuk mengonfirmasi kehadiran dan memilih sesi waktu yang tersedia.\n\nSalam,\nTim Rekrutmen DAW",
            'is_default' => true,
        ],
        'lolos_psikotes' => [
            'id'         => 'lolos_psikotes',
            'title'      => 'Lolos Tes Psikologi',
            'category'   => 'psikotes',
            'stage'      => 'INTERVIEW_HR',
            'subject'    => 'Selamat! Anda Lolos Tes Psikologi - {{posisi}} - PT Daya Adicipta Wisesa',
            'body'       => "Halo {{nama}},\n\nSelamat! Anda telah menyelesaikan Tes Psikologi untuk posisi {{posisi}} di {{dealer}} dan dinyatakan LOLOS ke tahapan berikutnya yaitu Wawancara HR.\n\nJadwal dan detail wawancara akan kami perbarui pada portal karir Anda.\n\nSalam,\nTim Rekrutmen DAW",
            'is_default' => true,
        ],
        'tidak_lolos_psikotes' => [
            'id'         => 'tidak_lolos_psikotes',
            'title'      => 'Tidak Lolos Tes Psikologi',
            'category'   => 'psikotes hasil',
            'stage'      => 'PSIKOTES',
            'subject'    => 'Informasi Hasil Tes Psikologi - {{posisi}} - PT Daya Adicipta Wisesa',
            'body'       => "Halo {{nama}},\n\nTerima kasih atas partisipasi dan waktu yang Anda luangkan dalam mengikuti tahapan Tes Psikologi untuk posisi {{posisi}} di PT Daya Adicipta Wisesa.\n\nBerdasarkan hasil evaluasi, saat ini kami belum dapat memproses lamaran Anda ke tahapan berikutnya. Kami sangat mengapresiasi minat dan kompetensi yang telah Anda tunjukkan.\n\nSalam,\nTim Rekrutmen DAW",
            'is_default' => true,
        ],
        'pengingat_psikotes' => [
            'id'         => 'pengingat_psikotes',
            'title'      => 'Pengingat Psikotes',
            'category'   => 'reminder psikotes',
            'stage'      => 'PSIKOTES',
            'subject'    => 'Pengingat: Tes Psikologi Besok - {{posisi}} - PT Daya Adicipta Wisesa',
            'body'       => "Halo {{nama}},\n\nIni adalah pengingat bahwa jadwal Tes Psikologi Anda untuk posisi {{posisi}} akan dilaksanakan besok sesuai konfirmasi Anda.\n\nPastikan Anda mempersiapkan perangkat dan hadir tepat waktu sesuai konfirmasi yang telah Anda lakukan.\n\nSalam,\nTim Rekrutmen DAW",
            'is_default' => true,
        ],
        'undangan_wawancara_hr' => [
            'id'         => 'undangan_wawancara_hr',
            'title'      => 'Undangan Wawancara HR',
            'category'   => 'wawancara',
            'stage'      => 'INTERVIEW_HR',
            'subject'    => 'Undangan Wawancara HR - {{posisi}} - PT Daya Adicipta Wisesa',
            'body'       => "Halo {{nama}},\n\nKami mengundang Anda untuk mengikuti tahapan Wawancara HR untuk posisi {{posisi}} di PT Daya Adicipta Wisesa ({{dealer}}).\n\nSilakan cek jadwal wawancara Anda di portal karir rekrutmen DAW untuk melihat informasi waktu dan tautan/lokasi wawancara.\n\nSalam,\nTim Rekrutmen DAW",
            'is_default' => true,
        ],
        'lolos_wawancara_hr' => [
            'id'         => 'lolos_wawancara_hr',
            'title'      => 'Lolos Wawancara HR',
            'category'   => 'wawancara',
            'stage'      => 'INTERVIEW_USER',
            'subject'    => 'Selamat! Anda Lolos Wawancara HR - {{posisi}} - PT Daya Adicipta Wisesa',
            'body'       => "Halo {{nama}},\n\nSelamat! Berdasarkan hasil sesi Wawancara HR untuk posisi {{posisi}} di {{dealer}}, Anda dinyatakan LOLOS ke tahapan berikutnya yaitu Wawancara User (Departemen Terkait).\n\nInformasi mengenai jadwal wawancara user akan segera diinformasikan melalui portal rekrutmen DAW.\n\nSalam,\nTim Rekrutmen DAW",
            'is_default' => true,
        ],
        'tidak_lolos_wawancara_hr' => [
            'id'         => 'tidak_lolos_wawancara_hr',
            'title'      => 'Tidak Lolos Wawancara HR',
            'category'   => 'wawancara hasil',
            'stage'      => 'INTERVIEW_HR',
            'subject'    => 'Informasi Hasil Wawancara HR - {{posisi}} - PT Daya Adicipta Wisesa',
            'body'       => "Halo {{nama}},\n\nTerima kasih telah meluangkan waktu untuk hadir dalam sesi Wawancara HR posisi {{posisi}} di PT Daya Adicipta Wisesa.\n\nSetelah mempertimbangkan kesesuaian profil dan kualifikasi saat ini, kami mohon maaf belum dapat melanjutkan proses seleksi Anda ke tahap berikutnya. Kami mendoakan yang terbaik untuk perjalanan karir Anda selanjutnya.\n\nSalam,\nTim Rekrutmen DAW",
            'is_default' => true,
        ],
        'undangan_wawancara_user' => [
            'id'         => 'undangan_wawancara_user',
            'title'      => 'Undangan Wawancara User',
            'category'   => 'wawancara',
            'stage'      => 'INTERVIEW_USER',
            'subject'    => 'Undangan Wawancara User - {{posisi}} - PT Daya Adicipta Wisesa',
            'body'       => "Halo {{nama}},\n\nAnda diundang untuk mengikuti sesi Wawancara User bersama tim User Manager/Departemen untuk posisi {{posisi}} di PT Daya Adicipta Wisesa ({{dealer}}).\n\nDetail teknis dan waktu wawancara dapat Anda lihat melalui menu Tracking Lamaran Anda di portal rekrutmen DAW.\n\nSalam,\nTim Rekrutmen DAW",
            'is_default' => true,
        ],
        'lolos_wawancara_user' => [
            'id'         => 'lolos_wawancara_user',
            'title'      => 'Lolos Wawancara User',
            'category'   => 'wawancara',
            'stage'      => 'FINAL',
            'subject'    => 'Selamat! Anda Lolos Wawancara User - {{posisi}} - PT Daya Adicipta Wisesa',
            'body'       => "Halo {{nama}},\n\nSelamat! Anda telah menyelesaikan Wawancara User untuk posisi {{posisi}} di {{dealer}} dengan hasil baik dan dinyatakan LOLOS ke tahapan Keputusan Akhir.\n\nTim HR kami akan memproses tahap finalisasi dan offering.\n\nSalam,\nTim Rekrutmen DAW",
            'is_default' => true,
        ],
        'pengumuman_diterima' => [
            'id'         => 'pengumuman_diterima',
            'title'      => 'Pengumuman Diterima',
            'category'   => 'hasil',
            'stage'      => 'DITERIMA',
            'subject'    => 'Selamat! Anda Diterima Bergabung di PT Daya Adicipta Wisesa - {{posisi}}',
            'body'       => "Halo {{nama}},\n\nSelamat! Berdasarkan seluruh rangkaian proses seleksi yang telah Anda lalui, dengan bangga kami informasikan bahwa Anda dinyatakan DITERIMA untuk bergabung dengan keluarga besar PT Daya Adicipta Wisesa pada posisi {{posisi}} ({{dealer}}).\n\nDokumen penawaran kerja (Offering Letter) dan informasi kelengkapan berkas orientasi akan dikirimkan lebih lanjut oleh tim HR.\n\nSelamat bergabung dan mari bertumbuh bersama DAW!\n\nSalam hormat,\nTim Rekrutmen DAW",
            'is_default' => true,
        ],
        'pengumuman_ditolak' => [
            'id'         => 'pengumuman_ditolak',
            'title'      => 'Informasi Hasil Akhir',
            'category'   => 'hasil',
            'stage'      => 'DITOLAK',
            'subject'    => 'Informasi Hasil Akhir Seleksi - {{posisi}} - PT Daya Adicipta Wisesa',
            'body'       => "Halo {{nama}},\n\nTerima kasih yang sebesar-besarnya atas komitmen, antusiasme, dan waktu yang telah Anda berikan selama mengikuti rangkaian seleksi posisi {{posisi}} di PT Daya Adicipta Wisesa.\n\nSetelah pertimbangan mendalam di antara para kandidat, saat ini kami memutuskan untuk belum dapat menawarkan posisi tersebut kepada Anda. Profil Anda akan tetap menjadi bagian dari database kami untuk peluang mendatang.\n\nSalam sukses,\nTim Rekrutmen DAW",
            'is_default' => true,
        ],
    ];
}

/**
 * Retrieve all email templates (defaults merged with custom/edited templates).
 *
 * @return array<string, array{id: string, title: string, category: string, stage: string, subject: string, body: string, is_default: bool}>
 */
function recruitment_get_email_templates(): array {
    $custom = get_option( 'recruitment_email_templates', null );
    $defaults = recruitment_get_default_email_templates();
    if ( ! is_array( $custom ) ) {
        return $defaults;
    }
    return array_merge( $defaults, $custom );
}

/**
 * Save (create or update) an email template.
 *
 * @param array<string, mixed> $data
 * @return bool
 */
function recruitment_save_email_template( array $data ): bool {
    $id = sanitize_key( (string) ( $data['id'] ?? '' ) );
    if ( '' === $id ) {
        $id = 'tpl_' . time() . '_' . wp_rand( 100, 999 );
    }

    $title    = sanitize_text_field( (string) ( $data['title'] ?? 'Template Baru' ) );
    $category = sanitize_text_field( (string) ( $data['category'] ?? 'lamaran' ) );
    $stage    = strtoupper( sanitize_key( (string) ( $data['stage'] ?? 'ADMINISTRASI' ) ) );
    $subject  = sanitize_text_field( (string) ( $data['subject'] ?? '' ) );
    $body     = sanitize_textarea_field( (string) ( $data['body'] ?? '' ) );

    $current_templates = get_option( 'recruitment_email_templates', [] );
    if ( ! is_array( $current_templates ) ) {
        $current_templates = [];
    }

    $defaults = recruitment_get_default_email_templates();
    $is_default = isset( $defaults[ $id ] );

    $current_templates[ $id ] = [
        'id'         => $id,
        'title'      => $title,
        'category'   => $category,
        'stage'      => $stage,
        'subject'    => $subject,
        'body'       => $body,
        'is_default' => $is_default,
    ];

    return update_option( 'recruitment_email_templates', $current_templates );
}

/**
 * Delete a custom email template. Default templates cannot be deleted.
 *
 * @param string $template_id
 * @return bool
 */
function recruitment_delete_email_template( string $template_id ): bool {
    $id = sanitize_key( $template_id );
    $defaults = recruitment_get_default_email_templates();
    if ( isset( $defaults[ $id ] ) ) {
        // Reset default template by removing override from option
        $custom = get_option( 'recruitment_email_templates', [] );
        if ( is_array( $custom ) && isset( $custom[ $id ] ) ) {
            unset( $custom[ $id ] );
            return update_option( 'recruitment_email_templates', $custom );
        }
        return false;
    }

    $current = get_option( 'recruitment_email_templates', [] );
    if ( is_array( $current ) && isset( $current[ $id ] ) ) {
        unset( $current[ $id ] );
        return update_option( 'recruitment_email_templates', $current );
    }

    return false;
}

/**
 * Replace template placeholder tags with actual candidate/vacancy values.
 *
 * @param string $text
 * @param array<string, string> $vars
 * @return string
 */
function recruitment_replace_template_placeholders( string $text, array $vars ): string {
    $replacements = [
        '{{nama}}'    => $vars['nama'] ?? $vars['name'] ?? 'Pelamar',
        '{{posisi}}'  => $vars['posisi'] ?? $vars['position'] ?? $vars['title'] ?? 'Posisi Terkait',
        '{{dealer}}'  => $vars['dealer'] ?? $vars['location'] ?? 'PT Daya Adicipta Wisesa',
        '{{tahap}}'   => $vars['tahap'] ?? $vars['stage'] ?? '',
        '{{tanggal}}' => $vars['tanggal'] ?? wp_date( 'j F Y' ),
    ];

    return str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
}

/**
 * Build a complete DAW-branded HTML email message.
 *
 * @param string $subject
 * @param string $body
 * @param string $stage_badge
 * @return string
 */
function recruitment_build_status_email_html( string $subject, string $body, string $stage_badge = '' ): string {
    $body_html = nl2br( esc_html( $body ) );
    $badge_html = '';
    if ( '' !== trim( $stage_badge ) ) {
        $badge_html = '<div style="margin-bottom:20px;padding:12px 16px;background:#fef2f2;border-left:4px solid #c8102e;border-radius:6px;">'
            . '<span style="font-size:12px;font-weight:700;color:#c8102e;text-transform:uppercase;letter-spacing:0.5px;">Status Lamaran:</span> '
            . '<strong style="font-size:14px;color:#111827;">' . esc_html( $stage_badge ) . '</strong>'
            . '</div>';
    }
    $tracking_url = home_url( '/career/?recruitment_page=applicant-tracking' );

    return '<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html( $subject ) . '</title></head>
<body style="margin:0;padding:32px 16px;background:#f3f4f6;color:#111827;font-family:Arial,Helvetica,sans-serif;-webkit-font-smoothing:antialiased;">
    <table role="presentation" style="width:100%;max-width:620px;margin:0 auto;border-collapse:separate;border-spacing:0;background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
        <tr>
            <td style="padding:22px 28px;background:#c8102e;color:#fff;">
                <div style="font-size:18px;font-weight:800;letter-spacing:.04em;display:inline-block;">DAW <span style="color:#fee2e2;font-weight:400;font-size:14px;">| REKRUTMEN</span></div>
            </td>
        </tr>
        <tr>
            <td style="padding:32px 28px 24px;">
                ' . $badge_html . '
                <div style="font-size:14px;line-height:1.7;color:#374151;">
                    ' . $body_html . '
                </div>
                <div style="margin-top:28px;padding-top:20px;border-top:1px solid #f3f4f6;text-align:center;">
                    <a href="' . esc_url( $tracking_url ) . '" style="display:inline-block;background:#c8102e;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;padding:12px 28px;border-radius:8px;box-shadow:0 2px 4px rgba(200,16,46,0.25);">Masuk ke Tracking Lamaran</a>
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding:18px 28px;background:#f9fafb;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px;line-height:1.5;">
                <strong>PT Daya Adicipta Wisesa</strong><br>
                Jl. Raya Manado-Bitung KM 9.5, Watutumou II, Kalawat, Minahasa Utara, Sulawesi Utara.<br>
                <span style="color:#9ca3af;font-size:11px;">Email ini dikirim otomatis terkait proses seleksi Anda. Mohon tidak membalas email ini secara langsung.</span>
            </td>
        </tr>
    </table>
</body>
</html>';
}
