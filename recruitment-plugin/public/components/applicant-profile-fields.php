<?php
$applicant_profile = is_array( $applicant_profile ?? null ) ? $applicant_profile : [];
$profile_details = is_array( $applicant_profile['profile_details'] ?? null ) ? $applicant_profile['profile_details'] : [];
$profile_documents = is_array( $profile_details['documents'] ?? null ) ? $profile_details['documents'] : [];
$profile_work_experiences = is_array( $profile_details['work_experiences'] ?? null ) && $profile_details['work_experiences']
    ? $profile_details['work_experiences']
    : [ [ 'company' => '', 'position' => '', 'period' => '' ] ];
$profile_family_members = is_array( $profile_details['family_members'] ?? null ) && $profile_details['family_members']
    ? $profile_details['family_members']
    : [ [ 'name' => '', 'relationship' => '', 'age' => '', 'education' => '' ] ];

$profile_social_media = [];
foreach ( array_slice( (array) ( $profile_details['social_media'] ?? [] ), 0, 10 ) as $social_entry ) {
    if ( ! is_array( $social_entry ) ) {
        continue;
    }
    $profile_social_media[] = [
        'platform' => is_scalar( $social_entry['platform'] ?? null ) ? (string) $social_entry['platform'] : '',
        'username' => is_scalar( $social_entry['username'] ?? null ) ? (string) $social_entry['username'] : '',
    ];
}
if ( ! $profile_social_media ) {
    $profile_social_media = [ [ 'platform' => '', 'username' => '' ] ];
}
$social_platform_options = [ 'Instagram', 'Facebook', 'TikTok', 'X / Twitter', 'YouTube', 'LinkedIn', 'Lainnya' ];
$education_options = [ 'SMA/SMK', 'D1', 'D2', 'D3', 'D4/S1', 'S2', 'S3' ];
$sim_options = [ 'Tidak ada', 'A', 'B', 'C', 'A dan C', 'B dan C', 'A, B, dan C' ];

$application_profile_answers = ( new Recruitment_Application() )->prefill_profile_answers( $applicant_profile );
$application_profile_sections = ( new Recruitment_Application() )->get_reference_form_sections();

// Index all reference questions by key for reliable field rendering
$reference_questions = [];
foreach ( $application_profile_sections as $sec ) {
    foreach ( $sec['questions'] as $q ) {
        $reference_questions[ (string) $q['key'] ] = $q;
    }
}

// Prepare repeated tables
$application_table_prefixes = [ 'pendidikan_formal', 'pendidikan_nonformal', 'bahasa', 'riwayat_pekerjaan', 'atasan_dan_bawahan', 'keluarga_asal' ];
$application_table_titles = [
    'pendidikan_formal' => 'Pendidikan Formal',
    'pendidikan_nonformal' => 'Pendidikan Nonformal / Kursus',
    'bahasa' => 'Kemampuan Bahasa',
    'riwayat_pekerjaan' => 'Detail Gaji dan Alasan Pindah Pekerjaan Sebelumnya',
    'atasan_dan_bawahan' => 'Informasi Atasan dan Bawahan',
    'keluarga_asal' => 'Keluarga Asal (Orang Tua dan Saudara Kandung)',
];

$table_units = [];
foreach ( $application_table_prefixes as $prefix ) {
    $table_units[ $prefix ] = [ 'prefix' => $prefix, 'rows' => [] ];
}
foreach ( $application_profile_sections as $sec ) {
    foreach ( $sec['questions'] as $q ) {
        $key = (string) $q['key'];
        if ( preg_match( '/^(' . implode( '|', $application_table_prefixes ) . ')_(\d+)_(.+)$/', $key, $matches ) ) {
            $table_units[ $matches[1] ]['rows'][ $matches[2] ][ $matches[3] ] = $q;
        } elseif ( preg_match( '/^(keluarga_asal)_(.+)_(nama|jenis_kelamin|tempat_tanggal_lahir|pendidikan|pekerjaan)$/', $key, $matches ) ) {
            $table_units[ $matches[1] ]['rows'][ $matches[2] ][ $matches[3] ] = $q;
        }
    }
}

$registration = ! empty( $applicant_profile_registration );
$profile_disabled = ! empty( $preview ) || ! empty( $applicant_profile_disabled );
$profile_readonly = ! empty( $applicant_profile_readonly );
$profile_edit_url = recruitment_get_public_url( 'account-profile', [ 'edit' => '1' ] );
$profile_readonly_value = static function ( $value ): string {
    $value = trim( (string) $value );
    return '' !== $value
        ? esc_html( $value )
        : '<span class="daw-profile__empty-value">—</span>';
};

