<?php

/**
 * Validates applicant profile data and stores private profile documents.
 */
class Recruitment_Applicant_Profile {

    private const MAX_UPLOAD_SIZE = 5242880;

    private const MAX_PROCTORING_UPLOAD_SIZE = 2097152;

    private const EDUCATION_LEVELS = [ 'SMA/SMK', 'D1', 'D2', 'D3', 'D4/S1', 'S2', 'S3' ];

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $files
     * @param array<string, mixed> $existing_details
     * @param bool $require_registration_fields
     * @return array{success: bool, profile?: array<string, mixed>, new_documents?: array<int, array<string, string>>, replaced_documents?: array<int, array<string, string>>, error?: string}
     */
    public function prepare( array $input, array $files, array $existing_details = [], bool $require_registration_fields = false ): array {
        $input = function_exists( 'wp_unslash' ) ? wp_unslash( $input ) : $input;
        $profile = [
            'full_name' => sanitize_text_field( $this->scalar( $input['full_name'] ?? '' ) ),
            'email' => strtolower( sanitize_email( $this->scalar( $input['email'] ?? '' ) ) ),
            'gender' => sanitize_text_field( $this->scalar( $input['gender'] ?? '' ) ),
            'birth_place' => sanitize_text_field( $this->scalar( $input['birth_place'] ?? '' ) ),
            'birth_date' => sanitize_text_field( $this->scalar( $input['birth_date'] ?? '' ) ),
            'phone' => preg_replace( '/\D+/', '', $this->scalar( $input['phone'] ?? '' ) ),
            'address' => sanitize_textarea_field( $this->scalar( $input['address'] ?? '' ) ),
        ];
        $raw_answers = is_array( $input['application_answers'] ?? null ) ? $input['application_answers'] : [];
        $education_level = sanitize_text_field( $this->scalar( $input['education_level'] ?? ( $raw_answers['pendidikan_formal_1_tingkat'] ?? '' ) ) );
        $institution = sanitize_text_field( $this->scalar( $input['institution'] ?? ( $raw_answers['pendidikan_formal_1_nama_sekolah'] ?? '' ) ) );
        $major = sanitize_text_field( $this->scalar( $input['major'] ?? ( $raw_answers['pendidikan_formal_1_program_studi'] ?? '' ) ) );
        $gpa = sanitize_text_field( $this->scalar( $input['gpa'] ?? ( $raw_answers['pendidikan_formal_1_ipk'] ?? '' ) ) );
        $graduation_year = sanitize_text_field( $this->scalar( $input['graduation_year'] ?? ( $raw_answers['pendidikan_formal_1_tahun_lulus'] ?? '' ) ) );
        $marital_status = sanitize_text_field( $this->scalar( $input['marital_status'] ?? ( $raw_answers['status_pernikahan'] ?? '' ) ) );
        if ( 'Janda / Duda' === $marital_status ) {
            $marital_status = 'Cerai';
        }
        $interests = sanitize_textarea_field( $this->scalar( $input['interests'] ?? ( $raw_answers['keahlian_bahasa_sertifikasi_skill'] ?? '' ) ) );

        $details = [
            'education_level' => $education_level,
            'institution' => $institution,
            'major' => $major,
            'gpa' => $gpa,
            'graduation_year' => $graduation_year,
            'work_experiences' => [],
            'social_media' => [],
            'marital_status' => $marital_status,
            'family_members' => [],
            'interests' => $interests,
            'application_answers' => is_array( $existing_details['application_answers'] ?? null ) ? $existing_details['application_answers'] : [],
            'documents' => [],
        ];
        $allowed_document_mimes = [
            'profile_photo' => [ 'image/jpeg', 'image/png' ],
            'resume' => [ 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ],
            'portfolio' => [ 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png' ],
        ];
        foreach ( $allowed_document_mimes as $field => $mime_types ) {
            $document = $existing_details['documents'][ $field ] ?? null;
            if ( ! is_array( $document ) || ! in_array( $document['mime_type'] ?? '', $mime_types, true ) ) {
                continue;
            }
            $path = $this->document_path( $document );
            if ( null !== $path && is_file( $path ) && is_readable( $path ) ) {
                $details['documents'][ $field ] = $document;
            }
        }

        if ( '' === $profile['full_name'] || 255 < $this->text_length( $profile['full_name'] )
            || ! is_email( $profile['email'] ) || 50 < strlen( $profile['email'] )
            || ! in_array( $profile['gender'], [ 'Laki-laki', 'Perempuan' ], true )
            || '' === $profile['birth_place'] || 255 < $this->text_length( $profile['birth_place'] )
            || ! $this->is_valid_birth_date( $profile['birth_date'] )
            || ! preg_match( '/^\d{8,19}$/', $profile['phone'] )
            || '' === $profile['address'] || 100 < $this->text_length( $profile['address'] )
        ) {
            return [ 'success' => false, 'error' => 'invalid_personal_data' ];
        }

        if ( ! in_array( $details['education_level'], self::EDUCATION_LEVELS, true )
            || '' === $details['institution'] || 255 < $this->text_length( $details['institution'] )
            || '' === $details['major'] || 255 < $this->text_length( $details['major'] )
            || ! is_numeric( $details['gpa'] ) || 0 > (float) $details['gpa'] || 4 < (float) $details['gpa']
            || ! preg_match( '/^\d{4}$/', $details['graduation_year'] )
            || (int) $details['graduation_year'] < 1950 || (int) $details['graduation_year'] > (int) gmdate( 'Y' )
            || ! in_array( $details['marital_status'], [ 'Belum menikah', 'Menikah', 'Cerai' ], true )
            || '' === $details['interests'] || 3000 < $this->text_length( $details['interests'] )
        ) {
            return [ 'success' => false, 'error' => 'invalid_education_data' ];
        }
        $details['gpa'] = number_format( (float) $details['gpa'], 2, '.', '' );

        $experiences = $this->normalize_rows( $input['work_experiences'] ?? [], [ 'company', 'position', 'period' ], 50 );
        if ( ! $experiences['success'] ) {
            return [ 'success' => false, 'error' => 'invalid_work_experiences' ];
        }
        $details['work_experiences'] = $experiences['rows'];

        $social_media = $this->normalize_rows( $input['social_media'] ?? [], [ 'platform', 'username' ], 10 );
        if ( ! $social_media['success'] ) {
            return [ 'success' => false, 'error' => 'invalid_social_media' ];
        }
        $social_platforms = [ 'Instagram', 'Facebook', 'TikTok', 'X / Twitter', 'YouTube', 'LinkedIn', 'Lainnya' ];
        foreach ( $social_media['rows'] as $entry ) {
            if ( ! in_array( $entry['platform'], $social_platforms, true ) ) {
                return [ 'success' => false, 'error' => 'invalid_social_media' ];
            }
        }
        $details['social_media'] = $social_media['rows'];

        $family_members = $this->normalize_rows( $input['family_members'] ?? [], [ 'name', 'relationship', 'age', 'education' ], 50 );
        if ( ! $family_members['success'] || ( $require_registration_fields && empty( $family_members['rows'] ) ) ) {
            return [ 'success' => false, 'error' => 'invalid_family_members' ];
        }
        foreach ( $family_members['rows'] as $member ) {
            if ( ! ctype_digit( (string) $member['age'] ) || 0 > (int) $member['age'] || 120 < (int) $member['age'] ) {
                return [ 'success' => false, 'error' => 'invalid_family_members' ];
            }
        }
        $details['family_members'] = $family_members['rows'];

        if ( isset( $input['application_answers'] ) ) {
            $raw_application_answers = $input['application_answers'];
            if ( ! is_array( $raw_application_answers ) ) {
                return [ 'success' => false, 'error' => 'invalid_application_answers' ];
            }
            $allowed_application_questions = [];
            foreach ( ( new Recruitment_Application() )->get_reference_form_sections() as $section ) {
                foreach ( $section['questions'] as $question ) {
                    if ( 'file' !== $question['type'] ) {
                        $allowed_application_questions[ $question['key'] ] = $question;
                    }
                }
            }
            if ( count( $raw_application_answers ) > count( $allowed_application_questions ) ) {
                return [ 'success' => false, 'error' => 'invalid_application_answers' ];
            }
            $application_answers = [];
            foreach ( $raw_application_answers as $key => $raw_value ) {
                if ( ! is_string( $key ) || ! isset( $allowed_application_questions[ $key ] ) || ! is_scalar( $raw_value ) ) {
                    return [ 'success' => false, 'error' => 'invalid_application_answers' ];
                }
                $value = sanitize_textarea_field( (string) $raw_value );
                if ( 10000 < $this->text_length( $value ) ) {
                    return [ 'success' => false, 'error' => 'invalid_application_answers' ];
                }
                if ( '' !== $value ) {
                    $question = $allowed_application_questions[ $key ];
                    if ( in_array( $question['type'], [ 'dropdown', 'single_choice' ], true )
                        && ! in_array( $value, array_column( $question['options'], 'value' ), true )
                    ) {
                        return [ 'success' => false, 'error' => 'invalid_application_answers' ];
                    }
                    if ( 'email' === $question['type'] && ! is_email( $value ) ) {
                        return [ 'success' => false, 'error' => 'invalid_application_answers' ];
                    }
                    if ( 'number' === $question['type'] && ! is_numeric( $value ) ) {
                        return [ 'success' => false, 'error' => 'invalid_application_answers' ];
                    }
                    if ( 'date' === $question['type'] ) {
                        $date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
                        if ( ! $date instanceof DateTimeImmutable || $date->format( 'Y-m-d' ) !== $value ) {
                            return [ 'success' => false, 'error' => 'invalid_application_answers' ];
                        }
                    }
                    if ( 'phone' === $question['type'] && ! preg_match( '/^[0-9+().\-\s]{1,255}$/', $value ) ) {
                        return [ 'success' => false, 'error' => 'invalid_application_answers' ];
                    }
                    $application_answers[ $key ] = $value;
                }
            }
            if ( '' !== $details['education_level'] ) {
                $application_answers['pendidikan_formal_1_tingkat'] = $details['education_level'];
            }
            if ( '' !== $details['institution'] ) {
                $application_answers['pendidikan_formal_1_nama_sekolah'] = $details['institution'];
            }
            if ( '' !== $details['major'] ) {
                $application_answers['pendidikan_formal_1_program_studi'] = $details['major'];
            }
            if ( '' !== $details['gpa'] ) {
                $application_answers['pendidikan_formal_1_ipk'] = $details['gpa'];
            }
            if ( '' !== $details['graduation_year'] ) {
                $application_answers['pendidikan_formal_1_tahun_lulus'] = $details['graduation_year'];
            }
            if ( '' !== $details['interests'] ) {
                $application_answers['keahlian_bahasa_sertifikasi_skill'] = $details['interests'];
            }
            $details['application_answers'] = $application_answers;
        }

        $pending_uploads = [];
        $total_upload_size = 0;
        foreach ( [ 'profile_photo', 'resume', 'portfolio' ] as $field ) {
            $upload = $this->uploaded_file( $files, $field );
            if ( null === $upload ) {
                if ( ! empty( $upload['invalid'] ) ) {
                    return [ 'success' => false, 'error' => 'invalid_upload' ];
                }
                if ( $require_registration_fields && ( 'profile_photo' === $field || 'resume' === $field ) && empty( $details['documents'][ $field ] ) ) {
                    return [ 'success' => false, 'error' => 'invalid_upload' ];
                }
                continue;
            }

            $validated = $this->validate_upload( $upload, $field );
            if ( null === $validated ) {
                return [ 'success' => false, 'error' => 'invalid_upload' ];
            }
            $total_upload_size += (int) $validated['size'];
            $pending_uploads[ $field ] = $validated;
        }
        if ( 6 * 1024 * 1024 < $total_upload_size ) {
            return [ 'success' => false, 'error' => 'invalid_upload' ];
        }

        $new_documents = [];
        $replaced_documents = [];
        foreach ( $pending_uploads as $field => $upload ) {
            $document = $this->store_upload( $upload );
            if ( null === $document ) {
                $this->delete_documents( $new_documents );
                return [ 'success' => false, 'error' => 'upload_storage_failed' ];
            }
            if ( ! empty( $details['documents'][ $field ] ) && is_array( $details['documents'][ $field ] ) ) {
                $replaced_documents[] = $details['documents'][ $field ];
            }
            $details['documents'][ $field ] = $document;
            $new_documents[] = $document;
        }

        $profile['profile_details'] = $details;
        return [
            'success' => true,
            'profile' => $profile,
            'new_documents' => $new_documents,
            'replaced_documents' => $replaced_documents,
        ];
    }

    /**
     * @param array<int, array<string, string>> $documents
     */
    public function delete_documents( array $documents ): void {
        foreach ( $documents as $document ) {
            $path = $this->document_path( $document );
            if ( null !== $path && is_file( $path ) && ! unlink( $path ) ) {
                error_log( 'Applicant private document cleanup failed.' );
            }
        }
    }

    /**
     * Store a validated application upload as a private applicant document.
     *
     * @param array<string, mixed> $file
     * @return array<string, string>|null
     */
    public function store_uploaded_document( array $file, string $field ): ?array {
        if ( ! in_array( $field, [ 'profile_photo', 'resume', 'portfolio', 'proctoring_capture' ], true ) ) {
            throw new RuntimeException( 'Unsupported applicant document field.' );
        }

        $upload = $this->validate_upload(
            [
                'error'    => UPLOAD_ERR_OK,
                'name'     => $file['name'] ?? '',
                'tmp_name' => $file['tmp_name'] ?? '',
            ],
            $field
        );
        if ( null === $upload ) {
            return null;
        }

        return $this->store_upload( $upload );
    }

    /**
     * @param array<string, mixed> $document
     */
    public function document_path( array $document ): ?string {
        $key = is_scalar( $document['key'] ?? null ) ? (string) $document['key'] : '';
        if ( ! preg_match( '/^[a-f0-9]{32}\.(?:pdf|jpg|jpeg|png|doc|docx)$/', $key ) ) {
            return null;
        }
        $directory = $this->private_directory();
        return null === $directory ? null : $directory . DIRECTORY_SEPARATOR . $key;
    }

    private function is_valid_birth_date( string $date ): bool {
        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches ) ) {
            return false;
        }
        return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] )
            && $date <= gmdate( 'Y-m-d' )
            && 1900 <= (int) $matches[1];
    }

    /**
     * @param mixed $rows
     * @param array<int, string> $fields
     * @return array{success: bool, rows: array<int, array<string, string>>}
     */
    private function normalize_rows( $rows, array $fields, int $limit ): array {
        if ( ! is_array( $rows ) || count( $rows ) > $limit ) {
            return [ 'success' => false, 'rows' => [] ];
        }

        $normalized = [];
        foreach ( array_values( $rows ) as $row ) {
            if ( ! is_array( $row ) ) {
                return [ 'success' => false, 'rows' => [] ];
            }
            $item = [];
            $filled = 0;
            foreach ( $fields as $field ) {
                $value = sanitize_text_field( $this->scalar( $row[ $field ] ?? '' ) );
                if ( 255 < $this->text_length( $value ) ) {
                    return [ 'success' => false, 'rows' => [] ];
                }
                $item[ $field ] = $value;
                if ( '' !== $value ) {
                    ++$filled;
                }
            }
            if ( 0 === $filled ) {
                continue;
            }
            if ( count( $fields ) !== $filled ) {
                return [ 'success' => false, 'rows' => [] ];
            }
            $normalized[] = $item;
        }

        return [ 'success' => true, 'rows' => $normalized ];
    }

    /**
     * @param array<string, mixed> $files
     * @return array<string, mixed>|null
     */
    private function uploaded_file( array $files, string $field ): ?array {
        $file = $files[ $field ] ?? null;
        if ( ! is_array( $file ) ) {
            return null;
        }
        $error = (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE );
        if ( UPLOAD_ERR_NO_FILE === $error ) {
            return null;
        }
        if ( UPLOAD_ERR_OK !== $error ) {
            return [ 'invalid' => true ];
        }
        return $file;
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>|null
     */
    private function validate_upload( array $file, string $field ): ?array {
        $temporary_path = is_scalar( $file['tmp_name'] ?? null ) ? (string) $file['tmp_name'] : '';
        if ( '' === $temporary_path || ! is_uploaded_file( $temporary_path ) || ! is_readable( $temporary_path ) ) {
            return null;
        }
        $size = filesize( $temporary_path );
        $max_upload_size = 'proctoring_capture' === $field ? self::MAX_PROCTORING_UPLOAD_SIZE : self::MAX_UPLOAD_SIZE;
        if ( false === $size || 0 === $size || $max_upload_size < $size || ! function_exists( 'finfo_open' ) ) {
            return null;
        }
        $file_info = finfo_open( FILEINFO_MIME_TYPE );
        if ( false === $file_info ) {
            return null;
        }
        $mime_type = finfo_file( $file_info, $temporary_path );
        finfo_close( $file_info );

        $allowed = [
            'profile_photo' => [ 'image/jpeg' => 'jpg', 'image/png' => 'png' ],
            'proctoring_capture' => [ 'image/jpeg' => 'jpg' ],
            'resume' => [
                'application/pdf' => 'pdf',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            ],
            'portfolio' => [
                'application/pdf' => 'pdf',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
            ],
        ];
        if ( ! is_string( $mime_type ) || ! isset( $allowed[ $field ][ $mime_type ] ) ) {
            return null;
        }
        $extension = strtolower( pathinfo( sanitize_file_name( $this->scalar( $file['name'] ?? '' ) ), PATHINFO_EXTENSION ) );
        $allowed_extensions = [
            'image/jpeg' => [ 'jpg', 'jpeg' ],
            'image/png' => [ 'png' ],
            'application/pdf' => [ 'pdf' ],
            'application/msword' => [ 'doc' ],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => [ 'docx' ],
        ];
        if ( ! in_array( $extension, $allowed_extensions[ $mime_type ] ?? [], true ) ) {
            return null;
        }

        return [
            'tmp_name' => $temporary_path,
            'extension' => $allowed[ $field ][ $mime_type ],
            'mime_type' => $mime_type,
            'name' => sanitize_file_name( $this->scalar( $file['name'] ?? '' ) ),
            'size' => (string) $size,
        ];
    }

    /**
     * @param array<string, string> $upload
     * @return array<string, string>|null
     */
    private function store_upload( array $upload ): ?array {
        $directory = $this->private_directory();
        if ( null === $directory ) {
            return null;
        }
        try {
            $key = bin2hex( random_bytes( 16 ) ) . '.' . $upload['extension'];
        } catch ( RuntimeException $exception ) {
            error_log( 'Applicant private document name generation failed.' );
            return null;
        }
        $destination = $directory . DIRECTORY_SEPARATOR . $key;
        if ( ! move_uploaded_file( $upload['tmp_name'], $destination ) ) {
            return null;
        }
        return [
            'key' => $key,
            'name' => $upload['name'],
            'mime_type' => $upload['mime_type'],
            'size' => $upload['size'],
        ];
    }

    private function private_directory(): ?string {
        if ( ! function_exists( 'wp_upload_dir' ) ) {
            return null;
        }
        $uploads = wp_upload_dir( null, false );
        if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
            return null;
        }
        $directory = rtrim( (string) $uploads['basedir'], '/\\' ) . DIRECTORY_SEPARATOR . 'daw-applicant-private';
        if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
            return null;
        }
        $deny_rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n";
        $deny_file = $directory . DIRECTORY_SEPARATOR . '.htaccess';
        if ( ( is_file( $deny_file ) && $deny_rules !== file_get_contents( $deny_file ) )
            || ( ! is_file( $deny_file ) && false === file_put_contents( $deny_file, $deny_rules, LOCK_EX ) )
        ) {
            return null;
        }
        $index_file = $directory . DIRECTORY_SEPARATOR . 'index.php';
        if ( ! is_file( $index_file ) && false === file_put_contents( $index_file, "<?php\nhttp_response_code( 404 );\nexit;\n", LOCK_EX ) ) {
            return null;
        }
        return $directory;
    }

    /** @param mixed $value */
    private function scalar( $value ): string {
        return is_scalar( $value ) ? (string) $value : '';
    }

    private function text_length( string $value ): int {
        return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
    }
}
