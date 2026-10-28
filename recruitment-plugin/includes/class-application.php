<?php

/**
 * Application input normalization and validation.
 *
 * This service keeps request handling out of the page template and returns a
 * stable payload that can later be passed to Recruitment_Database.
 */
class Recruitment_Application {

    /**
     * Validate and normalize one public application submission.
     *
     * @param array<string, mixed> $request
     * @param array<string, mixed> $files
     * @return array{errors: array<int, string>, payload: array<string, mixed>}
     */
    public function prepare_submission( array $request, array $files = [] ): array {
        $payload = $this->build_payload( $request, $files );
        $errors  = [];

        if ( '' === $payload['applicant']['full_name'] || ! is_email( $payload['applicant']['email'] ) ) {
            $errors[] = 'Nama lengkap dan email aktif wajib diisi dengan benar.';
        }

        $required_fields = [
            'phone'            => $payload['applicant']['phone'],
            'birth_date'       => $payload['applicant']['birth_date'],
            'address'          => $payload['applicant']['address'],
            'identity_number'  => $payload['applicant']['identity_number'],
            'marital_status'   => $payload['family']['marital_status'],
            'family_relation'  => $payload['family']['family_relation'],
            'personal_relation' => $payload['family']['personal_relation'],
            'salary_expectation' => $payload['preferences']['salary_expectation'],
            'known_employee'   => $payload['preferences']['known_employee'],
        ];
        foreach ( $required_fields as $field => $value ) {
            if ( '' === $value ) {
                $errors[] = 'Lengkapi seluruh field wajib sebelum mengirim lamaran.';
                break;
            }
        }

        if ( ! empty( $payload['photo']['error'] ) && UPLOAD_ERR_NO_FILE !== $payload['photo']['error'] ) {
            $errors[] = 'Upload foto gagal. Silakan pilih file foto yang valid dan coba lagi.';
        }

        if ( ! $payload['education'] || '' === ( $payload['education'][0]['graduation_year'] ?? '' ) || '' === ( $payload['education'][0]['school'] ?? '' ) ) {
            $errors[] = 'Riwayat pendidikan terakhir wajib diisi.';
        }

        foreach ( [ 'pdp_accuracy', 'pdp_data', 'pdp_placement' ] as $consent ) {
            if ( empty( $payload['consents'][ $consent ] ) ) {
                $errors[] = 'Centang seluruh pernyataan PDP sebelum mengirim lamaran.';
                break;
            }
        }

        return [ 'errors' => $errors, 'payload' => $payload ];
    }

    /**
     * Validate and persist one application.
     *
     * @param array<string, mixed> $request
     * @param array<string, mixed> $files
     * @return array{errors: array<int, string>, payload: array<string, mixed>, success: bool}
     */
    public function submit( array $request, array $files = [] ): array {
        $prepared = $this->prepare_submission( $request, $files );
        if ( $prepared['errors'] ) {
            return [ 'errors' => $prepared['errors'], 'payload' => $prepared['payload'], 'success' => false ];
        }

        $fingerprint = hash( 'sha256', wp_json_encode( $prepared['payload'] ) );
        if ( isset( $_SESSION['recruitment_submission'][ $fingerprint ] ) ) {
            return [ 'errors' => [], 'payload' => $prepared['payload'], 'success' => true ];
        }

        $saved = ( new Recruitment_Database() )->save_application( $prepared['payload'] );
        if ( empty( $saved['success'] ) ) {
            return [
                'errors'  => [ (string) ( $saved['error'] ?? 'Data lamaran gagal disimpan. Silakan coba lagi.' ) ],
                'payload' => $prepared['payload'],
                'success' => false,
            ];
        }

        $_SESSION['recruitment_submission'][ $fingerprint ] = true;
        return [ 'errors' => [], 'payload' => $prepared['payload'], 'success' => true ];
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $files
     * @return array<string, mixed>
     */
    private function build_payload( array $request, array $files ): array {
        return [
            'application' => [
                'vacancy_id' => absint( $request['job_id'] ?? 0 ),
                'status'     => 'submitted',
            ],
            'applicant'   => [
                'full_name'       => $this->text( $request['full_name'] ?? '' ),
                'email'           => sanitize_email( $this->text( $request['email'] ?? '' ) ),
                'phone'           => $this->text( $request['phone'] ?? '' ),
                'birth_place'     => $this->text( $request['birth_place'] ?? '' ),
                'birth_date'      => $this->text( $request['birth_date'] ?? '' ),
                'address'         => $this->text( $request['address'] ?? '' ),
                'identity_number' => $this->text( $request['identity_number'] ?? '' ),
                'social_platform' => $this->text( $request['social_platform'] ?? '' ),
                'social_username' => $this->text( $request['social_username'] ?? '' ),
            ],
            'education'   => $this->rows(
                $request,
                [ 'education_level', 'graduation_year', 'school', 'major' ],
                'education_level'
            ),
            'work_history' => $this->rows(
                $request,
                [ 'company', 'position', 'work_period', 'supervisor', 'supervisor_phone' ],
                'company'
            ),
            'family'      => [
                'marital_status'  => $this->text( $request['marital_status'] ?? '' ),
                'family_relation' => $this->text( $request['family_relation'] ?? '' ),
                'personal_relation' => $this->text( $request['personal_relation'] ?? '' ),
                'immediate_family' => $this->text( $request['immediate_family'] ?? '' ),
                'personal_family'  => $this->text( $request['personal_family'] ?? '' ),
            ],
            'preferences' => [
                'salary_expectation' => $this->text( $request['salary_expectation'] ?? '' ),
                'benefits'           => $this->text( $request['benefits'] ?? '' ),
                'known_employee'     => $this->text( $request['known_employee'] ?? '' ),
            ],
            'consents'    => [
                'pdp_accuracy'  => ! empty( $request['pdp_accuracy'] ),
                'pdp_data'      => ! empty( $request['pdp_data'] ),
                'pdp_placement' => ! empty( $request['pdp_placement'] ),
            ],
            'photo'       => $this->file_metadata( $files['photo'] ?? [] ),
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @param string[]             $columns
     * @return array<int, array<string, string>>
     */
    private function rows( array $request, array $columns, string $required_column ): array {
        $values = [];
        foreach ( $columns as $column ) {
            $values[ $column ] = isset( $request[ $column ] ) && is_array( $request[ $column ] ) ? $request[ $column ] : [];
        }

        $rows = [];
        foreach ( $values[ $required_column ] as $index => $required_value ) {
            $row = [];
            foreach ( $columns as $column ) {
                $row[ $column ] = $this->text( $values[ $column ][ $index ] ?? '' );
            }
            if ( '' !== $row[ $required_column ] || count( array_filter( $row ) ) > 1 ) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @param mixed $value */
    private function text( $value ): string {
        return sanitize_textarea_field( is_scalar( $value ) ? (string) $value : '' );
    }

    /** @param array<string, mixed> $file */
    private function file_metadata( array $file ): array {
        return [
            'name'     => sanitize_file_name( (string) ( $file['name'] ?? '' ) ),
            'type'     => sanitize_mime_type( (string) ( $file['type'] ?? '' ) ),
            'size'     => absint( $file['size'] ?? 0 ),
            'error'    => absint( $file['error'] ?? UPLOAD_ERR_NO_FILE ),
            'tmp_name' => (string) ( $file['tmp_name'] ?? '' ),
        ];
    }
}
