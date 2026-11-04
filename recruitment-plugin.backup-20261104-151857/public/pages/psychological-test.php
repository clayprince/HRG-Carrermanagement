<?php
$state = sanitize_key( $_GET['state'] ?? 'review' );
$state = in_array( $state, [ 'review', 'processing', 'complete' ], true ) ? $state : 'review';
$preview_args = [ 'daw_ui_preview' => '1' ];
$processing_url = recruitment_get_public_url( 'psych-test', [ 'state' => 'processing' ] + $preview_args );
$complete_url = recruitment_get_public_url( 'psych-test', [ 'state' => 'complete' ] + $preview_args );
?>
<div class="daw-recruitment daw-recruitment--psych-test daw-recruitment--psych-test-<?= esc_attr( $state ) ?>">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__psych-test-main">
        <?php if ( 'review' === $state ) : ?>
            <section class="daw-psych-test-card daw-psych-test-card--review">
                <div class="daw-psych-test-icon daw-psych-test-icon--success" aria-hidden="true">&#10003;</div>
                <h1>Seluruh Tes Selesai</h1>
                <p>Semua rangkaian tes psikologi telah diselesaikan. Periksa kembali sebelum mengirim hasil.</p>
                <div class="daw-psych-test-progress-heading"><strong>RANGKUMAN TES</strong><span>5 / 5 Selesai</span></div>
                <div class="daw-psych-test-progress"><span></span></div>
                <div class="daw-psych-test-list">
                    <div><i>&#10003;</i><strong>IQ</strong><span>Tes Kemampuan Intelektual</span><b>Selesai</b></div>
                    <div><i>&#10003;</i><strong>Pauli</strong><span>Tes Ketelitian dan Kecepatan Kerja</span><b>Selesai</b></div>
                    <div><i>&#10003;</i><strong>Kepribadian</strong><span>Asesmen Karakteristik Individu</span><b>Selesai</b></div>
                    <div><i>&#10003;</i><strong>DISC</strong><span>Asesmen Perilaku</span><b>Selesai</b></div>
                    <div><i>&#10003;</i><strong>PAPI Kostick</strong><span>Asesmen Preferensi dalam Lingkungan Kerja</span><b>Selesai</b></div>
                </div>
                <div class="daw-psych-test-warning"><span aria-hidden="true">&#9888;</span><p>Pastikan seluruh rangkaian tes telah Anda selesaikan sebelum mengirim hasil. Setelah dikirim, jawaban tidak dapat diubah kembali.</p></div>
                <a class="daw-psych-test-button" href="<?= esc_url( $processing_url ) ?>">Kirim Hasil</a>
                <a class="daw-psych-test-secondary" href="<?= esc_url( recruitment_get_public_url( 'application-process' ) ) ?>">Kembali ke Proses Lamaran</a>
            </section>
        <?php elseif ( 'processing' === $state ) : ?>
            <section class="daw-psych-test-card daw-psych-test-card--processing" data-psych-processing data-next-url="<?= esc_url( $complete_url ) ?>">
                <div class="daw-psych-test-processing-icon" aria-hidden="true"><span>&#9636;</span></div>
                <h1>Memproses Hasil Tes</h1>
                <p>Data Anda sedang diproses oleh sistem. Mohon<br>tunggu sebentar.</p>
                <div class="daw-psych-test-processing-bar" role="progressbar" aria-label="Progress memproses hasil" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span data-psych-progress-bar></span></div>
                <small data-psych-progress-value>0%</small>
            </section>
        <?php else : ?>
            <section class="daw-psych-test-card daw-psych-test-card--complete">
                <div class="daw-psych-test-icon daw-psych-test-icon--success" aria-hidden="true">&#10003;</div>
                <h1>Tes Psikologi Berhasil<br>Diselesaikan</h1>
                <p>Seluruh rangkaian tes telah berhasil dikirim.</p>
                <div class="daw-psych-test-complete-list">
                    <strong>Tes yang telah diselesaikan:</strong>
                    <span><i>&#10003;</i>IQ</span>
                    <span><i>&#10003;</i>Pauli</span>
                    <span><i>&#10003;</i>Kepribadian</span>
                    <span><i>&#10003;</i>DISC</span>
                    <span><i>&#10003;</i>PAPI Kostick</span>
                </div>
                <div class="daw-psych-test-next-notice"><strong>SELANJUTNYA</strong><p>Tim HR akan melakukan proses validasi dan memberikan informasi mengenai tahapan berikutnya melalui akun pelamar.</p></div>
                <a class="daw-psych-test-button" href="<?= esc_url( recruitment_get_public_url( 'application-process' ) ) ?>">Kembali ke Proses Lamaran</a>
            </section>
        <?php endif; ?>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>