// Helper: render application table
$render_app_table = static function ( string $prefix, bool $include_row_1 = true, array $visible_keys = [] ) use (
    $table_units,
    $application_table_titles,
    $application_profile_answers,
    $profile_details,
    $education_options,
    $registration,
    $profile_disabled,
    $profile_readonly,
    $profile_readonly_value
): void {
    if ( empty( $table_units[ $prefix ]['rows'] ) ) {
        return;
    }
    $unit = $table_units[ $prefix ];
    $rows = $unit['rows'];
    if ( ! $include_row_1 && isset( $rows[1] ) ) {
        unset( $rows[1] );
    }
    if ( empty( $rows ) ) {
        return;
    }

    $first_row = reset( $rows );
    $fields = array_keys( $first_row );
    if ( $visible_keys ) {
        $fields = array_values( array_intersect( $fields, $visible_keys ) );
    }
    if ( empty( $fields ) ) {
        return;
    }

    $table_id = 'daw-table-' . sanitize_key( $prefix );
    $table_row_count = count( $rows );
    $last_filled_row = 1;
    $row_index = 0;
    foreach ( $rows as $row_key => $row_questions ) {
        ++$row_index;
        foreach ( $fields as $f ) {
            if ( ! isset( $row_questions[ $f ] ) ) {
                continue;
            }
            $k = (string) $row_questions[ $f ]['key'];
            $ans = (string) ( $application_profile_answers[ $k ] ?? '' );
            if ( '' !== trim( $ans ) ) {
                $last_filled_row = max( $last_filled_row, $row_index );
                break;
            }
        }
    }

    // If readonly mode:
    if ( $profile_readonly ) {
        ?>
        <div class="daw-application__table-group">
            <h3><?= esc_html( $application_table_titles[ $prefix ] ?? 'Informasi Berulang' ) ?></h3>
            <div class="daw-application__table-scroll">
                <table class="daw-application__table">
                    <thead>
                        <tr>
                            <th scope="col"><?= 0 === strpos( $prefix, 'keluarga_' ) ? 'Anggota Keluarga' : 'No.' ?></th>
                            <?php foreach ( $fields as $f ) : ?>
                                <th scope="col"><?= esc_html( 0 === strpos( $prefix, 'keluarga_' ) ? preg_replace( '/^.*\s+[—-]\s+/u', '', (string) $first_row[ $f ]['title'] ) : preg_replace( [ '/\s*[—-]\s*\d+$/u', '/^Prioritas\s+\d+\s*[—-]\s*/u' ], '', (string) $first_row[ $f ]['title'] ) ) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rendered_count = 0; ?>
                        <?php foreach ( $rows as $row_number => $row_questions ) : ?>
                            <?php
                            $has_data = false;
                            foreach ( $fields as $f ) {
                                $k = (string) ( $row_questions[ $f ]['key'] ?? '' );
                                if ( '' !== trim( (string) ( $application_profile_answers[ $k ] ?? '' ) ) ) {
                                    $has_data = true;
                                    break;
                                }
                            }
                            if ( ! $has_data && 1 !== (int) $row_number && 'pendidikan_formal' !== $prefix ) {
                                continue;
                            }
                            ++$rendered_count;
                            $row_heading = 0 === strpos( $prefix, 'keluarga_' ) ? preg_replace( '/\s+[—-]\s+.*$/u', '', (string) reset( $row_questions )['title'] ) : (string) $row_number;
                            ?>
                            <tr>
                                <th scope="row"><?= esc_html( $row_heading ) ?></th>
                                <?php foreach ( $fields as $f ) : ?>
                                    <?php
                                    $q = $row_questions[ $f ] ?? null;
                                    $val = $q ? (string) ( $application_profile_answers[ $q['key'] ] ?? '' ) : '';
                                    ?>
                                    <td><?= $profile_readonly_value( $val ) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ( 0 === $rendered_count ) : ?>
                            <tr><td colspan="<?= count( $fields ) + 1 ?>"><?= $profile_readonly_value( '' ) ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return;
    }

    // Editable mode:
    ?>
    <div class="daw-application__table-group">
        <h3><?= esc_html( $application_table_titles[ $prefix ] ?? 'Informasi Berulang' ) ?></h3>
        <div class="daw-application__table-scroll">
            <table class="daw-application__table">
                <thead>
                    <tr>
                        <th scope="col"><?= 0 === strpos( $prefix, 'keluarga_' ) ? 'Anggota Keluarga' : 'No.' ?></th>
                        <?php foreach ( $fields as $f ) : ?>
                            <?php $column_title = 0 === strpos( $prefix, 'keluarga_' ) ? preg_replace( '/^.*\s+[—-]\s+/u', '', (string) $first_row[ $f ]['title'] ) : preg_replace( [ '/\s*[—-]\s*\d+$/u', '/^Prioritas\s+\d+\s*[—-]\s*/u' ], '', (string) $first_row[ $f ]['title'] ); ?>
                            <th scope="col"><?= esc_html( $column_title ) ?><?php if ( $registration && 'pendidikan_formal' === $prefix && in_array( $f, [ 'tingkat', 'nama_sekolah', 'program_studi', 'ipk', 'tahun_lulus' ], true ) ) : ?> <span class="daw-registration__required-mark" aria-label="wajib diisi">*</span><?php endif; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody data-application-table-body="<?= esc_attr( $table_id ) ?>">
                    <?php $row_pos = 0; ?>
                    <?php foreach ( $rows as $row_number => $row_questions ) : ?>
                        <?php
                        ++$row_pos;
                        $is_first_row = 1 === $row_pos;
                        $row_hidden = $row_pos > $last_filled_row;
                        $row_heading = 0 === strpos( $prefix, 'keluarga_' ) ? preg_replace( '/\s+[—-]\s+.*$/u', '', (string) reset( $row_questions )['title'] ) : (string) $row_number;
                        ?>
                        <tr data-application-table-row <?= $row_hidden ? 'hidden' : '' ?>>
                            <th scope="row"><?= esc_html( $row_heading ) ?></th>
                            <?php foreach ( $fields as $f ) : ?>
                                <?php
                                $q = $row_questions[ $f ];
                                $q_key = (string) $q['key'];
                                $q_val = (string) ( $application_profile_answers[ $q_key ] ?? '' );
                                $q_name = 'application_answers[' . $q_key . ']';
                                $is_row_disabled = $profile_disabled || $row_hidden;

                                // Special binding for row 1 formal education
                                if ( 'pendidikan_formal' === $prefix && $is_first_row ) {
                                    if ( 'tingkat' === $f ) {
                                        ?>
                                        <td>
                                            <select name="education_level" aria-label="Tingkat Pendidikan" required <?= $is_row_disabled ? 'disabled' : '' ?>>
                                                <option value="">Pilih pendidikan</option>
                                                <?php foreach ( $education_options as $opt ) : ?>
                                                    <option value="<?= esc_attr( $opt ) ?>" <?= selected( $profile_details['education_level'] ?? $q_val, $opt, false ) ?>><?= esc_html( $opt ) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="hidden" name="<?= esc_attr( $q_name ) ?>" value="<?= esc_attr( $profile_details['education_level'] ?? $q_val ) ?>">
                                        </td>
                                        <?php
                                        continue;
                                    } elseif ( 'nama_sekolah' === $f ) {
                                        ?>
                                        <td>
                                            <input name="institution" aria-label="Nama Sekolah / Institusi" value="<?= esc_attr( $profile_details['institution'] ?? $q_val ) ?>" maxlength="255" required <?= $is_row_disabled ? 'disabled' : '' ?>>
                                            <input type="hidden" name="<?= esc_attr( $q_name ) ?>" value="<?= esc_attr( $profile_details['institution'] ?? $q_val ) ?>">
                                        </td>
                                        <?php
                                        continue;
                                    } elseif ( 'program_studi' === $f ) {
                                        ?>
                                        <td>
                                            <input name="major" aria-label="Program Studi" value="<?= esc_attr( $profile_details['major'] ?? $q_val ) ?>" maxlength="255" required <?= $is_row_disabled ? 'disabled' : '' ?>>
                                            <input type="hidden" name="<?= esc_attr( $q_name ) ?>" value="<?= esc_attr( $profile_details['major'] ?? $q_val ) ?>">
                                        </td>
                                        <?php
                                        continue;
                                    } elseif ( 'ipk' === $f ) {
                                        ?>
                                        <td>
                                            <input type="number" name="gpa" aria-label="IPK" value="<?= esc_attr( $profile_details['gpa'] ?? $q_val ) ?>" min="0" max="4" step="0.01" required <?= $is_row_disabled ? 'disabled' : '' ?>>
                                            <input type="hidden" name="<?= esc_attr( $q_name ) ?>" value="<?= esc_attr( $profile_details['gpa'] ?? $q_val ) ?>">
                                        </td>
                                        <?php
                                        continue;
                                    } elseif ( 'tahun_lulus' === $f ) {
                                        ?>
                                        <td>
                                            <input type="number" name="graduation_year" aria-label="Tahun Lulus" value="<?= esc_attr( $profile_details['graduation_year'] ?? $q_val ) ?>" min="1950" max="<?= esc_attr( gmdate( 'Y' ) ) ?>" step="1" required <?= $is_row_disabled ? 'disabled' : '' ?>>
                                            <input type="hidden" name="<?= esc_attr( $q_name ) ?>" value="<?= esc_attr( $profile_details['graduation_year'] ?? $q_val ) ?>">
                                        </td>
                                        <?php
                                        continue;
                                    }
                                }
                                ?>
                                <td>
                                    <?php if ( in_array( $q['type'], [ 'dropdown', 'single_choice' ], true ) ) : ?>
                                        <select name="<?= esc_attr( $q_name ) ?>" aria-label="<?= esc_attr( $q['title'] ) ?>" <?= $is_row_disabled ? 'disabled' : '' ?>>
                                            <option value="">Pilih jawaban</option>
                                            <?php foreach ( $q['options'] as $option ) : ?>
                                                <?php $opt_val = (string) ( $option['value'] ?? '' ); ?>
                                                <option value="<?= esc_attr( $opt_val ) ?>" <?= selected( $q_val, $opt_val, false ) ?>><?= esc_html( $option['label'] ?? $opt_val ) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ( 'paragraph' === $q['type'] ) : ?>
                                        <textarea name="<?= esc_attr( $q_name ) ?>" aria-label="<?= esc_attr( $q['title'] ) ?>" rows="3" maxlength="10000" <?= $is_row_disabled ? 'disabled' : '' ?>><?= esc_textarea( $q_val ) ?></textarea>
                                    <?php else : ?>
                                        <?php $in_type = [ 'number' => 'number', 'date' => 'date', 'email' => 'email', 'phone' => 'tel' ][ $q['type'] ] ?? 'text'; ?>
                                        <input type="<?= esc_attr( $in_type ) ?>" name="<?= esc_attr( $q_name ) ?>" aria-label="<?= esc_attr( $q['title'] ) ?>" value="<?= esc_attr( $q_val ) ?>" <?= $is_row_disabled ? 'disabled' : '' ?>>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ( $table_row_count > 1 ) : ?>
            <button type="button" class="daw-profile__add-row daw-application__table-add" data-add-application-table-row="<?= esc_attr( $table_id ) ?>" data-max-application-table-rows="<?= esc_attr( (string) $table_row_count ) ?>" <?= $profile_disabled || $last_filled_row >= $table_row_count ? 'disabled' : '' ?>>+ Tambah Baris</button>
        <?php endif; ?>
    </div>
    <?php
};

