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
                <div class="daw-recruitment__psychotest-step"><span>5</span><small>Foto &amp; KTP</small></div>
            </div>
            <section class="daw-recruitment__psychotest-card">
                <header class="daw-consent__header">
                    <span class="daw-consent__eyebrow">Persetujuan dan privasi</span>
                    <h1>Persetujuan Tes <?= esc_html( $test_labels[ $test_key ] ) ?></h1>
                    <p>Sebelum melanjutkan, tinjau informasi tentang data yang digunakan selama tes dan cara data tersebut dilindungi.</p>
                </header>

                <section class="daw-consent__section" aria-labelledby="daw-consent-data-title">
                    <h2 id="daw-consent-data-title">Informasi yang perlu Anda ketahui</h2>
                    <div class="daw-consent__facts">
                        <article class="daw-consent__fact">
                            <h3>Jawaban tes</h3>
                            <p>Jawaban dan waktu pengerjaan dicatat dan disimpan untuk proses seleksi.</p>
                        </article>
                        <article class="daw-consent__fact">
                            <h3>Foto identitas</h3>
                            <p>Satu foto selfie sambil memegang sisi depan KTP disimpan untuk pemeriksaan identitas.</p>
                        </article>
                        <article class="daw-consent__fact">
                            <h3>Pemeriksaan kamera</h3>
                            <p>Browser memeriksa secara lokal jika wajah tidak terlihat beberapa saat atau lebih dari satu wajah terdeteksi. Video tidak dikirim untuk analisis. Maksimal lima snapshot peringatan dapat disimpan untuk seluruh sesi.</p>
                        </article>
                        <article class="daw-consent__fact">
                            <h3>Audio</h3>
                            <p>Mikrofon tidak diakses selama psikotes. Audio tidak direkam atau disimpan.</p>
                        </article>
                    </div>
                    <p class="daw-consent__note">Sistem tidak melakukan pencocokan identitas wajah atau analisis OCR. Snapshot dikirim hanya saat peringatan dan disimpan secara privat untuk tinjauan HR.</p>
                </section>

                <section class="daw-consent__section daw-consent__section--review" aria-labelledby="daw-consent-review-title">
                    <h2 id="daw-consent-review-title">Penggunaan dan perlindungan data</h2>
                    <ul class="daw-consent__list">
                        <li>Peringatan dapat dicatat saat wajah tidak terlihat beberapa saat, lebih dari satu wajah terdeteksi, Anda berpindah tab, atau kamera terputus. Deteksi dapat keliru; setiap peringatan ditinjau oleh manusia dan tidak otomatis menggagalkan tes.</li>
                        <li>Jika kamera tidak tersedia, Anda dapat meminta verifikasi identitas manual oleh HR.</li>
                        <li>Gambar hanya dapat diakses melalui akun WordPress Administrator, dan setiap akses dicatat. Gambar dihapus otomatis paling lambat satu tahun setelah tes selesai. Jika tes tidak selesai, masa simpan dihitung sejak persetujuan diberikan.</li>
                    </ul>
                </section>

                <form class="daw-consent__form" method="post" action="<?= esc_url( recruitment_get_public_url( 'psychotest-consent', $consent_arguments ) ) ?>">
                    <?php wp_nonce_field( 'recruitment_psychotest_consent' ); ?>
                    <input type="hidden" name="test" value="<?= esc_attr( $test_key ) ?>">
                    <label class="daw-consent__checkbox">
                        <input type="checkbox" name="consent" value="1" required>
                        <span>Saya telah membaca dan memahami informasi di atas, termasuk pemrosesan lokal wajah serta pengambilan dan penyimpanan foto identitas dan snapshot peringatan. Saya memahami bahwa audio tidak direkam dan verifikasi manual dapat diminta jika kamera tidak tersedia.</span>
                    </label>
                    <button class="daw-recruitment__button" type="submit">Setuju dan lanjutkan</button>
                </form>
            </section>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
