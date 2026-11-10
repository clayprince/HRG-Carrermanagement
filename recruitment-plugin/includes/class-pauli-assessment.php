<?php

/**
 * Candidate-facing Pauli assessment session controller.
 */
class Recruitment_Pauli_Assessment {

    private const USER_META_KEY = 'recruitment_pauli_assessment';
    private const IQ_USER_META_KEY = 'recruitment_iq_assessment';
    private const COLUMN_DURATION = 180;
    private const SEGMENT_COUNT = 20;
    private const ANSWER_COUNT = 900;
    private const NUMBER_COUNT = self::ANSWER_COUNT + 1;

    private Recruitment_Database $database;

    public function __construct() {
        $this->database = new Recruitment_Database();
    }

    public function handle_public_request(): void {
        if ( 'psych-pauli' !== sanitize_key( wp_unslash( $_GET['recruitment_page'] ?? '' ) ) ) {
            return;
        }

        $candidate_id = $this->get_current_candidate_id();
        if ( $candidate_id < 1 ) {
            wp_safe_redirect( add_query_arg( 'account_error', 'applicant_account_required', recruitment_get_public_url( 'applicant-login' ) ) );
            exit;
        }

        $user_id = recruitment_get_assessment_user_id();
        $state = recruitment_get_assessment_state( $user_id, self::USER_META_KEY );
        $state = is_array( $state ) ? $state : [];
        if ( 'active' === ( $state['status'] ?? '' ) && (int) ( $state['candidate_id'] ?? 0 ) === $candidate_id ) {
            $this->upgrade_session( $user_id, $state );
        }
        if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
            $this->handle_advance_request( $user_id, $candidate_id, $state );
        }

        if ( '1' === sanitize_text_field( wp_unslash( $_GET['start'] ?? '' ) ) ) {
            if ( ! in_array( $state['status'] ?? '', [ 'active', 'complete' ], true )
                || (int) ( $state['candidate_id'] ?? 0 ) !== $candidate_id ) {
                if ( ! recruitment_is_psychotest_test_enabled( 'pauli' ) ) {
                    wp_die( esc_html__( 'Tes PAULI sedang tidak tersedia untuk sesi online. Silakan periksa jadwal dengan tim HR.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
                }
                $this->start_session( $user_id, $candidate_id );
            }
            wp_safe_redirect( recruitment_get_public_url( 'psych-pauli' ) );
            exit;
        }

        if ( 'active' === ( $state['status'] ?? '' ) && (int) ( $state['candidate_id'] ?? 0 ) === $candidate_id ) {
            $this->sync_elapsed_columns( $user_id, $candidate_id, $state );
        }
    }

    public function save_column_ajax(): void {
        check_ajax_referer( 'recruitment_pauli_save_column', 'nonce' );
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
            wp_send_json_error( [ 'message' => 'Sesi tes Pauli tidak aktif.' ], 409 );
        }

        $segment = absint( wp_unslash( $_POST['segment'] ?? 0 ) );
        $current_segment = absint( $state['current_segment'] ?? 0 );
        if ( $segment !== $current_segment || $current_segment >= count( (array) ( $state['columns'] ?? [] ) ) ) {
            wp_send_json_error( [ 'message' => 'Kolom ini sudah berakhir. Halaman akan beralih ke kolom berikutnya.' ], 409 );
        }

        $answers = wp_unslash( $_POST['answers'] ?? '' );
        $column = $state['columns'][ $current_segment ] ?? [];
        $answer_count = max( 0, count( (array) ( $column['numbers'] ?? [] ) ) - 1 );
        if ( self::ANSWER_COUNT !== $answer_count || ! is_string( $answers ) || 1 !== preg_match( '/\A[0-9-]{' . $answer_count . '}\z/', $answers ) ) {
            wp_send_json_error( [ 'message' => 'Jawaban kolom Pauli tidak valid.' ], 400 );
        }

        $saved = $this->save_column( $state, $current_segment, $answers );
        if ( empty( $saved['success'] ) ) {
            wp_send_json_error( [ 'message' => (string) ( $saved['error'] ?? 'Jawaban belum tersimpan.' ) ], 500 );
        }
        recruitment_update_assessment_state( $user_id, self::USER_META_KEY, $state );
        $this->sync_elapsed_columns( $user_id, $candidate_id, $state );
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
            $total_answered = 0;
            foreach ( (array) ( $state['columns'] ?? [] ) as $completed_column ) {
                $total_answered += strlen( str_replace( '-', '', (string) ( $completed_column['answers'] ?? '' ) ) );
            }
            return [
                'complete' => true,
                'score' => absint( $state['score'] ?? 0 ),
                'column_count' => count( (array) ( $state['columns'] ?? [] ) ),
                'total_answered' => $total_answered,
            ];
        }
        if ( 'active' !== ( $state['status'] ?? '' ) ) {
            return [ 'ready' => false ];
        }

