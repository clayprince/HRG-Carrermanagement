<?php
nocache_headers();
$view = ( new Recruitment_Pauli_Assessment() )->get_page_view();
$psychotest_home_url = recruitment_get_public_url( 'psychotest' );
?>
<div class="daw-recruitment daw-recruitment--psych-test daw-recruitment--pauli">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__psychotest-main">
        <div class="daw-recruitment__psychotest-shell daw-recruitment__psychotest-shell--assessment">
            <?php if ( ! empty( $view['complete'] ) ) : ?>
                <section class="daw-psych-test-card daw-pauli-assessment__message">
                    <div class="daw-psych-test-icon daw-psych-test-icon--success" aria-hidden="true">&#10003;</div>
                    <h1>Tes Pauli Selesai</h1>
                    <p>Tes 60 menit selesai. Anda menjawab <?= esc_html( (string) $view['total_answered'] ) ?> soal dan memperoleh <?= esc_html( (string) $view['score'] ) ?> jawaban benar. Hasil tersimpan untuk ditinjau oleh tim HR.</p>
                    <a class="daw-psych-test-button" href="<?= esc_url( $psychotest_home_url ) ?>">Kembali ke Daftar Tes</a>
                </section>
            <?php elseif ( ! empty( $view['error'] ) ) : ?>
                <section class="daw-psych-test-card daw-pauli-assessment__message">
                    <h1>Tes Pauli belum dapat dibuka</h1>
                    <p role="alert"><?= esc_html( $view['error'] ) ?></p>
                    <a class="daw-psych-test-button" href="<?= esc_url( $psychotest_home_url ) ?>">Kembali</a>
                </section>
            <?php elseif ( empty( $view['ready'] ) ) : ?>
                <section class="daw-psych-test-card daw-pauli-assessment__message">
                    <h1>Sesi tes Pauli belum dimulai</h1>
                    <p>Mulai dari halaman persiapan, lalu selesaikan Tes IQ terlebih dahulu. Jika bank soal Pauli belum tersedia, silakan hubungi tim HR.</p>
                    <a class="daw-psych-test-button" href="<?= esc_url( $psychotest_home_url ) ?>">Kembali ke Daftar Tes</a>
                </section>
            <?php else : ?>
                <?php
                $test = $view['test'];
                $number_sequence = implode( '', $test['numbers'] );
                ?>
                <section
                    class="daw-psych-test-card daw-pauli-assessment daw-pauli-live"
                    data-pauli-assessment
                    data-ajax-url="<?= esc_url( $view['ajax_url'] ) ?>"
                    data-save-nonce="<?= esc_attr( $view['nonce'] ) ?>"
                    data-segment="<?= esc_attr( (string) $test['segment'] ) ?>"
                    data-segment-deadline="<?= esc_attr( (string) $test['segment_deadline'] ) ?>"
                    data-deadline="<?= esc_attr( (string) $test['deadline'] ) ?>"
                    data-number-sequence="<?= esc_attr( $number_sequence ) ?>"
                    data-cursor="<?= esc_attr( (string) $test['cursor'] ) ?>"
                    data-correct="<?= esc_attr( (string) $test['correct'] ) ?>"
                    data-incorrect="<?= esc_attr( (string) $test['incorrect'] ) ?>"
                >
                    <header class="daw-pauli-assessment__header">
                        <div>
                            <span class="daw-recruitment__eyebrow daw-recruitment__eyebrow--red">TES PAULI · LAJUR <?= esc_html( (string) ( $test['segment'] + 1 ) ) ?> DARI <?= esc_html( (string) $test['segment_count'] ) ?></span>
                            <h1>Tes Koran Pauli</h1>
                            <p>Jumlahkan dua angka yang berurutan. Masukkan angka satuan hasilnya secepat dan setepat mungkin.</p>
                        </div>
                        <div class="daw-pauli-assessment__timers">
                            <div><span>PINDAH LAJUR</span><time data-pauli-segment-timer>--:--</time></div>
                            <div><span>SISA WAKTU</span><time data-pauli-total-timer>--:--</time></div>
                        </div>
                    </header>

                    <div class="daw-pauli-live__scoreboard" aria-label="Skor tes">
                        <div class="is-correct"><span>Benar</span><strong data-pauli-correct><?= esc_html( (string) $test['correct'] ) ?></strong></div>
                        <div class="is-incorrect"><span>Salah</span><strong data-pauli-incorrect><?= esc_html( (string) $test['incorrect'] ) ?></strong></div>
                        <div><span>Total</span><strong data-pauli-answered><?= esc_html( (string) $test['answered'] ) ?></strong></div>
                    </div>

                    <p class="daw-pauli-live__instruction"><strong>Contoh:</strong> 7 + 8 = 5. Hanya masukkan angka satuan. Lajur berganti otomatis setiap tiga menit.</p>
                    <p class="daw-pauli-assessment__save-status" role="status" data-pauli-save-status>Jawaban disimpan otomatis.</p>

                    <form class="daw-pauli-live__form" data-pauli-assessment-form autocomplete="off">
                        <input type="hidden" value="<?= esc_attr( $test['answers'] ) ?>" data-pauli-answer-payload>
                        <div class="daw-pauli-live__problem" aria-live="polite">
                            <div class="daw-pauli-live__previous" aria-label="Jawaban sebelumnya"><span data-pauli-previous><?= $test['cursor'] > 0 ? esc_html( $test['answers'][ $test['cursor'] - 1 ] ) : '—' ?></span></div>
                            <div class="daw-pauli-live__sum" aria-label="Penjumlahan saat ini">
                                <span data-pauli-left><?= esc_html( (string) ( $test['numbers'][ $test['cursor'] ] ?? '—' ) ) ?></span>
                                <span class="daw-pauli-live__plus" aria-hidden="true">+</span>
                                <span data-pauli-right><?= esc_html( (string) ( $test['numbers'][ $test['cursor'] + 1 ] ?? '—' ) ) ?></span>
                            </div>
                            <label class="daw-pauli-live__answer-label" for="daw-pauli-answer">Jawaban angka satuan</label>
                            <input id="daw-pauli-answer" class="daw-pauli-live__answer" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" readonly autofocus data-pauli-current-answer aria-label="Jawaban angka satuan">
                        </div>

                        <div class="daw-pauli-live__keypad" aria-label="Keypad angka">
                            <?php for ( $digit = 1; $digit <= 9; $digit++ ) : ?>
                                <button type="button" data-pauli-key="<?= esc_attr( (string) $digit ) ?>"><?= esc_html( (string) $digit ) ?></button>
                            <?php endfor; ?>
                            <button type="button" class="is-clear" data-pauli-clear aria-label="Hapus jawaban">&larr;</button>
                            <button type="button" data-pauli-key="0">0</button>
                            <button type="button" class="is-confirm" data-pauli-confirm aria-label="Konfirmasi jawaban">&check;</button>
                        </div>
                    </form>
                    <footer class="daw-pauli-live__footer">
                        <span>Lajur berikutnya terbuka otomatis saat aba-aba.</span>
                        <strong><?= esc_html( (string) ( $test['segment'] + 1 ) ) ?> / <?= esc_html( (string) $test['segment_count'] ) ?> lajur</strong>
                    </footer>
                </section>
            <?php endif; ?>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
