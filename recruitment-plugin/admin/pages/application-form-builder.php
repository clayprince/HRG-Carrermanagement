<?php
$form_types = [
    'short'         => 'Jawaban Singkat',
    'paragraph'     => 'Paragraf / Jawaban Panjang',
    'number'        => 'Angka',
    'date'          => 'Tanggal',
    'email'         => 'Email',
    'phone'         => 'Nomor Telepon',
    'file'          => 'Upload File',
    'dropdown'      => 'Pilihan Dropdown',
    'single_choice' => 'Pilihan Satu Jawaban',
];

$database = new Recruitment_Database();
$form_result = $database->get_application_form();
$sections = ! empty( $form_result['success'] ) ? $form_result['sections'] : [];
$fixed_field_types = ( new Recruitment_Application() )->get_fixed_field_types();
$form_notice = sanitize_key( wp_unslash( $_GET['form_notice'] ?? '' ) );
$notice = 'saved' === $form_notice ? 'Form lamaran berhasil disimpan dan akan digunakan pada pengajuan berikutnya.' : '';
$warning = ! empty( $form_result['success'] ) ? '' : (string) ( $form_result['error'] ?? 'Konfigurasi form tidak dapat dimuat dari Oracle.' );
$error = 'save_failed' === $form_notice ? 'Form gagal disimpan ke Oracle. Periksa koneksi dan migration yang diperlukan, lalu coba lagi.' : '';
$form_load_failed = empty( $form_result['success'] );

