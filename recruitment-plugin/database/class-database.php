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
            $account_statement->execute( [ 'username' => $username, 'email' => $email ] );
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
            'SELECT d.detail_lowongan_id AS "id", d.judul_pekerjaan AS "title", d.lokasi_penempatan AS "location", k.nama_dealer AS "dealer", d.kategori_kj AS "job_type", d.deskripsi_pekerjaan AS "description" FROM HRS_DETAIL_INFO_LOWONGAN d JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.kolom_pendaftaran_id = d.kolom_pendaftaran_id WHERE k.status_kolom = :status ORDER BY d.detail_lowongan_id DESC',
            [ 'status' => 1 ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_applicants(): array {
        return $this->fetch_all(
            'SELECT pkl.profile_kandidat_login AS "id", NVL(p.nama_lengkap, p.nama_kandidat) AS "name", NVL(p.email, pkl.email) AS "email", NVL(p.nomor_telepon, TO_CHAR(pkl.nomor_telepon)) AS "phone", NVL(pkl.created_at, p.dibuat_pada) AS "created_at" FROM HRS_PROFILE_KANDIDAT_LOGIN pkl JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = pkl.kandidat_id ORDER BY NVL(pkl.created_at, p.dibuat_pada) DESC'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_applications(): array {
        return $this->fetch_all(
            'SELECT lamaran_id AS "id", detail_lowongan_id AS "vacancy_id", profile_kandidat_id AS "applicant_id", status_terkini AS "status", NVL(created_at, tanggal_melamar) AS "created_at" FROM HRS_LAMARAN ORDER BY lamaran_id DESC'
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

public function get_final_decision_candidates(): array {
        return $this->fetch_all(
            'WITH latest_interviews AS (SELECT W.KANDIDAT_ID, W.NILAI_TOTAL, W.REKOMENDASI, W.NAMA_DEPARTEMEN, W.TANGGAL_WAWANCARA, ROW_NUMBER() OVER (PARTITION BY W.KANDIDAT_ID ORDER BY W.SUBMITTED_AT DESC, W.HASIL_USER DESC) AS RN FROM HRS_HASIL_WAWANCARA_USER W), latest_decisions AS (SELECT D.KANDIDAT_ID, D.KEPUTUSAN_AKHIR, ROW_NUMBER() OVER (PARTITION BY D.KANDIDAT_ID ORDER BY D.REKAPAN_KEPUTUSAN_ID DESC) AS RN FROM HRS_REKAPAN_KEPUTUSAN_HR D) SELECT P.KANDIDAT_ID AS ID, COALESCE(P.NAMA_LENGKAP, P.NAMA_KANDIDAT) AS NAME, \'KANDIDAT-\' || TO_CHAR(P.KANDIDAT_ID) AS CODE, P.POSISI_DILAMAR AS POSITION, P.DEALER AS DEALER, I.NAMA_DEPARTEMEN AS DEPARTMENT, TO_CHAR(P.TANGGAL_LAMARAN, \'YYYY-MM\') AS BATCH, TO_CHAR(P.TANGGAL_LAMARAN, \'YYYY\') AS APPLICATION_YEAR, I.REKOMENDASI AS USER_RESULT, I.TANGGAL_WAWANCARA AS USER_INTERVIEW_DATE, D.KEPUTUSAN_AKHIR AS DECISION, P.EMAIL AS EMAIL, I.NILAI_TOTAL AS USER_SCORE FROM HRS_PROFIL_KANDIDAT P JOIN latest_interviews I ON I.KANDIDAT_ID = P.KANDIDAT_ID AND I.RN = 1 LEFT JOIN latest_decisions D ON D.KANDIDAT_ID = P.KANDIDAT_ID AND D.RN = 1 ORDER BY I.TANGGAL_WAWANCARA DESC NULLS LAST, P.KANDIDAT_ID DESC'
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
            'WITH latest_schedule AS (SELECT J.*, ROW_NUMBER() OVER (PARTITION BY J.KANDIDAT_ID ORDER BY J.JADWAL_ID DESC) AS RN FROM HRS_JADWAL_KANDIDAT J), latest_result AS (SELECT W.*, ROW_NUMBER() OVER (PARTITION BY W.KANDIDAT_ID ORDER BY W.SUBMITTED_AT DESC, W.HASIL_USER DESC) AS RN FROM HRS_HASIL_WAWANCARA_USER W WHERE LOWER(W.NAMA_DEPARTEMEN) = LOWER(:result_department)), latest_decision AS (SELECT D.*, ROW_NUMBER() OVER (PARTITION BY D.KANDIDAT_ID ORDER BY D.REKAPAN_KEPUTUSAN_ID DESC) AS RN FROM HRS_REKAPAN_KEPUTUSAN_HR D) SELECT S.KANDIDAT_ID AS CANDIDATE_ID, COALESCE(P.NAMA_LENGKAP, P.NAMA_KANDIDAT, S.NAMA_KANDIDAT) AS CANDIDATE_NAME, P.EMAIL, COALESCE(P.NOMOR_TELEPON, P.NOMOR_HP, TO_CHAR(P.NO_TELP)) AS PHONE, P.JENIS_KELAMIN AS GENDER, P.TEMPAT_LAHIR AS BIRTH_PLACE, P.TANGGAL_LAHIR AS BIRTH_DATE, P.PROVINSI AS PROVINCE, P.KOTA AS CITY, P.PENDIDIKAN AS EDUCATION, P.TANGGAL_LAMARAN AS APPLICATION_DATE, P.POSISI_DILAMAR AS POSITION, P.DEALER, S.NAMA_DEPARTEMEN AS DEPARTMENT, S.STATUS_KANDIDAT AS CANDIDATE_STATUS, J.TANGGAL_WAWANCARA AS INTERVIEW_DATE, J.JAM_WAWANCARA AS INTERVIEW_TIME, J.METODE_WAWANCARA AS INTERVIEW_METHOD, J.LOKASI_WAWANCARA AS INTERVIEW_LOCATION, J.HASIL_HR AS HR_RESULT, W.NILAI_PENAMPILAN AS SCORE_PRESENTATION, W.NILAI_KOMUNIKASI AS SCORE_COMMUNICATION, W.NILAI_MOTIVASI AS SCORE_MOTIVATION, W.NILAI_PENGALAMAN AS SCORE_EXPERIENCE, W.NILAI_KETERAMPILAN AS SCORE_KNOWLEDGE, W.NILAI_KERJASAMA AS SCORE_TEAMWORK, W.NILAI_PROBLEMSOLVING AS SCORE_PROBLEM_SOLVING, W.NILAI_ADAPTASI AS SCORE_ADAPTABILITY, W.NILAI_TOTAL AS SCORE, W.REKOMENDASI, W.CATATAN_WAWANCARA AS INTERVIEW_NOTES, W.NAMA_PEWAWANCARA AS INTERVIEWER, W.TANGGAL_WAWANCARA AS RESULT_DATE, W.STATUS_SELEKSI AS INTERVIEW_STATUS, D.KEPUTUSAN_AKHIR AS FINAL_DECISION FROM HRS_STATUS_KANDIDAT S LEFT JOIN HRS_PROFIL_KANDIDAT P ON P.KANDIDAT_ID = S.KANDIDAT_ID LEFT JOIN latest_schedule J ON J.KANDIDAT_ID = S.KANDIDAT_ID AND J.RN = 1 LEFT JOIN latest_result W ON W.KANDIDAT_ID = S.KANDIDAT_ID AND W.RN = 1 LEFT JOIN latest_decision D ON D.KANDIDAT_ID = S.KANDIDAT_ID AND D.RN = 1 WHERE LOWER(S.NAMA_DEPARTEMEN) = LOWER(:department) AND S.STATUS_KANDIDAT_ID = (SELECT MAX(S2.STATUS_KANDIDAT_ID) FROM HRS_STATUS_KANDIDAT S2 WHERE S2.KANDIDAT_ID = S.KANDIDAT_ID AND LOWER(S2.NAMA_DEPARTEMEN) = LOWER(:department2)) ORDER BY J.TANGGAL_WAWANCARA NULLS LAST, S.KANDIDAT_ID DESC',
            [ 'result_department' => $department, 'department' => $department, 'department2' => $department ]
        );
    }

public function get_user_interview_results( string $department ): array {
        return $this->fetch_all(
            'WITH latest_results AS (SELECT W.*, ROW_NUMBER() OVER (PARTITION BY W.KANDIDAT_ID, LOWER(W.NAMA_DEPARTEMEN) ORDER BY W.SUBMITTED_AT DESC, W.HASIL_USER DESC) AS RN FROM HRS_HASIL_WAWANCARA_USER W WHERE LOWER(W.NAMA_DEPARTEMEN) = LOWER(:department)) SELECT W.KANDIDAT_ID AS CANDIDATE_ID, COALESCE(P.NAMA_LENGKAP, W.NAMA_KANDIDAT) AS CANDIDATE_NAME, P.POSISI_DILAMAR AS POSITION, W.TANGGAL_WAWANCARA AS INTERVIEW_DATE, W.NILAI_TOTAL AS SCORE, W.REKOMENDASI AS RECOMMENDATION, W.STATUS_SELEKSI AS INTERVIEW_STATUS, D.KEPUTUSAN_AKHIR AS FINAL_DECISION FROM latest_results W LEFT JOIN HRS_PROFIL_KANDIDAT P ON P.KANDIDAT_ID = W.KANDIDAT_ID LEFT JOIN (SELECT KANDIDAT_ID, KEPUTUSAN_AKHIR, ROW_NUMBER() OVER (PARTITION BY KANDIDAT_ID ORDER BY REKAPAN_KEPUTUSAN_ID DESC) AS RN FROM HRS_REKAPAN_KEPUTUSAN_HR) D ON D.KANDIDAT_ID = W.KANDIDAT_ID AND D.RN = 1 WHERE W.RN = 1 ORDER BY W.SUBMITTED_AT DESC',
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
                'SELECT NILAI_TOTAL, REKOMENDASI, NAMA_DEPARTEMEN, TANGGAL_WAWANCARA FROM HRS_HASIL_WAWANCARA_USER WHERE KANDIDAT_ID = :candidate_id ORDER BY SUBMITTED_AT DESC, HASIL_USER DESC FETCH FIRST 1 ROWS ONLY'
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
        $choices = array_values( array_filter( array_map( 'trim', (array) ( $question['choices'] ?? [] ) ) ) );
        $correct_texts = array_map( 'trim', explode( '|', (string) ( $question['answer'] ?? '' ) ) );
        $status = 'inactive' === ( $question['status'] ?? '' ) ? 'inactive' : 'active';
        $subtest_titles = [
            'iq_numbers' => 'IQ - 1 Berhitung Angka',
            'iq_assembly' => 'IQ - 2 Gabungan Bagian',
            'iq_words' => 'IQ - 3 Hubungan Kata',
            'iq_abstraction' => 'IQ - 4 Abstraksi Nonverbal',
        ];
        if ( ! isset( $subtest_titles[ $subtest ] ) || '' === $prompt || count( $choices ) < 2 || ! in_array( $type, [ 'multiple_choice', 'multi_select' ], true ) ) {
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

            $question_id = absint( $question['id'] ?? 0 );
            $question_data = wp_json_encode(
                [ 'prompt' => $prompt, 'subtest' => $subtest, 'active' => 'active' === $status ],
                JSON_UNESCAPED_UNICODE
            );
            $create_new = $question_id < 1;
            if ( ! $create_new ) {
                $existing_statement = $connection->prepare(
                    'SELECT TES_PSIKOLOGI_ID, DISPLAY_ORDER, JENIS_PERTANYAAN AS TYPE FROM HRS_PERTANYAAN_PSIKOTES WHERE PERTANYAAN_PSIKOTES_ID = :id FOR UPDATE'
                );
                $existing_statement->execute( [ 'id' => $question_id ] );
                $existing = $existing_statement->fetch( PDO::FETCH_ASSOC );
                if ( ! $existing || (int) $existing['TES_PSIKOLOGI_ID'] !== $test_id ) {
                    throw new RuntimeException( 'IQ question was not found in the selected subtest.' );
                }
                $answer_statement = $connection->prepare(
                    'SELECT JAWABAN_PSIKOTES_ID, JAWABAN_TEKS, APAKAH_BENAR FROM HRS_JAWABAN_PSIKOTES WHERE PERTANYAAN_PSIKOTES_ID = :question_id ORDER BY URUTAN_TAMPILAN, JAWABAN_PSIKOTES_ID'
                );
                $answer_statement->execute( [ 'question_id' => $question_id ] );
                $existing_answers = [];
                while ( $answer = $answer_statement->fetch( PDO::FETCH_ASSOC ) ) {
                    $answer['JAWABAN_TEKS'] = $this->read_oracle_text( $answer['JAWABAN_TEKS'] );
                    $existing_answers[] = $answer;
                }
                $existing_choices = array_map( function ( $answer ) {
                    return (string) $answer['JAWABAN_TEKS'];
                }, $existing_answers );
                $existing_correct = [];
                foreach ( $existing_answers as $index => $answer ) {
                    if ( 1 === (int) $answer['APAKAH_BENAR'] ) {
                        $existing_correct[] = $existing_choices[ $index ];
                    }
                }
                $old_question_statement = $connection->prepare(
                    'SELECT TEKS_PERTANYAAN FROM HRS_PERTANYAAN_PSIKOTES WHERE PERTANYAAN_PSIKOTES_ID = :id'
                );
                $old_question_statement->execute( [ 'id' => $question_id ] );
                $old_question_data = json_decode( $this->read_oracle_text( $old_question_statement->fetchColumn() ), true );
                if ( ! is_array( $old_question_data ) ) {
                    throw new RuntimeException( 'IQ question data is invalid.' );
                }
                $same_content = ( $old_question_data['prompt'] ?? '' ) === $prompt
                    && $existing_choices === $choices
                    && $existing_correct === array_values( array_intersect( $choices, $correct_texts ) )
                    && ( $existing['TYPE'] ?? $type ) === $type;

                if ( $same_content ) {
                    $update = $connection->prepare(
                        'UPDATE HRS_PERTANYAAN_PSIKOTES SET TEKS_PERTANYAAN = :question_data, JENIS_PERTANYAAN = :type WHERE PERTANYAAN_PSIKOTES_ID = :id'
                    );
                    $update->execute( [ 'question_data' => $question_data, 'type' => $type, 'id' => $question_id ] );
                    if ( $owns_transaction ) {
                        $connection->commit();
                    }
                    return [ 'success' => true, 'id' => $question_id ];
                }

                $create_new = true;
                $old_question_data['active'] = false;
                $deactivate = $connection->prepare(
                    'UPDATE HRS_PERTANYAAN_PSIKOTES SET TEKS_PERTANYAAN = :question_data WHERE PERTANYAAN_PSIKOTES_ID = :id'
                );
                $deactivate->execute(
                    [
                        'question_data' => wp_json_encode( $old_question_data, JSON_UNESCAPED_UNICODE ),
                        'id' => $question_id,
                    ]
                );
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
                    'display_order' => (int) $display_order_statement->fetchColumn(),
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
                'INSERT INTO HRS_HASIL_WAWANCARA_USER (KANDIDAT_ID, STATUS_KANDIDAT_ID, NAMA_PEWAWANCARA, NAMA_DEPARTEMEN, NILAI_PENAMPILAN, NILAI_KOMUNIKASI, NILAI_MOTIVASI, NILAI_PENGALAMAN, NILAI_KETERAMPILAN, NILAI_KERJASAMA, NILAI_PROBLEMSOLVING, NILAI_ADAPTASI, NILAI_TOTAL, REKOMENDASI, CATATAN_WAWANCARA, TANGGAL_WAWANCARA, SUBMITTED_AT, STATUS_SELEKSI, NAMA_KANDIDAT) VALUES (:candidate_id, :status_candidate_id, :interviewer, :department, :presentation, :communication, :motivation, :experience, :skills, :teamwork, :problem_solving, :adaptability, :score, :recommendation, :notes, TO_DATE(:interview_date, \'YYYY-MM-DD\'), CURRENT_TIMESTAMP, :selection_status, :candidate_name)'
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