<?php
$back_url = recruitment_get_public_url( 'application-process' );
$user_id = recruitment_get_assessment_user_id();
$iq_state = recruitment_get_assessment_state( $user_id, 'recruitment_iq_assessment' );
$pauli_state = recruitment_get_assessment_state( $user_id, 'recruitment_pauli_assessment' );
$personality_state = recruitment_get_assessment_state( $user_id, 'recruitment_personality_assessment' );
$iq_complete = is_array( $iq_state ) && 'complete' === ( $iq_state['status'] ?? '' );
$pauli_complete = is_array( $pauli_state ) && 'complete' === ( $pauli_state['status'] ?? '' );
$disc_complete = is_array( $personality_state ) && 'complete' === ( $personality_state['tests']['disc']['status'] ?? '' );
$papi_complete = is_array( $personality_state ) && 'complete' === ( $personality_state['tests']['papi']['status'] ?? '' );
$completed_count = count( array_filter( [ $iq_complete, $pauli_complete, $disc_complete, $papi_complete ] ) );
$progress = (int) round( $completed_count / 4 * 100 );
$assessment_database = new Recruitment_Database();
$pauli_assessments = $assessment_database->get_pauli_assessment();
$pauli_duration = $assessment_database->has_error()
    ? 0
    : absint( $pauli_assessments[0]['DURATION'] ?? 0 );
$pauli_duration_label = $pauli_duration > 0
    ? $pauli_duration . ' menit'
    : 'Belum tersedia';
$tests = [
    [
        'number' => '01',
        'title' => 'IQ',
        'description' => 'Tes Kemampuan Intelektual',
        'duration' => '4 subtes dengan batas waktu masing-masing',
        'complete' => $iq_complete,
        'active' => is_array( $iq_state ) && 'active' === ( $iq_state['status'] ?? '' ),
        'unlocked' => true,
        'screen' => 'psych-iq',
        'test' => 'iq',
    ],
    [
        'number' => '02',
        'title' => 'PAULI',
        'description' => 'Tes Ketelitian dan Konsentrasi Kerja',
        'duration' => $pauli_duration_label,
        'complete' => $pauli_complete,
        'active' => is_array( $pauli_state ) && 'active' === ( $pauli_state['status'] ?? '' ),
        'unlocked' => $iq_complete,
        'screen' => 'psych-pauli',
        'test' => 'pauli',
    ],
    [
        'number' => '03',
        'title' => 'DISC',
        'description' => 'Asesmen Perilaku',
        'duration' => 'Tanpa batas waktu · pengingat setelah 20 menit',
        'complete' => $disc_complete,
        'active' => is_array( $personality_state ) && 'active' === ( $personality_state['tests']['disc']['status'] ?? '' ),
        'unlocked' => $pauli_complete,
        'screen' => 'psych-personality',
        'test' => 'disc',
    ],
    [
        'number' => '04',
        'title' => 'PAPI KOSTICK',
        'description' => 'Asesmen Preferensi dalam Lingkungan Kerja',
        'duration' => 'Tanpa batas waktu · pengingat setelah 30 menit',
        'complete' => $papi_complete,
        'active' => is_array( $personality_state ) && 'active' === ( $personality_state['tests']['papi']['status'] ?? '' ),
        'unlocked' => $disc_complete,
        'screen' => 'psych-personality',
        'test' => 'papi',
    ],
];
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
                    <p>Selesaikan tes secara berurutan. Jawaban tersimpan saat Anda mengerjakannya. Durasi Pauli berubah mengikuti jumlah kolom yang ditetapkan HR.</p>
                </div>
                <div class="daw-recruitment__psychotest-counter" aria-label="Progress tes psikologi">
                    <span class="daw-recruitment__muted-small"><?= esc_html( (string) $completed_count ) ?> / 4</span>
                    <strong>Tes Selesai</strong>
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
                    <span><?= esc_html( (string) $completed_count ) ?> dari 4 tes selesai</span>
                    <span><?= esc_html( (string) $progress ) ?>%</span>
                </div>
            </section>

            <section class="daw-recruitment__psychotest-card daw-recruitment__psychotest-alert">
                <div class="daw-recruitment__info-box">
                    <div class="daw-recruitment__info-box-icon" aria-hidden="true">i</div>
                    <div>
                        <strong>Sebelum memulai</strong>
                        <p>Gunakan perangkat yang nyaman, koneksi internet stabil, dan tempat yang tenang. Tes Pauli berdurasi <?= esc_html( $pauli_duration_label ) ?>; DISC dan PAPI tidak dibatasi waktu dan hanya menampilkan pengingat.</p>
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
                    $test_url = $active
                        ? recruitment_get_public_url( $test['screen'], 'psych-personality' === $test['screen'] ? [ 'test' => $test['test'] ] : [] )
                        : recruitment_get_public_url( 'psychotest-preparation', [ 'test' => $test['test'] ] );
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
                        <?php if ( ! $complete && $unlocked ) : ?>
                            <a class="daw-recruitment__button daw-recruitment__button--small" href="<?= esc_url( $test_url ) ?>"><?= esc_html( $active ? 'Lanjutkan Tes' : 'Mulai Tes' ) ?></a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>

            <div class="daw-recruitment__psychotest-footer">
                <div class="daw-recruitment__psychotest-divider" aria-hidden="true"></div>
                <p>Setiap tes akan terbuka setelah tahapan sebelumnya selesai.</p>
            </div>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
