<?php
$database = new Recruitment_Database();
$results = $database->get_psychotest_results();
$results_error = $database->has_error();
$admin_user = wp_get_current_user();
$hr_identity = recruitment_get_current_hr_identity();
$can_view_proctoring = current_user_can( 'manage_options' );
$proctoring_report = [ 'sessions' => [], 'events' => [] ];
$proctoring_error = false;
if ( $can_view_proctoring && $results ) {
    $candidate_ids = array_map( 'absint', array_column( $results, 'candidate_id' ) );
    $proctoring_report = $database->get_proctoring_reports( $candidate_ids );
    $proctoring_error = $database->has_error();
}
$sessions_by_candidate = [];
$events_by_session = [];
foreach ( $proctoring_report['sessions'] as $session ) {
    $sessions_by_candidate[ (int) $session['candidate_id'] ][] = $session;
}
foreach ( $proctoring_report['events'] as $event ) {
    $events_by_session[ (string) $event['session_id'] ][] = $event;
}
?>
<div class="wrap daw-personality-results">
    <header class="daw-psychotest-settings__header">
        <p class="daw-psychotest-settings__breadcrumb"><a href="<?= esc_url( recruitment_get_admin_url( 'psychotest-settings' ) ) ?>">Psikotes</a> <span>/</span> Hasil Psikotes</p>
        <div class="daw-psychotest-settings__heading">
            <div>
                <h1>Hasil Psikotes</h1>
                <p>Hasil IQ, Pauli, DISC, dan PAPI Kostick beserta snapshot kamera dan status mikrofon untuk tinjauan. Tidak ada audio yang direkam.</p>
            </div>
            <div class="daw-psychotest-settings__header-account">
                <span class="daw-psychotest-settings__avatar" aria-hidden="true"><?= esc_html( $hr_identity['avatar'] ) ?></span>
                <span><strong><?= esc_html( $hr_identity['name'] ) ?></strong><small><?= esc_html( $hr_identity['email'] ) ?></small></span>
            </div>
        </div>
    </header>

    <?php if ( $results_error ) : ?>
        <div class="notice notice-error"><p>Data hasil tidak dapat dibaca dari Oracle. Periksa koneksi database dan log WordPress.</p></div>
    <?php else : ?>
        <?php if ( $proctoring_error ) : ?>
            <div class="notice notice-error"><p>Metadata bukti keamanan belum dapat dibaca. Pastikan migrasi proctoring telah ditinjau dan diterapkan oleh pemilik Oracle.</p></div>
        <?php endif; ?>
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
                    <th>Bukti Keamanan</th>
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
                        <td>
                            <?php if ( ! $can_view_proctoring ) : ?>
                                Akses gambar dibatasi untuk Administrator.
                            <?php elseif ( $proctoring_error ) : ?>
                                Data tidak tersedia.
                            <?php else :
                                $candidate_id = absint( $result['candidate_id'] ?? 0 );
                                $candidate_sessions = $sessions_by_candidate[ $candidate_id ] ?? [];
                                if ( ! $candidate_sessions ) : ?>
                                    Belum ada sesi pengawasan.
                                <?php else :
                                    foreach ( $candidate_sessions as $session ) :
                                        $identity_status = (string) ( $session['identity_status'] ?? '' );
                                        $session_events = $events_by_session[ (string) $session['session_id'] ] ?? [];
                                        ?>
                                        <details>
                                            <summary><?= 'CAPTURED' === $identity_status ? 'Identitas terekam' : ( 'MANUAL_VERIFIED' === $identity_status ? 'Identitas diverifikasi manual' : ( 'MANUAL_REVIEW' === $identity_status ? 'Perlu verifikasi manual' : 'Belum diverifikasi' ) ) ?> · <?= esc_html( (string) count( $session_events ) ) ?> peristiwa</summary>
                                            <small>Masa simpan sampai <?= esc_html( (string) ( $session['retention_until'] ?? '—' ) ) ?></small>
                                            <?php if ( 'MANUAL_REVIEW' === $identity_status ) : ?>
                                                <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
                                                    <input type="hidden" name="action" value="daw_proctoring_review">
                                                    <input type="hidden" name="candidate_id" value="<?= esc_attr( (string) $candidate_id ) ?>">
                                                    <input type="hidden" name="session_id" value="<?= esc_attr( (string) $session['session_id'] ) ?>">
                                                    <?php wp_nonce_field( 'daw_proctoring_review_' . (string) $session['session_id'] ); ?>
                                                    <button type="submit" class="button button-secondary">Tandai identitas telah diverifikasi</button>
                                                </form>
                                            <?php endif; ?>
                                            <ul>
                                                <?php foreach ( $session_events as $event ) :
                                                    $event_type = (string) ( $event['event_type'] ?? '' );
                                                    $event_labels = [
                                                        'IDENTITY_CAPTURE' => 'Foto identitas',
                                                        'MANUAL_IDENTITY' => 'Diminta verifikasi manual',
                                                        'TAB_HIDDEN' => 'Berpindah tab',
                                                        'CAMERA_STOPPED' => 'Kamera terputus',
                                                        'CAMERA_UNAVAILABLE' => 'Kamera tidak tersedia',
                                                        'MICROPHONE_AVAILABLE' => 'Mikrofon tersedia (status saja)',
                                                        'MICROPHONE_UNAVAILABLE' => 'Mikrofon tidak tersedia atau izin ditolak',
                                                        'IMAGE_ACCESS' => 'Akses gambar oleh Administrator',
                                                        'MANUAL_VERIFIED' => 'Identitas diverifikasi manual',
                                                    ];
                                                    ?>
                                                    <li>
                                                        <?= esc_html( $event_labels[ $event_type ] ?? 'Peristiwa pengawasan' ) ?>
                                                        · <?= esc_html( (string) ( $event['event_at'] ?? '' ) ) ?>
                                                        <?php if ( 'IMAGE_ACCESS' === $event_type ) : ?>
                                                            · Administrator ID <?= esc_html( (string) ( $event['actor_user_id'] ?? '' ) ) ?> mengakses bukti <?= esc_html( (string) ( $event['target_event_id'] ?? '' ) ) ?>
                                                        <?php endif; ?>
                                                        <?php if ( ! empty( $event['file_key'] ) ) :
                                                            $image_url = add_query_arg(
                                                                [
                                                                    'action' => 'daw_proctoring_image',
                                                                    'candidate_id' => $candidate_id,
                                                                    'session_id' => (string) $session['session_id'],
                                                                    'event_id' => absint( $event['event_id'] ?? 0 ),
                                                                ],
                                                                admin_url( 'admin-post.php' )
                                                            );
                                                            ?>
                                                            · <a href="<?= esc_url( wp_nonce_url( $image_url, 'daw_proctoring_image_' . $candidate_id ) ) ?>">Unduh gambar</a>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </details>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ( ! $results ) : ?>
                    <tr><td colspan="8">Belum ada jawaban atau hasil psikotes yang tersimpan di Oracle.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
