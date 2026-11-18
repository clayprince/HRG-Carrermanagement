<?php
$requested_application_id = isset( $_GET['application_id'] ) && is_scalar( $_GET['application_id'] )
    ? absint( wp_unslash( (string) $_GET['application_id'] ) )
    : 0;
if ( $requested_application_id < 1 ) {
    wp_die( esc_html__( 'Pilih lamaran psikotes yang aktif sebelum memulai persiapan.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
}
$stage_status = recruitment_applicant_psychotest_stage_status( (string) $requested_application_id );
if ( null === $stage_status ) {
    wp_die( esc_html__( 'Status lamaran tidak dapat diverifikasi. Silakan coba kembali nanti.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
}
if ( ! $stage_status ) {
    wp_die( esc_html__( 'Persiapan hanya tersedia untuk lamaran yang sedang berada pada tahap psikotes.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
}
$proctoring = new Recruitment_Psychotest_Proctoring();
if ( $proctoring->has_current_consent( $requested_application_id ) ) {
    wp_safe_redirect( recruitment_get_public_url( 'psychotest', [ 'application_id' => $requested_application_id ] ) );
    exit;
}
if ( $proctoring->get_current_session_error() ) {
    wp_die( esc_html__( 'Status persiapan tidak dapat dibaca dari Oracle. Silakan coba kembali nanti.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
}
$back_url = recruitment_get_public_url( 'psychotest', [ 'application_id' => $requested_application_id ] );
$requested_test = isset( $_GET['test'] ) && is_scalar( $_GET['test'] ) ? sanitize_key( (string) $_GET['test'] ) : '';
$test_key = $requested_test;
if ( '' === $test_key || ! recruitment_is_psychotest_test_enabled( $test_key ) ) {
    foreach ( [ 'iq', 'pauli', 'disc', 'papi' ] as $candidate_test ) {
        if ( recruitment_is_psychotest_test_enabled( $candidate_test ) ) {
            $test_key = $candidate_test;
            break;
        }
    }
}
if ( '' === $test_key || ! recruitment_is_psychotest_test_enabled( $test_key ) ) {
    wp_die( esc_html__( 'Tes ini belum dibuka, sudah ditutup, atau tidak diaktifkan oleh HR.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
}
$global_settings = (array) get_option( 'recruitment_psychotest_settings', [] );
$test_settings = (array) get_option( 'recruitment_psychotest_tests', [] );
$camera_required = isset( $test_settings[ $test_key ]['camera_required'] )
    ? ! empty( $test_settings[ $test_key ]['camera_required'] )
    : ! empty( $global_settings['camera_required'] ?? true );
$device_arguments = [ 'test' => $test_key, 'application_id' => $requested_application_id ];
$device_url = recruitment_get_public_url( 'psychotest-device', $device_arguments );
?>
<div class="daw-recruitment daw-recruitment--psychotest-preparation">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__psychotest-main">
        <div class="daw-recruitment__psychotest-shell daw-recruitment__psychotest-shell--narrow">
            <a class="daw-recruitment__back-link" href="<?= esc_url( $back_url ) ?>">&larr; Kembali ke Beranda Psikotes</a>

            <div class="daw-recruitment__psychotest-stepper" aria-label="Tahap persiapan psikotes">
                <div class="daw-recruitment__psychotest-step is-active">
                    <span>1</span>
                    <small>Persiapan</small>
                </div>
                <div class="daw-recruitment__psychotest-step">
                    <span>2</span>
                    <small>Perangkat</small>
                </div>
                <div class="daw-recruitment__psychotest-step">
                    <span>3</span>
                    <small>Kamera</small>
                </div>
                <div class="daw-recruitment__psychotest-step">
                    <span>4</span>
                    <small>Persetujuan</small>
                </div>
                <div class="daw-recruitment__psychotest-step">
                    <span>5</span>
                    <small>Foto &amp; KTP</small>
                </div>
            </div>

            <div class="daw-recruitment__psychotest-prep-card">
                <div class="daw-recruitment__prepare-intro">
                    <h1>Persiapan Assesment</h1>
                    <p>Lengkapi persiapan ini satu kali sebelum memulai rangkaian tes psikotes.</p>
                </div>

                <div class="daw-recruitment__prepare-section">
                    <h2>Daftar Persiapan</h2>
                    <ul class="daw-recruitment__prepare-list">
                        <li>
                            <span class="daw-recruitment__prepare-icon">✓</span>
                            <div>
                                <strong>Internet stabil</strong>
                                <small>Pastikan koneksi internet Anda stabil sebelum memulai tes.</small>
                            </div>
                        </li>
                        <li>
                            <span class="daw-recruitment__prepare-icon">✓</span>
                            <div>
                                <strong>Laptop / desktop</strong>
                                <small>Gunakan perangkat dengan layar yang cukup besar dan nyaman untuk mengerjakan soal.</small>
                            </div>
                        </li>
                        <li>
                            <span class="daw-recruitment__prepare-icon">✓</span>
                            <div>
                                <strong>Baterai cukup</strong>
                                <small>Pastikan daya baterai mencukupi agar tes tidak terganggu di tengah proses.</small>
                            </div>
                        </li>
                        <li>
                            <span class="daw-recruitment__prepare-icon">✓</span>
                            <div>
                                <strong>Tempat tenang</strong>
                                <small>Kerjakan di ruangan yang tenang dan bebas dari gangguan suara maupun orang lain.</small>
                            </div>
                        </li>
                        <li>
                            <span class="daw-recruitment__prepare-icon">✓</span>
                            <div>
                                <strong>Baca instruksi</strong>
                                <small>Perhatikan setiap arahan dan jangan melewatkan petunjuk yang diberikan pada tiap tes.</small>
                            </div>
                        </li>
                        <li>
                            <span class="daw-recruitment__prepare-icon">✓</span>
                            <div>
                                <strong>Kerjakan mandiri</strong>
                                <small>Jangan berdiskusi, meminta bantuan, atau membuka sumber lain saat assessment berlangsung.</small>
                            </div>
                        </li>
                        <li>
                            <span class="daw-recruitment__prepare-icon">✓</span>
                            <div>
                                <strong>Jangan meninggalkan halaman</strong>
                                <small>Usahakan untuk tidak berpindah tab atau menutup halaman sepanjang tes berjalan.</small>
                            </div>
                        </li>
                        <li>
                            <span class="daw-recruitment__prepare-icon">✓</span>
                            <div>
                                <strong><?= $camera_required ? 'Izinkan kamera' : 'Kamera opsional' ?></strong>
                                <small><?= $camera_required ? 'Izinkan akses kamera pada tahap pemeriksaan sebelum melanjutkan.' : 'Pemeriksaan kamera dapat dilewati untuk tes ini.' ?></small>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="daw-recruitment__prepare-actions">
                    <a class="daw-recruitment__button daw-recruitment__button--full" href="<?= esc_url( $device_url ) ?>">Saya Siap — Periksa Perangkat &rarr;</a>
                    <p>Setelah persiapan dan verifikasi identitas selesai, Anda dapat memulai Tes IQ.</p>
                </div>
            </div>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
