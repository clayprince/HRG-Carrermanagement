<?php
$test_key = sanitize_key( wp_unslash( $_GET['test'] ?? 'iq' ) );
$test_key = in_array( $test_key, [ 'iq', 'pauli', 'disc', 'papi' ], true ) ? $test_key : 'iq';
$proctoring = new Recruitment_Psychotest_Proctoring();
$session = $proctoring->get_current_session();
if ( $proctoring->get_current_session_error() ) {
    wp_die( esc_html__( 'Status persetujuan tidak dapat dibaca dari Oracle.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
}
if ( ! is_array( $session ) ) {
    wp_die( esc_html__( 'Persetujuan pengawasan belum tersimpan. Kembali ke halaman persetujuan dan coba lagi.', 'recruitment-plugin' ), '', [ 'response' => 403 ] );
}
$identity_status = (string) ( $session['identity_status'] ?? '' );
$identity_processed = in_array( $identity_status, [ 'CAPTURED', 'MANUAL_REVIEW', 'MANUAL_VERIFIED' ], true );
$test_labels = [ 'iq' => 'IQ', 'pauli' => 'Pauli', 'disc' => 'DISC', 'papi' => 'PAPI Kostick' ];
?>
<div class="daw-recruitment daw-recruitment--psychotest-identity">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__psychotest-main">
        <div class="daw-recruitment__psychotest-shell daw-recruitment__psychotest-shell--narrow">
            <section class="daw-recruitment__psychotest-card">
                <h1>Foto Sebelum Psikotes</h1>
                <?php if ( 'MANUAL_REVIEW' === $identity_status ) : ?>
                    <p role="status">Kamera tidak digunakan. Tim HR akan memeriksa identitas Anda secara manual; pilihan ini tidak otomatis menggagalkan tes.</p>
                <?php elseif ( 'MANUAL_VERIFIED' === $identity_status ) : ?>
                    <p role="status">Tim HR telah menyelesaikan verifikasi identitas manual Anda.</p>
                <?php elseif ( 'CAPTURED' === $identity_status ) : ?>
                    <p role="status">Foto sudah tersimpan. Anda dapat melanjutkan ke tes.</p>
                <?php else : ?>
                    <p>Aktifkan kamera dan ambil satu foto. Foto bebas; setelah foto berhasil disimpan, Anda akan langsung lanjut ke tes.</p>
                    <div class="daw-proctoring-capture" data-proctoring-identity>
                        <video data-proctoring-identity-video autoplay muted playsinline hidden></video>
                        <canvas data-proctoring-identity-canvas hidden></canvas>
                        <p role="status" data-proctoring-identity-status>Aktifkan kamera untuk mengambil foto.</p>
                        <button type="button" class="daw-recruitment__button" data-proctoring-identity-start>Aktifkan Kamera</button>
                        <button type="button" class="daw-recruitment__button" data-proctoring-identity-capture disabled>Ambil Foto</button>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" enctype="multipart/form-data" data-proctoring-identity-form>
                    <?php wp_nonce_field( 'daw_proctoring_identity' ); ?>
                    <input type="hidden" name="action" value="daw_proctoring_identity">
                    <input type="hidden" name="test" value="<?= esc_attr( $test_key ) ?>">
                    <input type="hidden" name="proctoring_action" value="<?= esc_attr( $identity_processed ? 'continue' : 'manual' ) ?>" data-proctoring-identity-action>
                    <?php if ( $identity_processed ) : ?>
                        <button class="daw-recruitment__button daw-recruitment__button--full" type="submit">Lanjut ke Tes <?= esc_html( $test_labels[ $test_key ] ) ?></button>
                    <?php else : ?>
                        <button class="daw-recruitment__button daw-recruitment__button--ghost" type="submit" data-proctoring-identity-manual>Minta Verifikasi Manual</button>
                        <p class="description">Gunakan jalur ini bila kamera atau koneksi tidak tersedia. Tes tetap memerlukan pemeriksaan identitas manual oleh HR.</p>
                    <?php endif; ?>
                    <p role="alert" data-proctoring-identity-error hidden></p>
                </form>
                <p class="description">Satu foto disimpan secara privat selama maksimal satu tahun setelah tes selesai. Pemeriksaan mikrofon saat tes hanya mencatat status perangkat; audio tidak direkam.</p>
            </section>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
