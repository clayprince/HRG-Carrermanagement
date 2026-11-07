<?php
$view = ( new Recruitment_IQ_Assessment() )->get_page_view();
$preparation_url = recruitment_get_public_url( 'psychotest-preparation', [ 'test' => 'iq' ] );
$psychotest_home_url = recruitment_get_public_url( 'psychotest' );
?>
<div class="daw-recruitment daw-recruitment--psych-test daw-recruitment--iq">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__psychotest-main">
        <div class="daw-recruitment__psychotest-shell daw-recruitment__psychotest-shell--assessment">
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
                <section
                    class="daw-psych-test-card daw-iq-assessment"
                    data-iq-assessment
                    data-deadline="<?= esc_attr( (string) $view['test']['deadline'] ) ?>"
                    data-ajax-url="<?= esc_url( $view['ajax_url'] ) ?>"
                    data-save-nonce="<?= esc_attr( $view['nonce'] ) ?>"
                >
                    <header class="daw-iq-assessment__header">
                        <div>
                            <span class="daw-recruitment__eyebrow daw-recruitment__eyebrow--red">TES IQ · SUBTES <?= esc_html( (string) ( $view['test']['index'] + 1 ) ) ?> DARI <?= esc_html( (string) $view['test']['count'] ) ?></span>
                            <h1><?= esc_html( $view['test']['title'] ) ?></h1>
                            <p><?= esc_html( $view['test']['instructions'] ) ?></p>
                        </div>
                        <div class="daw-iq-assessment__timer">
                            <span>SISA WAKTU</span>
                            <time data-iq-timer aria-live="off">--:--</time>
                        </div>
                    </header>

                    <p class="daw-iq-assessment__save-status" data-iq-save-status role="status">Jawaban disimpan otomatis.</p>
                    <form method="post" action="<?= esc_url( recruitment_get_public_url( 'psych-iq' ) ) ?>" data-iq-form>
                        <?php wp_nonce_field( 'recruitment_iq_advance' ); ?>
                        <input type="hidden" name="iq_action" value="advance">
                        <div class="daw-iq-assessment__progress">
                            <span data-iq-progress-label>0 dari <?= esc_html( (string) count( $view['questions'] ) ) ?> soal dijawab</span>
                            <progress max="<?= esc_attr( (string) count( $view['questions'] ) ) ?>" value="0" data-iq-progress-bar aria-label="Progress menjawab soal"></progress>
                        </div>
                        <div class="daw-iq-assessment__questions">
                            <?php foreach ( $view['questions'] as $question ) :
                                $question_id = (int) $question['id'];
                                $selected_answers = array_map( 'absint', (array) ( $question['selected_answers'] ?? [] ) );
                                $input_type = 'multi_select' === ( $question['type'] ?? '' ) ? 'checkbox' : 'radio';
                                ?>
                                <fieldset class="daw-iq-assessment__question" data-iq-question="<?= esc_attr( (string) $question_id ) ?>" data-max-select="<?= esc_attr( 'checkbox' === $input_type ? '2' : '1' ) ?>">
                                    <legend><strong>Nomor <?= esc_html( (string) $question['number'] ) ?></strong> <?= esc_html( $question['prompt'] ) ?></legend>
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
                        <div class="daw-iq-assessment__footer">
                            <span>Pastikan jawaban tersimpan sebelum mengakhiri subtes.</span>
                            <button type="submit" class="daw-psych-test-button">Selesaikan subtes <?= esc_html( (string) ( $view['test']['index'] + 1 ) ) ?></button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
