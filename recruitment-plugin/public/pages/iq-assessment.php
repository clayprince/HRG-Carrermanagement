<?php
$view = ( new Recruitment_IQ_Assessment() )->get_page_view();
$preparation_url = recruitment_get_public_url( 'psychotest-preparation', [ 'test' => 'iq' ] );
$psychotest_home_url = recruitment_get_public_url( 'psychotest' );
$test_ready = ! empty( $view['ready'] ) && empty( $view['error'] );
$questions = (array) ( $view['questions'] ?? [] );
$question_count = count( $questions );
$initial_question_index = 0;
foreach ( $questions as $question_index => $question ) {
    if ( empty( $question['selected_answers'] ) ) {
        $initial_question_index = (int) $question_index;
        break;
    }
    $initial_question_index = (int) $question_index;
}
?>
<div class="daw-recruitment daw-recruitment--psych-test daw-recruitment--iq<?= $test_ready ? ' daw-recruitment--exam-active' : '' ?>">
    <?php if ( $test_ready ) : ?>
        <header class="daw-exam-bar">
            <a class="daw-exam-bar__brand" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>" aria-label="DAW Recruitment">
                <span>D</span>
                <span>DAW Recruitment<small>Tes IQ</small></span>
            </a>
            <span class="daw-exam-bar__stage">Subtes <?= esc_html( (string) ( $view['test']['index'] + 1 ) ) ?> dari <?= esc_html( (string) $view['test']['count'] ) ?></span>
            <div class="daw-exam-bar__details">
                <span class="daw-exam-bar__timer"><span aria-hidden="true">&#9711;</span> <time data-iq-timer aria-live="off">--:--</time></span>
                <span data-exam-question-counter>1 / <?= esc_html( (string) $question_count ) ?></span>
            </div>
        </header>
    <?php else : ?>
        <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <?php endif; ?>
    <main class="daw-recruitment__psychotest-main">
        <div class="daw-recruitment__psychotest-shell daw-recruitment__psychotest-shell--assessment<?= $test_ready ? ' daw-exam-shell' : '' ?>">
            <?php if ( ! empty( $view['error'] ) ) : ?>
                <section class="daw-psych-test-card">
                    <h1>Tes IQ belum tersedia</h1>
                    <p role="alert"><?= esc_html( $view['error'] ) ?></p>
                    <a class="daw-psych-test-button" href="<?= esc_url( $psychotest_home_url ) ?>">Kembali ke daftar tes</a>
                </section>
            <?php elseif ( ! empty( $view['complete'] ) ) : ?>
                <section class="daw-psych-test-card daw-psych-test-card--complete">
                    <div class="daw-psych-test-icon daw-psych-test-icon--success" aria-hidden="true">&#10003;</div>
                    <h1>Tes IQ selesai</h1>
                    <p>Seluruh subtes telah diselesaikan dan hasil tersimpan untuk ditinjau tim HR.</p>
                    <a class="daw-psych-test-button" href="<?= esc_url( $psychotest_home_url ) ?>">Kembali ke daftar tes</a>
                </section>
            <?php elseif ( empty( $view['ready'] ) ) : ?>
                <section class="daw-psych-test-card">
                    <h1>Tes IQ belum dimulai</h1>
                    <p>Siapkan perangkat dan koneksi internet sebelum memulai.</p>
                    <a class="daw-psych-test-button" href="<?= esc_url( $preparation_url ) ?>">Mulai persiapan</a>
                    <a class="daw-psych-test-secondary" href="<?= esc_url( $psychotest_home_url ) ?>">Kembali ke daftar tes</a>
                </section>
            <?php else : ?>
                <div class="daw-exam-layout">
                    <div class="daw-exam-primary">
                        <section
                            class="daw-psych-test-card daw-iq-assessment"
                            data-iq-assessment
                            data-deadline="<?= esc_attr( (string) $view['test']['deadline'] ) ?>"
                            data-ajax-url="<?= esc_url( $view['ajax_url'] ) ?>"
                            data-save-nonce="<?= esc_attr( $view['nonce'] ) ?>"
                            data-current-index="<?= esc_attr( (string) $initial_question_index ) ?>"
                        >
                            <div class="daw-exam-card__eyebrow">
                                <span><i aria-hidden="true"></i> Tes Kemampuan Intelektual</span>
                                <p class="daw-iq-assessment__save-status" data-iq-save-status role="status">Jawaban disimpan otomatis.</p>
                            </div>
                            <p class="daw-exam-card__instructions"><?= esc_html( $view['test']['instructions'] ) ?></p>
                            <form method="post" action="<?= esc_url( recruitment_get_public_url( 'psych-iq' ) ) ?>" data-iq-form>
                                <?php wp_nonce_field( 'recruitment_iq_advance' ); ?>
                                <input type="hidden" name="iq_action" value="advance">
                                <div class="daw-iq-assessment__questions">
                                    <?php foreach ( $questions as $question_index => $question ) :
                                        $question_id = (int) $question['id'];
                                        $selected_answers = array_map( 'absint', (array) ( $question['selected_answers'] ?? [] ) );
                                        $input_type = 'multi_select' === ( $question['type'] ?? '' ) ? 'checkbox' : 'radio';
                                        ?>
                                        <fieldset class="daw-iq-assessment__question daw-exam-question" data-iq-question="<?= esc_attr( (string) $question_id ) ?>" data-question-index="<?= esc_attr( (string) $question_index ) ?>" data-max-select="<?= esc_attr( 'checkbox' === $input_type ? '2' : '1' ) ?>" <?= $question_index !== $initial_question_index ? 'hidden' : '' ?>>
                                            <legend><span class="daw-exam-question__number">PERTANYAAN <?= esc_html( (string) ( $question_index + 1 ) ) ?> DARI <?= esc_html( (string) $question_count ) ?></span><strong><?= esc_html( $question['prompt'] ) ?></strong></legend>
                                            <input type="hidden" name="answer_present[<?= esc_attr( (string) $question_id ) ?>]" value="1">
                                            <?php foreach ( $question['choices'] as $choice_index => $choice ) :
                                                $choice_id = (int) $choice['id'];
                                                ?>
                                                <label class="daw-iq-assessment__choice">
                                                    <input
                                                        type="<?= esc_attr( $input_type ) ?>"
                                                        name="answers[<?= esc_attr( (string) $question_id ) ?>][]"
                                                        value="<?= esc_attr( (string) $choice_id ) ?>"
                                                        data-iq-answer
                                                        <?= checked( in_array( $choice_id, $selected_answers, true ), true, false ) ?>
                                                    >
                                                    <span class="daw-iq-assessment__choice-key" aria-hidden="true"><?= esc_html( chr( 65 + (int) $choice_index ) ) ?></span>
                                                    <span class="daw-iq-assessment__choice-text"><?= esc_html( $choice['text'] ) ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </fieldset>
                                    <?php endforeach; ?>
                                </div>
                                <div class="daw-exam-controls">
                                    <button type="button" class="daw-exam-controls__previous" data-exam-previous disabled>&larr; Sebelumnya</button>
                                    <button type="button" class="daw-psych-test-button" data-exam-next>Berikutnya &rarr;</button>
                                    <button type="submit" class="daw-psych-test-button" data-exam-finish hidden>Selesaikan subtes &rarr;</button>
                                </div>
                            </form>
                            <nav class="daw-exam-navigator" aria-label="Navigator soal">
                                <div class="daw-exam-navigator__heading">
                                    <strong>Navigator Soal</strong>
                                    <span><span data-exam-answered-count>0</span> dari <?= esc_html( (string) $question_count ) ?> terjawab</span>
                                </div>
                                <div class="daw-exam-navigator__buttons">
                                    <?php foreach ( $questions as $question_index => $question ) : ?>
                                        <button type="button" data-exam-question-nav="<?= esc_attr( (string) $question_index ) ?>" aria-label="Buka soal <?= esc_attr( (string) ( $question_index + 1 ) ) ?>"><?= esc_html( (string) ( $question_index + 1 ) ) ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </nav>
                        </section>
                    </div>
                    <?php require recruitment_get_plugin_path( 'public/components/psychotest-proctoring-watch.php' ); ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
