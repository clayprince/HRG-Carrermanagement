<?php
$database = new Recruitment_Database();
$results = $database->get_psychotest_results();
$results_error = $database->has_error();
$admin_user = wp_get_current_user();
$hr_identity = recruitment_get_current_hr_identity();
$can_view_proctoring = current_user_can( 'manage_recruitment' ) || current_user_can( 'manage_options' );
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
$completed_count = count(
    array_filter(
        $results,
        static fn ( array $result ): bool => 'complete' === ( $result['status'] ?? '' )
    )
);
$in_progress_count = count( $results ) - $completed_count;
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
        <?php if ( isset( $_GET['proctoring_review'] ) && 'saved' === sanitize_key( wp_unslash( $_GET['proctoring_review'] ) ) ) : ?>
            <div class="notice notice-success is-dismissible"><p>Tinjauan event pengawasan berhasil disimpan.</p></div>
        <?php endif; ?>
        <?php if ( $proctoring_error ) : ?>
            <div class="notice notice-error"><p>Metadata bukti keamanan belum dapat dibaca. Pastikan migrasi proctoring telah ditinjau dan diterapkan oleh pemilik Oracle.</p></div>
        <?php endif; ?>
        <section class="daw-psychotest-results__stats" aria-label="Ringkasan hasil psikotes">
            <article><span>Total Hasil</span><strong><?= esc_html( number_format_i18n( count( $results ) ) ) ?></strong></article>
            <article><span>Tes Selesai</span><strong><?= esc_html( number_format_i18n( $completed_count ) ) ?></strong></article>
            <article><span>Belum Selesai</span><strong><?= esc_html( number_format_i18n( $in_progress_count ) ) ?></strong></article>
        </section>
        <div class="daw-psychotest-results__table-wrap">
        <table class="widefat striped daw-psychotest-results__table">
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
                    $is_scored_assessment = false !== strpos( $test_label, 'IQ' ) || false !== strpos( $test_label, 'PAULI' ) || ( (float) ( $result['score'] ?? 0 ) > 0 );
                    $is_offline_result = ( 0 === (int) ( $result['response_count'] ?? 0 ) && ! empty( $result['completed_at'] ) ) || false !== strpos( $test_label, 'OFFLINE' );
                    $status = $is_offline_result
                        ? 'Selesai (Offline)'
                        : ( 'complete' === ( $result['status'] ?? '' ) ? 'Selesai' : 'Jawaban tersimpan — belum diselesaikan' );
                    ?>
                    <tr>
                        <td>
                            <strong><?= esc_html( (string) ( $result['candidate_name'] ?? 'Kandidat' ) ) ?></strong><br>
                            <small>ID <?= esc_html( (string) ( $result['candidate_id'] ?? '' ) ) ?><?= ! empty( $result['candidate_email'] ) ? ' · ' . esc_html( (string) $result['candidate_email'] ) : '' ?></small>
                        </td>
                        <td>
                            <?= esc_html( $title ) ?>
                            <?php if ( $is_offline_result ) : ?>
                                <br><span style="display:inline-block;margin-top:3px;font-size:10px;padding:2px 6px;background:#e0e7ff;color:#3730a3;border-radius:4px;font-weight:600;">Input Offline</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $is_offline_result ) : ?>
                                <span style="display:inline-block;padding:2px 8px;background:#ecfdf5;color:#065f46;border-radius:4px;font-size:12px;font-weight:600;">✓ Selesai (Offline)</span>
                            <?php else : ?>
                                <span class="daw-psychotest-results__status <?= 'complete' === ( $result['status'] ?? '' ) ? 'is-complete' : 'is-progress' ?>"><?= esc_html( $status ) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $is_offline_result ) : ?>
                                <span style="color:#64748b;font-size:12px;">Input Manual HR</span>
                            <?php else : ?>
                                <?= esc_html( (string) ( $result['response_count'] ?? 0 ) ) ?> / <?= esc_html( (string) ( $result['question_count'] ?? 0 ) ) ?>
                            <?php endif; ?>
                        </td>
                        <td><strong style="color:#0f172a;"><?= $is_offline_result || $is_scored_assessment ? esc_html( (string) ( $result['score'] ?? 0 ) ) : 'Tidak dihitung' ?></strong></td>
                        <td><?= esc_html( (string) ( $result['started_at'] ?? '' ) ) ?></td>
                        <td><?= esc_html( (string) ( $result['completed_at'] ?? '—' ) ) ?></td>
                        <td>
                            <?php if ( $is_offline_result ) : ?>
                                <span style="color:#64748b;font-size:12px;">Diselenggarakan offline di kantor DAW.</span>
                            <?php elseif ( ! $can_view_proctoring ) : ?>
                                Akses gambar dibatasi untuk HR berwenang.
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
                                        $violation_events = array_values(
                                            array_filter(
                                                $session_events,
                                                static fn ( $event ): bool => in_array(
                                                    (string) ( $event['event_type'] ?? '' ),
                                                    [ 'CAMERA_STOPPED', 'FACE_NOT_DETECTED', 'MULTIPLE_FACES', 'FACE_TURNED_AWAY' ],
                                                    true
                                                ) && is_numeric( $event['detection_duration_ms'] ?? null )
                                            )
                                        );
                                        $total_detection_duration_ms = array_sum(
                                            array_map(
                                                static fn ( $event ): int => absint( $event['detection_duration_ms'] ?? 0 ),
                                                $violation_events
                                            )
                                        );
                                        $repeat_escalations = count(
                                            array_filter(
                                                $violation_events,
                                                static fn ( $event ): bool => in_array(
                                                    (string) ( $event['severity'] ?? '' ),
                                                    [ 'repeated', 'serious' ],
                                                    true
                                                )
                                            )
                                        );
                                        $repeat_peak = 0;
                                        $occurrence_times = [];
                                        foreach ( $violation_events as $violation_event ) {
                                            $repeat_peak = max( $repeat_peak, absint( $violation_event['repeat_count'] ?? 1 ) );
                                            if ( '' !== (string) ( $violation_event['event_at'] ?? '' ) ) {
                                                $occurrence_times[] = (string) $violation_event['event_at'];
                                            }
                                        }
                                        sort( $occurrence_times, SORT_STRING );
                                        ?>
                                        <details>
                                            <summary><?= 'CAPTURED' === $identity_status ? 'Identitas terekam' : ( 'MANUAL_VERIFIED' === $identity_status ? 'Identitas diverifikasi manual' : ( 'MANUAL_REVIEW' === $identity_status ? 'Perlu verifikasi manual' : 'Belum diverifikasi' ) ) ?> · Lamaran #<?= esc_html( (string) ( $session['application_id'] ?? 'lama' ) ) ?> · <?= esc_html( (string) count( $session_events ) ) ?> peristiwa</summary>
                                            <small>Masa simpan sampai <?= esc_html( (string) ( $session['retention_until'] ?? '—' ) ) ?></small>
                                            <p><strong>Ringkasan deteksi:</strong>
                                                <?= esc_html( (string) count( $violation_events ) ) ?> event mencapai threshold
                                                · Durasi indikasi <?= esc_html( number_format_i18n( $total_detection_duration_ms / 1000, 1 ) ) ?> detik (dilaporkan browser)
                                                · Event berulang/serius <?= esc_html( (string) $repeat_escalations ) ?>
                                                · Frekuensi tertinggi <?= esc_html( (string) $repeat_peak ) ?> event dalam jendela aturannya
                                                <?php if ( $occurrence_times ) : ?>
                                                    · Pertama <?= esc_html( $occurrence_times[0] ) ?> · Terakhir <?= esc_html( $occurrence_times[ count( $occurrence_times ) - 1 ] ) ?>
                                                <?php endif; ?>
                                            </p>
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
                                                        'TAB_HIDDEN' => 'Halaman ujian disembunyikan atau fokus berpindah',
                                                        'TAB_VISIBLE' => 'Kembali ke halaman ujian',
                                                        'CAMERA_STOPPED' => 'Kamera terputus',
                                                        'CAMERA_UNAVAILABLE' => 'Kamera tidak tersedia',
                                                        'FACE_NOT_DETECTED' => 'Wajah tidak terdeteksi beberapa saat',
                                                        'MULTIPLE_FACES' => 'Lebih dari satu wajah terdeteksi',
                                                        'FACE_TURNED_AWAY' => 'Wajah menghadap menyamping dalam durasi lama',
                                                        'WARNING_SHOWN' => 'Peringatan ditampilkan kepada peserta',
                                                        'FACE_DETECTION_UNAVAILABLE' => 'Deteksi wajah tidak tersedia',
                                                        'MICROPHONE_AVAILABLE' => 'Mikrofon tersedia (status saja)',
                                                        'MICROPHONE_UNAVAILABLE' => 'Mikrofon tidak tersedia atau izin ditolak',
                                                        'IMAGE_ACCESS' => 'Akses gambar oleh HR',
                                                        'REPORT_DOWNLOAD' => 'Laporan proctoring diunduh',
                                                        'MANUAL_VERIFIED' => 'Identitas diverifikasi manual',
                                                    ];
                                                    $stage_labels = [
                                                        'preparation' => 'Persiapan',
                                                        'iq' => 'IQ',
                                                        'pauli' => 'Pauli',
                                                        'disc' => 'DISC',
                                                        'papi' => 'PAPI',
                                                        'report' => 'Laporan',
                                                    ];
                                                    $review_status = (string) ( $event['review_status'] ?? 'NOT_APPLICABLE' );
                                                    $review_labels = [
                                                        'PENDING' => 'Menunggu tinjauan HR',
                                                        'CONFIRMED' => 'Dikonfirmasi HR',
                                                        'DISMISSED' => 'Dikesampingkan HR',
                                                        'NOT_APPLICABLE' => 'Aktivitas log',
                                                    ];
                                                    $severity_labels = [
                                                        'warning' => 'Peringatan',
                                                        'repeated' => 'Berulang',
                                                        'serious' => 'Serius',
                                                    ];
                                                    $is_detection_event = in_array(
                                                        $event_type,
                                                        [ 'CAMERA_STOPPED', 'FACE_NOT_DETECTED', 'MULTIPLE_FACES', 'FACE_TURNED_AWAY' ],
                                                        true
                                                    ) && is_numeric( $event['detection_duration_ms'] ?? null );
                                                    ?>
                                                    <li>
                                                        <?= esc_html( $event_labels[ $event_type ] ?? 'Peristiwa pengawasan' ) ?>
                                                        <?php if ( '' !== (string) ( $event['assessment_stage'] ?? '' ) ) : ?>
                                                            · <?= esc_html( $stage_labels[ (string) $event['assessment_stage'] ] ?? (string) $event['assessment_stage'] ) ?>
                                                        <?php endif; ?>
                                                        · <?= esc_html( (string) ( $event['event_at'] ?? '' ) ) ?>
                                                        <?php if ( 'IMAGE_ACCESS' === $event_type ) : ?>
                                                            · Pengguna HR ID <?= esc_html( (string) ( $event['actor_user_id'] ?? '' ) ) ?> mengakses bukti <?= esc_html( (string) ( $event['target_event_id'] ?? '' ) ) ?>
                                                        <?php endif; ?>
                                                        <?php if ( $is_detection_event ) : ?>
                                                            <br>
                                                            <small>
                                                                <?= esc_html( $review_labels[ $review_status ] ?? 'Status tinjauan tidak diketahui' ) ?>
                                                                · Durasi indikasi <?= esc_html( number_format_i18n( (float) $event['detection_duration_ms'] / 1000, 1 ) ) ?> detik (dilaporkan browser)
                                                                · Pengulangan <?= esc_html( (string) absint( $event['repeat_count'] ?? 1 ) ) ?> kali
                                                                <?php if ( is_numeric( $event['detection_confidence'] ?? null ) ) : ?>
                                                                    · Confidence detector <?= esc_html( number_format_i18n( (float) $event['detection_confidence'] * 100, 1 ) ) ?>%
                                                                <?php endif; ?>
                                                                <?php if ( isset( $severity_labels[ (string) ( $event['severity'] ?? '' ) ] ) ) : ?>
                                                                    · <?= esc_html( $severity_labels[ (string) $event['severity'] ] ) ?>
                                                                <?php endif; ?>
                                                                <?php if ( isset( $severity_labels[ (string) ( $event['warning_level'] ?? '' ) ] ) ) : ?>
                                                                    · Notifikasi: <?= esc_html( $severity_labels[ (string) $event['warning_level'] ] ) ?>
                                                                <?php endif; ?>
                                                            </small>
                                                            <?php if ( 'PENDING' === $review_status ) : ?>
                                                                <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" style="margin:8px 0 12px; display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                                                                    <input type="hidden" name="action" value="daw_proctoring_review_event">
                                                                    <input type="hidden" name="candidate_id" value="<?= esc_attr( (string) $candidate_id ) ?>">
                                                                    <input type="hidden" name="session_id" value="<?= esc_attr( (string) $session['session_id'] ) ?>">
                                                                    <input type="hidden" name="event_id" value="<?= esc_attr( (string) absint( $event['event_id'] ?? 0 ) ) ?>">
                                                                    <?php wp_nonce_field( 'daw_proctoring_review_event_' . (string) $session['session_id'] . '_' . (string) absint( $event['event_id'] ?? 0 ) ); ?>
                                                                    <select name="review_status" aria-label="Hasil tinjauan event">
                                                                        <option value="CONFIRMED">Konfirmasi event</option>
                                                                        <option value="DISMISSED">Kesampingkan event</option>
                                                                    </select>
                                                                    <input type="text" name="review_note" maxlength="1000" placeholder="Catatan tinjauan (opsional)" aria-label="Catatan tinjauan HR">
                                                                    <button type="submit" class="button button-small">Simpan tinjauan</button>
                                                                </form>
                                                            <?php elseif ( '' !== (string) ( $event['reviewed_at'] ?? '' ) ) : ?>
                                                                <small>Ditinjau <?= esc_html( (string) $event['reviewed_at'] ) ?> oleh HR ID <?= esc_html( (string) ( $event['reviewed_by'] ?? '' ) ) ?><?= '' !== (string) ( $event['review_note'] ?? '' ) ? ' · ' . esc_html( (string) $event['review_note'] ) : '' ?></small>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                        <?php if ( ! empty( $event['file_key'] ) ) :
                                                            $base_image_args = [
                                                                'action' => 'daw_proctoring_image',
                                                                'candidate_id' => $candidate_id,
                                                                'session_id' => (string) $session['session_id'],
                                                                'event_id' => absint( $event['event_id'] ?? 0 ),
                                                            ];
                                                            $view_url = wp_nonce_url( add_query_arg( array_merge( $base_image_args, [ 'disposition' => 'inline' ] ), admin_url( 'admin-post.php' ) ), 'daw_proctoring_image_' . $candidate_id );
                                                            $download_url = wp_nonce_url( add_query_arg( $base_image_args, admin_url( 'admin-post.php' ) ), 'daw_proctoring_image_' . $candidate_id );
                                                            ?>
                                                            <div style="margin:6px 0 10px; display:flex; align-items:center; gap:10px;">
                                                                <a href="<?= esc_url( $view_url ) ?>" target="_blank" rel="noopener" title="Klik untuk memperbesar foto">
                                                                    <img src="<?= esc_url( $view_url ) ?>" alt="Bukti Foto Identitas" style="max-height:64px; max-width:96px; border-radius:6px; border:1px solid #cbd5e1; object-fit:cover; vertical-align:middle; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                                                                </a>
                                                                <div>
                                                                    <a class="button button-small" href="<?= esc_url( $view_url ) ?>" target="_blank" rel="noopener">👁 Lihat Foto</a>
                                                                    <a class="button button-small" href="<?= esc_url( $download_url ) ?>" style="margin-left:4px;">⬇ Unduh</a>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <?php if (
                                                absint( $session['application_id'] ?? 0 ) > 0
                                                && ! empty( recruitment_get_psychotest_result_ids_for_application(
                                                    $candidate_id,
                                                    absint( $session['application_id'] )
                                                ) )
                                            ) :
                                                $report_args = [
                                                    'action' => 'daw_proctoring_report',
                                                    'candidate_id' => $candidate_id,
                                                    'session_id' => (string) $session['session_id'],
                                                ];
                                                $report_url = wp_nonce_url(
                                                    add_query_arg( $report_args, admin_url( 'admin-post.php' ) ),
                                                    'daw_proctoring_report_' . (string) $session['session_id']
                                                );
                                                ?>
                                                <a class="button button-secondary" href="<?= esc_url( $report_url ) ?>">Unduh Laporan PDF Lamaran #<?= esc_html( (string) $session['application_id'] ) ?></a>
                                            <?php elseif ( absint( $session['application_id'] ?? 0 ) > 0 ) : ?>
                                                <small>Laporan tersedia setelah minimal satu tes pada lamaran ini selesai.</small>
                                            <?php else : ?>
                                                <small>Laporan per lamaran tidak tersedia untuk sesi lama.</small>
                                            <?php endif; ?>
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
        </div>
    <?php endif; ?>
</div>
