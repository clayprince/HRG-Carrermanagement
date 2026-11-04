<?php
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw_job_id = 'POST' === $request_method ? ( $_POST['job_id'] ?? 0 ) : ( $_GET['job_id'] ?? 0 );
$job_id = is_scalar( $raw_job_id ) ? absint( $raw_job_id ) : 0;
$current_user = wp_get_current_user();
$name = (string) $current_user->display_name;
$email = (string) $current_user->user_email;
$ui_preview = recruitment_is_ui_preview()
    || ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) );
$vacancy_database = new Recruitment_Database();
$vacancy_record = $job_id ? $vacancy_database->get_vacancy( $job_id ) : null;
$vacancy_is_available = is_array( $vacancy_record ) && 1 === (int) ( $vacancy_record['status'] ?? 0 );
$job = $vacancy_is_available ? recruitment_map_vacancy( $vacancy_record ) : [];
$job_title = (string) ( $job['title'] ?? '' );
$job_meta = trim( (string) ( $job['dealer'] ?? '' ) . ' · ' . (string) ( $job['location'] ?? '' ), ' ·' );
$job_deadline = (string) ( $job['deadline'] ?? '' );

$form_result = ( new Recruitment_Database() )->get_application_form();
$form_sections = ! empty( $form_result['success'] ) ? $form_result['sections'] : [];
$form_available = (bool) array_sum( array_map( static function ( $section ) {
    return count( $section['questions'] ?? [] );
}, $form_sections ) );
$form_sections = $form_available ? $form_sections : [];
$answers_input = [];
$pdp_checked = [];
foreach ( [ 'pdp_accuracy', 'pdp_data', 'pdp_placement' ] as $consent_name ) {
    $consent_value = $_POST[ $consent_name ] ?? null;
    $pdp_checked[ $consent_name ] = is_scalar( $consent_value )
        && '1' === (string) wp_unslash( $consent_value );
}
$errors = [];
$submitted = false;

if ( ! $vacancy_is_available ) {
    $errors[] = $vacancy_database->has_error()
        ? 'Lowongan belum dapat dimuat dari Oracle. Silakan coba kembali nanti.'
        : 'Lowongan tidak tersedia atau sudah tidak dipublikasikan.';
}
if ( ! $form_available ) {
    $errors[] = 'Form lamaran belum tersedia. Silakan hubungi tim HR.';
}

if ( 'POST' === $request_method ) {
    $answers_input = isset( $_POST['answers'] ) && is_array( $_POST['answers'] )
        ? wp_unslash( $_POST['answers'] )
        : [];
    $preview_flag = isset( $_POST['daw_ui_preview'] ) && is_scalar( $_POST['daw_ui_preview'] )
        ? sanitize_text_field( wp_unslash( (string) $_POST['daw_ui_preview'] ) )
        : '';
    $nonce = isset( $_POST['recruitment_nonce'] ) && is_scalar( $_POST['recruitment_nonce'] )
        ? sanitize_text_field( wp_unslash( (string) $_POST['recruitment_nonce'] ) )
        : '';
    $errors = [];
    if ( $ui_preview || '1' === $preview_flag ) {
        $errors[] = 'Mode preview hanya untuk melihat tampilan. Lamaran tidak dikirim.';
    } elseif ( ! wp_verify_nonce( $nonce, 'recruitment_apply' ) ) {
        $errors[] = 'Sesi form tidak valid. Silakan coba lagi.';
    } elseif ( ! $vacancy_is_available ) {
        $errors[] = 'Lowongan tidak tersedia atau sudah tidak dipublikasikan.';
    } elseif ( ! $form_available ) {
        $errors[] = 'Form lamaran belum tersedia. Silakan hubungi tim HR.';
    } else {
        $result = ( new Recruitment_Application() )->submit(
            wp_unslash( $_POST ),
            $_FILES,
            $form_sections,
            $name,
            $email,
            $job
        );
        $errors = $result['errors'];
        $name = (string) ( $result['payload']['applicant_name'] ?? $name );
        $email = (string) ( $result['payload']['applicant_email'] ?? $email );
        if ( $result['success'] ) {
            update_user_meta( get_current_user_id(), 'daw_application_confirmation_pending', '1' );
            wp_safe_redirect( recruitment_get_public_url( 'application-confirmation' ) );
            exit;
        }
    }
}

