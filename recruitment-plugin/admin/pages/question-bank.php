<?php
$cognitive_subtests = [
    'verbal_analogy' => 'Verbal Analogy',
    'reading_comprehension' => 'Reading Comprehension',
    'deductive_reasoning' => 'Deductive Reasoning',
    'quantitative_reasoning' => 'Quantitative Reasoning',
    'spatial_ability' => 'Spatial Ability',
    'inductive_reasoning' => 'Inductive Reasoning',
    'iq_numbers' => 'IQ - Berhitung Angka',
    'iq_assembly' => 'IQ - Gabungan Bagian',
    'iq_words' => 'IQ - Hubungan Kata',
    'iq_abstraction' => 'IQ - Abstraksi Nonverbal',
];

$personality_subtests = [
    'grit' => 'GRIT',
    'diversity' => 'Diversity',
    'humility' => 'Humility',
    'disc' => 'DISC',
    'integrity' => 'Integrity',
    'papi' => 'Papikostik',
    'papikostick' => 'Papikostik',
];

$test_labels = [
    'iq' => 'Test Kognitif',
    'disc' => 'DISC',
    'papi' => 'Papikostik',
    'grit' => 'GRIT',
    'diversity' => 'Diversity',
    'humility' => 'Humility',
    'integrity' => 'Integrity',
];

$subtest_labels = array_merge( $cognitive_subtests, $personality_subtests );

$type_labels = [
    'multiple_choice' => 'Pilihan Ganda',
    'multi_select' => 'Pilihan Ganda (Multi-select)',
    'numeric' => 'Numerik',
    'paired' => 'Pernyataan',
];

$database = new Recruitment_Database();
$questions = $database->get_psychotest_question_bank();
// Exclude Pauli questions completely - Pauli is dynamically generated via algorithm
$questions = array_values( array_filter( $questions, static fn( $q ) => 'pauli' !== ( $q['test'] ?? '' ) ) );

$notice = '';
$error = $database->has_error()
    ? 'Bank soal belum dapat dimuat dari Oracle. Silakan coba kembali.'
    : '';
$pdf_preview = false;
$pdf_preview_test = 'auto';
$pdf_preview_subtest = '';
$pdf_preview_text = '';
$pdf_source_name = '';
$pdf_preview_count = 0;
$pdf_preview_questions = [];
$pdf_selected_indexes = [];
$pdf_question_answers = [];
$pdf_parse_error = '';
$success_redirect_url = '';
$pdf_importer = new Recruitment_Psychotest_Pdf_Importer();