        $segment = absint( $state['current_segment'] ?? 0 );
        $column = $state['columns'][ $segment ] ?? null;
        $catalog = $this->database->get_pauli_assessment();
        if ( $this->database->has_error() ) {
            return [ 'error' => 'Koneksi Oracle belum tersedia. Silakan coba kembali nanti.' ];
        }
        $test = $catalog[0] ?? null;
        $column_count = count( (array) ( $state['columns'] ?? [] ) );
        $total_duration = absint( $state['deadline'] ?? 0 ) - absint( $state['started_at'] ?? 0 );
        if ( ! is_array( $test ) || (int) $test['ID'] !== (int) ( $state['test_id'] ?? 0 )
            || self::SEGMENT_COUNT !== $column_count || absint( $state['column_count'] ?? $column_count ) !== $column_count
            || count( $test['questions'] ) < $column_count
            || (int) $test['DURATION'] < $column_count * 3
            || (int) $test['QUESTION_COUNT'] < $column_count * self::ANSWER_COUNT
            || $total_duration !== $column_count * self::COLUMN_DURATION
            || ! is_array( $column ) || $segment >= $column_count ) {
            return [ 'error' => 'Konfigurasi tes Pauli tidak lengkap. Silakan hubungi tim HR.' ];
        }

        $questions_by_id = [];
        foreach ( $test['questions'] as $question ) {
            $questions_by_id[ (int) $question['id'] ] = $question;
        }
        $question_id = absint( $column['question_id'] ?? 0 );
        $numbers = array_map( 'absint', (array) ( $column['numbers'] ?? [] ) );
        $answer_count = count( $numbers ) - 1;
        if ( ! isset( $questions_by_id[ $question_id ] )
            || self::ANSWER_COUNT !== $answer_count
            || 1 !== preg_match( '/\A[0-9-]{' . $answer_count . '}\z/', (string) ( $column['answers'] ?? '' ) ) ) {
            return [ 'error' => 'Kolom tes Pauli tidak tersedia. Silakan hubungi tim HR.' ];
        }

        $started_at = absint( $state['started_at'] ?? 0 );
        $deadline = $started_at + $total_duration;
        $column_deadline = min( $started_at + ( ( $segment + 1 ) * self::COLUMN_DURATION ), $deadline );
        $answers = (string) ( $column['answers'] ?? str_repeat( '-', self::ANSWER_COUNT ) );
        $answer_count = count( $numbers ) - 1;
        if ( 1 !== preg_match( '/\A[0-9-]{' . $answer_count . '}\z/', $answers ) ) {
            $answers = str_repeat( '-', $answer_count );
        }
        $cursor = strpos( $answers, '-' );
        $cursor = false === $cursor ? $answer_count : $cursor;
        $answered_total = 0;
        $correct_count = 0;
        foreach ( (array) $state['columns'] as $column_index => $session_column ) {
            if ( $column_index > $segment ) {
                break;
            }
            $session_answers = (string) ( $session_column['answers'] ?? '' );
            $answered_total += strlen( str_replace( '-', '', $session_answers ) );
            $correct_count += $this->count_correct_answers( $session_column );
        }

