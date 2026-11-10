<?php
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw_job_id = 'POST' === $request_method ? ( $_POST['job_id'] ?? 0 ) : ( $_GET['job_id'] ?? 0 );
$job_id = is_scalar( $raw_job_id ) ? absint( $raw_job_id ) : 0;
$applicant = recruitment_get_current_applicant();
$name = (string) ( $applicant['full_name'] ?? '' );
$email = (string) ( $applicant['email'] ?? '' );
$ui_preview = recruitment_is_ui_preview();
$vacancies_result = recruitment_get_public_jobs_result();
$job = null;
foreach ( $vacancies_result['jobs'] as $vacancy_item ) {
    if ( (int) $vacancy_item['id'] === $job_id ) {
        $job = $vacancy_item;
        break;
    }
}
$vacancy_is_available = null !== $job;
$job = $job ?: [];
$job_title = (string) ( $job['title'] ?? '' );
$job_meta = trim( (string) ( $job['dealer'] ?? '' ) . ' · ' . (string) ( $job['location'] ?? '' ), ' ·' );
$job_deadline = (string) ( $job['deadline'] ?? '' );
$profile_answers = ( new Recruitment_Application() )->prefill_profile_answers( is_array( $applicant ) ? $applicant : [] );

$form_result = ( new Recruitment_Database() )->get_application_form();
$form_sections = ! empty( $form_result['success'] ) ? $form_result['sections'] : [];
$form_available = (bool) array_sum( array_map( static function ( $section ) {
    return count( $section['questions'] ?? [] );
}, $form_sections ) );
$reference_form = false;
if ( ! $form_available ) {
    $reference_form = true;
    $reference_sections = ( new Recruitment_Application() )->get_reference_form_sections();
    $bootstrap_result = ( new Recruitment_Database() )->save_application_form( $reference_sections );
    if ( ! empty( $bootstrap_result['success'] ) ) {
        $form_result = ( new Recruitment_Database() )->get_application_form();
        $form_sections = ! empty( $form_result['success'] ) ? $form_result['sections'] : [];
        $form_available = (bool) array_sum( array_map( static function ( $section ) {
            return count( $section['questions'] ?? [] );
        }, $form_sections ) );
        $reference_form = ! $form_available;
    } else {
        $form_sections = $reference_sections;
        $form_available = true;
    }
}
$answers_input = [];
$pdp_checked = [];
foreach ( [ 'pdp_accuracy', 'pdp_data', 'pdp_placement' ] as $consent_name ) {
    $consent_value = $_POST[ $consent_name ] ?? null;
    $pdp_checked[ $consent_name ] = is_scalar( $consent_value )
        && '1' === (string) wp_unslash( $consent_value );
}
$errors = [];
$submitted = false;
$table_prefixes = [ 'pendidikan_formal', 'pendidikan_nonformal', 'bahasa', 'riwayat_pekerjaan', 'atasan_dan_bawahan', 'media_sosial' ];
$form_units = [];
foreach ( $form_sections as $section_index => $section ) {
    $form_units[ $section_index ] = [];
    $unit_indexes = [];
    foreach ( $section['questions'] as $question ) {
        $key = (string) ( $question['key'] ?? '' );
        $prefix = '';
        $row_key = '';
        $field_key = '';
        if ( preg_match( '/^(' . implode( '|', $table_prefixes ) . ')_(\d+)_(.+)$/', $key, $matches ) ) {
            $prefix = $matches[1];
            $row_key = $matches[2];
            $field_key = $matches[3];
        } elseif ( preg_match( '/^(keluarga_(?:inti|asal))_(.+)_(nama|jenis_kelamin|tempat_tanggal_lahir|pendidikan|pekerjaan)$/', $key, $matches ) ) {
            $prefix = $matches[1];
            $row_key = $matches[2];
            $field_key = $matches[3];
        } elseif ( preg_match( '/^(minat_(?:jenis_pekerjaan|bidang_industri))_(\d+)$/', $key, $matches ) ) {
            $prefix = $matches[1];
            $row_key = $matches[2];
            $field_key = 'minat';
        }

        if ( '' === $prefix ) {
            $form_units[ $section_index ][] = [ 'type' => 'question', 'question' => $question ];
            continue;
        }
        if ( ! isset( $unit_indexes[ $prefix ] ) ) {
            $unit_indexes[ $prefix ] = count( $form_units[ $section_index ] );
            $form_units[ $section_index ][] = [ 'type' => 'table', 'prefix' => $prefix, 'rows' => [] ];
        }
        $unit_index = $unit_indexes[ $prefix ];
        $form_units[ $section_index ][ $unit_index ]['rows'][ $row_key ][ $field_key ] = $question;
    }
}
$table_titles = [
    'pendidikan_formal' => 'Pendidikan Formal',
    'pendidikan_nonformal' => 'Pendidikan Nonformal / Kursus',
    'bahasa' => 'Kemampuan Bahasa',
    'riwayat_pekerjaan' => 'Riwayat Pekerjaan',
    'atasan_dan_bawahan' => 'Atasan dan Bawahan',
    'keluarga_inti' => 'Keluarga Inti',
    'keluarga_asal' => 'Keluarga Asal',
    'media_sosial' => 'Media Sosial',
    'minat_jenis_pekerjaan' => 'Prioritas Jenis Pekerjaan',
    'minat_bidang_industri' => 'Prioritas Bidang Industri',
];
$render_question = static function ( array $question, bool $table_cell = false ) use ( &$answers_input, $profile_answers, $applicant ): string {
    $key = (string) $question['key'];
    $type = (string) $question['type'];
    $required = ! empty( $question['required'] );
    $accessible_label = $table_cell ? 'aria-label="' . esc_attr( (string) $question['title'] ) . '"' : '';
    $field_name = 'answers[' . $key . ']';
    $old_value = $answers_input[ $key ] ?? '';
    $old_value = is_scalar( $old_value ) ? (string) $old_value : '';
    if ( '' === $old_value && isset( $profile_answers[ $key ] ) && is_scalar( $profile_answers[ $key ] ) ) {
        $old_value = (string) $profile_answers[ $key ];
    }
    if ( '' !== $old_value && in_array( $type, [ 'dropdown', 'single_choice' ], true ) ) {
        $choice_aliases = [
            'Laki-laki' => [ 'L', 'Laki laki', 'Pria' ],
            'Perempuan' => [ 'P', 'Wanita' ],
            'SMA/SMK' => [ 'SMA / SMK' ],
            'D1' => [ 'Diploma (D1/D2/D3)' ],
            'D2' => [ 'Diploma (D1/D2/D3)' ],
            'D3' => [ 'Diploma (D1/D2/D3)' ],
            'D4/S1' => [ 'S1 (Sarjana)' ],
            'S2' => [ 'S2 (Magister)' ],
            'Belum menikah' => [ 'Belum Menikah' ],
            'Janda / Duda' => [ 'Cerai' ],
        ];
        foreach ( $question['options'] as $option ) {
            $option_value = (string) ( $option['value'] ?? '' );
            if ( 0 === strcasecmp( $old_value, $option_value )
                || in_array( $option_value, $choice_aliases[ $old_value ] ?? [], true ) ) {
                $old_value = $option_value;
                break;
            }
        }
    }

    ob_start();
    if ( in_array( $type, [ 'dropdown', 'single_choice' ], true ) ) :
        ?>
        <label class="<?= ! $table_cell ? '' : 'daw-application__table-field' ?>">
            <?php if ( ! $table_cell ) : ?><?= esc_html( $question['title'] ) ?><?php if ( $required ) : ?> <em>*</em><?php endif; ?><?php endif; ?>
            <select name="<?= esc_attr( $field_name ) ?>" <?= $accessible_label ?> <?= $required ? 'required' : '' ?>>
                <option value="">Pilih jawaban</option>
                <?php foreach ( $question['options'] as $option ) : ?>
                    <?php $option_value = (string) ( $option['value'] ?? '' ); ?>
                    <option value="<?= esc_attr( $option_value ) ?>" <?= selected( $old_value, $option_value, false ) ?>><?= esc_html( $option['label'] ?? $option_value ) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php else : ?>
        <label class="<?= ! $table_cell && ( 'paragraph' === $type || 'file' === $type ) ? 'daw-recruitment__field--full' : '' ?>">
            <?php if ( ! $table_cell ) : ?><?= esc_html( $question['title'] ) ?><?php if ( $required ) : ?> <em>*</em><?php endif; ?><?php endif; ?>
            <?php if ( 'paragraph' === $type ) : ?>
                <textarea name="<?= esc_attr( $field_name ) ?>" rows="3" <?= $accessible_label ?> <?= $required ? 'required' : '' ?>><?= esc_textarea( $old_value ) ?></textarea>
            <?php elseif ( 'file' === $type ) : ?>
                <?php
                $document_key = [
                    'foto_terkini' => 'profile_photo',
                    'cv_resume' => 'resume',
                    'portofolio' => 'portfolio',
                ][ $key ] ?? '';
                $profile_documents = is_array( $applicant['profile_details']['documents'] ?? null ) ? $applicant['profile_details']['documents'] : [];
                ?>
                <?php if ( '' !== $document_key && ! empty( $profile_documents[ $document_key ] ) ) : ?>
                    <small class="daw-application__saved-document">Tersimpan di profil (unggah ulang jika ingin menyertakannya pada lamaran ini): <a href="<?= esc_url( recruitment_get_public_url( 'applicant-file', [ 'document' => $document_key ] ) ) ?>"><?= esc_html( $profile_documents[ $document_key ]['name'] ?? 'Lihat dokumen tersimpan' ) ?></a></small>
                <?php endif; ?>
                <span class="daw-recruitment__upload"><input type="file" name="<?= esc_attr( $field_name ) ?>" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" <?= $accessible_label ?> <?= $required ? 'required' : '' ?>><strong>Unggah file</strong><small>PDF / JPG / PNG · Maks 5 MB</small></span>
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
                <input type="<?= esc_attr( $input_type ) ?>" name="<?= esc_attr( $field_name ) ?>" value="<?= esc_attr( $old_value ) ?>" <?= $accessible_label ?> <?= $required ? 'required' : '' ?>>
            <?php endif; ?>
        </label>
    <?php endif;
    return (string) ob_get_clean();
};

