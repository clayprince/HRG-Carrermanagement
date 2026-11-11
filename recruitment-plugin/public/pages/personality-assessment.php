<?php
$test_key = in_array( sanitize_key( wp_unslash( $_GET['test'] ?? 'disc' ) ), [ 'disc', 'papi' ], true )
    ? sanitize_key( wp_unslash( $_GET['test'] ?? 'disc' ) )
    : 'disc';
$view = ( new Recruitment_Personality_Assessment() )->get_page_view();
$psychotest_home_url = recruitment_get_public_url( 'psychotest' );
$preparation_url = recruitment_get_public_url( 'psychotest-preparation', [ 'test' => $test_key ] );
$next_preparation_url = recruitment_get_public_url( 'psychotest-preparation', [ 'test' => 'papi' ] );
$test_ready = ! empty( $view['ready'] ) && empty( $view['error'] );
$questions = (array) ( $view['questions'] ?? [] );
$question_count = count( $questions );
$initial_question_index = 0;
foreach ( $questions as $question_index => $question ) {
    $answer = (array) ( $question['answer'] ?? [] );
    $answered = 'disc' === $test_key
        ? isset( $answer['most'], $answer['least'] ) && (string) $answer['most'] !== (string) $answer['least']
        : isset( $answer['choice'] );
    if ( ! $answered ) {
        $initial_question_index = (int) $question_index;
        break;
    }
    $initial_question_index = (int) $question_index;
}
?>
<div class="daw-recruitment daw-recruitment--psych-test daw-recruitment--personality<?= $test_ready ? ' daw-recruitment--exam-active' : '' ?>">
    <?php if ( $test_ready ) : ?>
        <header class="daw-exam-bar">
            <a class="daw-exam-bar__brand" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>" aria-label="DAW Recruitment">
                <span>D</span>
                <span>DAW Recruitment<small><?= esc_html( $view['title'] ) ?></small></span>
            </a>
            <span class="daw-exam-bar__stage">Tes Kepribadian</span>
            <div class="daw-exam-bar__details">
                <span class="daw-exam-bar__timer"><span aria-hidden="true">&#9711;</span> <time data-personality-elapsed aria-live="off">00:00</time></span>
                <span data-exam-question-counter>1 / <?= esc_html( (string) $question_count ) ?></span>
            </div>
        </header>
    <?php else : ?>
        <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <?php endif; ?>
    <main class="daw-recruitment__psychotest-main">
        <div class="daw-recruitment__psychotest-shell daw-recruitment__psychotest-shell--narrow<?= $test_ready ? ' daw-exam-shell' : '' ?>">
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
                <div class="daw-exam-layout">
                    <div class="daw-exam-primary">
                        <section
                            class="daw-psych-test-card daw-personality-assessment"
                            data-personality-assessment
                            data-test-key="<?= esc_attr( $view['test_key'] ) ?>"
                            data-reminder-minutes="<?= esc_attr( (string) $view['duration'] ) ?>"
                            data-started-at="<?= esc_attr( (string) $view['started_at'] ) ?>"
                            data-ajax-url="<?= esc_url( $view['ajax_url'] ) ?>"
                            data-save-nonce="<?= esc_attr( $view['nonce'] ) ?>"
                            data-current-index="<?= esc_attr( (string) $initial_question_index ) ?>"
                        >
                            <div class="daw-exam-card__eyebrow">
                                <span><i aria-hidden="true"></i> <?= esc_html( 'disc' === $view['test_key'] ? 'Tes Kepribadian' : 'Tes PAPI Kostick' ) ?></span>
                                <p class="daw-personality-assessment__save-status" data-personality-save-status role="status">Jawaban disimpan otomatis.</p>
                            </div>
                            <p class="daw-exam-card__instructions"><?= esc_html( $view['instructions'] ) ?></p>
                            <p class="daw-personality-assessment__reminder" data-personality-reminder role="status" hidden></p>

                            <form method="post" action="<?= esc_url( recruitment_get_public_url( 'psych-personality', [ 'test' => $view['test_key'] ] ) ) ?>" data-personality-form>
                                <?php wp_nonce_field( 'recruitment_personality_complete_' . $view['test_key'] ); ?>
                                <input type="hidden" name="personality_action" value="complete">
                                <div class="daw-personality-assessment__questions">
                                    <?php foreach ( $questions as $question_index => $question ) :
                                        $question_id = (int) $question['id'];
                                        $answer = (array) $question['answer'];
                                        ?>
                                        <?php if ( 'disc' === $view['test_key'] ) : ?>
                                            <fieldset class="daw-personality-assessment__question daw-personality-assessment__question--disc daw-exam-question" data-personality-question="<?= esc_attr( (string) $question_id ) ?>" data-question-index="<?= esc_attr( (string) $question_index ) ?>" <?= $question_index !== $initial_question_index ? 'hidden' : '' ?>>
                                                <legend><span class="daw-exam-question__number">PERTANYAAN <?= esc_html( (string) ( $question_index + 1 ) ) ?> DARI <?= esc_html( (string) $question_count ) ?></span><strong>Pilih yang paling dan paling tidak menggambarkan diri Anda.</strong></legend>
                                                <div class="daw-personality-assessment__disc-head" aria-hidden="true"><span>Pernyataan</span><span>P · Paling menggambarkan</span><span>K · Paling tidak</span></div>
                                                <?php foreach ( $question['items'] as $item_index => $item ) : ?>
                                                    <div class="daw-personality-assessment__disc-row">
                                                        <span><?= esc_html( $item ) ?></span>
                                                        <label aria-label="Paling menggambarkan: <?= esc_attr( $item ) ?>"><input type="radio" name="answers[<?= esc_attr( (string) $question_id ) ?>][most]" value="<?= esc_attr( (string) $item_index ) ?>" data-personality-answer data-answer-part="most" <?= checked( (string) ( $answer['most'] ?? '' ), (string) $item_index, false ) ?>><span class="screen-reader-text">Paling menggambarkan</span></label>
                                                        <label aria-label="Paling tidak menggambarkan: <?= esc_attr( $item ) ?>"><input type="radio" name="answers[<?= esc_attr( (string) $question_id ) ?>][least]" value="<?= esc_attr( (string) $item_index ) ?>" data-personality-answer data-answer-part="least" <?= checked( (string) ( $answer['least'] ?? '' ), (string) $item_index, false ) ?>><span class="screen-reader-text">Paling tidak menggambarkan</span></label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </fieldset>
                                        <?php else : ?>
                                            <fieldset class="daw-personality-assessment__question daw-personality-assessment__question--papi daw-exam-question" data-personality-question="<?= esc_attr( (string) $question_id ) ?>" data-question-index="<?= esc_attr( (string) $question_index ) ?>" <?= $question_index !== $initial_question_index ? 'hidden' : '' ?>>
                                                <legend><span class="daw-exam-question__number">PERTANYAAN <?= esc_html( (string) ( $question_index + 1 ) ) ?> DARI <?= esc_html( (string) $question_count ) ?></span><strong>Pilih pernyataan yang paling mencerminkan diri Anda.</strong></legend>
                                                <div class="daw-personality-assessment__pairs">
                                                    <?php foreach ( $question['items'] as $item_index => $item ) : ?>
                                                        <label class="daw-personality-assessment__pair">
                                                            <input type="radio" name="answers[<?= esc_attr( (string) $question_id ) ?>][choice]" value="<?= esc_attr( (string) $item_index ) ?>" data-personality-answer data-answer-part="choice" <?= checked( (string) ( $answer['choice'] ?? '' ), (string) $item_index, false ) ?>>
                                                            <span><?= esc_html( $item ) ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </fieldset>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <div class="daw-exam-controls">
                                    <button type="button" class="daw-exam-controls__previous" data-exam-previous disabled>&larr; Sebelumnya</button>
                                    <button type="button" class="daw-recruitment__button" data-exam-next>Berikutnya &rarr;</button>
                                    <button type="submit" class="daw-recruitment__button" data-exam-finish hidden>Selesaikan Tes &rarr;</button>
                                </div>
                            </form>
                            <nav class="daw-exam-navigator" aria-label="Navigator soal">
                                <div class="daw-exam-navigator__heading">
                                    <strong>Navigator Soal</strong>
                                    <span><span data-exam-answered-count><?= esc_html( (string) $view['answered_count'] ) ?></span> dari <?= esc_html( (string) $question_count ) ?> terjawab</span>
                                </div>
                                <div class="daw-exam-navigator__buttons">
                                    <?php foreach ( $questions as $question_index => $question ) :
                                        $answer = (array) ( $question['answer'] ?? [] );
                                        $answered = 'disc' === $view['test_key']
                                            ? isset( $answer['most'], $answer['least'] ) && (string) $answer['most'] !== (string) $answer['least']
                                            : isset( $answer['choice'] );
                                        ?>
                                        <button type="button" data-exam-question-nav="<?= esc_attr( (string) $question_index ) ?>" aria-label="Buka soal <?= esc_attr( (string) ( $question_index + 1 ) ) ?>"<?= $answered ? ' class="is-answered"' : '' ?>><?= esc_html( (string) ( $question_index + 1 ) ) ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </nav>
                        </section>
                    </div>
                    <?php require recruitment_get_plugin_path( 'public/components/psychotest-camera-placeholder.php' ); ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
