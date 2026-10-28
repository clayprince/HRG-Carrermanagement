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
    return RECRUITMENT_PLUGIN_URL . ltrim( $path, '/' );
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

function recruitment_is_ui_preview(): bool {
    if ( '1' !== sanitize_text_field( wp_unslash( $_GET['daw_ui_preview'] ?? '' ) ) ) {
        return false;
    }

    if ( ( defined( 'RECRUITMENT_SANDBOX' ) && RECRUITMENT_SANDBOX )
        || ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) ) {
        return true;
    }

    $preview_screens = [
        'careers',
        'job-detail',
        'application',
        'application-form',
        'application-confirmation',
        'applicant-login',
        'applicant-register',
        'account-confirmation',
        'account-profile',
        'application-history',
        'application-detail',
        'application-process',
        'application-status',
        'tracking-detail',
        'psych-test',
        'psychotest',
        'psychotest-preparation',
        'psychotest-device',
        'interview-booking',
    ];
    $screen = sanitize_key( wp_unslash( $_GET['recruitment_page'] ?? '' ) );

    return in_array( $screen, $preview_screens, true );
}

function recruitment_is_applicant_user(): bool {
    if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() || ! function_exists( 'wp_get_current_user' ) ) {
        return false;
    }

    $user = wp_get_current_user();
    return in_array( 'daw_applicant', (array) ( $user->roles ?? [] ), true );
}

/**
 * Return the public vacancy list used by careers, detail, and application pages.
 *
 * @return array<int, array<string, mixed>>
 */
function recruitment_get_public_jobs(): array {
    $fallback_jobs = [
        [ 'id' => '1', 'title' => 'Sales Executive', 'location' => 'Airmadidi', 'dealer' => 'DAW Airmadidi', 'region' => 'Sulawesi Utara', 'type' => 'Full Time', 'description' => 'Bergabunglah dengan tim penjualan DAW dan jadilah bagian dari keluarga Honda yang terus berkembang.', 'deadline' => '30 September 2026', 'postedDate' => '1 Agustus 2026', 'requirements' => [ 'Pendidikan minimal D3 semua jurusan', 'Memiliki kemampuan komunikasi yang baik' ] ],
        [ 'id' => '2', 'title' => 'Service Advisor', 'location' => 'Manado', 'dealer' => 'DAW Manado', 'region' => 'Sulawesi Utara', 'type' => 'Full Time', 'description' => 'Menjadi penghubung antara pelanggan dan bengkel serta memastikan setiap kendaraan mendapatkan penanganan terbaik.', 'deadline' => '30 September 2026', 'postedDate' => '1 Agustus 2026', 'requirements' => [ 'Pendidikan minimal D3 Teknik Otomotif atau terkait', 'Berpengalaman sebagai Service Advisor min. 2 tahun' ] ],
        [ 'id' => '3', 'title' => 'Kepala Bengkel / Foreman', 'location' => 'Gorontalo', 'dealer' => 'AHASS Gorontalo', 'region' => 'Gorontalo', 'type' => 'Full Time', 'description' => 'Memimpin operasional bengkel AHASS dan memastikan semua pekerjaan servis berjalan sesuai standar Honda.', 'deadline' => '30 September 2026', 'postedDate' => '1 Agustus 2026', 'requirements' => [ 'Pendidikan minimal SMK Teknik Otomotif', 'Pengalaman sebagai mekanik min. 3 tahun' ] ],
        [ 'id' => '4', 'title' => 'Marketing Coordinator', 'location' => 'Ternate', 'dealer' => 'DAW Ternate', 'region' => 'Maluku Utara', 'type' => 'Full Time', 'description' => 'Mengelola kegiatan marketing dan promosi untuk meningkatkan brand awareness Honda di wilayah Maluku Utara.', 'deadline' => '30 September 2026', 'postedDate' => '1 Agustus 2026', 'requirements' => [ 'Pendidikan minimal S1 Marketing / Komunikasi', 'Pengalaman di bidang marketing min. 2 tahun' ] ],
        [ 'id' => '5', 'title' => 'Finance & Accounting Staff', 'location' => 'Manado', 'dealer' => 'DAW Head Office', 'region' => 'Sulawesi Utara', 'type' => 'Full Time', 'description' => 'Bertanggung jawab dalam pengelolaan keuangan dan laporan akuntansi perusahaan.', 'deadline' => '30 September 2026', 'postedDate' => '1 Agustus 2026', 'requirements' => [ 'Pendidikan minimal S1 Akuntansi / Keuangan', 'Berpengalaman min. 1 tahun di bidang keuangan' ] ],
        [ 'id' => '6', 'title' => 'Mekanik / Teknisi Motor', 'location' => 'Kotamobagu', 'dealer' => 'AHASS Kotamobagu', 'region' => 'Sulawesi Utara', 'type' => 'Full Time', 'description' => 'Melakukan perawatan dan perbaikan kendaraan Honda sesuai standar layanan AHASS.', 'deadline' => '30 September 2026', 'postedDate' => '1 Agustus 2026', 'requirements' => [ 'Pendidikan minimal SMK Teknik Otomotif', 'Memiliki sertifikat pelatihan Honda' ] ],
    ];

    $vacancies = get_posts( [ 'post_type' => 'daw_vacancy', 'post_status' => 'publish', 'numberposts' => -1 ] );
    if ( ! $vacancies ) {
        return $fallback_jobs;
    }

    $jobs = [];
    foreach ( $vacancies as $vacancy ) {
        $jobs[] = recruitment_map_vacancy( $vacancy );
    }

    return $jobs;
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
    return [
        'id' => (string) $vacancy->ID,
        'title' => get_the_title( $vacancy ),
        'location' => (string) get_post_meta( $vacancy->ID, '_daw_location', true ),
        'dealer' => (string) get_post_meta( $vacancy->ID, '_daw_dealer', true ),
        'region' => (string) get_post_meta( $vacancy->ID, '_daw_region', true ),
        'type' => (string) get_post_meta( $vacancy->ID, '_daw_type', true ),
        'description' => wp_trim_words( wp_strip_all_tags( $vacancy->post_content ), 24 ),
        'deadline' => $deadline ?: 'Belum ditentukan',
        'postedDate' => get_the_date( 'j F Y', $vacancy ),
        'requirements' => [ 'Lihat detail lowongan untuk persyaratan lengkap.' ],
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
