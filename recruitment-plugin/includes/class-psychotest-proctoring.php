<?php

/**
 * Handles consent, private evidence capture, and limited browser-observable alerts.
 */
class Recruitment_Psychotest_Proctoring {

    private Recruitment_Database $database;

    public function __construct() {
        $this->database = new Recruitment_Database();
    }

    public function init(): void {
        add_action( 'admin_post_daw_proctoring_identity', [ $this, 'handle_identity_submission' ] );
        add_action( 'admin_post_nopriv_daw_proctoring_identity', [ $this, 'handle_identity_submission' ] );
        add_action( 'admin_post_daw_proctoring_image', [ $this, 'handle_image_request' ] );
        add_action( 'admin_post_daw_proctoring_review', [ $this, 'handle_manual_review' ] );
        add_action( 'wp_ajax_recruitment_proctoring_event', [ $this, 'handle_event' ] );
        add_action( 'wp_ajax_nopriv_recruitment_proctoring_event', [ $this, 'handle_event' ] );
        add_action( 'template_redirect', [ $this, 'guard_active_assessment' ], 1 );
        add_action( 'init', [ $this, 'schedule_retention_cleanup' ] );
        add_action( 'daw_psychotest_proctoring_cleanup', [ $this, 'cleanup_expired_evidence' ] );
    }

    public function save_consent(): bool {
        $context = $this->get_context();
        return null !== $context
            && $this->database->create_proctoring_session( $context['candidate_id'], $context['session_id'] );
    }

