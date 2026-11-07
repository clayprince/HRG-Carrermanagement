<?php
$test_key = in_array( sanitize_key( wp_unslash( $_GET['test'] ?? 'disc' ) ), [ 'disc', 'papi' ], true )
    ? sanitize_key( wp_unslash( $_GET['test'] ?? 'disc' ) )
    : 'disc';
$view = ( new Recruitment_Personality_Assessment() )->get_page_view();
$psychotest_home_url = recruitment_get_public_url( 'psychotest' );
$preparation_url = recruitment_get_public_url( 'psychotest-preparation', [ 'test' => $test_key ] );
$next_preparation_url = recruitment_get_public_url( 'psychotest-preparation', [ 'test' => 'papi' ] );
?>
<div class="daw-recruitment daw-recruitment--psych-test daw-recruitment--personality">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__psychotest-main">
        <div class="daw-recruitment__psychotest-shell daw-recruitment__psychotest-shell--narrow">
            <?php if ( ! empty( $view['error'] ) ) : ?>
                <section class="daw-psych-test-card">
                    <h1>Tes Kepribadian belum tersedia</h1>
                    <p role="alert"><?= esc_html( $view['error'] ) ?></p>
                    <a class="daw-psych-test-button" href="<?= esc_url( $psychotest_home_url ) ?>">Kembali ke daftar tes</a>
                </section>
            <?php elseif ( ! empty( $view['complete'] ) ) : ?>
                <section class="daw-psych-test-card daw-psych-test-card--complete">
                    <div class="daw-psych-test-icon daw-psych-test-icon--success" aria-hidden="true">&#10003;</div>
                    <h1>Tes <?= esc_html( $view['title'] ) ?> selesai</h1>
                    <p>Jawaban Anda sudah disimpan. Tes ini tidak dibatasi waktu; standar waktu hanya menjadi pengingat.</p>
                    <?php if ( 'disc' === $test_key ) : ?>
                        <a class="daw-psych-test-button" href="<?= esc_url( $next_preparation_url ) ?>">Lanjut ke PAPI Kostick</a>
                    <?php else : ?>
                        <p>Seluruh tes dalam kelompok Kepribadian telah selesai.</p>
                        <a class="daw-psych-test-button" href="<?= esc_url( $psychotest_home_url ) ?>">Kembali ke daftar tes</a>
                    <?php endif; ?>
                </section>
            <?php elseif ( empty( $view['ready'] ) ) : ?>
                <section class="daw-psych-test-card">
                    <h1>Sesi tes <?= esc_html( 'disc' === $test_key ? 'DISC' : 'PAPI Kostick' ) ?> belum dimulai</h1>
                    <?php if ( 'papi' === $test_key ) : ?>
                        <p>Selesaikan DISC terlebih dahulu. Tidak ada batas waktu pengerjaan.</p>
                    <?php else : ?>
                        <p>Pastikan Anda sudah menyelesaikan Tes IQ dan Pauli sebelum memulai tes Kepribadian.</p>
                    <?php endif; ?>
                    <a class="daw-psych-test-button" href="<?= esc_url( $psychotest_home_url ) ?>">Kembali ke daftar tes</a>
                </section>
            <?php else : ?>
                <section
                    class="daw-psych-test-card daw-personality-assessment"
                    data-personality-assessment
                    data-test-key="<?= esc_attr( $view['test_key'] ) ?>"
                    data-reminder-minutes="<?= esc_attr( (string) $view['duration'] ) ?>"
                    data-started-at="<?= esc_attr( (string) $view['started_at'] ) ?>"
                    data-ajax-url="<?= esc_url( $view['ajax_url'] ) ?>"
                    data-save-nonce="<?= esc_attr( $view['nonce'] ) ?>"
                >
                    <header class="daw-personality-assessment__header">
                        <div>
                            <span class="daw-recruitment__eyebrow daw-recruitment__eyebrow--red">TES KEPRIBADIAN · <?= esc_html( strtoupper( $view['title'] ) ) ?></span>
                            <h1><?= esc_html( $view['title'] ) ?></h1>
                            <p><?= esc_html( $view['instructions'] ) ?></p>
                        </div>
                        <div class="daw-personality-assessment__timer">
                            <span>WAKTU BERJALAN</span>
                            <time data-personality-elapsed aria-live="off">00:00</time>
                            <small>Tanpa batas waktu</small>
                        </div>
                    </header>

                    <div class="daw-personality-assessment__progress">
                        <span><strong data-personality-progress><?= esc_html( (string) $view['answered_count'] ) ?></strong> / <?= esc_html( (string) $view['question_count'] ) ?> soal terjawab</span>
                        <span>Autosave aktif</span>
                    </div>
                    <p class="daw-personality-assessment__reminder" data-personality-reminder role="status" hidden></p>
                    <p class="daw-personality-assessment__save-status" data-personality-save-status role="status">Jawaban disimpan otomatis. Anda dapat melanjutkan tanpa batas waktu.</p>

                    <form method="post" action="<?= esc_url( recruitment_get_public_url( 'psych-personality', [ 'test' => $view['test_key'] ] ) ) ?>" data-personality-form>
                        <?php wp_nonce_field( 'recruitment_personality_complete_' . $view['test_key'] ); ?>
                        <input type="hidden" name="personality_action" value="complete">
                        <div class="daw-personality-assessment__questions">
                            <?php foreach ( $view['questions'] as $question ) :
                                $question_id = (int) $question['id'];
                                $answer = (array) $question['answer'];
                                ?>
                                <?php if ( 'disc' === $view['test_key'] ) : ?>
                                    <fieldset class="daw-personality-assessment__question daw-personality-assessment__question--disc" data-personality-question="<?= esc_attr( (string) $question_id ) ?>">
                                        <legend><strong>Nomor <?= esc_html( (string) $question['number'] ) ?></strong><span>Pilih satu yang paling menggambarkan dan satu yang paling tidak menggambarkan diri Anda.</span></legend>
                                        <div class="daw-personality-assessment__disc-head" aria-hidden="true"><span>Pernyataan</span><span>Paling menggambarkan</span><span>Paling tidak</span></div>
                                        <?php foreach ( $question['items'] as $item_index => $item ) : ?>
                                            <div class="daw-personality-assessment__disc-row">
                                                <span><?= esc_html( $item ) ?></span>
                                                <label aria-label="Paling menggambarkan: <?= esc_attr( $item ) ?>"><input type="radio" name="answers[<?= esc_attr( (string) $question_id ) ?>][most]" value="<?= esc_attr( (string) $item_index ) ?>" data-personality-answer data-question-id="<?= esc_attr( (string) $question_id ) ?>" data-answer-part="most" required <?= checked( (string) ( $answer['most'] ?? '' ), (string) $item_index, false ) ?>><span class="screen-reader-text">Paling menggambarkan</span></label>
                                                <label aria-label="Paling tidak menggambarkan: <?= esc_attr( $item ) ?>"><input type="radio" name="answers[<?= esc_attr( (string) $question_id ) ?>][least]" value="<?= esc_attr( (string) $item_index ) ?>" data-personality-answer data-question-id="<?= esc_attr( (string) $question_id ) ?>" data-answer-part="least" required <?= checked( (string) ( $answer['least'] ?? '' ), (string) $item_index, false ) ?>><span class="screen-reader-text">Paling tidak menggambarkan</span></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </fieldset>
                                <?php else : ?>
                                    <fieldset class="daw-personality-assessment__question daw-personality-assessment__question--papi" data-personality-question="<?= esc_attr( (string) $question_id ) ?>">
                                        <legend><strong>Nomor <?= esc_html( (string) $question['number'] ) ?></strong><span>Pilih satu pernyataan yang paling mencerminkan diri Anda.</span></legend>
                                        <div class="daw-personality-assessment__pairs">
                                            <?php foreach ( $question['items'] as $item_index => $item ) : ?>
                                                <label class="daw-personality-assessment__pair">
                                                    <input type="radio" name="answers[<?= esc_attr( (string) $question_id ) ?>][choice]" value="<?= esc_attr( (string) $item_index ) ?>" data-personality-answer data-question-id="<?= esc_attr( (string) $question_id ) ?>" data-answer-part="choice" required <?= checked( (string) ( $answer['choice'] ?? '' ), (string) $item_index, false ) ?>>
                                                    <span><?= esc_html( $item ) ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <footer class="daw-personality-assessment__footer">
                            <a class="daw-recruitment__button daw-recruitment__button--ghost" href="<?= esc_url( $psychotest_home_url ) ?>">Simpan dan lanjutkan nanti</a>
                            <button type="submit" class="daw-recruitment__button" data-personality-complete>Selesaikan Tes <?= esc_html( $view['title'] ) ?></button>
                        </footer>
                    </form>
                </section>
            <?php endif; ?>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
