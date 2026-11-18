<?php
$application_id = recruitment_get_psychotest_application_id();
if ( $application_id < 1 ) {
    wp_safe_redirect( recruitment_get_public_url( 'application-process' ) );
    exit;
}
$stage_status = recruitment_applicant_psychotest_stage_status( (string) $application_id );
if ( null === $stage_status ) {
    wp_die( esc_html__( 'Status lamaran tidak dapat diverifikasi. Silakan coba kembali nanti.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
}
if ( ! $stage_status ) {
    wp_die( esc_html__( 'Beranda psikotes hanya tersedia untuk lamaran yang sedang berada pada tahap psikotes.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
}
$back_url = recruitment_get_public_url( 'application-process', [ 'application_id' => $application_id ] );
$user_id = recruitment_get_assessment_user_id();
$iq_state = recruitment_get_assessment_state( $user_id, 'recruitment_iq_assessment' );
$pauli_state = recruitment_get_assessment_state( $user_id, 'recruitment_pauli_assessment' );
$personality_state = recruitment_get_assessment_state( $user_id, 'recruitment_personality_assessment' );
$iq_state = recruitment_assessment_state_matches_candidate( $iq_state, $user_id ) ? $iq_state : [];
$pauli_state = recruitment_assessment_state_matches_candidate( $pauli_state, $user_id ) ? $pauli_state : [];
$personality_state = recruitment_assessment_state_matches_candidate( $personality_state, $user_id ) ? $personality_state : [];
$personality_tests = is_array( $personality_state['tests'] ?? null ) ? $personality_state['tests'] : [];
$iq_complete = is_array( $iq_state ) && 'complete' === ( $iq_state['status'] ?? '' );
$pauli_complete = is_array( $pauli_state ) && 'complete' === ( $pauli_state['status'] ?? '' );
$disc_complete = 'complete' === ( $personality_tests['disc']['status'] ?? '' );
$papi_complete = 'complete' === ( $personality_tests['papi']['status'] ?? '' );
$personality_complete = $disc_complete && $papi_complete;
$personality_active_test = is_array( $personality_state ) ? sanitize_key( (string) ( $personality_state['active_test'] ?? '' ) ) : '';
$personality_next_test = 'papi' === $personality_active_test || $disc_complete ? 'papi' : 'disc';
$personality_active = in_array( $personality_active_test, [ 'disc', 'papi' ], true )
    && 'active' === ( $personality_tests[ $personality_active_test ]['status'] ?? '' );
$proctoring = new Recruitment_Psychotest_Proctoring();
$preparation_complete = $proctoring->has_current_consent( $application_id );
if ( $proctoring->get_current_session_error() ) {
    wp_die( esc_html__( 'Status persiapan psikotes tidak dapat dibaca dari Oracle. Silakan coba kembali nanti.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
}
$psychotest_settings = recruitment_sync_psychotest_closing( (array) get_option( 'recruitment_psychotest_settings', [] ) );
$all_test_settings = (array) get_option( 'recruitment_psychotest_tests', [] );
$pauli_cfg = function_exists( 'recruitment_get_pauli_config' )
    ? recruitment_get_pauli_config()
    : [ 'duration_minutes' => 60, 'columns' => 10, 'numbers_per_column' => 50, 'interval_seconds' => 60 ];

$iq_enabled = recruitment_is_psychotest_test_enabled( 'iq' );
$pauli_enabled = recruitment_is_psychotest_test_enabled( 'pauli' );
$disc_enabled = recruitment_is_psychotest_test_enabled( 'disc' );
$papi_enabled = recruitment_is_psychotest_test_enabled( 'papi' );

$iq_settings = $all_test_settings['iq'] ?? [];
$disc_settings = $all_test_settings['disc'] ?? [];
$papi_settings = $all_test_settings['papi'] ?? [];

$tests_blueprint = [];

if ( $iq_enabled ) {
    $iq_dur = (int) ( $iq_settings['duration'] ?? 20 );
    $iq_qc = (int) ( $iq_settings['question_count'] ?? 10 );
    $tests_blueprint[] = [
        'key' => 'iq',
        'title' => 'IQ',
        'description' => 'Tes Kemampuan Intelektual',
        'duration' => $iq_dur . ' menit · ' . $iq_qc . ' soal',
        'complete' => $iq_complete,
        'active' => is_array( $iq_state ) && 'active' === ( $iq_state['status'] ?? '' ),
        'screen' => 'psych-iq',
        'test_param' => null,
        'test' => 'iq',
    ];
}

if ( $pauli_enabled ) {
    $pauli_dur = (int) ( $pauli_cfg['duration_minutes'] ?? 60 );
    $pauli_cols = (int) ( $pauli_cfg['columns'] ?? 10 );
    $tests_blueprint[] = [
        'key' => 'pauli',
        'title' => 'PAULI',
        'description' => 'Tes Ketelitian dan Kecepatan Kerja',
        'duration' => $pauli_dur . ' menit · ' . $pauli_cols . ' kolom',
        'complete' => $pauli_complete,
        'active' => is_array( $pauli_state ) && 'active' === ( $pauli_state['status'] ?? '' ),
        'screen' => 'psych-pauli',
        'test_param' => null,
        'test' => 'pauli',
    ];
}

if ( $disc_enabled ) {
    $disc_dur = (int) ( $disc_settings['duration'] ?? 10 );
    $disc_qc = (int) ( $disc_settings['question_count'] ?? 5 );
    $tests_blueprint[] = [
        'key' => 'disc',
        'title' => 'DISC',
        'description' => 'Asesmen Profil Perilaku Kerja',
        'duration' => $disc_dur . ' menit · ' . $disc_qc . ' skenario',
        'complete' => $disc_complete,
        'active' => 'disc' === $personality_active_test && 'active' === ( $personality_tests['disc']['status'] ?? '' ),
        'screen' => 'psych-personality',
        'test_param' => 'disc',
        'test' => 'disc',
    ];
}

if ( $papi_enabled ) {
    $papi_dur = (int) ( $papi_settings['duration'] ?? 10 );
    $papi_qc = (int) ( $papi_settings['question_count'] ?? 8 );
    $tests_blueprint[] = [
        'key' => 'papi',
        'title' => 'PAPI KOSTICK',
        'description' => 'Asesmen Preferensi & Peran Kerja',
        'duration' => $papi_dur . ' menit · ' . $papi_qc . ' pasangan',
        'complete' => $papi_complete,
        'active' => 'papi' === $personality_active_test && 'active' === ( $personality_tests['papi']['status'] ?? '' ),
        'screen' => 'psych-personality',
        'test_param' => 'papi',
        'test' => 'papi',
    ];
}

$tests = [];
$can_unlock = true;
foreach ( $tests_blueprint as $idx => $t ) {
    $is_unlocked = $can_unlock || $t['active'] || $t['complete'];
    $t['number'] = str_pad( (string) ( $idx + 1 ), 2, '0', STR_PAD_LEFT );
    $t['unlocked'] = $is_unlocked;
    $tests[] = $t;

    if ( ! $t['complete'] ) {
        $can_unlock = false;
    }
}
$completed_count = count( array_filter( $tests, static fn ( array $test ): bool => $test['complete'] ) );
$test_count = count( $tests );
$progress = 0 < $test_count ? (int) round( $completed_count / $test_count * 100 ) : 0;
?>
<div class="daw-recruitment daw-recruitment--psychotest-home">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__psychotest-main">
        <div class="daw-recruitment__psychotest-shell">
            <a class="daw-recruitment__back-link" href="<?= esc_url( $back_url ) ?>">&larr; Kembali ke Proses Lamaran</a>

            <header class="daw-recruitment__psychotest-header">
                <div class="daw-recruitment__psychotest-header-copy">
                    <span class="daw-recruitment__eyebrow daw-recruitment__eyebrow--red">TES PSIKOLOGI ONLINE</span>
                    <h1>Tes Psikologi</h1>
                    <p>Kerjakan tes yang diaktifkan oleh HR. Tahap Kepribadian terdiri dari DISC dan PAPI Kostick; jawaban tersimpan saat Anda mengerjakannya.</p>
                </div>
                <div class="daw-recruitment__psychotest-counter" aria-label="Progress tes psikologi">
                    <span class="daw-recruitment__muted-small"><?= esc_html( (string) $completed_count ) ?> / <?= esc_html( (string) $test_count ) ?></span>
                    <strong>Tahap Selesai</strong>
                </div>
            </header>

            <section class="daw-recruitment__psychotest-card daw-recruitment__psychotest-summary">
                <div class="daw-recruitment__psychotest-summary-row">
                    <span class="daw-recruitment__progress-label">Progress</span>
                    <span class="daw-recruitment__count-badge"><?= esc_html( (string) $progress ) ?>%</span>
                </div>
                <div class="daw-recruitment__progress-bar" aria-label="Progress tes psikologi">
                    <span style="width: <?= esc_attr( (string) $progress ) ?>%"></span>
                </div>
                <div class="daw-recruitment__psychotest-summary-meta">
                    <span><?= esc_html( (string) $completed_count ) ?> dari <?= esc_html( (string) $test_count ) ?> tahap selesai</span>
                    <span><?= esc_html( (string) $progress ) ?>%</span>
                </div>
            </section>

            <section class="daw-recruitment__psychotest-card daw-recruitment__psychotest-alert">
                <div class="daw-recruitment__info-box">
                    <div class="daw-recruitment__info-box-icon" aria-hidden="true">i</div>
                    <div>
                        <strong>Sebelum memulai</strong>
                        <p><?= 0 < $test_count ? 'Gunakan perangkat yang nyaman, koneksi internet stabil, dan tempat yang tenang. Selesaikan rangkaian tes secara bertahap sesuai petunjuk di setiap modul.' : 'Tes online sedang tidak tersedia atau belum diaktifkan oleh HR. Silakan periksa kembali jadwal di Tracking Lamaran atau hubungi HR.' ?></p>
                    </div>
                </div>
            </section>

            <section class="daw-recruitment__psychotest-list" aria-label="Daftar tes psikologi">
                <?php foreach ( $tests as $test ) :
                    $complete = $test['complete'];
                    $active = $test['active'];
                    $unlocked = $test['unlocked'];
                    $status = $complete ? 'Selesai' : ( $active ? 'Sedang dikerjakan' : ( $unlocked ? 'Tersedia' : 'Menunggu tes sebelumnya' ) );
                    $status_class = $complete ? 'complete' : ( $unlocked ? 'available' : 'locked' );
                    $test_arguments = 'psych-personality' === $test['screen'] ? [ 'test' => $test['test'] ] : [];
                    $test_arguments['application_id'] = $application_id;
                    if ( $preparation_complete ) {
                        if ( ! $active ) {
                            $test_arguments['start'] = '1';
                        }
                        $test_url = recruitment_get_public_url( $test['screen'], $test_arguments );
                    } else {
                        $test_url = '';
                    }
                    ?>
                    <article class="daw-recruitment__psychotest-item <?= esc_attr( 'is-' . $status_class ) ?>">
                        <div class="daw-recruitment__psychotest-index"><?= esc_html( $test['number'] ) ?></div>
                        <div class="daw-recruitment__psychotest-body">
                            <div class="daw-recruitment__psychotest-row">
                                <h2><?= esc_html( $test['title'] ) ?></h2>
                                <span class="daw-recruitment__tag daw-recruitment__psychotest-tag--<?= esc_attr( $status_class ) ?>"><?= esc_html( $status ) ?></span>
                            </div>
                            <p class="daw-recruitment__psychotest-description"><?= esc_html( $test['description'] ) ?></p>
                            <small><?= esc_html( $test['duration'] ) ?></small>
                        </div>
                        <?php if ( ! $complete && $unlocked && '' !== $test_url ) : ?>
                            <a class="daw-recruitment__button daw-recruitment__button--small" href="<?= esc_url( $test_url ) ?>"><?= esc_html( $active ? 'Lanjutkan Tes' : 'Mulai Tes' ) ?></a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>

            <?php if ( ! $preparation_complete && 0 < $test_count ) : ?>
                <section class="daw-recruitment__psychotest-card daw-recruitment__psychotest-preparation-cta">
                    <div>
                        <strong>Persiapan awal diperlukan</strong>
                        <p>Lengkapi pemeriksaan perangkat, kamera, persetujuan, dan verifikasi identitas sebelum memulai Tes IQ.</p>
                    </div>
                    <a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'psychotest-preparation', [ 'application_id' => $application_id ] ) ) ?>">Persiapan Assesment</a>
                </section>
            <?php endif; ?>

            <div class="daw-recruitment__psychotest-footer">
                <div class="daw-recruitment__psychotest-divider" aria-hidden="true"></div>
                <p>Setiap tes akan terbuka setelah tahapan sebelumnya selesai.</p>
            </div>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
