<?php
$database = new Recruitment_Database();
$catalog = $database->get_personality_assessments( true );
$catalog_error = $database->has_error();
$psychotest_results = $database->get_psychotest_results();
$results_error = $database->has_error();
$test_ids = [];
foreach ( [ 'disc', 'papi' ] as $test_key ) {
    if ( isset( $catalog[ $test_key ]['ID'] ) ) {
        $test_ids[ (int) $catalog[ $test_key ]['ID'] ] = $test_key;
    }
}
$candidates = [];
$record_error = false;
if ( ! $catalog_error && ! $results_error ) {
    foreach ( $psychotest_results as $result ) {
        $test_key = $test_ids[ absint( $result['test_id'] ?? 0 ) ] ?? '';
        $candidate_id = absint( $result['candidate_id'] ?? 0 );
        if ( '' === $test_key || 0 === $candidate_id ) {
            continue;
        }

        if ( ! isset( $candidates[ $candidate_id ] ) ) {
            $candidates[ $candidate_id ] = [
                'candidate_id' => $candidate_id,
                'candidate_name' => (string) ( $result['candidate_name'] ?? 'Kandidat' ),
                'candidate_email' => (string) ( $result['candidate_email'] ?? '' ),
                'tests' => [],
            ];
        }
        if ( isset( $candidates[ $candidate_id ]['tests'][ $test_key ] ) ) {
            continue;
        }

        $status = (string) ( $result['status'] ?? 'active' );
        $result_id = absint( $result['result_id'] ?? 0 );
        $record = [ 'success' => true, 'responses' => [], 'result' => [] ];
        if ( 'complete' === $status && $result_id > 0 ) {
            $record = $database->get_personality_assessment_record(
                (int) $catalog[ $test_key ]['ID'],
                $result_id,
                []
            );
            if ( empty( $record['success'] ) ) {
                $record_error = true;
            } else {
                $questions_by_id = [];
                foreach ( (array) ( $catalog[ $test_key ]['questions'] ?? [] ) as $index => $question ) {
                    $questions_by_id[ absint( $question['id'] ?? 0 ) ] = [
                        'number' => $index + 1,
                        'items' => array_values( (array) ( $question['items'] ?? [] ) ),
                    ];
                }

                $formatted_responses = [];
                foreach ( (array) ( $record['responses'] ?? [] ) as $response ) {
                    $question = $questions_by_id[ absint( $response['question_id'] ?? 0 ) ] ?? null;
                    $answer = json_decode( (string) ( $response['answer'] ?? '' ), true );
                    if ( ! is_array( $question ) || ! is_array( $answer ) ) {
                        $record['error'] = 'Data jawaban tidak cocok dengan bank soal yang tersedia.';
                        $record_error = true;
                        break;
                    }

                    $selected = [];
                    $answer_parts = 'disc' === $test_key ? [ 'most', 'least' ] : [ 'choice' ];
                    foreach ( $answer_parts as $part ) {
                        $item_index = $answer[ $part ] ?? null;
                        if ( ! is_int( $item_index ) || ! isset( $question['items'][ $item_index ] ) ) {
                            $record['error'] = 'Format jawaban tersimpan tidak valid.';
                            $record_error = true;
                            break 2;
                        }
                        $selected[ $part ] = $question['items'][ $item_index ];
                    }
                    if ( 'disc' === $test_key && $answer['most'] === $answer['least'] ) {
                        $record['error'] = 'Format jawaban DISC tersimpan tidak valid.';
                        $record_error = true;
                        break;
                    }
                    $formatted_responses[] = [
                        'number' => $question['number'],
                        'items' => $question['items'],
                        'selected' => $selected,
                    ];
                }
                if ( empty( $record['error'] ) ) {
                    $record['responses'] = $formatted_responses;
                }
            }
        }

        $candidates[ $candidate_id ]['tests'][ $test_key ] = [
            'status' => $status,
            'saved_count' => absint( $result['response_count'] ?? 0 ),
            'expected_count' => absint( $result['question_count'] ?? $catalog[ $test_key ]['QUESTION_COUNT'] ?? 0 ),
            'result_id' => $result_id,
            'record' => $record,
        ];
    }
}
$candidate_ids = array_keys( $candidates );
$candidate_page = max( 1, absint( $_GET['paged'] ?? 1 ) );
$candidates_per_page = 25;
$total_pages = (int) ceil( count( $candidate_ids ) / $candidates_per_page );
$visible_candidate_ids = array_slice( $candidate_ids, ( $candidate_page - 1 ) * $candidates_per_page, $candidates_per_page );
$page_url = static function ( int $page ): string {
    return add_query_arg(
        [
            'page' => 'recruitment-personality-results',
            'paged' => $page,
        ],
        admin_url( 'admin.php' )
    );
};
$test_labels = [ 'disc' => 'DISC', 'papi' => 'PAPI Kostick' ];
$status_labels = [ 'complete' => 'Selesai', 'active' => 'Sedang dikerjakan' ];
$admin_user = wp_get_current_user();
$hr_identity = recruitment_get_current_hr_identity();
?>
<div class="wrap daw-personality-results">
    <header class="daw-psychotest-settings__header">
        <p class="daw-psychotest-settings__breadcrumb"><a href="<?= esc_url( recruitment_get_admin_url( 'psychotest-settings' ) ) ?>">Psikotes</a> <span>/</span> Hasil Kepribadian</p>
        <div class="daw-psychotest-settings__heading">
            <div>
                <h1>Hasil Tes Kepribadian</h1>
                <p>Progres dan jawaban kandidat dibaca langsung dari catatan hasil psikotes di Oracle.</p>
            </div>
            <div class="daw-psychotest-settings__header-account">
                <span class="daw-psychotest-settings__avatar" aria-hidden="true"><?= esc_html( $hr_identity['avatar'] ) ?></span>
                <span><strong><?= esc_html( $hr_identity['name'] ) ?></strong><small><?= esc_html( $hr_identity['email'] ) ?></small></span>
            </div>
        </div>
    </header>

    <?php if ( $catalog_error || $results_error || $record_error ) : ?>
        <div class="notice notice-error"><p>Data hasil tidak dapat dibaca dari Oracle. Periksa koneksi database dan log WordPress.</p></div>
    <?php endif; ?>

    <?php if ( ! isset( $catalog['disc'], $catalog['papi'] ) ) : ?>
        <div class="notice notice-warning"><p>Bank DISC/PAPI Kostick belum tersedia. Hasil kandidat belum dapat ditampilkan.</p></div>
    <?php endif; ?>

    <p class="description">Skor DISC/PAPI tidak ditampilkan karena kunci skoring resmi tidak tersedia. Angka 0 yang diwajibkan kolom Oracle bukan skor kandidat.</p>
    <table class="widefat striped">
        <thead>
            <tr>
                <th rowspan="2">Kandidat</th>
                <th rowspan="2">ID kandidat Oracle</th>
                <th colspan="2">Tahap Kepribadian</th>
                <th rowspan="2">Jawaban tersimpan</th>
                <th rowspan="2">Data jawaban</th>
            </tr>
            <tr>
                <th>DISC</th>
                <th>PAPI Kostick</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $displayed_candidates = 0;
            foreach ( $visible_candidate_ids as $candidate_id ) :
                $candidate = $candidates[ $candidate_id ];
                $test_rows = [];
                $total_saved_responses = 0;
                foreach ( $test_labels as $test_key => $test_label ) {
                    $test_row = $candidate['tests'][ $test_key ] ?? [
                        'status' => 'not_started',
                        'saved_count' => 0,
                        'expected_count' => absint( $catalog[ $test_key ]['QUESTION_COUNT'] ?? 0 ),
                        'result_id' => 0,
                        'record' => [ 'success' => true, 'responses' => [], 'result' => [] ],
                    ];
                    $total_saved_responses += $test_row['saved_count'];
                    $test_rows[ $test_key ] = array_merge( [ 'label' => $test_label ], $test_row );
                }
                $displayed_candidates++;
                ?>
                <tr>
                    <td><strong><?= esc_html( $candidate['candidate_name'] ) ?></strong><?php if ( '' !== $candidate['candidate_email'] ) : ?><br><a href="mailto:<?= esc_attr( $candidate['candidate_email'] ) ?>"><?= esc_html( $candidate['candidate_email'] ) ?></a><?php endif; ?></td>
                    <td><?= esc_html( (string) $candidate_id ) ?></td>
                    <?php foreach ( $test_labels as $test_key => $test_label ) :
                        $test_row = $test_rows[ $test_key ];
                        $status_label = $status_labels[ $test_row['status'] ] ?? 'Belum dimulai';
                        ?>
                        <td><?= esc_html( $status_label ) ?><br><small><?= esc_html( (string) $test_row['saved_count'] ) ?> / <?= esc_html( (string) $test_row['expected_count'] ) ?> jawaban dari Oracle</small><?php if ( $test_row['result_id'] ) : ?><br><small>ID hasil: <?= esc_html( (string) $test_row['result_id'] ) ?></small><?php endif; ?></td>
                    <?php endforeach; ?>
                    <td><?= esc_html( (string) $total_saved_responses ) ?></td>
                    <td>
                        <details>
                            <summary>Lihat hasil dan jawaban</summary>
                            <?php foreach ( $test_rows as $test_row ) : ?>
                                <section style="margin-top:16px">
                                    <h3><?= esc_html( $test_row['label'] ) ?> — <?= esc_html( $status_labels[ $test_row['status'] ] ?? 'Belum dimulai' ) ?></h3>
                                    <?php if ( ! empty( $test_row['record']['error'] ) ) : ?>
                                        <p class="notice notice-error inline"><?= esc_html( $test_row['record']['error'] ) ?></p>
                                    <?php elseif ( 'not_started' === $test_row['status'] ) : ?>
                                        <p>Belum ada jawaban yang tersimpan di Oracle.</p>
                                    <?php elseif ( 'active' === $test_row['status'] ) : ?>
                                        <p>Tes belum selesai. Rincian jawaban akan tersedia setelah kandidat menyelesaikan tes.</p>
                                    <?php elseif ( empty( $test_row['record']['responses'] ) ) : ?>
                                        <p>Tes tercatat selesai, tetapi rincian jawaban tidak ditemukan di Oracle.</p>
                                    <?php else : ?>
                                        <?php if ( $test_row['result_id'] && ! empty( $test_row['record']['result'] ) ) : ?>
                                            <p>Hasil Oracle #<?= esc_html( (string) $test_row['result_id'] ) ?> · <?= esc_html( (string) ( $test_row['record']['result']['started_at'] ?? '' ) ) ?> – <?= esc_html( (string) ( $test_row['record']['result']['completed_at'] ?? '' ) ) ?> · Skor tidak dihitung.</p>
                                        <?php endif; ?>
                                        <table class="widefat striped">
                                            <thead><tr><th>Nomor</th><th>Pernyataan</th><th>Jawaban kandidat</th></tr></thead>
                                            <tbody>
                                                <?php foreach ( $test_row['record']['responses'] as $response ) :
                                                    $answer_labels = [];
                                                    foreach ( $response['selected'] as $part => $selected_text ) {
                                                        $answer_labels[] = ( 'most' === $part ? 'Paling menggambarkan: ' : ( 'least' === $part ? 'Paling tidak menggambarkan: ' : '' ) ) . $selected_text;
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><?= esc_html( (string) $response['number'] ) ?></td>
                                                        <td><?= nl2br( esc_html( implode( "\n", $response['items'] ) ) ) ?></td>
                                                        <td><?= esc_html( implode( "\n", $answer_labels ) ) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </section>
                            <?php endforeach; ?>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ( 0 === $displayed_candidates ) : ?>
                <tr><td colspan="6">Belum ada jawaban tes kepribadian yang tersimpan di Oracle.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $total_pages > 1 ) : ?>
        <div class="tablenav"><div class="tablenav-pages">
            <span class="displaying-num"><?= esc_html( (string) count( $candidate_ids ) ) ?> kandidat</span>
            <?php if ( $candidate_page > 1 ) : ?><a class="button" href="<?= esc_url( $page_url( $candidate_page - 1 ) ) ?>">Sebelumnya</a><?php endif; ?>
            <span><?= esc_html( (string) $candidate_page ) ?> / <?= esc_html( (string) $total_pages ) ?></span>
            <?php if ( $candidate_page < $total_pages ) : ?><a class="button" href="<?= esc_url( $page_url( $candidate_page + 1 ) ) ?>">Berikutnya</a><?php endif; ?>
        </div></div>
    <?php endif; ?>
</div>
