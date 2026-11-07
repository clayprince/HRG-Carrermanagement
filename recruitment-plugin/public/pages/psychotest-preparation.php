<?php
$back_url = recruitment_get_public_url( 'psychotest' );
$test_key = sanitize_key( wp_unslash( $_GET['test'] ?? 'iq' ) );
$test_key = in_array( $test_key, [ 'iq', 'pauli', 'disc', 'papi' ], true ) ? $test_key : 'iq';
$global_settings = (array) get_option( 'recruitment_psychotest_settings', [] );
$test_settings = (array) get_option( 'recruitment_psychotest_tests', [] );
$camera_required = isset( $test_settings[ $test_key ]['camera_required'] )
    ? ! empty( $test_settings[ $test_key ]['camera_required'] )
    : ! empty( $global_settings['camera_required'] ?? true );
$device_url = recruitment_get_public_url( 'psychotest-device', [ 'test' => $test_key ] );
?>
<div class="daw-recruitment daw-recruitment--psychotest-preparation">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__psychotest-main">
        <div class="daw-recruitment__psychotest-shell daw-recruitment__psychotest-shell--narrow">
            <a class="daw-recruitment__back-link" href="<?= esc_url( $back_url ) ?>">&larr; Kembali ke Daftar Tes</a>

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
            </div>

            <div class="daw-recruitment__psychotest-prep-card">
                <div class="daw-recruitment__prepare-intro">
                    <h1>Persiapan Tes <?= esc_html( strtoupper( 'papi' === $test_key ? 'PAPI Kostick' : $test_key ) ) ?></h1>
                    <p>Baca dan penuhi seluruh persyaratan di bawah ini agar proses assessment berjalan lancar, konsisten, dan sesuai jadwal.</p>
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
                                <small><?= $camera_required ? 'Izinkan akses kamera dan mikrofon sebelum melanjutkan.' : 'Pemeriksaan kamera dapat dilewati untuk tes ini.' ?></small>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="daw-recruitment__prepare-actions">
                    <a class="daw-recruitment__button daw-recruitment__button--full" href="<?= esc_url( $device_url ) ?>">Saya Siap — Periksa Perangkat &rarr;</a>
                    <p>Anda akan diarahkan ke pemeriksaan perangkat, kamera, dan halaman persetujuan sebelum tes dimulai.</p>
                </div>
            </div>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
