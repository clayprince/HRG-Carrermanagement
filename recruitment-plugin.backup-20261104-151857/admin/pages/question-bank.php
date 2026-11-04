<?php
$test_labels = [ 'iq' => 'IQ', 'pauli' => 'Pauli', 'personality' => 'Kepribadian', 'disc' => 'DISC', 'papi' => 'PAPI Kostick' ];
$type_labels = [ 'multiple_choice' => 'Pilihan Ganda', 'numeric' => 'Numerik', 'likert' => 'Likert', 'paired' => 'Berpasangan' ];
$default_questions = [
    [ 'id' => 'sample-iq-1', 'prompt' => 'Deret angka: 3, 6, 12, 24, ...', 'test' => 'iq', 'type' => 'multiple_choice', 'choices' => [ '36', '42', '48', '54' ], 'answer' => '48', 'status' => 'active', 'updated' => '2026-08-25' ],
    [ 'id' => 'sample-iq-2', 'prompt' => 'Dokter : Rumah Sakit = Guru : ...', 'test' => 'iq', 'type' => 'multiple_choice', 'choices' => [ 'Kantor', 'Sekolah', 'Pasar', 'Rumah' ], 'answer' => 'Sekolah', 'status' => 'active', 'updated' => '2026-08-25' ],
    [ 'id' => 'sample-iq-3', 'prompt' => 'Semua peserta A mengikuti tes. Rina adalah peserta A. Kesimpulan yang tepat adalah ...', 'test' => 'iq', 'type' => 'multiple_choice', 'choices' => [ 'Rina mengikuti tes', 'Rina membuat tes', 'Rina tidak mengikuti tes', 'Tidak dapat disimpulkan' ], 'answer' => 'Rina mengikuti tes', 'status' => 'active', 'updated' => '2026-08-24' ],
    [ 'id' => 'sample-pauli-1', 'prompt' => 'Hitung hasil penjumlahan pasangan angka pada lembar kerja.', 'test' => 'pauli', 'type' => 'numeric', 'choices' => [], 'answer' => '', 'status' => 'active', 'updated' => '2026-08-20' ],
    [ 'id' => 'sample-personality-1', 'prompt' => 'Saya nyaman bekerja sama dengan anggota tim yang memiliki cara kerja berbeda.', 'test' => 'personality', 'type' => 'likert', 'choices' => [ 'Sangat Tidak Setuju', 'Tidak Setuju', 'Netral', 'Setuju', 'Sangat Setuju' ], 'answer' => '', 'status' => 'active', 'updated' => '2026-08-22' ],
    [ 'id' => 'sample-personality-2', 'prompt' => 'Saya tetap berusaha menyelesaikan tugas meskipun menghadapi kendala.', 'test' => 'personality', 'type' => 'likert', 'choices' => [ 'Sangat Tidak Setuju', 'Tidak Setuju', 'Netral', 'Setuju', 'Sangat Setuju' ], 'answer' => '', 'status' => 'active', 'updated' => '2026-08-22' ],
    [ 'id' => 'sample-disc-1', 'prompt' => 'Pilih respons yang paling menggambarkan cara Anda mengambil keputusan di tempat kerja.', 'test' => 'disc', 'type' => 'paired', 'choices' => [ 'Saya segera menentukan arah tindakan', 'Saya mengajak tim menyepakati langkah' ], 'answer' => '', 'status' => 'active', 'updated' => '2026-08-21' ],
    [ 'id' => 'sample-papi-1', 'prompt' => 'Pilih pernyataan yang paling sesuai dengan kebiasaan kerja Anda.', 'test' => 'papi', 'type' => 'paired', 'choices' => [ 'Saya menyusun rencana sebelum mulai', 'Saya menyesuaikan rencana ketika keadaan berubah' ], 'answer' => '', 'status' => 'active', 'updated' => '2026-08-23' ],
];
$questions = get_option( 'recruitment_psychotest_question_bank', null );
if ( ! is_array( $questions ) ) {
    $questions = $default_questions;
}
$notice = '';

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['question_bank_action'] ) ) {
    check_admin_referer( 'recruitment_question_bank' );
    if ( ! current_user_can( 'manage_recruitment' ) ) {
        wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengelola bank soal.', 'recruitment-plugin' ) );
    }

    $action = sanitize_key( wp_unslash( $_POST['question_bank_action'] ) );
    $question_id = sanitize_text_field( wp_unslash( $_POST['question_id'] ?? '' ) );
    $question_index = null;
    foreach ( $questions as $index => $question ) {
        if ( ( $question['id'] ?? '' ) === $question_id ) {
            $question_index = $index;
            break;
        }
    }

    if ( 'save_question' === $action ) {
        $posted = isset( $_POST['question'] ) && is_array( $_POST['question'] ) ? wp_unslash( $_POST['question'] ) : [];
        $test = sanitize_key( $posted['test'] ?? '' );
        $type = sanitize_key( $posted['type'] ?? '' );
        $prompt = sanitize_textarea_field( $posted['prompt'] ?? '' );
        if ( '' !== $prompt && isset( $test_labels[ $test ], $type_labels[ $type ] ) ) {
            $choices = preg_split( '/\r\n|\r|\n/', (string) ( $posted['choices'] ?? '' ) ) ?: [];
            $choices = array_values( array_filter( array_map( 'sanitize_text_field', $choices ), static function ( $choice ) { return '' !== $choice; } ) );
            $question = [
                'id' => $question_index === null ? wp_generate_uuid4() : $question_id,
                'prompt' => $prompt,
                'test' => $test,
                'type' => $type,
                'choices' => $choices,
                'answer' => sanitize_text_field( $posted['answer'] ?? '' ),
                'status' => in_array( $posted['status'] ?? '', [ 'active', 'inactive' ], true ) ? $posted['status'] : 'active',
                'updated' => current_time( 'Y-m-d' ),
            ];
            if ( null === $question_index ) {
                $questions[] = $question;
                $notice = 'Soal berhasil ditambahkan.';
            } else {
                $questions[ $question_index ] = $question;
                $notice = 'Soal berhasil diperbarui.';
            }
        }
    } elseif ( null !== $question_index && 'duplicate' === $action ) {
        $duplicate = $questions[ $question_index ];
        $duplicate['id'] = wp_generate_uuid4();
        $duplicate['prompt'] .= ' (Salinan)';
        $duplicate['status'] = 'inactive';
        $duplicate['updated'] = current_time( 'Y-m-d' );
        array_splice( $questions, $question_index + 1, 0, [ $duplicate ] );
        $notice = 'Soal berhasil diduplikasi sebagai nonaktif.';
    } elseif ( null !== $question_index && 'toggle_status' === $action ) {
        $questions[ $question_index ]['status'] = 'active' === ( $questions[ $question_index ]['status'] ?? 'active' ) ? 'inactive' : 'active';
        $questions[ $question_index ]['updated'] = current_time( 'Y-m-d' );
        $notice = 'Status soal berhasil diperbarui.';
    }

    update_option( 'recruitment_psychotest_question_bank', array_values( $questions ) );
    $redirect_args = [ 'page' => 'recruitment-question-bank', 'saved' => '1' ];
    if ( ! empty( $_GET['test'] ) ) {
        $redirect_args['test'] = sanitize_key( wp_unslash( $_GET['test'] ) );
    }
    wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
    exit;
}

