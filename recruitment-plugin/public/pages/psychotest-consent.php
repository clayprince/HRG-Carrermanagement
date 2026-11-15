<?php
$test_key = sanitize_key( wp_unslash( $_GET['test'] ?? 'iq' ) );
$test_labels = [
    'iq' => 'IQ',
    'pauli' => 'Pauli',
    'disc' => 'DISC',
    'papi' => 'PAPI Kostick',
];
$requested_application_id = isset( $_GET['application_id'] ) && is_scalar( $_GET['application_id'] )
    ? absint( wp_unslash( (string) $_GET['application_id'] ) )
    : 0;
if ( 'iq' !== $test_key || $requested_application_id < 1 ) {
    wp_safe_redirect( recruitment_get_public_url( 'psychotest' ) );
    exit;
}
$stage_status = recruitment_applicant_psychotest_stage_status( (string) $requested_application_id );
if ( null === $stage_status ) {
    wp_die( esc_html__( 'Status lamaran tidak dapat diverifikasi. Silakan coba kembali nanti.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
}
if ( ! $stage_status ) {
    wp_die( esc_html__( 'Persetujuan hanya tersedia untuk lamaran yang sedang berada pada tahap psikotes.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
}
$proctoring = new Recruitment_Psychotest_Proctoring();
$proctoring_violation_config = $proctoring->get_violation_config();
$violation_threshold_label = static function ( array $config, string $event_type ): string {
    if ( empty( $config[ $event_type ]['enabled'] ) ) {
        return 'tidak aktif';
    }
    return number_format_i18n( (float) $config[ $event_type ]['threshold_ms'] / 1000, 1 ) . ' detik';
};
if ( $proctoring->has_current_consent( $requested_application_id ) ) {
    wp_safe_redirect( recruitment_get_public_url( 'psychotest', [ 'application_id' => $requested_application_id ] ) );
    exit;
}
if ( $proctoring->get_current_session_error() ) {
    wp_die( esc_html__( 'Status persiapan tidak dapat dibaca dari Oracle. Silakan coba kembali nanti.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
}
$consent_arguments = [ 'test' => $test_key ];
$consent_arguments['application_id'] = $requested_application_id;
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
                            <h3>Log aktivitas</h3>
                            <p>Browser (User-Agent) dan alamat IP yang terlihat oleh server dicatat bersama aktivitas pengawasan untuk laporan seleksi. Jika koneksi melewati proxy atau gateway, alamat yang tercatat dapat merupakan alamat proxy atau gateway tersebut.</p>
                        </article>
                        <article class="daw-consent__fact">
                            <h3>Pemeriksaan kamera</h3>
                            <p>Browser menganalisis gambar kamera secara lokal. Event baru dicatat setelah indikasi berlangsung sesuai threshold: wajah tidak terlihat <?= esc_html( $violation_threshold_label( $proctoring_violation_config, 'FACE_NOT_DETECTED' ) ) ?>, lebih dari satu wajah <?= esc_html( $violation_threshold_label( $proctoring_violation_config, 'MULTIPLE_FACES' ) ) ?>, arah wajah menyamping <?= esc_html( $violation_threshold_label( $proctoring_violation_config, 'FACE_TURNED_AWAY' ) ) ?>, atau kamera terputus <?= esc_html( $violation_threshold_label( $proctoring_violation_config, 'CAMERA_STOPPED' ) ) ?>. Jika frame tersedia, snapshot diambil saat threshold terpenuhi; kamera terputus tidak memiliki snapshot. Video utuh tidak dikirim atau disimpan.</p>
                        </article>
                        <article class="daw-consent__fact">
                            <h3>Audio</h3>
                            <p>Mikrofon tidak diakses selama psikotes. Audio tidak direkam atau disimpan.</p>
                        </article>
                    </div>
                    <p class="daw-consent__note">Deteksi dapat keliru dan pemulihan menunggu perilaku normal yang konsisten. Peringatan ditampilkan saat threshold terpenuhi; event ditandai untuk tinjauan manusia dan tidak otomatis menyimpulkan kecurangan atau menggagalkan tes. Arah wajah hanya perkiraan lokal; sistem tidak mencocokkan identitas wajah, membaca KTP dengan OCR, atau mengirim video untuk analisis.</p>
                </section>

                <section class="daw-consent__section daw-consent__section--review" aria-labelledby="daw-consent-review-title">
                    <h2 id="daw-consent-review-title">Penggunaan dan perlindungan data</h2>
                    <ul class="daw-consent__list">
                        <li>Peringatan dapat dicatat saat wajah tidak terlihat, lebih dari satu wajah terdeteksi, wajah menghadap menyamping cukup lama, halaman ujian disembunyikan/dibuka kembali, atau kamera terputus. Browser tidak mengungkap tab atau situs mana yang dibuka. HR dapat mengonfirmasi atau mengesampingkan event setelah meninjau bukti dan konteks.</li>
                        <li>Jika kamera tidak tersedia, Anda dapat meminta verifikasi identitas manual oleh HR.</li>
                        <li>Gambar dan laporan hanya dapat diakses HR berwenang, dan akses dicatat. Gambar serta log aktivitas dihapus otomatis paling lambat satu tahun setelah tes selesai. Jika tes tidak selesai, masa simpan dihitung sejak persetujuan diberikan.</li>
                    </ul>
                </section>

                <form class="daw-consent__form" method="post" action="<?= esc_url( recruitment_get_public_url( 'psychotest-consent', $consent_arguments ) ) ?>">
                    <?php wp_nonce_field( 'recruitment_psychotest_consent' ); ?>
                    <input type="hidden" name="test" value="<?= esc_attr( $test_key ) ?>">
                    <label class="daw-consent__checkbox">
                        <input type="checkbox" name="consent" value="1" required>
                        <span>Saya telah membaca dan memahami informasi di atas, termasuk pencatatan browser dan alamat IP, pemrosesan lokal arah/jumlah wajah, pencatatan saat halaman ujian disembunyikan, peringatan berbasis durasi, serta penyimpanan snapshot jika frame tersedia. Saya memahami bahwa audio tidak direkam, event memerlukan tinjauan manusia, dan verifikasi manual dapat diminta jika kamera tidak tersedia.</span>
                    </label>
                    <button class="daw-recruitment__button" type="submit">Setuju dan lanjutkan</button>
                </form>
            </section>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