if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) && isset( $_POST['question_bank_action'] ) ) {
    check_admin_referer( 'recruitment_question_bank' );
    if ( ! current_user_can( 'manage_recruitment' ) ) {
        wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengelola bank soal.', 'recruitment-plugin' ) );
    }

    $action = ! empty( $_POST['reparse_pdf_import'] )
        ? 'reparse_pdf_import'
        : sanitize_key( wp_unslash( (string) $_POST['question_bank_action'] ) );
    $question_id = isset( $_POST['question_id'] ) && is_scalar( $_POST['question_id'] )
        ? absint( wp_unslash( (string) $_POST['question_id'] ) )
        : 0;
    $posted = isset( $_POST['question'] ) && is_array( $_POST['question'] )
        ? wp_unslash( $_POST['question'] )
        : [];
    $test = isset( $posted['test'] ) && is_scalar( $posted['test'] )
        ? sanitize_key( (string) $posted['test'] )
        : '';
    $prompt = isset( $posted['prompt'] ) && is_scalar( $posted['prompt'] )
        ? sanitize_textarea_field( (string) $posted['prompt'] )
        : '';
    $image_url = isset( $posted['image_url'] ) && is_scalar( $posted['image_url'] )
        ? esc_url_raw( trim( (string) $posted['image_url'] ) )
        : '';
    $explanation = isset( $posted['explanation'] ) && is_scalar( $posted['explanation'] )
        ? sanitize_textarea_field( (string) $posted['explanation'] )
        : '';
    $is_active = isset( $posted['is_active'] ) && is_scalar( $posted['is_active'] ) && '1' === (string) $posted['is_active'];
    $result = [ 'success' => false, 'error' => 'Aksi bank soal tidak valid.' ];

    if ( 'delete_question' === $action && $question_id > 0 ) {
        $result = $database->delete_psychotest_question( $question_id );
        if ( ! empty( $result['success'] ) ) {
            $redirect_url = add_query_arg( [ 'page' => 'recruitment-question-bank', 'deleted' => '1' ], admin_url( 'admin.php' ) );
            if ( ! headers_sent() ) {
                wp_safe_redirect( $redirect_url );
                exit;
            }
            $success_redirect_url = $redirect_url;
            $notice = 'Soal berhasil dihapus dari Bank Soal.';
            $questions = array_values( array_filter( $database->get_psychotest_question_bank(), static fn( $q ) => 'pauli' !== ( $q['test'] ?? '' ) ) );
        } else {
            $error = (string) ( $result['error'] ?? 'Gagal menghapus soal.' );
        }
    } elseif ( 'toggle_question_status' === $action && $question_id > 0 ) {
        $result = $database->toggle_psychotest_question_status( $question_id );
        if ( ! empty( $result['success'] ) ) {
            $redirect_url = add_query_arg( [ 'page' => 'recruitment-question-bank', 'toggled' => '1' ], admin_url( 'admin.php' ) );
            if ( ! headers_sent() ) {
                wp_safe_redirect( $redirect_url );
                exit;
            }
            $success_redirect_url = $redirect_url;
            $notice = 'Status soal berhasil diubah.';
            $questions = array_values( array_filter( $database->get_psychotest_question_bank(), static fn( $q ) => 'pauli' !== ( $q['test'] ?? '' ) ) );
        } else {
            $error = (string) ( $result['error'] ?? 'Gagal mengubah status soal.' );
        }
    } elseif ( in_array( $action, [ 'preview_pdf_import', 'reparse_pdf_import', 'save_pdf_import' ], true ) ) {
        $requested_pdf_test = $test;
        $pdf_preview_subtest = isset( $posted['subtest'] ) && is_scalar( $posted['subtest'] )
            ? sanitize_key( (string) $posted['subtest'] )
            : '';
        if ( ! in_array( $requested_pdf_test, [ 'auto', 'iq', 'disc', 'papi' ], true )
            && ! ( 'reparse_pdf_import' === $action && '' === $requested_pdf_test ) ) {
            $result = [ 'success' => false, 'error' => 'Jenis tes untuk impor PDF tidak valid.' ];
        } elseif ( 'preview_pdf_import' === $action ) {
            $upload = isset( $_FILES['source_pdf'] ) && is_array( $_FILES['source_pdf'] )
                ? $_FILES['source_pdf']
                : [];
            $upload_error = isset( $upload['error'] ) && is_scalar( $upload['error'] )
                ? (int) $upload['error']
                : UPLOAD_ERR_NO_FILE;
            $temporary_path = isset( $upload['tmp_name'] ) && is_string( $upload['tmp_name'] )
                ? $upload['tmp_name']
                : '';
            $filename = isset( $upload['name'] ) && is_string( $upload['name'] )
                ? sanitize_file_name( $upload['name'] )
                : '';
            $pdf_source_name = $filename;
            $mime = class_exists( 'finfo' ) && '' !== $temporary_path
                ? ( new finfo( FILEINFO_MIME_TYPE ) )->file( $temporary_path )
                : '';
            $header = '' !== $temporary_path && is_readable( $temporary_path )
                ? file_get_contents( $temporary_path, false, null, 0, 5 )
                : false;

            if ( UPLOAD_ERR_OK !== $upload_error || ! is_uploaded_file( $temporary_path ) ) {
                $result = [ 'success' => false, 'error' => UPLOAD_ERR_INI_SIZE === $upload_error || UPLOAD_ERR_FORM_SIZE === $upload_error
                    ? 'Ukuran PDF melebihi batas unggah server.'
                    : 'Pilih file PDF yang valid untuk diproses.' ];
            } elseif ( 'pdf' !== strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) )
                || 'application/pdf' !== $mime
                || '%PDF-' !== $header ) {
                $result = [ 'success' => false, 'error' => 'File harus berformat PDF yang valid (ekstensi dan MIME application/pdf).' ];
            } else {
                $result = $pdf_importer->extract_text( $temporary_path );
                if ( ! empty( $result['success'] ) ) {
                    $pdf_preview = true;
                    $pdf_preview_text = (string) ( $result['text'] ?? '' );
                    $pdf_preview_test = 'auto' === $requested_pdf_test
                        ? $pdf_importer->detect_test( $pdf_preview_text )
                        : $requested_pdf_test;
                    $parsed = $pdf_importer->parse_questions( $pdf_preview_text, $requested_pdf_test, $pdf_preview_subtest );
                    if ( ! empty( $parsed['success'] ) ) {
                        $pdf_preview_questions = array_values( $parsed['questions'] ?? [] );
                        $pdf_preview_count = count( $pdf_preview_questions );
                        $pdf_selected_indexes = array_keys( $pdf_preview_questions );
                        $pdf_preview_subtest = (string) ( $pdf_preview_questions[0]['subtest'] ?? '' );
                    } else {
                        $pdf_parse_error = (string) ( $parsed['error'] ?? 'Format grup soal perlu diperiksa.' );
                    }
                }
            }
        } elseif ( 'reparse_pdf_import' === $action ) {
            $pdf_preview = true;
            $pdf_preview_text = isset( $_POST['pdf_import_text'] ) && is_scalar( $_POST['pdf_import_text'] )
                ? sanitize_textarea_field( wp_unslash( (string) $_POST['pdf_import_text'] ) )
                : '';
            $pdf_source_name = isset( $_POST['pdf_source_name'] ) && is_scalar( $_POST['pdf_source_name'] )
                ? sanitize_file_name( wp_unslash( (string) $_POST['pdf_source_name'] ) )
                : '';
            $pdf_preview_test = 'auto' === $requested_pdf_test
                ? $pdf_importer->detect_test( $pdf_preview_text )
                : $requested_pdf_test;
            if ( ! in_array( $pdf_preview_test, [ 'iq', 'disc', 'papi' ], true ) ) {
                $pdf_parse_error = 'Pilih kategori soal agar isi PDF dapat ditinjau.';
                $result = [ 'success' => false, 'error' => $pdf_parse_error ];
            } else {
                $parsed = $pdf_importer->parse_questions( $pdf_preview_text, $pdf_preview_test, $pdf_preview_subtest );
                if ( ! empty( $parsed['success'] ) ) {
                    $pdf_preview_questions = array_values( $parsed['questions'] ?? [] );
                    $pdf_preview_count = count( $pdf_preview_questions );
                    $pdf_selected_indexes = array_keys( $pdf_preview_questions );
                    $pdf_preview_subtest = (string) ( $pdf_preview_questions[0]['subtest'] ?? '' );
                    $result = [ 'success' => true ];
                } else {
                    $pdf_parse_error = (string) ( $parsed['error'] ?? 'Format soal perlu diperiksa.' );
                    $result = [ 'success' => false, 'error' => $pdf_parse_error ];
                }
            }
        } else {
            $pdf_preview = true;
            $pdf_preview_text = isset( $_POST['pdf_import_text'] ) && is_scalar( $_POST['pdf_import_text'] )
                ? sanitize_textarea_field( wp_unslash( (string) $_POST['pdf_import_text'] ) )
                : '';
            $pdf_source_name = isset( $_POST['pdf_source_name'] ) && is_scalar( $_POST['pdf_source_name'] )
                ? sanitize_file_name( wp_unslash( (string) $_POST['pdf_source_name'] ) )
                : '';
            $pdf_preview_test = 'auto' === $requested_pdf_test
                ? $pdf_importer->detect_test( $pdf_preview_text )
                : $requested_pdf_test;
            $parsed = in_array( $pdf_preview_test, [ 'iq', 'disc', 'papi' ], true )
                ? $pdf_importer->parse_questions( $pdf_preview_text, $pdf_preview_test, $pdf_preview_subtest )
                : [ 'success' => false, 'error' => 'Pilih kategori soal agar isi PDF dapat ditinjau.' ];
            if ( ! empty( $parsed['success'] ) ) {
                $pdf_preview_questions = array_values( $parsed['questions'] ?? [] );
                $pdf_preview_count = count( $pdf_preview_questions );
                $pdf_preview_subtest = (string) ( $pdf_preview_questions[0]['subtest'] ?? '' );
                $posted_indexes = isset( $_POST['selected_question_indexes'] ) && is_array( $_POST['selected_question_indexes'] )
                    ? wp_unslash( $_POST['selected_question_indexes'] )
                    : [];
                foreach ( $posted_indexes as $posted_index ) {
                    if ( is_scalar( $posted_index ) && ctype_digit( (string) $posted_index ) ) {
                        $index = (int) $posted_index;
                        if ( isset( $pdf_preview_questions[ $index ] ) ) {
                            $pdf_selected_indexes[] = $index;
                        }
                    }
                }
                $pdf_selected_indexes = array_values( array_unique( $pdf_selected_indexes ) );
                if ( ! $pdf_selected_indexes ) {
                    $result = [ 'success' => false, 'error' => 'Pilih minimal satu soal untuk ditambahkan ke bank.' ];
                } else {
                    $selected_questions = array_map(
                        static fn ( int $index ): array => $pdf_preview_questions[ $index ],
                        $pdf_selected_indexes
                    );
                    $posted_answers = isset( $_POST['pdf_answers'] ) && is_array( $_POST['pdf_answers'] )
                        ? wp_unslash( $_POST['pdf_answers'] )
                        : [];
                    foreach ( $posted_answers as $answer_index => $answer_value ) {
                        if ( is_scalar( $answer_index ) && ctype_digit( (string) $answer_index ) && is_scalar( $answer_value ) ) {
                            $pdf_question_answers[ (int) $answer_index ] = sanitize_text_field( (string) $answer_value );
                        }
                    }
                    $missing_answer = false;
                    foreach ( $selected_questions as $question_index => &$selected_question ) {
                        if ( 'iq' !== $pdf_preview_test ) {
                            break;
                        }
                        $answer_text = isset( $posted_answers[ $pdf_selected_indexes[ $question_index ] ] )
                            && is_scalar( $posted_answers[ $pdf_selected_indexes[ $question_index ] ] )
                            ? strtoupper( sanitize_text_field( (string) $posted_answers[ $pdf_selected_indexes[ $question_index ] ] ) )
                            : '';
                        $answer_labels = array_values( array_filter( preg_split( '/[\s,|]+/', $answer_text ) ?: [] ) );
                        $answer_indexes = [];
                        foreach ( $answer_labels as $answer_label ) {
                            if ( 1 !== preg_match( '/^[A-F]$/', $answer_label ) ) {
                                $answer_indexes = [];
                                break;
                            }
                            $answer_index = ord( $answer_label ) - ord( 'A' );
                            if ( 0 > $answer_index || $answer_index >= count( (array) ( $selected_question['choices'] ?? [] ) ) ) {
                                $answer_indexes = [];
                                break;
                            }
                            $answer_indexes[] = $answer_index + 1;
                        }
                        if ( ! $answer_indexes || count( $answer_indexes ) > 2 || count( $answer_indexes ) !== count( array_unique( $answer_indexes ) ) ) {
                            $missing_answer = true;
                            break;
                        }
                        $selected_question['answer'] = implode( '|', $answer_indexes );
                        $selected_question['type'] = 1 === count( $answer_indexes ) ? 'multiple_choice' : 'multi_select';
                    }
                    unset( $selected_question );
                    if ( $missing_answer ) {
                        $result = [ 'success' => false, 'error' => 'Lengkapi kunci jawaban soal IQ terpilih dengan huruf opsi, misalnya A atau A|C.' ];
                        $pdf_parse_error = $result['error'];
                    } else {
                        $result = $database->create_psychotest_questions( $pdf_preview_test, $selected_questions );
                    }
                }
            } else {
                $result = [ 'success' => false, 'error' => (string) ( $parsed['error'] ?? 'Format soal PDF tidak valid.' ) ];
                $pdf_parse_error = (string) ( $parsed['error'] ?? 'Format soal PDF tidak valid.' );
            }
        }
    } elseif ( 'create_question' === $action && '' !== trim( $prompt ) ) {
        $type = isset( $posted['type'] ) && is_scalar( $posted['type'] )
            ? sanitize_key( (string) $posted['type'] )
            : '';
        $choices_text = isset( $posted['choices'] ) && is_scalar( $posted['choices'] )
            ? (string) $posted['choices']
            : '';
        $choices = preg_split( '/\r\n|\r|\n/', $choices_text ) ?: [];
        $choices = array_values( array_map( 'sanitize_text_field', array_filter( $choices, static function ( $choice ): bool {
            return is_scalar( $choice ) && '' !== trim( (string) $choice );
        } ) ) );
        $choice_images = isset( $posted['choice_images'] ) && is_array( $posted['choice_images'] )
            ? array_values( array_map( 'esc_url_raw', $posted['choice_images'] ) )
            : [];
        $subtest = isset( $posted['subtest'] ) && is_scalar( $posted['subtest'] )
            ? sanitize_key( (string) $posted['subtest'] )
            : ( 'iq' === $test ? 'verbal_analogy' : '' );
        $answer_values = isset( $posted['answer'] ) && is_array( $posted['answer'] )
            ? array_values( array_filter( array_map( 'sanitize_text_field', array_filter( $posted['answer'], 'is_scalar' ) ) ) )
            : ( isset( $posted['answer'] ) && is_scalar( $posted['answer'] )
                ? [ sanitize_text_field( (string) $posted['answer'] ) ]
                : [] );
        $answer = implode( '|', $answer_values );
        $items = preg_split( '/\r\n|\r|\n/', $prompt ) ?: [];
        $result = $database->create_psychotest_question(
            [
                'test' => $test,
                'subtest' => $subtest,
                'prompt' => $prompt,
                'image_url' => $image_url,
                'explanation' => $explanation,
                'is_active' => $is_active,
                'type' => $type,
                'choices' => $choices,
                'choice_images' => $choice_images,
                'answer' => $answer,
                'items' => array_values( array_map( 'sanitize_text_field', $items ) ),
            ]
        );
    } elseif ( 'save_question' === $action && $question_id > 0 && '' !== trim( $prompt ) ) {
        $choices_text = isset( $posted['choices'] ) && is_scalar( $posted['choices'] )
            ? (string) $posted['choices']
            : '';
        $choices = preg_split( '/\r\n|\r|\n/', $choices_text ) ?: [];
        $choices = array_values( array_map( 'sanitize_text_field', array_filter( $choices, static function ( $choice ): bool {
            return is_scalar( $choice ) && '' !== trim( (string) $choice );
        } ) ) );
        $choice_images = isset( $posted['choice_images'] ) && is_array( $posted['choice_images'] )
            ? array_values( array_map( 'esc_url_raw', $posted['choice_images'] ) )
            : [];
        $subtest = isset( $posted['subtest'] ) && is_scalar( $posted['subtest'] )
            ? sanitize_key( (string) $posted['subtest'] )
            : '';
        $type = isset( $posted['type'] ) && is_scalar( $posted['type'] )
            ? sanitize_key( (string) $posted['type'] )
            : 'multiple_choice';
        $answer_values = isset( $posted['answer'] ) && is_array( $posted['answer'] )
            ? array_values( array_filter( array_map( 'sanitize_text_field', array_filter( $posted['answer'], 'is_scalar' ) ) ) )
            : ( isset( $posted['answer'] ) && is_scalar( $posted['answer'] )
                ? [ sanitize_text_field( (string) $posted['answer'] ) ]
                : [] );
        $answer = implode( '|', $answer_values );

        if ( 'iq' === $test || in_array( $test, [ 'kognitif', 'verbal_analogy', 'reading_comprehension' ], true ) || ! empty( $choices ) ) {
            $result = $database->save_iq_question(
                [
                    'id' => $question_id,
                    'subtest' => $subtest,
                    'prompt' => $prompt,
                    'image_url' => $image_url,
                    'explanation' => $explanation,
                    'is_active' => $is_active,
                    'type' => $type,
                    'choices' => $choices,
                    'choice_images' => $choice_images,
                    'answer' => $answer,
                ]
            );
        } elseif ( in_array( $test, [ 'disc', 'papi', 'grit', 'diversity', 'humility', 'integrity' ], true ) ) {
            $items = preg_split( '/\r\n|\r|\n/', $prompt ) ?: [];
            $result = $database->save_personality_question(
                [
                    'id' => $question_id,
                    'test' => $test,
                    'items' => array_values( array_map( 'sanitize_text_field', $items ) ),
                    'explanation' => $explanation,
                    'is_active' => $is_active,
                ]
            );
        } else {
            $result = [ 'success' => false, 'error' => 'Jenis tes tidak valid.' ];
        }
    } elseif ( 'save_question' === $action || 'create_question' === $action ) {
        $result = [
            'success' => false,
            'error' => 'create_question' === $action
                ? 'Lengkapi isi soal sebelum menambahkan ke bank.'
                : 'Pilih soal yang akan diperbarui dan lengkapi isinya.',
        ];
    }

    if ( ! empty( $result['success'] ) && ! in_array( $action, [ 'preview_pdf_import', 'reparse_pdf_import' ], true ) ) {
        $redirect_args = [ 'page' => 'recruitment-question-bank', 'saved' => '1' ];
        if ( 'save_pdf_import' === $action ) {
            $redirect_args['pdf_imported'] = '1';
        }
        $filter = isset( $_GET['test'] ) && is_scalar( $_GET['test'] )
            ? sanitize_key( wp_unslash( (string) $_GET['test'] ) )
            : $test;
        if ( 'save_pdf_import' === $action ) {
            $filter = $test;
        }
        if ( isset( $test_labels[ $filter ] ) ) {
            $redirect_args['test'] = $filter;
        }
        $redirect_url = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
        if ( ! headers_sent() ) {
            wp_safe_redirect( $redirect_url );
            exit;
        }
        $success_redirect_url = $redirect_url;
        $notice = 'save_pdf_import' === $action
            ? 'Soal hasil impor berhasil disimpan pada bank Oracle.'
            : 'Soal berhasil disimpan pada bank Oracle.';
        $questions = array_values( array_filter( $database->get_psychotest_question_bank(), static fn( $q ) => 'pauli' !== ( $q['test'] ?? '' ) ) );
        if ( $database->has_error() ) {
            $error = 'Soal berhasil disimpan, tetapi daftar bank soal gagal dimuat ulang dari Oracle.';
        }
    }

    if ( empty( $result['success'] ) ) {
        $error = (string) ( $result['error'] ?? 'Soal gagal disimpan ke database.' );
    }
}

