<?php
$database = new Recruitment_Database();
$results = $database->get_psychotest_results();
$admin_user = wp_get_current_user();
?>
<div class="wrap daw-personality-results">
    <header class="daw-psychotest-settings__header">
        <p class="daw-psychotest-settings__breadcrumb"><a href="<?= esc_url( recruitment_get_admin_url( 'psychotest-settings' ) ) ?>">Psikotes</a> <span>/</span> Hasil Psikotes</p>
        <div class="daw-psychotest-settings__heading">
            <div>
                <h1>Hasil Psikotes</h1>
                <p>Progress jawaban dan hasil yang sudah tersimpan di Oracle untuk IQ, Pauli, DISC, dan PAPI Kostick.</p>
            </div>
            <div class="daw-psychotest-settings__header-account">
                <span class="daw-psychotest-settings__avatar" aria-hidden="true">HR</span>
                <span><strong><?= esc_html( $admin_user->display_name ) ?></strong><small><?= esc_html( $admin_user->user_email ) ?></small></span>
            </div>
        </div>
    </header>

    <?php if ( $database->has_error() ) : ?>
        <div class="notice notice-error"><p>Data hasil tidak dapat dibaca dari Oracle. Periksa koneksi database dan log WordPress.</p></div>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Kandidat</th>
                    <th>Tes</th>
                    <th>Status</th>
                    <th>Jawaban tersimpan</th>
                    <th>Nilai</th>
                    <th>Mulai</th>
                    <th>Selesai</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $results as $result ) :
                    $title = (string) ( $result['test_title'] ?? '' );
                    $test_label = strtoupper( $title );
                    $is_scored_assessment = false !== strpos( $test_label, 'IQ' ) || false !== strpos( $test_label, 'PAULI' );
                    $status = 'complete' === ( $result['status'] ?? '' ) ? 'Selesai' : 'Jawaban tersimpan — belum diselesaikan';
                    ?>
                    <tr>
                        <td>
                            <strong><?= esc_html( (string) ( $result['candidate_name'] ?? 'Kandidat' ) ) ?></strong><br>
                            <small>ID <?= esc_html( (string) ( $result['candidate_id'] ?? '' ) ) ?><?= ! empty( $result['candidate_email'] ) ? ' · ' . esc_html( (string) $result['candidate_email'] ) : '' ?></small>
                        </td>
                        <td><?= esc_html( $title ) ?></td>
                        <td><?= esc_html( $status ) ?></td>
                        <td><?= esc_html( (string) ( $result['response_count'] ?? 0 ) ) ?> / <?= esc_html( (string) ( $result['question_count'] ?? 0 ) ) ?></td>
                        <td><?= $is_scored_assessment ? esc_html( (string) ( $result['score'] ?? 0 ) ) : 'Tidak dihitung' ?></td>
                        <td><?= esc_html( (string) ( $result['started_at'] ?? '' ) ) ?></td>
                        <td><?= esc_html( (string) ( $result['completed_at'] ?? '—' ) ) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ( ! $results ) : ?>
                    <tr><td colspan="7">Belum ada jawaban atau hasil psikotes yang tersimpan di Oracle.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