if ( '1' === sanitize_text_field( wp_unslash( $_GET['saved'] ?? '' ) ) ) {
    $notice = 'Perubahan bank soal berhasil disimpan.';
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
$active_count = count( array_filter( $questions, static function ( $question ) { return 'active' === ( $question['status'] ?? 'active' ); } ) );
$admin_user = wp_get_current_user();
$logout_url = wp_logout_url( home_url( '/' ) );
$test_filter_url = static function ( string $test ): string {
    return add_query_arg( [ 'page' => 'recruitment-question-bank', 'test' => $test ], admin_url( 'admin.php' ) );
};
?>
<div class="wrap daw-question-bank" data-question-bank>
    <header class="daw-question-bank__header"><div><p class="daw-question-bank__breadcrumb"><a href="<?= esc_url( recruitment_get_admin_url( 'psychotest-settings' ) ) ?>">Setting Psikotes</a><span>/</span>Bank Soal</p><h1>Bank Soal Psikotes</h1><p>Kelola soal untuk setiap jenis tes psikotes.</p></div><span class="daw-question-bank__profile"><span>HR</span><span class="daw-question-bank__profile-copy"><strong><?= esc_html( $admin_user->display_name ) ?></strong><small><?= esc_html( $admin_user->user_email ) ?></small></span><a href="<?= esc_url( $logout_url ) ?>">Keluar</a></span></header>
    <?php if ( $notice ) : ?><div class="notice notice-success is-dismissible"><p><?= esc_html( $notice ) ?></p></div><?php endif; ?>
    <section class="daw-question-bank__stats" aria-label="Jumlah soal per tes">
        <?php foreach ( $test_labels as $test_key => $test_label ) : ?><a href="<?= esc_url( $test_filter_url( $test_key ) ) ?>" class="daw-question-bank__stat"><strong><?= esc_html( (string) $test_counts[ $test_key ] ) ?></strong><span><?= esc_html( $test_label ) ?></span></a><?php endforeach; ?>
    </section>
    <section class="daw-question-bank__panel">
        <div class="daw-question-bank__panel-heading"><div><h2>Bank Soal Psikotes</h2><p><?= esc_html( (string) count( $filtered_questions ) ) ?> soal ditampilkan<?php if ( $filter_test && isset( $test_labels[ $filter_test ] ) ) : ?> untuk <?= esc_html( $test_labels[ $filter_test ] ) ?><?php endif; ?></p></div><div class="daw-question-bank__panel-actions">
            <form method="get" class="daw-question-bank__filters"><input type="hidden" name="page" value="recruitment-question-bank"><label class="screen-reader-text" for="question-test-filter">Filter tes</label><select id="question-test-filter" name="test"><option value="">Semua Tes</option><?php foreach ( $test_labels as $test_key => $test_label ) : ?><option value="<?= esc_attr( $test_key ) ?>" <?= selected( $filter_test, $test_key, false ) ?>><?= esc_html( $test_label ) ?></option><?php endforeach; ?></select><label class="screen-reader-text" for="question-status-filter">Filter status</label><select id="question-status-filter" name="status"><option value="">Semua Status</option><option value="active" <?= selected( $filter_status, 'active', false ) ?>>Aktif</option><option value="inactive" <?= selected( $filter_status, 'inactive', false ) ?>>Nonaktif</option></select><label class="screen-reader-text" for="question-search">Cari soal</label><input id="question-search" type="search" name="s" value="<?= esc_attr( $search ) ?>" placeholder="Cari soal..."><button type="submit" class="button">Filter</button></form>
            <button type="button" class="button button-primary" data-open-question-dialog>+ Tambah Soal</button>
        </div></div>
        <div class="daw-question-bank__table-wrap"><table class="widefat striped daw-question-bank__table"><thead><tr><th>No</th><th>Pertanyaan / Soal</th><th>Tes</th><th>Tipe</th><th>Status</th><th>Diperbarui</th><th>Aksi</th></tr></thead><tbody>
            <?php if ( ! $filtered_questions ) : ?><tr><td colspan="7" class="daw-question-bank__empty">Belum ada soal yang sesuai dengan filter.</td></tr><?php endif; ?>
            <?php foreach ( $filtered_questions as $index => $question ) :
                $question_test = $question['test'] ?? '';
                $question_type = $question['type'] ?? '';
                ?>
                <tr>
                    <td><?= esc_html( (string) ( $index + 1 ) ) ?></td>
                    <td><strong><?= esc_html( $question['prompt'] ?? '' ) ?></strong></td>
                    <td><span class="daw-question-bank__tag daw-question-bank__tag--<?= esc_attr( $question_test ) ?>"><?= esc_html( $test_labels[ $question_test ] ?? $question_test ) ?></span></td>
                    <td><span class="daw-question-bank__tag daw-question-bank__tag--type"><?= esc_html( $type_labels[ $question_type ] ?? $question_type ) ?></span></td>
                    <td><span class="daw-question-bank__status daw-question-bank__status--<?= esc_attr( $question['status'] ?? 'inactive' ) ?>"><?= 'active' === ( $question['status'] ?? '' ) ? 'Aktif' : 'Nonaktif' ?></span></td>
                    <td><?= esc_html( $question['updated'] ?? '' ) ?></td>
                    <td class="daw-question-bank__actions">
                        <button type="button" class="button-link" data-edit-question data-id="<?= esc_attr( $question['id'] ) ?>" data-prompt="<?= esc_attr( $question['prompt'] ) ?>" data-test="<?= esc_attr( $question_test ) ?>" data-type="<?= esc_attr( $question_type ) ?>" data-choices="<?= esc_attr( implode( "\n", (array) ( $question['choices'] ?? [] ) ) ) ?>" data-answer="<?= esc_attr( $question['answer'] ?? '' ) ?>" data-status="<?= esc_attr( $question['status'] ?? 'active' ) ?>">Edit</button>
                        <form method="post" class="daw-question-bank__row-action"><?php wp_nonce_field( 'recruitment_question_bank' ); ?><input type="hidden" name="question_bank_action" value="duplicate"><input type="hidden" name="question_id" value="<?= esc_attr( $question['id'] ) ?>"><button type="submit" class="button-link">Duplikat</button></form>
                        <form method="post" class="daw-question-bank__row-action"><?php wp_nonce_field( 'recruitment_question_bank' ); ?><input type="hidden" name="question_bank_action" value="toggle_status"><input type="hidden" name="question_id" value="<?= esc_attr( $question['id'] ) ?>"><button type="submit" class="button-link"><?= 'active' === ( $question['status'] ?? '' ) ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
    </section>
    <dialog class="daw-question-bank__dialog" data-question-dialog aria-labelledby="question-dialog-title">
        <form method="post" data-question-form>
            <?php wp_nonce_field( 'recruitment_question_bank' ); ?>
            <input type="hidden" name="question_bank_action" value="save_question"><input type="hidden" name="question_id" value="" data-question-id>
            <div class="daw-question-bank__dialog-heading"><h2 id="question-dialog-title">Tambah Soal</h2><button type="button" class="button-link" aria-label="Tutup" data-close-question-dialog>&times;</button></div>
            <label>Pertanyaan / Soal<textarea name="question[prompt]" rows="3" required data-question-prompt></textarea></label>
            <div class="daw-question-bank__dialog-grid"><label>Tes<select name="question[test]" data-question-test><?php foreach ( $test_labels as $test_key => $test_label ) : ?><option value="<?= esc_attr( $test_key ) ?>"><?= esc_html( $test_label ) ?></option><?php endforeach; ?></select></label><label>Tipe<select name="question[type]" data-question-type><?php foreach ( $type_labels as $type_key => $type_label ) : ?><option value="<?= esc_attr( $type_key ) ?>"><?= esc_html( $type_label ) ?></option><?php endforeach; ?></select></label><label>Status<select name="question[status]" data-question-status><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select></label></div>
            <label data-question-choices-label>Opsi jawaban<textarea name="question[choices]" rows="4" data-question-choices></textarea><small>Satu opsi untuk setiap baris.</small></label>
            <label data-question-answer-label>Jawaban benar<input name="question[answer]" data-question-answer></label>
            <div class="daw-question-bank__dialog-actions"><button type="button" class="button" data-close-question-dialog>Batal</button><button type="submit" class="button button-primary">Simpan Soal</button></div>
        </form>
    </dialog>
</div>