    public function is_secure_transport(): bool {
        if ( is_ssl() ) {
            return true;
        }
        $host_header = is_scalar( $_SERVER['HTTP_HOST'] ?? null ) ? (string) $_SERVER['HTTP_HOST'] : '';
        $host = strtolower( (string) parse_url( 'http://' . $host_header, PHP_URL_HOST ) );
        if ( in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true ) ) {
            return true;
        }
        return (bool) preg_match( '/\.(test|local|example|invalid)$/i', $host );
    }

    /** @return array<string, mixed>|null */
    public function get_current_session(): ?array {
        $context = $this->get_context();
        if ( null === $context ) {
            return null;
        }
        return $this->database->get_proctoring_session( $context['candidate_id'], $context['session_id'] );
    }

    public function get_current_session_error(): bool {
        return $this->database->has_error();
    }

    public function has_current_consent(): bool {
        $session = $this->get_current_session();
        return ! $this->database->has_error()
            && is_array( $session )
            && '' !== (string) ( $session['consent_at'] ?? '' )
            && in_array( (string) ( $session['identity_status'] ?? '' ), [ 'CAPTURED', 'MANUAL_REVIEW', 'MANUAL_VERIFIED' ], true );
    }

    public function mark_completed(): bool {
        $context = $this->get_context();
        return null !== $context
            && $this->database->mark_proctoring_completed( $context['candidate_id'], $context['session_id'] );
    }

    public function guard_active_assessment(): void {
        $page = sanitize_key( wp_unslash( $_GET['recruitment_page'] ?? '' ) );
        $assessment_keys = [
            'psych-iq' => [ 'recruitment_iq_assessment', 'iq' ],
            'psych-pauli' => [ 'recruitment_pauli_assessment', 'pauli' ],
            'psych-personality' => [ 'recruitment_personality_assessment', '' ],
        ];
        if ( ! isset( $assessment_keys[ $page ] ) ) {
            return;
        }

        $user_id = recruitment_get_assessment_user_id();
        $state = recruitment_get_assessment_state( $user_id, $assessment_keys[ $page ][0] );
        $requested_test = sanitize_key( wp_unslash( $_GET['test'] ?? 'disc' ) );
        $active = is_array( $state ) && 'active' === ( $state['status'] ?? '' );
        if ( 'psych-personality' === $page ) {
            $active = is_array( $state )
                && 'active' === ( $state['tests'][ $requested_test ]['status'] ?? '' )
                && $requested_test === ( $state['active_test'] ?? '' );
        }
        if ( ! $active ) {
            return;
        }

        $context = $this->get_context();
        $session = null === $context
            ? null
            : $this->database->get_proctoring_session( $context['candidate_id'], $context['session_id'] );
        if ( $this->database->has_error() ) {
            wp_die( esc_html__( 'Status persetujuan pengawasan tidak dapat dibaca. Tes dihentikan sampai koneksi Oracle tersedia.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
        }
        if ( ! is_array( $session ) || ! in_array( (string) ( $session['identity_status'] ?? '' ), [ 'CAPTURED', 'MANUAL_REVIEW', 'MANUAL_VERIFIED' ], true ) ) {
            $test_key = '' !== $assessment_keys[ $page ][1] ? $assessment_keys[ $page ][1] : ( in_array( $requested_test, [ 'disc', 'papi' ], true ) ? $requested_test : 'disc' );
            wp_safe_redirect( recruitment_get_public_url( 'psychotest-device', [ 'test' => $test_key ] ) );
            exit;
        }
    }

    public function handle_identity_submission(): void {
        check_admin_referer( 'daw_proctoring_identity' );
        $is_ajax = wp_doing_ajax()
            || ( isset( $_SERVER['HTTP_ACCEPT'] ) && false !== strpos( (string) $_SERVER['HTTP_ACCEPT'], 'application/json' ) )
            || ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && 'xmlhttprequest' === strtolower( (string) $_SERVER['HTTP_X_REQUESTED_WITH'] ) );

        $fail = function( string $message, int $status_code = 400 ) use ( $is_ajax ) {
            if ( $is_ajax ) {
                wp_send_json_error( [ 'message' => $message ], $status_code );
            }
            wp_die( esc_html( $message ), '', [ 'response' => $status_code ] );
        };

        if ( ! $this->is_secure_transport() ) {
            $fail( __( 'Kamera dan pengiriman bukti hanya diizinkan melalui HTTPS atau localhost.', 'recruitment-plugin' ), 403 );
        }
        $context = $this->get_context();
        if ( null === $context ) {
            $fail( __( 'Sesi pelamar tidak valid.', 'recruitment-plugin' ), 403 );
        }

        $test_key = sanitize_key( wp_unslash( $_POST['test'] ?? '' ) );
        $test_routes = [
            'iq' => 'psych-iq',
            'pauli' => 'psych-pauli',
            'disc' => 'psych-personality',
            'papi' => 'psych-personality',
        ];
        if ( ! isset( $test_routes[ $test_key ] ) ) {
            $fail( __( 'Jenis tes tidak valid.', 'recruitment-plugin' ), 400 );
        }

        $session = $this->database->get_proctoring_session( $context['candidate_id'], $context['session_id'] );
        if ( $this->database->has_error() ) {
            $fail( __( 'Data persetujuan tidak dapat dibaca dari Oracle.', 'recruitment-plugin' ), 503 );
        }
        if ( ! is_array( $session ) ) {
            $this->database->create_proctoring_session( $context['candidate_id'], $context['session_id'] );
            $session = $this->database->get_proctoring_session( $context['candidate_id'], $context['session_id'] );
        }

        $action = sanitize_key( wp_unslash( $_POST['proctoring_action'] ?? '' ) );
        if ( 'capture' === $action ) {
            $stored_document = $this->store_capture_upload();
            if ( null === $stored_document ) {
                $fail( __( 'Foto tidak dapat diproses atau gagal disimpan. Aktifkan kamera dan ambil foto kembali.', 'recruitment-plugin' ), 400 );
            }
            $saved = $this->database->save_proctoring_identity(
                $context['candidate_id'],
                $context['session_id'],
                $stored_document['key'],
                false
            );
            if ( ! $saved ) {
                ( new Recruitment_Applicant_Profile() )->delete_documents( [ $stored_document ] );
                $fail( __( 'Foto identitas gagal dicatat di Oracle. Silakan coba kembali.', 'recruitment-plugin' ), 503 );
            }
        } elseif ( 'manual' === $action ) {
            if ( ! $this->database->save_proctoring_identity( $context['candidate_id'], $context['session_id'], null, true ) ) {
                $fail( __( 'Permintaan verifikasi manual gagal dicatat. Silakan coba kembali.', 'recruitment-plugin' ), 503 );
            }
        } elseif ( 'continue' !== $action || ! in_array( (string) ( $session['identity_status'] ?? '' ), [ 'CAPTURED', 'MANUAL_REVIEW', 'MANUAL_VERIFIED' ], true ) ) {
            $fail( __( 'Selesaikan pengambilan foto atau pilih verifikasi manual terlebih dahulu.', 'recruitment-plugin' ), 409 );
        }

        if ( ! recruitment_grant_psychotest_consent( $test_key ) ) {
            $fail( __( 'Persetujuan tes tidak dapat disimpan. Muat ulang halaman dan coba kembali.', 'recruitment-plugin' ), 500 );
        }
        $arguments = [ 'start' => '1' ];
        if ( 'psych-personality' === $test_routes[ $test_key ] ) {
            $arguments['test'] = $test_key;
        }
        $target_url = recruitment_get_public_url( $test_routes[ $test_key ], $arguments );

        if ( $is_ajax ) {
            wp_send_json_success( [ 'redirect' => $target_url ] );
        }
        wp_safe_redirect( $target_url );
        exit;
    }

    public function handle_event(): void {
        check_ajax_referer( 'recruitment_proctoring_event', 'nonce' );
        if ( ! $this->is_secure_transport() ) {
            wp_send_json_error( [ 'message' => 'Pengawasan kamera memerlukan HTTPS atau localhost.' ], 403 );
        }
        $context = $this->get_context();
        if ( null === $context ) {
            wp_send_json_error( [ 'message' => 'Sesi pelamar tidak valid.' ], 403 );
        }

        $session = $this->database->get_proctoring_session( $context['candidate_id'], $context['session_id'] );
        if ( $this->database->has_error() ) {
            wp_send_json_error( [ 'message' => 'Data sesi pengawasan tidak dapat dibaca dari Oracle.' ], 503 );
        }
        if ( ! is_array( $session ) || ! in_array( (string) ( $session['identity_status'] ?? '' ), [ 'CAPTURED', 'MANUAL_REVIEW', 'MANUAL_VERIFIED' ], true ) ) {
            wp_send_json_error( [ 'message' => 'Persetujuan pengawasan belum aktif.' ], 403 );
        }

        $event_type = strtoupper( sanitize_key( wp_unslash( $_POST['event_type'] ?? '' ) ) );
        $file = $_FILES['snapshot'] ?? null;
        $stored_document = is_array( $file ) ? $this->store_capture_upload() : null;
        if ( is_array( $file ) && null === $stored_document ) {
            wp_send_json_error( [ 'message' => 'Snapshot tidak valid atau gagal disimpan.' ], 400 );
        }

        $result = $this->database->save_proctoring_event(
            $context['candidate_id'],
            $context['session_id'],
            $event_type,
            is_array( $stored_document ) ? $stored_document['key'] : null
        );
        if ( empty( $result['success'] ) ) {
            if ( is_array( $stored_document ) ) {
                ( new Recruitment_Applicant_Profile() )->delete_documents( [ $stored_document ] );
            }
            wp_send_json_error( [ 'message' => 'Peringatan pengawasan gagal disimpan.' ], 503 );
        }
        if ( is_array( $stored_document ) && empty( $result['captured'] ) ) {
            ( new Recruitment_Applicant_Profile() )->delete_documents( [ $stored_document ] );
        }
        wp_send_json_success( [ 'captured' => ! empty( $result['captured'] ) ] );
    }

    public function handle_image_request(): void {
        $candidate_id = absint( $_GET['candidate_id'] ?? 0 );
        $session_id = sanitize_text_field( wp_unslash( $_GET['session_id'] ?? '' ) );
        $event_id = absint( $_GET['event_id'] ?? 0 );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Data bukti hanya dapat diakses oleh Administrator.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
        if ( ! $this->is_secure_transport() ) {
            wp_die( esc_html__( 'Akses bukti hanya diizinkan melalui HTTPS atau localhost.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
        check_admin_referer( 'daw_proctoring_image_' . $candidate_id );
        $event = $this->database->get_proctoring_image_record( $candidate_id, $session_id, $event_id );
        if ( $this->database->has_error() ) {
            wp_die( esc_html__( 'Bukti tidak dapat dibaca dari Oracle.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
        }
        if ( ! is_array( $event ) ) {
            wp_die( esc_html__( 'Bukti tidak ditemukan.', 'recruitment-plugin' ), '', [ 'response' => 404 ] );
        }
        if ( ! $this->database->log_proctoring_image_access( $session_id, get_current_user_id(), $event_id ) ) {
            wp_die( esc_html__( 'Akses bukti gagal dicatat di Oracle.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
        }
        $document_path = ( new Recruitment_Applicant_Profile() )->document_path( [ 'key' => $event['file_key'] ?? '' ] );
        if ( null === $document_path || ! is_file( $document_path ) || ! is_readable( $document_path ) ) {
            wp_die( esc_html__( 'Berkas bukti tidak tersedia.', 'recruitment-plugin' ), '', [ 'response' => 404 ] );
        }
        if ( ! function_exists( 'finfo_open' ) ) {
            wp_die( esc_html__( 'Pemeriksaan keamanan berkas tidak tersedia.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
        }
        $file_info = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = false === $file_info ? false : finfo_file( $file_info, $document_path );
        if ( false !== $file_info ) {
            finfo_close( $file_info );
        }
        if ( 'image/jpeg' !== $mime_type ) {
            wp_die( esc_html__( 'Format bukti tidak valid.', 'recruitment-plugin' ), '', [ 'response' => 415 ] );
        }

        nocache_headers();
        header( 'Content-Type: image/jpeg' );
        $disposition = ( isset( $_GET['disposition'] ) && 'inline' === $_GET['disposition'] ) ? 'inline' : 'attachment';
        header( 'Content-Disposition: ' . $disposition . '; filename="psychotest-evidence-' . $event_id . '.jpg"' );
        header( 'X-Content-Type-Options: nosniff' );
        readfile( $document_path );
        exit;
    }

    public function handle_manual_review(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Verifikasi manual hanya dapat dilakukan oleh Administrator.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
        if ( ! $this->is_secure_transport() ) {
            wp_die( esc_html__( 'Verifikasi hanya diizinkan melalui HTTPS atau localhost.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
        $candidate_id = absint( $_POST['candidate_id'] ?? 0 );
        $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
        check_admin_referer( 'daw_proctoring_review_' . $session_id );
        if ( ! $this->database->review_proctoring_identity( $candidate_id, $session_id, get_current_user_id() ) ) {
            wp_die( esc_html__( 'Verifikasi identitas manual gagal disimpan.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
        }
        wp_safe_redirect( wp_get_referer() ?: recruitment_get_admin_url( 'psychotest-results' ) );
        exit;
    }

    public function schedule_retention_cleanup(): void {
        if ( ! wp_next_scheduled( 'daw_psychotest_proctoring_cleanup' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'daw_psychotest_proctoring_cleanup' );
        }
    }

    public function cleanup_expired_evidence(): void {
        $expired = $this->database->get_expired_proctoring_sessions();
        if ( $this->database->has_error() ) {
            error_log( 'Psychotest proctoring retention lookup failed.' );
            return;
        }
        $profile = new Recruitment_Applicant_Profile();
        foreach ( $expired as $session ) {
            $files_deleted = true;
            foreach ( $session['file_keys'] as $file_key ) {
                $path = $profile->document_path( [ 'key' => $file_key ] );
                if ( null === $path || ( is_file( $path ) && ! unlink( $path ) ) ) {
                    $files_deleted = false;
                }
            }
            if ( ! $files_deleted ) {
                error_log( 'Psychotest proctoring evidence cleanup failed; metadata was retained.' );
                continue;
            }
            if ( ! $this->database->delete_expired_proctoring_session( $session['session_id'] ) ) {
                error_log( 'Psychotest proctoring expired metadata cleanup failed.' );
            }
        }
    }

    /**
     * @return array{candidate_id: int, session_id: string}|null
     */
    private function get_context(): ?array {
        $applicant = recruitment_get_current_applicant();
        if ( ! is_array( $applicant ) || ! ctype_digit( (string) ( $applicant['profile_id'] ?? '' ) ) ) {
            return null;
        }
        $candidate_id = absint( $applicant['profile_id'] );
        if ( 1 > $candidate_id ) {
            return null;
        }
        return [
            'candidate_id' => $candidate_id,
            'session_id' => hash_hmac( 'sha256', 'psychotest-proctoring:' . $candidate_id, wp_salt( 'auth' ) ),
        ];
    }

    /** @return array<string, string>|null */
    private function store_capture_upload(): ?array {
        $file = $_FILES['snapshot'] ?? null;
        if ( ! is_array( $file ) ) {
            return null;
        }
        $file['name'] = 'capture.jpg';
        return ( new Recruitment_Applicant_Profile() )->store_uploaded_document( $file, 'proctoring_capture' );
    }
}