$has_form = $vacancy_is_available && $form_available;
?>
<div class="daw-recruitment daw-recruitment--application">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__section daw-recruitment__application-main">
        <div class="daw-recruitment__container daw-recruitment__form-container">
            <?php if ( $has_form ) : ?>
                <a class="daw-recruitment__back" href="<?= esc_url( recruitment_get_public_url( 'job-detail', [ 'job_id' => $job_id ] ) ) ?>">&larr; Kembali ke Detail Lowongan</a>
                <section class="daw-recruitment__application-job"><span class="daw-recruitment__application-job-icon">&diams;</span><div><strong><?= esc_html( $job_title ) ?></strong><small><?= esc_html( $job_meta ) ?></small></div><span class="daw-recruitment__application-deadline">Deadline: <?= esc_html( $job_deadline ) ?></span></section>
                <div class="daw-recruitment__alert daw-recruitment__alert--notice"><?= esc_html( $ui_preview ? 'Mode preview: formulir dapat dilihat, tetapi lamaran tidak akan dikirim.' : 'Isi seluruh formulir dengan lengkap dan benar. Pastikan semua data dapat dipertanggungjawabkan.' ) ?></div>
                <?php foreach ( $errors as $error ) : ?><div class="daw-recruitment__alert"><?= esc_html( $error ) ?></div><?php endforeach; ?>
                <form class="daw-recruitment__application-form" method="post" enctype="multipart/form-data" data-application-wizard <?= $ui_preview ? 'data-preview-only="true"' : '' ?>>
                    <input type="hidden" name="job_id" value="<?= esc_attr( $job_id ) ?>">
                    <input type="hidden" name="daw_ui_preview" value="<?= esc_attr( $ui_preview ? '1' : '0' ) ?>">
                    <?php wp_nonce_field( 'recruitment_apply', 'recruitment_nonce' ); ?>
                    <?php foreach ( $form_sections as $section_index => $section ) : ?>
                        <section class="daw-recruitment__form-card">
                            <div class="daw-recruitment__form-card-title"><span><?= esc_html( sprintf( '%02d', $section_index + 1 ) ) ?></span><div><h2><?= esc_html( $section['title'] ) ?></h2><?php if ( '' !== (string) $section['description'] ) : ?><p><?= esc_html( $section['description'] ) ?></p><?php endif; ?></div></div>
                            <div class="daw-recruitment__form-fields">
                                <?php foreach ( $section['questions'] as $question ) : ?>
                                    <?php
                                    $key = (string) $question['key'];
                                    $type = (string) $question['type'];
                                    $required = ! empty( $question['required'] );
                                    $field_name = 'answers[' . $key . ']';
                                    $old_value = $answers_input[ $key ] ?? '';
                                    $old_value = is_scalar( $old_value ) ? (string) $old_value : '';
                                    if ( '' === $old_value && 'nama_lengkap' === $key ) {
                                        $old_value = $name;
                                    } elseif ( '' === $old_value && 'email_aktif' === $key ) {
                                        $old_value = $email;
                                    }
                                    ?>
                                    <?php if ( 'single_choice' === $type ) : ?>
                                        <fieldset class="daw-recruitment__field--full">
                                            <legend><?= esc_html( $question['title'] ) ?><?php if ( $required ) : ?> <em>*</em><?php endif; ?></legend>
                                            <div class="daw-recruitment__choice-row">
                                                <?php foreach ( $question['options'] as $option ) : ?>
                                                    <?php $option_value = (string) ( $option['value'] ?? '' ); ?>
                                                    <label><input type="radio" name="<?= esc_attr( $field_name ) ?>" value="<?= esc_attr( $option_value ) ?>" <?= checked( $old_value, $option_value, false ) ?> <?= $required ? 'required' : '' ?>> <?= esc_html( $option['label'] ?? $option_value ) ?></label>
                                                <?php endforeach; ?>
                                            </div>
                                        </fieldset>
                                    <?php else : ?>
                                        <label class="<?= 'paragraph' === $type || 'file' === $type ? 'daw-recruitment__field--full' : '' ?>">
                                            <?= esc_html( $question['title'] ) ?><?php if ( $required ) : ?> <em>*</em><?php endif; ?>
                                            <?php if ( 'paragraph' === $type ) : ?>
                                                <textarea name="<?= esc_attr( $field_name ) ?>" rows="3" <?= $required ? 'required' : '' ?>><?= esc_textarea( $old_value ) ?></textarea>
                                            <?php elseif ( 'dropdown' === $type ) : ?>
                                                <select name="<?= esc_attr( $field_name ) ?>" <?= $required ? 'required' : '' ?>>
                                                    <option value="">Pilih jawaban</option>
                                                    <?php foreach ( $question['options'] as $option ) : ?>
                                                        <?php $option_value = (string) ( $option['value'] ?? '' ); ?>
                                                        <option value="<?= esc_attr( $option_value ) ?>" <?= selected( $old_value, $option_value, false ) ?>><?= esc_html( $option['label'] ?? $option_value ) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php elseif ( 'file' === $type ) : ?>
                                                <span class="daw-recruitment__upload"><input type="file" name="<?= esc_attr( $field_name ) ?>" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" <?= $required ? 'required' : '' ?>><strong>Unggah file</strong><small>PDF / JPG / PNG · Maks 5 MB</small></span>
                                            <?php else : ?>
                                                <?php
                                                $input_type = [
                                                    'short' => 'text',
                                                    'number' => 'number',
                                                    'date' => 'date',
                                                    'email' => 'email',
                                                    'phone' => 'tel',
                                                ][ $type ] ?? 'text';
                                                ?>
                                                <input type="<?= esc_attr( $input_type ) ?>" name="<?= esc_attr( $field_name ) ?>" value="<?= esc_attr( $old_value ) ?>" <?= $required ? 'required' : '' ?>>
                                            <?php endif; ?>
                                        </label>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                    <section class="daw-recruitment__form-card daw-recruitment__form-card--pdp">
                        <div class="daw-recruitment__form-card-title"><span>♢</span><div><h2>Pernyataan dan Persetujuan Data Pribadi (PDP)</h2><p>Centang seluruh pernyataan di bawah sebelum mengirimkan lamaran.</p></div></div>
                        <div class="daw-recruitment__pdp-list">
                            <label><input type="checkbox" name="pdp_accuracy" value="1" required data-pdp-consent <?= checked( $pdp_checked['pdp_accuracy'], true, false ) ?>> Saya menyatakan bahwa data-data yang saya isi dalam formulir ini adalah benar dan menggambarkan kondisi saya saat ini dan saya bersedia untuk mempertanggungjawabkannya.</label>
                            <label><input type="checkbox" name="pdp_data" value="1" required data-pdp-consent <?= checked( $pdp_checked['pdp_data'], true, false ) ?>> Saya bersedia memberikan Data Pribadi saya seperti CV, Ijazah, Transkrip Nilai, Foto, KTP, SIM, NPWP, Nomor Rekening Bank, SPT Kartu Keluarga, Media Sosial Saya dan data lainnya untuk diakses, disimpan dan dipergunakan untuk proses recruitment di Daya Group.</label>
                            <label><input type="checkbox" name="pdp_placement" value="1" required data-pdp-consent <?= checked( $pdp_checked['pdp_placement'], true, false ) ?>> Saya bersedia ditempatkan di seluruh area penempatan Daya Adicipta Wisesa dan seluruh anak perusahaan di bawah naungan Daya Group.</label>
                        </div>
                        <p class="daw-recruitment__pdp-note">ⓘ Centang ketiga pernyataan di atas untuk mengaktifkan tombol Submit.</p>
                    </section>
                    <div class="daw-recruitment__form-actions"><a class="daw-recruitment__button daw-recruitment__button--muted" href="<?= esc_url( recruitment_get_public_url( 'job-detail', [ 'job_id' => $job_id ] ) ) ?>">← Batal</a><button class="daw-recruitment__button daw-recruitment__submit" type="submit" <?= $ui_preview ? 'disabled aria-disabled="true" title="Tidak tersedia pada mode preview"' : 'disabled' ?> data-submit-application><?= $ui_preview ? 'Preview - Lamaran Nonaktif' : 'Submit Lamaran' ?></button></div>
                </form>
            <?php else : ?>
                <section class="daw-recruitment__panel"><h1>Form Lamaran Tidak Tersedia</h1><?php foreach ( $errors as $error ) : ?><p><?= esc_html( $error ) ?></p><?php endforeach; ?><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Lihat Lowongan</a></section>
            <?php endif; ?>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