// -------------------------------------------------------------
// READONLY MODE
// -------------------------------------------------------------
if ( $profile_readonly ) :
    $profile_work_experiences = array_values( array_filter( $profile_work_experiences, static fn ( $item ): bool => is_array( $item ) && '' !== trim( implode( '', array_map( 'strval', $item ) ) ) ) );
    $profile_family_members = array_values( array_filter( $profile_family_members, static fn ( $item ): bool => is_array( $item ) && '' !== trim( implode( '', array_map( 'strval', $item ) ) ) ) );
    $profile_social_media = array_values( array_filter( $profile_social_media, static fn ( $item ): bool => is_array( $item ) && '' !== trim( (string) ( $item['platform'] ?? '' ) . (string) ( $item['username'] ?? '' ) ) ) );

    $render_qa_readonly = static function ( string $q_key, string $custom_title = '' ) use ( $reference_questions, $application_profile_answers, $profile_readonly_value ): void {
        $q = $reference_questions[ $q_key ] ?? null;
        $title = $custom_title ?: ( $q['title'] ?? $q_key );
        $val = (string) ( $application_profile_answers[ $q_key ] ?? '' );
        ?>
        <tr>
            <th scope="row"><?= esc_html( $title ) ?></th>
            <td><?= $profile_readonly_value( $val ) ?></td>
        </tr>
        <?php
    };
    ?>
    <div class="daw-profile-fields daw-profile-fields--readonly">
        <!-- 1. Data Pribadi -->
        <details class="daw-account__card daw-profile__section daw-profile__accordion" open>
            <summary class="daw-profile__accordion-summary">
                <h2 class="daw-profile__accordion-heading">Data Pribadi</h2>
                <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
            </summary>
            <div class="daw-profile__accordion-body">
                <div class="daw-profile__grid">
                    <div class="daw-profile__value daw-profile__value--full"><span>FOTO PROFIL</span><?php if ( ! empty( $profile_documents['profile_photo'] ) ) : ?><img class="daw-profile__photo-preview" src="<?= esc_url( recruitment_get_public_url( 'applicant-file', [ 'document' => 'profile_photo' ] ) ) ?>" alt="Foto profil pelamar"><?php elseif ( ! empty( $applicant_profile_preview_photo_url ) ) : ?><img class="daw-profile__photo-preview" src="<?= esc_url( $applicant_profile_preview_photo_url ) ?>" alt="Foto profil preview"><?php else : ?><strong><?= $profile_readonly_value( '' ) ?></strong><?php endif; ?></div>
                    <div class="daw-profile__value"><span>NAMA LENGKAP</span><strong><?= $profile_readonly_value( $applicant_profile['full_name'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value"><span>GENDER</span><strong><?= $profile_readonly_value( $applicant_profile['gender'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value"><span>TEMPAT LAHIR</span><strong><?= $profile_readonly_value( $applicant_profile['birth_place'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value"><span>TANGGAL LAHIR</span><?php $birth_timestamp = strtotime( (string) ( $applicant_profile['birth_date'] ?? '' ) ); ?><strong><?= $birth_timestamp ? esc_html( wp_date( 'j F Y', $birth_timestamp ) ) : $profile_readonly_value( '' ) ?></strong></div>
                    <div class="daw-profile__value daw-profile__value--full"><span>ALAMAT SAAT INI</span><strong class="daw-profile__readonly-text"><?= $profile_readonly_value( $applicant_profile['address'] ?? '' ) ?></strong></div>

                    <!-- Identitas Administrasi Lamaran ditaruh tepat di bawah Alamat Saat Ini -->
                    <div class="daw-profile__value daw-profile__value--full"><span>ALAMAT SESUAI KTP</span><strong class="daw-profile__readonly-text"><?= $profile_readonly_value( $application_profile_answers['alamat_ktp'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value"><span>NOMOR KTP</span><strong><?= $profile_readonly_value( $application_profile_answers['nomor_ktp'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value"><span>AGAMA / KEPERCAYAAN</span><strong><?= $profile_readonly_value( $application_profile_answers['agama_kepercayaan'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value"><span>KEWARGANEGARAAN</span><strong><?= $profile_readonly_value( $application_profile_answers['kewarganegaraan'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value"><span>SIM YANG DIMILIKI</span><strong><?= $profile_readonly_value( $application_profile_answers['jenis_sim'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value"><span>SUMBER INFORMASI LOWONGAN</span><strong><?= $profile_readonly_value( $application_profile_answers['sumber_informasi_lowongan'] ?? '' ) ?></strong></div>

                    <div class="daw-profile__value"><span>NOMOR TELEPON / WHATSAPP</span><strong><?= $profile_readonly_value( $applicant_profile['phone'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value"><span>EMAIL AKTIF</span><strong><?= $profile_readonly_value( $applicant_profile['email'] ?? '' ) ?></strong></div>
                </div>
            </div>
        </details>

        <!-- 2. Dokumen -->
        <details class="daw-account__card daw-profile__section daw-profile__accordion" open>
            <summary class="daw-profile__accordion-summary">
                <h2 class="daw-profile__accordion-heading">Dokumen</h2>
                <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
            </summary>
            <div class="daw-profile__accordion-body">
                <div class="daw-profile__grid">
                    <div class="daw-profile__value"><span>CV / RESUME</span><?php if ( ! empty( $profile_documents['resume'] ) ) : ?><a class="daw-profile__document-link" href="<?= esc_url( recruitment_get_public_url( 'applicant-file', [ 'document' => 'resume' ] ) ) ?>"><?= esc_html( $profile_documents['resume']['name'] ?? 'Lihat CV tersimpan' ) ?></a><?php else : ?><strong><?= $profile_readonly_value( '' ) ?></strong><?php endif; ?></div>
                    <div class="daw-profile__value"><span>PORTOFOLIO</span><?php if ( ! empty( $profile_documents['portfolio'] ) ) : ?><a class="daw-profile__document-link" href="<?= esc_url( recruitment_get_public_url( 'applicant-file', [ 'document' => 'portfolio' ] ) ) ?>"><?= esc_html( $profile_documents['portfolio']['name'] ?? 'Lihat portofolio tersimpan' ) ?></a><?php else : ?><strong><?= $profile_readonly_value( '' ) ?></strong><?php endif; ?></div>
                </div>
            </div>
        </details>

        <!-- 3. Media Sosial -->
        <details class="daw-account__card daw-profile__section daw-profile__accordion" open>
            <summary class="daw-profile__accordion-summary">
                <h2 class="daw-profile__accordion-heading">Media Sosial</h2>
                <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
            </summary>
            <div class="daw-profile__accordion-body">
                <?php if ( $profile_social_media ) : ?>
                    <div class="daw-profile__social-list">
                        <?php foreach ( $profile_social_media as $social_entry ) : ?>
                            <div class="daw-profile__value"><span><?= esc_html( $social_entry['platform'] ?? 'Platform' ) ?></span><strong><?= $profile_readonly_value( $social_entry['username'] ?? '' ) ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?><div class="daw-profile__value"><strong><?= $profile_readonly_value( '' ) ?></strong></div><?php endif; ?>
            </div>
        </details>

        <!-- 4. Pendidikan (Unified) -->
        <details class="daw-account__card daw-profile__section daw-profile__accordion" open>
            <summary class="daw-profile__accordion-summary">
                <h2 class="daw-profile__accordion-heading">Pendidikan</h2>
                <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
            </summary>
            <div class="daw-profile__accordion-body">
                <p class="daw-profile__section-note">Pendidikan formal, kursus/pendidikan nonformal, dan kemampuan bahasa.</p>
                <?php
                $render_app_table( 'pendidikan_formal', true );
                $render_app_table( 'pendidikan_nonformal', true );
                $render_app_table( 'bahasa', true );
                ?>
            </div>
        </details>

        <!-- 5. Pengalaman Kerja (Tabel) -->
        <details class="daw-account__card daw-profile__section daw-profile__accordion" open>
            <summary class="daw-profile__accordion-summary">
                <h2 class="daw-profile__accordion-heading">Pengalaman Kerja</h2>
                <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
            </summary>
            <div class="daw-profile__accordion-body">
                <div class="daw-application__table-scroll">
                    <table class="daw-application__table daw-profile__work-table">
                        <thead>
                            <tr>
                                <th scope="col" style="width: 50px;">No.</th>
                                <th scope="col">Nama Perusahaan</th>
                                <th scope="col">Jabatan / Posisi</th>
                                <th scope="col">Periode Kerja</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( $profile_work_experiences ) : ?>
                                <?php foreach ( $profile_work_experiences as $index => $experience ) : ?>
                                    <tr>
                                        <td><?= esc_html( (string) ( $index + 1 ) ) ?></td>
                                        <td><?= $profile_readonly_value( $experience['company'] ?? '' ) ?></td>
                                        <td><?= $profile_readonly_value( $experience['position'] ?? '' ) ?></td>
                                        <td><?= $profile_readonly_value( $experience['period'] ?? '' ) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr><td colspan="4"><?= $profile_readonly_value( '' ) ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        <!-- 6. Riwayat dan Evaluasi Pekerjaan (Tepat di bawah Pengalaman Kerja) -->
        <details class="daw-account__card daw-profile__section daw-profile__accordion" open>
            <summary class="daw-profile__accordion-summary">
                <h2 class="daw-profile__accordion-heading">Riwayat dan Evaluasi Pekerjaan</h2>
                <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
            </summary>
            <div class="daw-profile__accordion-body">
                <p class="daw-profile__section-note">Informasi atasan langsung, evaluasi kinerja, dan pengalaman perubahan.</p>
                <?php
                $render_app_table( 'atasan_dan_bawahan', true );
                $render_app_table( 'riwayat_pekerjaan', true, [ 'gaji_pokok', 'alasan_pindah' ] );
                ?>
                <div class="daw-profile__grid" style="margin-top: 14px;">
                    <div class="daw-profile__value daw-profile__value--full"><span>PERUBAHAN YANG PERNAH DILAKUKAN DAN HASILNYA</span><strong><?= $profile_readonly_value( $application_profile_answers['perubahan_di_perusahaan_terdahulu'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value daw-profile__value--full"><span>PENGALAMAN PERUBAHAN POSITIF</span><strong><?= $profile_readonly_value( $application_profile_answers['pengalaman_perubahan_positif'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value"><span>BERSEDIA DIHUBUNGI ATASAN SEBELUMNYA?</span><strong><?= $profile_readonly_value( $application_profile_answers['kesediaan_kontak_atasan'] ?? '' ) ?></strong></div>
                </div>
            </div>
        </details>

        <!-- 7. Status Keluarga dan Lingkungan -->
        <details class="daw-account__card daw-profile__section daw-profile__accordion" open>
            <summary class="daw-profile__accordion-summary">
                <h2 class="daw-profile__accordion-heading">Status Keluarga dan Lingkungan</h2>
                <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
            </summary>
            <div class="daw-profile__accordion-body">
                <div class="daw-profile__grid">
                    <div class="daw-profile__value"><span>STATUS PERNIKAHAN</span><strong><?= $profile_readonly_value( $profile_details['marital_status'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value"><span>TANGGAL PERTUNANGAN / PERNIKAHAN</span><strong><?= $profile_readonly_value( $application_profile_answers['tanggal_pertunangan_atau_pernikahan'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value"><span>URUTAN ANDA &amp; JUMLAH SAUDARA</span><strong><?= $profile_readonly_value( $application_profile_answers['urutan_dalam_saudara'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value"><span>STATUS RUMAH TINGGAL</span><strong><?= $profile_readonly_value( $application_profile_answers['status_tempat_tinggal'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value"><span>LAMA KOST / KONTRAK</span><strong><?= $profile_readonly_value( $application_profile_answers['lama_kost_atau_kontrak'] ?? '' ) ?></strong></div>
                </div>

                <h3 class="daw-profile__subheading">Hubungan dengan Karyawan DAW</h3>
                <div class="daw-application__table-scroll">
                    <table class="daw-application__table daw-profile__qa-table">
                        <thead><tr><th scope="col">Pertanyaan</th><th scope="col">Jawaban</th></tr></thead>
                        <tbody>
                            <?php $render_qa_readonly( 'hubungan_keluarga_karyawan_daw' ); ?>
                            <?php $render_qa_readonly( 'hubungan_pribadi_karyawan_daw' ); ?>
                        </tbody>
                    </table>
                </div>
                <div class="daw-profile__grid daw-profile__related-answers">
                    <div class="daw-profile__value daw-profile__value--full"><span>NAMA &amp; JABATAN KELUARGA DI DAW</span><strong><?= $profile_readonly_value( $application_profile_answers['nama_keluarga_karyawan_daw'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value daw-profile__value--full"><span>NAMA &amp; JABATAN RELASI PRIBADI DI DAW</span><strong><?= $profile_readonly_value( $application_profile_answers['nama_hubungan_pribadi_karyawan_daw'] ?? '' ) ?></strong></div>
                </div>

                <h3 class="daw-profile__subheading">Susunan Keluarga Inti</h3>
                <?php if ( $profile_family_members ) : ?>
                    <div class="daw-application__table-scroll">
                        <table class="daw-application__table daw-profile__family-table">
                            <thead><tr><th scope="col">No.</th><th scope="col">Nama Keluarga</th><th scope="col">Hubungan</th><th scope="col">Usia</th><th scope="col">Pendidikan Terakhir</th></tr></thead>
                            <tbody>
                                <?php foreach ( $profile_family_members as $index => $member ) : ?>
                                    <tr>
                                        <th scope="row"><?= esc_html( (string) ( $index + 1 ) ) ?></th>
                                        <td><?= $profile_readonly_value( $member['name'] ?? '' ) ?></td>
                                        <td><?= $profile_readonly_value( $member['relationship'] ?? '' ) ?></td>
                                        <td><?= $profile_readonly_value( $member['age'] ?? '' ) ?></td>
                                        <td><?= $profile_readonly_value( $member['education'] ?? '' ) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?><div class="daw-profile__value"><strong><?= $profile_readonly_value( '' ) ?></strong></div><?php endif; ?>

                <?php $render_app_table( 'keluarga_asal', true ); ?>

                <h3 class="daw-profile__subheading">Riwayat Kesehatan</h3>
                <div class="daw-application__table-scroll">
                    <table class="daw-application__table daw-profile__qa-table">
                        <thead><tr><th scope="col">Pertanyaan</th><th scope="col">Jawaban</th></tr></thead>
                        <tbody>
                            <?php $render_qa_readonly( 'riwayat_diabetes' ); ?>
                            <?php $render_qa_readonly( 'riwayat_darah_tinggi' ); ?>
                            <?php $render_qa_readonly( 'riwayat_buta_warna' ); ?>
                            <?php $render_qa_readonly( 'riwayat_wasir' ); ?>
                            <?php $render_qa_readonly( 'riwayat_tbc' ); ?>
                        </tbody>
                    </table>
                </div>
                <div class="daw-profile__grid daw-profile__related-answers">
                    <div class="daw-profile__value daw-profile__value--full"><span>RIWAYAT ALERGI</span><strong><?= $profile_readonly_value( $application_profile_answers['riwayat_alergi'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value daw-profile__value--full"><span>RIWAYAT SAKIT LAIN</span><strong><?= $profile_readonly_value( $application_profile_answers['riwayat_sakit_lain'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value daw-profile__value--full"><span>RIWAYAT PENYAKIT KELUARGA</span><strong><?= $profile_readonly_value( $application_profile_answers['riwayat_penyakit_keluarga'] ?? '' ) ?></strong></div>
                </div>
            </div>
        </details>

        <!-- 8. Minat dan Konsep Pribadi (Tabel 2 Kolom) -->
        <details class="daw-account__card daw-profile__section daw-profile__accordion" open>
            <summary class="daw-profile__accordion-summary">
                <h2 class="daw-profile__accordion-heading">Minat dan Konsep Pribadi</h2>
                <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
            </summary>
            <div class="daw-profile__accordion-body">
                <p class="daw-profile__section-note">Motivasi, ekspektasi, fasilitas, dan preferensi kerja.</p>
                <div class="daw-application__table-scroll">
                    <table class="daw-application__table daw-profile__qa-table">
                        <thead>
                            <tr>
                                <th scope="col" style="width: 38%;">Pertanyaan</th>
                                <th scope="col" style="width: 62%;">Jawaban</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $render_qa_readonly( 'motivasi_bergabung' );
                            $render_qa_readonly( 'pengetahuan_perusahaan' );
                            $render_qa_readonly( 'harapan_bekerja' );
                            $render_qa_readonly( 'gaji_yang_diharapkan' );
                            $render_qa_readonly( 'fasilitas_yang_diharapkan' );
                            $render_qa_readonly( 'tunjangan_transportasi_saat_ini' );
                            $render_qa_readonly( 'tunjangan_makan_saat_ini' );
                            $render_qa_readonly( 'tunjangan_komunikasi_saat_ini' );
                            $render_qa_readonly( 'tunjangan_kehadiran_saat_ini' );
                            $render_qa_readonly( 'car_ownership_program_saat_ini' );
                            $render_qa_readonly( 'house_ownership_program_saat_ini' );
                            $render_qa_readonly( 'asuransi_rawat_jalan_saat_ini' );
                            $render_qa_readonly( 'asuransi_rawat_inap_saat_ini' );
                            $render_qa_readonly( 'insentif_saat_ini' );
                            $render_qa_readonly( 'bonus_tahunan_saat_ini' );
                            $render_qa_readonly( 'laptop_ownership_program_saat_ini' );
                            $render_qa_readonly( 'fasilitas_lain_saat_ini' );
                            $render_qa_readonly( 'bersedia_background_check' );
                            $render_qa_readonly( 'kapan_mulai_bekerja' );
                            $render_qa_readonly( 'minat_jenis_pekerjaan_1' );
                            $render_qa_readonly( 'minat_jenis_pekerjaan_2' );
                            $render_qa_readonly( 'minat_jenis_pekerjaan_3' );
                            $render_qa_readonly( 'minat_bidang_industri_1' );
                            $render_qa_readonly( 'minat_bidang_industri_2' );
                            $render_qa_readonly( 'minat_bidang_industri_3' );
                            $render_qa_readonly( 'jabatan_cita_cita' );
                            $render_qa_readonly( 'upaya_mencapai_jabatan' );
                            $render_qa_readonly( 'bersedia_penempatan_luar_kota' );
                            $render_qa_readonly( 'lingkungan_kerja_disukai' );
                            $render_qa_readonly( 'lingkungan_kerja_tidak_disukai' );
                            $render_qa_readonly( 'tipe_orang_disukai' );
                            $render_qa_readonly( 'tipe_orang_tidak_disukai' );
                            $render_qa_readonly( 'kelebihan_diri' );
                            $render_qa_readonly( 'kelemahan_diri' );
                            $render_qa_readonly( 'kesulitan_mengambil_keputusan' );
                            $render_qa_readonly( 'mengenal_karyawan_daw' );
                            ?>
                            <tr>
                                <th scope="row">Keahlian Bahasa, Sertifikasi, Skill, dan Minat Lainnya</th>
                                <td><?= $profile_readonly_value( $profile_details['interests'] ?? '' ) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        <!-- 9. Aktivitas Sosial dan Kegiatan Lain -->
        <details class="daw-account__card daw-profile__section daw-profile__accordion" open>
            <summary class="daw-profile__accordion-summary">
                <h2 class="daw-profile__accordion-heading">Aktivitas Sosial dan Kegiatan Lain</h2>
                <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
            </summary>
            <div class="daw-profile__accordion-body">
                <div class="daw-profile__grid">
                    <div class="daw-profile__value daw-profile__value--full"><span>APA HOBI ANDA?</span><strong><?= $profile_readonly_value( $application_profile_answers['hobi'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value daw-profile__value--full"><span>BAGAIMANA CARA ANDA MENGISI WAKTU LUANG?</span><strong><?= $profile_readonly_value( $application_profile_answers['kegiatan_waktu_luang'] ?? '' ) ?></strong></div>
                    <div class="daw-profile__value daw-profile__value--full"><span>PENGALAMAN ORGANISASI</span><strong><?= $profile_readonly_value( $application_profile_answers['pengalaman_organisasi'] ?? '' ) ?></strong></div>
                </div>
            </div>
        </details>
    </div>
    <?php
    return;
endif;

// -------------------------------------------------------------
// EDITABLE FORM MODE
// -------------------------------------------------------------
$render_qa_input = static function ( string $q_key, string $custom_title = '', bool $is_required = false ) use (
    $reference_questions,
    $application_profile_answers,
    $profile_disabled
): void {
    $q = $reference_questions[ $q_key ] ?? null;
    $title = $custom_title ?: ( $q['title'] ?? $q_key );
    $type = $q['type'] ?? 'short';
    $val = (string) ( $application_profile_answers[ $q_key ] ?? '' );
    $field_name = 'application_answers[' . $q_key . ']';
    ?>
    <tr>
        <th scope="row"><?= esc_html( $title ) ?><?php if ( $is_required ) : ?> <em style="color:var(--daw-red);">*</em><?php endif; ?></th>
        <td>
            <?php if ( in_array( $type, [ 'dropdown', 'single_choice' ], true ) ) : ?>
                <select name="<?= esc_attr( $field_name ) ?>" aria-label="<?= esc_attr( $title ) ?>" <?= $profile_disabled ? 'disabled' : '' ?> <?= $is_required ? 'required' : '' ?>>
                    <option value="">Pilih jawaban</option>
                    <?php foreach ( ( $q['options'] ?? [] ) as $opt ) : ?>
                        <?php $oval = (string) ( $opt['value'] ?? '' ); ?>
                        <option value="<?= esc_attr( $oval ) ?>" <?= selected( $val, $oval, false ) ?>><?= esc_html( $opt['label'] ?? $oval ) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ( 'paragraph' === $type ) : ?>
                <textarea name="<?= esc_attr( $field_name ) ?>" aria-label="<?= esc_attr( $title ) ?>" rows="3" maxlength="10000" <?= $profile_disabled ? 'disabled' : '' ?> <?= $is_required ? 'required' : '' ?>><?= esc_textarea( $val ) ?></textarea>
            <?php else : ?>
                <?php $in_type = [ 'number' => 'number', 'date' => 'date', 'email' => 'email', 'phone' => 'tel' ][ $type ] ?? 'text'; ?>
                <input type="<?= esc_attr( $in_type ) ?>" name="<?= esc_attr( $field_name ) ?>" aria-label="<?= esc_attr( $title ) ?>" value="<?= esc_attr( $val ) ?>" maxlength="10000" <?= $profile_disabled ? 'disabled' : '' ?> <?= $is_required ? 'required' : '' ?>>
            <?php endif; ?>
        </td>
    </tr>
    <?php
};
?>
<div class="daw-profile-fields<?= $registration ? ' daw-profile-fields--registration' : '' ?><?= ! $registration ? ' daw-profile-fields--unified' : '' ?>">
    <!-- 1. Data Pribadi (Dropdown Card) -->
    <details class="daw-account__card daw-profile__section daw-profile__accordion" open>
        <summary class="daw-profile__accordion-summary">
            <h2 class="daw-profile__accordion-heading">Data Pribadi</h2>
            <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="daw-profile__accordion-body">
            <div class="daw-profile__grid">
                <label class="daw-profile__value daw-profile__value--full"><span>FOTO PROFIL</span>
                    <?php if ( ! empty( $profile_documents['profile_photo'] ) ) : ?><img class="daw-profile__photo-preview" src="<?= esc_url( recruitment_get_public_url( 'applicant-file', [ 'document' => 'profile_photo' ] ) ) ?>" alt="Foto profil pelamar"><?php elseif ( ! empty( $applicant_profile_preview_photo_url ) ) : ?><img class="daw-profile__photo-preview" src="<?= esc_url( $applicant_profile_preview_photo_url ) ?>" alt="Foto profil preview"><?php endif; ?>
                    <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png" <?= $registration && empty( $profile_documents['profile_photo'] ) ? 'required' : '' ?> <?= $profile_disabled ? 'disabled' : '' ?>>
                    <small>JPG atau PNG, maksimal 5 MB.</small>
                </label>
                <label class="daw-profile__value"><span>NAMA LENGKAP</span><input name="full_name" value="<?= esc_attr( $applicant_profile['full_name'] ?? '' ) ?>" maxlength="255" required <?= $profile_disabled ? 'disabled' : '' ?>></label>
                <label class="daw-profile__value"><span>GENDER</span><select name="gender" required <?= $profile_disabled ? 'disabled' : '' ?>><option value="">Pilih gender</option><option value="Laki-laki" <?= selected( $applicant_profile['gender'] ?? '', 'Laki-laki', false ) ?>>Laki-laki</option><option value="Perempuan" <?= selected( $applicant_profile['gender'] ?? '', 'Perempuan', false ) ?>>Perempuan</option></select></label>
                <label class="daw-profile__value"><span>TEMPAT LAHIR</span><input name="birth_place" value="<?= esc_attr( $applicant_profile['birth_place'] ?? '' ) ?>" maxlength="255" required <?= $profile_disabled ? 'disabled' : '' ?>></label>
                <label class="daw-profile__value"><span>TANGGAL LAHIR</span><input type="date" name="birth_date" value="<?= esc_attr( $applicant_profile['birth_date'] ?? '' ) ?>" max="<?= esc_attr( gmdate( 'Y-m-d' ) ) ?>" required <?= $profile_disabled ? 'disabled' : '' ?>></label>
                <label class="daw-profile__value daw-profile__value--full"><span>ALAMAT SAAT INI</span><textarea name="address" maxlength="100" required <?= $profile_disabled ? 'disabled' : '' ?>><?= esc_textarea( $applicant_profile['address'] ?? '' ) ?></textarea><small>Maksimal 100 karakter.</small></label>

                <!-- Identitas Administrasi Lamaran ditaruh tepat di bawah Alamat Saat Ini -->
                <label class="daw-profile__value daw-profile__value--full"><span>ALAMAT SESUAI KTP</span><textarea name="application_answers[alamat_ktp]" maxlength="10000" rows="3" placeholder="Sesuai dokumen KTP" <?= $profile_disabled ? 'disabled' : '' ?>><?= esc_textarea( $application_profile_answers['alamat_ktp'] ?? '' ) ?></textarea></label>
                <label class="daw-profile__value"><span>NOMOR KTP</span><input name="application_answers[nomor_ktp]" value="<?= esc_attr( $application_profile_answers['nomor_ktp'] ?? '' ) ?>" maxlength="30" placeholder="16 digit NIK" <?= $profile_disabled ? 'disabled' : '' ?>></label>
                <label class="daw-profile__value"><span>AGAMA / KEPERCAYAAN</span><input name="application_answers[agama_kepercayaan]" value="<?= esc_attr( $application_profile_answers['agama_kepercayaan'] ?? '' ) ?>" maxlength="50" <?= $profile_disabled ? 'disabled' : '' ?>></label>
                <label class="daw-profile__value"><span>KEWARGANEGARAAN</span><input name="application_answers[kewarganegaraan]" value="<?= esc_attr( $application_profile_answers['kewarganegaraan'] ?? 'WNI' ) ?>" maxlength="50" <?= $profile_disabled ? 'disabled' : '' ?>></label>
                <label class="daw-profile__value"><span>SIM YANG DIMILIKI</span><select name="application_answers[jenis_sim]" <?= $profile_disabled ? 'disabled' : '' ?>><option value="">Pilih SIM</option><?php foreach ( $sim_options as $sim_opt ) : ?><option value="<?= esc_attr( $sim_opt ) ?>" <?= selected( $application_profile_answers['jenis_sim'] ?? '', $sim_opt, false ) ?>><?= esc_html( $sim_opt ) ?></option><?php endforeach; ?></select></label>
                <label class="daw-profile__value"><span>SUMBER INFORMASI LOWONGAN</span><input name="application_answers[sumber_informasi_lowongan]" value="<?= esc_attr( $application_profile_answers['sumber_informasi_lowongan'] ?? '' ) ?>" maxlength="255" placeholder="Contoh: Website, Instagram, Teman" <?= $profile_disabled ? 'disabled' : '' ?>></label>

                <label class="daw-profile__value"><span>NOMOR TELEPON / WHATSAPP</span><input type="tel" name="phone" value="<?= esc_attr( $applicant_profile['phone'] ?? '' ) ?>" maxlength="19" inputmode="numeric" pattern="[0-9]{8,19}" required <?= $profile_disabled ? 'disabled' : '' ?>></label>
                <?php if ( ! $registration ) : ?>
                    <label class="daw-profile__value"><span>EMAIL AKTIF</span><input type="email" name="email" value="<?= esc_attr( $applicant_profile['email'] ?? '' ) ?>" maxlength="50" autocomplete="email" required <?= $profile_disabled ? 'disabled' : '' ?>><small>Perubahan email juga mengubah email untuk masuk.</small></label>
                <?php endif; ?>
            </div>
        </div>
    </details>

    <!-- 2. Dokumen (Dropdown Card) -->
    <details class="daw-account__card daw-profile__section daw-profile__accordion" open>
        <summary class="daw-profile__accordion-summary">
            <h2 class="daw-profile__accordion-heading">Dokumen</h2>
            <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="daw-profile__accordion-body">
            <p class="daw-profile__section-note">Dokumen hanya dapat diakses setelah masuk ke akun pelamar. Maksimal 5 MB per file dan 6 MB untuk total file yang diunggah sekaligus.</p>
            <div class="daw-profile__grid">
                <label class="daw-profile__value"><span>CV / RESUME</span>
                    <?php if ( ! empty( $profile_documents['resume'] ) ) : ?><a class="daw-profile__document-link" href="<?= esc_url( recruitment_get_public_url( 'applicant-file', [ 'document' => 'resume' ] ) ) ?>">Lihat <?= esc_html( $profile_documents['resume']['name'] ?? 'CV tersimpan' ) ?></a><?php endif; ?>
                    <input type="file" name="resume" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" <?= $registration && empty( $profile_documents['resume'] ) ? 'required' : '' ?> <?= $profile_disabled ? 'disabled' : '' ?>>
                    <small>PDF, DOC, atau DOCX, maksimal 5 MB.</small>
                </label>
                <label class="daw-profile__value"><span>PORTOFOLIO (OPSIONAL)</span>
                    <?php if ( ! empty( $profile_documents['portfolio'] ) ) : ?><a class="daw-profile__document-link" href="<?= esc_url( recruitment_get_public_url( 'applicant-file', [ 'document' => 'portfolio' ] ) ) ?>">Lihat <?= esc_html( $profile_documents['portfolio']['name'] ?? 'Portofolio tersimpan' ) ?></a><?php endif; ?>
                    <input type="file" name="portfolio" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/jpeg,image/png" <?= $profile_disabled ? 'disabled' : '' ?>>
                    <small>PDF, DOC, DOCX, JPG, atau PNG, maksimal 5 MB.</small>
                </label>
            </div>
        </div>
    </details>

    <!-- 3. Media Sosial (Dropdown Card) -->
    <details class="daw-account__card daw-profile__section daw-profile__accordion" data-profile-collection="social_media" open>
        <summary class="daw-profile__accordion-summary">
            <h2 class="daw-profile__accordion-heading">Media Sosial yang Dimiliki &amp; Username</h2>
            <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="daw-profile__accordion-body">
            <p class="daw-profile__section-note">Opsional. Tambahkan platform dan username atau URL profil media sosial yang ingin dicantumkan (maksimal 10).</p>
            <?php foreach ( $profile_social_media as $index => $social_entry ) : ?>
                <article class="daw-profile__repeatable daw-profile__social-row" data-profile-row>
                    <div class="daw-profile__repeatable-heading"><strong data-row-number>Media Sosial <?= esc_html( (string) ( $index + 1 ) ) ?></strong><button type="button" class="daw-profile__remove-row" data-remove-profile-row <?= $profile_disabled ? 'disabled' : '' ?>>Hapus</button></div>
                    <div class="daw-profile__grid">
                        <label class="daw-profile__value"><span>PLATFORM</span><select name="social_media[<?= esc_attr( (string) $index ) ?>][platform]" <?= $profile_disabled ? 'disabled' : '' ?>><option value="">Pilih platform</option><?php foreach ( $social_platform_options as $platform ) : ?><option value="<?= esc_attr( $platform ) ?>" <?= selected( $social_entry['platform'] ?? '', $platform, false ) ?>><?= esc_html( $platform ) ?></option><?php endforeach; ?></select></label>
                        <label class="daw-profile__value"><span>USERNAME / URL PROFIL</span><input name="social_media[<?= esc_attr( (string) $index ) ?>][username]" value="<?= esc_attr( $social_entry['username'] ?? '' ) ?>" maxlength="255" placeholder="@username atau URL profil" <?= $profile_disabled ? 'disabled' : '' ?>></label>
                    </div>
                </article>
            <?php endforeach; ?>
            <template data-profile-template="social_media"><article class="daw-profile__repeatable daw-profile__social-row" data-profile-row>
                <div class="daw-profile__repeatable-heading"><strong data-row-number></strong><button type="button" class="daw-profile__remove-row" data-remove-profile-row>Hapus</button></div>
                <div class="daw-profile__grid">
                    <label class="daw-profile__value"><span>PLATFORM</span><select name="social_media[__INDEX__][platform]"><option value="">Pilih platform</option><?php foreach ( $social_platform_options as $platform ) : ?><option value="<?= esc_attr( $platform ) ?>"><?= esc_html( $platform ) ?></option><?php endforeach; ?></select></label>
                    <label class="daw-profile__value"><span>USERNAME / URL PROFIL</span><input name="social_media[__INDEX__][username]" maxlength="255" placeholder="@username atau URL profil"></label>
                </div>
            </article></template>
            <button class="daw-profile__add-row" type="button" data-add-profile-row="social_media" data-max-profile-rows="10" <?= $profile_disabled ? 'disabled data-profile-readonly="1"' : '' ?>>+ Tambah Media Sosial</button>
        </div>
    </details>

    <!-- 4. Pendidikan (Unified Dropdown Card - Menggantikan card pendidikan lama) -->
    <details class="daw-account__card daw-profile__section daw-profile__accordion" open>
        <summary class="daw-profile__accordion-summary">
            <h2 class="daw-profile__accordion-heading">Pendidikan</h2>
            <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="daw-profile__accordion-body">
            <p class="daw-profile__section-note">Pendidikan formal, kursus/pendidikan nonformal, dan kemampuan bahasa.</p>
            <?php
            // Tabel Pendidikan Formal (Baris 1 wajib & tersinkronisasi ke profil utama)
            $render_app_table( 'pendidikan_formal', true );

            // Tabel Pendidikan Nonformal / Kursus
            $render_app_table( 'pendidikan_nonformal', true );

            // Tabel Kemampuan Bahasa
            $render_app_table( 'bahasa', true );
            ?>
        </div>
    </details>

    <!-- 5. Pengalaman Kerja (Tabel Dropdown Card) -->
    <details class="daw-account__card daw-profile__section daw-profile__accordion" data-profile-collection="work_experiences" open>
        <summary class="daw-profile__accordion-summary">
            <h2 class="daw-profile__accordion-heading">Pengalaman Kerja</h2>
            <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="daw-profile__accordion-body">
            <p class="daw-profile__section-note">Opsional. Tambahkan semua pengalaman kerja yang relevan (maksimal 50).</p>
            <div class="daw-application__table-scroll">
                <table class="daw-application__table daw-profile__work-table">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 50px;">No.</th>
                            <th scope="col">Nama Perusahaan</th>
                            <th scope="col">Jabatan / Posisi</th>
                            <th scope="col">Periode Kerja</th>
                            <th scope="col" style="width: 75px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody data-profile-tbody="work_experiences">
                        <?php foreach ( $profile_work_experiences as $index => $experience ) : ?>
                            <tr data-profile-row>
                                <td data-row-number><?= esc_html( (string) ( $index + 1 ) ) ?></td>
                                <td><input name="work_experiences[<?= esc_attr( (string) $index ) ?>][company]" value="<?= esc_attr( $experience['company'] ?? '' ) ?>" maxlength="255" placeholder="Nama Perusahaan" <?= $profile_disabled ? 'disabled' : '' ?>></td>
                                <td><input name="work_experiences[<?= esc_attr( (string) $index ) ?>][position]" value="<?= esc_attr( $experience['position'] ?? '' ) ?>" maxlength="255" placeholder="Jabatan / Posisi" <?= $profile_disabled ? 'disabled' : '' ?>></td>
                                <td><input name="work_experiences[<?= esc_attr( (string) $index ) ?>][period]" value="<?= esc_attr( $experience['period'] ?? '' ) ?>" placeholder="Contoh: Jan 2022 - Des 2024" maxlength="255" <?= $profile_disabled ? 'disabled' : '' ?>></td>
                                <td><button type="button" class="daw-profile__remove-row" data-remove-profile-row <?= $profile_disabled ? 'disabled' : '' ?>>Hapus</button></td>
                            </tr>
                        <?php endforeach; ?>
                        <template data-profile-template="work_experiences">
                            <tr data-profile-row>
                                <td data-row-number></td>
                                <td><input name="work_experiences[__INDEX__][company]" maxlength="255" placeholder="Nama Perusahaan"></td>
                                <td><input name="work_experiences[__INDEX__][position]" maxlength="255" placeholder="Jabatan / Posisi"></td>
                                <td><input name="work_experiences[__INDEX__][period]" placeholder="Contoh: Jan 2022 - Des 2024" maxlength="255"></td>
                                <td><button type="button" class="daw-profile__remove-row" data-remove-profile-row>Hapus</button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <button class="daw-profile__add-row" type="button" data-add-profile-row="work_experiences" data-max-profile-rows="50" <?= $profile_disabled ? 'disabled data-profile-readonly="1"' : '' ?>>+ Tambah Pengalaman</button>
        </div>
    </details>

    <!-- 6. Riwayat dan Evaluasi Pekerjaan (Tepat di bawah Pengalaman Kerja) -->
    <details class="daw-account__card daw-profile__section daw-profile__accordion" open>
        <summary class="daw-profile__accordion-summary">
            <h2 class="daw-profile__accordion-heading">Riwayat dan Evaluasi Pekerjaan</h2>
            <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="daw-profile__accordion-body">
            <p class="daw-profile__section-note">Informasi atasan langsung, evaluasi kinerja, dan pengalaman perubahan.</p>
            <?php
            // Tabel Atasan dan Bawahan
            $render_app_table( 'atasan_dan_bawahan', true );

            // Detail Gaji & Alasan Pindah Pekerjaan Sebelumnya
            $render_app_table( 'riwayat_pekerjaan', true, [ 'gaji_pokok', 'alasan_pindah' ] );
            ?>
            <div class="daw-profile__grid" style="margin-top: 14px;">
                <label class="daw-profile__value daw-profile__value--full">
                    <span>PERUBAHAN YANG PERNAH DILAKUKAN DAN HASILNYA</span>
                    <textarea name="application_answers[perubahan_di_perusahaan_terdahulu]" rows="3" maxlength="10000" <?= $profile_disabled ? 'disabled' : '' ?>><?= esc_textarea( $application_profile_answers['perubahan_di_perusahaan_terdahulu'] ?? '' ) ?></textarea>
                </label>
                <label class="daw-profile__value daw-profile__value--full">
                    <span>PENGALAMAN PERUBAHAN POSITIF</span>
                    <textarea name="application_answers[pengalaman_perubahan_positif]" rows="3" maxlength="10000" <?= $profile_disabled ? 'disabled' : '' ?>><?= esc_textarea( $application_profile_answers['pengalaman_perubahan_positif'] ?? '' ) ?></textarea>
                </label>
                <label class="daw-profile__value">
                    <span>BERSEDIA DIHUBUNGI ATASAN SEBELUMNYA?</span>
                    <select name="application_answers[kesediaan_kontak_atasan]" <?= $profile_disabled ? 'disabled' : '' ?>>
                        <option value="">Pilih jawaban</option>
                        <option value="Ya" <?= selected( $application_profile_answers['kesediaan_kontak_atasan'] ?? '', 'Ya', false ) ?>>Ya</option>
                        <option value="Tidak" <?= selected( $application_profile_answers['kesediaan_kontak_atasan'] ?? '', 'Tidak', false ) ?>>Tidak</option>
                    </select>
                </label>
            </div>
        </div>
    </details>

    <!-- 7. Status Keluarga dan Lingkungan (Dropdown Card) -->
    <details class="daw-account__card daw-profile__section daw-profile__accordion" data-profile-collection="family_members" open>
        <summary class="daw-profile__accordion-summary">
            <h2 class="daw-profile__accordion-heading">Status Keluarga dan Lingkungan</h2>
            <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="daw-profile__accordion-body">
            <div class="daw-profile__grid">
                <label class="daw-profile__value daw-profile__marital-status"><span>STATUS PERNIKAHAN</span><select name="marital_status" required <?= $profile_disabled ? 'disabled' : '' ?>><option value="">Pilih status</option><?php foreach ( [ 'Belum menikah', 'Menikah', 'Cerai' ] as $status ) : ?><option value="<?= esc_attr( $status ) ?>" <?= selected( $profile_details['marital_status'] ?? '', $status, false ) ?>><?= esc_html( $status ) ?></option><?php endforeach; ?></select></label>
                <label class="daw-profile__value"><span>TANGGAL PERTUNANGAN / PERNIKAHAN</span><input type="date" name="application_answers[tanggal_pertunangan_atau_pernikahan]" value="<?= esc_attr( $application_profile_answers['tanggal_pertunangan_atau_pernikahan'] ?? '' ) ?>" <?= $profile_disabled ? 'disabled' : '' ?>></label>
                <label class="daw-profile__value"><span>URUTAN ANDA &amp; JUMLAH SAUDARA</span><input name="application_answers[urutan_dalam_saudara]" value="<?= esc_attr( $application_profile_answers['urutan_dalam_saudara'] ?? '' ) ?>" placeholder="Contoh: Anak ke 2 dari 3 bersaudara" maxlength="255" <?= $profile_disabled ? 'disabled' : '' ?>></label>
                <label class="daw-profile__value"><span>STATUS RUMAH TINGGAL</span><select name="application_answers[status_tempat_tinggal]" <?= $profile_disabled ? 'disabled' : '' ?>><option value="">Pilih status rumah</option><?php foreach ( [ 'Rumah Pribadi', 'Rumah Keluarga Suami / Istri', 'Rumah Kost', 'Rumah Orang Tua', 'Rumah Kontrak', 'Lainnya' ] as $home_opt ) : ?><option value="<?= esc_attr( $home_opt ) ?>" <?= selected( $application_profile_answers['status_tempat_tinggal'] ?? '', $home_opt, false ) ?>><?= esc_html( $home_opt ) ?></option><?php endforeach; ?></select></label>
                <label class="daw-profile__value"><span>LAMA KOST / KONTRAK</span><input name="application_answers[lama_kost_atau_kontrak]" value="<?= esc_attr( $application_profile_answers['lama_kost_atau_kontrak'] ?? '' ) ?>" placeholder="Contoh: 2 tahun" maxlength="255" <?= $profile_disabled ? 'disabled' : '' ?>></label>
            </div>

            <h3 class="daw-profile__subheading">Hubungan dengan Karyawan DAW</h3>
            <div class="daw-application__table-scroll">
                <table class="daw-application__table daw-profile__qa-table">
                    <thead><tr><th scope="col">Pertanyaan</th><th scope="col">Jawaban</th></tr></thead>
                    <tbody>
                        <?php $render_qa_input( 'hubungan_keluarga_karyawan_daw' ); ?>
                        <?php $render_qa_input( 'hubungan_pribadi_karyawan_daw' ); ?>
                    </tbody>
                </table>
            </div>
            <div class="daw-profile__grid daw-profile__related-answers">
                <label class="daw-profile__value daw-profile__value--full"><span>NAMA &amp; JABATAN KELUARGA DI DAW</span><textarea name="application_answers[nama_keluarga_karyawan_daw]" rows="2" maxlength="10000" placeholder="Jika ada, sebutkan nama dan jabatannya" <?= $profile_disabled ? 'disabled' : '' ?>><?= esc_textarea( $application_profile_answers['nama_keluarga_karyawan_daw'] ?? '' ) ?></textarea></label>
                <label class="daw-profile__value daw-profile__value--full"><span>NAMA &amp; JABATAN RELASI PRIBADI DI DAW</span><textarea name="application_answers[nama_hubungan_pribadi_karyawan_daw]" rows="2" maxlength="10000" placeholder="Jika ada, sebutkan nama dan jabatannya" <?= $profile_disabled ? 'disabled' : '' ?>><?= esc_textarea( $application_profile_answers['nama_hubungan_pribadi_karyawan_daw'] ?? '' ) ?></textarea></label>
            </div>

            <h3 class="daw-profile__subheading">Susunan Keluarga Inti</h3>
            <p class="daw-profile__section-note"><?= $registration ? 'Isi minimal satu anggota keluarga. Jika mulai mengisi satu baris, semua kolom bertanda * wajib dilengkapi.' : 'Opsional saat edit. Jika mulai mengisi anggota keluarga, lengkapi semua kolomnya.' ?> Maksimal 50 baris.</p>
            <div class="daw-application__table-scroll">
                <table class="daw-application__table daw-profile__family-table">
                    <thead><tr><th scope="col">No.</th><th scope="col">Nama Keluarga<?php if ( $registration ) : ?> <span class="daw-registration__required-mark" aria-label="wajib diisi">*</span><?php endif; ?></th><th scope="col">Hubungan<?php if ( $registration ) : ?> <span class="daw-registration__required-mark" aria-label="wajib diisi">*</span><?php endif; ?></th><th scope="col">Usia<?php if ( $registration ) : ?> <span class="daw-registration__required-mark" aria-label="wajib diisi">*</span><?php endif; ?></th><th scope="col">Pendidikan Terakhir<?php if ( $registration ) : ?> <span class="daw-registration__required-mark" aria-label="wajib diisi">*</span><?php endif; ?></th><th scope="col">Aksi</th></tr></thead>
                    <tbody data-profile-tbody="family_members">
                        <?php foreach ( $profile_family_members as $index => $member ) : ?>
                            <tr class="daw-profile__family-row" data-profile-row>
                                <th scope="row" data-row-number><?= esc_html( (string) ( $index + 1 ) ) ?></th>
                                <td><input name="family_members[<?= esc_attr( (string) $index ) ?>][name]" value="<?= esc_attr( $member['name'] ?? '' ) ?>" maxlength="255" placeholder="Nama keluarga" <?= $profile_disabled ? 'disabled' : '' ?>></td>
                                <td><input name="family_members[<?= esc_attr( (string) $index ) ?>][relationship]" value="<?= esc_attr( $member['relationship'] ?? '' ) ?>" maxlength="255" placeholder="Contoh: Ayah" <?= $profile_disabled ? 'disabled' : '' ?>></td>
                                <td><input type="number" name="family_members[<?= esc_attr( (string) $index ) ?>][age]" value="<?= esc_attr( $member['age'] ?? '' ) ?>" min="0" max="120" step="1" placeholder="Usia" <?= $profile_disabled ? 'disabled' : '' ?>></td>
                                <td><input name="family_members[<?= esc_attr( (string) $index ) ?>][education]" value="<?= esc_attr( $member['education'] ?? '' ) ?>" maxlength="255" placeholder="Pendidikan" <?= $profile_disabled ? 'disabled' : '' ?>></td>
                                <td><button type="button" class="daw-profile__remove-row" data-remove-profile-row <?= $profile_disabled ? 'disabled' : '' ?>>Hapus</button></td>
                            </tr>
                        <?php endforeach; ?>
                        <template data-profile-template="family_members">
                            <tr class="daw-profile__family-row" data-profile-row>
                                <th scope="row" data-row-number></th>
                                <td><input name="family_members[__INDEX__][name]" maxlength="255" placeholder="Nama keluarga"></td>
                                <td><input name="family_members[__INDEX__][relationship]" maxlength="255" placeholder="Contoh: Ayah"></td>
                                <td><input type="number" name="family_members[__INDEX__][age]" min="0" max="120" step="1" placeholder="Usia"></td>
                                <td><input name="family_members[__INDEX__][education]" maxlength="255" placeholder="Pendidikan"></td>
                                <td><button type="button" class="daw-profile__remove-row" data-remove-profile-row>Hapus</button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <button class="daw-profile__add-row" type="button" data-add-profile-row="family_members" data-max-profile-rows="50" <?= $profile_disabled ? 'disabled data-profile-readonly="1"' : '' ?>>+ Tambah Anggota Keluarga</button>

            <!-- Tabel Keluarga Asal -->
            <?php $render_app_table( 'keluarga_asal', true ); ?>

            <h3 class="daw-profile__subheading">Riwayat Kesehatan</h3>
            <div class="daw-application__table-scroll">
                <table class="daw-application__table daw-profile__qa-table">
                    <thead><tr><th scope="col">Pertanyaan</th><th scope="col">Jawaban</th></tr></thead>
                    <tbody>
                        <?php $render_qa_input( 'riwayat_diabetes' ); ?>
                        <?php $render_qa_input( 'riwayat_darah_tinggi' ); ?>
                        <?php $render_qa_input( 'riwayat_buta_warna' ); ?>
                        <?php $render_qa_input( 'riwayat_wasir' ); ?>
                        <?php $render_qa_input( 'riwayat_tbc' ); ?>
                    </tbody>
                </table>
            </div>
            <div class="daw-profile__grid daw-profile__related-answers">
                <label class="daw-profile__value daw-profile__value--full"><span>RIWAYAT ALERGI (JIKA YA, SEBUTKAN)</span><textarea name="application_answers[riwayat_alergi]" rows="2" maxlength="10000" <?= $profile_disabled ? 'disabled' : '' ?>><?= esc_textarea( $application_profile_answers['riwayat_alergi'] ?? '' ) ?></textarea></label>
                <label class="daw-profile__value daw-profile__value--full"><span>RIWAYAT SAKIT LAIN &amp; KAPAN DIALAMI</span><textarea name="application_answers[riwayat_sakit_lain]" rows="2" maxlength="10000" <?= $profile_disabled ? 'disabled' : '' ?>><?= esc_textarea( $application_profile_answers['riwayat_sakit_lain'] ?? '' ) ?></textarea></label>
                <label class="daw-profile__value daw-profile__value--full"><span>RIWAYAT PENYAKIT KELUARGA</span><textarea name="application_answers[riwayat_penyakit_keluarga]" rows="2" maxlength="10000" <?= $profile_disabled ? 'disabled' : '' ?>><?= esc_textarea( $application_profile_answers['riwayat_penyakit_keluarga'] ?? '' ) ?></textarea></label>
            </div>
        </div>
    </details>

    <!-- 8. Minat dan Konsep Pribadi (Tabel 2 Kolom) -->
    <details class="daw-account__card daw-profile__section daw-profile__accordion" open>
        <summary class="daw-profile__accordion-summary">
            <h2 class="daw-profile__accordion-heading">Minat dan Konsep Pribadi</h2>
            <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="daw-profile__accordion-body">
            <p class="daw-profile__section-note">Motivasi, ekspektasi, fasilitas, dan preferensi kerja.</p>
            <div class="daw-application__table-scroll">
                <table class="daw-application__table daw-profile__qa-table">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 38%;">Pertanyaan</th>
                            <th scope="col" style="width: 62%;">Jawaban</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $render_qa_input( 'motivasi_bergabung' );
                        $render_qa_input( 'pengetahuan_perusahaan' );
                        $render_qa_input( 'harapan_bekerja' );
                        $render_qa_input( 'gaji_yang_diharapkan' );
                        $render_qa_input( 'fasilitas_yang_diharapkan' );
                        $render_qa_input( 'tunjangan_transportasi_saat_ini' );
                        $render_qa_input( 'tunjangan_makan_saat_ini' );
                        $render_qa_input( 'tunjangan_komunikasi_saat_ini' );
                        $render_qa_input( 'tunjangan_kehadiran_saat_ini' );
                        $render_qa_input( 'car_ownership_program_saat_ini' );
                        $render_qa_input( 'house_ownership_program_saat_ini' );
                        $render_qa_input( 'asuransi_rawat_jalan_saat_ini' );
                        $render_qa_input( 'asuransi_rawat_inap_saat_ini' );
                        $render_qa_input( 'insentif_saat_ini' );
                        $render_qa_input( 'bonus_tahunan_saat_ini' );
                        $render_qa_input( 'laptop_ownership_program_saat_ini' );
                        $render_qa_input( 'fasilitas_lain_saat_ini' );
                        $render_qa_input( 'bersedia_background_check' );
                        $render_qa_input( 'kapan_mulai_bekerja' );
                        $render_qa_input( 'minat_jenis_pekerjaan_1' );
                        $render_qa_input( 'minat_jenis_pekerjaan_2' );
                        $render_qa_input( 'minat_jenis_pekerjaan_3' );
                        $render_qa_input( 'minat_bidang_industri_1' );
                        $render_qa_input( 'minat_bidang_industri_2' );
                        $render_qa_input( 'minat_bidang_industri_3' );
                        $render_qa_input( 'jabatan_cita_cita' );
                        $render_qa_input( 'upaya_mencapai_jabatan' );
                        $render_qa_input( 'bersedia_penempatan_luar_kota' );
                        $render_qa_input( 'lingkungan_kerja_disukai' );
                        $render_qa_input( 'lingkungan_kerja_tidak_disukai' );
                        $render_qa_input( 'tipe_orang_disukai' );
                        $render_qa_input( 'tipe_orang_tidak_disukai' );
                        $render_qa_input( 'kelebihan_diri' );
                        $render_qa_input( 'kelemahan_diri' );
                        $render_qa_input( 'kesulitan_mengambil_keputusan' );
                        $render_qa_input( 'mengenal_karyawan_daw' );
                        ?>
                        <tr>
                            <th scope="row">Keahlian Bahasa, Sertifikasi, Skill, dan Minat Lainnya <em style="color:var(--daw-red);">*</em></th>
                            <td>
                                <textarea name="interests" maxlength="3000" rows="3" required placeholder="Tuliskan bahasa yang dikuasai, sertifikasi, keahlian, dan minat lainnya." <?= $profile_disabled ? 'disabled' : '' ?>><?= esc_textarea( $profile_details['interests'] ?? '' ) ?></textarea>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </details>

    <!-- 9. Aktivitas Sosial dan Kegiatan Lain (Dropdown Card) -->
    <details class="daw-account__card daw-profile__section daw-profile__accordion" open>
        <summary class="daw-profile__accordion-summary">
            <h2 class="daw-profile__accordion-heading">Aktivitas Sosial dan Kegiatan Lain</h2>
            <span class="daw-profile__accordion-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="daw-profile__accordion-body">
            <div class="daw-profile__grid">
                <label class="daw-profile__value daw-profile__value--full">
                    <span>APA HOBI ANDA?</span>
                    <textarea name="application_answers[hobi]" rows="3" maxlength="10000" <?= $profile_disabled ? 'disabled' : '' ?>><?= esc_textarea( $application_profile_answers['hobi'] ?? '' ) ?></textarea>
                </label>
                <label class="daw-profile__value daw-profile__value--full">
                    <span>BAGAIMANA CARA ANDA MENGISI WAKTU LUANG?</span>
                    <textarea name="application_answers[kegiatan_waktu_luang]" rows="3" maxlength="10000" <?= $profile_disabled ? 'disabled' : '' ?>><?= esc_textarea( $application_profile_answers['kegiatan_waktu_luang'] ?? '' ) ?></textarea>
                </label>
                <label class="daw-profile__value daw-profile__value--full">
                    <span>PENGALAMAN ORGANISASI (NAMA, JABATAN, DAN LAMA KEANGGOTAAN)</span>
                    <textarea name="application_answers[pengalaman_organisasi]" rows="3" maxlength="10000" <?= $profile_disabled ? 'disabled' : '' ?>><?= esc_textarea( $application_profile_answers['pengalaman_organisasi'] ?? '' ) ?></textarea>
                </label>
            </div>
        </div>
    </details>
</div>
