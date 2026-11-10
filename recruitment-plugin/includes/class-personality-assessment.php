<?php

/**
 * Candidate-facing DISC and PAPI Kostick assessment sessions.
 */
class Recruitment_Personality_Assessment {

    private const USER_META_KEY = 'recruitment_personality_assessment';
    private const IQ_USER_META_KEY = 'recruitment_iq_assessment';
    private const PAULI_USER_META_KEY = 'recruitment_pauli_assessment';
    private const REMINDER_MINUTES = [ 'disc' => 20, 'papi' => 30 ];
    private const QUESTION_COUNTS = [ 'disc' => 24, 'papi' => 90 ];
    private const ITEM_COUNTS = [ 'disc' => 4, 'papi' => 2 ];

    private Recruitment_Database $database;

    public function __construct() {
        $this->database = new Recruitment_Database();
    }

    public function handle_public_request(): void {
        if ( 'psych-personality' !== sanitize_key( wp_unslash( $_GET['recruitment_page'] ?? '' ) ) ) {
            return;
        }

        $candidate_id = $this->get_current_candidate_id();
        if ( $candidate_id < 1 ) {
            wp_safe_redirect( add_query_arg( 'account_error', 'applicant_account_required', recruitment_get_public_url( 'applicant-login' ) ) );
            exit;
        }

        $test_key = $this->get_requested_test_key();
        $user_id = recruitment_get_assessment_user_id();
        if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
            $this->handle_complete_request( $user_id, $candidate_id, $test_key );
        }
        if ( '1' === sanitize_text_field( wp_unslash( $_GET['start'] ?? '' ) ) ) {
            $state = recruitment_get_assessment_state( $user_id, self::USER_META_KEY );
            $test_already_started = is_array( $state )
                && (int) ( $state['candidate_id'] ?? 0 ) === $candidate_id
                && in_array( $state['tests'][ $test_key ]['status'] ?? '', [ 'active', 'complete' ], true );
            if ( ! $test_already_started && ! recruitment_is_psychotest_test_enabled( $test_key ) ) {
                wp_die( esc_html__( 'Tes kepribadian sedang tidak tersedia untuk sesi online. Silakan periksa jadwal dengan tim HR.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
            }
            $this->start_test( $user_id, $candidate_id, $test_key );
            wp_safe_redirect( recruitment_get_public_url( 'psych-personality', [ 'test' => $test_key ] ) );
            exit;
        }
    }

    public function save_answer_ajax(): void {
        check_ajax_referer( 'recruitment_personality_save_answer', 'nonce' );
        $candidate_id = $this->get_current_candidate_id();
        if ( $candidate_id < 1 ) {
            $database_error = $this->database->has_error();
            wp_send_json_error(
                [ 'message' => $database_error ? 'Koneksi Oracle belum tersedia.' : 'Profil kandidat tidak valid.' ],
                $database_error ? 503 : 403
            );
        }

        $test_key = $this->get_requested_test_key();
        $user_id = recruitment_get_assessment_user_id();
        $state = recruitment_get_assessment_state( $user_id, self::USER_META_KEY );
        if ( ! $this->is_valid_state( $state, $candidate_id )
            || 'active' !== ( $state['tests'][ $test_key ]['status'] ?? '' )
            || $test_key !== ( $state['active_test'] ?? '' ) ) {
            wp_send_json_error( [ 'message' => 'Sesi tes kepribadian tidak aktif.' ], 409 );
        }

        $question_id = absint( wp_unslash( $_POST['question_id'] ?? 0 ) );
        $question_ids = array_map( 'absint', (array) ( $state['tests'][ $test_key ]['question_order'] ?? [] ) );
        if ( ! in_array( $question_id, $question_ids, true ) ) {
            wp_send_json_error( [ 'message' => 'Soal tidak termasuk dalam sesi tes ini.' ], 400 );
        }
        $answer = $this->normalize_answer( $test_key, wp_unslash( $_POST['answer'] ?? [] ) );
        if ( is_wp_error( $answer ) ) {
            wp_send_json_error( [ 'message' => $answer->get_error_message() ], 400 );
        }
        $saved = $this->save_answer( $state, $test_key, $question_id, $answer );
        if ( empty( $saved['success'] ) ) {
            wp_send_json_error( [ 'message' => (string) ( $saved['error'] ?? 'Jawaban belum tersimpan.' ) ], 500 );
        }

        recruitment_update_assessment_state( $user_id, self::USER_META_KEY, $state );
        wp_send_json_success( [ 'saved' => true ] );
    }

    /**
     * @return array<string, mixed>
     */
    public function get_page_view(): array {
        $test_key = $this->get_requested_test_key();
        $candidate_id = $this->get_current_candidate_id();
        if ( $candidate_id < 1 ) {
            return [ 'login_required' => true ];
        }
        $user_id = recruitment_get_assessment_user_id();
        $state = recruitment_get_assessment_state( $user_id, self::USER_META_KEY );
        if ( $candidate_id < 1 || ! $this->is_valid_state( $state, $candidate_id ) ) {
            return [ 'ready' => false, 'test_key' => $test_key ];
        }

        $test_state = $state['tests'][ $test_key ] ?? [];
        if ( 'complete' === ( $test_state['status'] ?? '' ) ) {
            return [
                'complete' => true,
                'test_key' => $test_key,
                'title' => 'disc' === $test_key ? 'DISC' : 'PAPI Kostick',
                'result_id' => absint( $test_state['result_id'] ?? 0 ),
                'next_test' => 'disc' === $test_key ? 'papi' : '',
            ];
        }
        if ( 'active' !== ( $test_state['status'] ?? '' ) || $test_key !== ( $state['active_test'] ?? '' ) ) {
            return [ 'ready' => false, 'test_key' => $test_key ];
        }

        $catalog = $this->database->get_personality_assessments();
        if ( $this->database->has_error() ) {
            return [ 'error' => 'Koneksi Oracle belum tersedia. Silakan coba kembali nanti.' ];
        }
        $test = $catalog[ $test_key ] ?? null;
        if ( ! is_array( $test ) || (int) $test['ID'] !== (int) $test_state['id']
            || empty( $test['questions'] )
            || (int) $test['QUESTION_COUNT'] !== count( $test['questions'] ) ) {
            return [ 'error' => 'Bank soal tes kepribadian belum tersedia atau belum lengkap. Silakan hubungi tim HR.' ];
        }

        $questions_by_id = [];
        foreach ( $test['questions'] as $question ) {
            $questions_by_id[ (int) $question['id'] ] = $question;
        }
        $questions = [];
        foreach ( $test_state['question_order'] as $question_id ) {
            $question_id = absint( $question_id );
            if ( ! isset( $questions_by_id[ $question_id ] ) ) {
                return [ 'error' => 'Soal pada sesi ini tidak lagi tersedia. Silakan hubungi tim HR.' ];
            }
            $question = $questions_by_id[ $question_id ];
            if ( self::ITEM_COUNTS[ $test_key ] !== count( (array) ( $question['items'] ?? [] ) ) ) {
                return [ 'error' => 'Bank soal kepribadian tidak memiliki jumlah pernyataan yang valid. Silakan hubungi tim HR.' ];
            }
            $question['answer'] = $test_state['answers'][ $question_id ] ?? [];
            $question['number'] = count( $questions ) + 1;
            $questions[] = $question;
        }

        return [
            'ready' => true,
            'test_key' => $test_key,
            'title' => 'disc' === $test_key ? 'DISC' : 'PAPI Kostick',
            'duration' => self::REMINDER_MINUTES[ $test_key ],
            'started_at' => absint( $test_state['started_at'] ),
            'instructions' => 'disc' === $test_key
                ? 'Setiap nomor berisi empat pernyataan. Pilih tepat satu yang paling menggambarkan dan satu yang paling tidak menggambarkan diri Anda; kedua pilihan harus berbeda.'
                : 'Terdapat 90 pasangan pernyataan. Pilih satu pernyataan yang paling mencerminkan diri Anda pada setiap pasangan dan jawab seluruh nomor.',
            'questions' => $questions,
            'answered_count' => count( $test_state['answers'] ),
            'question_count' => count( $questions ),
            'nonce' => wp_create_nonce( 'recruitment_personality_save_answer' ),
            'complete_nonce' => wp_create_nonce( 'recruitment_personality_complete_' . $test_key ),
            'ajax_url' => admin_url( 'admin-ajax.php' ),
        ];
    }

    private function get_requested_test_key(): string {
        $requested_test = $_GET['test'] ?? $_POST['test'] ?? 'disc';
        $test_key = sanitize_key( wp_unslash( $requested_test ) );
        return in_array( $test_key, [ 'disc', 'papi' ], true ) ? $test_key : 'disc';
    }

    private function get_current_candidate_id(): int {
        return recruitment_get_assessment_user_id();
    }

    /**
     * @param mixed $state
     */
    private function is_valid_state( mixed $state, int $candidate_id ): bool {
        return is_array( $state )
            && (int) ( $state['candidate_id'] ?? 0 ) === $candidate_id
            && isset( $state['tests']['disc'], $state['tests']['papi'] )
            && is_array( $state['tests']['disc'] )
            && is_array( $state['tests']['papi'] );
    }

    private function start_test( int $user_id, int $candidate_id, string $test_key ): void {
        $state = recruitment_get_assessment_state( $user_id, self::USER_META_KEY );
        $test_already_started = $this->is_valid_state( $state, $candidate_id )
            && in_array( $state['tests'][ $test_key ]['status'] ?? '', [ 'active', 'complete' ], true );
        if ( ! $test_already_started && ! recruitment_consume_psychotest_consent( $test_key ) ) {
            wp_die( esc_html__( 'Berikan persetujuan tes terlebih dahulu.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
        if ( ! $this->is_valid_state( $state, $candidate_id ) ) {
            if ( 'disc' !== $test_key ) {
                wp_die( esc_html__( 'Tes DISC harus diselesaikan sebelum PAPI Kostick dapat dimulai.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
            }
            $this->assert_previous_assessments_complete( $user_id, $candidate_id );
            $catalog = $this->database->get_personality_assessments();
            if ( $this->database->has_error() ) {
                wp_die( esc_html__( 'Koneksi Oracle belum tersedia. Silakan coba kembali nanti.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
            }
            if ( ! $this->has_valid_catalog( $catalog ) ) {
                wp_die( esc_html__( 'Bank soal DISC dan PAPI Kostick belum tersedia atau belum lengkap. Silakan hubungi tim HR.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
            }
            $state = [
                'version' => 1,
                'candidate_id' => $candidate_id,
                'active_test' => '',
                'tests' => [],
            ];
            foreach ( [ 'disc', 'papi' ] as $key ) {
                $test = $catalog[ $key ];
                $question_order = array_map( 'intval', array_column( $test['questions'], 'id' ) );
                $state['tests'][ $key ] = [
                    'id' => (int) $test['ID'],
                    'status' => 'disc' === $key ? 'available' : 'locked',
                    'started_at' => 0,
                    'question_order' => $question_order,
                    'answers' => [],
                    'response_ids' => [],
                    'result_id' => 0,
                ];
            }
        }

        $test_state = $state['tests'][ $test_key ];
        if ( 'complete' === ( $test_state['status'] ?? '' ) ) {
            return;
        }
        if ( 'papi' === $test_key && 'complete' !== ( $state['tests']['disc']['status'] ?? '' ) ) {
            wp_die( esc_html__( 'Selesaikan DISC terlebih dahulu sebelum memulai PAPI Kostick.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
        $settings = (array) get_option( 'recruitment_psychotest_settings', [] );
        $test_settings = (array) get_option( 'recruitment_psychotest_tests', [] );
        if ( ( isset( $settings['status'] ) && 'active' !== $settings['status'] )
            || ( isset( $test_settings[ $test_key ]['active'] ) && empty( $test_settings[ $test_key ]['active'] ) ) ) {
            wp_die( esc_html__( 'Tes kepribadian sedang dinonaktifkan oleh tim HR.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
        $state['active_test'] = $test_key;
        $state['tests'][ $test_key ]['status'] = 'active';
        if ( empty( $state['tests'][ $test_key ]['started_at'] ) ) {
            $state['tests'][ $test_key ]['started_at'] = time();
        }
        recruitment_update_assessment_state( $user_id, self::USER_META_KEY, $state );
    }

    private function assert_previous_assessments_complete( int $user_id, int $candidate_id ): void {
        $iq_state = recruitment_get_assessment_state( $user_id, self::IQ_USER_META_KEY );
        $pauli_state = recruitment_get_assessment_state( $user_id, self::PAULI_USER_META_KEY );
        if ( ! is_array( $iq_state ) || 'complete' !== ( $iq_state['status'] ?? '' )
            || (int) ( $iq_state['candidate_id'] ?? 0 ) !== $candidate_id
            || ! is_array( $pauli_state ) || 'complete' !== ( $pauli_state['status'] ?? '' )
            || (int) ( $pauli_state['candidate_id'] ?? 0 ) !== $candidate_id ) {
            wp_die( esc_html__( 'Tes kepribadian dapat dimulai setelah Tes IQ dan Pauli selesai.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
    }

    /**
     * @param array<string, array<string, mixed>> $catalog
     */
    private function has_valid_catalog( array $catalog ): bool {
        foreach ( [ 'disc', 'papi' ] as $key ) {
            if ( ! isset( $catalog[ $key ] )
                || self::REMINDER_MINUTES[ $key ] !== (int) $catalog[ $key ]['DURATION']
                || self::QUESTION_COUNTS[ $key ] !== count( (array) ( $catalog[ $key ]['questions'] ?? [] ) )
                || (int) $catalog[ $key ]['QUESTION_COUNT'] !== count( $catalog[ $key ]['questions'] ) ) {
                return false;
            }
            foreach ( $catalog[ $key ]['questions'] as $question ) {
                if ( self::ITEM_COUNTS[ $key ] !== count( (array) ( $question['items'] ?? [] ) ) ) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param mixed $raw_answer
     * @return array<string, int>|WP_Error
     */
    private function normalize_answer( string $test_key, mixed $raw_answer ): array|WP_Error {
        if ( ! is_array( $raw_answer ) ) {
            return new WP_Error( 'invalid_personality_answer', 'Pilih jawaban sebelum melanjutkan.' );
        }
        if ( 'disc' === $test_key ) {
            foreach ( [ 'most', 'least' ] as $key ) {
                if ( ! isset( $raw_answer[ $key ] ) || ! preg_match( '/\A[0-3]\z/', (string) $raw_answer[ $key ] ) ) {
                    return new WP_Error( 'invalid_disc_answer', 'Pilih satu pernyataan paling menggambarkan dan satu paling tidak menggambarkan.' );
                }
            }
            if ( (int) $raw_answer['most'] === (int) $raw_answer['least'] ) {
                return new WP_Error( 'same_disc_answer', 'Pilihan paling dan paling tidak harus berbeda.' );
            }
            return [ 'most' => (int) $raw_answer['most'], 'least' => (int) $raw_answer['least'] ];
        }
        if ( ! isset( $raw_answer['choice'] ) || ! preg_match( '/\A[0-1]\z/', (string) $raw_answer['choice'] ) ) {
            return new WP_Error( 'invalid_papi_answer', 'Pilih satu pernyataan pada setiap pasangan.' );
        }
        return [ 'choice' => (int) $raw_answer['choice'] ];
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, int> $answer
     * @return array<string, mixed>
     */
    private function save_answer( array &$state, string $test_key, int $question_id, array $answer ): array {
        $test_state = $state['tests'][ $test_key ];
        $response_id = absint( $test_state['response_ids'][ $question_id ] ?? 0 );
        $saved = $this->database->save_personality_response(
            $state['candidate_id'],
            (int) $test_state['id'],
            $question_id,
            $response_id,
            $answer
        );
        if ( empty( $saved['success'] ) || empty( $saved['response_id'] ) ) {
            return [ 'success' => false, 'error' => (string) ( $saved['error'] ?? 'Jawaban belum tersimpan.' ) ];
        }
        $state['tests'][ $test_key ]['response_ids'][ $question_id ] = (int) $saved['response_id'];
        $state['tests'][ $test_key ]['answers'][ $question_id ] = $answer;
        return [ 'success' => true ];
    }

    private function handle_complete_request( int $user_id, int $candidate_id, string $test_key ): void {
        $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'recruitment_personality_complete_' . $test_key ) ) {
            wp_die( esc_html__( 'Permintaan penyelesaian tes tidak valid. Muat ulang halaman dan coba kembali.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
        if ( 'complete' !== sanitize_key( wp_unslash( $_POST['personality_action'] ?? '' ) ) ) {
            wp_die( esc_html__( 'Tindakan penyelesaian tes tidak valid.', 'recruitment-plugin' ), '', [ 'response' => 400 ] );
        }
        $state = recruitment_get_assessment_state( $user_id, self::USER_META_KEY );
        if ( ! $this->is_valid_state( $state, $candidate_id )
            || 'active' !== ( $state['tests'][ $test_key ]['status'] ?? '' )
            || $test_key !== ( $state['active_test'] ?? '' ) ) {
            wp_die( esc_html__( 'Sesi tes kepribadian tidak aktif atau tidak valid.', 'recruitment-plugin' ), '', [ 'response' => 409 ] );
        }

        $posted_answers = wp_unslash( $_POST['answers'] ?? [] );
        if ( ! is_array( $posted_answers ) ) {
            wp_die( esc_html__( 'Jawaban tes tidak valid.', 'recruitment-plugin' ), '', [ 'response' => 400 ] );
        }
        foreach ( $state['tests'][ $test_key ]['question_order'] as $question_id ) {
            $question_id = absint( $question_id );
            $raw_answer = $posted_answers[ $question_id ] ?? $state['tests'][ $test_key ]['answers'][ $question_id ] ?? null;
            $answer = $this->normalize_answer( $test_key, $raw_answer );
            if ( is_wp_error( $answer ) ) {
                wp_die( esc_html( $answer->get_error_message() ), '', [ 'response' => 400 ] );
            }
            $saved_answer = $state['tests'][ $test_key ]['answers'][ $question_id ] ?? null;
            if ( $answer !== $saved_answer ) {
                $saved = $this->save_answer( $state, $test_key, $question_id, $answer );
                if ( empty( $saved['success'] ) ) {
                    wp_die( esc_html( $saved['error'] ?? 'Jawaban tes gagal disimpan.' ), '', [ 'response' => 500 ] );
                }
                recruitment_update_assessment_state( $user_id, self::USER_META_KEY, $state );
            }
        }

        if ( count( $state['tests'][ $test_key ]['question_order'] ?? [] ) !== count( $state['tests'][ $test_key ]['response_ids'] ?? [] ) ) {
            wp_die( esc_html__( 'Semua soal harus tersimpan sebelum tes dapat diselesaikan.', 'recruitment-plugin' ), '', [ 'response' => 409 ] );
        }
        $result = $this->database->complete_personality_assessment(
            $candidate_id,
            (int) $state['tests'][ $test_key ]['id'],
            (int) $state['tests'][ $test_key ]['started_at'],
            array_values( array_map( 'absint', (array) $state['tests'][ $test_key ]['response_ids'] ) )
        );
        if ( empty( $result['success'] ) || empty( $result['result_id'] ) ) {
            wp_die( esc_html( $result['error'] ?? 'Hasil tes gagal disimpan.' ), '', [ 'response' => 500 ] );
        }

        $state['tests'][ $test_key ]['result_id'] = (int) $result['result_id'];
        $state['tests'][ $test_key ]['status'] = 'complete';
        $state['tests'][ $test_key ]['completed_at'] = time();
        $state['active_test'] = '';
        if ( 'disc' === $test_key ) {
            $state['tests']['papi']['status'] = 'available';
        } else {
            $state['completed_at'] = time();
        }
        recruitment_update_assessment_state( $user_id, self::USER_META_KEY, $state );
        wp_safe_redirect( recruitment_get_public_url( 'psych-personality', [ 'test' => $test_key ] ) );
        exit;
    }
}