if ( ! $vacancy_is_available ) {
    $errors[] = $vacancies_result['success'] ? 'Lowongan tidak tersedia atau sudah tidak dipublikasikan.' : 'Lowongan belum dapat dimuat karena layanan data sedang bermasalah.';
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
        $errors[] = $vacancies_result['success'] ? 'Lowongan tidak tersedia atau sudah tidak dipublikasikan.' : 'Lowongan belum dapat dimuat karena layanan data sedang bermasalah.';
    } elseif ( ! $form_available ) {
        $errors[] = 'Form lamaran belum tersedia. Silakan hubungi tim HR.';
    } else {
        if ( $reference_form ) {
            $bootstrap_result = ( new Recruitment_Database() )->save_application_form( ( new Recruitment_Application() )->get_reference_form_sections() );
            if ( ! empty( $bootstrap_result['success'] ) ) {
                $reloaded_form = ( new Recruitment_Database() )->get_application_form();
                if ( ! empty( $reloaded_form['success'] ) && ! empty( $reloaded_form['sections'] ) ) {
                    $form_sections = $reloaded_form['sections'];
                    $reference_form = false;
                }
            }
        }
        if ( $reference_form ) {
            $errors[] = 'Form belum dapat dihubungkan ke Oracle. Pastikan migrasi form sudah dijalankan.';
        }
        if ( $errors ) {
            // Keep the submitted values visible; do not attempt a partial application save.
        } else {
        $result = ( new Recruitment_Application() )->submit(
            wp_unslash( $_POST ),
            $_FILES,
            $form_sections,
            $name,
            $email,
            $job,
            (string) ( $applicant['id'] ?? '' )
        );
        $errors = $result['errors'];
        $name = (string) ( $result['payload']['applicant_name'] ?? $name );
        $email = (string) ( $result['payload']['applicant_email'] ?? $email );
        if ( $result['success'] ) {
            $confirmation_key = 'daw_applicant_confirmation_' . hash( 'sha256', (string) $applicant['session_token'] );
            set_transient(
                $confirmation_key,
                [
                    'application_id'    => (string) ( $result['application_id'] ?? '' ),
                    'application_token' => (string) ( $result['application_token'] ?? '' ),
                    'already_submitted' => ! empty( $result['already_submitted'] ),
                ],
                5 * MINUTE_IN_SECONDS
            );
            wp_safe_redirect( recruitment_get_public_url( 'application-confirmation' ) );
            exit;
        }
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
                <div class="daw-recruitment__alert daw-recruitment__alert--notice"><?= esc_html( $ui_preview ? 'Mode preview: formulir dapat dilihat, tetapi lamaran tidak akan dikirim.' : 'Data yang sudah tersedia di profil pelamar akan terisi otomatis. Periksa dan lengkapi jawaban sebelum mengirim.' ) ?></div>
                <?php if ( $reference_form ) : ?><div class="daw-recruitment__alert" role="status">Form acuan digunakan. Data akan divalidasi dan disimpan saat Anda menekan Submit Lamaran.</div><?php endif; ?>
                <?php foreach ( $errors as $error ) : ?><div class="daw-recruitment__alert"><?= esc_html( $error ) ?></div><?php endforeach; ?>
                <form class="daw-recruitment__application-form" method="post" enctype="multipart/form-data" data-application-wizard <?= $ui_preview ? 'data-preview-only="true"' : '' ?>>
                    <input type="hidden" name="job_id" value="<?= esc_attr( $job_id ) ?>">
                    <input type="hidden" name="daw_ui_preview" value="<?= esc_attr( $ui_preview ? '1' : '0' ) ?>">
                    <?php wp_nonce_field( 'recruitment_apply', 'recruitment_nonce' ); ?>
                    <?php foreach ( $form_sections as $section_index => $section ) : ?>
                        <section class="daw-recruitment__form-card">
                            <div class="daw-recruitment__form-card-title"><span><?= esc_html( sprintf( '%02d', $section_index + 1 ) ) ?></span><div><h2><?= esc_html( $section['title'] ) ?></h2><?php if ( '' !== (string) $section['description'] ) : ?><p><?= esc_html( $section['description'] ) ?></p><?php endif; ?></div></div>
                            <div class="daw-recruitment__form-fields">
                                <?php if ( false !== stripos( (string) $section['title'], 'Minat dan Konsep Pribadi' ) ) : ?>
                                    <div class="daw-application__table-scroll" style="grid-column: 1 / -1; margin-top: 4px;">
                                        <table class="daw-application__table daw-profile__qa-table">
                                            <thead>
                                                <tr>
                                                    <th scope="col" style="width: 38%;">Pertanyaan</th>
                                                    <th scope="col" style="width: 62%;">Jawaban</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ( $form_units[ $section_index ] as $unit ) : ?>
                                                    <?php if ( 'question' === $unit['type'] ) : ?>
                                                        <tr>
                                                            <th scope="row"><?= esc_html( $unit['question']['title'] ) ?><?php if ( ! empty( $unit['question']['required'] ) ) : ?> <em style="color:var(--daw-red);">*</em><?php endif; ?></th>
                                                            <td><?= $render_question( $unit['question'], true ) ?></td>
                                                        </tr>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else : ?>
                                    <?php $optional_fields_open = false; ?>
                                    <?php foreach ( $form_units[ $section_index ] as $unit ) : ?>
                                        <?php
                                        $unit_questions = 'table' === $unit['type']
                                            ? array_merge( ...array_values( $unit['rows'] ) )
                                            : [ $unit['question'] ];
                                        $unit_required = (bool) array_filter( $unit_questions, static fn ( $item ): bool => ! empty( $item['required'] ) );
                                        if ( $unit_required && $optional_fields_open ) {
                                            echo '</div></details>';
                                            $optional_fields_open = false;
                                        }
                                        if ( ! $unit_required && ! $optional_fields_open ) :
                                            $optional_fields_open = true;
                                        ?>
                                            <details class="daw-application__optional">
                                                <summary><strong>Bagian Opsional</strong><span>▽ Opsional · boleh dilewati jika tidak relevan</span><span class="daw-application__optional-chevron" aria-hidden="true">⌄</span></summary>
                                                <div class="daw-application__optional-content">
                                        <?php endif; ?>
                                        <?php if ( 'question' === $unit['type'] ) : ?>
                                            <?= $render_question( $unit['question'] ) ?>
                                        <?php else : ?>
                                            <div class="daw-application__table-group">
                                                <h3><?= esc_html( $table_titles[ $unit['prefix'] ] ?? 'Informasi Berulang' ) ?></h3>
                                                <div class="daw-application__table-scroll">
                                                    <table class="daw-application__table">
                                                        <thead><tr><th scope="col"><?= 0 === strpos( $unit['prefix'], 'keluarga_' ) ? 'Anggota Keluarga' : 'No.' ?></th><?php foreach ( reset( $unit['rows'] ) as $question ) : ?><th scope="col"><?= esc_html( 0 === strpos( $unit['prefix'], 'keluarga_' ) ? preg_replace( '/^.*\s+[—-]\s+/u', '', (string) $question['title'] ) : preg_replace( [ '/\s*[—-]\s*\d+$/u', '/^Prioritas\s+\d+\s*[—-]\s*/u' ], '', (string) $question['title'] ) ) ?></th><?php endforeach; ?></tr></thead>
                                                        <tbody>
                                                            <?php foreach ( $unit['rows'] as $row_number => $row_questions ) : ?>
                                                                <?php $row_heading = 0 === strpos( $unit['prefix'], 'keluarga_' ) ? preg_replace( '/\s+[—-]\s+.*$/u', '', (string) reset( $row_questions )['title'] ) : (string) $row_number; ?>
                                                                <tr><th scope="row"><?= esc_html( $row_heading ) ?></th>
                                                                    <?php foreach ( $row_questions as $question ) : ?><td><?= $render_question( $question, true ) ?></td><?php endforeach; ?>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php if ( $optional_fields_open ) : ?></div></details><?php endif; ?>
                                <?php endif; ?>
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
                    <div class="daw-recruitment__form-actions"><a class="daw-recruitment__button daw-recruitment__button--muted" href="<?= esc_url( recruitment_get_public_url( 'job-detail', [ 'job_id' => $job_id ] ) ) ?>">← Batal</a><button class="daw-recruitment__button daw-recruitment__submit" type="submit" <?= $ui_preview ? 'disabled aria-disabled="true" title="Mode preview"' : 'disabled' ?> data-submit-application><?= $ui_preview ? 'Preview - Lamaran Nonaktif' : 'Submit Lamaran' ?></button></div>
                </form>
            <?php else : ?>
                <section class="daw-recruitment__panel"><h1>Form Lamaran Tidak Tersedia</h1><?php foreach ( $errors as $error ) : ?><p><?= esc_html( $error ) ?></p><?php endforeach; ?><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Lihat Lowongan</a></section>
            <?php endif; ?>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
