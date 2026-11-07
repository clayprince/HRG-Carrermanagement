<?php

/**
 * Candidate-facing IQ assessment session controller.
 */
class Recruitment_IQ_Assessment {

    private const USER_META_KEY = 'recruitment_iq_assessment';
    private const SUBTEST_DURATIONS = [ 7, 7, 5, 12 ];

    private Recruitment_Database $database;

    public function __construct() {
        $this->database = new Recruitment_Database();
    }

    public function handle_public_request(): void {
        if ( 'psych-iq' !== sanitize_key( wp_unslash( $_GET['recruitment_page'] ?? '' ) ) ) {
            return;
        }

        $candidate_id = $this->get_current_candidate_id();
        if ( $candidate_id < 1 ) {
            wp_safe_redirect( add_query_arg( 'account_error', 'applicant_account_required', recruitment_get_public_url( 'applicant-login' ) ) );
            exit;
        }

        $user_id = recruitment_get_assessment_user_id();
        $state = recruitment_get_assessment_state( $user_id, self::USER_META_KEY );
        if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
            $this->handle_advance_request( $user_id, $candidate_id, is_array( $state ) ? $state : [] );
        }

        if ( '1' === sanitize_text_field( wp_unslash( $_GET['start'] ?? '' ) ) ) {
            if ( ! is_array( $state ) || empty( $state['status'] ) ) {
                $this->start_session( $user_id, $candidate_id );
            }
            wp_safe_redirect( recruitment_get_public_url( 'psych-iq' ) );
            exit;
        }

