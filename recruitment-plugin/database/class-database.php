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
        return [ 'success' => false, 'error' => 'schema_unsupported' ];

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
        return [ 'success' => false, 'error' => 'schema_unsupported' ];

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
     * Verify a portal account stored in HRS_ROLE_AKSES.
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
                    'SELECT role_akses_id AS "id", email AS "email", username AS "username", kata_sandi AS "password_hash", status_akun AS "status" FROM hrs_role_akses WHERE LOWER(email) = :identifier AND peran = :role'
                );
                $statement->execute( [ 'identifier' => $identifier, 'role' => 'ADMIN_HR' ] );
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
                $password_column = 'kata_sandi';
                $id_column = 'role_akses_id';
            } else {
                $statement = $this->get_connection()->prepare(
                    'SELECT role_akses_id AS "id", username AS "username", NVL(nama_lengkap, username) AS "display_name", email AS "email", kata_sandi AS "password_hash", departemen_id AS "department_id", nama_departemen AS "department", status_akun AS "status" FROM hrs_role_akses WHERE (LOWER(email) = :email OR LOWER(username) = :username) AND peran = :role'
                );
                $statement->execute( [ 'email' => $identifier, 'username' => $identifier, 'role' => 'KEPALA_DEPARTEMEN' ] );
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
                $id_column = 'role_akses_id';
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
                    'UPDATE hrs_role_akses SET ' . $password_column . ' = :password_hash WHERE ' . $id_column . ' = :account_id'
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
     * Create an Oracle-backed HR account with a PHP password hash.
     *
     * @return array{success: bool, error?: string}
     */
    public function create_hr_account( string $email, string $password ): array {
        $email = strtolower( trim( $email ) );
        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) || strlen( $email ) > 254 || strlen( $password ) < 8 ) {
            return [ 'success' => false, 'error' => 'invalid_account_data' ];
        }

        try {
            $password_hash = password_hash( $password, PASSWORD_DEFAULT );
            if ( false === $password_hash ) {
                throw new RuntimeException( 'HR password could not be hashed.' );
            }

            $statement = $this->get_connection()->prepare(
                'INSERT INTO hrs_role_akses (nama_pengguna, username, email, kata_sandi, peran, status_akun) VALUES (:email, :email, :email, :password_hash, :role, :status)'
            );
            $statement->execute(
                [
                    'email'         => $email,
                    'password_hash' => $password_hash,
                    'role'          => 'ADMIN_HR',
                    'status'        => 'ACTIVE',
                ]
            );
            $account_statement = $this->get_connection()->prepare( 'SELECT role_akses_id FROM hrs_role_akses WHERE username = :username AND email = :email' );
            $account_statement->execute( [ 'username' => $email, 'email' => $email ] );
            $account_id = (int) $account_statement->fetchColumn();
            if ( $account_id > 0 ) {
                $this->save_account_permissions( $account_id, [ 'request_lowongan', 'user_interview', 'interview_history' ] );
            }
            return [ 'success' => true ];
        } catch ( PDOException $exception ) {
            if ( $this->is_unique_violation( $exception ) ) {
                return [ 'success' => false, 'error' => 'email_exists' ];
            }
            $this->log_error( 'HR account creation failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        } catch ( RuntimeException $exception ) {
            $this->log_error( 'HR account creation failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * @return array<int, array{id: string, name: string, status: string}>
     */
    public function get_departments(): array {
        $this->last_error = null;
        return $this->fetch_all( 'SELECT departemen_id AS "id", nama_departemen AS "name", status_departemen AS "status" FROM HRS_DEPARTEMEN WHERE status_departemen IS NULL OR status_departemen = 1 ORDER BY nama_departemen' );
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
                'SELECT role_akses_id AS "id", email AS "email" FROM hrs_role_akses WHERE LOWER(email) = :email AND peran = :role AND status_akun = :status'
            );
            $statement->execute( [ 'email' => $email, 'role' => 'ADMIN_HR', 'status' => 'ACTIVE' ] );
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
            "SELECT u.role_akses_id AS \"id\", u.username AS \"username\", u.email AS \"email\", u.departemen_id AS \"department_id\", NVL(d.nama_departemen, u.nama_departemen) AS \"department\", u.status_akun AS \"status\" FROM hrs_role_akses u LEFT JOIN HRS_DEPARTEMEN d ON d.departemen_id = u.departemen_id WHERE u.peran = 'KEPALA_DEPARTEMEN' ORDER BY u.username"
        );
    }

    /**
     * Return all portal accounts without exposing password hashes.
     *
     * @return array<int, array{id: string, username: string, email: string, department: string, role: string, status: string}>
     */
    public function get_portal_accounts(): array {
        $this->last_error = null;
        return $this->fetch_all(
            "SELECT
                u.role_akses_id AS \"id\",
                NVL(u.nama_lengkap, NVL(u.username, u.email)) AS \"username\",
                u.nama_lengkap AS \"full_name\",
                u.email AS \"email\",
                NVL(d.nama_departemen, u.nama_departemen) AS \"department\",
                u.peran AS \"role\",
                u.status_akun AS \"status\"
            FROM HRS_ROLE_AKSES u
            LEFT JOIN HRS_DEPARTEMEN d ON d.departemen_id = u.departemen_id
            WHERE u.peran IN ('ADMIN_HR', 'KEPALA_DEPARTEMEN')
            ORDER BY u.role_akses_id"
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
                'UPDATE hrs_role_akses SET status_akun = :status WHERE role_akses_id = :account_id AND peran = :role'
            );
            $statement->execute( [ 'status' => $status, 'account_id' => $account_id, 'role' => 'KEPALA_DEPARTEMEN' ] );
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
                'SELECT nama_departemen FROM HRS_DEPARTEMEN WHERE departemen_id = :department_id'
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
                'INSERT INTO hrs_role_akses (departemen_id, nama_departemen, username, nama_lengkap, email, kata_sandi, peran, status_akun) VALUES (:department_id, :department_name, :username, :full_name, :email, :password_hash, :role, :status)'
            );
            $statement->execute(
                [
                    'department_id'   => $department_id,
                    'department_name' => (string) $department_name,
                    'username'        => $username,
                    'full_name'       => $username,
                    'email'           => $email,
                    'password_hash'   => $password_hash,
                    'role'            => 'KEPALA_DEPARTEMEN',
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
     * Update one department account and optionally replace its password.
     *
     * @return array{success: bool, error?: string}
     */
    public function update_department_account( int $account_id, string $username, string $full_name, string $email, int $department_id, ?string $password ): array {
        if ( $account_id < 1 || '' === $username || '' === $full_name || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) || $department_id < 1 ) {
            return [ 'success' => false, 'error' => 'invalid_account_data' ];
        }
        try {
            $connection = $this->get_connection();
            $department = $connection->prepare( 'SELECT nama_departemen FROM HRS_DEPARTEMEN WHERE departemen_id = :id AND (status_departemen IS NULL OR status_departemen = 1)' );
            $department->execute( [ 'id' => $department_id ] );
            $department_name = $department->fetchColumn();
            if ( false === $department_name ) {
                return [ 'success' => false, 'error' => 'department_not_found' ];
            }
            $sql = 'UPDATE hrs_role_akses SET username = :username, nama_lengkap = :full_name, email = :email, departemen_id = :department_id, nama_departemen = :department_name';
            $params = [ 'username' => $username, 'full_name' => $full_name, 'email' => strtolower( $email ), 'department_id' => $department_id, 'department_name' => $department_name, 'id' => $account_id, 'role' => 'KEPALA_DEPARTEMEN' ];
            if ( null !== $password && strlen( $password ) >= 8 ) {
                $password_hash = password_hash( $password, PASSWORD_DEFAULT );
                if ( false === $password_hash ) {
                    return [ 'success' => false, 'error' => 'invalid_password' ];
                }
                $sql .= ', kata_sandi = :password_hash';
                $params['password_hash'] = $password_hash;
            }
            $statement = $connection->prepare( $sql . ' WHERE role_akses_id = :id AND peran = :role' );
            $statement->execute( $params );
            return 1 === $statement->rowCount() ? [ 'success' => true ] : [ 'success' => false, 'error' => 'account_not_found' ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'Department Oracle account update failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Delete one department account only.
     *
     * @return array{success: bool, error?: string}
     */
    public function delete_department_account( int $account_id ): array {
        try {
            $statement = $this->get_connection()->prepare( 'DELETE FROM hrs_role_akses WHERE role_akses_id = :id AND peran = :role' );
            $statement->execute( [ 'id' => $account_id, 'role' => 'KEPALA_DEPARTEMEN' ] );
            return 1 === $statement->rowCount() ? [ 'success' => true ] : [ 'success' => false, 'error' => 'account_not_found' ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'Department Oracle account deletion failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }

    }

    /**
     * Replace the feature grants for one account.
     *
     * @param string[] $features
     */
    public function save_account_permissions( int $account_id, array $features ): void {
        $allowed = [ 'request_lowongan', 'user_interview', 'interview_history', 'recruitment_report' ];
        $features = array_values( array_intersect( $allowed, $features ) );
        $connection = $this->get_connection();
        $delete = $connection->prepare( 'DELETE FROM HRS_ROLE_AKSES_FITUR WHERE ROLE_AKSES_ID = :id' );
        $delete->execute( [ 'id' => $account_id ] );
        $insert = $connection->prepare( 'INSERT INTO HRS_ROLE_AKSES_FITUR (ROLE_AKSES_ID, FITUR_KEY, DIIZINKAN) VALUES (:id, :feature, 1)' );
        foreach ( $features as $feature ) {
            $insert->execute( [ 'id' => $account_id, 'feature' => $feature ] );
        }
    }

    /**
     * Update non-secret identity fields for one HR account.
     *
     * @return array{success: bool, error?: string}
     */
    public function update_hr_profile( string $account_id, string $username, string $email ): array {
        if ( ! ctype_digit( $account_id ) || '0' === $account_id || '' === $username || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            return [ 'success' => false, 'error' => 'invalid_profile_data' ];
        }
        try {
            $connection = $this->get_connection();
            $duplicate = $connection->prepare(
                'SELECT COUNT(*) FROM hrs_role_akses WHERE (LOWER(username) = :username OR LOWER(email) = :email) AND role_akses_id <> :account_id'
            );
            $duplicate->execute( [ 'username' => strtolower( $username ), 'email' => strtolower( $email ), 'account_id' => $account_id ] );
            if ( 0 < (int) $duplicate->fetchColumn() ) {
                return [ 'success' => false, 'error' => 'duplicate_account' ];
            }
            $update = $connection->prepare(
                'UPDATE hrs_role_akses SET username = :username, email = :email WHERE role_akses_id = :account_id AND peran = :role'
            );
            $update->execute( [ 'username' => $username, 'email' => strtolower( $email ), 'account_id' => $account_id, 'role' => 'ADMIN_HR' ] );
            return 1 === $update->rowCount() ? [ 'success' => true ] : [ 'success' => false, 'error' => 'account_not_found' ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'HR Oracle profile update failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Verify a portal password without changing it.
     *
     * @return array{success: bool, error?: string}
     */
    public function verify_portal_password( string $account_id, string $role, string $password ): array {
        if ( ! ctype_digit( $account_id ) || '0' === $account_id || '' === $password || ! in_array( $role, [ 'ADMIN_HR', 'KEPALA_DEPARTEMEN' ], true ) ) {
            return [ 'success' => false, 'error' => 'invalid_password_data' ];
        }
        try {
            $statement = $this->get_connection()->prepare(
                'SELECT kata_sandi FROM hrs_role_akses WHERE role_akses_id = :account_id AND peran = :role AND status_akun = :status'
            );
            $statement->execute( [ 'account_id' => $account_id, 'role' => $role, 'status' => 'ACTIVE' ] );
            return password_verify( $password, (string) $statement->fetchColumn() )
                ? [ 'success' => true ]
                : [ 'success' => false, 'error' => 'invalid_current_password' ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'Portal password verification failed', $exception );
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
                'SELECT kata_sandi FROM hrs_role_akses WHERE role_akses_id = :account_id AND peran = :role'
            );
            $lookup->execute( [ 'account_id' => $account_id, 'role' => 'ADMIN_HR' ] );
            $current_hash = $lookup->fetchColumn();
            if ( false === $current_hash || ! password_verify( $current_password, (string) $current_hash ) ) {
                return [ 'success' => false, 'error' => 'invalid_current_password' ];
            }

            $new_hash = password_hash( $new_password, PASSWORD_DEFAULT );
            if ( false === $new_hash ) {
                throw new RuntimeException( 'HR password could not be hashed.' );
            }

            $update = $connection->prepare(
                'UPDATE hrs_role_akses SET kata_sandi = :password_hash WHERE role_akses_id = :account_id AND peran = :role'
            );
            $update->execute( [ 'password_hash' => $new_hash, 'account_id' => $account_id, 'role' => 'ADMIN_HR' ] );
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
     * Verify and replace a department account password.
     *
     * @return array{success: bool, error?: string}
     */
    public function change_department_password( string $account_id, string $current_password, string $new_password ): array {
        if ( ! ctype_digit( $account_id ) || '0' === $account_id || '' === $current_password || strlen( $new_password ) < 8 ) {
            return [ 'success' => false, 'error' => 'invalid_password_data' ];
        }

        try {
            $connection = $this->get_connection();
            $lookup = $connection->prepare(
                'SELECT kata_sandi FROM hrs_role_akses WHERE role_akses_id = :account_id AND peran = :role'
            );
            $lookup->execute( [ 'account_id' => $account_id, 'role' => 'KEPALA_DEPARTEMEN' ] );
            $current_hash = $lookup->fetchColumn();
            if ( false === $current_hash || ! password_verify( $current_password, (string) $current_hash ) ) {
                return [ 'success' => false, 'error' => 'invalid_current_password' ];
            }

            $new_hash = password_hash( $new_password, PASSWORD_DEFAULT );
            if ( false === $new_hash ) {
                throw new RuntimeException( 'Department password could not be hashed.' );
            }

            $update = $connection->prepare(
                'UPDATE hrs_role_akses SET kata_sandi = :password_hash WHERE role_akses_id = :account_id AND peran = :role'
            );
            $update->execute(
                [
                    'password_hash' => $new_hash,
                    'account_id'    => $account_id,
                    'role'          => 'KEPALA_DEPARTEMEN',
                ]
            );
            if ( 1 !== $update->rowCount() ) {
                return [ 'success' => false, 'error' => 'account_not_found' ];
            }

            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'Department Oracle password update failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Read the current Oracle status for a portal account.
     *
     * @return array{success: bool, status?: string, error?: string}
     */
    public function get_portal_account_status( string $account_type, string $account_id ): array {
        $role = 'hr' === $account_type ? 'ADMIN_HR' : ( 'user_department' === $account_type ? 'KEPALA_DEPARTEMEN' : '' );
        if ( '' === $role || ! ctype_digit( $account_id ) || '0' === $account_id ) {
            return [ 'success' => false, 'error' => 'invalid_account' ];
        }

        try {
            $statement = $this->get_connection()->prepare(
                'SELECT status_akun FROM hrs_role_akses WHERE role_akses_id = :account_id AND peran = :role'
            );
            $statement->execute( [ 'account_id' => $account_id, 'role' => $role ] );
            $status = $statement->fetchColumn();
            if ( false === $status ) {
                return [ 'success' => false, 'error' => 'account_not_found' ];
            }

            return [ 'success' => true, 'status' => strtoupper( (string) $status ) ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'Portal Oracle account status lookup failed', $exception );
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
                'UPDATE hrs_role_akses SET kata_sandi = :password_hash WHERE role_akses_id = :account_id AND peran = :role AND status_akun = :status'
            );
            $statement->execute(
                [
                    'password_hash' => $password_hash,
                    'account_id'    => $account_id,
                    'role'          => 'ADMIN_HR',
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
     * Load active sections, questions, and answer options from Oracle.
     *
     * @return array{success: bool, sections?: array<int, array<string, mixed>>, error?: string}
     */
    public function get_application_form(): array {
        $this->last_error = null;
        $failure_stage = 'loading sections';
        try {
            $connection = $this->get_connection();
            $section_statement = $connection->query(
                'SELECT BAGIAN_PENDAFTARAN_ID AS "id", NAMA_SECTION AS "title", DESKRIPSI AS "description" FROM HRS_BAGIAN_PENDAFTARAN WHERE IS_ACTIVE = 1 ORDER BY DISPLAY_ORDER, BAGIAN_PENDAFTARAN_ID'
            );
            $section_rows = $section_statement->fetchAll( PDO::FETCH_ASSOC );
            $sections = [];
            $section_index = [];
            foreach ( $section_rows as $row ) {
                $id = (string) $row['id'];
                $section_index[ $id ] = count( $sections );
                $sections[] = [
                    'id'          => $id,
                    'title'       => (string) $row['title'],
                    'description' => $this->oracle_lob_to_string( $row['description'] ?? '' ),
                    'questions'   => [],
                ];
            }

            $failure_stage = 'loading questions';
            $field_statement = $connection->query(
                'SELECT FIELD_ID AS "id", BAGIAN_PENDAFTARAN_ID AS "section_id", FIELD_KEY AS "key", FIELD_TITLE AS "title", FIELD_TYPE AS "type", IS_REQUIRED AS "required" FROM RECRUIT_FORM_FIELDS WHERE IS_ACTIVE = 1 ORDER BY DISPLAY_ORDER, FIELD_ID'
            );
            $field_rows = $field_statement->fetchAll( PDO::FETCH_ASSOC );
            $active_field_count = count( $field_rows );
            if ( 0 === $active_field_count ) {
                $failure_stage = 'checking question configuration';
                $count_statement = $connection->query( 'SELECT COUNT(*) FROM RECRUIT_FORM_FIELDS' );
                if ( 0 === (int) $count_statement->fetchColumn() ) {
                    return [
                        'success'  => true,
                        'sections' => ( new Recruitment_Application() )->get_fixed_form_sections(),
                    ];
                }
            }

            $options_by_field = [];
            $failure_stage = 'loading answer options';
            $option_statement = $connection->query(
                'SELECT o.FIELD_ID AS "field_id", o.OPTION_VALUE AS "value", o.OPTION_LABEL AS "label" FROM RECRUIT_FORM_OPTIONS o JOIN RECRUIT_FORM_FIELDS f ON f.FIELD_ID = o.FIELD_ID WHERE f.IS_ACTIVE = 1 ORDER BY o.DISPLAY_ORDER, o.OPTION_ID'
            );
            foreach ( $option_statement->fetchAll( PDO::FETCH_ASSOC ) as $option ) {
                $options_by_field[ (string) $option['field_id'] ][] = [
                    'value' => (string) $option['value'],
                    'label' => (string) $option['label'],
                ];
            }

            foreach ( $field_rows as $row ) {
                $section_id = (string) $row['section_id'];
                if ( ! isset( $section_index[ $section_id ] ) ) {
                    continue;
                }
                $field_id = (string) $row['id'];
                $sections[ $section_index[ $section_id ] ]['questions'][] = [
                    'id'       => $field_id,
                    'key'      => (string) $row['key'],
                    'title'    => (string) $row['title'],
                    'type'     => (string) $row['type'],
                    'required' => 1 === (int) $row['required'],
                    'options'  => $options_by_field[ $field_id ] ?? [],
                ];
            }

            return [ 'success' => true, 'sections' => $sections ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_oracle_failure( 'Oracle application form load failed while ' . $failure_stage, $exception );
            return [
                'success' => false,
                'error'    => 'Konfigurasi form dinamis belum dapat dimuat dari Oracle. Periksa koneksi dan struktur form dinamis pada database target.',
            ];
        }
    }

    /**
     * Save the active form while retaining removed fields for historical answers.
     *
     * @param array<int, array<string, mixed>> $sections
     * @return array{success: bool, error?: string}
     */
    public function save_application_form( array $sections ): array {
        if ( ! $sections ) {
            return [ 'success' => false, 'error' => 'Form harus memiliki sedikitnya satu section.' ];
        }

        $fixed_types = ( new Recruitment_Application() )->get_fixed_field_types();
        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();

            $existing_sections = [];
            $section_statement = $connection->query( 'SELECT BAGIAN_PENDAFTARAN_ID AS "id" FROM HRS_BAGIAN_PENDAFTARAN' );
            foreach ( $section_statement->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
                $existing_sections[ (string) $row['id'] ] = true;
            }

            $existing_fields = [];
            $existing_fields_by_key = [];
            $field_statement = $connection->query( 'SELECT FIELD_ID AS "id", FIELD_KEY AS "key" FROM RECRUIT_FORM_FIELDS' );
            foreach ( $field_statement->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
                $existing_fields[ (string) $row['id'] ] = (string) $row['key'];
                $existing_fields_by_key[ (string) $row['key'] ] = (string) $row['id'];
            }

            $connection->exec( 'UPDATE HRS_BAGIAN_PENDAFTARAN SET IS_ACTIVE = 0 WHERE IS_ACTIVE = 1' );
            $connection->exec( 'UPDATE RECRUIT_FORM_FIELDS SET IS_ACTIVE = 0 WHERE IS_ACTIVE = 1' );

            $active_keys = [];
            foreach ( array_values( $sections ) as $section_order => $section ) {
                if ( ! is_array( $section ) || '' === trim( (string) ( $section['title'] ?? '' ) ) ) {
                    throw new RuntimeException( 'Data section form tidak valid.' );
                }

                $section_id = (string) ( $section['id'] ?? '' );
                if ( '' !== $section_id && ( ! ctype_digit( $section_id ) || ! isset( $existing_sections[ $section_id ] ) ) ) {
                    throw new RuntimeException( 'Referensi section form tidak ditemukan.' );
                }
                if ( '' === $section_id ) {
                    $section_id = $this->insert_oracle_record(
                        $connection,
                        'INSERT INTO HRS_BAGIAN_PENDAFTARAN (NAMA_SECTION, DESKRIPSI, DISPLAY_ORDER, IS_ACTIVE) VALUES (:title, :description, :display_order, 1)',
                        'BAGIAN_PENDAFTARAN_ID',
                        [
                            'title'         => (string) $section['title'],
                            'description'   => (string) ( $section['description'] ?? '' ),
                            'display_order' => $section_order + 1,
                        ]
                    );
                    $existing_sections[ $section_id ] = true;
                } else {
                    $update_section = $connection->prepare(
                        'UPDATE HRS_BAGIAN_PENDAFTARAN SET NAMA_SECTION = :title, DESKRIPSI = :description, DISPLAY_ORDER = :display_order, IS_ACTIVE = 1 WHERE BAGIAN_PENDAFTARAN_ID = :section_id'
                    );
                    $update_section->execute(
                        [
                            'title'         => (string) $section['title'],
                            'description'   => (string) ( $section['description'] ?? '' ),
                            'display_order' => $section_order + 1,
                            'section_id'    => $section_id,
                        ]
                    );
                }

                foreach ( array_values( (array) ( $section['questions'] ?? [] ) ) as $field_order => $question ) {
                    if ( ! is_array( $question ) ) {
                        throw new RuntimeException( 'Data pertanyaan form tidak valid.' );
                    }
                    $field_id = (string) ( $question['id'] ?? '' );
                    $field_key = sanitize_key( (string) ( $question['key'] ?? '' ) );
                    $field_type = sanitize_key( (string) ( $question['type'] ?? '' ) );
                    $field_title = sanitize_text_field( (string) ( $question['title'] ?? '' ) );
                    if ( '' !== $field_id ) {
                        if ( ! ctype_digit( $field_id ) || ! isset( $existing_fields[ $field_id ] ) ) {
                            throw new RuntimeException( 'Referensi pertanyaan form tidak ditemukan.' );
                        }
                        $field_key = $existing_fields[ $field_id ];
                    }
                    if (
                        '' === $field_key
                        || '' === $field_title
                        || ! preg_match( '/^[a-z0-9_]{1,100}$/', $field_key )
                        || ! in_array( $field_type, [ 'short', 'paragraph', 'number', 'date', 'email', 'phone', 'file', 'dropdown', 'single_choice' ], true )
                        || isset( $active_keys[ $field_key ] )
                    ) {
                        throw new RuntimeException( 'Pertanyaan form berisi key, jenis jawaban, atau judul yang tidak valid.' );
                    }
                    if ( isset( $fixed_types[ $field_key ] ) && $fixed_types[ $field_key ] !== $field_type ) {
                        throw new RuntimeException( 'Jenis jawaban field inti tidak dapat diubah.' );
                    }
                    $active_keys[ $field_key ] = true;

                    $parameters = [
                        'section_id'   => $section_id,
                        'field_title'  => $field_title,
                        'field_type'   => $field_type,
                        'is_required'  => empty( $question['required'] ) ? 0 : 1,
                        'display_order' => $field_order + 1,
                    ];
                    if ( '' === $field_id ) {
                        if ( isset( $existing_fields_by_key[ $field_key ] ) ) {
                            throw new RuntimeException( 'Key pertanyaan sudah pernah digunakan.' );
                        }
                        $field_id = $this->insert_oracle_record(
                            $connection,
                            'INSERT INTO RECRUIT_FORM_FIELDS (BAGIAN_PENDAFTARAN_ID, FIELD_KEY, FIELD_TITLE, FIELD_TYPE, IS_REQUIRED, DISPLAY_ORDER, IS_ACTIVE) VALUES (:section_id, :field_key, :field_title, :field_type, :is_required, :display_order, 1)',
                            'FIELD_ID',
                            $parameters + [ 'field_key' => $field_key ]
                        );
                        $existing_fields[ $field_id ] = $field_key;
                        $existing_fields_by_key[ $field_key ] = $field_id;
                    } else {
                        $update_field = $connection->prepare(
                            'UPDATE RECRUIT_FORM_FIELDS SET BAGIAN_PENDAFTARAN_ID = :section_id, FIELD_TITLE = :field_title, FIELD_TYPE = :field_type, IS_REQUIRED = :is_required, DISPLAY_ORDER = :display_order, IS_ACTIVE = 1, UPDATED_AT = CURRENT_TIMESTAMP WHERE FIELD_ID = :field_id AND FIELD_KEY = :field_key'
                        );
                        $update_field->execute(
                            $parameters + [
                                'field_id' => $field_id,
                                'field_key' => $field_key,
                            ]
                        );
                    }

                    $delete_options = $connection->prepare( 'DELETE FROM RECRUIT_FORM_OPTIONS WHERE FIELD_ID = :field_id' );
                    $delete_options->execute( [ 'field_id' => $field_id ] );
                    $insert_option = $connection->prepare(
                        'INSERT INTO RECRUIT_FORM_OPTIONS (FIELD_ID, OPTION_VALUE, OPTION_LABEL, DISPLAY_ORDER) VALUES (:field_id, :option_value, :option_label, :display_order)'
                    );
                    foreach ( array_values( (array) ( $question['options'] ?? [] ) ) as $option_order => $option ) {
                        if ( ! is_array( $option ) ) {
                            continue;
                        }
                        $option_value = sanitize_text_field( (string) ( $option['value'] ?? '' ) );
                        if ( '' === $option_value ) {
                            continue;
                        }
                        $option_label = sanitize_text_field( (string) ( $option['label'] ?? $option_value ) );
                        $insert_option->execute(
                            [
                                'field_id'     => $field_id,
                                'option_value' => $option_value,
                                'option_label' => $option_label,
                                'display_order' => $option_order + 1,
                            ]
                        );
                    }
                }
            }

            foreach ( $fixed_types as $field_key => $field_type ) {
                if ( ! isset( $active_keys[ $field_key ] ) ) {
                    throw new RuntimeException( 'Field inti "' . $field_key . '" tidak dapat dihapus dari form.' );
                }
                if ( 'foto_terkini' === $field_key ) {
                    $required_statement = $connection->prepare(
                        'SELECT IS_REQUIRED FROM RECRUIT_FORM_FIELDS WHERE FIELD_KEY = :field_key AND IS_ACTIVE = 1'
                    );
                    $required_statement->execute( [ 'field_key' => $field_key ] );
                    if ( 1 !== (int) $required_statement->fetchColumn() ) {
                        throw new RuntimeException( 'Foto Terkini harus tetap menjadi field wajib.' );
                    }
                }
            }

            $connection->commit();
            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'Oracle application form save failed', $exception );
            return [ 'success' => false, 'error' => $exception->getMessage() ];
        }
    }

    /**
     * Save mapped candidate data and form answers in one Oracle transaction.
     *
     * @param array<string, mixed> $submission
     * @return array{success: bool, error?: string}
     */
    public function save_application_submission( array $submission ): array {
        $answers = [];
        $has_dynamic_field_ids = false;
        $has_missing_field_ids = false;
        foreach ( (array) ( $submission['answers'] ?? [] ) as $answer ) {
            if ( is_array( $answer ) && isset( $answer['field_key'] ) ) {
                $answers[ (string) $answer['field_key'] ] = $answer;
                if ( ! empty( $answer['field_id'] ) && ctype_digit( (string) $answer['field_id'] ) ) {
                    $has_dynamic_field_ids = true;
                } else {
                    $has_missing_field_ids = true;
                }
            }
        }
        if ( $has_dynamic_field_ids && $has_missing_field_ids ) {
            return [ 'success' => false, 'error' => 'Konfigurasi field form berubah. Muat ulang halaman dan coba kirim kembali.' ];
        }

        $photo = $answers['foto_terkini']['file'] ?? null;
        if ( ! is_array( $photo ) || empty( $photo['tmp_name'] ) ) {
            return [ 'success' => false, 'error' => 'Foto pelamar wajib diunggah.' ];
        }

        $connection = null;
        $photo_stream = null;
        $answer_stream = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $photo_stream = fopen( (string) $photo['tmp_name'], 'rb' );
            if ( false === $photo_stream ) {
                throw new RuntimeException( 'The applicant photo could not be opened.' );
            }

            $candidate_id = $this->insert_oracle_record(
                $connection,
                'INSERT INTO HRS_PROFIL_KANDIDAT (FOTO_KANDIDAT, NAMA_KANDIDAT, TEMPAT_LAHIR, TANGGAL_LAHIR, JENIS_KELAMIN, EMAIL, TANGGAL_LAMARAN, PENDIDIKAN, STATUS_PERNIKAHAN, NOMOR_KTP, NAMA_LENGKAP, ALAMAT_DOMISILI, NOMOR_TELEPON, NOMOR_HP, DIBUAT_PADA, DIPERBARUI_PADA) VALUES (:photo, :candidate_name, :birth_place, TO_DATE(:birth_date, \'YYYY-MM-DD\'), :gender, :email, CURRENT_DATE, :education, :marital_status, :national_id, :full_name, :address, :phone, :mobile, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                'KANDIDAT_ID',
                [
                    'photo' => $photo_stream,
                    'full_name' => $submission['applicant_name'],
                    'candidate_name' => $submission['applicant_name'],
                    'birth_place' => $answers['tempat_lahir']['value'] ?? '',
                    'birth_date' => $answers['tanggal_lahir']['value'] ?? '',
                    'gender' => $answers['jenis_kelamin']['value'] ?? '',
                    'email' => $submission['applicant_email'],
                    'education' => $answers['pendidikan_terakhir']['value'] ?? '',
                    'marital_status' => $answers['status_pernikahan']['value'] ?? '',
                    'national_id' => $answers['nomor_ktp']['value'] ?? '',
                    'address' => $answers['alamat_saat_ini']['value'] ?? '',
                    'phone' => $answers['nomor_telepon']['value'] ?? '',
                    'mobile' => $answers['nomor_telepon']['value'] ?? '',
                ]
            );
            fclose( $photo_stream );
            $photo_stream = null;

            $this->insert_oracle_record(
                $connection,
                'INSERT INTO HRS_PENDIDIKAN (JENJANG_PENDIDIKAN, NAMA_INSTITUSI, PROGRAM_STUDI, IPK, TAHUN_LULUS, KANDIDAT_ID, CREATED_AT, UPDATED_AT) VALUES (:level, :institution, :program, :gpa, :graduation_year, :candidate_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                'PENDIDIKAN_ID',
                [
                    'level' => $answers['pendidikan_terakhir']['value'] ?? '',
                    'institution' => $answers['nama_institusi']['value'] ?? '',
                    'program' => $answers['jurusan_program_studi']['value'] ?? '',
                    'gpa' => $answers['ipk']['value'] ?? null,
                    'graduation_year' => $answers['tahun_lulus']['value'] ?? '',
                    'candidate_id' => $candidate_id,
                ]
            );

            if (
                '' !== trim( (string) ( $answers['nama_perusahaan']['value'] ?? '' ) )
                || '' !== trim( (string) ( $answers['jabatan_posisi']['value'] ?? '' ) )
                || '' !== trim( (string) ( $answers['tanggal_mulai_kerja']['value'] ?? '' ) )
                || '' !== trim( (string) ( $answers['tanggal_selesai_kerja']['value'] ?? '' ) )
                || '' !== trim( (string) ( $answers['alasan_berhenti']['value'] ?? '' ) )
            ) {
                $experience_id = $this->insert_oracle_record(
                    $connection,
                    'INSERT INTO HRS_PENGALAMAN_KERJA (NAMA_PERUSAHAAN, POSISI, TANGGAL_MULAI, TANGGAL_SELESAI, KANDIDAT_ID, DESKRIPSI_PEKERJAAN, CREATED_AT, UPDATED_AT) VALUES (:company, :position, TO_DATE(NULLIF(:start_date, \'\'), \'YYYY-MM-DD\'), TO_DATE(NULLIF(:end_date, \'\'), \'YYYY-MM-DD\'), :candidate_id, :description, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                    'PENGALAMAN_KERJA_ID',
                    [
                        'company' => $answers['nama_perusahaan']['value'] ?? '',
                        'position' => $answers['jabatan_posisi']['value'] ?? '',
                        'start_date' => $answers['tanggal_mulai_kerja']['value'] ?? '',
                        'end_date' => $answers['tanggal_selesai_kerja']['value'] ?? '',
                        'candidate_id' => $candidate_id,
                        'description' => $answers['alasan_berhenti']['value'] ?? '',
                    ]
                );
                $update_experience = $connection->prepare(
                    'UPDATE HRS_PROFIL_KANDIDAT SET PENGALAMAN_KERJA_ID = :experience_id WHERE KANDIDAT_ID = :candidate_id'
                );
                $update_experience->execute( [ 'experience_id' => $experience_id, 'candidate_id' => $candidate_id ] );
            }

            $this->insert_oracle_record(
                $connection,
                'INSERT INTO HRS_KELUARGA (STATUS_PERNIKAHAN, SUSUNAN_KELUARGA_INTI, KANDIDAT_ID) VALUES (:marital_status, :family, :candidate_id)',
                'KELUARGA_ID',
                [
                    'marital_status' => $answers['status_pernikahan']['value'] ?? '',
                    'family' => $answers['susunan_keluarga_inti']['value'] ?? '',
                    'candidate_id' => $candidate_id,
                ]
            );
            $this->insert_oracle_record(
                $connection,
                'INSERT INTO HRS_MINAT_DAN_KONSEP_PRIBADI (HARAPAN_GAJI, MOTIVASI, KANDIDAT_ID) VALUES (:salary, :motivation, :candidate_id)',
                'MINAT_KOSEP_ID',
                [
                    'salary' => $answers['gaji_yang_diharapkan']['value'] ?? '',
                    'motivation' => $answers['motivasi_bergabung']['value'] ?? '',
                    'candidate_id' => $candidate_id,
                ]
            );
            foreach ( [ 'Keakuratan Data', 'Pemrosesan Data', 'Penempatan Kerja' ] as $consent ) {
                $statement = $connection->prepare( 'INSERT INTO HRS_PDP (PDP, KANDIDAT_ID) VALUES (:consent, :candidate_id)' );
                $statement->execute( [ 'consent' => $consent, 'candidate_id' => $candidate_id ] );
            }

            $profile_id = $this->insert_oracle_record(
                $connection,
                'INSERT INTO HRS_PROFILE_KANDIDAT_LOGIN (KANDIDAT_ID, EMAIL, JENIS_KELAMIN, TEMPAT_LAHIR, TANGGAL_LAHIR, CREATED_AT, UPDATED_AT) VALUES (:candidate_id, :email, :gender, :birth_place, TO_DATE(:birth_date, \'YYYY-MM-DD\'), CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                'PROFILE_KANDIDAT_LOGIN',
                [
                    'candidate_id' => $candidate_id,
                    'email' => $submission['applicant_email'],
                    'gender' => $answers['jenis_kelamin']['value'] ?? '',
                    'birth_place' => $answers['tempat_lahir']['value'] ?? '',
                    'birth_date' => $answers['tanggal_lahir']['value'] ?? '',
                ]
            );
            $application_id = $this->insert_oracle_record(
                $connection,
                'INSERT INTO HRS_LAMARAN (PROFILE_KANDIDAT_ID, DETAIL_LOWONGAN_ID, TANGGAL_MELAMAR, STATUS_TERKINI, STATUS_AKHIR, CREATED_AT, UPDATED_AT) VALUES (:profile_id, :vacancy_id, CURRENT_TIMESTAMP, :current_status, :final_status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                'LAMARAN_ID',
                [
                    'profile_id' => $profile_id,
                    'vacancy_id' => $submission['vacancy_source_id'],
                    'current_status' => 'SUBMITTED',
                    'final_status' => 'SUBMITTED',
                ]
            );
            $history = $connection->prepare(
                'INSERT INTO HRS_RIWAYAT_STATUS_PELAMAR (LAMARAN_ID, TAHAP_SELEKSI, STATUS_SELEKSI, TANGGAL_STATUS, KETERANGAN_PELAMAR) VALUES (:application_id, :stage, :status, CURRENT_DATE, :note)'
            );
            $history->execute(
                [
                    'application_id' => $application_id,
                    'stage' => 'Administrasi',
                    'status' => 'SUBMITTED',
                    'note' => 'Lamaran diterima',
                ]
            );

            if ( $has_dynamic_field_ids ) {
                $submission_id = $this->insert_oracle_record(
                    $connection,
                    'INSERT INTO RECRUIT_FORM_SUBMISSIONS (SUBMISSION_TOKEN, APPLICANT_EMAIL, APPLICANT_NAME, SOURCE_VACANCY_ID, SOURCE_VACANCY_TITLE, PDP_ACCURACY, PDP_DATA, PDP_PLACEMENT) VALUES (:token, :email, :name, :vacancy_id, :vacancy_title, 1, 1, 1)',
                    'SUBMISSION_ID',
                    [
                        'token'         => bin2hex( random_bytes( 32 ) ),
                        'email'         => $submission['applicant_email'],
                        'name'          => $submission['applicant_name'],
                        'vacancy_id'    => $submission['vacancy_source_id'],
                        'vacancy_title' => $submission['vacancy_title'],
                    ]
                );
                $answer_insert = $connection->prepare(
                    'INSERT INTO RECRUIT_FORM_ANSWERS (SUBMISSION_ID, FIELD_ID, FIELD_KEY, FIELD_TITLE, FIELD_TYPE, ANSWER_VALUE, FILE_NAME, FILE_MIME_TYPE, FILE_SIZE, FILE_CONTENT) VALUES (:submission_id, :field_id, :field_key, :field_title, :field_type, :answer_value, :file_name, :file_mime_type, :file_size, :file_content)'
                );
                $active_field_check = $connection->prepare(
                    'SELECT COUNT(*) FROM RECRUIT_FORM_FIELDS WHERE FIELD_ID = :field_id AND FIELD_KEY = :field_key AND IS_ACTIVE = 1'
                );
                foreach ( $answers as $answer ) {
                    if ( 'foto_terkini' === (string) $answer['field_key'] ) {
                        continue;
                    }
                    $field_id = (string) $answer['field_id'];
                    $active_field_check->execute( [ 'field_id' => $field_id, 'field_key' => (string) $answer['field_key'] ] );
                    if ( 1 !== (int) $active_field_check->fetchColumn() ) {
                        throw new RuntimeException( 'Konfigurasi pertanyaan berubah saat pengiriman. Muat ulang halaman dan coba kembali.' );
                    }

                    $file = $answer['file'] ?? null;
                    if ( is_array( $file ) ) {
                        $answer_stream = fopen( (string) $file['tmp_name'], 'rb' );
                        if ( false === $answer_stream ) {
                            throw new RuntimeException( 'File jawaban tidak dapat dibuka untuk disimpan.' );
                        }
                    }
                    $answer_insert->bindValue( ':submission_id', $submission_id, PDO::PARAM_STR );
                    $answer_insert->bindValue( ':field_id', $field_id, PDO::PARAM_STR );
                    $answer_insert->bindValue( ':field_key', (string) $answer['field_key'], PDO::PARAM_STR );
                    $answer_insert->bindValue( ':field_title', (string) $answer['field_title'], PDO::PARAM_STR );
                    $answer_insert->bindValue( ':field_type', (string) $answer['field_type'], PDO::PARAM_STR );
                    $answer_insert->bindValue( ':answer_value', is_array( $file ) ? null : (string) ( $answer['value'] ?? '' ), is_array( $file ) ? PDO::PARAM_NULL : PDO::PARAM_STR );
                    $answer_insert->bindValue( ':file_name', is_array( $file ) ? (string) $file['name'] : null, is_array( $file ) ? PDO::PARAM_STR : PDO::PARAM_NULL );
                    $answer_insert->bindValue( ':file_mime_type', is_array( $file ) ? (string) $file['mime_type'] : null, is_array( $file ) ? PDO::PARAM_STR : PDO::PARAM_NULL );
                    $answer_insert->bindValue( ':file_size', is_array( $file ) ? (string) $file['size'] : null, is_array( $file ) ? PDO::PARAM_STR : PDO::PARAM_NULL );
                    $answer_insert->bindValue( ':file_content', $answer_stream, is_array( $file ) ? PDO::PARAM_LOB : PDO::PARAM_NULL );
                    $answer_insert->execute();
                    if ( is_resource( $answer_stream ) ) {
                        fclose( $answer_stream );
                        $answer_stream = null;
                    }
                }
            }

            $connection->commit();
            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( is_resource( $photo_stream ) ) {
                fclose( $photo_stream );
            }
            if ( is_resource( $answer_stream ) ) {
                fclose( $answer_stream );
            }
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
    public function get_vacancies( bool $published_only = true ): array {
        $status_filter = $published_only ? ' WHERE k.STATUS_KOLOM = 1' : '';
        $rows = $this->fetch_all(
            'SELECT d.DETAIL_LOWONGAN_ID AS "id", d.JUDUL_PEKERJAAN AS "title", d.LOKASI_PENEMPATAN AS "location", k.NAMA_DEALER AS "dealer", d.KATEGORI_KJ AS "type", d.DESKRIPSI_PEKERJAAN AS "description", d.TANGGUNG_JAWAB AS "responsibilities", d.PENDIDIKAN_MINIMUM AS "education_requirement", d.PENGALAMAN_MINIMUM AS "experience_requirement", d.SERTIFIKASI_WAJIB AS "certification_requirement", d.BATAS_LAMARAN AS "deadline", d.REQUEST_AT AS "posted_at", k.STATUS_KOLOM AS "status" FROM HRS_DETAIL_INFO_LOWONGAN d JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.KOLOM_PENDAFTARAN_ID = d.KOLOM_PENDAFTARAN_ID' . $status_filter . ' ORDER BY d.DETAIL_LOWONGAN_ID DESC'
        );

        try {
            return array_map( function ( array $row ): array {
                foreach ( [ 'description', 'responsibilities', 'education_requirement', 'experience_requirement', 'certification_requirement' ] as $field ) {
                    $row[ $field ] = $this->oracle_lob_to_string( $row[ $field ] ?? '' );
                }
                return $row;
            }, $rows );
        } catch ( RuntimeException $exception ) {
            $this->last_error = $exception->getMessage();
            $this->log_error( 'Recruitment Oracle vacancy data could not be read', $exception );
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_vacancy( int $vacancy_id ): ?array {
        if ( $vacancy_id < 1 ) {
            return null;
        }

        $statement = null;
        try {
            $statement = $this->get_connection()->prepare(
                'SELECT d.DETAIL_LOWONGAN_ID AS "id", d.JUDUL_PEKERJAAN AS "title", d.LOKASI_PENEMPATAN AS "location", k.NAMA_DEALER AS "dealer", d.KATEGORI_KJ AS "type", d.DESKRIPSI_PEKERJAAN AS "description", d.TANGGUNG_JAWAB AS "responsibilities", d.PENDIDIKAN_MINIMUM AS "education_requirement", d.PENGALAMAN_MINIMUM AS "experience_requirement", d.SERTIFIKASI_WAJIB AS "certification_requirement", d.BATAS_LAMARAN AS "deadline", d.REQUEST_AT AS "posted_at", k.STATUS_KOLOM AS "status" FROM HRS_DETAIL_INFO_LOWONGAN d JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.KOLOM_PENDAFTARAN_ID = d.KOLOM_PENDAFTARAN_ID WHERE d.DETAIL_LOWONGAN_ID = :vacancy_id'
            );
            $statement->execute( [ 'vacancy_id' => $vacancy_id ] );
            $row = $statement->fetch( PDO::FETCH_ASSOC );
            if ( ! is_array( $row ) ) {
                return null;
            }
            foreach ( [ 'description', 'responsibilities', 'education_requirement', 'experience_requirement', 'certification_requirement' ] as $field ) {
                $row[ $field ] = $this->oracle_lob_to_string( $row[ $field ] ?? '' );
            }
            return $row;
        } catch ( PDOException | RuntimeException $exception ) {
            $this->last_error = $exception->getMessage();
            $this->log_error( 'Recruitment Oracle vacancy lookup failed', $exception );
            return null;
        }
    }

    /**
     * Create a request, landing entry, and vacancy as one Oracle transaction.
     *
     * @param array<string, mixed> $vacancy
     * @return array{success: bool, vacancy_id?: string, error?: string}
     */
    public function create_vacancy( array $vacancy ): array {
        $status = (string) ( $vacancy['status'] ?? '' );
        if ( ! in_array( $status, [ 'draft', 'published' ], true ) ) {
            return [ 'success' => false, 'error' => 'invalid_vacancy_status' ];
        }

        $request_code = trim( (string) ( $vacancy['request_code'] ?? '' ) );
        if ( strlen( $request_code ) > 14 ) {
            return [ 'success' => false, 'error' => 'invalid_request_code' ];
        }

        $connection = null;
        $form_details_stream = null;
        try {
            $connection = $this->get_connection();
            if ( '' !== $request_code ) {
                $existing_statement = $connection->prepare(
                    'SELECT d.DETAIL_LOWONGAN_ID AS "id", k.STATUS_KOLOM AS "status" FROM HRS_DETAIL_INFO_LOWONGAN d JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.KOLOM_PENDAFTARAN_ID = d.KOLOM_PENDAFTARAN_ID WHERE d.NO_FPTK = :request_code'
                );
                $existing_statement->execute( [ 'request_code' => $request_code ] );
                $existing_vacancy = $existing_statement->fetch( PDO::FETCH_ASSOC );
                if ( is_array( $existing_vacancy ) ) {
                    if ( 'published' === $status && 1 !== (int) $existing_vacancy['status'] ) {
                        $publish_statement = $connection->prepare(
                            'UPDATE HRS_KOLOM_LANDING_PENDAFTARAN SET STATUS_KOLOM = 1 WHERE KOLOM_PENDAFTARAN_ID = (SELECT KOLOM_PENDAFTARAN_ID FROM HRS_DETAIL_INFO_LOWONGAN WHERE DETAIL_LOWONGAN_ID = :vacancy_id)'
                        );
                        $publish_statement->execute( [ 'vacancy_id' => (string) $existing_vacancy['id'] ] );
                        if ( 1 !== $publish_statement->rowCount() ) {
                            throw new RuntimeException( 'The existing approved request could not be published.' );
                        }
                    }
                    return [ 'success' => true, 'vacancy_id' => (string) $existing_vacancy['id'] ];
                }
            }

            $request_details = is_array( $vacancy['request_details'] ?? null ) ? $vacancy['request_details'] : [];
            $request_details_json = $request_details
                ? json_encode( $request_details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE )
                : null;
            if ( false === $request_details_json ) {
                throw new RuntimeException( 'HR vacancy request details could not be encoded.' );
            }
            if ( null !== $request_details_json ) {
                $form_details_stream = fopen( 'php://temp', 'r+' );
                if ( false === $form_details_stream || false === fwrite( $form_details_stream, $request_details_json ) ) {
                    throw new RuntimeException( 'HR vacancy request details could not be prepared for Oracle.' );
                }
                rewind( $form_details_stream );
            }

            $details = $request_details;
            $target_date = (string) ( $vacancy['target_date'] ?? '' );
            $varchar_detail = static function ( string $key, int $limit ) use ( $details ): ?string {
                $value = trim( (string) ( $details[ $key ] ?? '' ) );
                $length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
                return '' !== $value && $length <= $limit ? $value : null;
            };
            $age_limit = null;
            if ( preg_match_all( '/\d+/', (string) ( $details['age_range'] ?? '' ), $age_matches ) && ! empty( $age_matches[0] ) ) {
                $age_limit = (string) max( array_map( 'intval', $age_matches[0] ) );
            }
            $connection->beginTransaction();
            $request_id = $this->insert_oracle_record(
                $connection,
                'INSERT INTO HRS_REQUEST_LOWONGAN (NO_FPTK, REQUEST_AT, NAMA_DEPARTEMEN, POSITION_IN_NEED, LEVEL_JABATAN, KATEGORI_KJ, HIRING_USER_NAME, JUMLAH_KEBUTUHAN, STATUS_KARYAWAN, PRIORITAS, TARGET_PEMENUHAN, LOKASI_PENEMPATAN, ALASAN_KEBUTUHAN, TUJUAN_UTAMA_POSISI, TANGGUNG_JAWAB_UTAMA, OUTPUT_HARAPAN, DAMPAK_KEKOSONGAN, PENDIDIKAN_MINIMUM, PENGALAMAN_MINIMUM, SERTIFIKASI_WAJIB, HARDSKILL_WAJIB, SOFTSKILL_WAJIB, KRITERIA_TAMBAHAN, JURUSAN_PRIORITAS, BATAS_USIA, KEBUTUHAN_DINAS, PERSYARATAN_KHUSUS_LAINNYA, HR_FORM_DETAILS) VALUES (:request_code, CURRENT_TIMESTAMP, :department, :title, :job_level, :category, :hiring_user, :headcount, :employee_status, :priority, TO_DATE(:target_date, \'YYYY-MM-DD\'), :location, :reason, :role_summary, :responsibilities, :expected_output, :vacancy_impact, :education, :experience, :certification, :hard_skills, :soft_skills, :additional_criteria, :major, :age_limit, :travel_requirement, :special_requirements, :hr_form_details)',
                'REQUEST_ID',
                [
                    'request_code' => '' === $request_code ? null : $request_code,
                    'department' => $details['department'] ?? null,
                    'title' => $vacancy['title'],
                    'job_level' => $details['job_level'] ?? null,
                    'category' => $details['employment_type'] ?? $vacancy['type'],
                    'hiring_user' => $details['hiring_user'] ?? null,
                    'headcount' => (string) max( 1, absint( $details['headcount'] ?? 1 ) ),
                    'employee_status' => $details['employee_status'] ?? null,
                    'priority' => $details['priority'] ?? null,
                    'target_date' => '' === $target_date ? null : $target_date,
                    'location' => $vacancy['location'],
                    'reason' => $details['reason'] ?? $vacancy['description'],
                    'role_summary' => $varchar_detail( 'role_summary', 255 ),
                    'responsibilities' => $vacancy['responsibilities'],
                    'expected_output' => $varchar_detail( 'kpi', 250 ),
                    'vacancy_impact' => $varchar_detail( 'main_challenges', 250 ),
                    'education' => $vacancy['education_requirement'],
                    'experience' => $varchar_detail( 'minimum_experience', 250 ),
                    'certification' => $varchar_detail( 'certifications', 250 ),
                    'hard_skills' => $varchar_detail( 'required_skills', 250 ),
                    'soft_skills' => $varchar_detail( 'preferred_skills', 250 ),
                    'additional_criteria' => $varchar_detail( 'industry_experience', 250 ),
                    'major' => $varchar_detail( 'major', 255 ),
                    'age_limit' => $age_limit,
                    'travel_requirement' => $details['travel_requirement'] ?? null,
                    'special_requirements' => $varchar_detail( 'special_notes', 255 ),
                    'hr_form_details' => $form_details_stream,
                ]
            );
            $landing_id = $this->insert_oracle_record(
                $connection,
                'INSERT INTO HRS_KOLOM_LANDING_PENDAFTARAN (JUDUL_PEKERJAAN, NAMA_DEPARTEMEN, NAMA_DEALER, KATEGORI_KJ, DESKRIPSI_PEKERJAAN, PERSYARATAN_UTAMA, BATAS_LAMARAN, DIPOSTING_PADA, STATUS_KOLOM, URUTAN_TAMPIL) VALUES (:title, :department, :dealer, :category, :description, :education, TO_DATE(:deadline, \'YYYY-MM-DD\'), CURRENT_DATE, :status, (SELECT NVL(MAX(URUTAN_TAMPIL), 0) + 1 FROM HRS_KOLOM_LANDING_PENDAFTARAN))',
                'KOLOM_PENDAFTARAN_ID',
                [
                    'title' => $vacancy['title'],
                    'department' => $details['department'] ?? null,
                    'dealer' => $vacancy['dealer'],
                    'category' => $vacancy['type'],
                    'description' => $vacancy['description'],
                    'education' => $vacancy['education_requirement'],
                    'deadline' => $vacancy['deadline'],
                    'status' => 'published' === $status ? 1 : 0,
                ]
            );
            $vacancy_id = $this->insert_oracle_record(
                $connection,
                'INSERT INTO HRS_DETAIL_INFO_LOWONGAN (REQUEST_LOWONGAN_ID, KOLOM_PENDAFTARAN_ID, JUDUL_PEKERJAAN, DESKRIPSI_PEKERJAAN, TANGGUNG_JAWAB, NO_FPTK, REQUEST_AT, NAMA_DEPARTEMENT, LEVEL_JABATAN, KATEGORI_KJ, JUMLAH_KEBUTUHAN, PRIORITAS, BATAS_LAMARAN, LOKASI_PENEMPATAN, PENDIDIKAN_MINIMUM, PENGALAMAN_MINIMUM, SERTIFIKASI_WAJIB) VALUES (:request_id, :landing_id, :title, :description, :responsibilities, :request_code, CURRENT_TIMESTAMP, :department, :job_level, :category, :headcount, :priority, TO_DATE(:deadline, \'YYYY-MM-DD\'), :location, :education, :experience, :certification)',
                'DETAIL_LOWONGAN_ID',
                [
                    'request_id' => $request_id,
                    'landing_id' => $landing_id,
                    'title' => $vacancy['title'],
                    'description' => $vacancy['description'],
                    'responsibilities' => $vacancy['responsibilities'],
                    'request_code' => '' === $request_code ? null : $request_code,
                    'department' => $details['department'] ?? null,
                    'job_level' => $details['job_level'] ?? null,
                    'category' => $details['employment_type'] ?? $vacancy['type'],
                    'headcount' => (string) max( 1, absint( $details['headcount'] ?? 1 ) ),
                    'priority' => $details['priority'] ?? null,
                    'deadline' => $vacancy['deadline'],
                    'location' => $vacancy['location'],
                    'education' => $vacancy['education_requirement'],
                    'experience' => $vacancy['experience_requirement'],
                    'certification' => $vacancy['certification_requirement'],
                ]
            );
            $connection->commit();
            if ( is_resource( $form_details_stream ) ) {
                fclose( $form_details_stream );
            }
            return [ 'success' => true, 'vacancy_id' => $vacancy_id ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            if ( is_resource( $form_details_stream ) ) {
                fclose( $form_details_stream );
            }
            $this->log_oracle_failure( 'Oracle vacancy creation failed', $exception );
            return [ 'success' => false, 'error' => 'vacancy_save_failed' ];
        }
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function set_vacancy_status( int $vacancy_id, string $status ): array {
        if ( $vacancy_id < 1 || ! in_array( $status, [ 'draft', 'published' ], true ) ) {
            return [ 'success' => false, 'error' => 'invalid_vacancy_status' ];
        }

        try {
            $statement = $this->get_connection()->prepare(
                'UPDATE HRS_KOLOM_LANDING_PENDAFTARAN SET STATUS_KOLOM = :status WHERE KOLOM_PENDAFTARAN_ID = (SELECT KOLOM_PENDAFTARAN_ID FROM HRS_DETAIL_INFO_LOWONGAN WHERE DETAIL_LOWONGAN_ID = :vacancy_id)'
            );
            $statement->execute(
                [
                    'status' => 'published' === $status ? 1 : 0,
                    'vacancy_id' => $vacancy_id,
                ]
            );
            if ( 1 !== $statement->rowCount() ) {
                return [ 'success' => false, 'error' => 'vacancy_not_found' ];
            }
            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_oracle_failure( 'Oracle vacancy status update failed', $exception );
            return [ 'success' => false, 'error' => 'vacancy_update_failed' ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_applicants(): array {
        return $this->fetch_all(
            'SELECT p.KANDIDAT_ID AS "id", NVL(p.NAMA_LENGKAP, p.NAMA_KANDIDAT) AS "name", NVL(p.EMAIL, pkl.EMAIL) AS "email", NVL(p.NOMOR_TELEPON, TO_CHAR(pkl.NOMOR_TELEPON)) AS "phone", NVL(pkl.CREATED_AT, p.DIBUAT_PADA) AS "created_at" FROM HRS_PROFIL_KANDIDAT p LEFT JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON p.KANDIDAT_ID = pkl.KANDIDAT_ID ORDER BY NVL(p.DIBUAT_PADA, pkl.CREATED_AT) DESC'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_applications(): array {
        return $this->fetch_all(
            'SELECT a.LAMARAN_ID AS "id", a.DETAIL_LOWONGAN_ID AS "vacancy_id", p.KANDIDAT_ID AS "applicant_id", a.STATUS_TERKINI AS "status", NVL(a.CREATED_AT, a.TANGGAL_MELAMAR) AS "created_at", NVL(p.NAMA_LENGKAP, p.NAMA_KANDIDAT) AS "applicant_name", p.EMAIL AS "applicant_email", d.JUDUL_PEKERJAAN AS "vacancy_title" FROM HRS_LAMARAN a JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON pkl.PROFILE_KANDIDAT_LOGIN = a.PROFILE_KANDIDAT_ID JOIN HRS_PROFIL_KANDIDAT p ON p.KANDIDAT_ID = pkl.KANDIDAT_ID JOIN HRS_DETAIL_INFO_LOWONGAN d ON d.DETAIL_LOWONGAN_ID = a.DETAIL_LOWONGAN_ID ORDER BY a.LAMARAN_ID DESC'
        );
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

    /**
     * @param array<string, mixed> $parameters
     */
    private function insert_oracle_record( PDO $connection, string $sql, string $id_column, array $parameters ): string {
        if ( ! preg_match( '/^[A-Z_]+$/', $id_column ) ) {
            throw new InvalidArgumentException( 'Invalid Oracle identity column.' );
        }

        $statement = $connection->prepare( $sql . ' RETURNING ' . $id_column . ' INTO :generated_id' );
        foreach ( $parameters as $name => $value ) {
            $type = is_resource( $value ) ? PDO::PARAM_LOB : ( null === $value ? PDO::PARAM_NULL : PDO::PARAM_STR );
            $statement->bindValue( ':' . $name, $value, $type );
        }
        $generated_id = '';
        $statement->bindParam( ':generated_id', $generated_id, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 40 );
        $statement->execute();
        if ( '' === $generated_id ) {
            throw new RuntimeException( 'The Oracle-generated record ID could not be retrieved.' );
        }

        return $generated_id;
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
        $oracle_code = preg_match( '/ORA-\d{5}/', $exception->getMessage(), $matches ) ? ', ' . $matches[0] : '';
        error_log( $context . ' (Oracle error ' . (string) $exception->getCode() . $oracle_code . ').' );
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



    /** Oracle operations for interview decisions and psychometric assessments. */
private function build_iq_seed_questions(): array {
        $tests = [
            [
                'key' => 'iq_numbers',
                'title' => 'IQ - 1 Berhitung Angka',
                'instructions' => 'Pilih satu jawaban terbaik untuk setiap soal hitungan.',
                'duration' => 7,
                'questions' => [],
            ],
            [
                'key' => 'iq_assembly',
                'title' => 'IQ - 2 Gabungan Bagian',
                'instructions' => 'Pilih dua susunan yang menggunakan setiap bagian tepat satu kali.',
                'duration' => 7,
                'questions' => [],
            ],
            [
                'key' => 'iq_words',
                'title' => 'IQ - 3 Hubungan Kata',
                'instructions' => 'Pilih dua kata yang memiliki hubungan makna paling dekat atau berlawanan.',
                'duration' => 5,
                'questions' => [],
            ],
            [
                'key' => 'iq_abstraction',
                'title' => 'IQ - 4 Abstraksi Nonverbal',
                'instructions' => 'Amati pola simbol, lalu pilih dua simbol yang memiliki ciri kelompok yang sama.',
                'duration' => 12,
                'questions' => [],
            ],
        ];

        for ( $index = 0; $index < 40; $index++ ) {
            $operation = $index % 4;
            if ( 0 === $operation ) {
                $left = 18 + ( $index * 2 );
                $right = 7 + $index;
                $operator = '+';
                $answer = $left + $right;
            } elseif ( 1 === $operation ) {
                $left = 90 + ( $index * 3 );
                $right = 11 + $index;
                $operator = '-';
                $answer = $left - $right;
            } elseif ( 2 === $operation ) {
                $left = 3 + ( $index % 9 );
                $right = 4 + ( $index % 7 );
                $operator = 'x';
                $answer = $left * $right;
            } else {
                $right = 2 + ( $index % 8 );
                $answer = 3 + ( $index % 9 );
                $left = $right * $answer;
                $operator = ':';
            }
            $choices = [ (string) $answer, (string) ( $answer + 2 ), (string) max( 0, $answer - 3 ), (string) ( $answer + 7 ) ];
            $tests[0]['questions'][] = [
                'prompt' => sprintf( '%d %s %d = ...', $left, $operator, $right ),
                'type' => 'multiple_choice',
                'choices' => $choices,
                'answer_indexes' => [ 0 ],
            ];
        }

        $symbol_pool = [ '●', '▲', '■', '◆', '✚', '⬟' ];
        $part_sets = [];
        for ( $first = 0; $first < count( $symbol_pool ) - 2; $first++ ) {
            for ( $second = $first + 1; $second < count( $symbol_pool ) - 1; $second++ ) {
                for ( $third = $second + 1; $third < count( $symbol_pool ); $third++ ) {
                    $part_sets[] = [ $symbol_pool[ $first ], $symbol_pool[ $second ], $symbol_pool[ $third ] ];
                }
            }
        }
        for ( $index = 0; $index < 20; $index++ ) {
            $parts = $part_sets[ $index % count( $part_sets ) ];
            $first = $parts;
            $second = [ $parts[2], $parts[0], $parts[1] ];
            $extra_symbol = $symbol_pool[ ( $index + 3 ) % count( $symbol_pool ) ];
            if ( in_array( $extra_symbol, $parts, true ) ) {
                $extra_symbol = $symbol_pool[ ( $index + 4 ) % count( $symbol_pool ) ];
            }
            $choices = [
                implode( ' + ', $first ),
                implode( ' + ', [ $parts[0], $parts[0], $parts[2] ] ),
                implode( ' + ', $second ),
                implode( ' + ', [ $parts[0], $parts[1] ] ),
                implode( ' + ', [ $parts[1], $parts[1], $parts[2] ] ),
                implode( ' + ', [ $parts[0], $parts[2], $extra_symbol ] ),
            ];
            $tests[1]['questions'][] = [
                'prompt' => 'Terdapat tiga kepingan: ' . implode( ', ', $parts ) . '. Pilih dua susunan yang memakai setiap kepingan tepat satu kali.',
                'type' => 'multi_select',
                'choices' => $choices,
                'answer_indexes' => [ 0, 2 ],
            ];
        }

        $word_pairs = [
            [ 'cermat', 'teliti' ], [ 'gembira', 'riang' ], [ 'indah', 'elok' ], [ 'cerdas', 'pandai' ],
            [ 'sunyi', 'sepi' ], [ 'sulit', 'sukar' ], [ 'hemat', 'irit' ], [ 'kokoh', 'kuat' ],
            [ 'mulai', 'mengawali' ], [ 'akhir', 'penutup' ], [ 'luas', 'lebar' ], [ 'cepat', 'lekas' ],
            [ 'penuh', 'padat' ], [ 'tenang', 'damai' ], [ 'jujur', 'terus terang' ], [ 'rapi', 'teratur' ],
            [ 'tinggi', 'rendah' ], [ 'luas', 'sempit' ], [ 'rajin', 'malas' ], [ 'terang', 'gelap' ],
            [ 'awal', 'akhir' ], [ 'maju', 'mundur' ], [ 'keras', 'lunak' ], [ 'ramai', 'sepi' ],
            [ 'tajam', 'tumpul' ], [ 'halus', 'kasar' ], [ 'panjang', 'pendek' ], [ 'cepat', 'lambat' ],
            [ 'berani', 'takut' ], [ 'terbuka', 'tertutup' ], [ 'naik', 'turun' ], [ 'dekat', 'jauh' ],
            [ 'kuat', 'lemah' ], [ 'bersih', 'kotor' ], [ 'sederhana', 'mewah' ], [ 'menang', 'kalah' ],
            [ 'kering', 'basah' ], [ 'berat', 'ringan' ], [ 'hangat', 'dingin' ], [ 'hidup', 'mati' ],
        ];
        $word_distractors = [
            'awan', 'meja', 'pohon', 'garam', 'piring', 'kertas', 'sungai', 'sepatu', 'jendela', 'benih',
            'kamera', 'pasir', 'bantal', 'kunci', 'kapal', 'sendok', 'awan', 'pagar', 'lampu', 'taman',
            'roti', 'gelas', 'gunung', 'pintu', 'daun', 'kursi', 'batu', 'buku', 'jarum', 'ikan',
            'tas', 'jam', 'kereta', 'peta', 'bunga', 'kaca', 'topi', 'lantai', 'sapu', 'radio',
        ];
        foreach ( $word_pairs as $index => $pair ) {
            $distractors = [ $word_distractors[ $index ], $word_distractors[ ( $index + 13 ) % count( $word_distractors ) ] ];
            $choices = [ $pair[0], $distractors[0], $pair[1], $distractors[1] ];
            $tests[2]['questions'][] = [
                'prompt' => 'Pilih dua kata yang memiliki hubungan makna paling dekat atau berlawanan.',
                'type' => 'multi_select',
                'choices' => $choices,
                'answer_indexes' => [ 0, 2 ],
            ];
        }

        $shape_sets = [
            [ '△', '▽', '□', '◇', '○', '⬟' ],
            [ '□', '◇', '△', '▽', '○', '⬟' ],
            [ '○', '◌', '△', '□', '⬟', '◇' ],
            [ '⬟', '⬠', '○', '□', '△', '◇' ],
        ];
        $shape_examples = [
            [ '△ ▽ ▷ ◁', '▲ ▼ ▶ ◀', '◁ △ ▽ ▶', '▽ ◁ ▶ △', '▶ ▲ ◀ ▽' ],
            [ '□ ◇ ▭ ▱', '▢ ▪ ▫ ▰', '▭ □ ▱ ◇', '▱ ▢ ◇ ▭', '◇ ▰ □ ▫' ],
            [ '○ ◌ ◎ ◉', '◍ ◯ ◉ ◎', '◎ ○ ◍ ◌', '◉ ◌ ◯ ◍', '◯ ◎ ○ ◉' ],
            [ '⬟ ⬠ ⬟ ⬠', '⬠ ⬟ ⬠ ⬟', '⬟ ⬟ ⬠ ⬠', '⬠ ⬠ ⬟ ⬟', '⬟ ⬠ ⬠ ⬟' ],
        ];
        for ( $index = 0; $index < 20; $index++ ) {
            $category = $index % count( $shape_sets );
            $variant = intdiv( $index, count( $shape_sets ) );
            $choices = array_map(
                static function ( $shape, $choice_index ) {
                    return chr( 65 + $choice_index ) . '. ' . $shape;
                },
                $shape_sets[ $category ],
                array_keys( $shape_sets[ $category ] )
            );
            $tests[3]['questions'][] = [
                'prompt' => 'Kelompok contoh: ' . $shape_examples[ $category ][ $variant ] . '. Pilih dua simbol yang memiliki jenis bentuk yang sama dengan kelompok contoh.',
                'type' => 'multi_select',
                'choices' => $choices,
                'answer_indexes' => [ 0, 1 ],
            ];
        }

        return $tests;
    }

public function complete_iq_subtest( int $test_id, string $started_at, array $response_ids ): array {
        $response_ids = array_values( array_filter( array_map( 'absint', $response_ids ) ) );
        $connection = null;
        $owns_transaction = false;
        try {
            $connection = $this->get_connection();
            $owns_transaction = ! $connection->inTransaction();
            if ( $owns_transaction ) {
                $connection->beginTransaction();
            }
            $test_statement = $connection->prepare(
                "SELECT TES_PSIKOLOGI_ID FROM HRS_TES_PSIKOLOGI WHERE TES_PSIKOLOGI_ID = :id AND UPPER(JUDUL) LIKE 'IQ - %' AND IS_ACTIVE = 1 FOR UPDATE"
            );
            $test_statement->execute( [ 'id' => $test_id ] );
            if ( ! $test_statement->fetchColumn() ) {
                throw new RuntimeException( 'IQ subtest was not found or is inactive.' );
            }

            $score = 0;
            if ( $response_ids ) {
                $placeholders = [];
                $parameters = [];
                foreach ( $response_ids as $index => $response_id ) {
                    $placeholder = 'response_' . $index;
                    $placeholders[] = ':' . $placeholder;
                    $parameters[ $placeholder ] = $response_id;
                }
                $score_statement = $connection->prepare(
                    'SELECT NVL(SUM(NILAI_DIPEROLEH), 0) FROM HRS_RESPON_PSIKOTES WHERE RESPON_PSIKOTES_ID IN (' . implode( ', ', $placeholders ) . ')'
                );
                $score_statement->execute( $parameters );
                $score = (int) $score_statement->fetchColumn();
            }

            $insert = $connection->prepare(
                'INSERT INTO HRS_HASIL_TES_PSIKOLOGI (MULAI_PADA, DIKUMPULKAN_PADA, NILAI, TES_PSIKOLOGI_ID) VALUES (TO_TIMESTAMP(:started_at, \'YYYY-MM-DD HH24:MI:SS\'), CURRENT_TIMESTAMP, :score, :test_id) RETURNING HASIL_TES_PSIKOLOGI_ID INTO :out_id'
            );
            $result_id = $this->execute_returning_id(
                $insert,
                [
                    'started_at' => $started_at,
                    'score' => $score,
                    'test_id' => $test_id,
                ],
                'out_id'
            );
            if ( $owns_transaction ) {
                $connection->commit();
            }
            return [ 'success' => true, 'result_id' => $result_id, 'score' => $score ];
        } catch ( Throwable $exception ) {
            if ( $owns_transaction && $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment IQ subtest completion failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Hasil subtes IQ gagal disimpan ke Oracle.' ];
        }
    }

public function complete_pauli_assessment( int $test_id, string $started_at, array $response_ids ): array {
        $response_ids = array_values( array_unique( array_filter( array_map( 'absint', $response_ids ) ) ) );
        if ( 20 !== count( $response_ids ) ) {
            return [ 'success' => false, 'error' => 'Semua 20 kolom Pauli harus tersimpan sebelum tes diselesaikan.' ];
        }


        $connection = null;
        $owns_transaction = false;
        try {
            $connection = $this->get_connection();
            $owns_transaction = ! $connection->inTransaction();
            if ( $owns_transaction ) {
                $connection->beginTransaction();
            }
            $test_statement = $connection->prepare(
                "SELECT TES_PSIKOLOGI_ID FROM HRS_TES_PSIKOLOGI WHERE TES_PSIKOLOGI_ID = :test_id AND UPPER(JUDUL) LIKE 'PAULI - %' AND IS_ACTIVE = 1 FOR UPDATE"
            );
            $test_statement->execute( [ 'test_id' => $test_id ] );
            if ( ! $test_statement->fetchColumn() ) {
                throw new RuntimeException( 'Active Pauli assessment was not found.' );
            }

            $placeholders = [];
            $parameters = [ 'test_id' => $test_id ];
            foreach ( $response_ids as $index => $response_id ) {
                $placeholder = 'response_' . $index;
                $placeholders[] = ':' . $placeholder;
                $parameters[ $placeholder ] = $response_id;
            }
            $score_statement = $connection->prepare(
                "SELECT COUNT(DISTINCT R.RESPON_PSIKOTES_ID) AS RESPONSE_COUNT, NVL(SUM(R.NILAI_DIPEROLEH), 0) AS SCORE FROM HRS_RESPON_PSIKOTES R JOIN HRS_PERTANYAAN_PSIKOTES Q ON Q.PERTANYAAN_PSIKOTES_ID = R.PERTANYAAN_PSIKOTES_ID WHERE Q.TES_PSIKOLOGI_ID = :test_id AND R.RESPON_PSIKOTES_ID IN (" . implode( ', ', $placeholders ) . ')'
            );
            $score_statement->execute( $parameters );
            $score_row = $score_statement->fetch( PDO::FETCH_ASSOC );
            if ( ! $score_row || 20 !== (int) $score_row['RESPONSE_COUNT'] ) {
                throw new RuntimeException( 'Pauli responses do not belong to all assessment columns.' );
            }
            $score = (int) $score_row['SCORE'];
            $insert_result = $connection->prepare(
                'INSERT INTO HRS_HASIL_TES_PSIKOLOGI (MULAI_PADA, DIKUMPULKAN_PADA, NILAI, TES_PSIKOLOGI_ID) VALUES (TO_TIMESTAMP(:started_at, \'YYYY-MM-DD HH24:MI:SS\'), CURRENT_TIMESTAMP, :score, :test_id) RETURNING HASIL_TES_PSIKOLOGI_ID INTO :out_id'
            );
            $result_id = $this->execute_returning_id(
                $insert_result,
                [
                    'started_at' => $started_at,
                    'score' => $score,
                    'test_id' => $test_id,
                ],
                'out_id'
            );
            if ( $owns_transaction ) {
                $connection->commit();
            }
            return [ 'success' => true, 'result_id' => $result_id, 'score' => $score ];
        } catch ( Throwable $exception ) {
            if ( $owns_transaction && $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment Pauli assessment completion failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Hasil tes Pauli gagal disimpan ke Oracle.' ];
        }
    }

public function complete_personality_assessment( int $test_id, int $started_at, array $response_ids ): array {
        $response_ids = array_values( array_unique( array_filter( array_map( 'absint', $response_ids ) ) ) );
        $connection = null;
        $owns_transaction = false;
        try {
            $connection = $this->get_connection();
            $owns_transaction = ! $connection->inTransaction();
            if ( $owns_transaction ) {
                $connection->beginTransaction();
            }
            $test_statement = $connection->prepare(
                'SELECT JUMLAH_SOAL FROM HRS_TES_PSIKOLOGI WHERE TES_PSIKOLOGI_ID = :test_id AND IS_ACTIVE = 1 AND (UPPER(JUDUL) LIKE \'DISC - %\' OR UPPER(JUDUL) LIKE \'PAPI KOSTICK - %\') FOR UPDATE'
            );
            $test_statement->execute( [ 'test_id' => $test_id ] );
            $expected_count = (int) $test_statement->fetchColumn();
            if ( $expected_count < 1 || count( $response_ids ) !== $expected_count ) {
                throw new RuntimeException( 'Personality assessment responses are incomplete.' );
            }

            $placeholders = [];
            $parameters = [ 'test_id' => $test_id ];
            foreach ( $response_ids as $index => $response_id ) {
                $placeholder = 'response_' . $index;
                $placeholders[] = ':' . $placeholder;
                $parameters[ $placeholder ] = $response_id;
            }
            $response_statement = $connection->prepare(
                'SELECT Q.PERTANYAAN_PSIKOTES_ID, R.RESPON_PSIKOTES_ID FROM HRS_RESPON_PSIKOTES R JOIN HRS_PERTANYAAN_PSIKOTES Q ON Q.PERTANYAAN_PSIKOTES_ID = R.PERTANYAAN_PSIKOTES_ID WHERE Q.TES_PSIKOLOGI_ID = :test_id AND R.RESPON_PSIKOTES_ID IN (' . implode( ', ', $placeholders ) . ')'
            );
            $response_statement->execute( $parameters );
            $saved_question_ids = [];
            while ( $response = $response_statement->fetch( PDO::FETCH_ASSOC ) ) {
                $saved_question_ids[] = (int) $response['PERTANYAAN_PSIKOTES_ID'];
            }
            if ( count( array_unique( $saved_question_ids ) ) !== $expected_count ) {
                throw new RuntimeException( 'Personality assessment responses do not match its questions.' );
            }

            // Oracle requires a numeric value; this zero is a storage sentinel, not a personality score.
            $insert_result = $connection->prepare(
                'INSERT INTO HRS_HASIL_TES_PSIKOLOGI (MULAI_PADA, DIKUMPULKAN_PADA, NILAI, TES_PSIKOLOGI_ID) VALUES (TO_TIMESTAMP(:started_at, \'YYYY-MM-DD HH24:MI:SS\'), CURRENT_TIMESTAMP, 0, :test_id) RETURNING HASIL_TES_PSIKOLOGI_ID INTO :out_id'
            );
            $result_id = $this->execute_returning_id(
                $insert_result,
                [ 'started_at' => gmdate( 'Y-m-d H:i:s', $started_at ), 'test_id' => $test_id ],
                'out_id'
            );
            if ( $owns_transaction ) {
                $connection->commit();
            }
            return [ 'success' => true, 'result_id' => $result_id ];
        } catch ( Throwable $exception ) {
            if ( $owns_transaction && $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment personality assessment completion failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Hasil tes kepribadian gagal disimpan ke Oracle.' ];
        }
    }

private function execute_returning_id( PDOStatement $statement, array $parameters, string $output_parameter ): int {
        foreach ( $parameters as $name => $value ) {
            $statement->bindValue( ':' . $name, $value );
        }
        $id = 0;
        $statement->bindParam( ':' . $output_parameter, $id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 32 );
        $statement->execute();
        return (int) $id;
    }

public function get_candidate_interview_schedule( int $candidate_id ): array {
        return $this->fetch_all(
            'SELECT JADWAL_ID, TANGGAL_WAWANCARA, JAM_WAWANCARA, LOKASI_WAWANCARA, METODE_WAWANCARA, HASIL_HR FROM HRS_JADWAL_KANDIDAT WHERE KANDIDAT_ID = :candidate_id ORDER BY TANGGAL_WAWANCARA DESC, JADWAL_ID DESC',
            [ 'candidate_id' => $candidate_id ]
        );
    }

public function get_candidate_tracking_data( int $candidate_id ): array {
        $profile = $this->fetch_all(
            'SELECT KANDIDAT_ID, NAMA_LENGKAP, EMAIL, POSISI_DILAMAR, DEALER, STATUS_SELEKSI FROM HRS_PROFIL_KANDIDAT WHERE KANDIDAT_ID = :candidate_id',
            [ 'candidate_id' => $candidate_id ]
        );
        $applications = $this->fetch_all(
            'SELECT L.LAMARAN_ID, L.TANGGAL_MELAMAR, L.STATUS_TERKINI, L.STATUS_AKHIR, P.POSISI_DILAMAR FROM HRS_LAMARAN L JOIN HRS_PROFILE_KANDIDAT_LOGIN PL ON PL.PROFILE_KANDIDAT_LOGIN = L.PROFILE_KANDIDAT_ID JOIN HRS_PROFIL_KANDIDAT P ON P.KANDIDAT_ID = PL.KANDIDAT_ID WHERE PL.KANDIDAT_ID = :candidate_id ORDER BY L.LAMARAN_ID DESC',
            [ 'candidate_id' => $candidate_id ]
        );
        $status_history = $this->fetch_all(
            'SELECT R.RIWAYAT_STATUS_ID, R.LAMARAN_ID, R.TAHAP_SELEKSI, R.STATUS_SELEKSI, R.TANGGAL_STATUS, R.KETERANGAN_PELAMAR FROM HRS_RIWAYAT_STATUS_PELAMAR R JOIN HRS_LAMARAN L ON L.LAMARAN_ID = R.LAMARAN_ID JOIN HRS_PROFILE_KANDIDAT_LOGIN PL ON PL.PROFILE_KANDIDAT_LOGIN = L.PROFILE_KANDIDAT_ID WHERE PL.KANDIDAT_ID = :candidate_id ORDER BY R.TANGGAL_STATUS DESC, R.RIWAYAT_STATUS_ID DESC',
            [ 'candidate_id' => $candidate_id ]
        );
        $interviews = $this->fetch_all(
            'SELECT HASIL_USER, NILAI_TOTAL, REKOMENDASI, CATATAN_WAWANCARA, TANGGAL_WAWANCARA, STATUS_SELEKSI, NAMA_DEPARTEMEN FROM HRS_HASIL_WAWANCARA_USER WHERE KANDIDAT_ID = :candidate_id ORDER BY SUBMITTED_AT DESC, HASIL_USER DESC FETCH FIRST 1 ROWS ONLY',
            [ 'candidate_id' => $candidate_id ]
        );
        $decisions = $this->fetch_all(
            'SELECT KEPUTUSAN_AKHIR, TANGGAL_WAWANCARA, TOTAL_NILAI FROM HRS_REKAPAN_KEPUTUSAN_HR WHERE KANDIDAT_ID = :candidate_id ORDER BY REKAPAN_KEPUTUSAN_ID DESC FETCH FIRST 1 ROWS ONLY',
            [ 'candidate_id' => $candidate_id ]
        );

        return [
            'profile' => $profile[0] ?? null,
            'applications' => $applications,
            'status_history' => $status_history,
            'interview' => $interviews[0] ?? null,
            'decision' => $decisions[0] ?? null,
        ];
    }

/**
 * Read one application using its unguessable public tracking token.
 *
 * @return array<string, mixed>|null
 */
public function get_application_tracking_by_token( string $token ): ?array {
    if ( 1 !== preg_match( '/\A[a-f0-9]{64}\z/', $token ) ) {
        return null;
    }

    $applications = $this->fetch_all(
        'SELECT L.LAMARAN_ID AS "application_id", L.TANGGAL_MELAMAR AS "applied_at", L.STATUS_TERKINI AS "current_status", L.STATUS_AKHIR AS "final_status", P.KANDIDAT_ID AS "candidate_id", COALESCE(P.NAMA_LENGKAP, P.NAMA_KANDIDAT) AS "candidate_name", D.JUDUL_PEKERJAAN AS "position", D.LOKASI_PENEMPATAN AS "location", K.NAMA_DEALER AS "dealer" FROM HRS_LAMARAN L JOIN HRS_PROFILE_KANDIDAT_LOGIN PL ON PL.PROFILE_KANDIDAT_LOGIN = L.PROFILE_KANDIDAT_ID JOIN HRS_PROFIL_KANDIDAT P ON P.KANDIDAT_ID = PL.KANDIDAT_ID JOIN HRS_DETAIL_INFO_LOWONGAN D ON D.DETAIL_LOWONGAN_ID = L.DETAIL_LOWONGAN_ID LEFT JOIN HRS_KOLOM_LANDING_PENDAFTARAN K ON K.KOLOM_PENDAFTARAN_ID = D.KOLOM_PENDAFTARAN_ID WHERE L.TRACKING_TOKEN = :token',
        [ 'token' => $token ]
    );
    if ( ! $applications ) {
        return null;
    }

    $application = $applications[0];
    $application_id = (int) $application['application_id'];
    $candidate_id = (int) $application['candidate_id'];
    $application['status_history'] = $this->fetch_all(
        'SELECT TAHAP_SELEKSI AS "stage", STATUS_SELEKSI AS "status", TANGGAL_STATUS AS "status_date" FROM HRS_RIWAYAT_STATUS_PELAMAR WHERE LAMARAN_ID = :application_id ORDER BY TANGGAL_STATUS, RIWAYAT_STATUS_ID',
        [ 'application_id' => $application_id ]
    );
    $interviews = $this->fetch_all(
        'SELECT STATUS_SELEKSI AS "status", TANGGAL_WAWANCARA AS "interview_date" FROM HRS_HASIL_WAWANCARA_USER WHERE KANDIDAT_ID = :candidate_id ORDER BY SUBMITED_AT DESC, HASIL_USER DESC FETCH FIRST 1 ROWS ONLY',
        [ 'candidate_id' => $candidate_id ]
    );
    $decisions = $this->fetch_all(
        'SELECT KEPUTUSAN_AKHIR AS "decision" FROM HRS_REKAPAN_KEPUTUSAN_HR WHERE KANDIDAT_ID = :candidate_id ORDER BY REKAPAN_KEPUTUSAN_ID DESC FETCH FIRST 1 ROWS ONLY',
        [ 'candidate_id' => $candidate_id ]
    );
    $application['interview'] = $interviews[0] ?? null;
    $application['decision'] = $decisions[0]['decision'] ?? '';
    unset( $application['application_id'], $application['candidate_id'] );

    return $application;
}

public function get_final_decision_candidates(): array {
        return $this->fetch_all(
        'WITH latest_interviews AS (SELECT W.KANDIDAT_ID, W.NILAI_TOTAL, W.REKOMENDASI, W.NAMA_DEPARTEMEN, W.TANGGAL_WAWANCARA, W.STATUS_SELEKSI, ROW_NUMBER() OVER (PARTITION BY W.KANDIDAT_ID ORDER BY W.SUBMITED_AT DESC, W.HASIL_USER DESC) AS RN FROM HRS_HASIL_WAWANCARA_USER W), latest_decisions AS (SELECT D.KANDIDAT_ID, D.KEPUTUSAN_AKHIR, ROW_NUMBER() OVER (PARTITION BY D.KANDIDAT_ID ORDER BY D.REKAPAN_KEPUTUSAN_ID DESC) AS RN FROM HRS_REKAPAN_KEPUTUSAN_HR D) SELECT P.KANDIDAT_ID AS ID, COALESCE(P.NAMA_LENGKAP, P.NAMA_KANDIDAT) AS NAME, \'KANDIDAT-\' || TO_CHAR(P.KANDIDAT_ID) AS CODE, P.POSISI_DILAMAR AS POSITION, P.DEALER AS DEALER, I.NAMA_DEPARTEMEN AS DEPARTMENT, TO_CHAR(P.TANGGAL_LAMARAN, \'YYYY-MM\') AS BATCH, TO_CHAR(P.TANGGAL_LAMARAN, \'YYYY\') AS APPLICATION_YEAR, I.REKOMENDASI AS USER_RESULT, I.TANGGAL_WAWANCARA AS USER_INTERVIEW_DATE, I.STATUS_SELEKSI AS USER_INTERVIEW_STATUS, D.KEPUTUSAN_AKHIR AS DECISION, P.EMAIL AS EMAIL, I.NILAI_TOTAL AS USER_SCORE FROM HRS_PROFIL_KANDIDAT P JOIN latest_interviews I ON I.KANDIDAT_ID = P.KANDIDAT_ID AND I.RN = 1 LEFT JOIN latest_decisions D ON D.KANDIDAT_ID = P.KANDIDAT_ID AND D.RN = 1 ORDER BY I.TANGGAL_WAWANCARA DESC NULLS LAST, P.KANDIDAT_ID DESC'
        );
    }

public function get_iq_assessment( bool $include_inactive = false ): array {
        try {
            $connection = $this->get_connection();
            $test_filter = $include_inactive ? '' : ' AND IS_ACTIVE = 1';
            $test_statement = $connection->query(
                "SELECT TES_PSIKOLOGI_ID AS ID, JUDUL AS TITLE, PETUNJUK_PENGERJAAN AS INSTRUCTIONS, DURASI_PENGERJAAN AS DURATION, JUMLAH_SOAL AS QUESTION_COUNT, URUTAN_TAMPILAN AS DISPLAY_ORDER FROM HRS_TES_PSIKOLOGI WHERE UPPER(JUDUL) LIKE 'IQ - %'" . $test_filter . ' ORDER BY URUTAN_TAMPILAN, TES_PSIKOLOGI_ID'
            );
            $tests = [];
            while ( $test = $test_statement->fetch( PDO::FETCH_ASSOC ) ) {
                $test['INSTRUCTIONS'] = $this->read_oracle_text( $test['INSTRUCTIONS'] );
                $tests[] = $test;
            }
            if ( ! $tests ) {
                return [];
            }

            $test_ids = array_map( static function ( $test ) {
                return (int) $test['ID'];
            }, $tests );
            $test_placeholders = [];
            $parameters = [];
            foreach ( $test_ids as $index => $test_id ) {
                $placeholder = 'test_' . $index;
                $test_placeholders[] = ':' . $placeholder;
                $parameters[ $placeholder ] = $test_id;
            }

            $question_statement = $connection->prepare(
                'SELECT PERTANYAAN_PSIKOTES_ID AS ID, TES_PSIKOLOGI_ID AS TEST_ID, TEKS_PERTANYAAN AS QUESTION_DATA, JENIS_PERTANYAAN AS TYPE, DISPLAY_ORDER FROM HRS_PERTANYAAN_PSIKOTES WHERE TES_PSIKOLOGI_ID IN (' . implode( ', ', $test_placeholders ) . ') ORDER BY TES_PSIKOLOGI_ID, DISPLAY_ORDER, PERTANYAAN_PSIKOTES_ID'
            );
            $question_statement->execute( $parameters );
            $questions = [];
            while ( $question = $question_statement->fetch( PDO::FETCH_ASSOC ) ) {
                $question['QUESTION_DATA'] = $this->read_oracle_text( $question['QUESTION_DATA'] );
                $questions[] = $question;
            }

            $question_ids = array_map( static function ( $question ) {
                return (int) $question['ID'];
            }, $questions );
            $answers_by_question = [];
            if ( $question_ids ) {
                $question_placeholders = [];
                $answer_parameters = [];
                foreach ( $question_ids as $index => $question_id ) {
                    $placeholder = 'question_' . $index;
                    $question_placeholders[] = ':' . $placeholder;
                    $answer_parameters[ $placeholder ] = $question_id;
                }
                $answer_statement = $connection->prepare(
                    'SELECT JAWABAN_PSIKOTES_ID AS ID, PERTANYAAN_PSIKOTES_ID AS QUESTION_ID, JAWABAN_TEKS AS ANSWER_TEXT, APAKAH_BENAR AS IS_CORRECT, URUTAN_TAMPILAN AS DISPLAY_ORDER FROM HRS_JAWABAN_PSIKOTES WHERE PERTANYAAN_PSIKOTES_ID IN (' . implode( ', ', $question_placeholders ) . ') ORDER BY PERTANYAAN_PSIKOTES_ID, URUTAN_TAMPILAN, JAWABAN_PSIKOTES_ID'
                );
                $answer_statement->execute( $answer_parameters );
                while ( $answer = $answer_statement->fetch( PDO::FETCH_ASSOC ) ) {
                    $answers_by_question[ (int) $answer['QUESTION_ID'] ][] = [
                        'id' => (int) $answer['ID'],
                        'text' => $this->read_oracle_text( $answer['ANSWER_TEXT'] ),
                        'is_correct' => 1 === (int) $answer['IS_CORRECT'],
                    ];
                }
            }

            $questions_by_test = [];
            foreach ( $questions as $question ) {
                $question_id = (int) $question['ID'];
                $data = json_decode( $this->read_oracle_text( $question['QUESTION_DATA'] ), true );
                if ( ! is_array( $data ) || empty( $data['prompt'] ) ) {
                    throw new RuntimeException( 'An IQ question has invalid question data.' );
                }
                $answers = $answers_by_question[ $question_id ] ?? [];
                $active = ! empty( $data['active'] );
                if ( ! $include_inactive && ! $active ) {
                    continue;
                }
                $questions_by_test[ (int) $question['TEST_ID'] ][] = [
                    'id' => $question_id,
                    'prompt' => (string) $data['prompt'],
                    'subtest' => (string) ( $data['subtest'] ?? '' ),
                    'type' => (string) $question['TYPE'],
                    'active' => $active,
                    'display_order' => (int) $question['DISPLAY_ORDER'],
                    'choices' => $answers,
                ];
            }

            foreach ( $tests as &$test ) {
                $test_id = (int) $test['ID'];
                $test['ID'] = $test_id;
                $test['DURATION'] = (int) $test['DURATION'];
                $test['QUESTION_COUNT'] = (int) $test['QUESTION_COUNT'];
                $test['questions'] = $questions_by_test[ $test_id ] ?? [];
            }
            unset( $test );
            return $tests;
        } catch ( PDOException | RuntimeException $exception ) {
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment IQ assessment read failed: ' . $exception->getMessage() );
            return [];
        }
    }

public function get_iq_question_bank(): array {
        $questions = [];
        foreach ( $this->get_iq_assessment( true ) as $test ) {
            foreach ( $test['questions'] as $question ) {
                $answer_texts = [];
                foreach ( $question['choices'] as $choice ) {
                    if ( $choice['is_correct'] ) {
                        $answer_texts[] = $choice['text'];
                    }
                }
                $questions[] = [
                    'id' => (string) $question['id'],
                    'prompt' => $question['prompt'],
                    'test' => 'iq',
                    'subtest' => $question['subtest'],
                    'type' => $question['type'],
                    'choices' => array_column( $question['choices'], 'text' ),
                    'answer' => implode( ' | ', $answer_texts ),
                    'status' => $question['active'] ? 'active' : 'inactive',
                    'updated' => '',
                ];
            }
        }
        return $questions;
    }

public function get_psychotest_question_bank(): array {
        $questions = $this->get_iq_question_bank();

        foreach ( $this->get_pauli_assessment( true ) as $test ) {
            foreach ( (array) ( $test['questions'] ?? [] ) as $question ) {
                $questions[] = [
                    'id' => (string) ( $question['id'] ?? '' ),
                    'prompt' => (string) ( $question['prompt'] ?? '' ),
                    'test' => 'pauli',
                    'subtest' => '',
                    'type' => 'numeric',
                    'choices' => [],
                    'answer' => '',
                    'status' => ! empty( $question['active'] ) ? 'active' : 'inactive',
                    'updated' => '',
                    'number' => (int) ( $question['segment'] ?? 0 ),
                ];
            }
        }

        foreach ( $this->get_personality_assessments( true ) as $test_key => $test ) {
            foreach ( (array) ( $test['questions'] ?? [] ) as $question ) {
                $questions[] = [
                    'id' => (string) ( $question['id'] ?? '' ),
                    'prompt' => implode( "\n", array_map( 'strval', (array) ( $question['items'] ?? [] ) ) ),
                    'test' => (string) $test_key,
                    'subtest' => '',
                    'type' => 'paired',
                    'choices' => [],
                    'answer' => '',
                    'status' => ! empty( $question['active'] ) ? 'active' : 'inactive',
                    'updated' => '',
                    'number' => (int) ( $question['number'] ?? 0 ),
                ];
            }
        }

        return $questions;
    }

public function get_pauli_assessment( bool $include_inactive = false ): array {
        try {
            $connection = $this->get_connection();
            $test_filter = $include_inactive ? '' : ' AND IS_ACTIVE = 1';
            $test_statement = $connection->query(
                "SELECT TES_PSIKOLOGI_ID AS ID, JUDUL AS TITLE, PETUNJUK_PENGERJAAN AS INSTRUCTIONS, DURASI_PENGERJAAN AS DURATION, JUMLAH_SOAL AS QUESTION_COUNT FROM HRS_TES_PSIKOLOGI WHERE UPPER(JUDUL) LIKE 'PAULI - %'" . $test_filter . ' ORDER BY URUTAN_TAMPILAN, TES_PSIKOLOGI_ID'
            );
            $tests = [];
            while ( $test = $test_statement->fetch( PDO::FETCH_ASSOC ) ) {
                $test['INSTRUCTIONS'] = $this->read_oracle_text( $test['INSTRUCTIONS'] );
                $tests[] = $test;
            }
            if ( ! $tests ) {
                return [];
            }

            foreach ( $tests as &$test ) {
                $question_statement = $connection->prepare(
                    'SELECT PERTANYAAN_PSIKOTES_ID AS ID, TEKS_PERTANYAAN AS QUESTION_DATA, JENIS_PERTANYAAN AS TYPE, DISPLAY_ORDER FROM HRS_PERTANYAAN_PSIKOTES WHERE TES_PSIKOLOGI_ID = :test_id ORDER BY DISPLAY_ORDER, PERTANYAAN_PSIKOTES_ID'
                );
                $question_statement->execute( [ 'test_id' => (int) $test['ID'] ] );
                $questions = [];
                while ( $question = $question_statement->fetch( PDO::FETCH_ASSOC ) ) {
                    $data = json_decode( $this->read_oracle_text( $question['QUESTION_DATA'] ), true );
                    if ( ! is_array( $data ) || empty( $data['prompt'] ) ) {
                        throw new RuntimeException( 'A Pauli column has invalid question data.' );
                    }
                    if ( ! $include_inactive && empty( $data['active'] ) ) {
                        continue;
                    }
                    $questions[] = [
                        'id' => (int) $question['ID'],
                        'prompt' => (string) $data['prompt'],
                        'segment' => absint( $data['segment'] ?? 0 ),
                        'active' => ! empty( $data['active'] ),
                        'type' => (string) $question['TYPE'],
                        'display_order' => (int) $question['DISPLAY_ORDER'],
                    ];
                }
                $test['ID'] = (int) $test['ID'];
                $test['DURATION'] = (int) $test['DURATION'];
                $test['QUESTION_COUNT'] = (int) $test['QUESTION_COUNT'];
                $test['questions'] = $questions;
            }
            unset( $test );
            return $tests;
        } catch ( PDOException | RuntimeException $exception ) {
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment Pauli assessment read failed: ' . $exception->getMessage() );
            return [];
        }
    }

public function get_personality_assessment_record( int $test_id, int $result_id, array $response_ids ): array {
        $response_ids = array_values( array_unique( array_filter( array_map( 'absint', $response_ids ) ) ) );
        if ( $test_id < 1 || ( $result_id < 1 && ! $response_ids ) ) {
            return [ 'success' => false, 'error' => 'ID tes atau data jawaban tidak valid.' ];
        }

        try {
            $connection = $this->get_connection();
            $result = [];
            if ( $result_id > 0 ) {
                $result_statement = $connection->prepare(
                    'SELECT HASIL_TES_PSIKOLOGI_ID AS ID, MULAI_PADA, DIKUMPULKAN_PADA, NILAI FROM HRS_HASIL_TES_PSIKOLOGI WHERE HASIL_TES_PSIKOLOGI_ID = :result_id AND TES_PSIKOLOGI_ID = :test_id'
                );
                $result_statement->execute( [ 'result_id' => $result_id, 'test_id' => $test_id ] );
                $result = $result_statement->fetch( PDO::FETCH_ASSOC ) ?: [];
                if ( ! $result ) {
                    throw new RuntimeException( 'The personality assessment result was not found for its test.' );
                }
            }

            $responses = [];
            if ( $response_ids ) {
                $placeholders = [];
                $parameters = [ 'test_id' => $test_id ];
                foreach ( $response_ids as $index => $response_id ) {
                    $placeholder = 'response_' . $index;
                    $placeholders[] = ':' . $placeholder;
                    $parameters[ $placeholder ] = $response_id;
                }
                $response_statement = $connection->prepare(
                    'SELECT R.RESPON_PSIKOTES_ID AS RESPONSE_ID, R.HASIL AS RESPONSE_STATUS, R.TEKS_JAWABAN AS ANSWER_DATA, Q.PERTANYAAN_PSIKOTES_ID AS QUESTION_ID, Q.TEKS_PERTANYAAN AS QUESTION_DATA, Q.JENIS_PERTANYAAN AS QUESTION_TYPE FROM HRS_RESPON_PSIKOTES R JOIN HRS_PERTANYAAN_PSIKOTES Q ON Q.PERTANYAAN_PSIKOTES_ID = R.PERTANYAAN_PSIKOTES_ID WHERE Q.TES_PSIKOLOGI_ID = :test_id AND R.RESPON_PSIKOTES_ID IN (' . implode( ', ', $placeholders ) . ') ORDER BY Q.DISPLAY_ORDER, Q.PERTANYAAN_PSIKOTES_ID'
                );
                $response_statement->execute( $parameters );
                while ( $row = $response_statement->fetch( PDO::FETCH_ASSOC ) ) {
                    $question = json_decode( $this->read_oracle_text( $row['QUESTION_DATA'] ), true );
                    $answer = json_decode( $this->read_oracle_text( $row['ANSWER_DATA'] ), true );
                    if ( ! is_array( $question ) || ! is_array( $question['items'] ?? null ) || ! is_array( $answer ) ) {
                        throw new RuntimeException( 'A saved personality response has invalid question or answer data.' );
                    }

                    $selected = [];
                    foreach ( [ 'most', 'least', 'choice' ] as $part ) {
                        if ( isset( $answer[ $part ] ) && isset( $question['items'][ (int) $answer[ $part ] ] ) ) {
                            $selected[ $part ] = (string) $question['items'][ (int) $answer[ $part ] ];
                        }
                    }
                    $responses[] = [
                        'response_id' => (int) $row['RESPONSE_ID'],
                        'question_id' => (int) $row['QUESTION_ID'],
                        'number' => (int) ( $question['number'] ?? 0 ),
                        'question_type' => (string) $row['QUESTION_TYPE'],
                        'items' => array_map( 'strval', $question['items'] ),
                        'answer' => $answer,
                        'selected' => $selected,
                        'saved_status' => (string) $row['RESPONSE_STATUS'],
                    ];
                }
                if ( count( $responses ) !== count( $response_ids ) ) {
                    throw new RuntimeException( 'One or more saved personality responses do not belong to this test.' );
                }
            }

            return [ 'success' => true, 'result' => $result, 'responses' => $responses ];
        } catch ( Throwable $exception ) {
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment personality assessment report read failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Data jawaban tes kepribadian tidak dapat dibaca dari Oracle.' ];
        }
    }

public function get_personality_assessments( bool $include_inactive = false ): array {
        try {
            $connection = $this->get_connection();
            $active_filter = $include_inactive ? '' : ' AND IS_ACTIVE = 1';
            $test_statement = $connection->query(
                "SELECT TES_PSIKOLOGI_ID AS ID, JUDUL AS TITLE, PETUNJUK_PENGERJAAN AS INSTRUCTIONS, DURASI_PENGERJAAN AS DURATION, JUMLAH_SOAL AS QUESTION_COUNT, IS_ACTIVE FROM HRS_TES_PSIKOLOGI WHERE (UPPER(JUDUL) LIKE 'DISC - %' OR UPPER(JUDUL) LIKE 'PAPI KOSTICK - %')" . $active_filter . ' ORDER BY URUTAN_TAMPILAN, TES_PSIKOLOGI_ID'
            );
            $tests = [];
            while ( $test = $test_statement->fetch( PDO::FETCH_ASSOC ) ) {
                $test['INSTRUCTIONS'] = $this->read_oracle_text( $test['INSTRUCTIONS'] );
                $key = 0 === strpos( strtoupper( (string) $test['TITLE'] ), 'DISC - ' ) ? 'disc' : 'papi';
                $tests[ $key ] = $test;
            }
            if ( ! $tests ) {
                return [];
            }

            foreach ( $tests as $key => &$test ) {
                $question_statement = $connection->prepare(
                    'SELECT PERTANYAAN_PSIKOTES_ID AS ID, TEKS_PERTANYAAN AS QUESTION_DATA, JENIS_PERTANYAAN AS TYPE, DISPLAY_ORDER FROM HRS_PERTANYAAN_PSIKOTES WHERE TES_PSIKOLOGI_ID = :test_id ORDER BY DISPLAY_ORDER, PERTANYAAN_PSIKOTES_ID'
                );
                $question_statement->execute( [ 'test_id' => (int) $test['ID'] ] );
                $questions = [];
                while ( $question = $question_statement->fetch( PDO::FETCH_ASSOC ) ) {
                    $data = json_decode( $this->read_oracle_text( $question['QUESTION_DATA'] ), true );
                    if ( ! is_array( $data ) || empty( $data['number'] ) || ! is_array( $data['items'] ) ) {
                        throw new RuntimeException( 'A personality question has invalid question data.' );
                    }
                    if ( ! $include_inactive && empty( $data['active'] ) ) {
                        continue;
                    }
                    $expected_item_count = 'disc' === $key ? 4 : 2;
                    if ( count( $data['items'] ) !== $expected_item_count ) {
                        throw new RuntimeException( 'A personality question has an invalid number of statements.' );
                    }
                    $questions[] = [
                        'id' => (int) $question['ID'],
                        'number' => (int) $data['number'],
                        'items' => array_map( 'strval', $data['items'] ),
                        'active' => ! empty( $data['active'] ),
                        'type' => (string) $question['TYPE'],
                        'display_order' => (int) $question['DISPLAY_ORDER'],
                    ];
                }
                $test['ID'] = (int) $test['ID'];
                $test['DURATION'] = (int) $test['DURATION'];
                $test['QUESTION_COUNT'] = (int) $test['QUESTION_COUNT'];
                $test['IS_ACTIVE'] = (int) $test['IS_ACTIVE'];
                $test['questions'] = $questions;
            }
            unset( $test );
            return $tests;
        } catch ( PDOException | RuntimeException $exception ) {
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment personality assessment read failed: ' . $exception->getMessage() );
            return [];
        }
    }

public function get_user_interview_candidates( string $department ): array {
        return $this->fetch_all(
            'WITH latest_schedule AS (SELECT J.*, ROW_NUMBER() OVER (PARTITION BY J.KANDIDAT_ID ORDER BY J.JADWAL_ID DESC) AS RN FROM HRS_JADWAL_KANDIDAT J), latest_result AS (SELECT W.*, ROW_NUMBER() OVER (PARTITION BY W.KANDIDAT_ID ORDER BY W.SUBMITED_AT DESC, W.HASIL_USER DESC) AS RN FROM HRS_HASIL_WAWANCARA_USER W WHERE LOWER(W.NAMA_DEPARTEMEN) = LOWER(:result_department)), latest_decision AS (SELECT D.*, ROW_NUMBER() OVER (PARTITION BY D.KANDIDAT_ID ORDER BY D.REKAPAN_KEPUTUSAN_ID DESC) AS RN FROM HRS_REKAPAN_KEPUTUSAN_HR D) SELECT S.KANDIDAT_ID AS CANDIDATE_ID, COALESCE(P.NAMA_LENGKAP, P.NAMA_KANDIDAT, S.NAMA_KANDIDAT) AS CANDIDATE_NAME, P.POSISI_DILAMAR AS POSITION, P.DEALER, S.NAMA_DEPARTEMEN AS DEPARTMENT, S.STATUS_KANDIDAT AS CANDIDATE_STATUS, J.TANGGAL_WAWANCARA AS INTERVIEW_DATE, J.JAM_WAWANCARA AS INTERVIEW_TIME, J.METODE_WAWANCARA AS INTERVIEW_METHOD, J.LOKASI_WAWANCARA AS INTERVIEW_LOCATION, J.HASIL_HR AS HR_RESULT, W.NILAI_PENAMPILAN AS SCORE_PRESENTATION, W.NILAI_KOMUNIKASI AS SCORE_COMMUNICATION, W.NILAI_MOTIVASI AS SCORE_MOTIVATION, W.NILAI_PENGALAMAN AS SCORE_EXPERIENCE, W.NILAI_KETERAMPILAN AS SCORE_KNOWLEDGE, W.NILAI_KERJASAMA AS SCORE_TEAMWORK, W.NILAI_PROBLEMSOLVING AS SCORE_PROBLEM_SOLVING, W.NILAI_ADAPTASI AS SCORE_ADAPTABILITY, W.NILAI_TOTAL AS SCORE, W.REKOMENDASI, W.CATATAN_WAWANCARA AS INTERVIEW_NOTES, W.NAMA_PEWAWANCARA AS INTERVIEWER, W.TANGGAL_WAWANCARA AS RESULT_DATE, W.STATUS_SELEKSI AS INTERVIEW_STATUS, D.KEPUTUSAN_AKHIR AS FINAL_DECISION FROM HRS_STATUS_KANDIDAT S LEFT JOIN HRS_PROFIL_KANDIDAT P ON P.KANDIDAT_ID = S.KANDIDAT_ID LEFT JOIN latest_schedule J ON J.KANDIDAT_ID = S.KANDIDAT_ID AND J.RN = 1 LEFT JOIN latest_result W ON W.KANDIDAT_ID = S.KANDIDAT_ID AND W.RN = 1 LEFT JOIN latest_decision D ON D.KANDIDAT_ID = S.KANDIDAT_ID AND D.RN = 1 WHERE LOWER(S.NAMA_DEPARTEMEN) = LOWER(:department) AND S.STATUS_KANDIDAT_ID = (SELECT MAX(S2.STATUS_KANDIDAT_ID) FROM HRS_STATUS_KANDIDAT S2 WHERE S2.KANDIDAT_ID = S.KANDIDAT_ID AND LOWER(S2.NAMA_DEPARTEMEN) = LOWER(:department2)) ORDER BY J.TANGGAL_WAWANCARA NULLS LAST, S.KANDIDAT_ID DESC',
            [ 'result_department' => $department, 'department' => $department, 'department2' => $department ]
        );
    }

public function get_user_interview_results( string $department ): array {
        return $this->fetch_all(
            'WITH latest_results AS (SELECT W.*, ROW_NUMBER() OVER (PARTITION BY W.KANDIDAT_ID, LOWER(W.NAMA_DEPARTEMEN) ORDER BY W.SUBMITED_AT DESC, W.HASIL_USER DESC) AS RN FROM HRS_HASIL_WAWANCARA_USER W WHERE LOWER(W.NAMA_DEPARTEMEN) = LOWER(:department)) SELECT W.KANDIDAT_ID AS CANDIDATE_ID, COALESCE(P.NAMA_LENGKAP, W.NAMA_KANDIDAT) AS CANDIDATE_NAME, P.POSISI_DILAMAR AS POSITION, W.TANGGAL_WAWANCARA AS INTERVIEW_DATE, W.NILAI_TOTAL AS SCORE, W.REKOMENDASI AS RECOMMENDATION, W.STATUS_SELEKSI AS INTERVIEW_STATUS, D.KEPUTUSAN_AKHIR AS FINAL_DECISION FROM latest_results W LEFT JOIN HRS_PROFIL_KANDIDAT P ON P.KANDIDAT_ID = W.KANDIDAT_ID LEFT JOIN (SELECT KANDIDAT_ID, KEPUTUSAN_AKHIR, ROW_NUMBER() OVER (PARTITION BY KANDIDAT_ID ORDER BY REKAPAN_KEPUTUSAN_ID DESC) AS RN FROM HRS_REKAPAN_KEPUTUSAN_HR) D ON D.KANDIDAT_ID = W.KANDIDAT_ID AND D.RN = 1 WHERE W.RN = 1 ORDER BY W.SUBMITED_AT DESC',
            [ 'department' => $department ]
        );
    }

private function limit_text( mixed $value, int $length ): string {
        $text = trim( (string) $value );

        return function_exists( 'mb_substr' )
            ? mb_substr( $text, 0, $length, 'UTF-8' )
            : substr( $text, 0, $length );
    }

private function read_oracle_text( mixed $value ): string {
        return $this->oracle_lob_to_string( $value );
    }

public function save_final_decision( int $candidate_id, string $decision ): array {
        if ( $candidate_id < 1 || ! in_array( $decision, [ 'Diterima', 'Tidak Diterima' ], true ) || ! $this->is_configured() || ! class_exists( 'PDO' ) || ! in_array( 'oci', PDO::getAvailableDrivers(), true ) ) {
            return [ 'success' => false, 'error' => 'Database kandidat belum terhubung atau keputusan tidak valid.' ];
        }

        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();

            $statement = $connection->prepare(
                'SELECT COALESCE(NAMA_LENGKAP, NAMA_KANDIDAT) AS CANDIDATE_NAME FROM HRS_PROFIL_KANDIDAT WHERE KANDIDAT_ID = :candidate_id FOR UPDATE'
            );
            $statement->execute( [ 'candidate_id' => $candidate_id ] );
            $candidate_name = (string) $statement->fetchColumn();
            if ( '' === $candidate_name ) {
                throw new RuntimeException( 'Candidate interview result was not found.' );
            }

            $statement = $connection->prepare(
                'SELECT NILAI_TOTAL, REKOMENDASI, NAMA_DEPARTEMEN, TANGGAL_WAWANCARA FROM HRS_HASIL_WAWANCARA_USER WHERE KANDIDAT_ID = :candidate_id ORDER BY SUBMITED_AT DESC, HASIL_USER DESC FETCH FIRST 1 ROWS ONLY'
            );
            $statement->execute( [ 'candidate_id' => $candidate_id ] );
            $interview = $statement->fetch( PDO::FETCH_ASSOC );
            if ( ! $interview ) {
                throw new RuntimeException( 'Candidate interview result was not found.' );
            }

            $statement = $connection->prepare(
                'SELECT REKAPAN_KEPUTUSAN_ID FROM HRS_REKAPAN_KEPUTUSAN_HR WHERE KANDIDAT_ID = :candidate_id ORDER BY REKAPAN_KEPUTUSAN_ID DESC FETCH FIRST 1 ROWS ONLY'
            );
            $statement->execute( [ 'candidate_id' => $candidate_id ] );
            $decision_id = (int) $statement->fetchColumn();
            if ( $decision_id > 0 ) {
                $statement = $connection->prepare(
                    'UPDATE HRS_REKAPAN_KEPUTUSAN_HR SET NAMA_KANDIDAT = :candidate_name, NAMA_DEPARTEMENT = :department, TANGGAL_WAWANCARA = TO_DATE(:interview_date, \'YYYY-MM-DD\'), TOTAL_NILAI = :score, REKOMENDASI_USER = :recommendation, KEPUTUSAN_AKHIR = :decision, DITERIMA_HR = :accepted, MENUNGGU_KEPUTUSAN = :pending WHERE REKAPAN_KEPUTUSAN_ID = :decision_id'
                );
                $statement->execute(
                    [
                        'candidate_name' => $candidate_name,
                        'department' => $interview['NAMA_DEPARTEMEN'],
                        'interview_date' => gmdate( 'Y-m-d', strtotime( (string) $interview['TANGGAL_WAWANCARA'] ) ),
                        'score' => $interview['NILAI_TOTAL'],
                        'recommendation' => $interview['REKOMENDASI'],
                        'decision' => $decision,
                        'accepted' => 'Diterima' === $decision ? 1 : 0,
                        'pending' => 0,
                        'decision_id' => $decision_id,
                    ]
                );
            } else {
                $statement = $connection->prepare(
                    'INSERT INTO HRS_REKAPAN_KEPUTUSAN_HR (KANDIDAT_ID, NAMA_KANDIDAT, NAMA_DEPARTEMENT, TANGGAL_WAWANCARA, TOTAL_NILAI, REKOMENDASI_USER, KEPUTUSAN_AKHIR, DITERIMA_HR, MENUNGGU_KEPUTUSAN) VALUES (:candidate_id, :candidate_name, :department, TO_DATE(:interview_date, \'YYYY-MM-DD\'), :score, :recommendation, :decision, :accepted, :pending)'
                );
                $statement->execute(
                    [
                        'candidate_id' => $candidate_id,
                        'candidate_name' => $candidate_name,
                        'department' => $interview['NAMA_DEPARTEMEN'],
                        'interview_date' => gmdate( 'Y-m-d', strtotime( (string) $interview['TANGGAL_WAWANCARA'] ) ),
                        'score' => $interview['NILAI_TOTAL'],
                        'recommendation' => $interview['REKOMENDASI'],
                        'decision' => $decision,
                        'accepted' => 'Diterima' === $decision ? 1 : 0,
                        'pending' => 0,
                    ]
                );
            }

            $statement = $connection->prepare(
                'UPDATE HRS_REKAPAN_KEPUTUSAN SET KEPUTUSAN_FINAL = :decision, DITERIMA_HR = :accepted, MENUNGGU_KEPUTUSAN = :pending WHERE KANDIDAT_ID = :candidate_id'
            );
            $statement->execute(
                [
                    'decision' => $decision,
                    'accepted' => 'Diterima' === $decision ? 1 : 0,
                    'pending' => 0,
                    'candidate_id' => $candidate_id,
                ]
            );

            $statement = $connection->prepare(
                'UPDATE HRS_PROFIL_KANDIDAT SET STATUS_SELEKSI = :decision WHERE KANDIDAT_ID = :candidate_id'
            );
            $statement->execute( [ 'decision' => $decision, 'candidate_id' => $candidate_id ] );
            $statement = $connection->prepare(
                'UPDATE HRS_STATUS_KANDIDAT SET STATUS_KANDIDAT = :decision, HASIL_HR = :hr_result WHERE KANDIDAT_ID = :candidate_id'
            );
            $statement->execute( [ 'decision' => $decision, 'hr_result' => $decision, 'candidate_id' => $candidate_id ] );
            $statement = $connection->prepare(
                'SELECT L.LAMARAN_ID FROM HRS_LAMARAN L JOIN HRS_PROFILE_KANDIDAT_LOGIN PL ON PL.PROFILE_KANDIDAT_LOGIN = L.PROFILE_KANDIDAT_ID WHERE PL.KANDIDAT_ID = :candidate_id FOR UPDATE'
            );
            $statement->execute( [ 'candidate_id' => $candidate_id ] );
            $application_ids = $statement->fetchAll( PDO::FETCH_COLUMN );
            foreach ( $application_ids as $application_id ) {
                $statement = $connection->prepare(
                    'UPDATE HRS_LAMARAN SET STATUS_TERKINI = :decision, STATUS_AKHIR = :final_status, UPDATED_AT = CURRENT_TIMESTAMP WHERE LAMARAN_ID = :application_id'
                );
                $statement->execute( [ 'decision' => $decision, 'final_status' => $decision, 'application_id' => $application_id ] );
                $statement = $connection->prepare(
                    'INSERT INTO HRS_RIWAYAT_STATUS_PELAMAR (LAMARAN_ID, TAHAP_SELEKSI, STATUS_SELEKSI, TANGGAL_STATUS, KETERANGAN_PELAMAR) VALUES (:application_id, :stage, :decision, CURRENT_DATE, :description)'
                );
                $statement->execute(
                    [
                        'application_id' => $application_id,
                        'stage' => 'Keputusan Akhir',
                        'decision' => $decision,
                        'description' => $this->limit_text( $decision, 100 ),
                    ]
                );
            }

            $connection->commit();
            return [ 'success' => true ];
        } catch ( Throwable $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            error_log( 'Recruitment final decision save failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Keputusan akhir gagal disimpan. Silakan coba lagi.' ];
        }
    }

public function save_iq_question( array $question ): array {
        $subtest = sanitize_key( $question['subtest'] ?? '' );
        $prompt = trim( (string) ( $question['prompt'] ?? '' ) );
        $type = sanitize_key( $question['type'] ?? '' );
        $question_id = absint( $question['id'] ?? 0 );
        $choices = array_values( array_filter( array_map( 'trim', (array) ( $question['choices'] ?? [] ) ) ) );
        $correct_texts = array_map( 'trim', explode( '|', (string) ( $question['answer'] ?? '' ) ) );
        $status = 'inactive' === ( $question['status'] ?? '' ) ? 'inactive' : 'active';
        $subtest_titles = [
            'iq_numbers' => 'IQ - 1 Berhitung Angka',
            'iq_assembly' => 'IQ - 2 Gabungan Bagian',
            'iq_words' => 'IQ - 3 Hubungan Kata',
            'iq_abstraction' => 'IQ - 4 Abstraksi Nonverbal',
        ];
        if ( $question_id < 1 || ! isset( $subtest_titles[ $subtest ] ) || '' === $prompt || count( $choices ) < 2 || ! in_array( $type, [ 'multiple_choice', 'multi_select' ], true ) ) {
            return [ 'success' => false, 'error' => 'Data soal IQ tidak valid.' ];
        }
        $answer_indexes = [];
        foreach ( $choices as $index => $choice ) {
            if ( in_array( $choice, $correct_texts, true ) ) {
                $answer_indexes[] = $index;
            }
        }
        $required_answers = 'multi_select' === $type ? 2 : 1;
        if ( count( $answer_indexes ) !== $required_answers || count( array_unique( $choices ) ) !== count( $choices ) ) {
            return [ 'success' => false, 'error' => 'Pastikan opsi unik dan jumlah kunci jawaban sesuai tipe soal.' ];
        }

        $connection = null;
        $owns_transaction = false;
        try {
            $connection = $this->get_connection();
            $owns_transaction = ! $connection->inTransaction();
            if ( $owns_transaction ) {
                $connection->beginTransaction();
            }
            $test_statement = $connection->prepare(
                'SELECT TES_PSIKOLOGI_ID FROM HRS_TES_PSIKOLOGI WHERE JUDUL = :title FETCH FIRST 1 ROWS ONLY'
            );
            $test_statement->execute( [ 'title' => $subtest_titles[ $subtest ] ] );
            $test_id = (int) $test_statement->fetchColumn();
            if ( $test_id < 1 ) {
                throw new RuntimeException( 'IQ test catalog is not initialized.' );
            }

            $question_data = wp_json_encode(
                [ 'prompt' => $prompt, 'subtest' => $subtest, 'active' => 'active' === $status ],
                JSON_UNESCAPED_UNICODE
            );
            $create_new = $question_id < 1;
            $display_order = 0;
            if ( ! $create_new ) {
                $existing_statement = $connection->prepare(
                    'SELECT TES_PSIKOLOGI_ID, DISPLAY_ORDER, JENIS_PERTANYAAN AS TYPE FROM HRS_PERTANYAAN_PSIKOTES WHERE PERTANYAAN_PSIKOTES_ID = :id FOR UPDATE'
                );
                $existing_statement->execute( [ 'id' => $question_id ] );
                $existing = $existing_statement->fetch( PDO::FETCH_ASSOC );
                if ( ! $existing || (int) $existing['TES_PSIKOLOGI_ID'] !== $test_id ) {
                    throw new RuntimeException( 'IQ question was not found in the selected subtest.' );
                }
                $display_order = (int) $existing['DISPLAY_ORDER'];
                $answer_statement = $connection->prepare(
                    'SELECT JAWABAN_PSIKOTES_ID, JAWABAN_TEKS, APAKAH_BENAR FROM HRS_JAWABAN_PSIKOTES WHERE PERTANYAAN_PSIKOTES_ID = :question_id ORDER BY URUTAN_TAMPILAN, JAWABAN_PSIKOTES_ID'
                );
                $answer_statement->execute( [ 'question_id' => $question_id ] );
                $existing_answers = [];
                while ( $answer = $answer_statement->fetch( PDO::FETCH_ASSOC ) ) {
                    $answer['JAWABAN_TEKS'] = $this->read_oracle_text( $answer['JAWABAN_TEKS'] );
                    $existing_answers[] = $answer;
                }
                $old_question_statement = $connection->prepare(
                    'SELECT TEKS_PERTANYAAN FROM HRS_PERTANYAAN_PSIKOTES WHERE PERTANYAAN_PSIKOTES_ID = :id'
                );
                $old_question_statement->execute( [ 'id' => $question_id ] );
                $old_question_data = json_decode( $this->read_oracle_text( $old_question_statement->fetchColumn() ), true );
                if ( ! is_array( $old_question_data ) ) {
                    throw new RuntimeException( 'IQ question data is invalid.' );
                }
                if ( count( $existing_answers ) !== count( $choices ) || ( $existing['TYPE'] ?? $type ) !== $type ) {
                    throw new RuntimeException( 'IQ question option count and type must remain unchanged.' );
                }

                $question_data = wp_json_encode(
                    [
                        'prompt' => $prompt,
                        'subtest' => $subtest,
                        'active' => ! empty( $old_question_data['active'] ),
                    ],
                    JSON_UNESCAPED_UNICODE
                );
                $update_question = $connection->prepare(
                    'UPDATE HRS_PERTANYAAN_PSIKOTES SET TEKS_PERTANYAAN = :question_data WHERE PERTANYAAN_PSIKOTES_ID = :id'
                );
                $update_question->execute( [ 'question_data' => $question_data, 'id' => $question_id ] );
                $update_answer = $connection->prepare(
                    'UPDATE HRS_JAWABAN_PSIKOTES SET APAKAH_BENAR = :is_correct, JAWABAN_TEKS = :answer_text, NILAI = :score WHERE JAWABAN_PSIKOTES_ID = :answer_id AND PERTANYAAN_PSIKOTES_ID = :question_id'
                );
                foreach ( $existing_answers as $index => $answer ) {
                    $is_correct = in_array( $choices[ $index ], $correct_texts, true );
                    $update_answer->execute(
                        [
                            'is_correct' => $is_correct ? 1 : 0,
                            'answer_text' => $choices[ $index ],
                            'score' => $is_correct ? 1 : 0,
                            'answer_id' => (int) $answer['JAWABAN_PSIKOTES_ID'],
                            'question_id' => $question_id,
                        ]
                    );
                }
                if ( $owns_transaction ) {
                    $connection->commit();
                }
                return [ 'success' => true, 'id' => $question_id ];
            }

            $display_order_statement = $connection->prepare(
                'SELECT NVL(MAX(DISPLAY_ORDER), 0) + 1 FROM HRS_PERTANYAAN_PSIKOTES WHERE TES_PSIKOLOGI_ID = :test_id'
            );
            $display_order_statement->execute( [ 'test_id' => $test_id ] );
            $insert = $connection->prepare(
                'INSERT INTO HRS_PERTANYAAN_PSIKOTES (TES_PSIKOLOGI_ID, TEKS_PERTANYAAN, JENIS_PERTANYAAN, NILAI_PERTANYAAN, DISPLAY_ORDER) VALUES (:test_id, :question_data, :type, 1, :display_order) RETURNING PERTANYAAN_PSIKOTES_ID INTO :out_id'
            );
            $new_id = $this->execute_returning_id(
                $insert,
                [
                    'test_id' => $test_id,
                    'question_data' => $question_data,
                    'type' => $type,
                    'display_order' => $display_order > 0 ? $display_order : (int) $display_order_statement->fetchColumn(),
                ],
                'out_id'
            );
            foreach ( $choices as $index => $choice ) {
                $is_correct = in_array( $choice, $correct_texts, true );
                $insert_answer = $connection->prepare(
                    'INSERT INTO HRS_JAWABAN_PSIKOTES (PERTANYAAN_PSIKOTES_ID, APAKAH_BENAR, URUTAN_TAMPILAN, JAWABAN_TEKS, NILAI) VALUES (:question_id, :is_correct, :display_order, :answer_text, :score) RETURNING JAWABAN_PSIKOTES_ID INTO :out_id'
                );
                $this->execute_returning_id(
                    $insert_answer,
                    [
                        'question_id' => $new_id,
                        'is_correct' => $is_correct ? 1 : 0,
                        'display_order' => $index + 1,
                        'answer_text' => $choice,
                        'score' => $is_correct ? 1 : 0,
                    ],
                    'out_id'
                );
            }
            if ( $owns_transaction ) {
                $connection->commit();
            }
            return [ 'success' => true, 'id' => $new_id ];
        } catch ( Throwable $exception ) {
            if ( $owns_transaction && $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment IQ question save failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Soal IQ gagal disimpan ke Oracle.' ];
        }
    }

    public function save_pauli_question( array $question ): array {
        $question_id = absint( $question['id'] ?? 0 );
        $prompt = trim( (string) ( $question['prompt'] ?? '' ) );
        if ( $question_id < 1 || '' === $prompt ) {
            return [ 'success' => false, 'error' => 'Data soal Pauli tidak valid.' ];
        }

        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $statement = $connection->prepare(
                'SELECT TES_PSIKOLOGI_ID, JENIS_PERTANYAAN, DISPLAY_ORDER, TEKS_PERTANYAAN FROM HRS_PERTANYAAN_PSIKOTES WHERE PERTANYAAN_PSIKOTES_ID = :id FOR UPDATE'
            );
            $statement->execute( [ 'id' => $question_id ] );
            $existing = $statement->fetch( PDO::FETCH_ASSOC );
            if ( ! $existing ) {
                throw new RuntimeException( 'Pauli question was not found.' );
            }

            $test_statement = $connection->prepare(
                'SELECT JUDUL FROM HRS_TES_PSIKOLOGI WHERE TES_PSIKOLOGI_ID = :test_id'
            );
            $test_statement->execute( [ 'test_id' => (int) $existing['TES_PSIKOLOGI_ID'] ] );
            if ( 0 !== strpos( strtoupper( (string) $test_statement->fetchColumn() ), 'PAULI - ' ) ) {
                throw new RuntimeException( 'Question does not belong to the Pauli assessment.' );
            }

            $old_data = json_decode( $this->read_oracle_text( $existing['TEKS_PERTANYAAN'] ), true );
            if ( ! is_array( $old_data ) || empty( $old_data['segment'] ) ) {
                throw new RuntimeException( 'Pauli question data is invalid.' );
            }
            if ( (string) ( $old_data['prompt'] ?? '' ) === $prompt ) {
                $connection->commit();
                return [ 'success' => true, 'id' => $question_id ];
            }

            $new_data = [
                'prompt' => $prompt,
                'segment' => (int) $old_data['segment'],
                'active' => ! empty( $old_data['active'] ),
            ];
            $update = $connection->prepare(
                'UPDATE HRS_PERTANYAAN_PSIKOTES SET TEKS_PERTANYAAN = :question_data WHERE PERTANYAAN_PSIKOTES_ID = :id'
            );
            $update->execute(
                [
                    'question_data' => wp_json_encode( $new_data, JSON_UNESCAPED_UNICODE ),
                    'id' => $question_id,
                ],
            );
            $connection->commit();

            return [ 'success' => true, 'id' => $question_id ];
        } catch ( Throwable $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment Pauli question save failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Soal Pauli gagal disimpan ke Oracle.' ];
        }
    }

    public function save_personality_question( array $question ): array {
        $question_id = absint( $question['id'] ?? 0 );
        $test_key = sanitize_key( $question['test'] ?? '' );
        $expected_count = 'disc' === $test_key ? 4 : ( 'papi' === $test_key ? 2 : 0 );
        $items = array_values( array_map( 'trim', (array) ( $question['items'] ?? [] ) ) );
        if (
            $question_id < 1
            || 0 === $expected_count
            || $expected_count !== count( $items )
            || in_array( '', $items, true )
        ) {
            return [ 'success' => false, 'error' => 'Jumlah pernyataan DISC/PAPI tidak sesuai format tes.' ];
        }

        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $statement = $connection->prepare(
                'SELECT TES_PSIKOLOGI_ID, JENIS_PERTANYAAN, DISPLAY_ORDER, TEKS_PERTANYAAN FROM HRS_PERTANYAAN_PSIKOTES WHERE PERTANYAAN_PSIKOTES_ID = :id FOR UPDATE'
            );
            $statement->execute( [ 'id' => $question_id ] );
            $existing = $statement->fetch( PDO::FETCH_ASSOC );
            if ( ! $existing ) {
                throw new RuntimeException( 'Personality question was not found.' );
            }

            $test_statement = $connection->prepare(
                'SELECT JUDUL FROM HRS_TES_PSIKOLOGI WHERE TES_PSIKOLOGI_ID = :test_id'
            );
            $test_statement->execute( [ 'test_id' => (int) $existing['TES_PSIKOLOGI_ID'] ] );
            $test_title = strtoupper( (string) $test_statement->fetchColumn() );
            $expected_prefix = 'disc' === $test_key ? 'DISC - ' : 'PAPI KOSTICK - ';
            if ( 0 !== strpos( $test_title, $expected_prefix ) ) {
                throw new RuntimeException( 'Question does not belong to the selected personality assessment.' );
            }

            $old_data = json_decode( $this->read_oracle_text( $existing['TEKS_PERTANYAAN'] ), true );
            if ( ! is_array( $old_data ) || empty( $old_data['number'] ) || ! is_array( $old_data['items'] ) ) {
                throw new RuntimeException( 'Personality question data is invalid.' );
            }
            if ( array_values( array_map( 'strval', $old_data['items'] ) ) === $items ) {
                $connection->commit();
                return [ 'success' => true, 'id' => $question_id ];
            }

            $new_data = [
                'number' => (int) $old_data['number'],
                'items' => $items,
                'active' => ! empty( $old_data['active'] ),
            ];
            $update = $connection->prepare(
                'UPDATE HRS_PERTANYAAN_PSIKOTES SET TEKS_PERTANYAAN = :question_data WHERE PERTANYAAN_PSIKOTES_ID = :id'
            );
            $update->execute(
                [
                    'question_data' => wp_json_encode( $new_data, JSON_UNESCAPED_UNICODE ),
                    'id' => $question_id,
                ],
            );
            $connection->commit();

            return [ 'success' => true, 'id' => $question_id ];
        } catch ( Throwable $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment personality question save failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Soal DISC/PAPI gagal disimpan ke Oracle.' ];
        }
    }

public function save_iq_response( int $question_id, array $selected_answer_ids, int $response_id = 0 ): array {
        $selected_answer_ids = array_values( array_unique( array_map( 'absint', $selected_answer_ids ) ) );
        $connection = null;
        $owns_transaction = false;
        try {
            $connection = $this->get_connection();
            $owns_transaction = ! $connection->inTransaction();
            if ( $owns_transaction ) {
                $connection->beginTransaction();
            }
            $question_type_statement = $connection->prepare(
                "SELECT Q.JENIS_PERTANYAAN FROM HRS_PERTANYAAN_PSIKOTES Q JOIN HRS_TES_PSIKOLOGI T ON T.TES_PSIKOLOGI_ID = Q.TES_PSIKOLOGI_ID WHERE Q.PERTANYAAN_PSIKOTES_ID = :question_id AND UPPER(T.JUDUL) LIKE 'IQ - %' AND T.IS_ACTIVE = 1"
            );
            $question_type_statement->execute( [ 'question_id' => $question_id ] );
            $question_type = (string) $question_type_statement->fetchColumn();
            if ( ! in_array( $question_type, [ 'multiple_choice', 'multi_select' ], true ) ) {
                throw new RuntimeException( 'IQ question type was not found or is unsupported.' );
            }
            if ( count( $selected_answer_ids ) > ( 'multi_select' === $question_type ? 2 : 1 ) ) {
                throw new RuntimeException( 'Too many answer choices were selected.' );
            }
            $answer_statement = $connection->prepare(
                'SELECT JAWABAN_PSIKOTES_ID, JAWABAN_TEKS, APAKAH_BENAR FROM HRS_JAWABAN_PSIKOTES WHERE PERTANYAAN_PSIKOTES_ID = :question_id ORDER BY URUTAN_TAMPILAN, JAWABAN_PSIKOTES_ID'
            );
            $answer_statement->execute( [ 'question_id' => $question_id ] );
            $answers = [];
            while ( $answer = $answer_statement->fetch( PDO::FETCH_ASSOC ) ) {
                $answer['JAWABAN_TEKS'] = $this->read_oracle_text( $answer['JAWABAN_TEKS'] );
                $answers[] = $answer;
            }
            if ( ! $answers ) {
                throw new RuntimeException( 'Question answer choices were not found.' );
            }
            $valid_ids = array_map( 'intval', array_column( $answers, 'JAWABAN_PSIKOTES_ID' ) );
            if ( array_diff( $selected_answer_ids, $valid_ids ) ) {
                throw new RuntimeException( 'Selected answers do not belong to the requested question.' );
            }
            $correct_ids = [];
            foreach ( $answers as $answer ) {
                if ( 1 === (int) $answer['APAKAH_BENAR'] ) {
                    $correct_ids[] = (int) $answer['JAWABAN_PSIKOTES_ID'];
                }
            }
            $is_correct = $selected_answer_ids && $correct_ids
                && ! array_diff( $selected_answer_ids, $correct_ids )
                && ! array_diff( $correct_ids, $selected_answer_ids );
            $answer_texts = [];
            foreach ( $answers as $answer ) {
                if ( in_array( (int) $answer['JAWABAN_PSIKOTES_ID'], $selected_answer_ids, true ) ) {
                    $answer_texts[] = (string) $answer['JAWABAN_TEKS'];
                }
            }

            if ( $response_id > 0 ) {
                $response_check = $connection->prepare(
                    'SELECT RESPON_PSIKOTES_ID FROM HRS_RESPON_PSIKOTES WHERE RESPON_PSIKOTES_ID = :response_id AND PERTANYAAN_PSIKOTES_ID = :question_id FOR UPDATE'
                );
                $response_check->execute( [ 'response_id' => $response_id, 'question_id' => $question_id ] );
                if ( ! $response_check->fetchColumn() ) {
                    throw new RuntimeException( 'Saved IQ response was not found.' );
                }
                $update_response = $connection->prepare(
                    'UPDATE HRS_RESPON_PSIKOTES SET HASIL = :result, TEKS_JAWABAN = :answer_text, NILAI_DIPEROLEH = :score, DIJAWAB_PADA = CURRENT_TIMESTAMP, DIPERBARUI_PADA = CURRENT_TIMESTAMP WHERE RESPON_PSIKOTES_ID = :response_id'
                );
                $update_response->execute(
                    [
                        'result' => $is_correct ? 'BENAR' : ( $selected_answer_ids ? 'SALAH' : 'KOSONG' ),
                        'answer_text' => $answer_texts ? implode( ' | ', $answer_texts ) : '(belum dijawab)',
                        'score' => $is_correct ? 1 : 0,
                        'response_id' => $response_id,
                    ]
                );
            } else {
                    $insert_response = $connection->prepare(
                        'INSERT INTO HRS_RESPON_PSIKOTES (HASIL, TEKS_JAWABAN, NILAI_DIPEROLEH, PERTANYAAN_PSIKOTES_ID, DIJAWAB_PADA, DIBUAT_PADA, DIPERBARUI_PADA) VALUES (:result, :answer_text, :score, :question_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING RESPON_PSIKOTES_ID INTO :out_id'
                    );
                    $response_id = $this->execute_returning_id(
                        $insert_response,
                        [
                            'result' => $is_correct ? 'BENAR' : ( $selected_answer_ids ? 'SALAH' : 'KOSONG' ),
                            'answer_text' => $answer_texts ? implode( ' | ', $answer_texts ) : '(belum dijawab)',
                            'score' => $is_correct ? 1 : 0,
                            'question_id' => $question_id,
                        ],
                        'out_id'
                    );
            }

            $delete_choices = $connection->prepare(
                'DELETE FROM HRS_PILIHAN_RESPON_PSIKOTES WHERE RESPON_PSIKOTES_ID = :response_id'
            );
            $delete_choices->execute( [ 'response_id' => $response_id ] );
            foreach ( $selected_answer_ids as $answer_id ) {
                $insert_choice = $connection->prepare(
                    'INSERT INTO HRS_PILIHAN_RESPON_PSIKOTES (RESPON_PSIKOTES_ID, JAWABAN_PSIKOTES_ID) VALUES (:response_id, :answer_id) RETURNING PILIHAN_RESPON_ID INTO :out_id'
                );
                $this->execute_returning_id(
                    $insert_choice,
                    [
                        'response_id' => $response_id,
                        'answer_id' => $answer_id,
                    ],
                    'out_id'
                );
            }
            if ( $owns_transaction ) {
                $connection->commit();
            }
            return [ 'success' => true, 'response_id' => $response_id ];
        } catch ( Throwable $exception ) {
            if ( $owns_transaction && $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment IQ response save failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Jawaban belum berhasil tersimpan. Periksa koneksi lalu coba kembali.' ];
        }
    }

public function save_pauli_column_response( int $test_id, int $question_id, int $response_id, string $answers, array $numbers ): array {
        if ( 99 !== strlen( $answers ) || 1 !== preg_match( '/\A[0-9-]{99}\z/', $answers )
            || 100 !== count( $numbers ) || array_filter( $numbers, static function ( $number ) {
                return ! is_numeric( $number ) || (int) $number < 0 || (int) $number > 9;
            } ) ) {
            return [ 'success' => false, 'error' => 'Jawaban kolom Pauli tidak valid.' ];
        }

        $numbers = array_map( 'intval', array_values( $numbers ) );
        $score = 0;
        for ( $index = 0; $index < 99; $index++ ) {
            if ( '-' !== $answers[ $index ] && (int) $answers[ $index ] === ( ( $numbers[ $index ] + $numbers[ $index + 1 ] ) % 10 ) ) {
                $score++;
            }
        }
        $answer_text = wp_json_encode(
            [ 'numbers' => $numbers, 'answers' => $answers ],
            JSON_UNESCAPED_UNICODE
        );

        $connection = null;
        $owns_transaction = false;
        try {
            $connection = $this->get_connection();
            $owns_transaction = ! $connection->inTransaction();
            if ( $owns_transaction ) {
                $connection->beginTransaction();
            }
            $question_statement = $connection->prepare(
                "SELECT Q.PERTANYAAN_PSIKOTES_ID FROM HRS_PERTANYAAN_PSIKOTES Q JOIN HRS_TES_PSIKOLOGI T ON T.TES_PSIKOLOGI_ID = Q.TES_PSIKOLOGI_ID WHERE Q.PERTANYAAN_PSIKOTES_ID = :question_id AND Q.TES_PSIKOLOGI_ID = :test_id AND UPPER(T.JUDUL) LIKE 'PAULI - %' AND T.IS_ACTIVE = 1 FOR UPDATE"
            );
            $question_statement->execute( [ 'question_id' => $question_id, 'test_id' => $test_id ] );
            if ( ! $question_statement->fetchColumn() ) {
                throw new RuntimeException( 'Pauli question was not found in the active assessment.' );
            }
            $has_answers = false !== strpos( $answers, '0' )
                || false !== strpos( $answers, '1' )
                || false !== strpos( $answers, '2' )
                || false !== strpos( $answers, '3' )
                || false !== strpos( $answers, '4' )
                || false !== strpos( $answers, '5' )
                || false !== strpos( $answers, '6' )
                || false !== strpos( $answers, '7' )
                || false !== strpos( $answers, '8' )
                || false !== strpos( $answers, '9' );
            $result = $has_answers ? 'TERJAWAB' : 'KOSONG';
            if ( $response_id > 0 ) {
                $response_check = $connection->prepare(
                    'SELECT RESPON_PSIKOTES_ID FROM HRS_RESPON_PSIKOTES WHERE RESPON_PSIKOTES_ID = :response_id AND PERTANYAAN_PSIKOTES_ID = :question_id FOR UPDATE'
                );
                $response_check->execute( [ 'response_id' => $response_id, 'question_id' => $question_id ] );
                if ( ! $response_check->fetchColumn() ) {
                    throw new RuntimeException( 'Saved Pauli response was not found.' );
                }
                $update_response = $connection->prepare(
                    'UPDATE HRS_RESPON_PSIKOTES SET HASIL = :result, TEKS_JAWABAN = :answer_text, NILAI_DIPEROLEH = :score, DIJAWAB_PADA = CURRENT_TIMESTAMP, DIPERBARUI_PADA = CURRENT_TIMESTAMP WHERE RESPON_PSIKOTES_ID = :response_id'
                );
                $update_response->execute(
                    [
                        'result' => $result,
                        'answer_text' => $answer_text,
                        'score' => $score,
                        'response_id' => $response_id,
                    ]
                );
            } else {
                $insert_response = $connection->prepare(
                    'INSERT INTO HRS_RESPON_PSIKOTES (HASIL, TEKS_JAWABAN, NILAI_DIPEROLEH, PERTANYAAN_PSIKOTES_ID, DIJAWAB_PADA, DIBUAT_PADA, DIPERBARUI_PADA) VALUES (:result, :answer_text, :score, :question_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING RESPON_PSIKOTES_ID INTO :out_id'
                );
                $response_id = $this->execute_returning_id(
                    $insert_response,
                    [
                        'result' => $result,
                        'answer_text' => $answer_text,
                        'score' => $score,
                        'question_id' => $question_id,
                    ],
                    'out_id'
                );
            }
            if ( $owns_transaction ) {
                $connection->commit();
            }
            return [ 'success' => true, 'response_id' => $response_id ];
        } catch ( Throwable $exception ) {
            if ( $owns_transaction && $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment Pauli response save failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Jawaban kolom Pauli gagal disimpan ke Oracle.' ];
        }
    }

public function save_personality_response( int $test_id, int $question_id, int $response_id, array $answer ): array {
        $connection = null;
        $owns_transaction = false;
        try {
            $connection = $this->get_connection();
            $owns_transaction = ! $connection->inTransaction();
            if ( $owns_transaction ) {
                $connection->beginTransaction();
            }
            $question_statement = $connection->prepare(
                'SELECT Q.JENIS_PERTANYAAN, Q.TEKS_PERTANYAAN FROM HRS_PERTANYAAN_PSIKOTES Q JOIN HRS_TES_PSIKOLOGI T ON T.TES_PSIKOLOGI_ID = Q.TES_PSIKOLOGI_ID WHERE Q.PERTANYAAN_PSIKOTES_ID = :question_id AND Q.TES_PSIKOLOGI_ID = :test_id AND T.IS_ACTIVE = 1 AND (UPPER(T.JUDUL) LIKE \'DISC - %\' OR UPPER(T.JUDUL) LIKE \'PAPI KOSTICK - %\')'
            );
            $question_statement->execute( [ 'question_id' => $question_id, 'test_id' => $test_id ] );
            $question = $question_statement->fetch( PDO::FETCH_ASSOC );
            if ( ! is_array( $question ) ) {
                throw new RuntimeException( 'Personality question was not found in the active assessment.' );
            }
            $question_data = json_decode( $this->read_oracle_text( $question['TEKS_PERTANYAAN'] ), true );
            $type = (string) $question['JENIS_PERTANYAAN'];
            if ( ! is_array( $question_data ) || empty( $question_data['active'] ) ) {
                throw new RuntimeException( 'Personality question is inactive.' );
            }
            if ( 'disc_most_least' === $type ) {
                if ( ! isset( $answer['most'], $answer['least'] )
                    || $answer['most'] < 0 || $answer['most'] > 3
                    || $answer['least'] < 0 || $answer['least'] > 3
                    || $answer['most'] === $answer['least'] ) {
                    throw new RuntimeException( 'DISC answer is invalid.' );
                }
            } elseif ( 'paired' === $type ) {
                if ( ! isset( $answer['choice'] ) || $answer['choice'] < 0 || $answer['choice'] > 1 ) {
                    throw new RuntimeException( 'PAPI Kostick answer is invalid.' );
                }
            } else {
                throw new RuntimeException( 'Personality question type is unsupported.' );
            }
            $answer_json = wp_json_encode( $answer, JSON_UNESCAPED_UNICODE );
            if ( ! is_string( $answer_json ) ) {
                throw new RuntimeException( 'Personality answer could not be encoded.' );
            }

            // Oracle requires both score columns to be non-null; zero is a storage sentinel, not a score.
            if ( $response_id > 0 ) {
                $response_check = $connection->prepare(
                    'SELECT RESPON_PSIKOTES_ID FROM HRS_RESPON_PSIKOTES WHERE RESPON_PSIKOTES_ID = :response_id AND PERTANYAAN_PSIKOTES_ID = :question_id FOR UPDATE'
                );
                $response_check->execute( [ 'response_id' => $response_id, 'question_id' => $question_id ] );
                if ( ! $response_check->fetchColumn() ) {
                    throw new RuntimeException( 'Saved personality response was not found.' );
                }
                $update_response = $connection->prepare(
                    'UPDATE HRS_RESPON_PSIKOTES SET HASIL = :result, TEKS_JAWABAN = :answer_text, NILAI_DIPEROLEH = 0, DIJAWAB_PADA = CURRENT_TIMESTAMP, DIPERBARUI_PADA = CURRENT_TIMESTAMP WHERE RESPON_PSIKOTES_ID = :response_id'
                );
                $update_response->execute(
                    [                     'result' => 'TANPA_SKOR', 'answer_text' => $answer_json, 'response_id' => $response_id ]
                );
            } else {
                $insert_response = $connection->prepare(
                    'INSERT INTO HRS_RESPON_PSIKOTES (HASIL, TEKS_JAWABAN, NILAI_DIPEROLEH, PERTANYAAN_PSIKOTES_ID, DIJAWAB_PADA, DIBUAT_PADA, DIPERBARUI_PADA) VALUES (:result, :answer_text, 0, :question_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING RESPON_PSIKOTES_ID INTO :out_id'
                );
                $response_id = $this->execute_returning_id(
                    $insert_response,
                    [ 'result' => 'TANPA_SKOR', 'answer_text' => $answer_json, 'question_id' => $question_id ],
                    'out_id'
                );
            }
            if ( $owns_transaction ) {
                $connection->commit();
            }
            return [ 'success' => true, 'response_id' => $response_id ];
        } catch ( Throwable $exception ) {
            if ( $owns_transaction && $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment personality response save failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Jawaban belum berhasil tersimpan. Periksa koneksi lalu coba kembali.' ];
        }
    }

public function save_user_interview_result( int $candidate_id, array $result ): array {
        $valid_recommendations = [ 'Sangat Baik', 'Baik', 'Dipertimbangkan', 'Tidak Dilanjutkan' ];
        $valid_selection_statuses = [ 'Lolos Wawancara User', 'Tidak Lolos Wawancara User' ];
        $score_keys = [ 'presentation', 'communication', 'motivation', 'experience', 'knowledge', 'teamwork', 'problem_solving', 'adaptability' ];
        $scores = (array) ( $result['scores'] ?? [] );
        $notes = trim( (string) ( $result['notes'] ?? '' ) );
        $interviewer = trim( (string) ( $result['interviewer'] ?? '' ) );
        $department = trim( (string) ( $result['department'] ?? '' ) );
        $valid_scores = count( $scores ) === count( $score_keys );
        foreach ( $score_keys as $score_key ) {
            $valid_scores = $valid_scores && isset( $scores[ $score_key ] ) && (int) $scores[ $score_key ] >= 1 && (int) $scores[ $score_key ] <= 5;
        }
        $text_length = static function ( string $value ): int {
            return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
        };
        if ( $candidate_id < 1 || '' === $department || ! $valid_scores || ! in_array( $result['recommendation'] ?? '', $valid_recommendations, true ) || ! in_array( $result['selection_status'] ?? '', $valid_selection_statuses, true ) ) {
            return [ 'success' => false, 'error' => 'Data penilaian wawancara tidak valid.' ];
        }
        if ( $text_length( $notes ) > 250 || $text_length( $interviewer ) > 150 || $text_length( $department ) > 100 ) {
            return [ 'success' => false, 'error' => 'Catatan maksimal 250 karakter; nama interviewer atau departemen melebihi batas database.' ];
        }
        if ( ! $this->is_configured() || ! class_exists( 'PDO' ) || ! in_array( 'oci', PDO::getAvailableDrivers(), true ) ) {
            return [ 'success' => false, 'error' => 'Database kandidat belum terhubung atau data kandidat tidak valid.' ];
        }

        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();

            $statement = $connection->prepare(
                'SELECT COALESCE(NAMA_LENGKAP, NAMA_KANDIDAT) AS CANDIDATE_NAME FROM HRS_PROFIL_KANDIDAT WHERE KANDIDAT_ID = :candidate_id FOR UPDATE'
            );
            $statement->execute( [ 'candidate_id' => $candidate_id ] );
            $name = (string) $statement->fetchColumn();
            if ( '' === $name ) {
                throw new RuntimeException( 'Candidate profile was not found.' );
            }

            $statement = $connection->prepare(
                'SELECT STATUS_KANDIDAT_ID FROM HRS_STATUS_KANDIDAT WHERE KANDIDAT_ID = :candidate_id AND LOWER(NAMA_DEPARTEMEN) = LOWER(:department) AND STATUS_KANDIDAT_ID = (SELECT MAX(S2.STATUS_KANDIDAT_ID) FROM HRS_STATUS_KANDIDAT S2 WHERE S2.KANDIDAT_ID = :candidate_id2 AND LOWER(S2.NAMA_DEPARTEMEN) = LOWER(:department2)) FOR UPDATE'
            );
            $statement->execute( [ 'candidate_id' => $candidate_id, 'department' => $department, 'candidate_id2' => $candidate_id, 'department2' => $department ] );
            $status_candidate_id = $statement->fetchColumn();
            if ( false === $status_candidate_id ) {
                throw new RuntimeException( 'Candidate is not assigned to this department.' );
            }

            $statement = $connection->prepare(
                'SELECT KEPUTUSAN_AKHIR FROM HRS_REKAPAN_KEPUTUSAN_HR WHERE KANDIDAT_ID = :candidate_id ORDER BY REKAPAN_KEPUTUSAN_ID DESC FETCH FIRST 1 ROWS ONLY'
            );
            $statement->execute( [ 'candidate_id' => $candidate_id ] );
            $final_decision = $statement->fetchColumn();
            if ( false !== $final_decision && '' !== trim( (string) $final_decision ) ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'Hasil wawancara terkunci karena keputusan akhir HR sudah disimpan.' ];
            }

            $statement = $connection->prepare(
                'SELECT TO_CHAR(TANGGAL_WAWANCARA, \'YYYY-MM-DD\') FROM HRS_JADWAL_KANDIDAT WHERE KANDIDAT_ID = :candidate_id ORDER BY JADWAL_ID DESC FETCH FIRST 1 ROWS ONLY'
            );
            $statement->execute( [ 'candidate_id' => $candidate_id ] );
            $interview_date = $statement->fetchColumn();
            if ( false === $interview_date || '' === $interview_date ) {
                throw new RuntimeException( 'Candidate does not have a scheduled user interview.' );
            }

            $statement = $connection->prepare(
                'INSERT INTO HRS_HASIL_WAWANCARA_USER (KANDIDAT_ID, STATUS_KANDIDAT_ID, NAMA_PEWAWANCARA, NAMA_DEPARTEMEN, NILAI_PENAMPILAN, NILAI_KOMUNIKASI, NILAI_MOTIVASI, NILAI_PENGALAMAN, NILAI_KETERAMPILAN, NILAI_KERJASAMA, NILAI_PROBLEMSOLVING, NILAI_ADAPTASI, NILAI_TOTAL, REKOMENDASI, CATATAN_WAWANCARA, TANGGAL_WAWANCARA, SUBMITED_AT, STATUS_SELEKSI, NAMA_KANDIDAT) VALUES (:candidate_id, :status_candidate_id, :interviewer, :department, :presentation, :communication, :motivation, :experience, :skills, :teamwork, :problem_solving, :adaptability, :score, :recommendation, :notes, TO_DATE(:interview_date, \'YYYY-MM-DD\'), CURRENT_TIMESTAMP, :selection_status, :candidate_name)'
            );
            $statement->execute(
                [
                    'candidate_id' => $candidate_id,
                    'status_candidate_id' => $status_candidate_id,
                    'interviewer' => $interviewer,
                    'department' => $department,
                    'presentation' => $result['scores']['presentation'],
                    'communication' => $result['scores']['communication'],
                    'motivation' => $result['scores']['motivation'],
                    'experience' => $result['scores']['experience'],
                    'skills' => $result['scores']['knowledge'],
                    'teamwork' => $result['scores']['teamwork'],
                    'problem_solving' => $result['scores']['problem_solving'],
                    'adaptability' => $result['scores']['adaptability'],
                    'score' => $result['score'],
                    'recommendation' => $result['recommendation'],
                    'notes' => $notes,
                    'interview_date' => $interview_date,
                    'selection_status' => $result['selection_status'],
                    'candidate_name' => $name,
                ]
            );

            $statement = $connection->prepare(
                'UPDATE HRS_PROFIL_KANDIDAT SET STATUS_SELEKSI = :selection_status WHERE KANDIDAT_ID = :candidate_id'
            );
            $statement->execute( [ 'selection_status' => $result['selection_status'], 'candidate_id' => $candidate_id ] );

            $statement = $connection->prepare( 'UPDATE HRS_STATUS_KANDIDAT SET STATUS_KANDIDAT = :selection_status, NAMA_KANDIDAT = :candidate_name WHERE STATUS_KANDIDAT_ID = :status_candidate_id' );
            $statement->execute(
                [
                    'selection_status' => $result['selection_status'],
                    'candidate_name' => $name,
                    'status_candidate_id' => $status_candidate_id,
                ]
            );
            if ( 0 === $statement->rowCount() ) {
                throw new RuntimeException( 'Candidate department assignment changed during interview submission.' );
            }

            $statement = $connection->prepare(
                'SELECT L.LAMARAN_ID FROM HRS_LAMARAN L JOIN HRS_PROFILE_KANDIDAT_LOGIN PL ON PL.PROFILE_KANDIDAT_LOGIN = L.PROFILE_KANDIDAT_ID WHERE PL.KANDIDAT_ID = :candidate_id FOR UPDATE'
            );
            $statement->execute( [ 'candidate_id' => $candidate_id ] );
            $application_ids = $statement->fetchAll( PDO::FETCH_COLUMN );
            foreach ( $application_ids as $application_id ) {
                $statement = $connection->prepare(
                    'UPDATE HRS_LAMARAN SET STATUS_TERKINI = :selection_status, UPDATED_AT = CURRENT_TIMESTAMP WHERE LAMARAN_ID = :application_id'
                );
                $statement->execute( [ 'selection_status' => $result['selection_status'], 'application_id' => $application_id ] );
                $statement = $connection->prepare(
                    'INSERT INTO HRS_RIWAYAT_STATUS_PELAMAR (LAMARAN_ID, TAHAP_SELEKSI, STATUS_SELEKSI, TANGGAL_STATUS, KETERANGAN_PELAMAR) VALUES (:application_id, :stage, :selection_status, CURRENT_DATE, :description)'
                );
                $statement->execute(
                    [
                        'application_id' => $application_id,
                        'stage' => 'Wawancara User',
                        'selection_status' => $result['selection_status'],
                        'description' => $this->limit_text( $result['recommendation'], 100 ),
                    ]
                );
            }

            $connection->commit();
            return [ 'success' => true ];
        } catch ( Throwable $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            error_log( 'Recruitment user interview result save failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Hasil wawancara gagal disimpan. Silakan coba lagi.' ];
        }
    }

public function seed_iq_assessment(): array {
        $connection = null;
        $owns_transaction = false;
        try {
            $connection = $this->get_connection();
            $existing = $connection->query(
                "SELECT COUNT(*) FROM HRS_TES_PSIKOLOGI WHERE UPPER(JUDUL) LIKE 'IQ - %'"
            )->fetchColumn();
            if ( (int) $existing > 0 ) {
                return [ 'success' => false, 'error' => 'Bank IQ sudah tersedia. Tidak ada data yang diubah.' ];
            }

            $owns_transaction = ! $connection->inTransaction();
            if ( $owns_transaction ) {
                $connection->beginTransaction();
            }
            $definitions = $this->build_iq_seed_questions();
            $total_questions = 0;

            foreach ( $definitions as $test_order => $definition ) {
                $insert_test = $connection->prepare(
                    'INSERT INTO HRS_TES_PSIKOLOGI (JUDUL, PETUNJUK_PENGERJAAN, DURASI_PENGERJAAN, JUMLAH_SOAL, IS_ACTIVE, URUTAN_TAMPILAN, CREATED_AT, UPDATED_AT) VALUES (:title, :instructions, :duration, :question_count, 1, :display_order, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING TES_PSIKOLOGI_ID INTO :out_id'
                );
                $test_id = $this->execute_returning_id(
                    $insert_test,
                    [
                        'title' => $definition['title'],
                        'instructions' => $definition['instructions'],
                        'duration' => $definition['duration'],
                        'question_count' => count( $definition['questions'] ),
                        'display_order' => $test_order + 1,
                    ],
                    'out_id'
                );

                foreach ( $definition['questions'] as $question_order => $question ) {
                    $question_data = wp_json_encode(
                        [
                            'prompt' => $question['prompt'],
                            'subtest' => $definition['key'],
                            'active' => true,
                        ],
                        JSON_UNESCAPED_UNICODE
                    );
                    $insert_question = $connection->prepare(
                        'INSERT INTO HRS_PERTANYAAN_PSIKOTES (TES_PSIKOLOGI_ID, TEKS_PERTANYAAN, JENIS_PERTANYAAN, NILAI_PERTANYAAN, DISPLAY_ORDER) VALUES (:test_id, :question_data, :type, 1, :display_order) RETURNING PERTANYAAN_PSIKOTES_ID INTO :out_id'
                    );
                    $question_id = $this->execute_returning_id(
                        $insert_question,
                        [
                            'test_id' => $test_id,
                            'question_data' => $question_data,
                            'type' => $question['type'],
                            'display_order' => $question_order + 1,
                        ],
                        'out_id'
                    );

                    foreach ( $question['choices'] as $choice_order => $choice ) {
                        $insert_answer = $connection->prepare(
                            'INSERT INTO HRS_JAWABAN_PSIKOTES (PERTANYAAN_PSIKOTES_ID, APAKAH_BENAR, URUTAN_TAMPILAN, JAWABAN_TEKS, NILAI) VALUES (:question_id, :is_correct, :display_order, :answer_text, :score) RETURNING JAWABAN_PSIKOTES_ID INTO :out_id'
                        );
                        $this->execute_returning_id(
                            $insert_answer,
                            [
                                'question_id' => $question_id,
                                'is_correct' => in_array( $choice_order, $question['answer_indexes'], true ) ? 1 : 0,
                                'display_order' => $choice_order + 1,
                                'answer_text' => $choice,
                                'score' => in_array( $choice_order, $question['answer_indexes'], true ) ? 1 : 0,
                            ],
                            'out_id'
                        );
                    }
                    $total_questions++;
                }
            }

            if ( $owns_transaction ) {
                $connection->commit();
            }
            return [ 'success' => true, 'count' => $total_questions ];
        } catch ( Throwable $exception ) {
            if ( $owns_transaction && $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment IQ assessment seed failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Bank soal IQ gagal disimpan ke Oracle.' ];
        }
    }

public function seed_pauli_assessment(): array {
        $connection = null;
        $owns_transaction = false;
        try {
            $connection = $this->get_connection();
            $existing = $connection->query(
                "SELECT COUNT(*) FROM HRS_TES_PSIKOLOGI WHERE UPPER(JUDUL) LIKE 'PAULI - %'"
            )->fetchColumn();
            if ( (int) $existing > 0 ) {
                return [ 'success' => false, 'error' => 'Bank Pauli sudah tersedia. Tidak ada data yang diubah.' ];
            }

            $owns_transaction = ! $connection->inTransaction();
            if ( $owns_transaction ) {
                $connection->beginTransaction();
            }
            $display_order_statement = $connection->query(
                'SELECT NVL(MAX(URUTAN_TAMPILAN), 0) + 1 FROM HRS_TES_PSIKOLOGI'
            );
            $insert_test = $connection->prepare(
                'INSERT INTO HRS_TES_PSIKOLOGI (JUDUL, PETUNJUK_PENGERJAAN, DURASI_PENGERJAAN, JUMLAH_SOAL, IS_ACTIVE, URUTAN_TAMPILAN, CREATED_AT, UPDATED_AT) VALUES (:title, :instructions, 60, 1980, 1, :display_order, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING TES_PSIKOLOGI_ID INTO :out_id'
            );
            $test_id = $this->execute_returning_id(
                $insert_test,
                [
                    'title' => 'PAULI - Tes Koran 60 Menit',
                    'instructions' => 'Jumlahkan dua angka yang berurutan dari atas ke bawah dan tulis angka satuan hasilnya. Setiap kolom dikerjakan selama tiga menit. Ketika aba-aba garis muncul, selesaikan kolom yang sedang dikerjakan; kolom yang telah dilewati tidak dapat dibuka kembali.',
                    'display_order' => (int) $display_order_statement->fetchColumn(),
                ],
                'out_id'
            );

            for ( $segment = 1; $segment <= 20; $segment++ ) {
                $question_data = wp_json_encode(
                    [
                        'prompt' => sprintf( 'Kolom %02d', $segment ),
                        'segment' => $segment,
                        'active' => true,
                    ],
                    JSON_UNESCAPED_UNICODE
                );
                $insert_question = $connection->prepare(
                    'INSERT INTO HRS_PERTANYAAN_PSIKOTES (TES_PSIKOLOGI_ID, TEKS_PERTANYAAN, JENIS_PERTANYAAN, NILAI_PERTANYAAN, DISPLAY_ORDER) VALUES (:test_id, :question_data, :type, 1, :display_order) RETURNING PERTANYAAN_PSIKOTES_ID INTO :out_id'
                );
                $this->execute_returning_id(
                    $insert_question,
                    [
                        'test_id' => $test_id,
                        'question_data' => $question_data,
                        'type' => 'numeric',
                        'display_order' => $segment,
                    ],
                    'out_id'
                );
            }

            if ( $owns_transaction ) {
                $connection->commit();
            }
            return [ 'success' => true, 'count' => 20 ];
        } catch ( Throwable $exception ) {
            if ( $owns_transaction && $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment Pauli assessment seed failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Bank Pauli gagal disimpan ke Oracle.' ];
        }
    }

public function seed_personality_assessments(): array {
        $connection = null;
        $owns_transaction = false;
        try {
            $seed_path = RECRUITMENT_PLUGIN_PATH . 'database/personality-assessment.json';
            $seed_json = file_get_contents( $seed_path );
            $definitions = false === $seed_json ? null : json_decode( $seed_json, true );
            if ( ! is_array( $definitions )
                || 24 !== count( $definitions['disc']['questions'] ?? [] )
                || 90 !== count( $definitions['papi']['questions'] ?? [] ) ) {
                throw new RuntimeException( 'The personality assessment source file is missing or incomplete.' );
            }

            $connection = $this->get_connection();
            $existing = $connection->query(
                "SELECT COUNT(*) FROM HRS_TES_PSIKOLOGI WHERE UPPER(JUDUL) LIKE 'DISC - %' OR UPPER(JUDUL) LIKE 'PAPI KOSTICK - %'"
            )->fetchColumn();
            if ( (int) $existing > 0 ) {
                return [ 'success' => false, 'error' => 'Katalog DISC atau PAPI Kostick sudah ada. Tidak ada data yang diubah.' ];
            }

            $owns_transaction = ! $connection->inTransaction();
            if ( $owns_transaction ) {
                $connection->beginTransaction();
            }
            $next_order = (int) $connection->query(
                'SELECT NVL(MAX(URUTAN_TAMPILAN), 0) + 1 FROM HRS_TES_PSIKOLOGI'
            )->fetchColumn();
            $counts = [];
            foreach ( [ 'disc', 'papi' ] as $key ) {
                $definition = $definitions[ $key ];
                $question_count = count( $definition['questions'] );
                $insert_test = $connection->prepare(
                    'INSERT INTO HRS_TES_PSIKOLOGI (JUDUL, PETUNJUK_PENGERJAAN, DURASI_PENGERJAAN, JUMLAH_SOAL, IS_ACTIVE, URUTAN_TAMPILAN, CREATED_AT, UPDATED_AT) VALUES (:title, :instructions, :duration, :question_count, 1, :display_order, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING TES_PSIKOLOGI_ID INTO :out_id'
                );
                $test_id = $this->execute_returning_id(
                    $insert_test,
                    [
                        'title' => (string) $definition['title'],
                        'instructions' => (string) $definition['instructions'],
                        'duration' => (int) $definition['duration'],
                        'question_count' => $question_count,
                        'display_order' => $next_order++,
                    ],
                    'out_id'
                );
                $question_type = 'disc' === $key ? 'disc_most_least' : 'paired';
                foreach ( $definition['questions'] as $index => $question ) {
                    if ( ! isset( $question['number'], $question['items'] )
                        || ! is_array( $question['items'] )
                        || count( $question['items'] ) !== ( 'disc' === $key ? 4 : 2 )
                        || (int) $question['number'] !== $index + 1 ) {
                        throw new RuntimeException( 'The personality assessment source has invalid question data.' );
                    }
                    $question_data = wp_json_encode(
                        [
                            'number' => (int) $question['number'],
                            'items' => array_map( 'sanitize_text_field', $question['items'] ),
                            'active' => true,
                        ],
                        JSON_UNESCAPED_UNICODE
                    );
                    if ( ! is_string( $question_data ) ) {
                        throw new RuntimeException( 'A personality question could not be encoded.' );
                    }
                    $insert_question = $connection->prepare(
                        'INSERT INTO HRS_PERTANYAAN_PSIKOTES (TES_PSIKOLOGI_ID, TEKS_PERTANYAAN, JENIS_PERTANYAAN, NILAI_PERTANYAAN, DISPLAY_ORDER) VALUES (:test_id, :question_data, :type, 0, :display_order)'
                    );
                    $insert_question->execute(
                        [
                            'test_id' => $test_id,
                            'question_data' => $question_data,
                            'type' => $question_type,
                            'display_order' => (int) $question['number'],
                        ]
                    );
                }
                $counts[ $key ] = $question_count;
            }
            if ( $owns_transaction ) {
                $connection->commit();
            }
            return [ 'success' => true, 'counts' => $counts ];
        } catch ( Throwable $exception ) {
            if ( $owns_transaction && $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment personality assessment seed failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Bank DISC dan PAPI Kostick gagal disimpan ke Oracle.' ];
        }
    }

public function set_iq_question_active( int $question_id, bool $active ): array {
        $connection = null;
        try {
            $connection = $this->get_connection();
            $statement = $connection->prepare(
                'SELECT P.TEKS_PERTANYAAN FROM HRS_PERTANYAAN_PSIKOTES P JOIN HRS_TES_PSIKOLOGI T ON T.TES_PSIKOLOGI_ID = P.TES_PSIKOLOGI_ID WHERE P.PERTANYAAN_PSIKOTES_ID = :id AND UPPER(T.JUDUL) LIKE :title'
            );
            $statement->execute( [ 'id' => $question_id, 'title' => 'IQ - %' ] );
            $data = json_decode( $this->read_oracle_text( $statement->fetchColumn() ), true );
            if ( ! is_array( $data ) ) {
                return [ 'success' => false, 'error' => 'Soal IQ tidak ditemukan.' ];
            }
            $data['active'] = $active;
            $update = $connection->prepare(
                'UPDATE HRS_PERTANYAAN_PSIKOTES SET TEKS_PERTANYAAN = :question_data WHERE PERTANYAAN_PSIKOTES_ID = :id'
            );
            $update->execute(
                [
                    'question_data' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ),
                    'id' => $question_id,
                ]
            );
            return [ 'success' => true ];
        } catch ( PDOException $exception ) {
            $this->last_error = $exception->getMessage();
            error_log( 'Recruitment IQ question status update failed: ' . $exception->getMessage() );
            return [ 'success' => false, 'error' => 'Status soal IQ gagal diperbarui di Oracle.' ];
        }
    }

}