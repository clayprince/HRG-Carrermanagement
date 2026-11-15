<?php

/**
 * Handles consent, private evidence capture, and limited browser-observable alerts.
 */
class Recruitment_Psychotest_Proctoring {

    public const CONSENT_VERSION = 'proctoring-v2';
    private const VIOLATION_CONFIG_OPTION = 'recruitment_proctoring_violation_config';

    private Recruitment_Database $database;
    private bool $application_context_error = false;

    public function __construct() {
        $this->database = new Recruitment_Database();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function get_default_violation_config(): array {
        return [
            'FACE_NOT_DETECTED' => [
                'enabled' => true,
                'threshold_ms' => 5000,
                'grace_ms' => 1000,
                'confidence_threshold' => 0.65,
                'signal_threshold' => null,
                'recovery_ms' => 1500,
                'cooldown_ms' => 30000,
                'severity' => 'warning',
                'warning_message' => 'Wajah tidak terlihat selama beberapa saat. Silakan kembali menghadap layar.',
                'repeat_warning_count' => 3,
                'repeat_warning_window_ms' => 120000,
                'repeat_serious_count' => 5,
                'repeat_serious_window_ms' => 300000,
            ],
            'MULTIPLE_FACES' => [
                'enabled' => true,
                'threshold_ms' => 3000,
                'grace_ms' => 750,
                'confidence_threshold' => 0.65,
                'signal_threshold' => null,
                'recovery_ms' => 1500,
                'cooldown_ms' => 30000,
                'severity' => 'warning',
                'warning_message' => 'Lebih dari satu wajah terlihat. Pastikan hanya peserta yang berada di area tes.',
                'repeat_warning_count' => 3,
                'repeat_warning_window_ms' => 120000,
                'repeat_serious_count' => 5,
                'repeat_serious_window_ms' => 300000,
            ],
            'FACE_TURNED_AWAY' => [
                'enabled' => true,
                'threshold_ms' => 5000,
                'grace_ms' => 1000,
                'confidence_threshold' => 0.65,
                'signal_threshold' => 0.3,
                'recovery_ms' => 1500,
                'cooldown_ms' => 30000,
                'severity' => 'warning',
                'warning_message' => 'Arah wajah terdeteksi menyamping cukup lama. Silakan kembali menghadap layar.',
                'repeat_warning_count' => 3,
                'repeat_warning_window_ms' => 120000,
                'repeat_serious_count' => 5,
                'repeat_serious_window_ms' => 300000,
            ],
            'CAMERA_STOPPED' => [
                'enabled' => true,
                'threshold_ms' => 5000,
                'grace_ms' => 1000,
                'confidence_threshold' => 0.65,
                'signal_threshold' => null,
                'recovery_ms' => 1500,
                'cooldown_ms' => 30000,
                'severity' => 'warning',
                'warning_message' => 'Kamera terputus. Aktifkan kembali kamera untuk melanjutkan tes.',
                'repeat_warning_count' => 3,
                'repeat_warning_window_ms' => 120000,
                'repeat_serious_count' => 5,
                'repeat_serious_window_ms' => 300000,
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function get_violation_config(): array {
        $stored = get_option( self::VIOLATION_CONFIG_OPTION, [] );
        return self::sanitize_violation_config( is_array( $stored ) ? $stored : [] );
    }

    /**
     * @param array<string, mixed> $posted
     * @return array<string, array<string, mixed>>
     */
    public static function sanitize_violation_config( array $posted ): array {
        $defaults = self::get_default_violation_config();
        $milliseconds = static function ( array $values, string $key, int $default ): int {
            $seconds_key = $key . '_seconds';
            if ( isset( $values[ $seconds_key ] ) && is_numeric( $values[ $seconds_key ] ) ) {
                return (int) round( (float) $values[ $seconds_key ] * 1000 );
            }
            return absint( $values[ $key . '_ms' ] ?? $default );
        };
        $config = [];
        foreach ( $defaults as $event_type => $default ) {
            $values = isset( $posted[ $event_type ] ) && is_array( $posted[ $event_type ] )
                ? $posted[ $event_type ]
                : [];
            $threshold_ms = $milliseconds( $values, 'threshold', (int) $default['threshold_ms'] );
            $grace_ms = $milliseconds( $values, 'grace', (int) $default['grace_ms'] );
            $warning_count = absint( $values['repeat_warning_count'] ?? $default['repeat_warning_count'] );
            $serious_count = absint( $values['repeat_serious_count'] ?? $default['repeat_serious_count'] );
            $confidence_threshold = isset( $values['confidence_percent'] ) && is_numeric( $values['confidence_percent'] )
                ? (float) $values['confidence_percent'] / 100
                : (float) ( $values['confidence_threshold'] ?? $default['confidence_threshold'] );
            $signal_threshold = isset( $values['signal_threshold'] ) && is_numeric( $values['signal_threshold'] )
                ? (float) $values['signal_threshold']
                : $default['signal_threshold'];
            $config[ $event_type ] = [
                'enabled' => array_key_exists( $event_type, $posted )
                    ? ! empty( $values['enabled'] )
                    : (bool) $default['enabled'],
                'threshold_ms' => min( 60000, max( 1000, $threshold_ms ) ),
                'grace_ms' => min( 5000, min( $threshold_ms, $grace_ms ) ),
                'confidence_threshold' => min( 1, max( 0.1, $confidence_threshold ) ),
                'signal_threshold' => null === $signal_threshold ? null : min( 1, max( 0.05, $signal_threshold ) ),
                'recovery_ms' => min( 10000, max( 250, $milliseconds( $values, 'recovery', (int) $default['recovery_ms'] ) ) ),
                'cooldown_ms' => min( 300000, $milliseconds( $values, 'cooldown', (int) $default['cooldown_ms'] ) ),
                'severity' => in_array( $values['severity'] ?? '', [ 'warning', 'repeated', 'serious' ], true )
                    ? $values['severity']
                    : $default['severity'],
                'warning_message' => '' !== trim( sanitize_text_field( (string) ( $values['warning_message'] ?? $default['warning_message'] ) ) )
                    ? sanitize_text_field( (string) ( $values['warning_message'] ?? $default['warning_message'] ) )
                    : $default['warning_message'],
                'repeat_warning_count' => min( 20, max( 2, $warning_count ) ),
                'repeat_warning_window_ms' => min( 1800000, max( 10000, $milliseconds( $values, 'repeat_warning_window', (int) $default['repeat_warning_window_ms'] ) ) ),
                'repeat_serious_count' => min( 30, max( $warning_count + 1, $serious_count ) ),
                'repeat_serious_window_ms' => min( 3600000, max( 10000, $milliseconds( $values, 'repeat_serious_window', (int) $default['repeat_serious_window_ms'] ) ) ),
            ];
        }
        return $config;
    }

    /**
     * @param array<string, mixed> $posted
     */
    public function save_violation_config( array $posted ): bool {
        if ( ! current_user_can( 'manage_recruitment' ) ) {
            return false;
        }
        $validated = self::sanitize_violation_config( $posted );
        if ( get_option( self::VIOLATION_CONFIG_OPTION, [] ) === $validated ) {
            return true;
        }
        return update_option( self::VIOLATION_CONFIG_OPTION, $validated );
    }

    /**
     * @return array<string, array<string, int|float|null>>
     */
    public function get_client_violation_config(): array {
        $client_config = [];
        foreach ( $this->get_violation_config() as $event_type => $rule ) {
            if ( empty( $rule['enabled'] ) ) {
                continue;
            }
            $client_config[ $event_type ] = [
                'thresholdMs' => (int) $rule['threshold_ms'],
                'graceMs' => (int) $rule['grace_ms'],
                'confidenceThreshold' => (float) $rule['confidence_threshold'],
                'recoveryMs' => (int) $rule['recovery_ms'],
                'signalThreshold' => null === $rule['signal_threshold'] ? null : (float) $rule['signal_threshold'],
            ];
        }
        return $client_config;
    }

    public function init(): void {
        add_action( 'admin_post_daw_proctoring_identity', [ $this, 'handle_identity_submission' ] );
        add_action( 'admin_post_nopriv_daw_proctoring_identity', [ $this, 'handle_identity_submission' ] );
        add_action( 'admin_post_daw_proctoring_image', [ $this, 'handle_image_request' ] );
        add_action( 'admin_post_daw_proctoring_report', [ $this, 'handle_report_download' ] );
        add_action( 'admin_post_daw_proctoring_review', [ $this, 'handle_manual_review' ] );
        add_action( 'admin_post_daw_proctoring_review_event', [ $this, 'handle_event_review' ] );
        add_action( 'wp_ajax_recruitment_proctoring_event', [ $this, 'handle_event' ] );
        add_action( 'wp_ajax_nopriv_recruitment_proctoring_event', [ $this, 'handle_event' ] );
        add_action( 'template_redirect', [ $this, 'guard_active_assessment' ], 1 );
        add_action( 'init', [ $this, 'schedule_retention_cleanup' ] );
        add_action( 'daw_psychotest_proctoring_cleanup', [ $this, 'cleanup_expired_evidence' ] );
    }

    public function save_consent( ?int $application_id = null ): bool {
        $context = $this->get_context( $application_id );
        return null !== $context
            && $this->database->create_proctoring_session(
                $context['candidate_id'],
                $context['session_id'],
                $context['application_id'] > 0 ? $context['application_id'] : null,
                self::CONSENT_VERSION
            );
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
    public function get_current_session( ?int $application_id = null ): ?array {
        $context = $this->get_context( $application_id );
        if ( null === $context ) {
            return null;
        }
        return $this->database->get_proctoring_session( $context['candidate_id'], $context['session_id'] );
    }

    public function get_current_session_error(): bool {
        return $this->application_context_error || $this->database->has_error();
    }

    public function has_current_consent( ?int $application_id = null ): bool {
        $session = $this->get_current_session( $application_id );
        return ! $this->database->has_error()
            && ! $this->application_context_error
            && is_array( $session )
            && '' !== (string) ( $session['consent_at'] ?? '' )
            && self::CONSENT_VERSION === (string) ( $session['consent_version'] ?? '' )
            && in_array( (string) ( $session['identity_status'] ?? '' ), [ 'CAPTURED', 'MANUAL_REVIEW', 'MANUAL_VERIFIED' ], true );
    }

    public function mark_completed(): bool {
        $application_id = isset( $_POST['application_id'] ) && is_scalar( $_POST['application_id'] )
            ? absint( wp_unslash( (string) $_POST['application_id'] ) )
            : 0;
        $context = $this->get_context( $application_id > 0 ? $application_id : null );
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

        $application_id = absint( $state['application_id'] ?? 0 );
        $context = $this->get_context( $application_id > 0 ? $application_id : null );
        if ( null === $context ) {
            recruitment_abort_if_applicant_session_unavailable();
        }
        $session = null === $context
            ? null
            : $this->database->get_proctoring_session( $context['candidate_id'], $context['session_id'] );
        if ( $this->get_current_session_error() ) {
            wp_die( esc_html__( 'Status persetujuan pengawasan tidak dapat dibaca. Tes dihentikan sampai koneksi Oracle tersedia.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
        }
        if (
            ! is_array( $session )
            || self::CONSENT_VERSION !== (string) ( $session['consent_version'] ?? '' )
            || ! in_array( (string) ( $session['identity_status'] ?? '' ), [ 'CAPTURED', 'MANUAL_REVIEW', 'MANUAL_VERIFIED' ], true )
        ) {
            $test_key = '' !== $assessment_keys[ $page ][1] ? $assessment_keys[ $page ][1] : ( in_array( $requested_test, [ 'disc', 'papi' ], true ) ? $requested_test : 'disc' );
            $arguments = [ 'test' => $test_key ];
            if ( $application_id > 0 ) {
                $arguments['application_id'] = $application_id;
            }
            wp_safe_redirect( recruitment_get_public_url( 'psychotest', $arguments ) );
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
        $application_id = isset( $_POST['application_id'] ) && is_scalar( $_POST['application_id'] )
            ? absint( wp_unslash( (string) $_POST['application_id'] ) )
            : 0;
        if ( 0 === $application_id ) {
            $fail( __( 'Lamaran psikotes tidak valid.', 'recruitment-plugin' ), 403 );
        }
        $stage_status = recruitment_applicant_psychotest_stage_status( (string) $application_id );
        if ( null === $stage_status ) {
            $fail( __( 'Status lamaran tidak dapat diverifikasi. Silakan coba kembali nanti.', 'recruitment-plugin' ), 503 );
        }
        if ( ! $stage_status ) {
            $fail( __( 'Verifikasi identitas hanya tersedia untuk lamaran yang sedang berada pada tahap psikotes.', 'recruitment-plugin' ), 403 );
        }

        $context = $this->get_context( $application_id );
        if ( null === $context ) {
            if ( 'service_unavailable' === recruitment_get_applicant_session_error() ) {
                $fail( __( 'Akun pelamar sedang tidak dapat diverifikasi. Sesi Anda tetap tersimpan; silakan coba kembali.', 'recruitment-plugin' ), 503 );
            }
            $fail( __( 'Sesi pelamar tidak valid.', 'recruitment-plugin' ), 403 );
        }

        $test_key = sanitize_key( wp_unslash( $_POST['test'] ?? '' ) );
        if ( 'iq' !== $test_key ) {
            $fail( __( 'Jenis tes tidak valid.', 'recruitment-plugin' ), 400 );
        }
        $test_route = 'psych-iq';

        $session = $this->database->get_proctoring_session( $context['candidate_id'], $context['session_id'] );
        if ( $this->database->has_error() ) {
            $fail( __( 'Data persetujuan tidak dapat dibaca dari Oracle.', 'recruitment-plugin' ), 503 );
        }
        if (
            ! is_array( $session )
            || '' === (string) ( $session['consent_at'] ?? '' )
            || self::CONSENT_VERSION !== (string) ( $session['consent_version'] ?? '' )
        ) {
            $fail( __( 'Selesaikan persetujuan tes sebelum mengirim verifikasi identitas.', 'recruitment-plugin' ), 403 );
        }

        $client_metadata = $this->get_request_client_metadata();
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
                false,
                $client_metadata['client_ip'],
                $client_metadata['client_user_agent']
            );
            if ( ! $saved ) {
                ( new Recruitment_Applicant_Profile() )->delete_documents( [ $stored_document ] );
                $fail( __( 'Foto identitas gagal dicatat di Oracle. Silakan coba kembali.', 'recruitment-plugin' ), 503 );
            }
        } elseif ( 'manual' === $action ) {
            if (
                ! $this->database->save_proctoring_identity(
                    $context['candidate_id'],
                    $context['session_id'],
                    null,
                    true,
                    $client_metadata['client_ip'],
                    $client_metadata['client_user_agent']
                )
            ) {
                $fail( __( 'Permintaan verifikasi manual gagal dicatat. Silakan coba kembali.', 'recruitment-plugin' ), 503 );
            }
        } elseif ( 'continue' !== $action || ! in_array( (string) ( $session['identity_status'] ?? '' ), [ 'CAPTURED', 'MANUAL_REVIEW', 'MANUAL_VERIFIED' ], true ) ) {
            $fail( __( 'Selesaikan pengambilan foto atau pilih verifikasi manual terlebih dahulu.', 'recruitment-plugin' ), 409 );
        }

        if ( ! recruitment_grant_psychotest_consent( $test_key, $application_id ) ) {
            $fail( __( 'Persetujuan tes tidak dapat disimpan. Muat ulang halaman dan coba kembali.', 'recruitment-plugin' ), 500 );
        }
        $target_url = recruitment_get_public_url( $test_route, [ 'start' => '1', 'application_id' => $application_id ] );

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
        $application_id = isset( $_POST['application_id'] ) && is_scalar( $_POST['application_id'] )
            ? absint( wp_unslash( (string) $_POST['application_id'] ) )
            : 0;
        $context = $this->get_context( $application_id > 0 ? $application_id : null );
        if ( null === $context ) {
            if ( 'service_unavailable' === recruitment_get_applicant_session_error() ) {
                wp_send_json_error( [ 'message' => 'Akun pelamar sedang tidak dapat diverifikasi. Sesi Anda tetap tersimpan; silakan coba kembali.' ], 503 );
            }
            wp_send_json_error( [ 'message' => 'Sesi pelamar tidak valid.' ], 403 );
        }

        $session = $this->database->get_proctoring_session( $context['candidate_id'], $context['session_id'] );
        if ( $this->database->has_error() ) {
            wp_send_json_error( [ 'message' => 'Data sesi pengawasan tidak dapat dibaca dari Oracle.' ], 503 );
        }
        if (
            ! is_array( $session )
            || self::CONSENT_VERSION !== (string) ( $session['consent_version'] ?? '' )
            || ! in_array( (string) ( $session['identity_status'] ?? '' ), [ 'CAPTURED', 'MANUAL_REVIEW', 'MANUAL_VERIFIED' ], true )
        ) {
            wp_send_json_error( [ 'message' => 'Persetujuan pengawasan belum aktif.' ], 403 );
        }

        $assessment_stage = $this->get_active_assessment_stage( $context['candidate_id'], $context['application_id'] );
        if ( null === $assessment_stage ) {
            wp_send_json_error( [ 'message' => 'Tahap tes aktif tidak dapat diverifikasi.' ], 409 );
        }
        $event_token_value = $_POST['event_token'] ?? '';
        if ( ! is_scalar( $event_token_value ) ) {
            wp_send_json_error( [ 'message' => 'Token peristiwa tidak valid.' ], 400 );
        }
        $event_token = sanitize_text_field( wp_unslash( (string) $event_token_value ) );
        if ( ! preg_match( '/\A[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\z/i', $event_token ) ) {
            wp_send_json_error( [ 'message' => 'Token peristiwa tidak valid.' ], 400 );
        }
        $event_type_value = $_POST['event_type'] ?? '';
        if ( ! is_scalar( $event_type_value ) ) {
            wp_send_json_error( [ 'message' => 'Jenis event tidak valid.' ], 400 );
        }
        $event_type = strtoupper( sanitize_key( wp_unslash( (string) $event_type_value ) ) );
        $violation_config = $this->get_violation_config();
        $violation_types = [ 'CAMERA_STOPPED', 'FACE_NOT_DETECTED', 'MULTIPLE_FACES', 'FACE_TURNED_AWAY' ];
        $rule = $violation_config[ $event_type ] ?? [];
        if ( in_array( $event_type, $violation_types, true ) && ( empty( $rule ) || empty( $rule['enabled'] ) ) ) {
            wp_send_json_error( [ 'message' => 'Deteksi ini sedang tidak diaktifkan oleh HR.' ], 403 );
        }
        $duration_value = $_POST['detection_duration_ms'] ?? '0';
        if (
            ! is_scalar( $duration_value )
            || ! is_numeric( (string) $duration_value )
            || 0 > (float) $duration_value
            || 3600000 < (float) $duration_value
        ) {
            wp_send_json_error( [ 'message' => 'Durasi deteksi tidak valid.' ], 400 );
        }
        $detection_duration_ms = (int) round( (float) wp_unslash( (string) $duration_value ) );
        $confidence_value = $_POST['detection_confidence'] ?? null;
        $detection_confidence = null;
        if ( null !== $confidence_value ) {
            if ( ! is_scalar( $confidence_value ) || ! is_numeric( (string) $confidence_value ) ) {
                wp_send_json_error( [ 'message' => 'Confidence deteksi tidak valid.' ], 400 );
            }
            $detection_confidence = (float) wp_unslash( (string) $confidence_value );
            if ( ! is_finite( $detection_confidence ) || 0 > $detection_confidence || 1 < $detection_confidence ) {
                wp_send_json_error( [ 'message' => 'Confidence deteksi di luar rentang.' ], 400 );
            }
        }
        if (
            in_array( $event_type, $violation_types, true )
            && ( $detection_duration_ms < (int) $rule['threshold_ms']
                || ( null !== $detection_confidence && $detection_confidence < (float) $rule['confidence_threshold'] ) )
        ) {
            wp_send_json_error( [ 'message' => 'Threshold deteksi belum terpenuhi.' ], 400 );
        }
        $file = $_FILES['snapshot'] ?? null;
        $stored_document = is_array( $file ) ? $this->store_capture_upload() : null;
        if ( is_array( $file ) && null === $stored_document ) {
            wp_send_json_error( [ 'message' => 'Snapshot tidak valid atau gagal disimpan.' ], 400 );
        }

        $client_metadata = $this->get_request_client_metadata();
        $result = $this->database->save_proctoring_event(
            $context['candidate_id'],
            $context['session_id'],
            $event_type,
            is_array( $stored_document ) ? $stored_document['key'] : null,
            $assessment_stage,
            $event_token,
            $client_metadata['client_ip'],
            $client_metadata['client_user_agent'],
            $detection_duration_ms,
            $detection_confidence,
            $rule
        );
        if ( empty( $result['success'] ) ) {
            if ( is_array( $stored_document ) ) {
                ( new Recruitment_Applicant_Profile() )->delete_documents( [ $stored_document ] );
            }
            $message = 'schema_unavailable' === ( $result['error'] ?? '' )
                ? 'Migrasi 023 belum diterapkan. Event tidak dicatat; hubungi administrator sistem.'
                : 'Peringatan pengawasan gagal disimpan.';
            wp_send_json_error( [ 'message' => $message ], 503 );
        }
        if ( is_array( $stored_document ) && empty( $result['captured'] ) ) {
            ( new Recruitment_Applicant_Profile() )->delete_documents( [ $stored_document ] );
        }
        $warning_level = (string) ( $result['warning_level'] ?? '' );
        $warning_message = '';
        if ( '' !== $warning_level && isset( $rule['warning_message'] ) ) {
            $warning_message = (string) $rule['warning_message'];
            if ( 'repeated' === $warning_level ) {
                $warning_message = 'Peringatan berulang (' . absint( $result['repeat_count'] ?? 0 ) . ' kejadian): ' . $warning_message;
            } elseif ( 'serious' === $warning_level ) {
                $warning_message = 'Peringatan serius (' . absint( $result['repeat_count'] ?? 0 ) . ' kejadian): ' . $warning_message;
            }
        }
        wp_send_json_success(
            [
                'captured' => ! empty( $result['captured'] ),
                'severity' => (string) ( $result['severity'] ?? '' ),
                'warning_level' => $warning_level,
                'warning_message' => $warning_message,
                'repeat_count' => absint( $result['repeat_count'] ?? 0 ),
                'review_status' => in_array( $event_type, $violation_types, true ) ? 'PENDING' : 'NOT_APPLICABLE',
            ]
        );
    }

    public function handle_image_request(): void {
        $candidate_id = absint( $_GET['candidate_id'] ?? 0 );
        $session_id = sanitize_text_field( wp_unslash( $_GET['session_id'] ?? '' ) );
        $event_id = absint( $_GET['event_id'] ?? 0 );
        if ( ! $this->can_access_proctoring_reports() ) {
            wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengakses bukti psikotes.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
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

    public function handle_report_download(): void {
        if ( ! $this->can_access_proctoring_reports() ) {
            wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengunduh laporan psikotes.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
        if ( ! $this->is_secure_transport() ) {
            wp_die( esc_html__( 'Unduhan laporan hanya diizinkan melalui HTTPS atau localhost.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }

        $candidate_id = absint( $_GET['candidate_id'] ?? 0 );
        $application_id = absint( $_GET['application_id'] ?? 0 );
        $session_id = sanitize_text_field( wp_unslash( $_GET['session_id'] ?? '' ) );
        if ( $candidate_id < 1 ) {
            wp_die( esc_html__( 'Kandidat laporan psikotes tidak valid.', 'recruitment-plugin' ), '', [ 'response' => 400 ] );
        }

        if ( $application_id > 0 ) {
            check_admin_referer( 'daw_proctoring_report_' . $application_id . '_' . $candidate_id );
            $session_id = '';
        } else {
            check_admin_referer( 'daw_proctoring_report_' . $session_id );
        }

        $application_profile = null;
        $report = null;
        if ( $application_id > 0 ) {
            $application_profile = $this->database->get_admin_application_profile( (string) $application_id );
            if ( $this->database->has_error() ) {
                wp_die( esc_html__( 'Data lamaran tidak dapat dibaca dari Oracle.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
            }
            if (
                ! is_array( $application_profile )
                || $candidate_id !== absint( $application_profile['application']['candidate_id'] ?? 0 )
            ) {
                wp_die( esc_html__( 'Kandidat tidak cocok dengan lamaran laporan.', 'recruitment-plugin' ), '', [ 'response' => 404 ] );
            }

            $proctoring_records = $this->database->get_proctoring_reports( [ $candidate_id ] );
            if ( $this->database->has_error() ) {
                wp_die( esc_html__( 'Data sesi pengawasan tidak dapat dibaca dari Oracle.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
            }
            foreach ( $proctoring_records['sessions'] as $proctoring_session ) {
                if ( $application_id === absint( $proctoring_session['application_id'] ?? 0 ) ) {
                    $session_id = (string) ( $proctoring_session['session_id'] ?? '' );
                    break;
                }
            }
            if ( '' !== $session_id ) {
                $report = $this->database->get_proctoring_report( $candidate_id, $session_id );
                if ( $this->database->has_error() ) {
                    wp_die( esc_html__( 'Data laporan pengawasan tidak dapat dibaca dari Oracle.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
                }
                if (
                    ! is_array( $report )
                    || $application_id !== absint( $report['session']['application_id'] ?? 0 )
                ) {
                    $report = null;
                    $session_id = '';
                }
            }
        } else {
            $report = $this->database->get_proctoring_report( $candidate_id, $session_id );
            if ( $this->database->has_error() ) {
                wp_die( esc_html__( 'Data laporan psikotes tidak dapat dibaca dari Oracle.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
            }
            if ( ! is_array( $report ) ) {
                wp_die( esc_html__( 'Sesi proctoring tidak ditemukan atau sudah melewati masa simpan.', 'recruitment-plugin' ), '', [ 'response' => 404 ] );
            }
            $application_id = absint( $report['session']['application_id'] ?? 0 );
            if ( $application_id < 1 ) {
                wp_die( esc_html__( 'Laporan per lamaran tidak tersedia untuk sesi proctoring lama.', 'recruitment-plugin' ), '', [ 'response' => 409 ] );
            }
            $application_profile = $this->database->get_admin_application_profile( (string) $application_id );
            if ( $this->database->has_error() ) {
                wp_die( esc_html__( 'Data lamaran tidak dapat dibaca dari Oracle.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
            }
            if (
                ! is_array( $application_profile )
                || $candidate_id !== absint( $application_profile['application']['candidate_id'] ?? 0 )
            ) {
                wp_die( esc_html__( 'Kandidat tidak cocok dengan lamaran laporan.', 'recruitment-plugin' ), '', [ 'response' => 404 ] );
            }
        }

        $result_ids = recruitment_get_psychotest_result_ids_for_application( $candidate_id, $application_id );
        if ( empty( $result_ids ) ) {
            wp_die( esc_html__( 'Laporan psikotes tersedia setelah minimal satu tes pada lamaran ini selesai.', 'recruitment-plugin' ), '', [ 'response' => 409 ] );
        }
        $test_results = $this->database->get_psychotest_results_for_report( $candidate_id, $result_ids );
        if ( $this->database->has_error() ) {
            wp_die( esc_html__( 'Nilai tes tidak dapat dibaca dari Oracle.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
        }
        if ( empty( $test_results ) ) {
            wp_die( esc_html__( 'Nilai tes untuk lamaran ini tidak ditemukan di Oracle.', 'recruitment-plugin' ), '', [ 'response' => 409 ] );
        }
        if ( ! class_exists( \Dompdf\Dompdf::class ) ) {
            wp_die( esc_html__( 'PDF belum dapat dibuat karena pustaka PDF tidak tersedia.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
        }

        $application = $application_profile['application'];
        $session = is_array( $report ) ? $report['session'] : [];
        $events = is_array( $report ) ? $report['events'] : [];
        $escape = static function ( $value ): string {
            return htmlspecialchars( is_scalar( $value ) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
        };
        $stage_labels = [
            'preparation' => 'Persiapan',
            'iq' => 'IQ',
            'pauli' => 'Pauli',
            'disc' => 'DISC',
            'papi' => 'PAPI',
            'report' => 'Laporan',
        ];
        $event_labels = [
            'IDENTITY_CAPTURE' => 'Foto identitas (wajah dan KTP)',
            'MANUAL_IDENTITY' => 'Diminta verifikasi identitas manual',
            'TAB_HIDDEN' => 'Halaman ujian disembunyikan atau kehilangan fokus',
            'TAB_VISIBLE' => 'Peserta kembali ke halaman ujian',
            'CAMERA_STOPPED' => 'Kamera terputus',
            'CAMERA_UNAVAILABLE' => 'Kamera tidak tersedia',
            'FACE_NOT_DETECTED' => 'Wajah tidak terdeteksi',
            'MULTIPLE_FACES' => 'Lebih dari satu wajah terdeteksi',
            'FACE_TURNED_AWAY' => 'Wajah menghadap menyamping dalam durasi lama',
            'WARNING_SHOWN' => 'Peringatan ditampilkan kepada peserta',
            'FACE_DETECTION_UNAVAILABLE' => 'Deteksi wajah tidak tersedia',
            'MICROPHONE_AVAILABLE' => 'Mikrofon tersedia (status saja)',
            'MICROPHONE_UNAVAILABLE' => 'Mikrofon tidak tersedia (status saja)',
            'IMAGE_ACCESS' => 'Bukti gambar diakses HR',
            'REPORT_DOWNLOAD' => 'Laporan diunduh',
            'MANUAL_VERIFIED' => 'Identitas diverifikasi manual',
        ];
        $review_status_labels = [
            'PENDING' => 'Menunggu tinjauan HR',
            'CONFIRMED' => 'Dikonfirmasi HR',
            'DISMISSED' => 'Dikesampingkan HR',
            'NOT_APPLICABLE' => 'Aktivitas log',
        ];
        $severity_labels = [
            'warning' => 'Peringatan',
            'repeated' => 'Berulang',
            'serious' => 'Serius',
        ];
        $score_rows = '';
        foreach ( $test_results as $test_result ) {
            $score = $test_result['score'] ?? null;
            $score_label = is_numeric( $score )
                ? number_format( (float) $score, floor( (float) $score ) === (float) $score ? 0 : 2, ',', '.' )
                : '—';
            $score_rows .= '<tr><td>' . $escape( $test_result['test_title'] ?? '' )
                . '</td><td>' . $escape( $score_label ) . '</td><td>'
                . $escape( (int) ( $test_result['response_count'] ?? 0 ) . ' / ' . (int) ( $test_result['question_count'] ?? 0 ) )
                . '</td><td>' . $escape( $test_result['started_at'] ?? '' )
                . '</td><td>' . $escape( $test_result['completed_at'] ?? '' ) . '</td></tr>';
        }
        $identity_images = [];
        $monitoring_images = [];
        $event_rows = '';
        $confirmed_event_count = 0;
        $total_detection_duration_ms = 0;
        $repeat_escalation_count = 0;
        $maximum_repeat_count = 0;
        $violation_occurrence_times = [];
        foreach ( $events as $event ) {
            $event_type = (string) ( $event['event_type'] ?? '' );
            $file_key = (string) ( $event['file_key'] ?? '' );
            $image_uri = '' !== $file_key ? $this->get_evidence_data_uri( $file_key ) : null;
            if ( 'IDENTITY_CAPTURE' === $event_type && null !== $image_uri ) {
                $identity_images[] = '<img src="' . $image_uri . '" alt="Foto identitas">';
            } elseif ( null !== $image_uri ) {
                $monitoring_images[] = '<img src="' . $image_uri . '" alt="Tangkapan foto pengawasan">';
            }
            $stage = (string) ( $event['assessment_stage'] ?? '' );
            $actor_user_id = absint( $event['actor_user_id'] ?? 0 );
            $browser = $this->get_browser_label( (string) ( $event['client_user_agent'] ?? '' ) );
            if ( $actor_user_id > 0 && 'Tidak direkam' === $browser ) {
                $browser = 'HR';
            }
            $client_ip = (string) ( $event['client_ip'] ?? '' );
            $type_label = $stage_labels[ $stage ] ?? ( $actor_user_id > 0 ? 'HR' : 'Lainnya' );
            $duration_ms = $event['detection_duration_ms'] ?? null;
            $duration_label = is_numeric( $duration_ms )
                ? number_format( (float) $duration_ms / 1000, 1, ',', '.' ) . ' dtk'
                : '—';
            $confidence_label = is_numeric( $event['detection_confidence'] ?? null )
                ? number_format( (float) $event['detection_confidence'] * 100, 1, ',', '.' ) . '%'
                : 'Tidak tersedia';
            $severity = $severity_labels[ (string) ( $event['severity'] ?? '' ) ] ?? '—';
            $warning_level = $severity_labels[ (string) ( $event['warning_level'] ?? '' ) ] ?? 'Tidak dikirim';
            $review_status = $review_status_labels[ (string) ( $event['review_status'] ?? 'NOT_APPLICABLE' ) ] ?? 'Status tidak diketahui';
            $review_note = trim( (string) ( $event['review_note'] ?? '' ) );
            if (
                is_numeric( $duration_ms )
                && in_array( $event_type, [ 'CAMERA_STOPPED', 'FACE_NOT_DETECTED', 'MULTIPLE_FACES', 'FACE_TURNED_AWAY' ], true )
            ) {
                $confirmed_event_count++;
                $total_detection_duration_ms += absint( $duration_ms );
                $maximum_repeat_count = max( $maximum_repeat_count, absint( $event['repeat_count'] ?? 1 ) );
                if ( in_array( (string) ( $event['severity'] ?? '' ), [ 'repeated', 'serious' ], true ) ) {
                    $repeat_escalation_count++;
                }
                if ( '' !== (string) ( $event['event_at'] ?? '' ) ) {
                    $violation_occurrence_times[] = (string) $event['event_at'];
                }
            }
            $event_rows .= '<tr><td>' . $escape( $event['event_at'] ?? '' ) . '</td><td>'
                . $escape( $browser ) . '</td><td>'
                . $escape( '' !== $client_ip ? $client_ip : 'Tidak direkam' ) . '</td><td>'
                . $escape( $event_labels[ $event_type ] ?? 'Peristiwa pengawasan' ) . '</td><td>'
                . $escape( $type_label ) . '</td><td>'
                . $escape( $duration_label ) . '</td><td>'
                . $escape( $confidence_label ) . '</td><td>'
                . $escape( $severity . ' · ' . $warning_level . ' · ' . absint( $event['repeat_count'] ?? 0 ) . 'x' ) . '</td><td>'
                . $escape( $review_status . ( '' !== $review_note ? ' · ' . $review_note : '' ) ) . '</td></tr>';
        }
        if ( '' === $event_rows ) {
            $event_rows = '<tr><td colspan="9">Belum ada aktivitas pengawasan yang tertaut ke lamaran ini.</td></tr>';
        }
        sort( $violation_occurrence_times, SORT_STRING );
        $first_occurrence = $violation_occurrence_times[0] ?? '—';
        $most_recent_occurrence = $violation_occurrence_times ? $violation_occurrence_times[ count( $violation_occurrence_times ) - 1 ] : '—';

        $identity_gallery_rows = '';
        foreach ( array_chunk( $identity_images, 2 ) as $image_row ) {
            $identity_gallery_rows .= '<tr>';
            foreach ( $image_row as $image ) {
                $identity_gallery_rows .= '<td>' . $image . '</td>';
            }
            if ( count( $image_row ) < 2 ) {
                $identity_gallery_rows .= '<td></td>';
            }
            $identity_gallery_rows .= '</tr>';
        }
        $identity_gallery = '' !== $identity_gallery_rows
            ? '<table class="photo-grid identity-grid"><tbody>' . $identity_gallery_rows . '</tbody></table>'
            : '<p>Foto identitas tidak tersedia.</p>';

        $monitoring_gallery_rows = '';
        foreach ( array_chunk( $monitoring_images, 7 ) as $image_row ) {
            $monitoring_gallery_rows .= '<tr>';
            foreach ( $image_row as $image ) {
                $monitoring_gallery_rows .= '<td>' . $image . '</td>';
            }
            for ( $empty_cells = count( $image_row ); $empty_cells < 7; $empty_cells++ ) {
                $monitoring_gallery_rows .= '<td></td>';
            }
            $monitoring_gallery_rows .= '</tr>';
        }
        $monitoring_gallery = '' !== $monitoring_gallery_rows
            ? '<table class="photo-grid monitoring-grid"><tbody>' . $monitoring_gallery_rows . '</tbody></table>'
            : '<p>Tidak ada tangkapan foto pengawasan yang tersedia.</p>';

        $session_label = '' !== $session_id ? substr( $session_id, 0, 12 ) : 'Tidak tersedia';
        $proctoring_notice = is_array( $report )
            ? ''
            : '<p class="notice">Sesi pengawasan belum tertaut ke lamaran ini. Foto identitas, snapshot, dan log pengawasan tidak disertakan agar data sesi lama tidak salah dikaitkan.</p>';
        $completed_at = (string) ( $session['completed_at'] ?? '' );
        if ( '' === $completed_at ) {
            foreach ( $test_results as $test_result ) {
                if ( '' !== (string) ( $test_result['completed_at'] ?? '' ) ) {
                    $completed_at = (string) $test_result['completed_at'];
                }
            }
        }
        $html = '<!doctype html><html lang="id"><meta charset="utf-8"><style>
            body{font:10px/1.45 DejaVu Sans,sans-serif;color:#172033}
            h1{font-size:19px;margin:0}
            h2{font-size:14px;color:#172033;margin:20px 0 7px;padding-bottom:5px;border-bottom:2px solid #261cff}
            p.meta{color:#56657a;margin:0 0 12px}
            table{border-collapse:collapse;width:100%;margin:8px 0 16px}
            th,td{border:1px solid #d5dde7;padding:6px;text-align:left;vertical-align:top}
            th{background:#f2f5f9}
            .brand{margin-bottom:17px}
            .brand-mark{display:inline-block;width:15px;height:24px;margin-right:8px;vertical-align:middle;background:#ffad00}
            .meta-grid td{width:33.3%;border:0;padding:4px 8px 8px 0}
            .meta-label{display:block;color:#56657a;font-weight:bold;margin-bottom:3px}
            .photo-grid{border:0;page-break-inside:auto}
            .photo-grid td{border:0;padding:4px;vertical-align:middle}
            .identity-grid td{width:50%}
            .identity-grid img{max-width:100%;max-height:190px}
            .monitoring-grid td{width:14.28%;height:64px}
            .monitoring-grid img{max-width:100%;max-height:64px}
            .activity{font-size:7px;table-layout:fixed}
            .activity th,.activity td{padding:3px;word-wrap:break-word}
            .activity .time{width:12%}.activity .browser{width:9%}.activity .ip{width:11%}
            .activity .description{width:18%}.activity .type{width:8%}
            .activity .duration{width:7%}.activity .confidence{width:7%}
            .activity .warning{width:12%}.activity .review{width:16%}
            .activity tr{page-break-inside:avoid}
            .notice{padding:8px;background:#fff8e6;border:1px solid #f0d98a}
            </style><body><div class="brand"><span class="brand-mark"></span><h1 style="display:inline">Laporan Psikotes</h1></div><p class="meta">Lamaran #'
            . $escape( $application_id ) . ' · Sesi '
            . $escape( $session_label ) . ' · Dibuat '
            . $escape( wp_date( 'Y-m-d H:i:s' ) ) . '</p>';
        $html .= '<table class="meta-grid"><tbody><tr><td><span class="meta-label">Nama peserta</span><strong>'
            . $escape( $application['candidate_name'] ?? '' ) . '</strong></td><td><span class="meta-label">Tanggal penyelesaian</span><strong>'
            . $escape( $completed_at ?: 'Belum selesai' ) . '</strong></td><td><span class="meta-label">Jabatan</span><strong>'
            . $escape( $application['position'] ?? '' ) . '</strong></td></tr><tr><td><span class="meta-label">Email</span><strong>'
            . $escape( $application['candidate_email'] ?? '' ) . '</strong></td><td><span class="meta-label">Departemen / lokasi</span><strong>'
            . $escape( $application['department'] ?? '' ) . ' · ' . $escape( $application['dealer'] ?? '' )
            . '</strong></td><td><span class="meta-label">Status identitas</span><strong>'
            . $escape( $session['identity_status'] ?? 'Tidak tersedia' ) . '</strong></td></tr></tbody></table>';
        $html .= '<h2>Hasil Tes</h2><table><thead><tr><th>Tes</th><th>Nilai</th><th>Jawaban / soal</th><th>Mulai</th><th>Selesai</th></tr></thead><tbody>'
            . $score_rows . '</tbody></table>';
        $html .= '<h2>Foto Identitas</h2>' . $proctoring_notice . $identity_gallery;
        $html .= '<h2>Foto Pengawasan</h2>' . $monitoring_gallery;
        $html .= '<h2>Ringkasan Event Deteksi</h2><table><tbody><tr><th>Event threshold</th><td>'
            . $escape( $confirmed_event_count ) . '</td><th>Durasi indikasi browser</th><td>'
            . $escape( number_format( $total_detection_duration_ms / 1000, 1, ',', '.' ) . ' detik' )
            . '</td></tr><tr><th>Event berulang/serius</th><td>' . $escape( $repeat_escalation_count )
            . '</td><th>Frekuensi puncak dalam window</th><td>' . $escape( $maximum_repeat_count )
            . '</td></tr><tr><th>Kejadian pertama</th><td>' . $escape( $first_occurrence )
            . '</td><th>Kejadian terakhir</th><td>' . $escape( $most_recent_occurrence ) . '</td></tr></tbody></table>';
        $html .= '<h2>Log Aktivitas dan Tinjauan Pengawasan</h2><table class="activity"><thead><tr><th class="time">Waktu</th><th class="browser">Browser</th><th class="ip">IP (terlihat server)</th><th class="description">Aktivitas</th><th class="type">Tahap</th><th class="duration">Durasi</th><th class="confidence">Confidence</th><th class="warning">Tingkat</th><th class="review">Tinjauan HR</th></tr></thead><tbody>'
            . $event_rows . '</tbody></table>';
        $html .= '<p class="notice">Catatan: event deteksi bukan kesimpulan otomatis; status temuan hanya berubah setelah tinjauan HR. Durasi dan confidence berasal dari detector browser, sedangkan waktu event berasal dari server dan hasil detector dapat keliru. Browser hanya mencatat halaman ujian disembunyikan/dibuka kembali; browser tidak mengungkap tab atau situs lain yang dibuka. User-Agent dapat tidak akurat. IP adalah alamat yang terlihat oleh server, yang dapat berupa proxy/gateway. Data lama yang tidak memiliki metadata ditampilkan sebagai “Tidak direkam”. Tidak ada audio atau video utuh yang direkam.</p></body></html>';

        $options = new \Dompdf\Options();
        $options->setIsRemoteEnabled( false );
        $dompdf = new \Dompdf\Dompdf( $options );
        $dompdf->loadHtml( $html, 'UTF-8' );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();
        if ( '' !== $session_id && ! $this->database->log_proctoring_report_download( $candidate_id, $session_id, get_current_user_id() ) ) {
            wp_die( esc_html__( 'Unduhan laporan gagal dicatat untuk audit.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
        }
        nocache_headers();
        $filename = sanitize_file_name(
            'laporan-psikotes-' . $application_id . '-' . $candidate_id . '.pdf'
        );
        $dompdf->stream( $filename, [ 'Attachment' => true ] );
        exit;
    }

    public function handle_manual_review(): void {
        if ( ! $this->can_access_proctoring_reports() ) {
            wp_die( esc_html__( 'Anda tidak memiliki izin untuk meninjau verifikasi identitas.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
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

    public function handle_event_review(): void {
        if ( ! $this->can_access_proctoring_reports() ) {
            wp_die( esc_html__( 'Anda tidak memiliki izin untuk meninjau event pengawasan.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
        if ( ! $this->is_secure_transport() ) {
            wp_die( esc_html__( 'Tinjauan event hanya diizinkan melalui HTTPS atau localhost.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
        $candidate_id = absint( $_POST['candidate_id'] ?? 0 );
        $event_id = absint( $_POST['event_id'] ?? 0 );
        $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
        check_admin_referer( 'daw_proctoring_review_event_' . $session_id . '_' . $event_id );
        $review_status = strtoupper( sanitize_key( wp_unslash( $_POST['review_status'] ?? '' ) ) );
        $review_note = isset( $_POST['review_note'] ) && is_scalar( $_POST['review_note'] )
            ? sanitize_textarea_field( wp_unslash( (string) $_POST['review_note'] ) )
            : '';
        if ( ! in_array( $review_status, [ 'CONFIRMED', 'DISMISSED' ], true ) ) {
            wp_die( esc_html__( 'Status tinjauan event tidak valid.', 'recruitment-plugin' ), '', [ 'response' => 400 ] );
        }
        if ( ! $this->database->update_proctoring_event_review(
            $candidate_id,
            $session_id,
            $event_id,
            get_current_user_id(),
            $review_status,
            $review_note
        ) ) {
            wp_die( esc_html__( 'Status tinjauan event gagal disimpan. Periksa koneksi Oracle atau apakah event sudah ditinjau.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
        }
        $redirect = wp_get_referer() ?: recruitment_get_admin_url( 'psychotest-results' );
        wp_safe_redirect( add_query_arg( 'proctoring_review', 'saved', $redirect ) );
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

    private function can_access_proctoring_reports(): bool {
        return current_user_can( 'manage_recruitment' ) || current_user_can( 'manage_options' );
    }

    private function get_active_assessment_stage( int $candidate_id, int $application_id ): ?string {
        $user_id = recruitment_get_assessment_user_id();
        if ( $candidate_id < 1 || $user_id < 1 ) {
            return null;
        }

        foreach (
            [
                'iq' => 'recruitment_iq_assessment',
                'pauli' => 'recruitment_pauli_assessment',
            ] as $stage => $key
        ) {
            $state = recruitment_get_assessment_state( $user_id, $key );
            if (
                recruitment_assessment_state_matches_candidate( $state, $candidate_id )
                && 'active' === ( $state['status'] ?? '' )
                && absint( $state['application_id'] ?? 0 ) === $application_id
            ) {
                return $stage;
            }
        }

        $state = recruitment_get_assessment_state( $user_id, 'recruitment_personality_assessment' );
        $active_test = is_array( $state ) ? sanitize_key( (string) ( $state['active_test'] ?? '' ) ) : '';
        if (
            recruitment_assessment_state_matches_candidate( $state, $candidate_id )
            && in_array( $active_test, [ 'disc', 'papi' ], true )
            && 'active' === ( $state['tests'][ $active_test ]['status'] ?? '' )
            && absint( $state['application_id'] ?? 0 ) === $application_id
        ) {
            return $active_test;
        }

        return null;
    }

    private function get_evidence_data_uri( string $file_key ): ?string {
        if ( ! preg_match( '/\A[a-f0-9]{32}\.jpg\z/', $file_key ) ) {
            return null;
        }
        $path = ( new Recruitment_Applicant_Profile() )->document_path( [ 'key' => $file_key ] );
        if ( null === $path || ! is_file( $path ) || ! is_readable( $path ) || ! function_exists( 'finfo_open' ) ) {
            return null;
        }
        $file_info = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = false === $file_info ? false : finfo_file( $file_info, $path );
        if ( false !== $file_info ) {
            finfo_close( $file_info );
        }
        if ( 'image/jpeg' !== $mime_type ) {
            return null;
        }
        $content = file_get_contents( $path );
        return false === $content ? null : 'data:image/jpeg;base64,' . base64_encode( $content );
    }

    /**
     * @return array{client_ip: string|null, client_user_agent: string|null}
     */
    private function get_request_client_metadata(): array {
        $remote_address = is_scalar( $_SERVER['REMOTE_ADDR'] ?? null )
            ? trim( (string) $_SERVER['REMOTE_ADDR'] )
            : '';
        $client_ip = false !== filter_var( $remote_address, FILTER_VALIDATE_IP )
            ? $remote_address
            : null;
        $user_agent = is_scalar( $_SERVER['HTTP_USER_AGENT'] ?? null )
            ? preg_replace( '/[^\x20-\x7E]/', '', sanitize_text_field( (string) $_SERVER['HTTP_USER_AGENT'] ) )
            : '';
        $user_agent = substr( (string) $user_agent, 0, 512 );

        return [
            'client_ip' => $client_ip,
            'client_user_agent' => '' !== $user_agent ? $user_agent : null,
        ];
    }

    private function get_browser_label( string $user_agent ): string {
        if ( '' === $user_agent ) {
            return 'Tidak direkam';
        }
        foreach (
            [
                '/(?:Edg|Edge)\/([\d.]+)/' => 'Microsoft Edge',
                '/OPR\/([\d.]+)/' => 'Opera',
                '/SamsungBrowser\/([\d.]+)/' => 'Samsung Internet',
                '/Firefox\/([\d.]+)/' => 'Firefox',
                '/Chrome\/([\d.]+)/' => 'Chrome',
                '/Version\/([\d.]+).*Safari\//' => 'Safari',
            ] as $pattern => $browser
        ) {
            if ( preg_match( $pattern, $user_agent, $matches ) ) {
                return $browser . ' ' . $matches[1];
            }
        }

        return 'Lainnya';
    }

    /**
     * @return array{candidate_id: int, application_id: int, session_id: string}|null
     */
    private function get_context( ?int $application_id = null ): ?array {
        $this->application_context_error = false;
        $applicant = recruitment_get_current_applicant();
        if ( ! is_array( $applicant ) || ! ctype_digit( (string) ( $applicant['profile_id'] ?? '' ) ) ) {
            return null;
        }
        $candidate_id = absint( $applicant['profile_id'] );
        if ( 1 > $candidate_id ) {
            return null;
        }
        if ( null === $application_id ) {
            $application_id = recruitment_get_psychotest_application_id();
        }
        $application_id = $application_id ?? 0;
        if ( $application_id > 0 ) {
            $stage_status = recruitment_applicant_psychotest_stage_status( (string) $application_id );
            if ( null === $stage_status ) {
                $this->application_context_error = true;
                return null;
            }
            if ( ! $stage_status ) {
                return null;
            }
        }
        $session_scope = $application_id > 0 ? ':' . $application_id : '';
        return [
            'candidate_id' => $candidate_id,
            'application_id' => $application_id,
            'session_id' => hash_hmac( 'sha256', 'psychotest-proctoring:' . $candidate_id . $session_scope, wp_salt( 'auth' ) ),
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
