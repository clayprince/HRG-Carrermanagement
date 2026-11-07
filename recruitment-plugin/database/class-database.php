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
     * @return array{success: bool, account?: array{id: string, profile_id: string, email: string, full_name: string}, error?: string}
     */
    public function create_applicant_account( string $email, string $full_name, string $password, array $profile_data ): array {
        $email = strtolower( trim( $email ) );
        $full_name = trim( $full_name );
        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) || 50 < strlen( $email ) || '' === $full_name || 255 < strlen( $full_name ) || strlen( $password ) < 10 ) {
            return [ 'success' => false, 'error' => 'invalid_account_data' ];
        }
        $profile_details = $profile_data['profile_details'] ?? null;
        if ( ! is_array( $profile_details ) || $email !== strtolower( trim( (string) ( $profile_data['email'] ?? '' ) ) ) || $full_name !== trim( (string) ( $profile_data['full_name'] ?? '' ) ) ) {
            return [ 'success' => false, 'error' => 'invalid_account_data' ];
        }

        $connection = null;
        try {
            $password_hash = password_hash( $password, PASSWORD_BCRYPT );
            if ( false === $password_hash ) {
                throw new RuntimeException( 'Applicant password could not be hashed.' );
            }

            $connection = $this->get_connection();
            $connection->beginTransaction();
            $insert_profile = $connection->prepare(
                'INSERT INTO HRS_PROFIL_KANDIDAT (NAMA_KANDIDAT, NAMA_LENGKAP, EMAIL, JENIS_KELAMIN, TEMPAT_LAHIR, TANGGAL_LAHIR, NOMOR_HP, ALAMAT_DOMISILI, PENDIDIKAN, DIBUAT_PADA, DIPERBARUI_PADA) VALUES (:candidate_name, :full_name, :email, :gender, :birth_place, TO_DATE(:birth_date, \'YYYY-MM-DD\'), :phone, :address, :education_level, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING KANDIDAT_ID INTO :candidate_id'
            );
            $candidate_id = 0;
            $insert_profile->bindValue( ':candidate_name', $full_name );
            $insert_profile->bindValue( ':full_name', $full_name );
            $insert_profile->bindValue( ':email', $email );
            $insert_profile->bindValue( ':gender', (string) $profile_data['gender'] );
            $insert_profile->bindValue( ':birth_place', (string) $profile_data['birth_place'] );
            $insert_profile->bindValue( ':birth_date', (string) $profile_data['birth_date'] );
            $insert_profile->bindValue( ':phone', (string) $profile_data['phone'] );
            $insert_profile->bindValue( ':address', (string) $profile_data['address'] );
            $insert_profile->bindValue( ':education_level', (string) $profile_details['education_level'] );
            $insert_profile->bindParam( ':candidate_id', $candidate_id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 40 );
            $insert_profile->execute();

            if ( 0 >= (int) $candidate_id ) {
                throw new RuntimeException( 'Applicant master profile ID was not returned.' );
            }

            $login = $connection->prepare(
                'INSERT INTO HRS_PROFILE_KANDIDAT_LOGIN (KANDIDAT_ID, EMAIL, KATA_SANDI, STATUS_AKUN, CREATED_AT, UPDATED_AT) VALUES (:candidate_id, :email, :password_hash, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING PROFILE_KANDIDAT_LOGIN INTO :login_id'
            );
            $login_id = 0;
            $login->bindValue( ':candidate_id', (int) $candidate_id, PDO::PARAM_INT );
            $login->bindValue( ':email', $email );
            $login->bindValue( ':password_hash', $password_hash );
            $login->bindValue( ':status', 'ACTIVE' );
            $login->bindParam( ':login_id', $login_id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 40 );
            $login->execute();

            if ( 0 >= (int) $login_id ) {
                throw new RuntimeException( 'Applicant login ID was not returned.' );
            }
            $this->save_applicant_profile_details( $connection, (string) $candidate_id, $profile_details );
            $connection->commit();
            return [
                'success' => true,
                'account' => [ 'id' => (string) $login_id, 'profile_id' => (string) $candidate_id, 'email' => $email, 'full_name' => $full_name ],
            ];
        } catch ( PDOException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            if ( $this->is_unique_violation( $exception ) ) {
                return [ 'success' => false, 'error' => 'email_exists' ];
            }
            if ( $this->is_missing_table( $exception ) ) {
                $this->log_error( 'Applicant account creation requires an unapplied Oracle migration', $exception );
                return [ 'success' => false, 'error' => 'schema_not_ready' ];
            }
            $this->log_error( 'Applicant account creation failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        } catch ( RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_error( 'Applicant account creation failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Verify an applicant's Oracle-backed credentials.
     *
     * @return array{success: bool, account?: array{id: string, profile_id: string, email: string, full_name: string}, error?: string}
     */
    public function authenticate_applicant_account( string $email, string $password ): array {
        $email = strtolower( trim( $email ) );
        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) || '' === $password ) {
            return [ 'success' => false, 'error' => 'invalid_credentials' ];
        }

        try {
            $statement = $this->get_connection()->prepare(
                'SELECT l.profile_kandidat_login AS "id", l.kandidat_id AS "profile_id", l.email AS "email", NVL(p.nama_lengkap, p.nama_kandidat) AS "full_name", l.kata_sandi AS "password_hash", l.status_akun AS "status" FROM HRS_PROFILE_KANDIDAT_LOGIN l JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = l.kandidat_id WHERE LOWER(TRIM(l.email)) = :email'
            );
            $statement->execute( [ 'email' => $email ] );
            $account = $statement->fetch( PDO::FETCH_ASSOC );

            if ( ! $account || ! password_verify( $password, (string) $account['password_hash'] ) ) {
                return [ 'success' => false, 'error' => 'invalid_credentials' ];
            }
            if ( 'ACTIVE' !== strtoupper( (string) $account['status'] ) ) {
                return [ 'success' => false, 'error' => 'inactive_account' ];
            }

            if ( password_needs_rehash( (string) $account['password_hash'], PASSWORD_BCRYPT ) ) {
                $password_hash = password_hash( $password, PASSWORD_BCRYPT );
                if ( false === $password_hash ) {
                    throw new RuntimeException( 'Applicant password could not be rehashed.' );
                }
                $update = $this->get_connection()->prepare(
                    'UPDATE HRS_PROFILE_KANDIDAT_LOGIN SET KATA_SANDI = :password_hash, UPDATED_AT = CURRENT_TIMESTAMP WHERE PROFILE_KANDIDAT_LOGIN = :login_id AND STATUS_AKUN = :status'
                );
                $update->execute( [ 'password_hash' => $password_hash, 'login_id' => $account['id'], 'status' => 'ACTIVE' ] );
            }

            return [
                'success' => true,
                'account' => [
                    'id' => (string) $account['id'],
                    'profile_id' => (string) $account['profile_id'],
                    'email' => (string) $account['email'],
                    'full_name' => (string) $account['full_name'],
                ],
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
     * Load an active candidate account and its actual Oracle profile.
     *
     * @return array{success: bool, account?: array<string, mixed>, error?: string}
     */
    public function get_applicant_account( string $login_id ): array {
        if ( ! ctype_digit( $login_id ) || '0' === $login_id ) {
            return [ 'success' => false, 'error' => 'invalid_account' ];
        }
        try {
            $statement = $this->get_connection()->prepare(
                'SELECT l.profile_kandidat_login AS "id", l.kandidat_id AS "profile_id", l.email AS "email", l.status_akun AS "status", NVL(p.nama_lengkap, p.nama_kandidat) AS "full_name", p.jenis_kelamin AS "gender", p.tempat_lahir AS "birth_place", TO_CHAR(p.tanggal_lahir, \'YYYY-MM-DD\') AS "birth_date", NVL(p.nomor_hp, p.nomor_telepon) AS "phone", p.alamat_domisili AS "address", p.kota AS "city", p.provinsi AS "province", p.pendidikan AS "education" FROM HRS_PROFILE_KANDIDAT_LOGIN l JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = l.kandidat_id WHERE l.profile_kandidat_login = :login_id'
            );
            $statement->execute( [ 'login_id' => $login_id ] );
            $account = $statement->fetch( PDO::FETCH_ASSOC );
            if ( ! is_array( $account ) ) {
                return [ 'success' => false, 'error' => 'account_not_found' ];
            }
            if ( 'ACTIVE' !== strtoupper( (string) $account['status'] ) ) {
                return [ 'success' => false, 'error' => 'inactive_account' ];
            }
            $account = array_map( static fn ( $value ): string => (string) $value, $account );
            $account['profile_details'] = [];
            $account['profile_details_error'] = false;
            try {
                $details_statement = $this->get_connection()->prepare(
                    'SELECT DATA_PROFIL FROM HRS_PROFIL_KANDIDAT_DETAIL WHERE KANDIDAT_ID = :profile_id'
                );
                $details_statement->execute( [ 'profile_id' => $account['profile_id'] ] );
                $account['profile_details'] = $this->decode_applicant_profile_details( $details_statement->fetchColumn() );
            } catch ( PDOException | RuntimeException $exception ) {
                $this->log_error( 'Applicant Oracle extended profile lookup failed', $exception );
                $account['profile_details_error'] = true;
            }
            return [ 'success' => true, 'account' => $account ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'Applicant Oracle account lookup failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Update candidate profile fields and their extended profile details.
     *
     * @param array<string, mixed> $profile
     * @return array{success: bool, error?: string}
     */
    public function update_applicant_profile( string $login_id, array $profile ): array {
        if ( ! ctype_digit( $login_id ) || '0' === $login_id || '' === trim( $profile['full_name'] ?? '' ) || 255 < strlen( $profile['full_name'] ?? '' ) || 50 < strlen( $profile['email'] ?? '' ) || ! filter_var( $profile['email'] ?? '', FILTER_VALIDATE_EMAIL ) || 100 < strlen( $profile['gender'] ?? '' ) || 255 < strlen( $profile['birth_place'] ?? '' ) || 19 < strlen( $profile['phone'] ?? '' ) || 100 < strlen( $profile['address'] ?? '' ) || ! is_array( $profile['profile_details'] ?? null ) ) {
            return [ 'success' => false, 'error' => 'invalid_profile_data' ];
        }
        $date = $profile['birth_date'] ?? '';
        if ( '' !== $date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return [ 'success' => false, 'error' => 'invalid_profile_data' ];
        }
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $lookup = $connection->prepare( 'SELECT kandidat_id FROM HRS_PROFILE_KANDIDAT_LOGIN WHERE profile_kandidat_login = :id AND status_akun = :status' );
            $lookup->execute( [ 'id' => $login_id, 'status' => 'ACTIVE' ] );
            $profile_id = $lookup->fetchColumn();
            if ( false === $profile_id ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'account_not_found' ];
            }
            $update_profile = $connection->prepare(
                'UPDATE HRS_PROFIL_KANDIDAT SET NAMA_KANDIDAT = :candidate_name, NAMA_LENGKAP = :full_name, EMAIL = :email, JENIS_KELAMIN = :gender, TEMPAT_LAHIR = :birth_place, TANGGAL_LAHIR = TO_DATE(:birth_date, \'YYYY-MM-DD\'), NOMOR_HP = :phone, ALAMAT_DOMISILI = :address, KOTA = :city, PROVINSI = :province, PENDIDIKAN = :education_level, DIPERBARUI_PADA = CURRENT_TIMESTAMP WHERE KANDIDAT_ID = :profile_id'
            );
            $update_profile->execute(
                [
                    'candidate_name' => trim( $profile['full_name'] ),
                    'full_name' => trim( $profile['full_name'] ),
                    'email' => strtolower( trim( $profile['email'] ) ),
                    'gender' => $profile['gender'],
                    'birth_place' => $profile['birth_place'],
                    'birth_date' => '' === $date ? null : $date,
                    'phone' => $profile['phone'],
                    'address' => $profile['address'],
                    'city' => (string) ( $profile['city'] ?? '' ),
                    'province' => (string) ( $profile['province'] ?? '' ),
                    'education_level' => $profile['profile_details']['education_level'],
                    'profile_id' => $profile_id,
                ]
            );
            $update_login = $connection->prepare(
                'UPDATE HRS_PROFILE_KANDIDAT_LOGIN SET EMAIL = :email, JENIS_KELAMIN = :gender, TEMPAT_LAHIR = :birth_place, TANGGAL_LAHIR = TO_DATE(:birth_date, \'YYYY-MM-DD\'), NOMOR_TELEPON = CASE WHEN :phone_null IS NULL THEN NULL ELSE TO_NUMBER(:phone_value) END, ALAMAT = :address, KOTA = :city, PROVINSI = :province, UPDATED_AT = CURRENT_TIMESTAMP WHERE PROFILE_KANDIDAT_LOGIN = :login_id'
            );
            $phone = '' === $profile['phone'] ? null : $profile['phone'];
            $update_login->execute(
                [
                    'email' => strtolower( trim( $profile['email'] ) ),
                    'gender' => $profile['gender'],
                    'birth_place' => $profile['birth_place'],
                    'birth_date' => '' === $date ? null : $date,
                    'phone_null' => $phone,
                    'phone_value' => $phone,
                    'address' => $profile['address'],
                    'city' => $profile['city'],
                    'province' => $profile['province'],
                    'login_id' => $login_id,
                ]
            );
            $this->save_applicant_profile_details( $connection, (string) $profile_id, $profile['profile_details'] );
            $connection->commit();
            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( isset( $connection ) && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            if ( $exception instanceof PDOException && $this->is_unique_violation( $exception ) ) {
                return [ 'success' => false, 'error' => 'email_exists' ];
            }
            $this->log_error( 'Applicant Oracle profile update failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Change the password for one active candidate account.
     *
     * @return array{success: bool, error?: string}
     */
    public function change_applicant_password( string $login_id, string $current_password, string $new_password ): array {
        if ( ! ctype_digit( $login_id ) || '0' === $login_id || '' === $current_password || 10 > strlen( $new_password ) ) {
            return [ 'success' => false, 'error' => 'invalid_password_data' ];
        }
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $lookup = $connection->prepare( 'SELECT KATA_SANDI FROM HRS_PROFILE_KANDIDAT_LOGIN WHERE PROFILE_KANDIDAT_LOGIN = :id AND STATUS_AKUN = :status FOR UPDATE' );
            $lookup->execute( [ 'id' => $login_id, 'status' => 'ACTIVE' ] );
            $hash = $lookup->fetchColumn();
            if ( false === $hash || ! password_verify( $current_password, (string) $hash ) ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'invalid_current_password' ];
            }
            $new_hash = password_hash( $new_password, PASSWORD_BCRYPT );
            if ( false === $new_hash ) {
                throw new RuntimeException( 'Applicant password could not be hashed.' );
            }
            $update = $connection->prepare( 'UPDATE HRS_PROFILE_KANDIDAT_LOGIN SET KATA_SANDI = :password_hash, UPDATED_AT = CURRENT_TIMESTAMP WHERE PROFILE_KANDIDAT_LOGIN = :id' );
            $update->execute( [ 'password_hash' => $new_hash, 'id' => $login_id ] );
            $connection->commit();
            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( isset( $connection ) && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_error( 'Applicant Oracle password update failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /** @return array<int, array<string, string>> */
    public function get_applicant_applications( string $login_id ): array {
        if ( ! ctype_digit( $login_id ) || '0' === $login_id ) {
            return [];
        }
        return $this->fetch_all(
            'SELECT l.lamaran_id AS "id", l.detail_lowongan_id AS "vacancy_id", d.judul_pekerjaan AS "title", d.lokasi_penempatan AS "location", d.nama_departement AS "department", TO_CHAR(NVL(l.created_at, l.tanggal_melamar), \'YYYY-MM-DD HH24:MI:SS\') AS "created_at", l.status_terkini AS "status", l.status_akhir AS "final_status", (SELECT MAX(r.tahap_seleksi) KEEP (DENSE_RANK LAST ORDER BY r.tanggal_status, r.riwayat_status_id) FROM HRS_RIWAYAT_STATUS_PELAMAR r WHERE r.lamaran_id = l.lamaran_id) AS "latest_stage" FROM HRS_LAMARAN l JOIN HRS_DETAIL_INFO_LOWONGAN d ON d.detail_lowongan_id = l.detail_lowongan_id WHERE l.profile_kandidat_id = :login_id ORDER BY NVL(l.created_at, l.tanggal_melamar) DESC, l.lamaran_id DESC',
            [ 'login_id' => $login_id ]
        );
    }

    /** @return array<string, string>|null */
    public function get_applicant_application( string $login_id, string $application_id ): ?array {
        if ( ! ctype_digit( $login_id ) || '0' === $login_id || ! ctype_digit( $application_id ) || '0' === $application_id ) {
            return null;
        }
        $rows = $this->fetch_all(
            'SELECT l.lamaran_id AS "id", l.detail_lowongan_id AS "vacancy_id", d.judul_pekerjaan AS "title", d.lokasi_penempatan AS "location", d.nama_departement AS "department", TO_CHAR(NVL(l.created_at, l.tanggal_melamar), \'YYYY-MM-DD HH24:MI:SS\') AS "created_at", l.status_terkini AS "status", l.status_akhir AS "final_status" FROM HRS_LAMARAN l JOIN HRS_DETAIL_INFO_LOWONGAN d ON d.detail_lowongan_id = l.detail_lowongan_id WHERE l.profile_kandidat_id = :login_id AND l.lamaran_id = :application_id',
            [ 'login_id' => $login_id, 'application_id' => $application_id ]
        );
        return $rows[0] ?? null;
    }

    /** @return array<int, array<string, string>> */
    public function get_applicant_application_timeline( string $login_id, string $application_id ): array {
        if ( null === $this->get_applicant_application( $login_id, $application_id ) ) {
            return [];
        }
        return $this->fetch_all(
            'SELECT r.tahap_seleksi AS "stage", r.status_seleksi AS "status", TO_CHAR(r.tanggal_status, \'YYYY-MM-DD HH24:MI:SS\') AS "changed_at", r.keterangan_pelamar AS "note" FROM HRS_RIWAYAT_STATUS_PELAMAR r JOIN HRS_LAMARAN l ON l.lamaran_id = r.lamaran_id WHERE l.profile_kandidat_id = :login_id AND l.lamaran_id = :application_id ORDER BY r.tanggal_status, r.riwayat_status_id',
            [ 'login_id' => $login_id, 'application_id' => $application_id ]
        );
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

            if ( ! password_verify( $password, (string) $row['password_hash'] ) ) {
                return [ 'success' => false, 'error' => 'invalid_credentials' ];
            }

            if ( 'ACTIVE' !== strtoupper( (string) $row['status'] ) ) {
                return [ 'success' => false, 'error' => 'inactive_account' ];
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
    public function create_hr_account( string $username, string $full_name, string $email, string $department, string $password ): array {
        $username = trim( $username );
        $full_name = trim( $full_name );
        $email = strtolower( trim( $email ) );
        if ( '' === $username || strlen( $username ) > 100 || '' === $full_name || strlen( $full_name ) > 100 || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) || strlen( $email ) > 254 || '' === $department || strlen( $department ) > 100 || strlen( $password ) < 8 ) {
            return [ 'success' => false, 'error' => 'invalid_account_data' ];
        }

        try {
            $password_hash = password_hash( $password, PASSWORD_DEFAULT );
            if ( false === $password_hash ) {
                throw new RuntimeException( 'HR password could not be hashed.' );
            }

            $statement = $this->get_connection()->prepare(
                'INSERT INTO hrs_role_akses (nama_pengguna, username, nama_lengkap, nama_departemen, email, kata_sandi, peran, status_akun) VALUES (:username, :username, :full_name, :department, :email, :password_hash, :role, :status)'
            );
            $statement->execute(
                [
                    'username'      => $username,
                    'full_name'     => $full_name,
                    'department'    => $department,
                    'email'         => $email,
                    'password_hash' => $password_hash,
                    'role'          => 'ADMIN_HR',
                    'status'        => 'ACTIVE',
                ]
            );
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
     * Delete one HR account only.
     *
     * @return array{success: bool, error?: string}
     */
    public function delete_hr_account( int $account_id ): array {
        try {
            $statement = $this->get_connection()->prepare( 'DELETE FROM hrs_role_akses WHERE role_akses_id = :id AND peran = :role' );
            $statement->execute( [ 'id' => $account_id, 'role' => 'ADMIN_HR' ] );
            return 1 === $statement->rowCount() ? [ 'success' => true ] : [ 'success' => false, 'error' => 'account_not_found' ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'HR Oracle account deletion failed', $exception );
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
     * @return array{success: bool, status?: string, role?: string, department_id?: string, error?: string}
     */
    public function get_portal_account_status( string $account_type, string $account_id ): array {
        $role = 'hr' === $account_type ? 'ADMIN_HR' : ( 'user_department' === $account_type ? 'KEPALA_DEPARTEMEN' : '' );
        if ( '' === $role || ! ctype_digit( $account_id ) || '0' === $account_id ) {
            return [ 'success' => false, 'error' => 'invalid_account' ];
        }

        try {
            $statement = $this->get_connection()->prepare(
                'SELECT status_akun, peran, departemen_id FROM hrs_role_akses WHERE role_akses_id = :account_id AND peran = :role'
            );
            $statement->execute( [ 'account_id' => $account_id, 'role' => $role ] );
            $account = $statement->fetch( PDO::FETCH_ASSOC );
            if ( ! is_array( $account ) ) {
                return [ 'success' => false, 'error' => 'account_not_found' ];
            }

            if ( 'user_department' === $account_type && '' === (string) $account['DEPARTEMEN_ID'] ) {
                return [ 'success' => false, 'error' => 'invalid_account' ];
            }

            return [
                'success'       => true,
                'status'        => strtoupper( (string) $account['STATUS_AKUN'] ),
                'role'          => strtoupper( (string) $account['PERAN'] ),
                'department_id' => (string) ( $account['DEPARTEMEN_ID'] ?? '' ),
            ];
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
            return [ 'success' => false, 'error' => 'Data form tidak valid. Muat ulang halaman lalu coba lagi.' ];        } catch ( PDOException | RuntimeException $exception ) {
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
            $candidate_login_id = (string) ( $submission['candidate_login_id'] ?? '' );
            $vacancy_id = (string) ( $submission['vacancy_source_id'] ?? '' );
            if ( ! ctype_digit( $candidate_login_id ) || '0' === $candidate_login_id || ! ctype_digit( $vacancy_id ) || '0' === $vacancy_id ) {
                throw new InvalidArgumentException( 'Candidate or Oracle vacancy identifier is invalid.' );
            }
            $candidate_check = $connection->prepare( 'SELECT PROFILE_KANDIDAT_LOGIN, KANDIDAT_ID FROM HRS_PROFILE_KANDIDAT_LOGIN WHERE PROFILE_KANDIDAT_LOGIN = :id AND (STATUS_AKUN = :status OR STATUS_AKUN IS NULL) FOR UPDATE' );
            $candidate_check->execute( [ 'id' => $candidate_login_id, 'status' => 'ACTIVE' ] );
            $candidate_row = $candidate_check->fetch( PDO::FETCH_ASSOC );
            if ( ! $candidate_row ) {
                throw new InvalidArgumentException( 'Applicant account is not active.' );
            }
            $profile_id = (string) ( $candidate_row['KANDIDAT_ID'] ?? '' );

            $duplicate_check = $connection->prepare(
                'SELECT COUNT(*) FROM HRS_LAMARAN WHERE PROFILE_KANDIDAT_ID = :candidate_id AND DETAIL_LOWONGAN_ID = :vacancy_id'
            );
            $duplicate_check->execute( [ 'candidate_id' => $candidate_login_id, 'vacancy_id' => $vacancy_id ] );
            if ( 0 < (int) $duplicate_check->fetchColumn() ) {
                throw new InvalidArgumentException( 'Applicant has already applied to this vacancy.' );
            }

            // Ensure vacancy exists in Oracle HRS_DETAIL_INFO_LOWONGAN
            $vacancy_check = $connection->prepare( 'SELECT DETAIL_LOWONGAN_ID FROM HRS_DETAIL_INFO_LOWONGAN WHERE DETAIL_LOWONGAN_ID = :vacancy_id' );
            $vacancy_check->execute( [ 'vacancy_id' => $vacancy_id ] );
            $has_oracle_vacancy = false !== $vacancy_check->fetchColumn();

            if ( ! $has_oracle_vacancy ) {
                $wp_title = (string) ( $submission['vacancy_title'] ?? 'Lowongan' );
                $wp_dealer = '';
                $wp_loc = '';
                $wp_dept = '';
                $wp_type = '';
                $wp_desc = '';
                $wp_deadline = null;
                if ( function_exists( 'get_post' ) ) {
                    $wp_post = get_post( (int) $vacancy_id );
                    if ( $wp_post ) {
                        $wp_title = get_the_title( $wp_post ) ?: $wp_title;
                        $wp_desc = $wp_post->post_content ?: '';
                        $wp_dealer = (string) get_post_meta( $wp_post->ID, '_daw_dealer', true );
                        $wp_loc = (string) get_post_meta( $wp_post->ID, '_daw_location', true );
                        $wp_dept = (string) get_post_meta( $wp_post->ID, '_daw_region', true );
                        $wp_type = (string) get_post_meta( $wp_post->ID, '_daw_type', true );
                        $wp_deadline = (string) get_post_meta( $wp_post->ID, '_daw_deadline', true );
                    }
                }

                $kolom_check = $connection->prepare( 'SELECT KOLOM_PENDAFTARAN_ID FROM HRS_KOLOM_LANDING_PENDAFTARAN WHERE KOLOM_PENDAFTARAN_ID = :kolom_id' );
                $kolom_check->execute( [ 'kolom_id' => $vacancy_id ] );
                if ( false === $kolom_check->fetchColumn() ) {
                    $ins_kolom = $connection->prepare(
                        'INSERT INTO HRS_KOLOM_LANDING_PENDAFTARAN (KOLOM_PENDAFTARAN_ID, JUDUL_PEKERJAAN, NAMA_DEALER, STATUS_KOLOM, DIPOSTING_PADA) VALUES (:kolom_id, :title, :dealer, 1, CURRENT_DATE)'
                    );
                    $ins_kolom->execute( [
                        'kolom_id' => $vacancy_id,
                        'title'    => substr( $wp_title, 0, 1000 ),
                        'dealer'   => substr( $wp_dealer, 0, 400 ),
                    ] );
                }

                $deadline_val = ( ! empty( $wp_deadline ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $wp_deadline ) ) ? $wp_deadline : null;
                $ins_detail = $connection->prepare(
                    'INSERT INTO HRS_DETAIL_INFO_LOWONGAN (DETAIL_LOWONGAN_ID, KOLOM_PENDAFTARAN_ID, JUDUL_PEKERJAAN, DESKRIPSI_PEKERJAAN, LOKASI_PENEMPATAN, NAMA_DEPARTEMENT, KATEGORI_KJ, BATAS_LAMARAN, REQUEST_AT) VALUES (:detail_id, :kolom_id, :title, :description, :location, :department, :job_type, CASE WHEN :deadline IS NULL THEN NULL ELSE TO_DATE(:deadline_val, \'YYYY-MM-DD\') END, CURRENT_TIMESTAMP)'
                );
                $ins_detail->execute( [
                    'detail_id'    => $vacancy_id,
                    'kolom_id'     => $vacancy_id,
                    'title'        => substr( $wp_title, 0, 1000 ),
                    'description'  => $wp_desc,
                    'location'     => substr( $wp_loc ?: $wp_dealer, 0, 1000 ),
                    'department'   => substr( $wp_dept, 0, 1000 ),
                    'job_type'     => substr( $wp_type, 0, 1000 ),
                    'deadline'     => $deadline_val,
                    'deadline_val' => $deadline_val,
                ] );
            }

            // Sync answers and uploaded files into applicant's DATA_PROFIL in Oracle
            if ( '' !== $profile_id ) {
                $details_statement = $connection->prepare( 'SELECT DATA_PROFIL FROM HRS_PROFIL_KANDIDAT_DETAIL WHERE KANDIDAT_ID = :profile_id' );
                $details_statement->execute( [ 'profile_id' => $profile_id ] );
                $existing_raw = $details_statement->fetchColumn();
                $profile_details = $this->decode_applicant_profile_details( $existing_raw );

                if ( ! isset( $profile_details['application_answers'] ) || ! is_array( $profile_details['application_answers'] ) ) {
                    $profile_details['application_answers'] = [];
                }
                if ( ! isset( $profile_details['applications'] ) || ! is_array( $profile_details['applications'] ) ) {
                    $profile_details['applications'] = [];
                }

                $current_app_answers = [];
                foreach ( $submission['answers'] as $answer ) {
                    $k = (string) $answer['field_key'];
                    $v = $answer['value'] ?? ( isset( $answer['file']['name'] ) ? $answer['file']['name'] : '' );
                    $current_app_answers[ $k ] = [
                        'key'   => $k,
                        'title' => (string) $answer['field_title'],
                        'value' => $v,
                        'type'  => (string) $answer['field_type'],
                    ];
                    $profile_details['application_answers'][ $k ] = $v;
                }

                $profile_details['applications'][ $vacancy_id ] = [
                    'vacancy_id'    => $vacancy_id,
                    'vacancy_title' => (string) ( $submission['vacancy_title'] ?? '' ),
                    'applied_at'    => function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' ),
                    'answers'       => $current_app_answers,
                ];

                // Process uploaded files into documents if applicable
                if ( class_exists( 'Recruitment_Applicant_Profile' ) ) {
                    $profile_service = new Recruitment_Applicant_Profile();
                    foreach ( $submission['answers'] as $ans ) {
                        if ( ! empty( $ans['file'] ) && is_array( $ans['file'] ) && ! empty( $ans['file']['tmp_name'] ) ) {
                            $doc_key = [
                                'foto_terkini' => 'profile_photo',
                                'cv_resume'    => 'resume',
                                'portofolio'   => 'portfolio',
                            ][ $ans['field_key'] ] ?? $ans['field_key'];

                            $stored_doc = $profile_service->store_uploaded_document( $ans['file'], $doc_key );
                            if ( $stored_doc ) {
                                if ( ! isset( $profile_details['documents'] ) || ! is_array( $profile_details['documents'] ) ) {
                                    $profile_details['documents'] = [];
                                }
                                $profile_details['documents'][ $doc_key ] = $stored_doc;
                            }
                        }
                    }
                }

                $this->save_applicant_profile_details( $connection, (string) $profile_id, $profile_details );
            }

            // Check if RECRUIT_FORM_SUBMISSIONS table exists in the schema
            $table_check = $connection->prepare( "SELECT COUNT(*) FROM USER_TABLES WHERE TABLE_NAME = 'RECRUIT_FORM_SUBMISSIONS'" );
            $table_check->execute();
            if ( 0 < (int) $table_check->fetchColumn() ) {
                $submission_token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0x0fff ) | 0x4000, mt_rand( 0, 0x3fff ) | 0x8000, mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ) );
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

                if ( '' !== $submission_id ) {
                    $answer_statement = $connection->prepare(
                        'INSERT INTO RECRUIT_FORM_ANSWERS (SUBMISSION_ID, FIELD_ID, FIELD_KEY, FIELD_TITLE, FIELD_TYPE, ANSWER_VALUE, FILE_NAME, FILE_MIME_TYPE, FILE_SIZE, FILE_CONTENT) VALUES (:submission_id, :field_id, :field_key, :field_title, :field_type, :answer_value, :file_name, :file_mime_type, :file_size, :file_content)'
                    );
                    foreach ( $submission['answers'] as $answer ) {
                        $file = $answer['file'] ?? null;
                        $file_stream = null;
                        if ( is_array( $file ) && ! empty( $file['tmp_name'] ) && is_readable( $file['tmp_name'] ) ) {
                            $file_stream = fopen( $file['tmp_name'], 'rb' );
                        }

                        $answer_statement->bindValue( ':submission_id', $submission_id );
                        $answer_statement->bindValue( ':field_id', (string) ( $answer['field_id'] ?? '0' ) );
                        $answer_statement->bindValue( ':field_key', $answer['field_key'] );
                        $answer_statement->bindValue( ':field_title', $answer['field_title'] );
                        $answer_statement->bindValue( ':field_type', $answer['field_type'] );
                        $answer_statement->bindValue(
                            ':answer_value',
                            is_string( $answer['value'] ?? null ) ? $answer['value'] : null,
                            is_string( $answer['value'] ?? null ) ? PDO::PARAM_STR : PDO::PARAM_NULL
                        );
                        $answer_statement->bindValue( ':file_name', is_array( $file ) ? ( $file['name'] ?? null ) : null );
                        $answer_statement->bindValue( ':file_mime_type', is_array( $file ) ? ( $file['mime_type'] ?? null ) : null );
                        $answer_statement->bindValue( ':file_size', is_array( $file ) ? ( $file['size'] ?? null ) : null );
                        $answer_statement->bindValue(
                            ':file_content',
                            $file_stream,
                            is_resource( $file_stream ) ? PDO::PARAM_LOB : PDO::PARAM_NULL
                        );
                        try {
                            $answer_statement->execute();
                        } finally {
                            if ( is_resource( $file_stream ) ) {
                                fclose( $file_stream );
                            }
                        }
                    }
                }
            }

            // Insert application into HRS_LAMARAN
            $application = $connection->prepare(
                'INSERT INTO HRS_LAMARAN (PROFILE_KANDIDAT_ID, DETAIL_LOWONGAN_ID, TANGGAL_MELAMAR, STATUS_TERKINI, STATUS_AKHIR, CREATED_AT, UPDATED_AT) VALUES (:candidate_id, :vacancy_id, CURRENT_TIMESTAMP, :initial_status, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
            );
            $application->execute( [
                'candidate_id'   => $candidate_login_id,
                'vacancy_id'     => $vacancy_id,
                'initial_status' => 'SUBMITTED',
            ] );
            if ( 1 !== $application->rowCount() ) {
                throw new InvalidArgumentException( 'The selected Oracle vacancy is no longer available.' );
            }

            $application_id_query = $connection->prepare(
                'SELECT lamaran_id FROM HRS_LAMARAN WHERE profile_kandidat_id = :candidate_id AND detail_lowongan_id = :vacancy_id ORDER BY lamaran_id DESC FETCH FIRST 1 ROW ONLY'
            );
            $application_id_query->execute( [ 'candidate_id' => $candidate_login_id, 'vacancy_id' => $vacancy_id ] );
            $application_id = $application_id_query->fetchColumn();
            if ( false === $application_id ) {
                throw new RuntimeException( 'The new application ID could not be retrieved.' );
            }

            $history = $connection->prepare(
                'INSERT INTO HRS_RIWAYAT_STATUS_PELAMAR (LAMARAN_ID, TAHAP_SELEKSI, STATUS_SELEKSI, TANGGAL_STATUS, KETERANGAN_PELAMAR) VALUES (:application_id, :stage, :status, CURRENT_TIMESTAMP, :note)'
            );
            $history->execute(
                [
                    'application_id' => $application_id,
                    'stage'          => 'ADMINISTRASI',
                    'status'         => 'SUBMITTED',
                    'note'           => 'Lamaran berhasil diterima dan menunggu pemeriksaan HR.',
                ]
            );

            $connection->commit();
            return [ 'success' => true, 'application_id' => (string) $application_id ];
        } catch ( InvalidArgumentException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $message = 'Lowongan tidak tersedia atau akun pelamar tidak aktif.';
            if ( 'Applicant has already applied to this vacancy.' === $exception->getMessage() ) {
                $message = 'Anda sudah pernah melamar lowongan ini.';
            }
            return [ 'success' => false, 'error' => $message ];
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
            'SELECT d.detail_lowongan_id AS "id", d.judul_pekerjaan AS "title", d.lokasi_penempatan AS "location", k.nama_dealer AS "dealer", d.kategori_kj AS "job_type", d.deskripsi_pekerjaan AS "description", d.pendidikan_minimum AS "education", TO_CHAR(d.batas_lamaran, \'YYYY-MM-DD\') AS "deadline", d.nama_departement AS "department", TO_CHAR(d.request_at, \'YYYY-MM-DD\') AS "posted_at" FROM HRS_DETAIL_INFO_LOWONGAN d JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.kolom_pendaftaran_id = d.kolom_pendaftaran_id WHERE k.status_kolom = :status AND (d.batas_lamaran IS NULL OR d.batas_lamaran >= CURRENT_DATE) ORDER BY d.detail_lowongan_id DESC',
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
            'SELECT l.lamaran_id AS "id",
                   l.detail_lowongan_id AS "vacancy_id",
                   l.profile_kandidat_id AS "applicant_id",
                   l.status_terkini AS "status",
                   l.status_akhir AS "final_status",
                   NVL(l.created_at, l.tanggal_melamar) AS "created_at",
                   NVL(d.judul_pekerjaan, k.judul_pekerjaan) AS "title",
                   NVL(d.lokasi_penempatan, k.nama_dealer) AS "location",
                   k.nama_dealer AS "dealer",
                   d.nama_departement AS "department",
                   NVL(p.nama_lengkap, p.nama_kandidat) AS "applicant_name",
                   NVL(p.email, pkl.email) AS "applicant_email",
                   NVL(p.nomor_hp, p.nomor_telepon) AS "applicant_phone",
                   p.provinsi AS "province",
                   (SELECT MAX(r.tahap_seleksi) KEEP (DENSE_RANK LAST ORDER BY r.tanggal_status, r.riwayat_status_id) FROM HRS_RIWAYAT_STATUS_PELAMAR r WHERE r.lamaran_id = l.lamaran_id) AS "stage"
            FROM HRS_LAMARAN l
            LEFT JOIN HRS_DETAIL_INFO_LOWONGAN d ON d.detail_lowongan_id = l.detail_lowongan_id
            LEFT JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.kolom_pendaftaran_id = d.kolom_pendaftaran_id OR k.kolom_pendaftaran_id = l.detail_lowongan_id
            LEFT JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON pkl.profile_kandidat_login = l.profile_kandidat_id
            LEFT JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = pkl.kandidat_id
            ORDER BY NVL(l.created_at, l.tanggal_melamar) DESC, l.lamaran_id DESC'
        );
    }

    /**
     * Load the candidate profile and extended form answers for an HR detail view.
     *
     * @return array<string, mixed>|null
     */
    public function get_admin_applicant( string $login_id ): ?array {
        if ( ! ctype_digit( $login_id ) || '0' === $login_id ) {
            return null;
        }
        $rows = $this->fetch_all(
            'SELECT l.profile_kandidat_login AS "id", l.kandidat_id AS "profile_id", NVL(p.nama_lengkap, p.nama_kandidat) AS "name", NVL(p.email, l.email) AS "email", NVL(p.nomor_hp, p.nomor_telepon) AS "phone", p.jenis_kelamin AS "gender", p.tempat_lahir AS "birth_place", TO_CHAR(p.tanggal_lahir, \'YYYY-MM-DD\') AS "birth_date", p.alamat_domisili AS "address", p.kota AS "city", p.provinsi AS "province", p.pendidikan AS "education" FROM HRS_PROFILE_KANDIDAT_LOGIN l JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = l.kandidat_id WHERE l.profile_kandidat_login = :login_id',
            [ 'login_id' => $login_id ]
        );
        if ( empty( $rows[0] ) ) {
            return null;
        }
        $candidate = $rows[0];
        $details = $this->fetch_all(
            'SELECT DATA_PROFIL AS "profile_details" FROM HRS_PROFIL_KANDIDAT_DETAIL WHERE KANDIDAT_ID = :profile_id',
            [ 'profile_id' => $candidate['profile_id'] ]
        );
        $candidate['profile_details'] = ! empty( $details[0]['profile_details'] )
            ? $this->decode_applicant_profile_details( $details[0]['profile_details'] )
            : [];
        return $candidate;
    }

    /** @return array<int, array<string, string>> */
    public function get_admin_application_answers( string $application_id ): array {
        if ( ! ctype_digit( $application_id ) || '0' === $application_id ) {
            return [];
        }

        // Try RECRUIT_FORM_ANSWERS first if table exists and has answers
        $answers = $this->fetch_all(
            'SELECT a.FIELD_KEY AS "field_key", a.FIELD_TITLE AS "field_title", a.ANSWER_VALUE AS "answer_value", a.FILE_NAME AS "file_name" FROM RECRUIT_FORM_ANSWERS a JOIN RECRUIT_FORM_SUBMISSIONS s ON s.SUBMISSION_ID = a.SUBMISSION_ID JOIN HRS_LAMARAN l ON l.LAMARAN_ID = :application_id AND s.SOURCE_VACANCY_ID = l.DETAIL_LOWONGAN_ID JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON pkl.PROFILE_KANDIDAT_LOGIN = l.PROFILE_KANDIDAT_ID WHERE LOWER(TRIM(s.APPLICANT_EMAIL)) = LOWER(TRIM(pkl.EMAIL)) ORDER BY a.SUBMISSION_ID DESC, a.FIELD_KEY',
            [ 'application_id' => $application_id ]
        );
        if ( ! empty( $answers ) ) {
            return $answers;
        }

        // Fallback to DATA_PROFIL in HRS_PROFIL_KANDIDAT_DETAIL
        $app_rows = $this->fetch_all(
            'SELECT l.DETAIL_LOWONGAN_ID AS "vacancy_id", pkl.KANDIDAT_ID AS "profile_id" FROM HRS_LAMARAN l JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON pkl.PROFILE_KANDIDAT_LOGIN = l.PROFILE_KANDIDAT_ID WHERE l.LAMARAN_ID = :application_id',
            [ 'application_id' => $application_id ]
        );
        if ( empty( $app_rows[0]['profile_id'] ) ) {
            return [];
        }
        $profile_id = (string) $app_rows[0]['profile_id'];
        $vacancy_id = (string) ( $app_rows[0]['vacancy_id'] ?? '' );

        $details_rows = $this->fetch_all(
            'SELECT DATA_PROFIL AS "profile_details" FROM HRS_PROFIL_KANDIDAT_DETAIL WHERE KANDIDAT_ID = :profile_id',
            [ 'profile_id' => $profile_id ]
        );
        if ( empty( $details_rows[0]['profile_details'] ) ) {
            return [];
        }
        $profile_details = $this->decode_applicant_profile_details( $details_rows[0]['profile_details'] );

        $app_answers = $profile_details['applications'][ $vacancy_id ]['answers'] ?? null;
        if ( is_array( $app_answers ) && ! empty( $app_answers ) ) {
            $formatted = [];
            foreach ( $app_answers as $ans ) {
                $formatted[] = [
                    'field_key'    => (string) ( $ans['key'] ?? '' ),
                    'field_title'  => (string) ( $ans['title'] ?? $ans['key'] ?? '' ),
                    'answer_value' => (string) ( $ans['value'] ?? '' ),
                    'file_name'    => ( 'file' === ( $ans['type'] ?? '' ) ) ? (string) ( $ans['value'] ?? '' ) : null,
                ];
            }
            return $formatted;
        }

        if ( ! empty( $profile_details['application_answers'] ) && is_array( $profile_details['application_answers'] ) ) {
            $formatted = [];
            foreach ( $profile_details['application_answers'] as $k => $v ) {
                $formatted[] = [
                    'field_key'    => (string) $k,
                    'field_title'  => ucwords( str_replace( '_', ' ', (string) $k ) ),
                    'answer_value' => is_scalar( $v ) ? (string) $v : ( is_array( $v ) ? json_encode( $v ) : '' ),
                    'file_name'    => null,
                ];
            }
            return $formatted;
        }

        return [];
    }

    /** @return array<int, array<string, string>> */
    public function get_selection_history( string $application_id ): array {
        if ( ! ctype_digit( $application_id ) || '0' === $application_id ) {
            return [];
        }
        return $this->fetch_all(
            'SELECT RIWAYAT_STATUS_ID AS "id", TAHAP_SELEKSI AS "stage", STATUS_SELEKSI AS "status", TO_CHAR(TANGGAL_STATUS, \'YYYY-MM-DD HH24:MI:SS\') AS "date", KETERANGAN_PELAMAR AS "note" FROM HRS_RIWAYAT_STATUS_PELAMAR WHERE LAMARAN_ID = :app_id ORDER BY TANGGAL_STATUS DESC, RIWAYAT_STATUS_ID DESC',
            [ 'app_id' => $application_id ]
        );
    }

    /**
     * Update an application and append the event consumed by the candidate tracker.
     *
     * @return array{success: bool, error?: string}
     */
    public function update_application_status( string $application_id, string $status, string $stage, string $note ): array {
        if ( ! ctype_digit( $application_id ) || '0' === $application_id || '' === trim( $status ) || '' === trim( $stage ) ) {
            return [ 'success' => false, 'error' => 'Status lamaran tidak valid.' ];
        }
        $normalized = strtoupper( trim( $status ) );
        if ( in_array( $normalized, [ 'ACCEPTED', 'LOLOS' ], true ) ) {
            $normalized = 'DITERIMA';
        } elseif ( in_array( $normalized, [ 'REJECTED', 'TIDAK_LOLOS' ], true ) ) {
            $normalized = 'DITOLAK';
        }
        $allowed_statuses = [ 'SUBMITTED', 'SCREENING', 'PSIKOTES', 'INTERVIEW_HR', 'INTERVIEW_USER', 'DITERIMA', 'DITOLAK' ];
        if ( ! in_array( $normalized, $allowed_statuses, true ) ) {
            return [ 'success' => false, 'error' => 'Status lamaran tidak dikenal.' ];
        }
        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $update = $connection->prepare(
                'UPDATE HRS_LAMARAN SET STATUS_TERKINI = :status_value, STATUS_AKHIR = CASE WHEN :is_final = 1 THEN :final_status ELSE STATUS_AKHIR END, UPDATED_AT = CURRENT_TIMESTAMP WHERE LAMARAN_ID = :application_id'
            );
            $is_final = in_array( $normalized, [ 'DITERIMA', 'DITOLAK' ], true ) ? 1 : 0;
            $update->execute( [ 'status_value' => $normalized, 'is_final' => $is_final, 'final_status' => $normalized, 'application_id' => $application_id ] );
            if ( 1 !== $update->rowCount() ) {
                throw new InvalidArgumentException( 'Application not found.' );
            }
            $history = $connection->prepare(
                'INSERT INTO HRS_RIWAYAT_STATUS_PELAMAR (LAMARAN_ID, TAHAP_SELEKSI, STATUS_SELEKSI, TANGGAL_STATUS, KETERANGAN_PELAMAR) VALUES (:application_id, :stage, :status, CURRENT_TIMESTAMP, :note)'
            );
            $history->execute(
                [
                    'application_id' => $application_id,
                    'stage' => strtoupper( function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $stage ) : trim( strip_tags( $stage ) ) ),
                    'status' => $normalized,
                    'note' => function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $note ) : trim( strip_tags( $note ) ),
                ]
            );
            $connection->commit();
            return [ 'success' => true ];
        } catch ( InvalidArgumentException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            return [ 'success' => false, 'error' => 'Lamaran tidak ditemukan.' ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'Application status update failed', $exception );
            return [ 'success' => false, 'error' => 'Status lamaran gagal disimpan.' ];
        }
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
            $rows = $statement->fetchAll( PDO::FETCH_ASSOC );
            foreach ( $rows as &$row ) {
                foreach ( $row as &$value ) {
                    if ( is_resource( $value ) || ( is_object( $value ) && method_exists( $value, 'load' ) ) ) {
                        $value = $this->oracle_lob_to_string( $value );
                    }
                }
                unset( $value );
            }
            unset( $row );
            return $rows;
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
     * @param array<string, mixed> $details
     */
    private function save_applicant_profile_details( PDO $connection, string $profile_id, array $details ): void {
        $json = json_encode( $details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( false === $json ) {
            throw new RuntimeException( 'Applicant profile details could not be encoded.' );
        }

        $characters = preg_split( '//u', $json, -1, PREG_SPLIT_NO_EMPTY );
        if ( false === $characters ) {
            throw new RuntimeException( 'Applicant profile details could not be split for Oracle.' );
        }

        $chunks = [];
        $chunk = '';
        foreach ( $characters as $character ) {
            if ( '' !== $chunk && 1000 < strlen( $chunk ) + strlen( $character ) ) {
                $chunks[] = $chunk;
                $chunk = '';
            }
            $chunk .= $character;
        }
        if ( '' !== $chunk ) {
            $chunks[] = $chunk;
        }

        $profile_data_expression = implode(
            ' || ',
            array_map(
                static fn ( int $index ): string => 'TO_CLOB(:profile_data_' . $index . ')',
                array_keys( $chunks )
            )
        );
        $lookup = $connection->prepare( 'SELECT COUNT(*) FROM HRS_PROFIL_KANDIDAT_DETAIL WHERE KANDIDAT_ID = :profile_id' );
        $lookup->execute( [ 'profile_id' => $profile_id ] );
        $exists = 0 < (int) $lookup->fetchColumn();
        $statement = $exists
            ? $connection->prepare( 'UPDATE HRS_PROFIL_KANDIDAT_DETAIL SET DATA_PROFIL = ' . $profile_data_expression . ', DIPERBARUI_PADA = CURRENT_TIMESTAMP WHERE KANDIDAT_ID = :profile_id' )
            : $connection->prepare( 'INSERT INTO HRS_PROFIL_KANDIDAT_DETAIL (KANDIDAT_ID, DATA_PROFIL, DIBUAT_PADA, DIPERBARUI_PADA) VALUES (:profile_id, ' . $profile_data_expression . ', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)' );
        $statement->bindValue( ':profile_id', (int) $profile_id, PDO::PARAM_INT );
        foreach ( $chunks as $index => $profile_data_chunk ) {
            $statement->bindValue( ':profile_data_' . $index, $profile_data_chunk, PDO::PARAM_STR );
        }
        $statement->execute();
    }

    /** @return array<string, mixed> */
    private function decode_applicant_profile_details( $value ): array {
        $json = $this->oracle_lob_to_string( $value );
        if ( '' === $json ) {
            return [];
        }
        $details = json_decode( $json, true );
        if ( ! is_array( $details ) ) {
            throw new RuntimeException( 'Applicant profile details contain invalid JSON.' );
        }
        return $details;
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

    private function is_missing_table( PDOException $exception ): bool {
        return false !== stripos( $exception->getMessage(), 'ORA-00942' );
    }

}