        return [
            'ready' => true,
            'test' => [
                'title' => (string) $test['TITLE'],
                'segment' => $segment,
                'segment_count' => $column_count,
                'duration_minutes' => $column_count * 3,
                'segment_deadline' => $column_deadline,
                'deadline' => $deadline,
                'numbers' => $numbers,
                'answers' => $answers,
                'cursor' => $cursor,
                'answered' => $answered_total,
                'correct' => $correct_count,
                'incorrect' => $answered_total - $correct_count,
            ],
            'nonce' => wp_create_nonce( 'recruitment_pauli_save_column' ),
            'advance_nonce' => wp_create_nonce( 'recruitment_pauli_advance' ),
            'ajax_url' => admin_url( 'admin-ajax.php' ),
        ];
    }

    private function get_current_candidate_id(): int {
        return recruitment_get_assessment_user_id();
    }

    private function start_session( int $user_id, int $candidate_id ): void {
        if ( ! recruitment_consume_psychotest_consent( 'pauli' ) ) {
            wp_die( esc_html__( 'Berikan persetujuan tes terlebih dahulu.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
        $iq_state = recruitment_get_assessment_state( $user_id, self::IQ_USER_META_KEY );
        if ( ! is_array( $iq_state ) || 'complete' !== ( $iq_state['status'] ?? '' )
            || (int) ( $iq_state['candidate_id'] ?? 0 ) !== $candidate_id ) {
            wp_die( esc_html__( 'Tes Pauli dapat dimulai setelah Tes IQ kandidat selesai.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }

        $global_settings = (array) get_option( 'recruitment_psychotest_settings', [] );
        $test_settings = (array) get_option( 'recruitment_psychotest_tests', [] );
        if ( ( isset( $global_settings['status'] ) && 'active' !== $global_settings['status'] )
            || ( isset( $test_settings['pauli']['active'] ) && empty( $test_settings['pauli']['active'] ) ) ) {
            wp_die( esc_html__( 'Tes Pauli sedang dinonaktifkan oleh tim HR.', 'recruitment-plugin' ) );
        }

        $catalog = $this->database->get_pauli_assessment();
        if ( $this->database->has_error() ) {
            wp_die( esc_html__( 'Koneksi Oracle belum tersedia. Silakan coba kembali nanti.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
        }
        $test = $catalog[0] ?? null;
        if ( ! is_array( $test ) || empty( $test['questions'] )
            || (int) $test['DURATION'] !== count( $test['questions'] ) * 3
            || count( (array) ( $test['questions'] ?? [] ) ) < self::SEGMENT_COUNT
            || (int) $test['DURATION'] < self::SEGMENT_COUNT * 3
            || (int) $test['QUESTION_COUNT'] < self::SEGMENT_COUNT * self::ANSWER_COUNT ) {
            wp_die( esc_html__( 'Bank soal Pauli belum tersedia atau belum lengkap. Silakan hubungi tim HR.', 'recruitment-plugin' ) );
        }

        $columns = [];
        foreach ( array_slice( $test['questions'], 0, self::SEGMENT_COUNT ) as $question ) {
            $numbers = [];
            for ( $number = 0; $number < self::NUMBER_COUNT; $number++ ) {
                $numbers[] = random_int( 0, 9 );
            }
            $columns[] = [
                'question_id' => (int) $question['id'],
                'numbers' => $numbers,
                'answers' => str_repeat( '-', self::ANSWER_COUNT ),
                'response_id' => 0,
                'score' => 0,
            ];
        }

        $now = time();
        recruitment_update_assessment_state(
            $user_id,
            self::USER_META_KEY,
            [
                'version' => 1,
                'interface' => 'sequential',
                'candidate_id' => $candidate_id,
                'test_id' => (int) $test['ID'],
                'status' => 'active',
                'started_at' => $now,
                'deadline' => $now + ( count( $columns ) * self::COLUMN_DURATION ),
                'current_segment' => 0,
                'column_count' => count( $columns ),
                'columns' => $columns,
                'result_id' => 0,
                'score' => 0,
            ]
        );
    }

    /**
     * Expand active sessions created with the earlier 99-answer worksheet.
     *
     * @param array<string, mixed> $state
     */
    private function upgrade_session( int $user_id, array &$state ): void {
        $changed = false;
        foreach ( (array) ( $state['columns'] ?? [] ) as &$column ) {
            if ( ! is_array( $column ) ) {
                continue;
            }
            $numbers = array_map( 'absint', (array) ( $column['numbers'] ?? [] ) );
            $answers = (string) ( $column['answers'] ?? '' );
            if ( count( $numbers ) < self::NUMBER_COUNT ) {
                for ( $index = count( $numbers ); $index < self::NUMBER_COUNT; $index++ ) {
                    $numbers[] = random_int( 0, 9 );
                }
                $column['numbers'] = $numbers;
                $column['answers'] = str_pad( substr( $answers, 0, self::ANSWER_COUNT ), self::ANSWER_COUNT, '-' );
                $changed = true;
            }
            $column['score'] = $this->count_correct_answers( $column );
        }
        unset( $column );
        if ( 'sequential' !== ( $state['interface'] ?? '' ) ) {
            $state['interface'] = 'sequential';
            $changed = true;
        }
        if ( $changed ) {
            recruitment_update_assessment_state( $user_id, self::USER_META_KEY, $state );
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    private function handle_advance_request( int $user_id, int $candidate_id, array $state ): void {
        $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'recruitment_pauli_advance' ) ) {
            wp_die( esc_html__( 'Permintaan tes tidak valid. Muat ulang halaman dan coba kembali.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
        }
        if ( 'advance' !== sanitize_key( wp_unslash( $_POST['pauli_action'] ?? '' ) )
            || 'active' !== ( $state['status'] ?? '' )
            || (int) ( $state['candidate_id'] ?? 0 ) !== $candidate_id ) {
            wp_die( esc_html__( 'Sesi tes Pauli tidak aktif atau tidak valid.', 'recruitment-plugin' ), '', [ 'response' => 409 ] );
        }

        $segment = absint( wp_unslash( $_POST['segment'] ?? 0 ) );
        $current_segment = absint( $state['current_segment'] ?? 0 );
        if ( $segment !== $current_segment || $current_segment >= count( (array) ( $state['columns'] ?? [] ) )
            || time() < ( absint( $state['started_at'] ?? 0 ) + ( ( $current_segment + 1 ) * self::COLUMN_DURATION ) ) ) {
            wp_die( esc_html__( 'Kolom Pauli hanya dapat ditutup setelah aba-aba tiga menit.', 'recruitment-plugin' ), '', [ 'response' => 409 ] );
        }

        $answers = wp_unslash( $_POST['answers'] ?? '' );
        $column = $state['columns'][ $current_segment ] ?? [];
        $answer_count = max( 0, count( (array) ( $column['numbers'] ?? [] ) ) - 1 );
        if ( is_string( $answers ) && 99 === strlen( $answers ) && self::ANSWER_COUNT === $answer_count ) {
            $answers = str_pad( $answers, self::ANSWER_COUNT, '-' );
        }
        if ( self::ANSWER_COUNT !== $answer_count || ! is_string( $answers ) || 1 !== preg_match( '/\A[0-9-]{' . $answer_count . '}\z/', $answers ) ) {
            wp_die( esc_html__( 'Jawaban kolom Pauli tidak valid.', 'recruitment-plugin' ), '', [ 'response' => 400 ] );
        }
        $saved = $this->save_column( $state, $current_segment, $answers );
        if ( empty( $saved['success'] ) ) {
            wp_die( esc_html( $saved['error'] ?? 'Jawaban kolom Pauli gagal disimpan.' ), '', [ 'response' => 500 ] );
        }

        $state['current_segment'] = $current_segment + 1;
        recruitment_update_assessment_state( $user_id, self::USER_META_KEY, $state );
        $this->sync_elapsed_columns( $user_id, $candidate_id, $state );
        wp_safe_redirect( recruitment_get_public_url( 'psych-pauli' ) );
        exit;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function sync_elapsed_columns( int $user_id, int $candidate_id, array &$state ): void {
        if ( (int) ( $state['candidate_id'] ?? 0 ) !== $candidate_id || 'active' !== ( $state['status'] ?? '' ) ) {
            return;
        }

        $started_at = absint( $state['started_at'] ?? 0 );
        $column_count = count( (array) ( $state['columns'] ?? [] ) );
        $target_segment = min( intdiv( max( 0, time() - $started_at ), self::COLUMN_DURATION ), $column_count );
        while ( absint( $state['current_segment'] ?? 0 ) < $target_segment ) {
            $segment = absint( $state['current_segment'] );
            $column = $state['columns'][ $segment ] ?? [];
            $answers = (string) ( $column['answers'] ?? str_repeat( '-', max( 0, count( (array) ( $column['numbers'] ?? [] ) ) - 1 ) ) );
            $saved = $this->save_column( $state, $segment, $answers );
            if ( empty( $saved['success'] ) ) {
                wp_die( esc_html( $saved['error'] ?? 'Jawaban kolom Pauli gagal disimpan.' ), '', [ 'response' => 500 ] );
            }
            $state['current_segment'] = $segment + 1;
            recruitment_update_assessment_state( $user_id, self::USER_META_KEY, $state );
        }

        if ( absint( $state['current_segment'] ?? 0 ) >= $column_count && 'active' === ( $state['status'] ?? '' ) ) {
            $this->complete_session( $user_id, $candidate_id, $state );
        }
    }

    /**
     * @param array<string, mixed> $state
     * @return array{success: bool, error?: string}
     */
    private function save_column( array &$state, int $segment, string $answers ): array {
        $column = $state['columns'][ $segment ] ?? null;
        $numbers = is_array( $column ) ? array_map( 'absint', (array) ( $column['numbers'] ?? [] ) ) : [];
        $answer_count = count( $numbers ) - 1;
        if ( ! is_array( $column ) || self::ANSWER_COUNT !== $answer_count
            || 1 !== preg_match( '/\A[0-9-]{' . $answer_count . '}\z/', $answers ) ) {
            return [ 'success' => false, 'error' => 'Kolom Pauli tidak ditemukan.' ];
        }

        $saved = $this->database->save_pauli_column_response(
            $state['candidate_id'],
            absint( $state['test_id'] ?? 0 ),
            absint( $column['question_id'] ?? 0 ),
            absint( $column['response_id'] ?? 0 ),
            $answers,
            $numbers
        );
        if ( empty( $saved['success'] ) || empty( $saved['response_id'] ) ) {
            return [ 'success' => false, 'error' => (string) ( $saved['error'] ?? 'Jawaban kolom Pauli gagal disimpan.' ) ];
        }
        $state['columns'][ $segment ]['answers'] = $answers;
        $state['columns'][ $segment ]['response_id'] = (int) $saved['response_id'];
        $state['columns'][ $segment ]['score'] = $this->count_correct_answers( $state['columns'][ $segment ] );
        return [ 'success' => true ];
    }

    /**
     * @param array<string, mixed> $column
     */
    private function count_correct_answers( array $column ): int {
        $numbers = array_map( 'absint', (array) ( $column['numbers'] ?? [] ) );
        $answers = (string) ( $column['answers'] ?? '' );
        $limit = min( strlen( $answers ), max( 0, count( $numbers ) - 1 ) );
        $correct = 0;
        for ( $index = 0; $index < $limit; $index++ ) {
            if ( ctype_digit( $answers[ $index ] )
                && (int) $answers[ $index ] === ( $numbers[ $index ] + $numbers[ $index + 1 ] ) % 10 ) {
                $correct++;
            }
        }
        return $correct;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function complete_session( int $user_id, int $candidate_id, array &$state ): void {
        $result = $this->database->complete_pauli_assessment(
            $candidate_id,
            absint( $state['test_id'] ?? 0 ),
            gmdate( 'Y-m-d H:i:s', absint( $state['started_at'] ?? 0 ) ),
            array_column( (array) ( $state['columns'] ?? [] ), 'response_id' )
        );
        if ( empty( $result['success'] ) || empty( $result['result_id'] ) ) {
            wp_die( esc_html( $result['error'] ?? 'Hasil tes Pauli gagal disimpan.' ), '', [ 'response' => 500 ] );
        }
        $state['status'] = 'complete';
        $state['result_id'] = (int) $result['result_id'];
        $state['score'] = (int) $result['score'];
        $state['completed_at'] = time();
        recruitment_update_assessment_state( $user_id, self::USER_META_KEY, $state );
    }
}
