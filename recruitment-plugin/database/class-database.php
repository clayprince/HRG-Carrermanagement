<?php

require_once __DIR__ . '/connection/class-oracle-connection.php';

/**
 * Oracle data gateway for the recruitment domain.
 *
 * Pages use this gateway; Oracle configuration and PDO creation are handled
 * by Recruitment_Oracle_Connection.
 */
class Recruitment_Database {

    private Recruitment_Oracle_Connection $oracle;
    private ?string $last_error = null;

    /**
     * @param array<string, string> $config Optional configuration overrides.
     */
    public function __construct( array $config = [] ) {
        $this->oracle = new Recruitment_Oracle_Connection( $config );
    }

    /**
     * Create an Oracle-backed applicant login.
     *
     * @return array{success: bool, error?: string}
     */
    public function create_applicant_account( string $email, string $full_name, string $password ): array {
        $email = strtolower( trim( $email ) );
        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) || '' === trim( $full_name ) || strlen( $password ) < 10 ) {
            return [ 'success' => false, 'error' => 'invalid_account_data' ];
        }

        try {
            $password_hash = password_hash( $password, PASSWORD_DEFAULT );
            if ( false === $password_hash ) {
                throw new RuntimeException( 'Applicant password could not be hashed.' );
            }

            $statement = $this->get_connection()->prepare(
                'INSERT INTO akun_pelamar (email, nama_lengkap, password_hash, status_akun, dibuat_pada) VALUES (:email, :full_name, :password_hash, :status, CURRENT_TIMESTAMP)'
            );
            $statement->execute(
                [
                    'email'         => $email,
                    'full_name'     => trim( $full_name ),
                    'password_hash' => $password_hash,
                    'status'        => 'ACTIVE',
                ]
            );

            return [ 'success' => true ];
        } catch ( PDOException $exception ) {
            if ( $this->is_unique_violation( $exception ) ) {
                return [ 'success' => false, 'error' => 'email_exists' ];
            }
            $this->log_error( 'Applicant account creation failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        } catch ( RuntimeException $exception ) {
            $this->log_error( 'Applicant account creation failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Verify an applicant's Oracle-backed credentials.
     *
     * @return array{success: bool, account?: array{email: string, full_name: string}, error?: string}
     */
    public function authenticate_applicant_account( string $email, string $password ): array {
        $email = strtolower( trim( $email ) );
        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) || '' === $password ) {
            return [ 'success' => false, 'error' => 'invalid_credentials' ];
        }

        try {
            $statement = $this->get_connection()->prepare(
                'SELECT email AS "email", nama_lengkap AS "full_name", password_hash AS "password_hash", status_akun AS "status" FROM akun_pelamar WHERE email = :email'
            );
            $statement->execute( [ 'email' => $email ] );
            $account = $statement->fetch( PDO::FETCH_ASSOC );

            if ( ! $account || 'ACTIVE' !== strtoupper( (string) $account['status'] ) || ! password_verify( $password, (string) $account['password_hash'] ) ) {
                return [ 'success' => false, 'error' => 'invalid_credentials' ];
            }

            if ( password_needs_rehash( (string) $account['password_hash'], PASSWORD_DEFAULT ) ) {
                $password_hash = password_hash( $password, PASSWORD_DEFAULT );
                if ( false === $password_hash ) {
                    throw new RuntimeException( 'Applicant password could not be rehashed.' );
                }
                $update = $this->get_connection()->prepare(
                    'UPDATE akun_pelamar SET password_hash = :password_hash, diperbarui_pada = CURRENT_TIMESTAMP WHERE email = :email'
                );
                $update->execute( [ 'password_hash' => $password_hash, 'email' => $email ] );
            }

            return [
                'success' => true,
                'account' => [ 'email' => (string) $account['email'], 'full_name' => (string) $account['full_name'] ],
            ];
        } catch ( PDOException $exception ) {
            $this->log_error( 'Applicant Oracle authentication failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        } catch ( RuntimeException $exception ) {
            $this->log_error( 'Applicant Oracle authentication failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Verify a portal account stored in HR_USER or USERS_AD.
     *
     * @return array{success: bool, account?: array{type: string, id: string, email: string, username: string, display_name: string, department_id: string, department: string}, error?: string}
     */
    public function authenticate_portal_account( string $target, string $identifier, string $password ): array {
        $identifier = strtolower( trim( $identifier ) );
        if ( ! in_array( $target, [ 'hr', 'user_department' ], true ) || '' === $identifier || '' === $password ) {
            return [ 'success' => false, 'error' => 'invalid_credentials' ];
        }

        try {
            if ( 'hr' === $target ) {
                if ( ! filter_var( $identifier, FILTER_VALIDATE_EMAIL ) ) {
                    return [ 'success' => false, 'error' => 'invalid_credentials' ];
                }

                $statement = $this->get_connection()->prepare(
                    'SELECT hr_id AS "id", nama_pengguna AS "email", password AS "password_hash", status_akun AS "status" FROM hr_user WHERE LOWER(nama_pengguna) = :identifier'
                );
                $statement->execute( [ 'identifier' => $identifier ] );
                $rows = $statement->fetchAll( PDO::FETCH_ASSOC );
                if ( 1 !== count( $rows ) ) {
                    return [ 'success' => false, 'error' => 'invalid_credentials' ];
                }

                $row = $rows[0];
                $account = [
                    'type'          => 'hr',
                    'id'            => (string) $row['id'],
                    'email'         => (string) $row['email'],
                    'username'      => (string) $row['email'],
                    'display_name'  => (string) $row['email'],
                    'department_id' => '',
                    'department'    => '',
                ];
                $password_column = 'password';
                $id_column = 'hr_id';
            } else {
                $statement = $this->get_connection()->prepare(
                    'SELECT userdepartemen_id AS "id", username AS "username", email AS "email", kata_sandi AS "password_hash", departemen_id AS "department_id", nama_departemen AS "department", status_akun AS "status" FROM users_ad WHERE LOWER(email) = :email OR LOWER(username) = :username'
                );
                $statement->execute( [ 'email' => $identifier, 'username' => $identifier ] );
                $rows = $statement->fetchAll( PDO::FETCH_ASSOC );
                if ( 1 !== count( $rows ) ) {
                    return [ 'success' => false, 'error' => 'invalid_credentials' ];
                }

                $row = $rows[0];
                $account = [
                    'type'          => 'user_department',
                    'id'            => (string) $row['id'],
                    'email'         => (string) $row['email'],
                    'username'      => (string) $row['username'],
                    'display_name'  => (string) $row['username'],
                    'department_id' => (string) $row['department_id'],
                    'department'    => (string) $row['department'],
                ];
                $password_column = 'kata_sandi';
                $id_column = 'userdepartemen_id';
            }

            if ( 'ACTIVE' !== strtoupper( (string) $row['status'] ) || ! password_verify( $password, (string) $row['password_hash'] ) ) {
                return [ 'success' => false, 'error' => 'invalid_credentials' ];
            }

            if ( password_needs_rehash( (string) $row['password_hash'], PASSWORD_DEFAULT ) ) {
                $password_hash = password_hash( $password, PASSWORD_DEFAULT );
                if ( false === $password_hash ) {
                    throw new RuntimeException( 'Portal account password could not be rehashed.' );
                }
                $update = $this->get_connection()->prepare(
                    'UPDATE ' . ( 'hr' === $target ? 'hr_user' : 'users_ad' ) . ' SET ' . $password_column . ' = :password_hash WHERE ' . $id_column . ' = :account_id'
                );
                $update->execute( [ 'password_hash' => $password_hash, 'account_id' => $account['id'] ] );
            }

            return [ 'success' => true, 'account' => $account ];
        } catch ( PDOException $exception ) {
            $this->log_error( 'Portal Oracle authentication failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        } catch ( RuntimeException $exception ) {
            $this->log_error( 'Portal Oracle authentication failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function get_departments(): array {
        $this->last_error = null;
        return $this->fetch_all( 'SELECT departemen_id AS "id", nama_departemen AS "name" FROM departemen ORDER BY nama_departemen' );
    }

    /**
     * Find an active HR account for a password-reset request.
     *
     * @return array{success: bool, account?: array{id: string, email: string}, error?: string}
     */
    public function get_active_hr_account_by_email( string $email ): array {
        $email = strtolower( trim( $email ) );
        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            return [ 'success' => false, 'error' => 'account_not_found' ];
        }

        try {
            $statement = $this->get_connection()->prepare(
                'SELECT hr_id AS "id", nama_pengguna AS "email" FROM hr_user WHERE LOWER(nama_pengguna) = :email AND status_akun = :status'
            );
            $statement->execute( [ 'email' => $email, 'status' => 'ACTIVE' ] );
            $rows = $statement->fetchAll( PDO::FETCH_ASSOC );
            if ( 1 !== count( $rows ) ) {
                return [ 'success' => false, 'error' => 'account_not_found' ];
            }

            return [
                'success' => true,
                'account' => [ 'id' => (string) $rows[0]['id'], 'email' => (string) $rows[0]['email'] ],
            ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'HR Oracle password reset lookup failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Return department accounts without exposing their password hashes.
     *
     * @return array<int, array{id: string, username: string, email: string, department_id: string, department: string, status: string}>
     */
    public function get_department_accounts(): array {
        $this->last_error = null;
        return $this->fetch_all(
            'SELECT u.userdepartemen_id AS "id", u.username AS "username", u.email AS "email", u.departemen_id AS "department_id", NVL(d.nama_departemen, u.nama_departemen) AS "department", u.status_akun AS "status" FROM users_ad u LEFT JOIN departemen d ON d.departemen_id = u.departemen_id ORDER BY u.username'
        );
    }

    /**
     * Set the active state of one Oracle-backed department account.
     *
     * @return array{success: bool, error?: string}
     */
    public function set_department_account_status( int $account_id, string $status ): array {
        $status = strtoupper( trim( $status ) );
        if ( $account_id < 1 || ! in_array( $status, [ 'ACTIVE', 'INACTIVE' ], true ) ) {
            return [ 'success' => false, 'error' => 'invalid_account_status' ];
        }

        try {
            $statement = $this->get_connection()->prepare(
                'UPDATE users_ad SET status_akun = :status WHERE userdepartemen_id = :account_id'
            );
            $statement->execute( [ 'status' => $status, 'account_id' => $account_id ] );
            if ( 1 !== $statement->rowCount() ) {
                return [ 'success' => false, 'error' => 'account_not_found' ];
            }

            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'Department Oracle account status update failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Create an Oracle-backed department account with a PHP password hash.
     *
     * @return array{success: bool, error?: string}
     */
    public function create_department_account( string $username, string $email, int $department_id, string $password ): array {
        $username = trim( $username );
        $email = strtolower( trim( $email ) );
        if ( '' === $username || strlen( $username ) > 100 || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) || strlen( $email ) > 50 || $department_id < 1 || strlen( $password ) < 8 ) {
            return [ 'success' => false, 'error' => 'invalid_account_data' ];
        }

        try {
            $department_statement = $this->get_connection()->prepare(
                'SELECT nama_departemen FROM departemen WHERE departemen_id = :department_id'
            );
            $department_statement->execute( [ 'department_id' => $department_id ] );
            $department_name = $department_statement->fetchColumn();
            if ( false === $department_name ) {
                return [ 'success' => false, 'error' => 'department_not_found' ];
            }

            $password_hash = password_hash( $password, PASSWORD_DEFAULT );
            if ( false === $password_hash ) {
                throw new RuntimeException( 'Department account password could not be hashed.' );
            }

            $statement = $this->get_connection()->prepare(
                'INSERT INTO users_ad (departemen_id, nama_departemen, username, email, kata_sandi, status_akun) VALUES (:department_id, :department_name, :username, :email, :password_hash, :status)'
            );
            $statement->execute(
                [
                    'department_id'   => $department_id,
                    'department_name' => (string) $department_name,
                    'username'        => $username,
                    'email'           => $email,
                    'password_hash'   => $password_hash,
                    'status'          => 'ACTIVE',
                ]
            );

            return [ 'success' => true ];
        } catch ( PDOException $exception ) {
            if ( $this->is_unique_violation( $exception ) ) {
                return [ 'success' => false, 'error' => 'duplicate_account' ];
            }
            $this->log_error( 'Department Oracle account creation failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        } catch ( RuntimeException $exception ) {
            $this->log_error( 'Department Oracle account creation failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Verify the current HR password and replace it with a new PHP password hash.
     *
     * @return array{success: bool, error?: string}
     */
    public function change_hr_password( string $account_id, string $current_password, string $new_password ): array {
        if ( ! ctype_digit( $account_id ) || '0' === $account_id || '' === $current_password || strlen( $new_password ) < 8 ) {
            return [ 'success' => false, 'error' => 'invalid_password_data' ];
        }

        try {
            $connection = $this->get_connection();
            $lookup = $connection->prepare(
                'SELECT password FROM hr_user WHERE hr_id = :account_id'
            );
            $lookup->execute( [ 'account_id' => $account_id ] );
            $current_hash = $lookup->fetchColumn();
            if ( false === $current_hash || ! password_verify( $current_password, (string) $current_hash ) ) {
                return [ 'success' => false, 'error' => 'invalid_current_password' ];
            }

            $new_hash = password_hash( $new_password, PASSWORD_DEFAULT );
            if ( false === $new_hash ) {
                throw new RuntimeException( 'HR password could not be hashed.' );
            }

            $update = $connection->prepare(
                'UPDATE hr_user SET password = :password_hash WHERE hr_id = :account_id'
            );
            $update->execute( [ 'password_hash' => $new_hash, 'account_id' => $account_id ] );
            if ( 1 !== $update->rowCount() ) {
                return [ 'success' => false, 'error' => 'account_not_found' ];
            }

            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'HR Oracle password update failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Replace an active HR password after validating a reset token in WordPress.
     *
     * @return array{success: bool, error?: string}
     */
    public function reset_hr_password( string $account_id, string $new_password ): array {
        if ( ! ctype_digit( $account_id ) || '0' === $account_id || strlen( $new_password ) < 8 ) {
            return [ 'success' => false, 'error' => 'invalid_password_data' ];
        }

        try {
            $password_hash = password_hash( $new_password, PASSWORD_DEFAULT );
            if ( false === $password_hash ) {
                throw new RuntimeException( 'HR reset password could not be hashed.' );
            }

            $statement = $this->get_connection()->prepare(
                'UPDATE hr_user SET password = :password_hash WHERE hr_id = :account_id AND status_akun = :status'
            );
            $statement->execute(
                [
                    'password_hash' => $password_hash,
                    'account_id'    => $account_id,
                    'status'        => 'ACTIVE',
                ]
            );
            if ( 1 !== $statement->rowCount() ) {
                return [ 'success' => false, 'error' => 'account_not_found' ];
            }

            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'HR Oracle password reset failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Return all published vacancies from Oracle.
     *
     * Load the active Oracle-backed application form.
     *
     * @return array{success: bool, sections?: array<int, array<string, mixed>>, error?: string}
     */
    public function get_application_form(): array {
        try {
            $connection = $this->get_connection();
            $section_statement = $connection->query(
                'SELECT BAGIAN_PENDAFTARAN_ID AS "id", SECTION_KEY AS "section_key", NAMA_SECTION AS "title", DESKRIPSI AS "description", DISPLAY_ORDER AS "display_order" FROM BAGIAN_PENDAFTARAN WHERE IS_ACTIVE = 1 ORDER BY DISPLAY_ORDER, BAGIAN_PENDAFTARAN_ID'
            );
            $sections = [];
            $section_indexes = [];
            foreach ( $section_statement->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
                $section_id = (string) $row['id'];
                $section_indexes[ $section_id ] = count( $sections );
                $sections[] = [
                    'id'          => $section_id,
                    'section_key' => (string) ( $row['section_key'] ?? '' ),
                    'title'       => (string) ( $row['title'] ?? '' ),
                    'description' => $this->oracle_lob_to_string( $row['description'] ?? '' ),
                    'questions'   => [],
                ];
            }

            $field_statement = $connection->query(
                'SELECT FIELD_ID AS "id", BAGIAN_PENDAFTARAN_ID AS "section_id", FIELD_KEY AS "key", FIELD_TITLE AS "title", FIELD_TYPE AS "type", IS_REQUIRED AS "required" FROM RECRUIT_FORM_FIELDS WHERE IS_ACTIVE = 1 ORDER BY BAGIAN_PENDAFTARAN_ID, DISPLAY_ORDER, FIELD_ID'
            );
            $fields = [];
            foreach ( $field_statement->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
                $section_id = (string) $row['section_id'];
                if ( ! isset( $section_indexes[ $section_id ] ) ) {
                    throw new RuntimeException( 'An active application field has no active section.' );
                }

                $question = [
                    'id'       => (string) $row['id'],
                    'key'      => (string) $row['key'],
                    'title'    => (string) $row['title'],
                    'type'     => (string) $row['type'],
                    'required' => 1 === (int) $row['required'],
                    'options'  => [],
                ];
                $section_index = $section_indexes[ $section_id ];
                $question_index = count( $sections[ $section_index ]['questions'] );
                $sections[ $section_index ]['questions'][] = $question;
                $fields[ (string) $row['id'] ] = [ $section_index, $question_index ];
            }

            $option_statement = $connection->query(
                'SELECT o.FIELD_ID AS "field_id", o.OPTION_VALUE AS "value", o.OPTION_LABEL AS "label" FROM RECRUIT_FORM_OPTIONS o JOIN RECRUIT_FORM_FIELDS f ON f.FIELD_ID = o.FIELD_ID WHERE f.IS_ACTIVE = 1 ORDER BY o.FIELD_ID, o.DISPLAY_ORDER, o.OPTION_ID'
            );
            foreach ( $option_statement->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
                $field_id = (string) $row['field_id'];
                if ( ! isset( $fields[ $field_id ] ) ) {
                    throw new RuntimeException( 'An application option has no active field.' );
                }
                [ $section_index, $question_index ] = $fields[ $field_id ];
                $sections[ $section_index ]['questions'][ $question_index ]['options'][] = [
                    'value' => (string) $row['value'],
                    'label' => (string) $row['label'],
                ];
            }

            return [ 'success' => true, 'sections' => $sections ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_oracle_failure( 'Application form read failed', $exception );
            return [ 'success' => false, 'error' => 'Form lamaran belum tersedia dari Oracle.' ];
        }
    }

    /**
     * Replace the active form configuration while preserving inactive records.
     *
     * @param array<int, array<string, mixed>> $sections
     * @return array{success: bool, error?: string}
     */
    public function save_application_form( array $sections ): array {
        $connection = null;
        try {
            if ( ! $sections ) {
                return [ 'success' => false, 'error' => 'Form harus memiliki sedikitnya satu section.' ];
            }

            $connection = $this->get_connection();
            $connection->beginTransaction();

            $existing_sections = [];
            foreach ( $connection->query( 'SELECT BAGIAN_PENDAFTARAN_ID FROM BAGIAN_PENDAFTARAN' )->fetchAll( PDO::FETCH_COLUMN ) as $id ) {
                $existing_sections[ (string) $id ] = true;
            }
            $existing_fields = [];
            $existing_field_keys = [];
            $existing_field_statement = $connection->query(
                'SELECT FIELD_ID, FIELD_KEY FROM RECRUIT_FORM_FIELDS'
            );
            foreach ( $existing_field_statement->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
                $field_id = (string) $row['FIELD_ID'];
                $existing_fields[ $field_id ] = true;
                $existing_field_keys[ (string) $row['FIELD_KEY'] ] = $field_id;
            }
            $active_sections = array_map(
                'strval',
                $connection->query( 'SELECT BAGIAN_PENDAFTARAN_ID FROM BAGIAN_PENDAFTARAN WHERE IS_ACTIVE = 1' )->fetchAll( PDO::FETCH_COLUMN )
            );
            $active_fields = array_map(
                'strval',
                $connection->query( 'SELECT FIELD_ID FROM RECRUIT_FORM_FIELDS WHERE IS_ACTIVE = 1' )->fetchAll( PDO::FETCH_COLUMN )
            );

            $submitted_sections = [];
            $submitted_fields = [];
            foreach ( $sections as $section_index => $section ) {
                $section_id = trim( (string) ( $section['id'] ?? '' ) );
                $section_key = 'form_section_' . wp_generate_uuid4();
                if ( '' !== $section_id ) {
                    if ( ! ctype_digit( $section_id ) || ! isset( $existing_sections[ $section_id ] ) || isset( $submitted_sections[ $section_id ] ) ) {
                        throw new InvalidArgumentException( 'The submitted form contains an invalid section reference.' );
                    }
                    $section_statement = $connection->prepare(
                        'UPDATE BAGIAN_PENDAFTARAN SET SECTION_KEY = NVL(SECTION_KEY, :section_key), NAMA_SECTION = :title, DESKRIPSI = :description, DISPLAY_ORDER = :display_order, UPDATE_AT = CURRENT_TIMESTAMP, IS_ACTIVE = 1 WHERE BAGIAN_PENDAFTARAN_ID = :section_id'
                    );
                    $section_statement->execute(
                        [
                            'section_key'  => $section_key,
                            'title'        => $section['title'],
                            'description'  => $section['description'],
                            'display_order' => $section_index + 1,
                            'section_id'   => $section_id,
                        ]
                    );
                } else {
                    $section_statement = $connection->prepare(
                        'INSERT INTO BAGIAN_PENDAFTARAN (SECTION_KEY, NAMA_SECTION, DESKRIPSI, DISPLAY_ORDER, CREATED_AT, UPDATE_AT, IS_ACTIVE) VALUES (:section_key, :title, :description, :display_order, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1)'
                    );
                    $section_statement->execute(
                        [
                            'section_key'  => $section_key,
                            'title'        => $section['title'],
                            'description'  => $section['description'],
                            'display_order' => $section_index + 1,
                        ]
                    );
                    $section_id_statement = $connection->prepare(
                        'SELECT BAGIAN_PENDAFTARAN_ID FROM BAGIAN_PENDAFTARAN WHERE SECTION_KEY = :section_key'
                    );
                    $section_id_statement->execute( [ 'section_key' => $section_key ] );
                    $section_id = (string) $section_id_statement->fetchColumn();
                    if ( '' === $section_id ) {
                        throw new RuntimeException( 'The new application section could not be retrieved.' );
                    }
                    $existing_sections[ $section_id ] = true;
                }

                $submitted_sections[ $section_id ] = true;
                foreach ( $section['questions'] as $question_index => $question ) {
                    $field_id = trim( (string) ( $question['id'] ?? '' ) );
                    $field_key = (string) $question['key'];
                    if ( '' !== $field_id ) {
                        if ( ! ctype_digit( $field_id ) || ! isset( $existing_fields[ $field_id ] ) || isset( $submitted_fields[ $field_id ] ) ) {
                            throw new InvalidArgumentException( 'The submitted form contains an invalid field reference.' );
                        }
                        $field_statement = $connection->prepare(
                            'UPDATE RECRUIT_FORM_FIELDS SET BAGIAN_PENDAFTARAN_ID = :section_id, FIELD_KEY = :field_key, FIELD_TITLE = :field_title, FIELD_TYPE = :field_type, IS_REQUIRED = :is_required, DISPLAY_ORDER = :display_order, IS_ACTIVE = 1, UPDATED_AT = CURRENT_TIMESTAMP WHERE FIELD_ID = :field_id'
                        );
                        $field_statement->execute(
                            [
                                'section_id'   => $section_id,
                                'field_key'    => $field_key,
                                'field_title'  => $question['title'],
                                'field_type'   => $question['type'],
                                'is_required'  => $question['required'] ? 1 : 0,
                                'display_order' => $question_index + 1,
                                'field_id'     => $field_id,
                            ]
                        );
                    } elseif ( isset( $existing_field_keys[ $field_key ] ) ) {
                        $field_id = $existing_field_keys[ $field_key ];
                        $field_statement = $connection->prepare(
                            'UPDATE RECRUIT_FORM_FIELDS SET BAGIAN_PENDAFTARAN_ID = :section_id, FIELD_TITLE = :field_title, FIELD_TYPE = :field_type, IS_REQUIRED = :is_required, DISPLAY_ORDER = :display_order, IS_ACTIVE = 1, UPDATED_AT = CURRENT_TIMESTAMP WHERE FIELD_ID = :field_id'
                        );
                        $field_statement->execute(
                            [
                                'section_id'   => $section_id,
                                'field_title'  => $question['title'],
                                'field_type'   => $question['type'],
                                'is_required'  => $question['required'] ? 1 : 0,
                                'display_order' => $question_index + 1,
                                'field_id'     => $field_id,
                            ]
                        );
                    } else {
                        $field_statement = $connection->prepare(
                            'INSERT INTO RECRUIT_FORM_FIELDS (BAGIAN_PENDAFTARAN_ID, FIELD_KEY, FIELD_TITLE, FIELD_TYPE, IS_REQUIRED, DISPLAY_ORDER, IS_ACTIVE) VALUES (:section_id, :field_key, :field_title, :field_type, :is_required, :display_order, 1)'
                        );
                        $field_statement->execute(
                            [
                                'section_id'   => $section_id,
                                'field_key'    => $field_key,
                                'field_title'  => $question['title'],
                                'field_type'   => $question['type'],
                                'is_required'  => $question['required'] ? 1 : 0,
                                'display_order' => $question_index + 1,
                            ]
                        );
                        $field_id_statement = $connection->prepare(
                            'SELECT FIELD_ID FROM RECRUIT_FORM_FIELDS WHERE FIELD_KEY = :field_key'
                        );
                        $field_id_statement->execute( [ 'field_key' => $field_key ] );
                        $field_id = (string) $field_id_statement->fetchColumn();
                        if ( '' === $field_id ) {
                            throw new RuntimeException( 'The new application field could not be retrieved.' );
                        }
                        $existing_fields[ $field_id ] = true;
                        $existing_field_keys[ $field_key ] = $field_id;
                    }

                    $submitted_fields[ $field_id ] = true;
                    $delete_options = $connection->prepare( 'DELETE FROM RECRUIT_FORM_OPTIONS WHERE FIELD_ID = :field_id' );
                    $delete_options->execute( [ 'field_id' => $field_id ] );
                    foreach ( $question['options'] as $option_index => $option ) {
                        $option_statement = $connection->prepare(
                            'INSERT INTO RECRUIT_FORM_OPTIONS (FIELD_ID, OPTION_VALUE, OPTION_LABEL, DISPLAY_ORDER) VALUES (:field_id, :option_value, :option_label, :display_order)'
                        );
                        $option_statement->execute(
                            [
                                'field_id'      => $field_id,
                                'option_value'  => $option['value'],
                                'option_label'  => $option['label'],
                                'display_order' => $option_index + 1,
                            ]
                        );
                    }
                }
            }

            foreach ( $active_fields as $field_id ) {
                if ( ! isset( $submitted_fields[ $field_id ] ) ) {
                    $statement = $connection->prepare(
                        'UPDATE RECRUIT_FORM_FIELDS SET IS_ACTIVE = 0, UPDATED_AT = CURRENT_TIMESTAMP WHERE FIELD_ID = :field_id'
                    );
                    $statement->execute( [ 'field_id' => $field_id ] );
                }
            }
            foreach ( $active_sections as $section_id ) {
                if ( ! isset( $submitted_sections[ $section_id ] ) ) {
                    $statement = $connection->prepare(
                        'UPDATE BAGIAN_PENDAFTARAN SET IS_ACTIVE = 0, UPDATE_AT = CURRENT_TIMESTAMP WHERE BAGIAN_PENDAFTARAN_ID = :section_id'
                    );
                    $statement->execute( [ 'section_id' => $section_id ] );
                }
            }

            $connection->commit();
            return [ 'success' => true ];
        } catch ( InvalidArgumentException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            return [ 'success' => false, 'error' => 'Data form tidak valid. Muat ulang halaman lalu coba lagi.' ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'Application form save failed', $exception );
            return [ 'success' => false, 'error' => 'Form lamaran gagal disimpan ke Oracle.' ];
        }
    }

    /**
     * Save an application and all of its dynamic answers atomically.
     *
     * @param array<string, mixed> $submission
     * @return array{success: bool, error?: string}
     */
    public function save_application_submission( array $submission ): array {
        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $submission_token = wp_generate_uuid4();
            $statement = $connection->prepare(
                'INSERT INTO RECRUIT_FORM_SUBMISSIONS (SUBMISSION_TOKEN, APPLICANT_EMAIL, APPLICANT_NAME, SOURCE_VACANCY_ID, SOURCE_VACANCY_TITLE, PDP_ACCURACY, PDP_DATA, PDP_PLACEMENT) VALUES (:token, :email, :name, :vacancy_id, :vacancy_title, 1, 1, 1)'
            );
            $statement->execute(
                [
                    'token'         => $submission_token,
                    'email'         => $submission['applicant_email'],
                    'name'          => $submission['applicant_name'],
                    'vacancy_id'    => $submission['vacancy_source_id'],
                    'vacancy_title' => $submission['vacancy_title'],
                ]
            );

            $id_statement = $connection->prepare(
                'SELECT SUBMISSION_ID FROM RECRUIT_FORM_SUBMISSIONS WHERE SUBMISSION_TOKEN = :token'
            );
            $id_statement->execute( [ 'token' => $submission_token ] );
            $submission_id = (string) $id_statement->fetchColumn();
            if ( '' === $submission_id ) {
                throw new RuntimeException( 'The new application submission could not be retrieved.' );
            }

            $answer_statement = $connection->prepare(
                'INSERT INTO RECRUIT_FORM_ANSWERS (SUBMISSION_ID, FIELD_ID, FIELD_KEY, FIELD_TITLE, FIELD_TYPE, ANSWER_VALUE, FILE_NAME, FILE_MIME_TYPE, FILE_SIZE, FILE_CONTENT) VALUES (:submission_id, :field_id, :field_key, :field_title, :field_type, :answer_value, :file_name, :file_mime_type, :file_size, :file_content)'
            );
            foreach ( $submission['answers'] as $answer ) {
                $file = $answer['file'] ?? null;
                $file_stream = null;
                if ( is_array( $file ) ) {
                    $file_stream = fopen( $file['tmp_name'], 'rb' );
                    if ( false === $file_stream ) {
                        throw new RuntimeException( 'The uploaded application file could not be opened.' );
                    }
                }

                $answer_statement->bindValue( ':submission_id', $submission_id );
                $answer_statement->bindValue( ':field_id', (string) $answer['field_id'] );
                $answer_statement->bindValue( ':field_key', $answer['field_key'] );
                $answer_statement->bindValue( ':field_title', $answer['field_title'] );
                $answer_statement->bindValue( ':field_type', $answer['field_type'] );
                $answer_statement->bindValue(
                    ':answer_value',
                    is_string( $answer['value'] ?? null ) ? $answer['value'] : null,
                    is_string( $answer['value'] ?? null ) ? PDO::PARAM_STR : PDO::PARAM_NULL
                );
                $answer_statement->bindValue( ':file_name', is_array( $file ) ? $file['name'] : null );
                $answer_statement->bindValue( ':file_mime_type', is_array( $file ) ? $file['mime_type'] : null );
                $answer_statement->bindValue( ':file_size', is_array( $file ) ? $file['size'] : null );
                $answer_statement->bindValue(
                    ':file_content',
                    $file_stream,
                    is_array( $file ) ? PDO::PARAM_LOB : PDO::PARAM_NULL
                );
                try {
                    $answer_statement->execute();
                } finally {
                    if ( is_resource( $file_stream ) ) {
                        fclose( $file_stream );
                    }
                }
            }

            $connection->commit();
            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'Application submission save failed', $exception );
            return [ 'success' => false, 'error' => 'Data lamaran gagal disimpan. Silakan coba lagi.' ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_vacancies(): array {
        return $this->fetch_all(
            'SELECT id, title, location, dealer, region, job_type, description FROM daw_vacancies WHERE status = :status ORDER BY id DESC',
            [ 'status' => 'published' ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_applicants(): array {
        return $this->fetch_all( 'SELECT id, name, email, phone, created_at FROM daw_applicants ORDER BY created_at DESC' );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_applications(): array {
        return $this->fetch_all( 'SELECT id, vacancy_id, applicant_id, application_token, status, created_at FROM daw_applications ORDER BY created_at DESC' );
    }

    /**
     * Persist an application and its public token in one transaction.
     *
     * The unique index on daw_applications.application_token is the final
     * race-safe guard; a duplicate token causes this method to retry.
     *
     * @param array<string, mixed> $payload
     * @return array{success: bool, token?: string, error?: string}
     */
    public function save_application( array $payload ): array {
        for ( $attempt = 0; $attempt < 3; $attempt++ ) {
            try {
                $token = $this->generate_application_token();
                $connection = $this->get_connection();
                $connection->beginTransaction();

                $applicant = $payload['applicant'];
                $statement = $connection->prepare(
                    'INSERT INTO daw_applicants (name, email, phone, created_at) VALUES (:name, :email, :phone, CURRENT_TIMESTAMP)'
                );
                $statement->execute(
                    [
                        'name'  => $applicant['full_name'],
                        'email' => $applicant['email'],
                        'phone' => $applicant['phone'],
                    ]
                );
                $applicant_id = (int) $connection->lastInsertId();

                $statement = $connection->prepare(
                    'INSERT INTO daw_applications (vacancy_id, applicant_id, application_token, status, created_at) VALUES (:vacancy_id, :applicant_id, :application_token, :status, CURRENT_TIMESTAMP)'
                );
                $statement->execute(
                    [
                        'vacancy_id'       => $payload['application']['vacancy_id'],
                        'applicant_id'     => $applicant_id,
                        'application_token' => $token,
                        'status'           => $payload['application']['status'],
                    ]
                );

                $connection->commit();
                return [ 'success' => true, 'token' => $token ];
            } catch ( PDOException $exception ) {
                if ( isset( $connection ) && $connection->inTransaction() ) {
                    $connection->rollBack();
                }
                if ( $this->is_unique_violation( $exception ) ) {
                    continue;
                }
                error_log( 'Recruitment application save failed: ' . $exception->getMessage() );
                return [ 'success' => false, 'error' => 'Data lamaran gagal disimpan. Silakan coba lagi.' ];
            } catch ( RuntimeException $exception ) {
                $this->log_error( 'Recruitment application save failed', $exception );
                return [ 'success' => false, 'error' => 'Koneksi database belum tersedia. Silakan coba lagi nanti.' ];
            }
        }

        return [ 'success' => false, 'error' => 'Token lamaran gagal dibuat unik. Silakan coba lagi.' ];
    }

    /** @return array<string, mixed>|null */
    public function find_application_by_token( string $token, string $contact = '' ): ?array {
        $rows = $this->fetch_all(
            'SELECT a.id, a.application_token AS token, a.status, a.created_at, p.name, p.email FROM daw_applications a JOIN daw_applicants p ON p.id = a.applicant_id WHERE a.application_token = :token AND (:contact_email = \'\' OR LOWER(p.email) = LOWER(:contact_email) OR p.phone = :contact_phone)',
            [ 'token' => $token, 'contact_email' => $contact, 'contact_phone' => $contact ]
        );
        return $rows[0] ?? null;
    }

    /** @return array<int, array{date: string, status: string, slots: array<int, array<string, string>>}>
     */
    public function get_interview_availability( string $token = '' ): array {
        $rows = $this->fetch_all(
            'SELECT interview_date AS slot_date, start_time, end_time, status FROM daw_interview_slots WHERE status IN (:available, :full) AND interview_date >= CURRENT_DATE ORDER BY interview_date, start_time',
            [ 'available' => 'available', 'full' => 'full' ]
        );
        $days = [];
        foreach ( $rows as $row ) {
            $date = (string) ( $row['slot_date'] ?? '' );
            if ( '' === $date ) {
                continue;
            }
            $days[ $date ]['date'] = $date;
            $days[ $date ]['status'] = 'full';
            $slot_status = strtolower( (string) ( $row['status'] ?? 'full' ) );
            $days[ $date ]['slots'][] = [ 'start' => (string) $row['start_time'], 'end' => (string) $row['end_time'], 'status' => $slot_status ];
            if ( 'available' === $slot_status ) {
                $days[ $date ]['status'] = 'available';
            }
        }
        return array_values( $days );
    }

    public function has_error(): bool {
        return null !== $this->last_error;
    }

    /** @return array<string, string>|null */
    public function get_interview_booking( string $token ): ?array {
        $rows = $this->fetch_all(
            'SELECT interview_date AS date, start_time AS start, end_time AS end, status FROM daw_interview_bookings WHERE application_token = :token',
            [ 'token' => $token ]
        );
        return $rows[0] ?? null;
    }

    /** @return array{success: bool, error?: string} */
    public function save_interview_booking( string $token, string $date, string $start, string $end ): array {
        try {
            $statement = $this->get_connection()->prepare(
                'INSERT INTO daw_interview_bookings (application_token, interview_date, start_time, end_time, status, created_at) VALUES (:token, :date_value, :start_time, :end_time, :status, CURRENT_TIMESTAMP)'
            );
            $statement->execute( [ 'token' => $token, 'date_value' => $date, 'start_time' => $start, 'end_time' => $end, 'status' => 'Confirmed' ] );
            return [ 'success' => true ];
        } catch ( PDOException $exception ) {
            error_log( 'Interview booking save failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Booking jadwal gagal disimpan. Silakan coba lagi.' ];
        } catch ( RuntimeException $exception ) {
            $this->log_error( 'Interview booking save failed', $exception );
            return [ 'success' => false, 'error' => 'Koneksi database belum tersedia. Silakan coba lagi nanti.' ];
        }
    }

    public function is_configured(): bool {
        return $this->oracle->is_configured();
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<int, array<string, mixed>>
     */
    private function fetch_all( string $sql, array $parameters = [] ): array {
        try {
            $statement = $this->get_connection()->prepare( $sql );
            $statement->execute( $parameters );
            return $statement->fetchAll( PDO::FETCH_ASSOC );
        } catch ( PDOException | RuntimeException $exception ) {
            $this->last_error = $exception->getMessage();
            $this->log_error( 'Recruitment Oracle query failed', $exception );
            return [];
        }
    }

    private function get_connection(): PDO {
        return $this->oracle->get_connection();
    }

    /** @param mixed $value */
    private function oracle_lob_to_string( $value ): string {
        if ( is_resource( $value ) ) {
            $content = stream_get_contents( $value );
            if ( false === $content ) {
                throw new RuntimeException( 'An Oracle LOB value could not be read.' );
            }
            return $content;
        }

        if ( is_object( $value ) && method_exists( $value, 'load' ) ) {
            return (string) $value->load();
        }

        return is_scalar( $value ) ? (string) $value : '';
    }

    private function log_oracle_failure( string $context, Throwable $exception ): void {
        $this->last_error = $exception->getMessage();
        error_log( $context . ' (Oracle error ' . (string) $exception->getCode() . ').' );
    }

    private function log_error( string $context, Throwable $exception ): void {
        $this->last_error = $exception->getMessage();
        $previous = $exception->getPrevious();
        $details = $previous ? $previous->getMessage() : $exception->getMessage();
        error_log( $context . ': ' . $details );
    }

    private function generate_application_token(): string {
        return 'DAW-' . gmdate( 'Y' ) . '-' . strtoupper( rtrim( strtr( base64_encode( random_bytes( 6 ) ), '+/', '-_' ), '=' ) );
    }

    private function is_unique_violation( PDOException $exception ): bool {
        return false !== stripos( $exception->getMessage(), 'ORA-00001' ) || false !== stripos( $exception->getMessage(), 'unique constraint' );
    }

}