<?php
$database = new Recruitment_Database();
$catalog = $database->get_personality_assessments( true );
$candidate_page = max( 1, absint( $_GET['paged'] ?? 1 ) );
$candidates_query = new WP_User_Query(
    [
        'role' => 'daw_applicant',
        'meta_key' => 'recruitment_personality_assessment',
        'number' => 25,
        'paged' => $candidate_page,
        'orderby' => 'registered',
        'order' => 'DESC',
    ]
);
$candidate_users = $candidates_query->get_results();
$total_pages = (int) $candidates_query->get_total_pages();
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
$admin_user = wp_get_current_user();
?>
<div class="wrap daw-personality-results">
    <header class="daw-psychotest-settings__header">
        <p class="daw-psychotest-settings__breadcrumb"><a href="<?= esc_url( recruitment_get_admin_url( 'psychotest-settings' ) ) ?>">Psikotes</a> <span>/</span> Hasil Kepribadian</p>
        <div class="daw-psychotest-settings__heading">
            <div>
                <h1>Hasil Tes Kepribadian</h1>
                <p>Jawaban dan hasil tes dibaca dari Oracle, lalu dipetakan ke akun pelamar menggunakan ID user WordPress. Skema Oracle tidak diubah.</p>
            </div>
            <div class="daw-psychotest-settings__header-account">
                <span class="daw-psychotest-settings__avatar" aria-hidden="true">HR</span>
                <span><strong><?= esc_html( $admin_user->display_name ) ?></strong><small><?= esc_html( $admin_user->user_email ) ?></small></span>
            </div>
        </div>
    </header>

    <?php if ( $database->has_error() ) : ?>
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
                <th rowspan="2">ID akun WordPress</th>
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
            foreach ( $candidate_users as $candidate_user ) :
                $candidate_id = absint( $candidate_user->ID );
                $state = get_user_meta( $candidate_user->ID, 'recruitment_personality_assessment', true );
                if ( ! $candidate_id || ! is_array( $state ) || (int) ( $state['candidate_id'] ?? 0 ) !== $candidate_id ) {
                    continue;
                }

                $test_rows = [];
                $total_saved_responses = 0;
                foreach ( $test_labels as $test_key => $test_label ) {
                    $test_state = is_array( $state['tests'][ $test_key ] ?? null ) ? $state['tests'][ $test_key ] : [];
                    $status = (string) ( $test_state['status'] ?? 'belum_dimulai' );
                    $response_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $test_state['response_ids'] ?? [] ) ) ) ) );
                    $record = [ 'success' => true, 'responses' => [], 'result' => [] ];
                    if ( in_array( $status, [ 'active', 'complete' ], true ) && isset( $catalog[ $test_key ] ) && ( $response_ids || ! empty( $test_state['result_id'] ) ) ) {
                        $record = $database->get_personality_assessment_record(
                            (int) $catalog[ $test_key ]['ID'],
                            absint( $test_state['result_id'] ?? 0 ),
                            $response_ids
                        );
                    }
                    $saved_count = ! empty( $record['success'] ) ? count( $record['responses'] ?? [] ) : 0;
                    $total_saved_responses += $saved_count;
                    $test_rows[ $test_key ] = [
                        'label' => $test_label,
                        'status' => $status,
                        'saved_count' => $saved_count,
                        'expected_count' => absint( $catalog[ $test_key ]['QUESTION_COUNT'] ?? 0 ),
                        'result_id' => absint( $test_state['result_id'] ?? 0 ),
                        'record' => $record,
                    ];
                }
                $displayed_candidates++;
                ?>
                <tr>
                    <td><strong><?= esc_html( $candidate_user->display_name ) ?></strong><br><a href="mailto:<?= esc_attr( $candidate_user->user_email ) ?>"><?= esc_html( $candidate_user->user_email ) ?></a></td>
                    <td><?= esc_html( (string) $candidate_id ) ?></td>
                    <?php foreach ( $test_labels as $test_key => $test_label ) :
                        $test_row = $test_rows[ $test_key ];
                        $status_labels = [ 'complete' => 'Selesai', 'active' => 'Sedang dikerjakan', 'available' => 'Tersedia', 'locked' => 'Menunggu', 'belum_dimulai' => 'Belum dimulai' ];
                        ?>
                        <td><?= esc_html( $status_labels[ $test_row['status'] ] ?? $test_row['status'] ) ?><br><small><?= esc_html( (string) $test_row['saved_count'] ) ?> / <?= esc_html( (string) $test_row['expected_count'] ) ?> jawaban dari Oracle</small><?php if ( $test_row['result_id'] ) : ?><br><small>ID hasil: <?= esc_html( (string) $test_row['result_id'] ) ?></small><?php endif; ?></td>
                    <?php endforeach; ?>
                    <td><?= esc_html( (string) $total_saved_responses ) ?></td>
                    <td>
                        <details>
                            <summary>Lihat hasil dan jawaban</summary>
                            <?php foreach ( $test_rows as $test_row ) : ?>
                                <section style="margin-top:16px">
                                    <h3><?= esc_html( $test_row['label'] ) ?> — <?= esc_html( $status_labels[ $test_row['status'] ] ?? $test_row['status'] ) ?></h3>
                                    <?php if ( ! empty( $test_row['record']['error'] ) ) : ?>
                                        <p class="notice notice-error inline"><?= esc_html( $test_row['record']['error'] ) ?></p>
                                    <?php elseif ( ! $test_row['saved_count'] ) : ?>
                                        <p>Belum ada jawaban yang tersimpan di Oracle.</p>
                                    <?php else : ?>
                                        <?php if ( $test_row['result_id'] && ! empty( $test_row['record']['result'] ) ) : ?>
                                            <p>Hasil Oracle #<?= esc_html( (string) $test_row['result_id'] ) ?> · <?= esc_html( (string) $test_row['record']['result']['MULAI_PADA'] ) ?> – <?= esc_html( (string) $test_row['record']['result']['DIKUMPULKAN_PADA'] ) ?> · Skor tidak dihitung.</p>
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
                <tr><td colspan="6">Belum ada akun kandidat dengan sesi tes kepribadian.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $total_pages > 1 ) : ?>
        <div class="tablenav"><div class="tablenav-pages">
            <span class="displaying-num"><?= esc_html( (string) $candidates_query->get_total() ) ?> kandidat</span>
            <?php if ( $candidate_page > 1 ) : ?><a class="button" href="<?= esc_url( $page_url( $candidate_page - 1 ) ) ?>">Sebelumnya</a><?php endif; ?>
            <span><?= esc_html( (string) $candidate_page ) ?> / <?= esc_html( (string) $total_pages ) ?></span>
            <?php if ( $candidate_page < $total_pages ) : ?><a class="button" href="<?= esc_url( $page_url( $candidate_page + 1 ) ) ?>">Berikutnya</a><?php endif; ?>
        </div></div>
    <?php endif; ?>
</div>