if ( '' === $notice && '1' === sanitize_text_field( wp_unslash( $_GET['saved'] ?? '' ) ) ) {
    $notice = '1' === sanitize_text_field( wp_unslash( $_GET['pdf_imported'] ?? '' ) )
        ? 'Soal hasil impor berhasil disimpan pada bank Oracle.'
        : 'Soal berhasil disimpan pada bank Oracle.';
} elseif ( '' === $notice && '1' === sanitize_text_field( wp_unslash( $_GET['deleted'] ?? '' ) ) ) {
    $notice = 'Soal berhasil dihapus dari Bank Soal.';
} elseif ( '' === $notice && '1' === sanitize_text_field( wp_unslash( $_GET['toggled'] ?? '' ) ) ) {
    $notice = 'Status keaktifan soal berhasil diperbarui.';
}

$filter_test = sanitize_key( $_GET['test'] ?? '' );
$filter_subtest = sanitize_key( $_GET['subtest'] ?? '' );
$filter_status = sanitize_key( $_GET['status'] ?? '' );
$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );

$filtered_questions = array_values( array_filter( $questions, static function ( $question ) use ( $filter_test, $filter_subtest, $filter_status, $search ) {
    if ( 'pauli' === ( $question['test'] ?? '' ) ) {
        return false;
    }
    $matches_test = '' === $filter_test || $filter_test === ( $question['test'] ?? '' );
    $matches_subtest = '' === $filter_subtest || $filter_subtest === ( $question['subtest'] ?? '' );
    $matches_status = '' === $filter_status || $filter_status === ( $question['status'] ?? '' );
    $matches_search = '' === $search || false !== stripos( (string) ( $question['prompt'] ?? '' ), $search );
    return $matches_test && $matches_subtest && $matches_status && $matches_search;
} ) );

$kognitif_count = count( array_filter( $questions, static fn( $q ) => in_array( $q['test'] ?? '', [ 'iq', 'kognitif' ], true ) ) );
$kepribadian_count = count( array_filter( $questions, static fn( $q ) => in_array( $q['test'] ?? '', [ 'disc', 'papi', 'grit', 'diversity', 'humility', 'integrity' ], true ) ) );
$disc_count = count( array_filter( $questions, static fn( $q ) => 'disc' === ( $q['test'] ?? '' ) ) );
$papi_count = count( array_filter( $questions, static fn( $q ) => in_array( $q['test'] ?? '', [ 'papi', 'papikostick' ], true ) ) );

