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
$error = $database->has_error()
    ? 'Bank soal belum dapat dimuat dari Oracle. Silakan coba kembali.'
    : '';
$pdf_preview = false;
$pdf_preview_test = 'disc';
$pdf_preview_subtest = 'iq_numbers';
$pdf_preview_text = '';
$pdf_preview_count = 0;
$pdf_parse_error = '';
$pdf_importer = new Recruitment_Psychotest_Pdf_Importer();

if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) && isset( $_POST['question_bank_action'] ) ) {
    check_admin_referer( 'recruitment_question_bank' );
    if ( ! current_user_can( 'manage_recruitment' ) ) {
        wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengelola bank soal.', 'recruitment-plugin' ) );
    }

    $action = sanitize_key( wp_unslash( (string) $_POST['question_bank_action'] ) );
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
    $result = [ 'success' => false, 'error' => 'Aksi bank soal tidak valid.' ];

    if ( in_array( $action, [ 'preview_pdf_import', 'save_pdf_import' ], true ) ) {
        $pdf_preview_test = $test;
        $pdf_preview_subtest = isset( $posted['subtest'] ) && is_scalar( $posted['subtest'] )
            ? sanitize_key( (string) $posted['subtest'] )
            : '';
        if ( ! in_array( $pdf_preview_test, [ 'iq', 'disc', 'papi' ], true ) ) {
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
                    $parsed = $pdf_importer->parse_questions( $pdf_preview_text, $pdf_preview_test, $pdf_preview_subtest );
                    if ( ! empty( $parsed['success'] ) ) {
                        $pdf_preview_count = count( $parsed['questions'] ?? [] );
                    } else {
                        $pdf_parse_error = (string) ( $parsed['error'] ?? 'Format grup soal perlu diperiksa.' );
                    }
                }
            }
        } else {
            $pdf_preview = true;
            $pdf_preview_text = isset( $_POST['pdf_import_text'] ) && is_scalar( $_POST['pdf_import_text'] )
                ? sanitize_textarea_field( wp_unslash( (string) $_POST['pdf_import_text'] ) )
                : '';
            $parsed = $pdf_importer->parse_questions( $pdf_preview_text, $pdf_preview_test, $pdf_preview_subtest );
            if ( ! empty( $parsed['success'] ) ) {
                $result = $database->create_psychotest_questions( $pdf_preview_test, $parsed['questions'] ?? [] );
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
        $subtest = isset( $posted['subtest'] ) && is_scalar( $posted['subtest'] )
            ? sanitize_key( (string) $posted['subtest'] )
            : '';
        $answer = isset( $posted['answer'] ) && is_scalar( $posted['answer'] )
            ? sanitize_text_field( (string) $posted['answer'] )
            : '';
        $items = preg_split( '/\r\n|\r|\n/', $prompt ) ?: [];
        $result = $database->create_psychotest_question(
            [
                'test' => $test,
                'subtest' => $subtest,
                'prompt' => $prompt,
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
            $answer = isset( $posted['answer'] ) && is_scalar( $posted['answer'] )
                ? sanitize_text_field( (string) $posted['answer'] )
                : '';
            $result = $database->save_iq_question(
                [
                    'id' => $question_id,
                    'subtest' => $subtest,
                    'prompt' => $prompt,
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
                ]
            );
        } elseif ( in_array( $test, [ 'disc', 'papi' ], true ) ) {
            $items = preg_split( '/\r\n|\r|\n/', $prompt ) ?: [];
            $result = $database->save_personality_question(
                [
                    'id' => $question_id,
                    'test' => $test,
                    'items' => array_values( array_map( 'sanitize_text_field', $items ) ),
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

    if ( ! empty( $result['success'] ) && 'preview_pdf_import' !== $action ) {
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
        wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
        exit;
    }

    if ( empty( $result['success'] ) ) {
        $error = (string) ( $result['error'] ?? 'Soal gagal disimpan ke Oracle.' );
    }
}

if ( '1' === sanitize_text_field( wp_unslash( $_GET['saved'] ?? '' ) ) ) {
    $notice = '1' === sanitize_text_field( wp_unslash( $_GET['pdf_imported'] ?? '' ) )
        ? 'Soal hasil impor berhasil disimpan pada bank Oracle.'
        : 'Soal berhasil disimpan pada bank Oracle.';
}
$filter_test = sanitize_key( $_GET['test'] ?? '' );
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
<div class="wrap daw-question-bank" data-question-bank data-filter-test="<?= esc_attr( isset( $test_labels[ $filter_test ] ) ? $filter_test : '' ) ?>">
    <header class="daw-question-bank__header"><div><p class="daw-question-bank__breadcrumb"><a href="<?= esc_url( recruitment_get_admin_url( 'psychotest-settings' ) ) ?>">Setting Psikotes</a><span>/</span>Bank Soal</p><h1>Bank Soal Psikotes</h1><p>Tambah atau perbarui soal IQ, Pauli, DISC, dan PAPI yang digunakan tes online.</p></div><span class="daw-question-bank__profile"><span><?= esc_html( $hr_identity['avatar'] ) ?></span><span class="daw-question-bank__profile-copy"><strong><?= esc_html( $hr_identity['name'] ) ?></strong><small><?= esc_html( $hr_identity['email'] ) ?></small></span><?= $logout_form ?></span></header>
    <?php if ( '' !== $notice ) : ?><div class="notice notice-success is-dismissible"><p><?= esc_html( $notice ) ?></p></div><?php endif; ?>
    <?php if ( '' !== $error ) : ?><div class="notice notice-error"><p><?= esc_html( $error ) ?></p></div><?php endif; ?>
    <section class="daw-question-bank__stats" aria-label="Jumlah soal per tes">
        <?php foreach ( $test_stat_cards as $card ) : ?><div class="daw-question-bank__stat"><strong><?= esc_html( (string) $card['count'] ) ?></strong><span><?= esc_html( $card['label'] ) ?></span></div><?php endforeach; ?>
    </section>
    <section class="daw-question-bank__panel">
        <div class="daw-question-bank__panel-heading"><div><h2>Bank Soal Psikotes</h2><p><?= esc_html( (string) count( $filtered_questions ) ) ?> soal ditampilkan<?php if ( isset( $test_labels[ $filter_test ] ) ) : ?> untuk <?= esc_html( $test_labels[ $filter_test ] ) ?><?php endif; ?></p><small>Soal baru ditambahkan setelah urutan terakhir. Sesi yang sedang berjalan tetap menggunakan soal yang sudah dibuka saat sesi dimulai.</small></div><div class="daw-question-bank__panel-actions">
            <form method="get" class="daw-question-bank__filters"><input type="hidden" name="page" value="recruitment-question-bank"><label class="screen-reader-text" for="question-test-filter">Filter tes</label><select id="question-test-filter" name="test"><option value="">Semua Tes</option><?php foreach ( $test_labels as $test_key => $test_label ) : ?><option value="<?= esc_attr( $test_key ) ?>" <?= selected( $filter_test, $test_key, false ) ?>><?= esc_html( $test_label ) ?></option><?php endforeach; ?></select><label class="screen-reader-text" for="question-status-filter">Filter status</label><select id="question-status-filter" name="status"><option value="">Semua Status</option><option value="active" <?= selected( $filter_status, 'active', false ) ?>>Aktif</option><option value="inactive" <?= selected( $filter_status, 'inactive', false ) ?>>Nonaktif</option></select><label class="screen-reader-text" for="question-search">Cari soal</label><input id="question-search" type="search" name="s" value="<?= esc_attr( $search ) ?>" placeholder="Cari soal..."><button type="submit" class="button">Filter</button></form>
            <button type="button" class="button button-primary" data-new-question>Tambah Soal</button>
        </div></div>
        <div class="daw-question-bank__pdf-import">
            <div class="daw-question-bank__pdf-import-copy"><h3>Impor soal dari PDF</h3><p>Unggah naskah soal kosong berbasis teks. Hasil ekstraksi hanya ditampilkan untuk ditinjau HR dan tidak disimpan sebelum dikonfirmasi. Impor IQ dilakukan untuk satu subtes per PDF.</p><small>PDF scan/OCR belum didukung. Jangan unggah hasil tes peserta atau dokumen berisi identitas.</small></div>
            <form method="post" enctype="multipart/form-data" class="daw-question-bank__pdf-upload">
                <?php wp_nonce_field( 'recruitment_question_bank' ); ?>
                <input type="hidden" name="question_bank_action" value="preview_pdf_import">
                <label for="pdf-import-test">Jenis tes</label>
                <select id="pdf-import-test" name="question[test]" data-pdf-import-test required><option value="disc" <?= selected( $pdf_preview_test, 'disc', false ) ?>>DISC</option><option value="papi" <?= selected( $pdf_preview_test, 'papi', false ) ?>>PAPI Kostick</option><option value="iq" <?= selected( $pdf_preview_test, 'iq', false ) ?>>IQ</option></select>
                <label for="pdf-import-subtest" data-pdf-import-subtest-field <?= 'iq' !== $pdf_preview_test ? 'hidden' : '' ?>>Subtes IQ</label>
                <select id="pdf-import-subtest" name="question[subtest]" data-pdf-import-subtest <?= 'iq' === $pdf_preview_test ? 'required' : '' ?>><option value="iq_numbers" <?= selected( $pdf_preview_subtest, 'iq_numbers', false ) ?>>IQ - 1 Berhitung Angka</option><option value="iq_assembly" <?= selected( $pdf_preview_subtest, 'iq_assembly', false ) ?>>IQ - 2 Gabungan Bagian</option><option value="iq_words" <?= selected( $pdf_preview_subtest, 'iq_words', false ) ?>>IQ - 3 Hubungan Kata</option><option value="iq_abstraction" <?= selected( $pdf_preview_subtest, 'iq_abstraction', false ) ?>>IQ - 4 Abstraksi Nonverbal</option></select>
                <label for="pdf-import-file">File PDF</label>
                <input id="pdf-import-file" type="file" name="source_pdf" accept="application/pdf,.pdf" required>
                <button type="submit" class="button">Ekstrak untuk ditinjau</button>
            </form>
        </div>
        <?php if ( $pdf_preview ) : ?>
            <form method="post" class="daw-question-bank__pdf-preview">
                <?php wp_nonce_field( 'recruitment_question_bank' ); ?>
                <input type="hidden" name="question_bank_action" value="save_pdf_import">
                <input type="hidden" name="question[test]" value="<?= esc_attr( $pdf_preview_test ) ?>">
                <input type="hidden" name="question[subtest]" value="<?= esc_attr( $pdf_preview_subtest ) ?>">
                <h3>Pratinjau hasil ekstraksi</h3>
                <?php if ( '' !== $pdf_parse_error ) : ?><p class="daw-question-bank__pdf-validation daw-question-bank__pdf-validation--error"><?= esc_html( $pdf_parse_error ) ?></p><?php elseif ( $pdf_preview_count > 0 ) : ?><p class="daw-question-bank__pdf-validation"><?= esc_html( (string) $pdf_preview_count ) ?> <?= 'iq' === $pdf_preview_test ? 'soal IQ' : 'grup pernyataan' ?> valid. Periksa dan edit isi berikut sebelum menyimpan.</p><?php endif; ?>
                <label for="pdf-import-text"><?= 'iq' === $pdf_preview_test ? 'Format tiap soal: teks pertanyaan, pilihan berlabel A. sampai F., lalu kunci jawaban (contoh: Kunci: B). Pisahkan soal dengan satu baris kosong.' : 'Satu pernyataan per baris. Tanpa baris kosong, pernyataan dikelompokkan otomatis per 4 untuk DISC atau per 2 untuk PAPI.' ?></label>
                <textarea id="pdf-import-text" name="pdf_import_text" rows="16" required><?= esc_textarea( $pdf_preview_text ) ?></textarea>
                <button type="submit" class="button button-primary">Simpan soal yang valid ke bank Oracle</button>
            </form>
        <?php endif; ?>
        <div class="daw-question-bank__table-wrap"><table class="widefat striped daw-question-bank__table"><thead><tr><th>No</th><th>Pertanyaan / Soal</th><th>Tes</th><th>Tipe</th><th>Status</th><th>Diperbarui</th><th>Aksi</th></tr></thead><tbody>
            <?php if ( ! $filtered_questions ) : ?><tr><td colspan="7" class="daw-question-bank__empty"><?= '' !== $error ? 'Data bank soal belum tersedia karena Oracle tidak dapat dimuat.' : 'Belum ada soal yang sesuai dengan filter.' ?></td></tr><?php endif; ?>
            <?php foreach ( $filtered_questions as $index => $question ) :
                $question_test = (string) ( $question['test'] ?? '' );
                $question_type = (string) ( $question['type'] ?? '' );
                ?>
                <tr>
                    <td><?= esc_html( (string) ( $question['number'] ?? ( $index + 1 ) ) ) ?></td>
                    <td><strong><?= nl2br( esc_html( (string) ( $question['prompt'] ?? '' ) ) ) ?></strong></td>
                    <td><span class="daw-question-bank__tag daw-question-bank__tag--<?= esc_attr( $question_test ) ?>"><?= esc_html( $test_labels[ $question_test ] ?? $question_test ) ?><?php if ( 'iq' === $question_test && isset( $subtest_labels[ $question['subtest'] ?? '' ] ) ) : ?><small><?= esc_html( $subtest_labels[ $question['subtest'] ] ) ?></small><?php endif; ?></span></td>
                    <td><span class="daw-question-bank__tag daw-question-bank__tag--type"><?= esc_html( $type_labels[ $question_type ] ?? $question_type ) ?></span></td>
                    <td><span class="daw-question-bank__status daw-question-bank__status--<?= esc_attr( $question['status'] ?? 'inactive' ) ?>"><?= 'active' === ( $question['status'] ?? '' ) ? 'Aktif' : 'Nonaktif' ?></span></td>
                    <td><?= esc_html( $question['updated'] ?? '—' ) ?></td>
                    <td class="daw-question-bank__actions">
                        <button type="button" class="button-link" data-edit-question data-id="<?= esc_attr( $question['id'] ) ?>" data-number="<?= esc_attr( (string) ( $question['number'] ?? '' ) ) ?>" data-prompt="<?= esc_attr( $question['prompt'] ) ?>" data-test="<?= esc_attr( $question_test ) ?>" data-subtest="<?= esc_attr( $question['subtest'] ?? '' ) ?>" data-type="<?= esc_attr( $question_type ) ?>" data-choices="<?= esc_attr( implode( "\n", (array) ( $question['choices'] ?? [] ) ) ) ?>" data-answer="<?= esc_attr( $question['answer'] ?? '' ) ?>">Edit</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
    </section>
    <dialog class="daw-question-bank__dialog" data-question-dialog aria-labelledby="question-dialog-title">
        <form method="post" data-question-form>
            <?php wp_nonce_field( 'recruitment_question_bank' ); ?>
            <input type="hidden" name="question_bank_action" value="save_question" data-question-action><input type="hidden" name="question_id" value="" data-question-id>
            <div class="daw-question-bank__dialog-heading"><h2 id="question-dialog-title">Edit Soal</h2><button type="button" class="button-link" aria-label="Tutup" data-close-question-dialog>&times;</button></div>
            <label data-question-prompt-label>Pertanyaan / Soal<textarea name="question[prompt]" rows="4" required data-question-prompt></textarea><small data-question-prompt-help></small></label>
            <div class="daw-question-bank__dialog-grid"><label>Tes<select data-question-test disabled><?php foreach ( $test_labels as $test_key => $test_label ) : ?><option value="<?= esc_attr( $test_key ) ?>"><?= esc_html( $test_label ) ?></option><?php endforeach; ?></select><input type="hidden" name="question[test]" data-question-test-value></label><label data-question-subtest-label>Subtes IQ<select data-question-subtest disabled><?php foreach ( $subtest_labels as $subtest_key => $subtest_label ) : ?><option value="<?= esc_attr( $subtest_key ) ?>"><?= esc_html( $subtest_label ) ?></option><?php endforeach; ?></select><input type="hidden" name="question[subtest]" data-question-subtest-value></label><label data-question-type-label>Tipe<select data-question-type disabled><?php foreach ( $type_labels as $type_key => $type_label ) : ?><option value="<?= esc_attr( $type_key ) ?>"><?= esc_html( $type_label ) ?></option><?php endforeach; ?></select><input type="hidden" name="question[type]" data-question-type-value></label></div>
            <label data-question-choices-label>Opsi jawaban<textarea name="question[choices]" rows="4" data-question-choices></textarea><small>Satu opsi untuk setiap baris.</small></label>
            <label data-question-answer-label>Jawaban benar<input name="question[answer]" data-question-answer><small>Gunakan nomor opsi atau teks opsi; pisahkan jawaban multi-select dengan tanda |.</small></label>
            <div class="daw-question-bank__dialog-actions"><button type="button" class="button" data-close-question-dialog>Batal</button><button type="submit" class="button button-primary" data-question-submit>Simpan Perubahan</button></div>
        </form>
    </dialog>
</div>