        if ( is_array( $state ) && 'active' === ( $state['status'] ?? '' ) ) {
            $index = absint( $state['subtest_index'] ?? 0 );
            $deadline = absint( $state['tests'][ $index ]['deadline'] ?? 0 );
            if ( $deadline > 0 && time() >= $deadline ) {
                $this->advance_subtest( $user_id, $candidate_id, $state );
                wp_safe_redirect( recruitment_get_public_url( 'psych-iq' ) );
                exit;
            }
        }
    }

    public function save_answer_ajax(): void {
        check_ajax_referer( 'recruitment_iq_save_answer', 'nonce' );
        $candidate_id = $this->get_current_candidate_id();
        if ( $candidate_id < 1 ) {
            $database_error = $this->database->has_error();
            wp_send_json_error(
                [ 'message' => $database_error ? 'Koneksi Oracle belum tersedia.' : 'Profil kandidat tidak valid.' ],
                $database_error ? 503 : 403
            );
        }

        $user_id = recruitment_get_assessment_user_id();
        $state = recruitment_get_assessment_state( $user_id, self::USER_META_KEY );
        if ( ! is_array( $state ) || 'active' !== ( $state['status'] ?? '' ) || (int) ( $state['candidate_id'] ?? 0 ) !== $candidate_id ) {
            wp_send_json_error( [ 'message' => 'Sesi tes tidak aktif.' ], 409 );
        }

        $index = absint( $state['subtest_index'] ?? 0 );
        $test = $state['tests'][ $index ] ?? null;
        if ( ! is_array( $test ) || time() >= absint( $test['deadline'] ?? 0 ) ) {
            wp_send_json_error( [ 'message' => 'Waktu subtes sudah habis. Halaman akan melanjutkan ke subtes berikutnya.' ], 409 );
        }

        $question_id = absint( $_POST['question_id'] ?? 0 );
        $allowed_questions = array_map( 'absint', (array) ( $test['question_order'] ?? [] ) );
        if ( ! in_array( $question_id, $allowed_questions, true ) ) {
            wp_send_json_error( [ 'message' => 'Soal tidak termasuk dalam subtes yang sedang dikerjakan.' ], 400 );
        }
        $raw_answers = wp_unslash( $_POST['selected_answers'] ?? [] );
        $selected_answers = is_array( $raw_answers ) ? array_map( 'absint', $raw_answers ) : [];
        $saved = $this->save_answer( $state, $index, $question_id, $selected_answers );
        if ( empty( $saved['success'] ) ) {
            wp_send_json_error( [ 'message' => (string) ( $saved['error'] ?? 'Jawaban belum tersimpan.' ) ], 400 );
        }

        recruitment_update_assessment_state( $user_id, self::USER_META_KEY, $state );
        wp_send_json_success( [ 'saved' => true ] );
    }

    /**
     * @return array<string, mixed>
     */
    public function get_page_view(): array {
        $candidate_id = $this->get_current_candidate_id();
        if ( $candidate_id < 1 ) {
            return [ 'login_required' => true ];
        }
        $user_id = recruitment_get_assessment_user_id();
        $state = recruitment_get_assessment_state( $user_id, self::USER_META_KEY );
        if ( $candidate_id < 1 || ! is_array( $state ) || (int) ( $state['candidate_id'] ?? 0 ) !== $candidate_id ) {
            return [ 'ready' => false ];
        }
        if ( 'complete' === ( $state['status'] ?? '' ) ) {
            return [ 'complete' => true ];
        }
        if ( 'active' !== ( $state['status'] ?? '' ) ) {
            return [ 'ready' => false ];
        }

        $index = absint( $state['subtest_index'] ?? 0 );
        $test_state = $state['tests'][ $index ] ?? null;
        $catalog = $this->database->get_iq_assessment();
        if ( $this->database->has_error() ) {
            return [ 'error' => 'Koneksi Oracle belum tersedia. Silakan coba kembali nanti.' ];
        }
        $test = $catalog[ $index ] ?? null;
        if ( ! is_array( $test_state ) || ! is_array( $test ) || (int) $test['ID'] !== (int) $test_state['id'] ) {
            return [ 'error' => 'Konfigurasi tes IQ tidak lengkap. Silakan hubungi tim HR.' ];
        }

        $questions_by_id = [];
        foreach ( $test['questions'] as $question ) {
            $questions_by_id[ (int) $question['id'] ] = $question;
        }
        $questions = [];
        foreach ( (array) $test_state['question_order'] as $order => $question_id ) {
            $question_id = absint( $question_id );
            if ( ! isset( $questions_by_id[ $question_id ] ) ) {
                return [ 'error' => 'Soal pada sesi ini tidak lagi tersedia. Silakan hubungi tim HR.' ];
            }
            $question = $questions_by_id[ $question_id ];
            $choice_order = array_map( 'absint', (array) ( $test_state['choice_order'][ $question_id ] ?? [] ) );
            $choices_by_id = [];
            foreach ( $question['choices'] as $choice ) {
                $choices_by_id[ (int) $choice['id'] ] = [
                    'id' => (int) $choice['id'],
                    'text' => (string) $choice['text'],
                ];
            }
            $question['choices'] = [];
            foreach ( $choice_order as $choice_id ) {
                if ( isset( $choices_by_id[ $choice_id ] ) ) {
                    $question['choices'][] = $choices_by_id[ $choice_id ];
                }
            }
            $question['number'] = $order + 1;
            $question['selected_answers'] = array_map(
                'absint',
                (array) ( $test_state['answers'][ $question_id ] ?? [] )
            );
            $questions[] = $question;
        }

        return [
            'ready' => true,
            'test' => [
                'title' => (string) $test['TITLE'],
                'duration' => (int) $test['DURATION'],
                'deadline' => (int) $test_state['deadline'],
                'index' => $index,
                'count' => count( $state['tests'] ),
                'instructions' => (string) $test['INSTRUCTIONS'],
            ],
            'questions' => $questions,
            'nonce' => wp_create_nonce( 'recruitment_iq_save_answer' ),
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'advance_nonce' => wp_create_nonce( 'recruitment_iq_advance' ),
        ];
    }

    private function get_current_candidate_id(): int {
        return recruitment_get_assessment_user_id();
    }

    private function start_session( int $user_id, int $candidate_id ): void {
        if ( ! recruitment_consume_psychotest_consent( 'iq' ) ) {
            wp_die( esc_html__( 'Berikan persetujuan tes terlebih dahulu.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
        $global_settings = (array) get_option( 'recruitment_psychotest_settings', [] );
        $test_settings = (array) get_option( 'recruitment_psychotest_tests', [] );
        if ( isset( $global_settings['status'] ) && 'active' !== $global_settings['status'] ) {
            wp_die( esc_html__( 'Tes psikologi sedang dinonaktifkan oleh tim HR.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
        if ( empty( $test_settings['iq']['active'] ) && isset( $test_settings['iq']['active'] ) ) {
            wp_die( esc_html__( 'Tes IQ sedang dinonaktifkan oleh tim HR.', 'recruitment-plugin' ) );
        }
        $catalog = $this->database->get_iq_assessment();
        if ( $this->database->has_error() ) {
            wp_die( esc_html__( 'Koneksi Oracle belum tersedia. Silakan coba kembali nanti.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
        }
        if ( 4 !== count( $catalog ) ) {
            wp_die( esc_html__( 'Bank soal IQ belum tersedia atau belum lengkap. Silakan hubungi tim HR.', 'recruitment-plugin' ) );
        }

        $tests = [];
        foreach ( $catalog as $index => $test ) {
            if ( ! isset( self::SUBTEST_DURATIONS[ $index ] )
                || (int) $test['DURATION'] !== self::SUBTEST_DURATIONS[ $index ]
                || empty( $test['questions'] )
                || (int) $test['QUESTION_COUNT'] !== count( $test['questions'] ) ) {
                wp_die( esc_html__( 'Setiap subtes IQ harus memiliki soal dan durasi yang valid. Silakan hubungi tim HR.', 'recruitment-plugin' ) );
            }
            $question_ids = [];
            $choice_order = [];
            foreach ( $test['questions'] as $question ) {
                $question_id = (int) $question['id'];
                if ( count( $question['choices'] ) < 2 ) {
                    wp_die( esc_html__( 'Ada soal IQ yang belum memiliki pilihan jawaban lengkap.', 'recruitment-plugin' ) );
                }
                $question_ids[] = $question_id;
                $choice_order[ $question_id ] = array_map( 'intval', array_column( $question['choices'], 'id' ) );
                shuffle( $choice_order[ $question_id ] );
            }
            shuffle( $question_ids );
            $tests[] = [
                'id' => (int) $test['ID'],
                'duration' => (int) $test['DURATION'],
                'started_at' => 0,
                'deadline' => 0,
                'question_order' => $question_ids,
                'choice_order' => $choice_order,
                'answers' => [],
                'response_ids' => [],
                'result_id' => 0,
            ];
        }

        $now = time();
        $tests[0]['started_at'] = $now;
        $tests[0]['deadline'] = $now + ( $tests[0]['duration'] * MINUTE_IN_SECONDS );
        recruitment_update_assessment_state(
            $user_id,
            self::USER_META_KEY,
            [
                'version' => 1,
                'candidate_id' => $candidate_id,
                'status' => 'active',
                'subtest_index' => 0,
                'tests' => $tests,
                'results' => [],
            ]
        );
    }

    /**
     * @param array<string, mixed> $state
     */
    private function handle_advance_request( int $user_id, int $candidate_id, array $state ): void {
        $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'recruitment_iq_advance' ) ) {
            wp_die( esc_html__( 'Permintaan tes tidak valid. Muat ulang halaman dan coba kembali.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
        if ( 'advance' !== sanitize_key( wp_unslash( $_POST['iq_action'] ?? '' ) )
            || 'active' !== ( $state['status'] ?? '' )
            || (int) ( $state['candidate_id'] ?? 0 ) !== $candidate_id ) {
            wp_die( esc_html__( 'Sesi tes tidak aktif atau tidak valid.', 'recruitment-plugin' ), '', [ 'response' => 409 ] );
        }

        $index = absint( $state['subtest_index'] ?? 0 );
        $test = $state['tests'][ $index ] ?? null;
        if ( ! is_array( $test ) ) {
            wp_die( esc_html__( 'Tahap tes tidak ditemukan.', 'recruitment-plugin' ), '', [ 'response' => 409 ] );
        }
        if ( time() >= absint( $test['deadline'] ?? 0 ) ) {
            $this->advance_subtest( $user_id, $candidate_id, $state );
            wp_safe_redirect( recruitment_get_public_url( 'psych-iq' ) );
            exit;
        }
        $posted_answers = wp_unslash( $_POST['answers'] ?? [] );
        $answer_markers = wp_unslash( $_POST['answer_present'] ?? [] );
        if ( ! is_array( $posted_answers ) || ! is_array( $answer_markers ) ) {
            wp_die( esc_html__( 'Jawaban tes tidak valid.', 'recruitment-plugin' ), '', [ 'response' => 400 ] );
        }
        foreach ( array_keys( $answer_markers ) as $question_id ) {
            $question_id = absint( $question_id );
            $allowed_questions = array_map( 'absint', (array) $test['question_order'] );
            if ( ! in_array( $question_id, $allowed_questions, true ) ) {
                wp_die( esc_html__( 'Jawaban tes tidak valid.', 'recruitment-plugin' ), '', [ 'response' => 400 ] );
            }
            $answer_ids = $posted_answers[ $question_id ] ?? [];
            if ( ! is_array( $answer_ids ) ) {
                wp_die( esc_html__( 'Jawaban tes tidak valid.', 'recruitment-plugin' ), '', [ 'response' => 400 ] );
            }
            $answer_ids = array_values( array_filter( array_map( 'absint', $answer_ids ) ) );
            $has_saved_response = ! empty( $test['response_ids'][ $question_id ] );
            if ( ! $answer_ids && ! $has_saved_response ) {
                continue;
            }
            $saved = $this->save_answer( $state, $index, $question_id, $answer_ids );
            if ( empty( $saved['success'] ) ) {
                wp_die( esc_html( $saved['error'] ?? 'Jawaban belum berhasil tersimpan.' ), '', [ 'response' => 500 ] );
            }
            recruitment_update_assessment_state( $user_id, self::USER_META_KEY, $state );
        }

        $this->advance_subtest( $user_id, $candidate_id, $state );
        wp_safe_redirect( recruitment_get_public_url( 'psych-iq' ) );
        exit;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function save_answer( array &$state, int $index, int $question_id, array $selected_answers ): array {
        $selected_answers = array_values( array_unique( array_map( 'absint', $selected_answers ) ) );
        if ( count( $selected_answers ) > 2 ) {
            return [ 'success' => false, 'error' => 'Pilih paling banyak dua jawaban untuk soal ini.' ];
        }
        $test = $state['tests'][ $index ] ?? null;
        if ( ! is_array( $test ) ) {
            return [ 'success' => false, 'error' => 'Subtes aktif tidak ditemukan.' ];
        }
        $response_id = absint( $test['response_ids'][ $question_id ] ?? 0 );
        $result = $this->database->save_iq_response( $state['candidate_id'], $question_id, $selected_answers, $response_id );
        if ( empty( $result['success'] ) || empty( $result['response_id'] ) ) {
            return [ 'success' => false, 'error' => (string) ( $result['error'] ?? 'Jawaban belum tersimpan.' ) ];
        }
        $state['tests'][ $index ]['response_ids'][ $question_id ] = (int) $result['response_id'];
        $state['tests'][ $index ]['answers'][ $question_id ] = $selected_answers;
        return [ 'success' => true ];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function advance_subtest( int $user_id, int $candidate_id, array $state ): void {
        $index = absint( $state['subtest_index'] ?? 0 );
        $test = $state['tests'][ $index ] ?? null;
        if ( ! is_array( $test ) || (int) ( $state['candidate_id'] ?? 0 ) !== $candidate_id ) {
            wp_die( esc_html__( 'Sesi tes tidak dapat dilanjutkan.', 'recruitment-plugin' ), '', [ 'response' => 409 ] );
        }
        $result = $this->database->complete_iq_subtest(
            $candidate_id,
            (int) $test['id'],
            gmdate( 'Y-m-d H:i:s', (int) $test['started_at'] ),
            array_values( array_map( 'absint', (array) $test['response_ids'] ) )
        );
        if ( empty( $result['success'] ) || empty( $result['result_id'] ) ) {
            wp_die( esc_html( $result['error'] ?? 'Hasil subtes gagal disimpan.' ), '', [ 'response' => 500 ] );
        }

        $state['tests'][ $index ]['result_id'] = (int) $result['result_id'];
        $state['results'][] = (int) $result['result_id'];
        $next_index = $index + 1;
        if ( $next_index >= count( $state['tests'] ) ) {
            $state['status'] = 'complete';
            $state['completed_at'] = time();
        } else {
            $state['subtest_index'] = $next_index;
            $now = time();
            $state['tests'][ $next_index ]['started_at'] = $now;
            $state['tests'][ $next_index ]['deadline'] = $now + ( (int) $state['tests'][ $next_index ]['duration'] * MINUTE_IN_SECONDS );
        }
        recruitment_update_assessment_state( $user_id, self::USER_META_KEY, $state );
    }
}