$total_questions = array_sum( array_map( static function ( $section ) {
    return count( $section['questions'] ?? [] );
}, $sections ) );
?>
<div class="wrap recruitment-form-builder">
    <div data-form-builder>
        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
            <input type="hidden" name="action" value="daw_save_application_form">
            <?php wp_nonce_field( 'daw_save_application_form' ); ?>
        <header class="recruitment-form-builder__topbar">
            <div>
                <h1>Form Lamaran Kerja DAW <span class="recruitment-form-builder__draft">Aktif</span></h1>
                <p class="recruitment-form-builder__summary"><span data-section-count><?= esc_html( count( $sections ) ) ?></span> section · <span data-question-count><?= esc_html( $total_questions ) ?></span> pertanyaan</p>
            </div>
            <div class="recruitment-form-builder__actions">
                <button type="button" class="button" data-preview-form>Pratinjau</button>
                <button type="button" class="button" data-add-section <?= $form_load_failed ? 'disabled' : '' ?>>+ Tambah Section</button>
                <button type="submit" class="button button-primary" <?= $form_load_failed ? 'disabled' : '' ?>>Simpan Form</button>
            </div>
        </header>

        <?php if ( $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p><?= esc_html( $notice ) ?></p></div>
        <?php endif; ?>
        <?php if ( $warning ) : ?>
            <div class="notice notice-warning"><p><?= esc_html( $warning ) ?></p></div>
        <?php endif; ?>
        <?php if ( $error ) : ?>
            <div class="notice notice-error"><p><?= esc_html( $error ) ?></p></div>
        <?php endif; ?>

        <p class="description">Field inti yang terhubung ke data kandidat Oracle dapat diubah judul dan kewajibannya, tetapi jenis jawaban serta key penyimpanannya dikunci. Pertanyaan tambahan dapat ditambah dan dihapus.</p>
        <fieldset class="recruitment-form-builder__fixed-fields" <?= $form_load_failed ? 'disabled' : '' ?> aria-label="Daftar pertanyaan form">
            <div class="recruitment-form-builder__sections" data-form-sections>
                <?php foreach ( $sections as $section_index => $section ) : ?>
                    <section class="recruitment-form-section" data-form-section>
                    <input type="hidden" name="form_sections[<?= esc_attr( $section_index ) ?>][section_id]" value="<?= esc_attr( $section['id'] ?? '' ) ?>" data-section-id>
                    <header class="recruitment-form-section__header">
                        <span class="recruitment-form-section__number" data-section-number><?= esc_html( sprintf( '%02d', $section_index + 1 ) ) ?></span>
                        <div class="recruitment-form-section__heading">
                            <label class="screen-reader-text" for="section-title-<?= esc_attr( $section_index ) ?>">Nama section</label>
                            <input id="section-title-<?= esc_attr( $section_index ) ?>" class="recruitment-form-section__title" name="form_sections[<?= esc_attr( $section_index ) ?>][title]" value="<?= esc_attr( $section['title'] ?? '' ) ?>" data-section-title required>
                            <input class="recruitment-form-section__description" name="form_sections[<?= esc_attr( $section_index ) ?>][description]" value="<?= esc_attr( $section['description'] ?? '' ) ?>" placeholder="Deskripsi section" data-section-description>
                        </div>
                        <span class="recruitment-form-section__count"><span data-section-question-count><?= esc_html( count( $section['questions'] ?? [] ) ) ?></span> pertanyaan</span>
                        <div class="recruitment-form-section__actions">
                            <button type="button" class="button button-small" data-move-section="up" aria-label="Pindahkan section ke atas">↑</button>
                            <button type="button" class="button button-small" data-move-section="down" aria-label="Pindahkan section ke bawah">↓</button>
                            <button type="button" class="button button-small button-link-delete" data-remove-section <?= array_intersect( array_keys( $fixed_field_types ), array_column( (array) ( $section['questions'] ?? [] ), 'key' ) ) ? 'disabled title="Pindahkan field inti ke section lain sebelum menghapus section ini."' : '' ?>>Hapus</button>
                        </div>
                    </header>
                    <div class="recruitment-form-section__questions" data-section-questions>
                        <?php foreach ( (array) ( $section['questions'] ?? [] ) as $question_index => $question ) : ?>
                            <?php $is_fixed_question = isset( $fixed_field_types[ $question['key'] ?? '' ] ); ?>
                            <article class="recruitment-form-question" data-form-question <?= $is_fixed_question ? 'data-fixed-question' : '' ?>>
                                <div class="recruitment-form-question__row">
                                    <span class="recruitment-form-question__number" data-question-number><?= esc_html( $question_index + 1 ) ?></span>
                                    <span class="recruitment-form-question__icon" aria-hidden="true">≡</span>
                                    <div class="recruitment-form-question__identity">
                                        <strong data-question-label><?= esc_html( $question['title'] ?? '' ) ?></strong>
                                        <span class="recruitment-form-question__meta"><span data-question-type-label><?= esc_html( $form_types[ $question['type'] ?? 'short' ] ?? 'Jawaban Singkat' ) ?></span><span data-question-required><?= ! empty( $question['required'] ) ? 'Wajib diisi' : 'Opsional' ?></span></span>
                                    </div>
                                    <div class="recruitment-form-question__actions">
                                        <button type="button" class="button button-small" data-move-question="up" aria-label="Pindahkan pertanyaan ke atas">↑</button>
                                        <button type="button" class="button button-small" data-move-question="down" aria-label="Pindahkan pertanyaan ke bawah">↓</button>
                                        <details class="recruitment-form-question__edit"><summary class="button button-small">Edit</summary>
                                            <div class="recruitment-form-question__editor">
                                                <input type="hidden" name="form_sections[<?= esc_attr( $section_index ) ?>][questions][<?= esc_attr( $question_index ) ?>][field_id]" value="<?= esc_attr( $question['id'] ?? '' ) ?>" data-question-id>
                                                <input type="hidden" name="form_sections[<?= esc_attr( $section_index ) ?>][questions][<?= esc_attr( $question_index ) ?>][key]" value="<?= esc_attr( $question['key'] ?? '' ) ?>" data-question-key>
                                                <label>Pertanyaan<input name="form_sections[<?= esc_attr( $section_index ) ?>][questions][<?= esc_attr( $question_index ) ?>][title]" value="<?= esc_attr( $question['title'] ?? '' ) ?>" data-question-title required></label>
                                                <?php if ( $is_fixed_question ) : ?>
                                                    <label>Jenis jawaban<input type="hidden" name="form_sections[<?= esc_attr( $section_index ) ?>][questions][<?= esc_attr( $question_index ) ?>][type]" value="<?= esc_attr( $question['type'] ?? 'short' ) ?>" data-question-type><span><?= esc_html( $form_types[ $question['type'] ?? 'short' ] ?? 'Jawaban Singkat' ) ?> (terkunci ke ERD)</span></label>
                                                <?php else : ?>
                                                    <label>Jenis jawaban<select name="form_sections[<?= esc_attr( $section_index ) ?>][questions][<?= esc_attr( $question_index ) ?>][type]" data-question-type><?php foreach ( $form_types as $type => $label ) : ?><option value="<?= esc_attr( $type ) ?>" <?= selected( $question['type'] ?? 'short', $type, false ) ?>><?= esc_html( $label ) ?></option><?php endforeach; ?></select></label>
                                                <?php endif; ?>
                                                <label class="recruitment-form-question__options" data-question-options>Opsi jawaban<textarea name="form_sections[<?= esc_attr( $section_index ) ?>][questions][<?= esc_attr( $question_index ) ?>][options]" rows="3" data-question-options-value><?= esc_textarea( implode( "\n", array_map( static function ( $option ) { return is_array( $option ) ? (string) ( $option['label'] ?? $option['value'] ?? '' ) : (string) $option; }, (array) ( $question['options'] ?? [] ) ) ) ) ?></textarea><small>Satu opsi untuk setiap baris.</small></label>
                                                <label class="recruitment-form-question__required"><input type="checkbox" name="form_sections[<?= esc_attr( $section_index ) ?>][questions][<?= esc_attr( $question_index ) ?>][required]" value="1" data-question-required-input <?= checked( ! empty( $question['required'] ), true, false ) ?>> Wajib diisi</label>
                                                <?php if ( $is_fixed_question ) : ?>
                                                    <span class="description">Field inti terhubung ke Oracle dan tidak dapat dihapus.</span>
                                                <?php else : ?>
                                                    <button type="button" class="button button-link-delete" data-remove-question>Hapus pertanyaan</button>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="recruitment-form-section__add-question" data-add-question>+ Tambah Pertanyaan</button>
                    </section>
                <?php endforeach; ?>
            </div>
        </fieldset>
        </form>
    </div>
    <dialog class="recruitment-form-preview" data-form-preview>
        <div class="recruitment-form-preview__header"><h2>Pratinjau Form Lamaran</h2><button type="button" class="button" data-close-preview>Tutup</button></div>
        <div class="recruitment-form-preview__body" data-preview-content></div>
    </dialog>
    <template data-question-template>
        <article class="recruitment-form-question" data-form-question>
            <div class="recruitment-form-question__row">
                <span class="recruitment-form-question__number" data-question-number></span><span class="recruitment-form-question__icon" aria-hidden="true">≡</span>
                <div class="recruitment-form-question__identity"><strong data-question-label>Pertanyaan baru</strong><span class="recruitment-form-question__meta"><span data-question-type-label>Jawaban Singkat</span><span data-question-required>Opsional</span></span></div>
                <div class="recruitment-form-question__actions"><button type="button" class="button button-small" data-move-question="up" aria-label="Pindahkan pertanyaan ke atas">↑</button><button type="button" class="button button-small" data-move-question="down" aria-label="Pindahkan pertanyaan ke bawah">↓</button>
                    <details class="recruitment-form-question__edit" open><summary class="button button-small">Edit</summary><div class="recruitment-form-question__editor">
                        <input type="hidden" data-question-id>
                        <input type="hidden" data-question-key>
                        <label>Pertanyaan<input value="Pertanyaan baru" data-question-title required></label>
                        <label>Jenis jawaban<select data-question-type><?php foreach ( $form_types as $type => $label ) : ?><option value="<?= esc_attr( $type ) ?>"><?= esc_html( $label ) ?></option><?php endforeach; ?></select></label>
                        <label class="recruitment-form-question__options" data-question-options>Opsi jawaban<textarea rows="3" data-question-options-value></textarea><small>Satu opsi untuk setiap baris.</small></label>
                        <label class="recruitment-form-question__required"><input type="checkbox" value="1" data-question-required-input> Wajib diisi</label><button type="button" class="button button-link-delete" data-remove-question>Hapus pertanyaan</button>
                    </div></details>
                </div>
            </div>
        </article>
    </template>
    <template data-section-template>
        <section class="recruitment-form-section" data-form-section>
            <input type="hidden" data-section-id>
            <header class="recruitment-form-section__header"><span class="recruitment-form-section__number" data-section-number></span><div class="recruitment-form-section__heading"><label class="screen-reader-text">Nama section</label><input class="recruitment-form-section__title" value="Section baru" data-section-title required><input class="recruitment-form-section__description" placeholder="Deskripsi section" data-section-description></div><span class="recruitment-form-section__count"><span data-section-question-count>0</span> pertanyaan</span><div class="recruitment-form-section__actions"><button type="button" class="button button-small" data-move-section="up" aria-label="Pindahkan section ke atas">↑</button><button type="button" class="button button-small" data-move-section="down" aria-label="Pindahkan section ke bawah">↓</button><button type="button" class="button button-small button-link-delete" data-remove-section>Hapus</button></div></header><div class="recruitment-form-section__questions" data-section-questions></div><button type="button" class="recruitment-form-section__add-question" data-add-question>+ Tambah Pertanyaan</button>
        </section>
    </template>
</div>
