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
                    'SELECT hr_id AS "id", nama_pengguna AS "email", password AS "password_hash" FROM hr_user WHERE LOWER(nama_pengguna) = :identifier'
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
                    'SELECT userdepartemen_id AS "id", username AS "username", email AS "email", kata_sandi AS "password_hash", departemen_id AS "department_id", nama_departemen AS "department" FROM users_ad WHERE LOWER(email) = :email OR LOWER(username) = :username'
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

            if ( ! password_verify( $password, (string) $row['password_hash'] ) ) {
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
        return $this->fetch_all( 'SELECT departemen_id AS "id", nama_departemen AS "name" FROM departemen ORDER BY nama_departemen' );
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
                'INSERT INTO users_ad (departemen_id, nama_departemen, username, email, kata_sandi) VALUES (:department_id, :department_name, :username, :email, :password_hash)'
            );
            $statement->execute(
                [
                    'department_id'   => $department_id,
                    'department_name' => (string) $department_name,
                    'username'        => $username,
                    'email'           => $email,
                    'password_hash'   => $password_hash,
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
     * Return all published vacancies from Oracle.
     *
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