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
                    <p>Seluruh 20 kolom telah ditutup dan hasil tes Anda tersimpan untuk ditinjau oleh tim HR.</p>
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
                $numbers = $test['numbers'];
                $answers = $test['answers'];
                ?>
                <section
                    class="daw-psych-test-card daw-pauli-assessment"
                    data-pauli-assessment
                    data-ajax-url="<?= esc_url( $view['ajax_url'] ) ?>"
                    data-save-nonce="<?= esc_attr( $view['nonce'] ) ?>"
                    data-segment="<?= esc_attr( (string) $test['segment'] ) ?>"
                    data-segment-deadline="<?= esc_attr( (string) $test['segment_deadline'] ) ?>"
                    data-deadline="<?= esc_attr( (string) $test['deadline'] ) ?>"
                >
                    <header class="daw-pauli-assessment__header">
                        <div>
                            <span class="daw-recruitment__eyebrow daw-recruitment__eyebrow--red">TES PAULI · KOLOM <?= esc_html( (string) ( $test['segment'] + 1 ) ) ?> DARI <?= esc_html( (string) $test['segment_count'] ) ?></span>
                            <h1><?= esc_html( $test['title'] ) ?></h1>
                            <p>Jumlahkan dua angka yang berurutan dari atas ke bawah. Masukkan hanya angka satuan hasil penjumlahan.</p>
                        </div>
                        <div class="daw-pauli-assessment__timers">
                            <div><span>GARIS DALAM</span><time data-pauli-segment-timer>--:--</time></div>
                            <div><span>TOTAL TES</span><time data-pauli-total-timer>--:--</time></div>
                        </div>
                    </header>

                    <ol class="daw-pauli-assessment__progress" aria-label="Progres kolom tes Pauli">
                        <?php for ( $step = 0; $step < $test['segment_count']; $step++ ) :
                            $step_class = $step < $test['segment'] ? 'is-complete' : ( $step === $test['segment'] ? 'is-current' : '' );
                            ?>
                            <li class="<?= esc_attr( $step_class ) ?>" <?= $step === $test['segment'] ? 'aria-current="step"' : '' ?>>
                                <span><?= esc_html( (string) ( $step + 1 ) ) ?></span>
                            </li>
                        <?php endfor; ?>
                    </ol>

                    <p class="daw-pauli-assessment__instruction"><strong>Contoh:</strong> 7 + 8 = 5 (tulis angka satuannya). Saat aba-aba garis muncul, kolom ini dikunci dan kolom berikutnya dibuka. Anda tidak dapat kembali ke kolom sebelumnya.</p>
                    <p class="daw-pauli-assessment__save-status" role="status" data-pauli-save-status>Jawaban disimpan otomatis. Jangan tutup halaman tes.</p>

                    <form method="post" action="<?= esc_url( recruitment_get_public_url( 'psych-pauli' ) ) ?>" data-pauli-assessment-form>
                        <?php wp_nonce_field( 'recruitment_pauli_advance' ); ?>
                        <input type="hidden" name="pauli_action" value="advance">
                        <input type="hidden" name="segment" value="<?= esc_attr( (string) $test['segment'] ) ?>">
                        <input type="hidden" name="answers" value="<?= esc_attr( $answers ) ?>" data-pauli-answer-payload>
                        <div class="daw-pauli-assessment__sheet" aria-label="Lembar angka Pauli">
                            <?php foreach ( $numbers as $index => $number ) : ?>
                                <label class="daw-pauli-assessment__row">
                                    <span class="daw-pauli-assessment__number" aria-label="Angka <?= esc_attr( (string) ( $index + 1 ) ) ?>"><?= esc_html( (string) $number ) ?></span>
                                    <?php if ( $index < count( $numbers ) - 1 ) :
                                        $answer = '-' === $answers[ $index ] ? '' : $answers[ $index ];
                                        ?>
                                        <input
                                            type="text"
                                            inputmode="numeric"
                                            pattern="[0-9]"
                                            maxlength="1"
                                            autocomplete="off"
                                            aria-label="Jawaban penjumlahan baris <?= esc_attr( (string) ( $index + 1 ) ) ?>"
                                            value="<?= esc_attr( $answer ) ?>"
                                            data-pauli-answer
                                            data-answer-index="<?= esc_attr( (string) $index ) ?>"
                                        >
                                    <?php else : ?>
                                        <span class="daw-pauli-assessment__end" aria-hidden="true">&bull;</span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="screen-reader-text" data-pauli-advance>Garis dan lanjutkan</button>
                    </form>

                    <footer class="daw-pauli-assessment__footer">
                        <span>Kolom terdahulu terkunci setelah aba-aba.</span>
                        <strong><?= esc_html( (string) ( $test['segment'] + 1 ) ) ?> / <?= esc_html( (string) $test['segment_count'] ) ?></strong>
                    </footer>
                </section>
            <?php endif; ?>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