$test_stat_cards = [
    [ 'label' => 'Total Soal Bank', 'count' => count( $questions ) ],
    [ 'label' => 'Soal Aktif', 'count' => count( array_filter( $questions, static fn( $q ) => 'active' === ( $q['status'] ?? '' ) ) ) ],
    [ 'label' => 'Test Kognitif', 'count' => $kognitif_count ],
    [ 'label' => 'Test Kepribadian', 'count' => $kepribadian_count ],
    [ 'label' => 'DISC', 'count' => $disc_count ],
    [ 'label' => 'PAPI Kostick', 'count' => $papi_count ],
];

$admin_user = wp_get_current_user();
$hr_identity = recruitment_get_current_hr_identity();
$logout_form = recruitment_logout_form( 'Keluar', home_url( '/' ) );
?>
<div class="wrap daw-question-bank<?= $pdf_preview ? ' daw-question-bank--import-preview' : '' ?>" data-question-bank data-filter-test="<?= esc_attr( isset( $test_labels[ $filter_test ] ) ? $filter_test : '' ) ?>">
    <header class="daw-question-bank__header">
        <div>
            <p class="daw-question-bank__breadcrumb"><a href="<?= esc_url( recruitment_get_admin_url( 'psychotest-settings' ) ) ?>">Setting Psikotes</a><span>/</span><a href="<?= esc_url( recruitment_get_admin_url( 'question-bank' ) ) ?>">Bank Soal</a><?php if ( $pdf_preview ) : ?><span>/</span>Preview Import<?php endif; ?></p>
            <h1><?= $pdf_preview ? 'Preview Soal Hasil Import' : 'Bank Soal Psikotes' ?></h1>
            <p><?= $pdf_preview ? 'Tinjau dan pilih soal dari PDF sebelum ditambahkan ke bank.' : 'Kelola bank soal Kognitif (IQ/TIKI) dan Kepribadian (DISC, Papikostik, GRIT, dll.) lengkap dengan gambar soal & opsi pilihan.' ?></p>
        </div>
        <span class="daw-question-bank__profile">
            <span><?= esc_html( $hr_identity['avatar'] ) ?></span>
            <span class="daw-question-bank__profile-copy"><strong><?= esc_html( $hr_identity['name'] ) ?></strong><small><?= esc_html( $hr_identity['email'] ) ?></small></span>
            <?= $logout_form ?>
        </span>
    </header>

    <?php if ( '' !== $notice ) : ?><div class="notice notice-success is-dismissible" style="padding:10px 14px;border-left:4px solid #10b981;margin:12px 0;"><p style="margin:0;font-weight:600;color:#065f46;"><?= esc_html( $notice ) ?></p></div><?php endif; ?>
    <?php if ( '' !== $error ) : ?><div class="notice notice-error" style="padding:10px 14px;border-left:4px solid #ef4444;margin:12px 0;"><p style="margin:0;font-weight:600;color:#991b1b;"><?= esc_html( $error ) ?></p></div><?php endif; ?>

    <?php if ( ! $pdf_preview ) : ?>
    <section class="daw-question-bank__stats" aria-label="Jumlah soal per tes">
        <?php foreach ( $test_stat_cards as $card ) : ?>
            <div class="daw-question-bank__stat"><strong><?= esc_html( (string) $card['count'] ) ?></strong><span><?= esc_html( $card['label'] ) ?></span></div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <section class="daw-question-bank__panel">
        <?php if ( ! $pdf_preview ) : ?>
        <div class="daw-question-bank__panel-heading">
            <div>
                <h2>Daftar Soal Psikotes</h2>
                <p><?= esc_html( (string) count( $filtered_questions ) ) ?> soal ditemukan<?php if ( isset( $test_labels[ $filter_test ] ) ) : ?> untuk kategori <?= esc_html( $test_labels[ $filter_test ] ) ?><?php endif; ?></p>
                <small style="color:#64748b;">*Tes Pauli tidak menggunakan bank soal statis karena angka penjumlahan dibuat dinamis oleh algoritma random number generator.</small>
            </div>
            <div class="daw-question-bank__panel-actions">
                <form method="get" class="daw-question-bank__filters">
                    <input type="hidden" name="page" value="recruitment-question-bank">
                    <label class="screen-reader-text" for="question-test-filter">Filter Kategori</label>
                    <select id="question-test-filter" name="test">
                        <option value="">Semua Tes</option>
                        <option value="iq" <?= selected( $filter_test, 'iq', false ) ?>>Test Kognitif (IQ/TIKI)</option>
                        <option value="disc" <?= selected( $filter_test, 'disc', false ) ?>>DISC</option>
                        <option value="papi" <?= selected( $filter_test, 'papi', false ) ?>>PAPI Kostick (Papikostik)</option>
                        <option value="grit" <?= selected( $filter_test, 'grit', false ) ?>>GRIT</option>
                        <option value="diversity" <?= selected( $filter_test, 'diversity', false ) ?>>Diversity</option>
                        <option value="humility" <?= selected( $filter_test, 'humility', false ) ?>>Humility</option>
                        <option value="integrity" <?= selected( $filter_test, 'integrity', false ) ?>>Integrity</option>
                    </select>

                    <label class="screen-reader-text" for="question-subtest-filter">Filter Sub-Kategori</label>
                    <select id="question-subtest-filter" name="subtest">
                        <option value="">Semua Sub-Kategori</option>
                        <optgroup label="Test Kognitif">
                            <option value="verbal_analogy" <?= selected( $filter_subtest, 'verbal_analogy', false ) ?>>Verbal Analogy</option>
                            <option value="reading_comprehension" <?= selected( $filter_subtest, 'reading_comprehension', false ) ?>>Reading Comprehension</option>
                            <option value="deductive_reasoning" <?= selected( $filter_subtest, 'deductive_reasoning', false ) ?>>Deductive Reasoning</option>
                            <option value="quantitative_reasoning" <?= selected( $filter_subtest, 'quantitative_reasoning', false ) ?>>Quantitative Reasoning</option>
                            <option value="spatial_ability" <?= selected( $filter_subtest, 'spatial_ability', false ) ?>>Spatial Ability</option>
                            <option value="inductive_reasoning" <?= selected( $filter_subtest, 'inductive_reasoning', false ) ?>>Inductive Reasoning</option>
                        </optgroup>
                        <optgroup label="Test Kepribadian">
                            <option value="grit" <?= selected( $filter_subtest, 'grit', false ) ?>>GRIT</option>
                            <option value="diversity" <?= selected( $filter_subtest, 'diversity', false ) ?>>Diversity</option>
                            <option value="humility" <?= selected( $filter_subtest, 'humility', false ) ?>>Humility</option>
                            <option value="disc" <?= selected( $filter_subtest, 'disc', false ) ?>>DISC</option>
                            <option value="integrity" <?= selected( $filter_subtest, 'integrity', false ) ?>>Integrity</option>
                            <option value="papikostick" <?= selected( $filter_subtest, 'papikostick', false ) ?>>Papikostik</option>
                        </optgroup>
                    </select>

                    <label class="screen-reader-text" for="question-status-filter">Filter status</label>
                    <select id="question-status-filter" name="status">
                        <option value="">Semua Status</option>
                        <option value="active" <?= selected( $filter_status, 'active', false ) ?>>Aktif</option>
                        <option value="inactive" <?= selected( $filter_status, 'inactive', false ) ?>>Nonaktif</option>
                    </select>

                    <label class="screen-reader-text" for="question-search">Cari soal</label>
                    <input id="question-search" type="search" name="s" value="<?= esc_attr( $search ) ?>" placeholder="Cari soal...">
                    <button type="submit" class="button">Filter</button>
                </form>
                <button type="button" class="button button-primary" data-new-question style="background:#c8102e;border-color:#c8102e;color:#fff;font-weight:600;">+ Tambah Soal</button>
            </div>
        </div>

        <div class="daw-question-bank__pdf-import" style="margin-bottom:20px;">
            <div class="daw-question-bank__pdf-import-copy">
                <h3>Impor soal dari PDF</h3>
                <p>Kategori soal akan terdeteksi otomatis dari isi PDF. Periksa soal sebelum menyimpan; kategori bisa disesuaikan manual.</p>
                <small>Format yang didukung: Kognitif (IQ/TIKI), DISC, dan PAPI Kostick.</small>
            </div>
            <form method="post" action="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-question-bank', 'view' => 'pdf-import' ], admin_url( 'admin.php' ) ) ) ?>" enctype="multipart/form-data" class="daw-question-bank__pdf-upload">
                <?php wp_nonce_field( 'recruitment_question_bank' ); ?>
                <input type="hidden" name="question_bank_action" value="preview_pdf_import">
                <label for="pdf-import-test">Kategori Tes</label>
                <select id="pdf-import-test" name="question[test]" data-pdf-import-test>
                    <option value="auto" <?= selected( $pdf_preview_test, 'auto', false ) ?>>Deteksi otomatis</option>
                    <option value="iq" <?= selected( $pdf_preview_test, 'iq', false ) ?>>Test Kognitif (IQ/TIKI)</option>
                    <option value="disc" <?= selected( $pdf_preview_test, 'disc', false ) ?>>DISC</option>
                    <option value="papi" <?= selected( $pdf_preview_test, 'papi', false ) ?>>PAPI Kostick (Papikostik)</option>
                </select>
                <label for="pdf-import-file">File PDF</label>
                <input id="pdf-import-file" type="file" name="source_pdf" accept="application/pdf,.pdf" required>
                <button type="submit" class="button">Ekstrak &amp; Tinjau PDF</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ( $pdf_preview ) : ?>
            <p class="daw-question-bank__preview-back"><a class="button" href="<?= esc_url( recruitment_get_admin_url( 'question-bank' ) ) ?>">&larr; Kembali ke Bank Soal</a></p>
            <form method="post" action="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-question-bank', 'view' => 'pdf-import' ], admin_url( 'admin.php' ) ) ) ?>" class="daw-question-bank__pdf-preview" data-pdf-preview>
                <?php wp_nonce_field( 'recruitment_question_bank' ); ?>
                <input type="hidden" name="question_bank_action" value="save_pdf_import">
                <label class="daw-question-bank__pdf-category">Kategori terdeteksi / koreksi
                    <select name="question[test]">
                        <option value="" <?= selected( $pdf_preview_test, '', false ) ?>>Pilih kategori</option>
                        <option value="auto" <?= selected( $pdf_preview_test, 'auto', false ) ?>>Deteksi otomatis</option>
                        <option value="iq" <?= selected( $pdf_preview_test, 'iq', false ) ?>>IQ (TIKI)</option>
                        <option value="disc" <?= selected( $pdf_preview_test, 'disc', false ) ?>>DISC</option>
                        <option value="papi" <?= selected( $pdf_preview_test, 'papi', false ) ?>>PAPI Kostick (Papikostik)</option>
                    </select>
                    <button type="submit" class="button" name="reparse_pdf_import" value="1">Perbarui pratinjau</button>
                </label>
                <input type="hidden" name="pdf_source_name" value="<?= esc_attr( $pdf_source_name ) ?>">
                <textarea name="pdf_import_text" hidden><?= esc_textarea( $pdf_preview_text ) ?></textarea>
                <div class="daw-question-bank__pdf-preview-heading">
                    <div><h3>Preview Soal Hasil Import</h3><p><?= esc_html( (string) $pdf_preview_count ) ?> soal terdeteksi dari <?= esc_html( '' !== $pdf_source_name ? $pdf_source_name : 'PDF' ) ?> — pilih soal yang ingin disimpan.</p></div>
                    <span class="daw-question-bank__pdf-draft">Draft Import</span>
                </div>
                <?php if ( '' !== $pdf_parse_error ) : ?><p class="daw-question-bank__pdf-validation daw-question-bank__pdf-validation--error"><?= esc_html( $pdf_parse_error ) ?></p><?php elseif ( $pdf_preview_count > 0 ) : ?><p class="daw-question-bank__pdf-validation"><?= esc_html( (string) $pdf_preview_count ) ?> <?= 'iq' === $pdf_preview_test ? 'soal IQ' : 'grup pernyataan' ?> terdeteksi · perkiraan kategori <?= esc_html( $test_labels[ $pdf_preview_test ] ?? $pdf_preview_test ) ?><?php if ( 'iq' === $pdf_preview_test && isset( $subtest_labels[ $pdf_preview_subtest ] ) ) : ?> · <?= esc_html( $subtest_labels[ $pdf_preview_subtest ] ) ?><?php endif; ?></p><?php endif; ?>
                <?php if ( $pdf_preview_questions ) : ?>
                    <div class="daw-question-bank__pdf-selection-bar">
                        <label><input type="checkbox" data-pdf-select-all <?= count( $pdf_selected_indexes ) === $pdf_preview_count ? 'checked' : '' ?>> Pilih semua soal</label>
                        <span><strong data-pdf-selected-count><?= esc_html( (string) count( $pdf_selected_indexes ) ) ?></strong>/<?= esc_html( (string) $pdf_preview_count ) ?> dipilih</span>
                    </div>
                    <div class="daw-question-bank__pdf-question-list">
                        <?php foreach ( $pdf_preview_questions as $question_index => $import_question ) :
                            $is_selected = in_array( $question_index, $pdf_selected_indexes, true );
                            $question_items = array_values( (array) ( $import_question['items'] ?? [] ) );
                            $question_choices = array_values( (array) ( $import_question['choices'] ?? [] ) );
                            $answer_indexes = array_values( array_filter( explode( '|', (string) ( $import_question['answer'] ?? '' ) ), static fn ( string $answer ): bool => ctype_digit( $answer ) ) );
                            $answer_labels = array_map( static fn ( string $answer ): string => chr( 64 + (int) $answer ), $answer_indexes );
                            ?>
                            <article class="daw-question-bank__pdf-question">
                                <header><label><input type="checkbox" name="selected_question_indexes[]" value="<?= esc_attr( (string) $question_index ) ?>" data-pdf-question-checkbox <?= checked( $is_selected, true, false ) ?>><strong>#<?= esc_html( (string) ( $question_index + 1 ) ) ?></strong></label><span class="daw-question-bank__tag daw-question-bank__tag--<?= esc_attr( $pdf_preview_test ) ?>"><?= esc_html( $test_labels[ $pdf_preview_test ] ?? $pdf_preview_test ) ?></span><?php if ( 'iq' === $pdf_preview_test && isset( $subtest_labels[ $pdf_preview_subtest ] ) ) : ?><small><?= esc_html( $subtest_labels[ $pdf_preview_subtest ] ) ?></small><?php endif; ?></header>
                                <?php if ( 'iq' === $pdf_preview_test ) : ?>
                                    <p class="daw-question-bank__pdf-prompt"><?= nl2br( esc_html( (string) ( $import_question['prompt'] ?? '' ) ) ) ?></p>
                                    <div class="daw-question-bank__pdf-choices"><?php foreach ( $question_choices as $choice_index => $choice ) : ?><span class="<?= in_array( (string) ( $choice_index + 1 ), $answer_indexes, true ) ? 'is-correct' : '' ?>"><?= esc_html( chr( 65 + $choice_index ) . '. ' . (string) $choice ) ?></span><?php endforeach; ?></div>
                                    <label class="daw-question-bank__pdf-answer-field">Kunci jawaban (huruf opsi)<input type="text" name="pdf_answers[<?= esc_attr( (string) $question_index ) ?>]" value="<?= esc_attr( $pdf_question_answers[ $question_index ] ?? implode( '|', $answer_labels ) ) ?>" placeholder="Contoh: A atau A|C" autocomplete="off"><?php if ( ! $answer_indexes ) : ?><small>Kunci tidak tercantum di PDF. Isi sendiri; jawaban tidak dibuat otomatis.</small><?php endif; ?></label>
                                <?php else : ?>
                                    <div class="daw-question-bank__pdf-choices"><?php foreach ( $question_items as $item_index => $item ) : ?><span><?= esc_html( chr( 65 + $item_index ) . '. ' . (string) $item ) ?></span><?php endforeach; ?></div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <button type="submit" class="button button-primary" data-pdf-apply <?= $pdf_preview_questions && $pdf_selected_indexes ? '' : 'disabled' ?>>Terapkan <span data-pdf-apply-count><?= esc_html( (string) count( $pdf_selected_indexes ) ) ?></span> soal terpilih</button>
            </form>
        <?php endif; ?>

        <?php if ( ! $pdf_preview ) : ?>
        <div class="daw-question-bank__table-wrap">
            <table class="widefat striped daw-question-bank__table">
                <thead>
                    <tr>
                        <th style="width:40px;">No</th>
                        <th>Pertanyaan / Soal</th>
                        <th>Kategori</th>
                        <th>Tipe</th>
                        <th style="width:90px;">Status</th>
                        <th style="width:130px;">Diperbarui</th>
                        <th style="width:230px;text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( ! $filtered_questions ) : ?>
                    <tr><td colspan="7" class="daw-question-bank__empty"><?= '' !== $error ? 'Data bank soal belum tersedia karena Oracle tidak dapat dimuat.' : 'Belum ada soal yang sesuai dengan filter.' ?></td></tr>
                <?php endif; ?>
                <?php foreach ( $filtered_questions as $index => $question ) :
                    $question_id = (int) ( $question['id'] ?? 0 );
                    $question_test = (string) ( $question['test'] ?? '' );
                    $question_subtest = (string) ( $question['subtest'] ?? '' );
                    $question_type = (string) ( $question['type'] ?? '' );
                    $question_image = (string) ( $question['image_url'] ?? '' );
                    $is_active = 'active' === ( $question['status'] ?? '' );
                    $choices_list = (array) ( $question['choices'] ?? [] );
                    $choices_data = (array) ( $question['choices_data'] ?? [] );
                    $choice_images = [];
                    foreach ( $choices_data as $cd ) {
                        $choice_images[] = (string) ( $cd['image_url'] ?? '' );
                    }
                    ?>
                    <tr>
                        <td><?= esc_html( (string) ( $question['number'] ?? ( $index + 1 ) ) ) ?></td>
                        <td>
                            <?php if ( '' !== $question_image ) : ?>
                                <div style="float:left;margin-right:12px;margin-bottom:6px;">
                                    <a href="<?= esc_url( $question_image ) ?>" target="_blank" title="Lihat gambar soal">
                                        <img src="<?= esc_url( $question_image ) ?>" alt="Gambar Soal" style="width:50px;height:50px;object-fit:cover;border-radius:6px;border:1px solid #cbd5e1;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                                    </a>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong><?= nl2br( esc_html( (string) ( $question['prompt'] ?? '' ) ) ) ?></strong>
                                <?php if ( '' !== $question_image ) : ?>
                                    <span style="display:inline-block;margin-left:6px;padding:2px 6px;background:#e0e7ff;color:#3730a3;border-radius:4px;font-size:10px;font-weight:600;">📷 Bergambar</span>
                                <?php endif; ?>
                                <?php if ( '' !== trim( (string) ( $question['explanation'] ?? '' ) ) ) : ?>
                                    <small class="daw-question-bank__explanation" style="display:block;color:#64748b;margin-top:4px;"><?= nl2br( esc_html( (string) $question['explanation'] ) ) ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="daw-question-bank__tag daw-question-bank__tag--<?= esc_attr( $question_test ) ?>">
                                <?= esc_html( $test_labels[ $question_test ] ?? strtoupper( $question_test ) ) ?>
                                <?php if ( isset( $subtest_labels[ $question_subtest ] ) ) : ?>
                                    <small style="display:block;margin-top:2px;font-weight:normal;opacity:0.9;"><?= esc_html( $subtest_labels[ $question_subtest ] ) ?></small>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td><span class="daw-question-bank__tag daw-question-bank__tag--type"><?= esc_html( $type_labels[ $question_type ] ?? $question_type ) ?></span></td>
                        <td>
                            <span class="daw-question-bank__status daw-question-bank__status--<?= esc_attr( $question['status'] ?? 'inactive' ) ?>">
                                <?= $is_active ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                        <td><small style="color:#64748b;"><?= esc_html( $question['updated'] ?? '—' ) ?></small></td>
                        <td class="daw-question-bank__actions" style="white-space:nowrap;text-align:center;">
                            <!-- 1. Pratinjau (Preview) -->
                            <button
                                type="button"
                                class="button button-small"
                                data-preview-question
                                data-id="<?= esc_attr( (string) $question_id ) ?>"
                                data-prompt="<?= esc_attr( (string) ( $question['prompt'] ?? '' ) ) ?>"
                                data-image-url="<?= esc_attr( $question_image ) ?>"
                                data-test="<?= esc_attr( $test_labels[ $question_test ] ?? $question_test ) ?>"
                                data-subtest="<?= esc_attr( $subtest_labels[ $question_subtest ] ?? $question_subtest ) ?>"
                                data-type="<?= esc_attr( $type_labels[ $question_type ] ?? $question_type ) ?>"
                                data-choices="<?= esc_attr( wp_json_encode( $choices_list ) ) ?>"
                                data-choice-images="<?= esc_attr( wp_json_encode( $choice_images ) ) ?>"
                                data-answer="<?= esc_attr( (string) ( $question['answer'] ?? '' ) ) ?>"
                                data-explanation="<?= esc_attr( (string) ( $question['explanation'] ?? '' ) ) ?>"
                                data-status="<?= esc_attr( $is_active ? 'Aktif' : 'Nonaktif' ) ?>"
                                title="Lihat pratinjau lengkap soal ini"
                                style="margin-right:4px;"
                            >👁️ Pratinjau</button>

                            <!-- 2. Edit -->
                            <button
                                type="button"
                                class="button button-small"
                                data-edit-question
                                data-id="<?= esc_attr( (string) $question_id ) ?>"
                                data-number="<?= esc_attr( (string) ( $question['number'] ?? '' ) ) ?>"
                                data-prompt="<?= esc_attr( (string) ( $question['prompt'] ?? '' ) ) ?>"
                                data-image-url="<?= esc_attr( $question_image ) ?>"
                                data-explanation="<?= esc_attr( (string) ( $question['explanation'] ?? '' ) ) ?>"
                                data-active="<?= esc_attr( $is_active ? '1' : '0' ) ?>"
                                data-test="<?= esc_attr( $question_test ) ?>"
                                data-subtest="<?= esc_attr( $question_subtest ) ?>"
                                data-type="<?= esc_attr( $question_type ) ?>"
                                data-choices="<?= esc_attr( implode( "\n", $choices_list ) ) ?>"
                                data-choice-images="<?= esc_attr( wp_json_encode( $choice_images ) ) ?>"
                                data-answer="<?= esc_attr( (string) ( $question['answer'] ?? '' ) ) ?>"
                                title="Edit pertanyaan, gambar, dan pilihan"
                                style="margin-right:4px;"
                            >✏️ Edit</button>

                            <!-- 3. Toggle Status -->
                            <form method="post" style="display:inline-block;margin-right:4px;">
                                <?php wp_nonce_field( 'recruitment_question_bank' ); ?>
                                <input type="hidden" name="question_bank_action" value="toggle_question_status">
                                <input type="hidden" name="question_id" value="<?= esc_attr( (string) $question_id ) ?>">
                                <button type="submit" class="button button-small" style="<?= $is_active ? 'color:#64748b;' : 'color:#059669;font-weight:600;' ?>" title="<?= $is_active ? 'Klik untuk menonaktifkan soal' : 'Klik untuk mengaktifkan soal' ?>">
                                    <?= $is_active ? '⚡ Nonaktifkan' : '⚡ Aktifkan' ?>
                                </button>
                            </form>

                            <!-- 4. Hapus -->
                            <form method="post" style="display:inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus soal ini dari Bank Soal? Tindakan ini tidak dapat dibatalkan.');">
                                <?php wp_nonce_field( 'recruitment_question_bank' ); ?>
                                <input type="hidden" name="question_bank_action" value="delete_question">
                                <input type="hidden" name="question_id" value="<?= esc_attr( (string) $question_id ) ?>">
                                <button type="submit" class="button button-small" style="color:#ef4444;" title="Hapus soal ini secara permanen">
                                    🗑️ Hapus
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <!-- Modal Pratinjau (Preview) Soal -->
    <dialog class="daw-question-bank__dialog" id="daw-question-preview-dialog" style="max-width:680px;border-radius:12px;padding:0;border:1px solid #e2e8f0;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
        <div style="padding:20px 24px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3 style="margin:0;font-size:16px;color:#0f172a;display:inline-block;">Pratinjau Soal Bank</h3>
                <span id="preview-modal-category" style="margin-left:8px;padding:2px 8px;background:#e0e7ff;color:#3730a3;border-radius:4px;font-size:11px;font-weight:600;"></span>
                <span id="preview-modal-status" style="margin-left:4px;padding:2px 8px;background:#ecfdf5;color:#065f46;border-radius:4px;font-size:11px;font-weight:600;"></span>
            </div>
            <button type="button" class="button-link" onclick="document.getElementById('daw-question-preview-dialog').close()" style="font-size:22px;color:#64748b;line-height:1;cursor:pointer;">&times;</button>
        </div>
        <div style="padding:24px;max-height:75vh;overflow-y:auto;">
            <div id="preview-modal-image-wrap" style="display:none;margin-bottom:16px;text-align:center;background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e2e8f0;">
                <img id="preview-modal-image" src="" alt="Gambar Soal" style="max-height:240px;max-width:100%;object-fit:contain;border-radius:6px;">
            </div>
            <div style="font-size:14px;color:#1e293b;line-height:1.6;margin-bottom:20px;font-weight:600;" id="preview-modal-prompt"></div>

            <div style="margin-bottom:20px;">
                <strong style="display:block;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">Pilihan Jawaban:</strong>
                <div id="preview-modal-choices" style="display:flex;flex-direction:column;gap:8px;"></div>
            </div>

            <div id="preview-modal-explanation-wrap" style="display:none;padding:12px 16px;background:#f8fafc;border-left:4px solid #3b82f6;border-radius:4px;">
                <strong style="display:block;font-size:12px;color:#1e40af;margin-bottom:4px;">Pembahasan / Catatan:</strong>
                <div id="preview-modal-explanation" style="font-size:12px;color:#334155;line-height:1.5;"></div>
            </div>
        </div>
        <div style="padding:16px 24px;border-top:1px solid #e2e8f0;background:#f8fafc;text-align:right;">
            <button type="button" class="button" onclick="document.getElementById('daw-question-preview-dialog').close()">Tutup Pratinjau</button>
        </div>
    </dialog>

    <!-- Modal Tambah & Edit Soal -->
    <dialog class="daw-question-bank__dialog" data-question-dialog aria-labelledby="question-dialog-title" style="max-width:720px;border-radius:12px;padding:0;border:1px solid #cbd5e1;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <form method="post" data-question-form style="margin:0;">
            <?php wp_nonce_field( 'recruitment_question_bank' ); ?>
            <input type="hidden" name="question_bank_action" value="save_question" data-question-action>
            <input type="hidden" name="question_id" value="" data-question-id>

            <div class="daw-question-bank__dialog-heading" style="padding:18px 24px;border-bottom:1px solid #e2e8f0;">
                <h2 id="question-dialog-title" style="margin:0;font-size:17px;color:#0f172a;">Tambah Soal Manual</h2>
                <button type="button" class="button-link" aria-label="Tutup" data-close-question-dialog style="font-size:22px;color:#64748b;">&times;</button>
            </div>

            <div style="padding:24px;max-height:75vh;overflow-y:auto;display:flex;flex-direction:column;gap:16px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <label>
                        <strong style="display:block;margin-bottom:6px;font-size:13px;color:#334155;">Kategori Tes</strong>
                        <select name="question[test]" data-question-test style="width:100%;padding:8px 12px;border-radius:6px;border:1px solid #cbd5e1;">
                            <option value="iq">Test Kognitif (IQ/TIKI)</option>
                            <option value="disc">DISC</option>
                            <option value="papi">PAPI Kostick (Papikostik)</option>
                            <option value="grit">GRIT</option>
                            <option value="diversity">Diversity</option>
                            <option value="humility">Humility</option>
                            <option value="integrity">Integrity</option>
                        </select>
                        <input type="hidden" name="question[test]" data-question-test-value>
                    </label>

                    <label data-question-subtest-wrapper>
                        <strong style="display:block;margin-bottom:6px;font-size:13px;color:#334155;">Sub-Kategori / Tipe Soal</strong>
                        <select name="question[subtest]" data-question-subtest style="width:100%;padding:8px 12px;border-radius:6px;border:1px solid #cbd5e1;">
                            <optgroup label="Test Kognitif">
                                <option value="verbal_analogy">Verbal Analogy</option>
                                <option value="reading_comprehension">Reading Comprehension</option>
                                <option value="deductive_reasoning">Deductive Reasoning</option>
                                <option value="quantitative_reasoning">Quantitative Reasoning</option>
                                <option value="spatial_ability">Spatial Ability</option>
                                <option value="inductive_reasoning">Inductive Reasoning</option>
                            </optgroup>
                            <optgroup label="Test Kepribadian">
                                <option value="grit">GRIT</option>
                                <option value="diversity">Diversity</option>
                                <option value="humility">Humility</option>
                                <option value="disc">DISC</option>
                                <option value="integrity">Integrity</option>
                                <option value="papikostick">Papikostik</option>
                            </optgroup>
                        </select>
                        <input type="hidden" name="question[subtest]" data-question-subtest-value>
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:1fr;gap:16px;">
                    <label data-question-type-label>
                        <strong style="display:block;margin-bottom:6px;font-size:13px;color:#334155;">Format Pertanyaan</strong>
                        <select data-question-type style="width:100%;padding:8px 12px;border-radius:6px;border:1px solid #cbd5e1;">
                            <option value="multiple_choice">Pilihan Ganda (Single Choice)</option>
                            <option value="multi_select">Pilihan Ganda (Multi-select)</option>
                            <option value="paired">Pernyataan Berpasangan</option>
                        </select>
                        <input type="hidden" name="question[type]" data-question-type-value>
                    </label>
                </div>

                <label data-question-prompt-label>
                    <strong style="display:block;margin-bottom:6px;font-size:13px;color:#334155;">Teks Pertanyaan / Soal <span style="color:#ef4444;">*</span></strong>
                    <textarea name="question[prompt]" rows="3" required placeholder="Tulis instruksi atau pertanyaan soal..." data-question-prompt style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #cbd5e1;"></textarea>
                    <small data-question-prompt-help style="color:#64748b;font-size:11px;"></small>
                </label>

                <!-- Input Gambar Soal (Opsional) -->
                <div style="background:#f8fafc;padding:14px;border-radius:8px;border:1px solid #e2e8f0;">
                    <label for="input-question-image" style="display:block;margin-bottom:6px;">
                        <strong style="font-size:13px;color:#334155;">📷 Gambar Soal (Opsional)</strong>
                        <span style="font-size:11px;color:#64748b;font-weight:normal;margin-left:6px;">Mendukung gambar geometri, tabel, grafik, diagram analogi dll.</span>
                    </label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="url" name="question[image_url]" id="input-question-image" placeholder="https://... URL gambar soal (atau unggah via Media WordPress)" data-question-image-url style="flex:1;padding:7px 10px;border-radius:6px;border:1px solid #cbd5e1;">
                        <button type="button" class="button daw-btn-media-upload" data-target-input="#input-question-image" data-target-preview="#preview-question-image">Pilih Gambar</button>
                    </div>
                    <div id="preview-question-image" style="margin-top:10px;display:none;">
                        <img src="" alt="Pratinjau Gambar Soal" style="max-height:120px;border-radius:6px;border:1px solid #cbd5e1;object-fit:contain;">
                    </div>
                </div>

                <textarea name="question[choices]" rows="4" hidden data-question-choices></textarea>

                <!-- Fieldset Pilihan Jawaban dengan Gambar Opsi -->
                <fieldset class="daw-question-bank__choice-fields" data-question-choices-label style="border:1px solid #e2e8f0;padding:16px;border-radius:8px;margin:0;">
                    <legend style="padding:0 8px;font-weight:600;color:#0f172a;font-size:13px;">Pilihan Jawaban &amp; Gambar Opsi (A - F)</legend>
                    <p style="margin:0 0 12px;font-size:11px;color:#64748b;">Isi teks pilihan jawaban. Anda juga dapat menambahkan gambar untuk masing-masing opsi pilihan jika diperlukan.</p>

                    <div style="display:flex;flex-direction:column;gap:12px;">
                    <?php foreach ( range( 0, 5 ) as $choice_index ) :
                        $letter = chr( 65 + $choice_index );
                        ?>
                        <div class="daw-question-bank__choice-row-card" data-choice-row="<?= esc_attr( (string) $choice_index ) ?>" style="<?= 3 < $choice_index ? 'display:none;' : '' ?>border:1px solid #e2e8f0;background:#fff;padding:10px 12px;border-radius:6px;">
                            <div style="display:flex;gap:10px;align-items:center;margin-bottom:6px;">
                                <span style="font-weight:700;font-size:14px;color:#c8102e;width:24px;"><?= esc_html( $letter ) ?>.</span>
                                <input type="text" placeholder="<?= esc_attr( 'Teks Opsi ' . $letter ) ?>" data-choice-input style="flex:1;padding:6px 10px;border-radius:4px;border:1px solid #cbd5e1;">
                            </div>
                            <div style="display:flex;gap:8px;align-items:center;padding-left:34px;">
                                <input type="url" name="question[choice_images][<?= esc_attr( (string) $choice_index ) ?>]" id="choice-img-<?= esc_attr( (string) $choice_index ) ?>" placeholder="URL gambar opsi <?= esc_attr( $letter ) ?> (opsional)" data-choice-image-input style="flex:1;padding:5px 8px;font-size:11px;border-radius:4px;border:1px solid #cbd5e1;">
                                <button type="button" class="button button-small daw-btn-media-upload" data-target-input="#choice-img-<?= esc_attr( (string) $choice_index ) ?>" data-target-preview="#preview-choice-img-<?= esc_attr( (string) $choice_index ) ?>">Pilih</button>
                            </div>
                            <div id="preview-choice-img-<?= esc_attr( (string) $choice_index ) ?>" style="display:none;margin-top:6px;padding-left:34px;">
                                <img src="" alt="Pratinjau Opsi" style="max-height:80px;border-radius:4px;border:1px solid #cbd5e1;object-fit:contain;">
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <button type="button" class="button-link daw-question-bank__add-choice" data-add-choice style="margin-top:10px;display:inline-block;font-size:12px;font-weight:600;">+ Tambah opsi pilihan</button>
                </fieldset>

                <label data-question-answer-label>
                    <strong style="display:block;margin-bottom:6px;font-size:13px;color:#334155;">Kunci Jawaban Benar <span style="color:#ef4444;">*</span></strong>
                    <select name="question[answer]" data-question-answer style="width:100%;padding:8px 12px;border-radius:6px;border:1px solid #cbd5e1;">
                        <option value="">Pilih kunci jawaban...</option>
                    </select>
                </label>

                <label>
                    <strong style="display:block;margin-bottom:6px;font-size:13px;color:#334155;">Pembahasan / Catatan (Opsional)</strong>
                    <textarea name="question[explanation]" rows="2" maxlength="4000" placeholder="Penjelasan atau kunci pembahasan soal..." data-question-explanation style="width:100%;padding:8px 12px;border-radius:6px;border:1px solid #cbd5e1;"></textarea>
                </label>

                <label class="daw-question-bank__active" style="display:flex;align-items:center;gap:8px;font-size:13px;color:#334155;cursor:pointer;">
                    <input type="checkbox" name="question[is_active]" value="1" checked data-question-active style="width:18px;height:18px;">
                    <strong>Aktifkan soal ini</strong> (soal dapat tampil pada sesi tes kandidat)
                </label>
            </div>

            <div class="daw-question-bank__dialog-actions" style="padding:16px 24px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="button" data-close-question-dialog>Batal</button>
                <button type="submit" class="button button-primary" data-question-submit style="background:#c8102e;border-color:#c8102e;color:#fff;font-weight:600;">Simpan Soal</button>
            </div>
        </form>
    </dialog>
