<?php
$test_labels = [ 'iq' => 'IQ', 'pauli' => 'Pauli', 'disc' => 'DISC', 'papi' => 'PAPI Kostick' ];
$subtest_labels = [
    'iq_numbers' => 'IQ - 1 Berhitung Angka',
    'iq_assembly' => 'IQ - 2 Gabungan Bagian',
    'iq_words' => 'IQ - 3 Hubungan Kata',
    'iq_abstraction' => 'IQ - 4 Abstraksi Nonverbal',
];
$type_labels = [ 'multiple_choice' => 'Pilihan Ganda', 'multi_select' => 'Pilihan Ganda (Multi-select)', 'numeric' => 'Numerik', 'paired' => 'Pernyataan' ];
$database = new Recruitment_Database();
$questions = $database->get_psychotest_question_bank();
$notice = '';
$pauli_initialized = false;
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
    $explanation = isset( $posted['explanation'] ) && is_scalar( $posted['explanation'] )
        ? sanitize_textarea_field( (string) $posted['explanation'] )
        : '';
    $is_active = isset( $posted['is_active'] ) && is_scalar( $posted['is_active'] ) && '1' === (string) $posted['is_active'];
    $result = [ 'success' => false, 'error' => 'Aksi bank soal tidak valid.' ];

    if ( 'initialize_pauli_bank' === $action ) {
        $result = $database->initialize_pauli_question_bank();
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
        $subtest = 'iq' === $test
            ? 'iq_numbers'
            : ( isset( $posted['subtest'] ) && is_scalar( $posted['subtest'] )
            ? sanitize_key( (string) $posted['subtest'] )
            : '' );
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
                'explanation' => $explanation,
                'is_active' => $is_active,
                'type' => $type,
                'choices' => $choices,
                'answer' => $answer,
                'items' => array_values( array_map( 'sanitize_text_field', $items ) ),
            ]
        );
    } elseif ( 'save_question' === $action && $question_id > 0 && '' !== trim( $prompt ) ) {
        if ( 'iq' === $test ) {
            $choices_text = isset( $posted['choices'] ) && is_scalar( $posted['choices'] )
                ? (string) $posted['choices']
                : '';
            $choices = preg_split( '/\r\n|\r|\n/', $choices_text ) ?: [];
            $choices = array_values( array_map( 'sanitize_text_field', array_filter( $choices, static function ( $choice ): bool {
                return is_scalar( $choice ) && '' !== trim( (string) $choice );
            } ) ) );
            $subtest = isset( $posted['subtest'] ) && is_scalar( $posted['subtest'] )
                ? sanitize_key( (string) $posted['subtest'] )
                : '';
            $type = isset( $posted['type'] ) && is_scalar( $posted['type'] )
                ? sanitize_key( (string) $posted['type'] )
                : '';
            $answer_values = isset( $posted['answer'] ) && is_array( $posted['answer'] )
                ? array_values( array_filter( array_map( 'sanitize_text_field', array_filter( $posted['answer'], 'is_scalar' ) ) ) )
                : ( isset( $posted['answer'] ) && is_scalar( $posted['answer'] )
                    ? [ sanitize_text_field( (string) $posted['answer'] ) ]
                    : [] );
            $answer = implode( '|', $answer_values );
            $result = $database->save_iq_question(
                [
                    'id' => $question_id,
                    'subtest' => $subtest,
                    'prompt' => $prompt,
                    'explanation' => $explanation,
                    'is_active' => $is_active,
                    'type' => $type,
                    'choices' => $choices,
                    'answer' => $answer,
                ]
            );
        } elseif ( 'pauli' === $test ) {
            $result = $database->save_pauli_question(
                [
                    'id' => $question_id,
                    'prompt' => $prompt,
                    'explanation' => $explanation,
                    'is_active' => $is_active,
                ]
            );
        } elseif ( in_array( $test, [ 'disc', 'papi' ], true ) ) {
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

    if ( ! empty( $result['success'] ) && 'initialize_pauli_bank' === $action ) {
        $pauli_initialized = true;
        $notice = 'Bank Pauli berhasil disiapkan. Angka penjumlahan dibuat otomatis untuk setiap sesi.';
        $questions = $database->get_psychotest_question_bank();
        if ( $database->has_error() ) {
            $notice = '';
            $error = 'Bank Pauli berhasil disiapkan, tetapi daftar soal belum dapat dimuat ulang dari Oracle.';
        }
    } elseif ( ! empty( $result['success'] ) && ! in_array( $action, [ 'preview_pdf_import', 'reparse_pdf_import' ], true ) ) {
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
        $questions = $database->get_psychotest_question_bank();
        if ( $database->has_error() ) {
            $error = 'Soal berhasil disimpan, tetapi daftar bank soal gagal dimuat ulang dari Oracle.';
        }
    }

    if ( empty( $result['success'] ) ) {
        $error = (string) ( $result['error'] ?? 'Soal gagal disimpan ke Oracle.' );
    }
}

if ( '1' === sanitize_text_field( wp_unslash( $_GET['pauli_initialized'] ?? '' ) ) ) {
    $notice = 'Bank Pauli berhasil disiapkan. Angka penjumlahan dibuat otomatis untuk setiap sesi.';
} elseif ( '' === $notice && '1' === sanitize_text_field( wp_unslash( $_GET['saved'] ?? '' ) ) ) {
    $notice = '1' === sanitize_text_field( wp_unslash( $_GET['pdf_imported'] ?? '' ) )
        ? 'Soal hasil impor berhasil disimpan pada bank Oracle.'
        : 'Soal berhasil disimpan pada bank Oracle.';
}
$filter_test = $pauli_initialized ? 'pauli' : sanitize_key( $_GET['test'] ?? '' );
$filter_status = sanitize_key( $_GET['status'] ?? '' );
$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
$filtered_questions = array_values( array_filter( $questions, static function ( $question ) use ( $filter_test, $filter_status, $search ) {
    $matches_test = '' === $filter_test || $filter_test === ( $question['test'] ?? '' );
    $matches_status = '' === $filter_status || $filter_status === ( $question['status'] ?? '' );
    $matches_search = '' === $search || false !== stripos( (string) ( $question['prompt'] ?? '' ), $search );
    return $matches_test && $matches_status && $matches_search;
} ) );
$test_counts = array_fill_keys( array_keys( $test_labels ), 0 );
foreach ( $questions as $question ) {
    if ( isset( $test_counts[ $question['test'] ?? '' ] ) ) {
        $test_counts[ $question['test'] ]++;
    }
}
$test_stat_cards = [
    [ 'label' => 'IQ', 'count' => $test_counts['iq'] ],
    [ 'label' => 'Pauli', 'count' => $test_counts['pauli'] ],
    [ 'label' => 'Kepribadian', 'count' => $test_counts['disc'] + $test_counts['papi'] ],
    [ 'label' => 'DISC', 'count' => $test_counts['disc'] ],
    [ 'label' => 'PAPI Kostick', 'count' => $test_counts['papi'] ],
];
$active_count = count( array_filter( $questions, static function ( $question ) {
    return 'active' === ( $question['status'] ?? 'inactive' );
} ) );
$admin_user = wp_get_current_user();
$hr_identity = recruitment_get_current_hr_identity();
$logout_form = recruitment_logout_form( 'Keluar', home_url( '/' ) );
?>
<div class="wrap daw-question-bank<?= $pdf_preview ? ' daw-question-bank--import-preview' : '' ?>" data-question-bank data-filter-test="<?= esc_attr( isset( $test_labels[ $filter_test ] ) ? $filter_test : '' ) ?>">
    <header class="daw-question-bank__header"><div><p class="daw-question-bank__breadcrumb"><a href="<?= esc_url( recruitment_get_admin_url( 'psychotest-settings' ) ) ?>">Setting Psikotes</a><span>/</span><a href="<?= esc_url( recruitment_get_admin_url( 'question-bank' ) ) ?>">Bank Soal</a><?php if ( $pdf_preview ) : ?><span>/</span>Preview Import<?php endif; ?></p><h1><?= $pdf_preview ? 'Preview Soal Hasil Import' : 'Bank Soal Psikotes' ?></h1><p><?= $pdf_preview ? 'Tinjau dan pilih soal dari PDF sebelum ditambahkan ke bank.' : 'Tambah atau perbarui soal IQ, Pauli, DISC, dan PAPI yang digunakan tes online.' ?></p></div><span class="daw-question-bank__profile"><span><?= esc_html( $hr_identity['avatar'] ) ?></span><span class="daw-question-bank__profile-copy"><strong><?= esc_html( $hr_identity['name'] ) ?></strong><small><?= esc_html( $hr_identity['email'] ) ?></small></span><?= $logout_form ?></span></header>
    <?php if ( '' !== $notice ) : ?><div class="notice notice-success is-dismissible"><p><?= esc_html( $notice ) ?></p></div><?php endif; ?>
    <?php if ( '' !== $error ) : ?><div class="notice notice-error"><p><?= esc_html( $error ) ?></p></div><?php endif; ?>
    <?php if ( ! $pdf_preview ) : ?>
    <section class="daw-question-bank__stats" aria-label="Jumlah soal per tes">
        <?php foreach ( $test_stat_cards as $card ) : ?><div class="daw-question-bank__stat"><strong><?= esc_html( (string) $card['count'] ) ?></strong><span><?= esc_html( $card['label'] ) ?></span></div><?php endforeach; ?>
    </section>
    <?php endif; ?>
    <section class="daw-question-bank__panel">
        <?php if ( ! $pdf_preview ) : ?>
        <div class="daw-question-bank__panel-heading"><div><h2>Bank Soal Psikotes</h2><p><?= esc_html( (string) count( $filtered_questions ) ) ?> soal ditampilkan<?php if ( isset( $test_labels[ $filter_test ] ) ) : ?> untuk <?= esc_html( $test_labels[ $filter_test ] ) ?><?php endif; ?></p><small>Soal terbaru ditampilkan paling atas. Urutan soal dalam tes tetap mengikuti nomor soal masing-masing.</small></div><div class="daw-question-bank__panel-actions">
            <form method="get" class="daw-question-bank__filters"><input type="hidden" name="page" value="recruitment-question-bank"><label class="screen-reader-text" for="question-test-filter">Filter tes</label><select id="question-test-filter" name="test"><option value="">Semua Tes</option><?php foreach ( $test_labels as $test_key => $test_label ) : ?><option value="<?= esc_attr( $test_key ) ?>" <?= selected( $filter_test, $test_key, false ) ?>><?= esc_html( $test_label ) ?></option><?php endforeach; ?></select><label class="screen-reader-text" for="question-status-filter">Filter status</label><select id="question-status-filter" name="status"><option value="">Semua Status</option><option value="active" <?= selected( $filter_status, 'active', false ) ?>>Aktif</option><option value="inactive" <?= selected( $filter_status, 'inactive', false ) ?>>Nonaktif</option></select><label class="screen-reader-text" for="question-search">Cari soal</label><input id="question-search" type="search" name="s" value="<?= esc_attr( $search ) ?>" placeholder="Cari soal..."><button type="submit" class="button">Filter</button></form>
            <?php if ( $test_counts['pauli'] < 20 ) : ?>
                <form method="post" class="daw-question-bank__initialize-pauli">
                    <?php wp_nonce_field( 'recruitment_question_bank' ); ?>
                    <input type="hidden" name="question_bank_action" value="initialize_pauli_bank">
                    <button type="submit" class="button button-secondary">Siapkan 20 Lajur Pauli</button>
                    <p class="description">Entri lajur bersifat teknis; angka soal dibuat acak saat sesi berjalan.</p>
                </form>
            <?php endif; ?>
            <button type="button" class="button button-primary" data-new-question>Tambah Soal</button>
        </div></div>
        <div class="daw-question-bank__pdf-import">
            <div class="daw-question-bank__pdf-import-copy"><h3>Impor soal dari PDF</h3><p>Kategori soal akan ditebak dari isi PDF. Periksa kartu soal sebelum menyimpan; kategori bisa dipilih manual bila deteksi tidak sesuai.</p><small>PDF scan/OCR belum didukung. Jangan unggah hasil tes peserta atau dokumen berisi identitas.</small></div>
            <form method="post" action="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-question-bank', 'view' => 'pdf-import' ], admin_url( 'admin.php' ) ) ) ?>" enctype="multipart/form-data" class="daw-question-bank__pdf-upload">
                <?php wp_nonce_field( 'recruitment_question_bank' ); ?>
                <input type="hidden" name="question_bank_action" value="preview_pdf_import">
                <label for="pdf-import-test">Kategori (opsional)</label>
                <select id="pdf-import-test" name="question[test]" data-pdf-import-test><option value="auto" <?= selected( $pdf_preview_test, 'auto', false ) ?>>Deteksi otomatis</option><option value="disc" <?= selected( $pdf_preview_test, 'disc', false ) ?>>DISC</option><option value="papi" <?= selected( $pdf_preview_test, 'papi', false ) ?>>PAPI Kostick</option><option value="iq" <?= selected( $pdf_preview_test, 'iq', false ) ?>>IQ</option></select>
                <label for="pdf-import-file">File PDF</label>
                <input id="pdf-import-file" type="file" name="source_pdf" accept="application/pdf,.pdf" required>
                <button type="submit" class="button">Ekstrak untuk ditinjau</button>
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
                        <option value="iq" <?= selected( $pdf_preview_test, 'iq', false ) ?>>IQ</option>
                        <option value="disc" <?= selected( $pdf_preview_test, 'disc', false ) ?>>DISC</option>
                        <option value="papi" <?= selected( $pdf_preview_test, 'papi', false ) ?>>PAPI Kostick</option>
                    </select>
                    <button type="submit" class="button" name="reparse_pdf_import" value="1">Perbarui pratinjau</button>
                </label>
                <input type="hidden" name="pdf_source_name" value="<?= esc_attr( $pdf_source_name ) ?>">
                <textarea name="pdf_import_text" hidden><?= esc_textarea( $pdf_preview_text ) ?></textarea>
                <div class="daw-question-bank__pdf-preview-heading">
                    <div><h3>Preview Soal Hasil Import</h3><p><?= esc_html( (string) $pdf_preview_count ) ?> soal terdeteksi dari <?= esc_html( '' !== $pdf_source_name ? $pdf_source_name : 'PDF' ) ?> — pilih soal yang ingin disimpan.</p></div>
                    <span class="daw-question-bank__pdf-draft">Draft Import</span>
                </div>
                <?php if ( '' !== $pdf_parse_error ) : ?><p class="daw-question-bank__pdf-validation daw-question-bank__pdf-validation--error"><?= esc_html( $pdf_parse_error ) ?></p><?php elseif ( $pdf_preview_count > 0 ) : ?><p class="daw-question-bank__pdf-validation"><?= esc_html( (string) $pdf_preview_count ) ?> <?= 'iq' === $pdf_preview_test ? 'soal IQ' : 'grup pernyataan' ?> terdeteksi · perkiraan kategori <?= esc_html( $test_labels[ $pdf_preview_test ] ?? $pdf_preview_test ) ?><?php if ( 'iq' === $pdf_preview_test ) : ?> · <?= esc_html( $subtest_labels[ $pdf_preview_subtest ] ?? $pdf_preview_subtest ) ?><?php endif; ?></p><?php endif; ?>
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
        <?php if ( ! $pdf_preview ) : ?><div class="daw-question-bank__table-wrap"><table class="widefat striped daw-question-bank__table"><thead><tr><th>No</th><th>Pertanyaan / Soal</th><th>Tes</th><th>Tipe</th><th>Status</th><th>Diperbarui</th><th>Aksi</th></tr></thead><tbody>
            <?php if ( ! $filtered_questions ) : ?><tr><td colspan="7" class="daw-question-bank__empty"><?= '' !== $error ? 'Data bank soal belum tersedia karena Oracle tidak dapat dimuat.' : 'Belum ada soal yang sesuai dengan filter.' ?></td></tr><?php endif; ?>
            <?php foreach ( $filtered_questions as $index => $question ) :
                $question_test = (string) ( $question['test'] ?? '' );
                $question_type = (string) ( $question['type'] ?? '' );
                ?>
                <tr>
                    <td><?= esc_html( (string) ( $question['number'] ?? ( $index + 1 ) ) ) ?></td>
                    <td><strong><?= nl2br( esc_html( (string) ( $question['prompt'] ?? '' ) ) ) ?></strong><?php if ( '' !== trim( (string) ( $question['explanation'] ?? '' ) ) ) : ?><small class="daw-question-bank__explanation"><?= nl2br( esc_html( (string) $question['explanation'] ) ) ?></small><?php endif; ?></td>
                    <td><span class="daw-question-bank__tag daw-question-bank__tag--<?= esc_attr( $question_test ) ?>"><?= esc_html( $test_labels[ $question_test ] ?? $question_test ) ?><?php if ( 'iq' === $question_test && isset( $subtest_labels[ $question['subtest'] ?? '' ] ) ) : ?><small><?= esc_html( $subtest_labels[ $question['subtest'] ] ) ?></small><?php endif; ?></span></td>
                    <td><span class="daw-question-bank__tag daw-question-bank__tag--type"><?= esc_html( $type_labels[ $question_type ] ?? $question_type ) ?></span></td>
                    <td><span class="daw-question-bank__status daw-question-bank__status--<?= esc_attr( $question['status'] ?? 'inactive' ) ?>"><?= 'active' === ( $question['status'] ?? '' ) ? 'Aktif' : 'Nonaktif' ?></span></td>
                    <td><?= esc_html( $question['updated'] ?? '—' ) ?></td>
                    <td class="daw-question-bank__actions">
                        <button type="button" class="button-link" data-edit-question data-id="<?= esc_attr( $question['id'] ) ?>" data-number="<?= esc_attr( (string) ( $question['number'] ?? '' ) ) ?>" data-prompt="<?= esc_attr( $question['prompt'] ) ?>" data-explanation="<?= esc_attr( $question['explanation'] ?? '' ) ?>" data-active="<?= esc_attr( (string) ( 'active' === ( $question['status'] ?? '' ) ? 1 : 0 ) ) ?>" data-test="<?= esc_attr( $question_test ) ?>" data-subtest="<?= esc_attr( $question['subtest'] ?? '' ) ?>" data-type="<?= esc_attr( $question_type ) ?>" data-choices="<?= esc_attr( implode( "\n", (array) ( $question['choices'] ?? [] ) ) ) ?>" data-answer="<?= esc_attr( $question['answer'] ?? '' ) ?>">Edit</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>
    <dialog class="daw-question-bank__dialog" data-question-dialog aria-labelledby="question-dialog-title">
        <form method="post" data-question-form>
            <?php wp_nonce_field( 'recruitment_question_bank' ); ?>
            <input type="hidden" name="question_bank_action" value="save_question" data-question-action><input type="hidden" name="question_id" value="" data-question-id>
            <div class="daw-question-bank__dialog-heading"><h2 id="question-dialog-title">Tambah Soal Manual</h2><button type="button" class="button-link" aria-label="Tutup" data-close-question-dialog>&times;</button></div>
            <input type="hidden" name="question[subtest]" data-question-subtest-value>
            <div class="daw-question-bank__dialog-grid">
                <label>Kategori Tes<select data-question-test><?php foreach ( $test_labels as $test_key => $test_label ) : ?><option value="<?= esc_attr( $test_key ) ?>"><?= esc_html( $test_label ) ?></option><?php endforeach; ?></select><input type="hidden" name="question[test]" data-question-test-value></label>
                <label data-question-type-label>Tipe Soal<select data-question-type><?php foreach ( $type_labels as $type_key => $type_label ) : ?><option value="<?= esc_attr( $type_key ) ?>"><?= esc_html( $type_label ) ?></option><?php endforeach; ?></select><input type="hidden" name="question[type]" data-question-type-value></label>
            </div>
            <label data-question-prompt-label>Teks Pertanyaan<textarea name="question[prompt]" rows="4" required placeholder="Tulis pertanyaan atau pernyataan..." data-question-prompt></textarea><small data-question-prompt-help></small></label>
            <textarea name="question[choices]" rows="4" hidden data-question-choices></textarea>
            <fieldset class="daw-question-bank__choice-fields" data-question-choices-label>
                <legend>Pilihan Jawaban</legend>
                <?php foreach ( range( 0, 5 ) as $choice_index ) : ?>
                    <label class="daw-question-bank__choice-row" data-choice-row="<?= esc_attr( (string) $choice_index ) ?>" <?= 3 < $choice_index ? 'hidden' : '' ?>>
                        <span><?= esc_html( chr( 65 + $choice_index ) ) ?></span>
                        <input type="text" placeholder="<?= esc_attr( 'Opsi ' . chr( 65 + $choice_index ) ) ?>" data-choice-input>
                    </label>
                <?php endforeach; ?>
                <button type="button" class="button-link daw-question-bank__add-choice" data-add-choice>+ Tambah opsi</button>
            </fieldset>
            <label data-question-answer-label>Kunci Jawaban<select name="question[answer]" data-question-answer><option value="">Pilih kunci...</option></select></label>
            <label>Pembahasan / Catatan<textarea name="question[explanation]" rows="3" maxlength="4000" placeholder="Opsional — penjelasan kunci jawaban..." data-question-explanation></textarea></label>
            <label class="daw-question-bank__active"><input type="checkbox" name="question[is_active]" value="1" checked data-question-active> Aktifkan soal ini</label>
            <div class="daw-question-bank__dialog-actions"><button type="button" class="button" data-close-question-dialog>Batal</button><button type="submit" class="button button-primary" data-question-submit>Simpan Soal</button></div>
        </form>
    </dialog>
</div>
<?php if ( '' !== $success_redirect_url ) : ?>
<script>window.location.replace(<?= wp_json_encode( $success_redirect_url ) ?>);</script>
<?php endif; ?>
