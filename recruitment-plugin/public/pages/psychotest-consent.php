<?php
$test_key = sanitize_key( wp_unslash( $_GET['test'] ?? '' ) );
$test_labels = [
    'iq' => 'IQ',
    'pauli' => 'Pauli',
    'disc' => 'DISC',
    'papi' => 'PAPI Kostick',
];
if ( ! isset( $test_labels[ $test_key ] ) ) {
    wp_safe_redirect( recruitment_get_public_url( 'psychotest' ) );
    exit;
}
$device_url = recruitment_get_public_url( 'psychotest-device', [ 'test' => $test_key ] );
?>
<div class="daw-recruitment daw-recruitment--psychotest-consent">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__psychotest-main">
        <div class="daw-recruitment__psychotest-shell daw-recruitment__psychotest-shell--narrow">
            <a class="daw-recruitment__back-link" href="<?= esc_url( $device_url ) ?>">&larr; Kembali ke pemeriksaan perangkat</a>
            <div class="daw-recruitment__psychotest-stepper" aria-label="Tahap persetujuan psikotes">
                <div class="daw-recruitment__psychotest-step"><span>1</span><small>Persiapan</small></div>
                <div class="daw-recruitment__psychotest-step"><span>2</span><small>Perangkat</small></div>
                <div class="daw-recruitment__psychotest-step"><span>3</span><small>Kamera</small></div>
                <div class="daw-recruitment__psychotest-step is-active"><span>4</span><small>Persetujuan</small></div>
            </div>
            <section class="daw-recruitment__psychotest-card">
                <h1>Persetujuan Tes <?= esc_html( $test_labels[ $test_key ] ) ?></h1>
                <p>Jawaban dan waktu pengerjaan akan dicatat dan disimpan untuk proses seleksi. Kamera dan mikrofon digunakan untuk pemeriksaan perangkat di browser; plugin tidak mengunggah rekaman.</p>
                <form method="post" action="<?= esc_url( recruitment_get_public_url( 'psychotest-consent', [ 'test' => $test_key ] ) ) ?>">
                    <?php wp_nonce_field( 'recruitment_psychotest_consent' ); ?>
                    <input type="hidden" name="test" value="<?= esc_attr( $test_key ) ?>">
                    <label>
                        <input type="checkbox" name="consent" value="1" required>
                        Saya memahami dan menyetujui pemrosesan jawaban tes untuk keperluan seleksi.
                    </label>
                    <button class="daw-recruitment__button daw-recruitment__button--full" type="submit">Setuju dan mulai tes</button>
                </form>
            </section>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
