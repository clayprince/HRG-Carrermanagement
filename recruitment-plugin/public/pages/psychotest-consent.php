<?php
$test_key = sanitize_key( wp_unslash( $_GET['test'] ?? '' ) );
$test_labels = [
    'iq' => 'IQ',
    'pauli' => 'Pauli',
    'disc' => 'DISC',
    'papi' => 'PAPI Kostick',
];
$requested_application_id = isset( $_GET['application_id'] ) && is_scalar( $_GET['application_id'] )
    ? absint( wp_unslash( (string) $_GET['application_id'] ) )
    : 0;
if ( ! isset( $test_labels[ $test_key ] ) ) {
    wp_safe_redirect( recruitment_get_public_url( 'psychotest' ) );
    exit;
}
$consent_arguments = [ 'test' => $test_key ];
if ( $requested_application_id > 0 ) {
    $consent_arguments['application_id'] = $requested_application_id;
}
$device_url = recruitment_get_public_url( 'psychotest-device', $consent_arguments );
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
                <p>Jawaban dan waktu pengerjaan akan dicatat dan disimpan untuk proses seleksi. Untuk pemeriksaan identitas dan keamanan tes, sistem akan menyimpan satu foto selfie sambil memegang sisi depan KTP serta paling banyak lima snapshot peringatan untuk seluruh sesi tes. Anda dapat memilih “Periksa Mikrofon” saat tes untuk memeriksa izin dan ketersediaan perangkat; hanya status tersedia/tidak tersedia yang dicatat. Audio tidak direkam atau disimpan, kamera dan mikrofon hanya digunakan untuk pemeriksaan perangkat di browser, dan gambar tidak dianalisis dengan OCR atau pencocokan wajah.</p>
                <ul>
                    <li>Peringatan dapat dicatat saat Anda berpindah tab atau kamera terputus. Peringatan menjadi bahan tinjauan manusia dan tidak otomatis menggagalkan tes.</li>
                    <li>Jika kamera tidak tersedia, Anda dapat meminta verifikasi identitas manual oleh HR.</li>
                    <li>Gambar hanya dapat diakses melalui akun WordPress Administrator, dicatat saat diakses, dan dihapus otomatis paling lambat satu tahun setelah tes selesai. Jika tes tidak selesai, masa simpan dihitung sejak persetujuan diberikan.</li>
                </ul>
                <form method="post" action="<?= esc_url( recruitment_get_public_url( 'psychotest-consent', $consent_arguments ) ) ?>">
                    <?php wp_nonce_field( 'recruitment_psychotest_consent' ); ?>
                    <input type="hidden" name="test" value="<?= esc_attr( $test_key ) ?>">
                    <label>
                        <input type="checkbox" name="consent" value="1" required>
                        Saya memahami dan menyetujui pengambilan serta penyimpanan foto identitas dan snapshot peringatan serta pencatatan status ketersediaan mikrofon sesuai penjelasan di atas. Saya memahami bahwa audio tidak direkam dan jalur verifikasi manual tersedia bila kamera tidak dapat digunakan.
                    </label>
                    <button class="daw-recruitment__button daw-recruitment__button--full" type="submit">Setuju dan lanjutkan verifikasi identitas</button>
                </form>
            </section>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
