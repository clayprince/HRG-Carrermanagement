<?php

/**
 * Application form normalization, validation, and submission handling.
 */
class Recruitment_Application {

    /** @var array<string, bool> */
    private const FIELD_TYPES = [
        'short'         => true,
        'paragraph'     => true,
        'number'        => true,
        'date'          => true,
        'email'         => true,
        'phone'         => true,
        'file'          => true,
        'dropdown'      => true,
        'single_choice' => true,
    ];

    /**
     * @param mixed $submitted
     * @return array{sections: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public function normalize_form_configuration( $submitted ): array {
        if ( ! is_array( $submitted ) ) {
            return [ 'sections' => [], 'errors' => [ 'Data form tidak valid.' ] ];
        }

        $errors = [];
        $sections = [];
        $used_keys = [];
        $question_count = 0;
        $submitted = array_values( $submitted );
        if ( count( $submitted ) > 50 ) {
            $errors[] = 'Form tidak boleh memiliki lebih dari 50 section.';
        }

        foreach ( array_slice( $submitted, 0, 50 ) as $section ) {
            if ( ! is_array( $section ) ) {
                $errors[] = 'Data salah satu section tidak valid.';
                continue;
            }

            $raw_section_id = trim( $this->scalar( $section['section_id'] ?? '' ) );
            $section_id = $this->identifier( $raw_section_id );
            if ( '' !== $raw_section_id && '' === $section_id ) {
                $errors[] = 'Referensi section tidak valid.';
            }
            $title = sanitize_text_field( $this->scalar( $section['title'] ?? '' ) );
            $description = sanitize_textarea_field( $this->scalar( $section['description'] ?? '' ) );
            if ( '' === $title ) {
                $errors[] = 'Nama section wajib diisi.';
            } elseif ( $this->text_length( $title ) > 255 ) {
                $errors[] = 'Nama section maksimal 255 karakter.';
            }

            $raw_questions = $section['questions'] ?? [];
            if ( ! is_array( $raw_questions ) ) {
                $errors[] = 'Daftar pertanyaan pada section tidak valid.';
                $raw_questions = [];
            }
            if ( count( $raw_questions ) > 100 ) {
                $errors[] = 'Setiap section maksimal memiliki 100 pertanyaan.';
            }

            $questions = [];
            foreach ( array_slice( array_values( $raw_questions ), 0, 100 ) as $question ) {
                if ( ! is_array( $question ) ) {
                    $errors[] = 'Data salah satu pertanyaan tidak valid.';
                    continue;
                }

                $question_title = sanitize_text_field( $this->scalar( $question['title'] ?? '' ) );
                $question_type = sanitize_key( $this->scalar( $question['type'] ?? 'short' ) );
                if ( '' === $question_title ) {
                    $errors[] = 'Nama pertanyaan wajib diisi.';
                } elseif ( $this->text_length( $question_title ) > 255 ) {
                    $errors[] = 'Nama pertanyaan maksimal 255 karakter.';
                }
                if ( ! isset( self::FIELD_TYPES[ $question_type ] ) ) {
                    $errors[] = 'Jenis jawaban tidak dikenal.';
                    $question_type = 'short';
                }

                $raw_field_id = trim( $this->scalar( $question['field_id'] ?? '' ) );
                $field_id = $this->identifier( $raw_field_id );
                if ( '' !== $raw_field_id && '' === $field_id ) {
                    $errors[] = 'Referensi pertanyaan tidak valid.';
                }

                $key = sanitize_key( $this->scalar( $question['key'] ?? '' ) );
                if ( '' === $key ) {
                    $key = sanitize_title( $question_title );
                }
                $key = substr( $key ?: 'field', 0, 100 );
                $base_key = $key;
                $suffix = 2;
                while ( isset( $used_keys[ $key ] ) ) {
                    $suffix_text = '_' . $suffix;
                    $key = substr( $base_key, 0, 100 - strlen( $suffix_text ) ) . $suffix_text;
                    $suffix++;
                }
                $used_keys[ $key ] = true;

                $options = [];
                if ( in_array( $question_type, [ 'dropdown', 'single_choice' ], true ) ) {
                    $raw_options = $question['options'] ?? '';
                    $raw_options = is_array( $raw_options )
                        ? array_map(
                            function ( $option ) {
                                return $this->scalar( $option );
                            },
                            $raw_options
                        )
                        : ( preg_split( '/\r\n|\r|\n/', $this->scalar( $raw_options ) ) ?: [] );
                    foreach ( $raw_options as $raw_option ) {
                        $option = sanitize_text_field( $raw_option );
                        if ( '' === $option ) {
                            continue;
                        }
                        if ( $this->text_length( $option ) > 255 ) {
                            $errors[] = 'Setiap pilihan jawaban maksimal 255 karakter.';
                            continue;
                        }
                        if ( ! in_array( $option, array_column( $options, 'value' ), true ) ) {
                            $options[] = [ 'value' => $option, 'label' => $option ];
                        }
                    }
                    if ( ! $options ) {
                        $errors[] = 'Pertanyaan pilihan harus memiliki sedikitnya satu opsi.';
                    }
                }

                $questions[] = [
                    'id'       => $field_id,
                    'key'      => $key,
                    'title'    => $question_title,
                    'type'     => $question_type,
                    'required' => ! empty( $question['required'] ),
                    'options'  => $options,
                ];
                $question_count++;
            }

            $sections[] = [
                'id'          => $section_id,
                'title'       => $title,
                'description' => $description,
                'questions'   => $questions,
            ];
        }

        if ( ! $sections ) {
            $errors[] = 'Form harus memiliki sedikitnya satu section.';
        }
        if ( 0 === $question_count ) {
            $errors[] = 'Form harus memiliki sedikitnya satu pertanyaan.';
        }

        return [ 'sections' => $sections, 'errors' => array_values( array_unique( $errors ) ) ];
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $files
     * @param array<int, array<string, mixed>> $sections
     * @param array<string, mixed> $vacancy
     * @return array{errors: array<int, string>, payload: array<string, mixed>}
     */
    public function prepare_submission(
        array $request,
        array $files,
        array $sections,
        string $account_name,
        string $account_email,
        array $vacancy
    ): array {
        $errors = [];
        $answers_input = $request['answers'] ?? [];
        if ( ! is_array( $answers_input ) ) {
            $answers_input = [];
            $errors[] = 'Data jawaban form tidak valid.';
        }

        $answers = [];
        foreach ( $sections as $section ) {
            foreach ( $section['questions'] as $question ) {
                $key = (string) $question['key'];
                $type = (string) $question['type'];
                $raw_value = $answers_input[ $key ] ?? '';

                if ( 'file' === $type ) {
                    $file = $this->uploaded_file( $files, $key );
                    if ( null === $file ) {
                        if ( ! empty( $question['required'] ) ) {
                            $errors[] = 'Unggah file untuk field "' . $question['title'] . '".';
                        }
                        continue;
                    }
                    $validated_file = $this->validate_upload( $file, (string) $question['title'], $errors );
                    if ( null !== $validated_file ) {
                        $answers[] = [
                            'field_id'    => (string) $question['id'],
                            'field_key'   => $key,
                            'field_title' => (string) $question['title'],
                            'field_type'  => $type,
                            'value'       => null,
                            'file'        => $validated_file,
                        ];
                    }
                    continue;
                }

                if ( ! is_scalar( $raw_value ) ) {
                    $errors[] = 'Jawaban untuk field "' . $question['title'] . '" tidak valid.';
                    continue;
                }
                $value = $this->normalize_answer( $type, (string) $raw_value );
                if ( '' !== trim( $value ) && $this->text_length( $value ) > 10000 ) {
                    $errors[] = 'Jawaban untuk field "' . $question['title'] . '" maksimal 10.000 karakter.';
                    continue;
                }
                if ( ! $this->valid_answer( $type, $value, $question['options'] ) ) {
                    $errors[] = 'Jawaban untuk field "' . $question['title'] . '" tidak sesuai format.';
                    continue;
                }
                if ( '' === trim( $value ) ) {
                    if ( ! empty( $question['required'] ) ) {
                        $errors[] = 'Field "' . $question['title'] . '" wajib diisi.';
                    }
                    continue;
                }

                $answers[] = [
                    'field_id'    => (string) $question['id'],
                    'field_key'   => $key,
                    'field_title' => (string) $question['title'],
                    'field_type'  => $type,
                    'value'       => $value,
                    'file'        => null,
                ];
            }
        }

        foreach ( [ 'pdp_accuracy', 'pdp_data', 'pdp_placement' ] as $consent ) {
            if ( ! isset( $request[ $consent ] ) || ! is_scalar( $request[ $consent ] ) || '1' !== (string) $request[ $consent ] ) {
                $errors[] = 'Centang seluruh pernyataan PDP sebelum mengirim lamaran.';
                break;
            }
        }

        $answer_by_key = [];
        foreach ( $answers as $answer ) {
            if ( null !== $answer['value'] ) {
                $answer_by_key[ $answer['field_key'] ] = $answer['value'];
            }
        }
        $applicant_name = sanitize_text_field( $answer_by_key['nama_lengkap'] ?? $account_name );
        $applicant_email = sanitize_email( $answer_by_key['email_aktif'] ?? $account_email );
        if ( '' === $applicant_name || ! is_email( $applicant_email ) ) {
            $errors[] = 'Nama dan email pelamar harus valid.';
        }
        if ( empty( $vacancy['id'] ) || ! ctype_digit( (string) $vacancy['id'] ) || (int) $vacancy['id'] < 1 ) {
            $errors[] = 'Lowongan yang dipilih tidak valid.';
        }
        if ( '' === trim( (string) ( $vacancy['title'] ?? '' ) ) ) {
            $errors[] = 'Judul lowongan tidak tersedia.';
        }

        return [
            'errors'  => array_values( array_unique( $errors ) ),
            'payload' => [
                'applicant_name'   => $applicant_name,
                'applicant_email'  => $applicant_email,
                'vacancy_source_id' => (string) ( $vacancy['id'] ?? '' ),
                'vacancy_title'    => sanitize_text_field( (string) ( $vacancy['title'] ?? '' ) ),
                'answers'          => $answers,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $files
     * @param array<int, array<string, mixed>> $sections
     * @param array<string, mixed> $vacancy
     * @return array{errors: array<int, string>, payload: array<string, mixed>, success: bool}
     */
    public function submit(
        array $request,
        array $files,
        array $sections,
        string $account_name,
        string $account_email,
        array $vacancy
    ): array {
        $prepared = $this->prepare_submission( $request, $files, $sections, $account_name, $account_email, $vacancy );
        if ( $prepared['errors'] ) {
            return [ 'errors' => $prepared['errors'], 'payload' => $prepared['payload'], 'success' => false ];
        }

        $saved = ( new Recruitment_Database() )->save_application_submission( $prepared['payload'] );
        if ( empty( $saved['success'] ) ) {
            return [
                'errors'  => [ (string) ( $saved['error'] ?? 'Data lamaran gagal disimpan. Silakan coba lagi.' ) ],
                'payload' => $prepared['payload'],
                'success' => false,
            ];
        }

        return [ 'errors' => [], 'payload' => $prepared['payload'], 'success' => true ];
    }

    private function normalize_answer( string $type, string $value ): string {
        if ( 'paragraph' === $type ) {
            return sanitize_textarea_field( $value );
        }
        if ( 'email' === $type ) {
            return sanitize_email( $value );
        }
        return sanitize_text_field( $value );
    }

    /**
     * @param array<int, array{value: string, label: string}> $options
     */
    private function valid_answer( string $type, string $value, array $options ): bool {
        if ( '' === trim( $value ) ) {
            return true;
        }
        if ( 'email' === $type ) {
            return is_email( $value );
        }
        if ( 'number' === $type ) {
            return is_numeric( $value );
        }
        if ( 'date' === $type ) {
            $date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
            return $date instanceof DateTimeImmutable && $date->format( 'Y-m-d' ) === $value;
        }
        if ( in_array( $type, [ 'dropdown', 'single_choice' ], true ) ) {
            return in_array( $value, array_column( $options, 'value' ), true );
        }
        return true;
    }

    /**
     * @param array<string, mixed> $files
     * @return array<string, mixed>|null
     */
    private function uploaded_file( array $files, string $key ): ?array {
        $group = $files['answers'] ?? null;
        if ( ! is_array( $group ) ) {
            return null;
        }
        $error = (int) ( $group['error'][ $key ] ?? UPLOAD_ERR_NO_FILE );
        if ( UPLOAD_ERR_NO_FILE === $error ) {
            return null;
        }
        return [
            'error'    => $error,
            'name'     => $group['name'][ $key ] ?? '',
            'tmp_name' => $group['tmp_name'][ $key ] ?? '',
            'size'     => $group['size'][ $key ] ?? 0,
        ];
    }

    /**
     * @param array<string, mixed> $file
     * @param array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validate_upload( array $file, string $title, array &$errors ): ?array {
        if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
            $errors[] = 'Upload untuk field "' . $title . '" gagal. Pilih file PDF, JPG, atau PNG maksimal 5 MB.';
            return null;
        }

        $temporary_path = (string) ( $file['tmp_name'] ?? '' );
        if ( '' === $temporary_path || ! is_uploaded_file( $temporary_path ) || ! is_readable( $temporary_path ) ) {
            $errors[] = 'File untuk field "' . $title . '" tidak dapat diverifikasi.';
            return null;
        }

        $size = filesize( $temporary_path );
        if ( false === $size || 0 === $size || $size > 5 * 1024 * 1024 ) {
            $errors[] = 'File untuk field "' . $title . '" harus berukuran 1 byte sampai 5 MB.';
            return null;
        }
        if ( ! function_exists( 'finfo_open' ) ) {
            $errors[] = 'Validasi tipe file belum tersedia di server.';
            return null;
        }

        $file_info = finfo_open( FILEINFO_MIME_TYPE );
        if ( false === $file_info ) {
            $errors[] = 'Validasi tipe file gagal dijalankan.';
            return null;
        }
        $mime_type = finfo_file( $file_info, $temporary_path );
        finfo_close( $file_info );

        $allowed_extensions = [
            'application/pdf' => [ 'pdf' ],
            'image/jpeg'      => [ 'jpg', 'jpeg' ],
            'image/png'       => [ 'png' ],
        ];
        $name = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
        $extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
        if ( ! is_string( $mime_type ) || ! isset( $allowed_extensions[ $mime_type ] ) || ! in_array( $extension, $allowed_extensions[ $mime_type ], true ) ) {
            $errors[] = 'File untuk field "' . $title . '" harus berupa PDF, JPG, atau PNG.';
            return null;
        }

        return [
            'name'     => $name,
            'mime_type' => $mime_type,
            'size'     => $size,
            'tmp_name' => $temporary_path,
        ];
    }

    /** @param mixed $value */
    private function scalar( $value ): string {
        return is_scalar( $value ) ? (string) $value : '';
    }

    /** @param mixed $value */
    private function identifier( $value ): string {
        $value = trim( $this->scalar( $value ) );
        return '' === $value || ctype_digit( $value ) ? $value : '';
    }

    private function text_length( string $value ): int {
        return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
    }
}