</div>

<?php if ( '' !== $success_redirect_url ) : ?>
<script>window.location.replace(<?= wp_json_encode( $success_redirect_url ) ?>);</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Preview Modal Handler
    var previewButtons = document.querySelectorAll('[data-preview-question]');
    var previewDialog = document.getElementById('daw-question-preview-dialog');
    if (previewDialog) {
        previewButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var prompt = btn.getAttribute('data-prompt') || '';
                var imgUrl = btn.getAttribute('data-image-url') || '';
                var category = (btn.getAttribute('data-test') || '') + (btn.getAttribute('data-subtest') ? ' · ' + btn.getAttribute('data-subtest') : '');
                var status = btn.getAttribute('data-status') || 'Aktif';
                var type = btn.getAttribute('data-type') || '';
                var choices = [];
                var choiceImages = [];
                try {
                    choices = JSON.parse(btn.getAttribute('data-choices') || '[]');
                } catch (e) {
                    choices = (btn.getAttribute('data-choices') || '').split('\n');
                }
                try {
                    choiceImages = JSON.parse(btn.getAttribute('data-choice-images') || '[]');
                } catch (e) {
                    choiceImages = [];
                }
                var answer = btn.getAttribute('data-answer') || '';
                var explanation = btn.getAttribute('data-explanation') || '';

                document.getElementById('preview-modal-category').textContent = category;
                document.getElementById('preview-modal-status').textContent = status;
                document.getElementById('preview-modal-prompt').innerHTML = prompt.replace(/\n/g, '<br>');

                var imgWrap = document.getElementById('preview-modal-image-wrap');
                var imgElem = document.getElementById('preview-modal-image');
                if (imgUrl) {
                    imgElem.src = imgUrl;
                    imgWrap.style.display = 'block';
                } else {
                    imgWrap.style.display = 'none';
                    imgElem.src = '';
                }

                var choicesContainer = document.getElementById('preview-modal-choices');
                choicesContainer.innerHTML = '';
                var answerParts = answer.split('|').map(function(s) { return s.trim().toUpperCase(); });

                choices.forEach(function (choiceText, idx) {
                    var letter = String.fromCharCode(65 + idx);
                    var isCorrect = answerParts.indexOf(letter) !== -1 || answerParts.indexOf(String(idx + 1)) !== -1 || answerParts.indexOf(choiceText.trim().toUpperCase()) !== -1;
                    var cImg = choiceImages[idx] || '';

                    var card = document.createElement('div');
                    card.style.padding = '10px 14px';
                    card.style.borderRadius = '6px';
                    card.style.border = isCorrect ? '2px solid #10b981' : '1px solid #e2e8f0';
                    card.style.background = isCorrect ? '#ecfdf5' : '#ffffff';

                    var html = '<div style="display:flex;align-items:center;justify-content:space-between;">';
                    html += '<div><strong style="color:' + (isCorrect ? '#065f46' : '#1e293b') + ';margin-right:8px;">' + letter + '.</strong> <span>' + escapeHtml(choiceText) + '</span></div>';
                    if (isCorrect) {
                        html += '<span style="background:#10b981;color:#ffffff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;">✓ KUNCI JAWABAN</span>';
                    }
                    html += '</div>';

                    if (cImg) {
                        html += '<div style="margin-top:8px;"><img src="' + escapeHtml(cImg) + '" alt="Opsi" style="max-height:90px;border-radius:4px;border:1px solid #cbd5e1;"></div>';
                    }

                    card.innerHTML = html;
                    choicesContainer.appendChild(card);
                });

                var expWrap = document.getElementById('preview-modal-explanation-wrap');
                var expElem = document.getElementById('preview-modal-explanation');
                if (explanation.trim()) {
                    expElem.innerHTML = explanation.replace(/\n/g, '<br>');
                    expWrap.style.display = 'block';
                } else {
                    expWrap.style.display = 'none';
                }

                previewDialog.showModal();
            });
        });
    }

    // 2. WP Media Uploader Integration
    var mediaButtons = document.querySelectorAll('.daw-btn-media-upload');
    mediaButtons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var targetInput = document.querySelector(btn.getAttribute('data-target-input'));
            var targetPreview = document.querySelector(btn.getAttribute('data-target-preview'));

            if (typeof wp !== 'undefined' && wp.media) {
                var mediaFrame = wp.media({
                    title: 'Pilih atau Unggah Gambar Soal',
                    button: { text: 'Gunakan Gambar' },
                    multiple: false
                });
                mediaFrame.on('select', function () {
                    var attachment = mediaFrame.state().get('selection').first().toJSON();
                    if (targetInput && attachment.url) {
                        targetInput.value = attachment.url;
                        if (targetPreview) {
                            targetPreview.querySelector('img').src = attachment.url;
                            targetPreview.style.display = 'block';
                        }
                    }
                });
                mediaFrame.open();
            } else {
                var url = prompt('Masukkan URL Gambar (https://...):', targetInput ? targetInput.value : '');
                if (url && targetInput) {
                    targetInput.value = url;
                    if (targetPreview) {
                        targetPreview.querySelector('img').src = url;
                        targetPreview.style.display = 'block';
                    }
                }
            }
        });
    });

    // 3. Image URL live preview
    var qImageInput = document.querySelector('[data-question-image-url]');
    if (qImageInput) {
        qImageInput.addEventListener('input', function () {
            var prev = document.getElementById('preview-question-image');
            if (prev) {
                if (qImageInput.value.trim()) {
                    prev.querySelector('img').src = qImageInput.value.trim();
                    prev.style.display = 'block';
                } else {
                    prev.style.display = 'none';
                }
            }
        });
    }

    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>
