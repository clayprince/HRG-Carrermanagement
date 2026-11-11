<?php

require_once __DIR__ . '/connection/class-oracle-connection.php';

/**
 * Oracle data gateway for the recruitment domain.
 *
 * Pages use this gateway; Oracle configuration and PDO creation are handled
 * by Recruitment_Oracle_Connection.
 */
class Recruitment_Database {

    private const MAX_PSYCHOTEST_IMPORT_QUESTIONS = 200;
    private const WORDPRESS_VACANCY_ID_OFFSET = 1000000000000;

    private Recruitment_Oracle_Connection $oracle;
    private ?string $last_error = null;
    private ?bool $application_token_column_exists = null;

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
            if ( $this->is_missing_schema_object( $exception ) ) {
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
     * Change an applicant login email and keep its linked profile email in sync.
     *
     * @return array{success: bool, error?: string}
     */
    public function update_applicant_account_email( string $full_name, string $old_email, string $new_email ): array {
        $full_name = trim( $full_name );
        $old_email = strtolower( trim( $old_email ) );
        $new_email = strtolower( trim( $new_email ) );
        if ( '' === $full_name || 255 < strlen( $full_name ) || ! filter_var( $old_email, FILTER_VALIDATE_EMAIL ) || 50 < strlen( $new_email ) || ! filter_var( $new_email, FILTER_VALIDATE_EMAIL ) || $old_email === $new_email ) {
            return [ 'success' => false, 'error' => 'invalid_email_change' ];
        }

        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $lookup = $connection->prepare(
                'SELECT l.PROFILE_KANDIDAT_LOGIN AS "login_id", l.KANDIDAT_ID AS "profile_id" FROM HRS_PROFILE_KANDIDAT_LOGIN l JOIN HRS_PROFIL_KANDIDAT p ON p.KANDIDAT_ID = l.KANDIDAT_ID WHERE LOWER(TRIM(l.EMAIL)) = :old_email AND UPPER(TRIM(NVL(p.NAMA_LENGKAP, p.NAMA_KANDIDAT))) = :full_name FOR UPDATE OF l.EMAIL, p.EMAIL'
            );
            $lookup->execute(
                [
                    'old_email' => $old_email,
                    'full_name' => strtoupper( $full_name ),
                ]
            );
            $accounts = $lookup->fetchAll( PDO::FETCH_ASSOC );
            if ( 0 === count( $accounts ) ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'applicant_not_found' ];
            }
            if ( 1 !== count( $accounts ) ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'applicant_ambiguous' ];
            }

            $login_id = (string) $accounts[0]['login_id'];
            $profile_id = (string) $accounts[0]['profile_id'];
            $duplicate = $connection->prepare(
                'SELECT COUNT(*) FROM HRS_PROFILE_KANDIDAT_LOGIN WHERE LOWER(TRIM(EMAIL)) = :new_email AND PROFILE_KANDIDAT_LOGIN <> :login_id'
            );
            $duplicate->execute(
                [
                    'new_email' => $new_email,
                    'login_id' => $login_id,
                ]
            );
            if ( 0 < (int) $duplicate->fetchColumn() ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'email_exists' ];
            }

            $update_login = $connection->prepare(
                'UPDATE HRS_PROFILE_KANDIDAT_LOGIN SET EMAIL = :email, UPDATED_AT = CURRENT_TIMESTAMP WHERE PROFILE_KANDIDAT_LOGIN = :login_id AND LOWER(TRIM(EMAIL)) = :old_email'
            );
            $update_login->execute(
                [
                    'email' => $new_email,
                    'login_id' => $login_id,
                    'old_email' => $old_email,
                ]
            );
            if ( 1 !== $update_login->rowCount() ) {
                throw new RuntimeException( 'Applicant login email changed while the update was in progress.' );
            }

            $update_profile = $connection->prepare(
                'UPDATE HRS_PROFIL_KANDIDAT SET EMAIL = :email, DIPERBARUI_PADA = CURRENT_TIMESTAMP WHERE KANDIDAT_ID = :profile_id'
            );
            $update_profile->execute(
                [
                    'email' => $new_email,
                    'profile_id' => $profile_id,
                ]
            );
            if ( 1 !== $update_profile->rowCount() ) {
                throw new RuntimeException( 'Applicant profile email could not be updated.' );
            }

            $connection->commit();
            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            if ( $exception instanceof PDOException && $this->is_unique_violation( $exception ) ) {
                return [ 'success' => false, 'error' => 'email_exists' ];
            }
            $this->log_error( 'Applicant Oracle email update failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Directly update a candidate's email address by candidate_id in both profile and login tables.
     *
     * @param int $candidate_id
     * @param string $new_email
     * @return array{success: bool, error?: string}
     */
    public function update_candidate_email( int $candidate_id, string $new_email ): array {
        $new_email = strtolower( trim( $new_email ) );
        if ( $candidate_id <= 0 || ! filter_var( $new_email, FILTER_VALIDATE_EMAIL ) ) {
            return [ 'success' => false, 'error' => 'invalid_input' ];
        }

        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();

            $stmt1 = $connection->prepare( 'UPDATE HRS_PROFIL_KANDIDAT SET EMAIL = :email WHERE KANDIDAT_ID = :id' );
            $stmt1->execute( [ 'email' => $new_email, 'id' => $candidate_id ] );

            $stmt2 = $connection->prepare( 'UPDATE HRS_PROFILE_KANDIDAT_LOGIN SET EMAIL = :email WHERE KANDIDAT_ID = :id' );
            $stmt2->execute( [ 'email' => $new_email, 'id' => $candidate_id ] );

            $connection->commit();
            return [ 'success' => true ];
        } catch ( Throwable $e ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            return [ 'success' => false, 'error' => $e->getMessage() ];
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
        $token_column = $this->get_application_token_column_expression();
        if ( null === $token_column ) {
            return [];
        }
        return $this->fetch_all(
            'SELECT l.lamaran_id AS "id", ' . $token_column . ' AS "token", l.detail_lowongan_id AS "vacancy_id", d.judul_pekerjaan AS "title", k.nama_dealer AS "location", NVL(d.nama_departemen, k.nama_departemen) AS "department", TO_CHAR(NVL(l.created_at, l.tanggal_melamar), \'YYYY-MM-DD HH24:MI:SS\') AS "created_at", l.status_terkini AS "status", l.status_akhir AS "final_status", (SELECT MAX(r.tahap_seleksi) KEEP (DENSE_RANK LAST ORDER BY r.tanggal_status, r.riwayat_status_id) FROM HRS_RIWAYAT_STATUS_PELAMAR r WHERE r.lamaran_id = l.lamaran_id) AS "latest_stage" FROM HRS_LAMARAN l JOIN HRS_DETAIL_INFO_LOWONGAN d ON d.detail_lowongan_id = l.detail_lowongan_id LEFT JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.kolom_pendaftaran_id = d.kolom_pendaftaran_id WHERE l.profile_kandidat_id = :login_id ORDER BY NVL(l.created_at, l.tanggal_melamar) DESC, l.lamaran_id DESC',
            [ 'login_id' => $login_id ]
        );
    }

    /** @return array<string, string>|null */
    public function get_applicant_application( string $login_id, string $application_id ): ?array {
        if ( ! ctype_digit( $login_id ) || '0' === $login_id || ! ctype_digit( $application_id ) || '0' === $application_id ) {
            return null;
        }
        $token_column = $this->get_application_token_column_expression();
        if ( null === $token_column ) {
            return null;
        }
        $rows = $this->fetch_all(
            'SELECT l.lamaran_id AS "id", ' . $token_column . ' AS "token", l.detail_lowongan_id AS "vacancy_id", d.judul_pekerjaan AS "title", k.nama_dealer AS "location", NVL(d.nama_departemen, k.nama_departemen) AS "department", TO_CHAR(NVL(l.created_at, l.tanggal_melamar), \'YYYY-MM-DD HH24:MI:SS\') AS "created_at", l.status_terkini AS "status", l.status_akhir AS "final_status" FROM HRS_LAMARAN l JOIN HRS_DETAIL_INFO_LOWONGAN d ON d.detail_lowongan_id = l.detail_lowongan_id LEFT JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.kolom_pendaftaran_id = d.kolom_pendaftaran_id WHERE l.profile_kandidat_id = :login_id AND l.lamaran_id = :application_id',
            [ 'login_id' => $login_id, 'application_id' => $application_id ]
        );
        return $rows[0] ?? null;
    }

    /** @return array<string, string>|null */
    public function get_applicant_application_by_token( string $login_id, string $application_token ): ?array {
        if ( ! ctype_digit( $login_id ) || '0' === $login_id || ! preg_match( '/^DAW-\d{4}-[A-F0-9]{32}$/', $application_token ) ) {
            return null;
        }
        $token_column = $this->get_application_token_column_expression();
        if ( null === $token_column || 'l.application_token' !== $token_column ) {
            return null;
        }
        $rows = $this->fetch_all(
            'SELECT l.lamaran_id AS "id", l.application_token AS "token", l.detail_lowongan_id AS "vacancy_id", d.judul_pekerjaan AS "title", k.nama_dealer AS "location", NVL(d.nama_departemen, k.nama_departemen) AS "department", TO_CHAR(NVL(l.created_at, l.tanggal_melamar), \'YYYY-MM-DD HH24:MI:SS\') AS "created_at", l.status_terkini AS "status", l.status_akhir AS "final_status" FROM HRS_LAMARAN l JOIN HRS_DETAIL_INFO_LOWONGAN d ON d.detail_lowongan_id = l.detail_lowongan_id LEFT JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.kolom_pendaftaran_id = d.kolom_pendaftaran_id WHERE l.profile_kandidat_id = :login_id AND l.application_token = :application_token',
            [ 'login_id' => $login_id, 'application_token' => $application_token ]
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

    /** @return array{confirmed: bool, confirmed_at: string, reminder_sent: bool}|null */
    public function get_psychotest_attendance( string $login_id, string $application_id ): ?array {
        if ( ! ctype_digit( $login_id ) || '0' === $login_id || ! ctype_digit( $application_id ) || '0' === $application_id ) {
            return null;
        }
        try {
            $statement = $this->get_connection()->prepare(
                'SELECT c.attendance_confirmed AS "confirmed", TO_CHAR(c.confirmed_at, \'YYYY-MM-DD HH24:MI:SS\') AS "confirmed_at", CASE WHEN c.email_reminder_sent_at IS NULL THEN \'N\' ELSE \'Y\' END AS "reminder_sent" FROM HRS_KONFIRMASI_PSIKOTES c JOIN HRS_LAMARAN l ON l.lamaran_id = c.lamaran_id WHERE l.profile_kandidat_id = :login_id AND l.lamaran_id = :application_id'
            );
            $statement->execute( [ 'login_id' => $login_id, 'application_id' => $application_id ] );
            $attendance = $statement->fetch( PDO::FETCH_ASSOC );
            if ( ! is_array( $attendance ) ) {
                return null;
            }

            return [
                'confirmed' => 'Y' === strtoupper( (string) $attendance['confirmed'] ),
                'confirmed_at' => (string) $attendance['confirmed_at'],
                'reminder_sent' => 'Y' === strtoupper( (string) $attendance['reminder_sent'] ),
            ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_oracle_failure( 'Psychotest attendance lookup failed', $exception );
            return null;
        }
    }

    /** @return array{success: bool, error?: string} */
    public function confirm_psychotest_attendance( string $login_id, string $application_id ): array {
        if ( ! ctype_digit( $login_id ) || '0' === $login_id || ! ctype_digit( $application_id ) || '0' === $application_id ) {
            return [ 'success' => false, 'error' => 'invalid_application' ];
        }

        try {
            $statement = $this->get_connection()->prepare(
                'MERGE INTO HRS_KONFIRMASI_PSIKOTES c USING (SELECT l.lamaran_id, l.profile_kandidat_id FROM HRS_LAMARAN l WHERE l.profile_kandidat_id = :login_id AND l.lamaran_id = :application_id) a ON (c.lamaran_id = a.lamaran_id) WHEN MATCHED THEN UPDATE SET c.profile_kandidat_id = a.profile_kandidat_id, c.attendance_confirmed = \'Y\', c.confirmed_at = CURRENT_TIMESTAMP WHEN NOT MATCHED THEN INSERT (lamaran_id, profile_kandidat_id, attendance_confirmed, confirmed_at) VALUES (a.lamaran_id, a.profile_kandidat_id, \'Y\', CURRENT_TIMESTAMP)'
            );
            $statement->execute( [ 'login_id' => $login_id, 'application_id' => $application_id ] );
            if ( 0 === $statement->rowCount() ) {
                return [ 'success' => false, 'error' => 'application_not_found' ];
            }
            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_oracle_failure( 'Psychotest attendance confirmation failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /** @return array{success: bool, error?: string} */
    public function update_psychotest_attendance_time( string $login_id, string $application_id, string $local_datetime ): array {
        if (
            ! ctype_digit( $login_id )
            || '0' === $login_id
            || ! ctype_digit( $application_id )
            || '0' === $application_id
            || ! preg_match( '/^\d{4}-\d{2}-\d{2}T(?:[01]\d|2[0-3]):[0-5]\d$/', $local_datetime )
        ) {
            return [ 'success' => false, 'error' => 'invalid_datetime' ];
        }

        try {
            $connection = $this->get_connection();
            $check = $connection->prepare(
                'SELECT COUNT(*) FROM HRS_KONFIRMASI_PSIKOTES c JOIN HRS_LAMARAN l ON l.lamaran_id = c.lamaran_id WHERE c.lamaran_id = :application_id AND c.profile_kandidat_id = :login_id AND c.attendance_confirmed = \'Y\' AND l.profile_kandidat_id = :application_login_id'
            );
            $check->execute(
                [
                    'application_id' => $application_id,
                    'login_id' => $login_id,
                    'application_login_id' => $login_id,
                ]
            );
            if ( 1 !== (int) $check->fetchColumn() ) {
                return [ 'success' => false, 'error' => 'confirmation_not_found' ];
            }

            $statement = $connection->prepare(
                'UPDATE HRS_KONFIRMASI_PSIKOTES SET confirmed_at = TO_TIMESTAMP(:confirmed_at, \'YYYY-MM-DD"T"HH24:MI\') WHERE lamaran_id = :application_id AND profile_kandidat_id = :login_id AND attendance_confirmed = \'Y\''
            );
            $statement->execute(
                [
                    'confirmed_at' => $local_datetime,
                    'application_id' => $application_id,
                    'login_id' => $login_id,
                ]
            );
            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_oracle_failure( 'Psychotest attendance time update failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /** @return array{success: bool, error?: string} */
    public function mark_psychotest_reminder_sent( string $login_id, string $application_id ): array {
        if ( ! ctype_digit( $login_id ) || '0' === $login_id || ! ctype_digit( $application_id ) || '0' === $application_id ) {
            return [ 'success' => false, 'error' => 'invalid_application' ];
        }

        try {
            $statement = $this->get_connection()->prepare(
                'UPDATE HRS_KONFIRMASI_PSIKOTES SET email_reminder_sent_at = CURRENT_TIMESTAMP WHERE lamaran_id = :application_id AND attendance_confirmed = \'Y\' AND EXISTS (SELECT 1 FROM HRS_LAMARAN l WHERE l.lamaran_id = HRS_KONFIRMASI_PSIKOTES.lamaran_id AND l.profile_kandidat_id = :login_id)'
            );
            $statement->execute( [ 'application_id' => $application_id, 'login_id' => $login_id ] );
            return 0 < $statement->rowCount()
                ? [ 'success' => true ]
                : [ 'success' => false, 'error' => 'confirmation_not_found' ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_oracle_failure( 'Psychotest reminder status update failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Get details for attendance reminder email.
     *
     * @return array{application_id: string, applicant_id: string, applicant_name: string, applicant_email: string, title: string, location: string, confirmed_at: string}|null
     */
    public function get_attendance_reminder_details( string $application_id ): ?array {
        if ( ! ctype_digit( $application_id ) || '0' === $application_id ) {
            return null;
        }

        try {
            $rows = $this->fetch_all(
                'SELECT c.lamaran_id AS "application_id",
                        c.profile_kandidat_id AS "applicant_id",
                        NVL(p.nama_lengkap, p.nama_kandidat) AS "applicant_name",
                        NVL(p.email, pkl.email) AS "applicant_email",
                        NVL(d.judul_pekerjaan, k.judul_pekerjaan) AS "title",
                        NVL(k.nama_dealer, d.lokasi_penempatan) AS "location",
                        TO_CHAR(c.confirmed_at, \'YYYY-MM-DD HH24:MI:SS\') AS "confirmed_at"
                 FROM HRS_KONFIRMASI_PSIKOTES c
                 JOIN HRS_LAMARAN l ON l.lamaran_id = c.lamaran_id
                 LEFT JOIN HRS_DETAIL_INFO_LOWONGAN d ON d.detail_lowongan_id = l.detail_lowongan_id
                 LEFT JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.kolom_pendaftaran_id = d.kolom_pendaftaran_id
                 LEFT JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON pkl.profile_kandidat_login = l.profile_kandidat_id
                 LEFT JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = NVL(pkl.kandidat_id, c.profile_kandidat_id)
                 WHERE c.lamaran_id = :application_id
                   AND c.attendance_confirmed = \'Y\'
                   AND c.confirmed_at IS NOT NULL',
                [ 'application_id' => $application_id ]
            );
            return $rows[0] ?? null;
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_oracle_failure( 'Attendance reminder details lookup failed', $exception );
            return null;
        }
    }

    /**
     * Get all confirmed psychotest attendances that have a valid confirmed_at timestamp.
     *
     * @return array<int, array{application_id: string, applicant_id: string, applicant_name: string, applicant_email: string, title: string, location: string, confirmed_at: string}>
     */
    public function get_confirmed_psychotest_attendances(): array {
        try {
            return $this->fetch_all(
                'SELECT c.lamaran_id AS "application_id",
                        c.profile_kandidat_id AS "applicant_id",
                        NVL(p.nama_lengkap, p.nama_kandidat) AS "applicant_name",
                        NVL(p.email, pkl.email) AS "applicant_email",
                        NVL(d.judul_pekerjaan, k.judul_pekerjaan) AS "title",
                        NVL(k.nama_dealer, d.lokasi_penempatan) AS "location",
                        TO_CHAR(c.confirmed_at, \'YYYY-MM-DD HH24:MI:SS\') AS "confirmed_at"
                 FROM HRS_KONFIRMASI_PSIKOTES c
                 JOIN HRS_LAMARAN l ON l.lamaran_id = c.lamaran_id
                 LEFT JOIN HRS_DETAIL_INFO_LOWONGAN d ON d.detail_lowongan_id = l.detail_lowongan_id
                 LEFT JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.kolom_pendaftaran_id = d.kolom_pendaftaran_id
                 LEFT JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON pkl.profile_kandidat_login = l.profile_kandidat_id
                 LEFT JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = NVL(pkl.kandidat_id, c.profile_kandidat_id)
                 WHERE c.attendance_confirmed = \'Y\'
                   AND c.confirmed_at IS NOT NULL'
            );
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_oracle_failure( 'Confirmed psychotest attendances lookup failed', $exception );
            return [];
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
                if ( ! filter_var( $identifier, FILTER_VALIDATE_EMAIL ) && 100 < strlen( $identifier ) ) {
                    return [ 'success' => false, 'error' => 'invalid_credentials' ];
                }

                $statement = $this->get_connection()->prepare(
                    'SELECT role_akses_id AS "id", email AS "email", username AS "username", NVL(nama_lengkap, username) AS "display_name", kata_sandi AS "password_hash", status_akun AS "status" FROM hrs_role_akses WHERE (LOWER(email) = :email OR LOWER(username) = :username) AND peran = :role'
                );
                $statement->execute( [ 'email' => $identifier, 'username' => $identifier, 'role' => 'ADMIN_HR' ] );
                $rows = $statement->fetchAll( PDO::FETCH_ASSOC );
                if ( 1 !== count( $rows ) ) {
                    return [ 'success' => false, 'error' => 'invalid_credentials' ];
                }

                $row = $rows[0];
                $account = [
                    'type'          => 'hr',
                    'id'            => (string) $row['id'],
                    'email'         => (string) $row['email'],
                    'username'      => ! empty( $row['username'] ) ? (string) $row['username'] : (string) $row['email'],
                    'display_name'  => ! empty( $row['username'] ) ? (string) $row['username'] : (string) $row['email'],
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
     * Find an active applicant account for a password-reset request.
     *
     * @return array{success: bool, account?: array{id: string, profile_id: string, email: string, full_name: string}, error?: string}
     */
    public function get_active_applicant_by_email( string $email ): array {
        $email = strtolower( trim( $email ) );
        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            return [ 'success' => false, 'error' => 'account_not_found' ];
        }

        try {
            $statement = $this->get_connection()->prepare(
                'SELECT l.profile_kandidat_login AS "id", l.kandidat_id AS "profile_id", l.email AS "email", NVL(p.nama_lengkap, p.nama_kandidat) AS "full_name", l.status_akun AS "status" FROM HRS_PROFILE_KANDIDAT_LOGIN l LEFT JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = l.kandidat_id WHERE LOWER(TRIM(l.email)) = :email AND (l.status_akun = \'ACTIVE\' OR l.status_akun IS NULL)'
            );
            $statement->execute( [ 'email' => $email ] );
            $rows = $statement->fetchAll( PDO::FETCH_ASSOC );
            if ( empty( $rows ) ) {
                return [ 'success' => false, 'error' => 'account_not_found' ];
            }

            $row = $rows[0];
            return [
                'success' => true,
                'account' => [
                    'id'         => (string) $row['id'],
                    'profile_id' => (string) ( $row['profile_id'] ?? '' ),
                    'email'      => (string) $row['email'],
                    'full_name'  => (string) ( $row['full_name'] ?? 'Pelamar' ),
                ],
            ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'Applicant Oracle password reset lookup failed', $exception );
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
    /**
     * Update HR administrator email in Oracle HRS_ROLE_AKSES.
     *
     * @param string $account_id
     * @param string $email
     * @return array{success: bool, error?: string}
     */
    public function update_hr_email( string $account_id, string $email ): array {
        $account_id = trim( (string) $account_id );
        $email      = strtolower( trim( (string) $email ) );

        if ( ! ctype_digit( $account_id ) || '0' === $account_id || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            return [ 'success' => false, 'error' => 'invalid_email' ];
        }

        try {
            $connection = $this->get_connection();

            // Check if another account in HRS_ROLE_AKSES already uses this email
            $duplicate = $connection->prepare(
                'SELECT COUNT(*) FROM hrs_role_akses WHERE LOWER(email) = :email AND role_akses_id <> :account_id'
            );
            $duplicate->execute( [ 'email' => $email, 'account_id' => $account_id ] );
            if ( 0 < (int) $duplicate->fetchColumn() ) {
                return [ 'success' => false, 'error' => 'duplicate_email' ];
            }

            // Update email
            $update = $connection->prepare(
                'UPDATE hrs_role_akses SET email = :email WHERE role_akses_id = :account_id'
            );
            $update->execute( [ 'email' => $email, 'account_id' => $account_id ] );

            // If username is empty or contains an email address, keep it in sync with the new email
            $check = $connection->prepare(
                'SELECT username FROM hrs_role_akses WHERE role_akses_id = :account_id'
            );
            $check->execute( [ 'account_id' => $account_id ] );
            $curr_user = $check->fetchColumn();
            if ( false !== $curr_user && ( null === $curr_user || '' === trim( (string) $curr_user ) || false !== strpos( (string) $curr_user, '@' ) ) ) {
                $upd_user = $connection->prepare(
                    'UPDATE hrs_role_akses SET username = :username WHERE role_akses_id = :account_id'
                );
                $upd_user->execute( [ 'username' => $email, 'account_id' => $account_id ] );
            }

            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'HR Oracle email update failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    public function update_hr_profile( string $account_id, string $username, string $email ): array {
        $account_id = trim( (string) $account_id );
        $username   = trim( (string) $username );
        $email      = strtolower( trim( (string) $email ) );

        if ( ! ctype_digit( $account_id ) || '0' === $account_id || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            return [ 'success' => false, 'error' => 'invalid_profile_data' ];
        }

        if ( '' === $username ) {
            $username = $email;
        }

        try {
            $connection = $this->get_connection();
            $duplicate = $connection->prepare(
                'SELECT COUNT(*) FROM hrs_role_akses WHERE (LOWER(username) = :username OR LOWER(email) = :email) AND role_akses_id <> :account_id'
            );
            $duplicate->execute( [ 'username' => strtolower( $username ), 'email' => $email, 'account_id' => $account_id ] );
            if ( 0 < (int) $duplicate->fetchColumn() ) {
                return [ 'success' => false, 'error' => 'duplicate_account' ];
            }
            $update = $connection->prepare(
                'UPDATE hrs_role_akses SET username = :username, email = :email WHERE role_akses_id = :account_id'
            );
            $update->execute( [ 'username' => $username, 'email' => $email, 'account_id' => $account_id ] );
            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'HR Oracle profile update failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Retrieve current HR profile details from Oracle HRS_ROLE_AKSES.
     *
     * @return array<string, mixed>|null
     */
    public function get_hr_profile( string $account_id ): ?array {
        if ( ! ctype_digit( $account_id ) || '0' === $account_id ) {
            return null;
        }
        try {
            $statement = $this->get_connection()->prepare(
                'SELECT role_akses_id AS "id", username AS "username", email AS "email", status_akun AS "status" FROM hrs_role_akses WHERE role_akses_id = :account_id'
            );
            $statement->execute( [ 'account_id' => $account_id ] );
            $row = $statement->fetch( PDO::FETCH_ASSOC );
            return $row ?: null;
        } catch ( PDOException | RuntimeException $exception ) {
            return null;
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
                'SELECT kata_sandi FROM hrs_role_akses WHERE role_akses_id = :account_id'
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
                'UPDATE hrs_role_akses SET kata_sandi = :password_hash WHERE role_akses_id = :account_id'
            );
            $update->execute( [ 'password_hash' => $new_hash, 'account_id' => $account_id ] );

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
                'UPDATE hrs_role_akses SET kata_sandi = :password_hash WHERE role_akses_id = :account_id'
            );
            $statement->execute(
                [
                    'password_hash' => $password_hash,
                    'account_id'    => $account_id,
                ]
            );

            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'HR Oracle password reset failed', $exception );
            return [ 'success' => false, 'error' => 'service_unavailable' ];
        }
    }

    /**
     * Reset applicant password using a verified reset token.
     *
     * @return array{success: bool, error?: string}
     */
    public function reset_applicant_password( string $login_id, string $new_password ): array {
        if ( ! ctype_digit( $login_id ) || '0' === $login_id || strlen( $new_password ) < 8 ) {
            return [ 'success' => false, 'error' => 'invalid_password_data' ];
        }

        try {
            $password_hash = password_hash( $new_password, PASSWORD_BCRYPT );
            if ( false === $password_hash ) {
                throw new RuntimeException( 'Applicant reset password could not be hashed.' );
            }

            $statement = $this->get_connection()->prepare(
                'UPDATE HRS_PROFILE_KANDIDAT_LOGIN SET KATA_SANDI = :password_hash, UPDATED_AT = CURRENT_TIMESTAMP WHERE PROFILE_KANDIDAT_LOGIN = :login_id AND (STATUS_AKUN = \'ACTIVE\' OR STATUS_AKUN IS NULL)'
            );
            $statement->execute(
                [
                    'password_hash' => $password_hash,
                    'login_id'      => $login_id,
                ]
            );
            if ( 1 !== $statement->rowCount() ) {
                return [ 'success' => false, 'error' => 'account_not_found' ];
            }

            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_error( 'Applicant Oracle password reset failed', $exception );
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
                'SELECT BAGIAN_PENDAFTARAN_ID AS "id", NAMA_SECTION AS "title", DESKRIPSI AS "description", DISPLAY_ORDER AS "display_order" FROM HRS_BAGIAN_PENDAFTARAN WHERE IS_ACTIVE = 1 ORDER BY DISPLAY_ORDER, BAGIAN_PENDAFTARAN_ID'
            );
            $sections = [];
            $section_indexes = [];
            foreach ( $section_statement->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
                $section_id = (string) $row['id'];
                $section_indexes[ $section_id ] = count( $sections );
                $sections[] = [
                    'id'          => $section_id,
                    'section_key' => '',
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
            foreach ( $connection->query( 'SELECT BAGIAN_PENDAFTARAN_ID FROM HRS_BAGIAN_PENDAFTARAN' )->fetchAll( PDO::FETCH_COLUMN ) as $id ) {
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
                $connection->query( 'SELECT BAGIAN_PENDAFTARAN_ID FROM HRS_BAGIAN_PENDAFTARAN WHERE IS_ACTIVE = 1' )->fetchAll( PDO::FETCH_COLUMN )
            );
            $active_fields = array_map(
                'strval',
                $connection->query( 'SELECT FIELD_ID FROM RECRUIT_FORM_FIELDS WHERE IS_ACTIVE = 1' )->fetchAll( PDO::FETCH_COLUMN )
            );

            $submitted_sections = [];
            $submitted_fields = [];
            foreach ( $sections as $section_index => $section ) {
                $section_id = trim( (string) ( $section['id'] ?? '' ) );
                if ( '' !== $section_id ) {
                    if ( ! ctype_digit( $section_id ) || ! isset( $existing_sections[ $section_id ] ) || isset( $submitted_sections[ $section_id ] ) ) {
                        throw new InvalidArgumentException( 'The submitted form contains an invalid section reference.' );
                    }
                    $section_statement = $connection->prepare(
                        'UPDATE HRS_BAGIAN_PENDAFTARAN SET NAMA_SECTION = :title, DESKRIPSI = :description, DISPLAY_ORDER = :display_order, IS_ACTIVE = 1 WHERE BAGIAN_PENDAFTARAN_ID = :section_id'
                    );
                    $section_statement->execute(
                        [
                            'title'        => $section['title'],
                            'description'  => $section['description'],
                            'display_order' => $section_index + 1,
                            'section_id'   => $section_id,
                        ]
                    );
                } else {
                    $section_statement = $connection->prepare(
                        'INSERT INTO HRS_BAGIAN_PENDAFTARAN (NAMA_SECTION, DESKRIPSI, DISPLAY_ORDER, IS_ACTIVE) VALUES (:title, :description, :display_order, 1) RETURNING BAGIAN_PENDAFTARAN_ID INTO :section_id'
                    );
                    $new_section_id = 0;
                    $section_statement->bindValue( ':title', $section['title'] );
                    $section_statement->bindValue( ':description', $section['description'] );
                    $section_statement->bindValue( ':display_order', $section_index + 1, PDO::PARAM_INT );
                    $section_statement->bindParam( ':section_id', $new_section_id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 40 );
                    $section_statement->execute();
                    if ( 0 >= (int) $new_section_id ) {
                        throw new RuntimeException( 'The new application section could not be retrieved.' );
                    }
                    $section_id = (string) $new_section_id;
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
                        'UPDATE HRS_BAGIAN_PENDAFTARAN SET IS_ACTIVE = 0 WHERE BAGIAN_PENDAFTARAN_ID = :section_id'
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
     * @return array{success: bool, application_id?: string, application_token?: string, already_submitted?: bool, error?: string}
     */
    public function save_application_submission( array $submission ): array {
        $connection = null;
        $profile_service = null;
        $stored_documents = [];
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
                'SELECT LAMARAN_ID, APPLICATION_TOKEN FROM HRS_LAMARAN WHERE PROFILE_KANDIDAT_ID = :candidate_id AND DETAIL_LOWONGAN_ID = :vacancy_id ORDER BY LAMARAN_ID DESC FETCH FIRST 1 ROWS ONLY'
            );
            $duplicate_check->execute( [ 'candidate_id' => $candidate_login_id, 'vacancy_id' => $vacancy_id ] );
            $existing_application = $duplicate_check->fetch( PDO::FETCH_ASSOC );
            if ( $existing_application ) {
                $connection->commit();
                return [
                    'success'          => true,
                    'application_id'    => (string) $existing_application['LAMARAN_ID'],
                    'application_token' => (string) $existing_application['APPLICATION_TOKEN'],
                    'already_submitted' => true,
                ];
            }

            // Ensure vacancy exists in Oracle HRS_DETAIL_INFO_LOWONGAN
            $vacancy_check = $connection->prepare( 'SELECT DETAIL_LOWONGAN_ID FROM HRS_DETAIL_INFO_LOWONGAN WHERE DETAIL_LOWONGAN_ID = :vacancy_id' );
            $vacancy_check->execute( [ 'vacancy_id' => $vacancy_id ] );
            $has_oracle_vacancy = false !== $vacancy_check->fetchColumn();
            $wp_post = null;
            $wp_post_id = (int) $vacancy_id - self::WORDPRESS_VACANCY_ID_OFFSET;
            if ( function_exists( 'get_post' ) && $wp_post_id > 0 ) {
                $candidate_post = get_post( $wp_post_id );
                if ( $candidate_post && 'daw_vacancy' === $candidate_post->post_type ) {
                    $wp_post = $candidate_post;
                }
            }
            $wp_department_id = '';
            $wp_dept = '';
            if ( ! $has_oracle_vacancy && $wp_post ) {
                $wp_department_id = (string) get_post_meta( $wp_post->ID, '_daw_department_id', true );
                if ( ! ctype_digit( $wp_department_id ) || '0' === $wp_department_id ) {
                    throw new InvalidArgumentException( 'Vacancy department is invalid.' );
                }
                $department_check = $connection->prepare(
                    'SELECT nama_departemen FROM HRS_DEPARTEMEN WHERE departemen_id = :department_id AND (status_departemen IS NULL OR status_departemen = 1)'
                );
                $department_check->execute( [ 'department_id' => $wp_department_id ] );
                $department_name = $department_check->fetchColumn();
                if ( false === $department_name ) {
                    throw new InvalidArgumentException( 'Vacancy department is invalid.' );
                }
                $wp_dept = (string) $department_name;
            }
            if ( ! $has_oracle_vacancy && '' === $wp_dept ) {
                throw new InvalidArgumentException( 'Vacancy department is invalid.' );
            }

            if ( ! $has_oracle_vacancy ) {
                $wp_title = (string) ( $submission['vacancy_title'] ?? 'Lowongan' );
                $wp_dealer = '';
                $wp_loc = '';
                $wp_type = '';
                $wp_desc = '';
                $wp_deadline = null;
                if ( $wp_post && 'daw_vacancy' === $wp_post->post_type ) {
                    $wp_title = get_the_title( $wp_post ) ?: $wp_title;
                    $wp_desc = $wp_post->post_content ?: '';
                    $wp_dealer = (string) get_post_meta( $wp_post->ID, '_daw_dealer', true );
                    $wp_loc = (string) get_post_meta( $wp_post->ID, '_daw_location', true );
                    $wp_type = (string) get_post_meta( $wp_post->ID, '_daw_type', true );
                    $wp_deadline = (string) get_post_meta( $wp_post->ID, '_daw_deadline', true );
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
                    'INSERT INTO HRS_DETAIL_INFO_LOWONGAN (DETAIL_LOWONGAN_ID, KOLOM_PENDAFTARAN_ID, JUDUL_PEKERJAAN, DESKRIPSI_PEKERJAAN, LOKASI_PENEMPATAN, DEPARTEMEN_ID, NAMA_DEPARTEMEN, KATEGORI_KJ, BATAS_LAMARAN, REQUEST_AT) VALUES (:detail_id, :kolom_id, :title, :description, :location, :department_id, :department, :job_type, CASE WHEN :deadline IS NULL THEN NULL ELSE TO_DATE(:deadline_val, \'YYYY-MM-DD\') END, CURRENT_TIMESTAMP)'
                );
                $ins_detail->execute( [
                    'detail_id'    => $vacancy_id,
                    'kolom_id'     => $vacancy_id,
                    'title'        => substr( $wp_title, 0, 1000 ),
                    'description'  => $wp_desc,
                    'location'     => substr( $wp_loc ?: $wp_dealer, 0, 1000 ),
                    'department_id' => $wp_department_id,
                    'department'    => substr( $wp_dept, 0, 1000 ),
                    'job_type'      => substr( $wp_type, 0, 1000 ),
                    'deadline'      => $deadline_val,
                    'deadline_val'  => $deadline_val,
                ] );
            }

            $token_column = $this->get_application_token_column_expression();
            if ( null === $token_column ) {
                throw new RuntimeException( 'Application token schema could not be verified.' );
            }
            $has_application_token = 'l.application_token' === $token_column;
            $application_token = '';
            if ( $has_application_token ) {
                try {
                    $application_random = bin2hex( random_bytes( 16 ) );
                } catch ( \Exception $exception ) {
                    throw new RuntimeException( 'A secure application token could not be generated.', 0, $exception );
                }
                $application_token = 'DAW-' . gmdate( 'Y' ) . '-' . strtoupper( $application_random );
            }
            $application_id = 0;
            $application_sql = $has_application_token
                ? 'INSERT INTO HRS_LAMARAN (PROFILE_KANDIDAT_ID, DETAIL_LOWONGAN_ID, APPLICATION_TOKEN, TANGGAL_MELAMAR, STATUS_TERKINI, STATUS_AKHIR, CREATED_AT, UPDATED_AT) VALUES (:candidate_id, :vacancy_id, :application_token, CURRENT_TIMESTAMP, :initial_status, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING LAMARAN_ID INTO :application_id'
                : 'INSERT INTO HRS_LAMARAN (PROFILE_KANDIDAT_ID, DETAIL_LOWONGAN_ID, TANGGAL_MELAMAR, STATUS_TERKINI, STATUS_AKHIR, CREATED_AT, UPDATED_AT) VALUES (:candidate_id, :vacancy_id, CURRENT_TIMESTAMP, :initial_status, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING LAMARAN_ID INTO :application_id';
            $application = $connection->prepare( $application_sql );
            $application->bindValue( ':candidate_id', $candidate_login_id );
            $application->bindValue( ':vacancy_id', $vacancy_id );
            if ( $has_application_token ) {
                $application->bindValue( ':application_token', $application_token );
            }
            $application->bindValue( ':initial_status', 'SUBMITTED' );
            $application->bindParam( ':application_id', $application_id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 40 );
            $application->execute();
            if ( 1 !== $application->rowCount() || 0 >= (int) $application_id ) {
                throw new RuntimeException( 'The new application ID could not be retrieved.' );
            }

            // Sync answers into applicant's DATA_PROFIL in Oracle
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
                    'application_id'    => (string) $application_id,
                    'application_token' => $application_token,
                    'vacancy_id'        => $vacancy_id,
                    'vacancy_title'     => (string) ( $submission['vacancy_title'] ?? '' ),
                    'applied_at'        => function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' ),
                    'answers'           => $current_app_answers,
                ];
            }

            // Check if RECRUIT_FORM_SUBMISSIONS table exists in the schema
            $table_check = $connection->prepare( "SELECT COUNT(*) FROM USER_TABLES WHERE TABLE_NAME = 'RECRUIT_FORM_SUBMISSIONS'" );
            $table_check->execute();
            if ( 0 < (int) $table_check->fetchColumn() ) {
                $submission_token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0x0fff ) | 0x4000, mt_rand( 0, 0x3fff ) | 0x8000, mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ) );
                $statement = $connection->prepare(
                    'INSERT INTO RECRUIT_FORM_SUBMISSIONS (SUBMISSION_TOKEN, LAMARAN_ID, APPLICANT_EMAIL, APPLICANT_NAME, SOURCE_VACANCY_ID, SOURCE_VACANCY_TITLE, PDP_ACCURACY, PDP_DATA, PDP_PLACEMENT) VALUES (:token, :application_id, :email, :name, :vacancy_id, :vacancy_title, 1, 1, 1)'
                );
                $statement->execute(
                    [
                        'token'         => $submission_token,
                        'application_id' => $application_id,
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

            if ( '' !== $profile_id ) {
                foreach ( $submission['answers'] as $answer ) {
                    $file = $answer['file'] ?? null;
                    if ( ! is_array( $file ) || empty( $file['tmp_name'] ) ) {
                        continue;
                    }

                    $field_key = (string) ( $answer['field_key'] ?? '' );
                    $doc_key = [
                        'foto_terkini' => 'profile_photo',
                        'cv_resume'    => 'resume',
                        'portofolio'   => 'portfolio',
                    ][ $field_key ] ?? null;
                    if ( null === $doc_key ) {
                        continue;
                    }
                    if ( ! class_exists( 'Recruitment_Applicant_Profile' ) ) {
                        throw new RuntimeException( 'Applicant document storage is unavailable.' );
                    }
                    if ( null === $profile_service ) {
                        $profile_service = new Recruitment_Applicant_Profile();
                    }

                    $stored_doc = $profile_service->store_uploaded_document( $file, $doc_key );
                    if ( null === $stored_doc ) {
                        throw new RuntimeException( 'Applicant document could not be stored.' );
                    }
                    $stored_documents[] = $stored_doc;
                    if ( ! isset( $profile_details['documents'] ) || ! is_array( $profile_details['documents'] ) ) {
                        $profile_details['documents'] = [];
                    }
                    $profile_details['documents'][ $doc_key ] = $stored_doc;
                }

                $this->save_applicant_profile_details( $connection, $profile_id, $profile_details );
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
            return [ 'success' => true, 'application_id' => (string) $application_id, 'application_token' => $application_token ];
        } catch ( InvalidArgumentException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            if ( $profile_service instanceof Recruitment_Applicant_Profile ) {
                $profile_service->delete_documents( $stored_documents );
            }
            $message = 'Lowongan tidak tersedia atau akun pelamar tidak aktif.';
            if ( 'Applicant has already applied to this vacancy.' === $exception->getMessage() ) {
                $message = 'Anda sudah pernah melamar lowongan ini.';
            } elseif ( 'Vacancy department is invalid.' === $exception->getMessage() ) {
                $message = 'Lowongan belum memiliki departemen Oracle yang valid. Hubungi HR untuk memperbarui data lowongan.';
            }
            return [ 'success' => false, 'error' => $message ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            if ( $profile_service instanceof Recruitment_Applicant_Profile ) {
                $profile_service->delete_documents( $stored_documents );
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
            'SELECT d.detail_lowongan_id AS "id", d.judul_pekerjaan AS "title", k.nama_dealer AS "location", k.nama_dealer AS "dealer", d.kategori_kj AS "job_type", d.deskripsi_pekerjaan AS "description", d.pendidikan_minimum AS "education", TO_CHAR(d.batas_lamaran, \'YYYY-MM-DD\') AS "deadline", NVL(d.nama_departemen, k.nama_departemen) AS "department", TO_CHAR(d.request_at, \'YYYY-MM-DD\') AS "posted_at" FROM HRS_DETAIL_INFO_LOWONGAN d JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.kolom_pendaftaran_id = d.kolom_pendaftaran_id WHERE k.status_kolom = :status AND (d.batas_lamaran IS NULL OR d.batas_lamaran >= CURRENT_DATE) ORDER BY d.detail_lowongan_id DESC',
            [ 'status' => 1 ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_applicants(): array {
        return $this->fetch_all(
            'SELECT pkl.profile_kandidat_login AS "id", NVL(p.nama_lengkap, p.nama_kandidat) AS "name", NVL(p.email, pkl.email) AS "email", NVL(p.nomor_telepon, TO_CHAR(pkl.nomor_telepon)) AS "phone", NVL(pkl.created_at, p.dibuat_pada) AS "created_at" FROM HRS_PROFILE_KANDIDAT_LOGIN pkl JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = pkl.kandidat_id WHERE UPPER(pkl.status_akun) = :status ORDER BY NVL(pkl.created_at, p.dibuat_pada) DESC',
            [ 'status' => 'ACTIVE' ]
        );
    }

    public function get_active_applicant_count(): int {
        $rows = $this->fetch_all(
            'SELECT COUNT(DISTINCT pkl.kandidat_id) AS "candidate_count" FROM HRS_PROFILE_KANDIDAT_LOGIN pkl JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = pkl.kandidat_id WHERE UPPER(pkl.status_akun) = :status',
            [ 'status' => 'ACTIVE' ]
        );

        return (int) ( $rows[0]['candidate_count'] ?? 0 );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * Return applications awaiting an HR interview and their latest psychotest score.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_hr_interview_candidates(): array {
        $this->last_error = null;
        return $this->fetch_all(
            'SELECT l.lamaran_id AS "application_id",
                    l.profile_kandidat_id AS "applicant_id",
                    l.status_terkini AS "status",
                    TO_CHAR(NVL(l.created_at, l.tanggal_melamar), \'YYYY-MM-DD HH24:MI:SS\') AS "created_at",
                    d.judul_pekerjaan AS "position",
                    d.nama_departemen AS "department",
                    k.nama_dealer AS "dealer",
                    p.kandidat_id AS "candidate_id",
                    NVL(p.nama_lengkap, p.nama_kandidat) AS "candidate_name",
                    NVL(p.email, pkl.email) AS "candidate_email",
                    (SELECT h.nilai
                     FROM HRS_HASIL_TES_PSIKOLOGI h
                     WHERE h.kandidat_id = p.kandidat_id
                     ORDER BY h.dikumpulkan_pada DESC NULLS LAST, h.hasil_tes_psikologi_id DESC
                     FETCH FIRST 1 ROW ONLY) AS "psychotest_score",
                    (SELECT TO_CHAR(j.tanggal_wawancara, \'YYYY-MM-DD\')
                     FROM HRS_JADWAL_KANDIDAT j
                     WHERE j.kandidat_id = p.kandidat_id
                     ORDER BY j.tanggal_wawancara DESC NULLS LAST, j.jadwal_id DESC
                     FETCH FIRST 1 ROW ONLY) AS "scheduled_interview_date",
                    (SELECT j.jam_wawancara
                     FROM HRS_JADWAL_KANDIDAT j
                     WHERE j.kandidat_id = p.kandidat_id
                     ORDER BY j.tanggal_wawancara DESC NULLS LAST, j.jadwal_id DESC
                     FETCH FIRST 1 ROW ONLY) AS "scheduled_interview_time",
                    hr.status_hasil AS "hr_result",
                    hr.rekomendasi AS "hr_recommendation"
             FROM HRS_LAMARAN l
             JOIN HRS_DETAIL_INFO_LOWONGAN d ON d.detail_lowongan_id = l.detail_lowongan_id
             LEFT JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.kolom_pendaftaran_id = d.kolom_pendaftaran_id
             JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON pkl.profile_kandidat_login = l.profile_kandidat_id
             JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = pkl.kandidat_id
             LEFT JOIN HRS_HASIL_WAWANCARA_HR hr ON hr.lamaran_id = l.lamaran_id
             WHERE UPPER(TRIM(l.status_terkini)) = \'INTERVIEW_HR\'
             ORDER BY NVL(l.created_at, l.tanggal_melamar), l.lamaran_id'
        );
    }

    /**
     * Return the saved HR interview notes for a candidate, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_hr_interview_history( string $application_id ): array {
        if ( ! ctype_digit( $application_id ) || '0' === $application_id ) {
            return [];
        }

        $this->last_error = null;
        return $this->fetch_all(
            'SELECT h.riwayat_id AS "history_id",
                    TO_CHAR(h.tanggal_wawancara, \'YYYY-MM-DD\') AS "interview_date",
                    h.nama_pewawancara AS "interviewer",
                    h.penilaian AS "rating",
                    h.rekomendasi AS "recommendation",
                    h.nama_departemen_tujuan AS "department",
                    h.role_akses_id AS "assigned_account_id",
                    u.username AS "department_user",
                    h.catatan_wawancara AS "notes",
                    h.catatan_rekomendasi AS "department_notes",
                    TO_CHAR(h.dibuat_pada, \'YYYY-MM-DD HH24:MI:SS\') AS "created_at"
             FROM HRS_RIWAYAT_WAWANCARA_HR h
             LEFT JOIN HRS_ROLE_AKSES u ON u.role_akses_id = h.role_akses_id
             WHERE h.lamaran_id = :application_id
             ORDER BY h.dibuat_pada DESC, h.riwayat_id DESC',
            [ 'application_id' => $application_id ]
        );
    }

    /**
     * Save an HR note without advancing the application.
     *
     * @return array{success: bool, error?: string}
     */
    public function save_hr_interview_note(
        string $application_id,
        string $interview_date,
        string $rating,
        string $recommendation,
        string $interviewer,
        string $notes,
        string $department_name,
        int $department_account_id,
        string $department_notes
    ): array {
        $validated = $this->validate_hr_interview_note_data(
            $application_id,
            $interview_date,
            $rating,
            $recommendation,
            $interviewer,
            $notes,
            $department_name,
            $department_account_id,
            $department_notes
        );
        if ( ! $validated ) {
            return [ 'success' => false, 'error' => 'Data catatan wawancara HR tidak valid.' ];
        }

        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $application = $connection->prepare(
                'SELECT l.status_terkini
                 FROM HRS_LAMARAN l
                 WHERE l.lamaran_id = :application_id
                 FOR UPDATE OF l.status_terkini'
            );
            $application->execute( [ 'application_id' => $application_id ] );
            $application_row = $application->fetch( PDO::FETCH_ASSOC );
            if ( ! is_array( $application_row ) ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'Lamaran tidak ditemukan.' ];
            }
            if ( 'INTERVIEW_HR' !== strtoupper( trim( (string) $application_row['STATUS_TERKINI'] ) ) ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'Lamaran tidak lagi menunggu wawancara HR.' ];
            }

            if ( $department_account_id > 0 ) {
                $this->validate_hr_interview_recipient( $connection, $department_name, $department_account_id );
            }
            $this->insert_hr_interview_note_history(
                $connection,
                $application_id,
                $interview_date,
                $rating,
                $recommendation,
                $interviewer,
                $notes,
                $department_name,
                $department_account_id,
                $department_notes
            );

            $connection->commit();
            return [ 'success' => true ];
        } catch ( InvalidArgumentException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            return [ 'success' => false, 'error' => $exception->getMessage() ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'HR interview note save failed', $exception );
            return [ 'success' => false, 'error' => 'Catatan wawancara HR gagal disimpan ke Oracle.' ];
        }
    }

    /**
     * Save the HR interview decision, application status, and tracking event atomically.
     *
     * @return array{success: bool, error?: string}
     */
    public function save_hr_interview_result(
        string $application_id,
        string $result,
        int $department_account_id,
        string $interviewer,
        string $notes,
        string $interview_date,
        string $rating,
        string $recommendation,
        string $department_name,
        string $department_notes
    ): array {
        $result = strtoupper( trim( $result ) );
        if (
            ! in_array( $result, [ 'LOLOS', 'TIDAK_LOLOS' ], true )
            || ( 'LOLOS' === $result && ( $department_account_id < 1 || '' === trim( $department_name ) ) )
            || ( 'TIDAK_LOLOS' === $result && 0 !== $department_account_id )
            || ! $this->validate_hr_interview_note_data(
                $application_id,
                $interview_date,
                $rating,
                $recommendation,
                $interviewer,
                $notes,
                $department_name,
                $department_account_id,
                $department_notes
            )
        ) {
            return [ 'success' => false, 'error' => 'Data hasil wawancara HR tidak valid.' ];
        }

        $interviewer = trim( $interviewer );
        $notes = trim( $notes );
        $department_name = trim( $department_name );
        $department_notes = trim( $department_notes );
        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $application = $connection->prepare(
                'SELECT l.status_terkini
                 FROM HRS_LAMARAN l
                 WHERE l.lamaran_id = :application_id
                 FOR UPDATE OF l.status_terkini'
            );
            $application->execute( [ 'application_id' => $application_id ] );
            $application_row = $application->fetch( PDO::FETCH_ASSOC );
            if ( ! is_array( $application_row ) ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'Lamaran tidak ditemukan.' ];
            }
            if ( 'INTERVIEW_HR' !== strtoupper( trim( (string) $application_row['STATUS_TERKINI'] ) ) ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'Lamaran tidak lagi menunggu wawancara HR.' ];
            }

            if ( 'LOLOS' === $result ) {
                $this->validate_hr_interview_recipient( $connection, $department_name, $department_account_id );
            }

            $recommendation = trim( $recommendation );
            $insert_result = $connection->prepare(
                'INSERT INTO HRS_HASIL_WAWANCARA_HR (
                    LAMARAN_ID, STATUS_HASIL, REKOMENDASI, CATATAN_WAWANCARA,
                    NAMA_PEWAWANCARA, ROLE_AKSES_ID, TANGGAL_WAWANCARA, PENILAIAN,
                    CATATAN_REKOMENDASI, NAMA_DEPARTEMEN_TUJUAN, DIBUAT_PADA, DIPERBARUI_PADA
                ) VALUES (
                    :application_id, :result, :recommendation, :notes,
                    :interviewer, :account_id, TO_DATE(:interview_date, \'YYYY-MM-DD\'), :rating,
                    :department_notes, :department_name, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )'
            );
            $insert_result->execute(
                [
                    'application_id' => $application_id,
                    'result' => $result,
                    'recommendation' => $recommendation,
                    'notes' => '' !== $notes ? $notes : null,
                    'interviewer' => $interviewer,
                    'account_id' => 'LOLOS' === $result ? $department_account_id : null,
                    'interview_date' => $interview_date,
                    'rating' => $rating,
                    'department_notes' => '' !== $department_notes ? $department_notes : null,
                    'department_name' => '' !== $department_name ? $department_name : null,
                ]
            );

            $this->insert_hr_interview_note_history(
                $connection,
                $application_id,
                $interview_date,
                $rating,
                $recommendation,
                $interviewer,
                $notes,
                $department_name,
                'LOLOS' === $result ? $department_account_id : 0,
                $department_notes
            );

            $next_status = 'LOLOS' === $result ? 'INTERVIEW_USER' : 'DITOLAK';
            $update_application = $connection->prepare(
                'UPDATE HRS_LAMARAN
                 SET STATUS_TERKINI = :status,
                     STATUS_AKHIR = CASE WHEN :is_rejected = 1 THEN :final_status ELSE NULL END,
                     UPDATED_AT = CURRENT_TIMESTAMP
                 WHERE LAMARAN_ID = :application_id'
            );
            $update_application->execute(
                [
                    'status' => $next_status,
                    'is_rejected' => 'LOLOS' === $result ? 0 : 1,
                    'final_status' => 'LOLOS' === $result ? null : 'DITOLAK',
                    'application_id' => $application_id,
                ]
            );
            if ( 1 !== $update_application->rowCount() ) {
                throw new RuntimeException( 'HR interview application status was not updated.' );
            }

            $history_note = 'Hasil wawancara HR: ' . $result . '. ' . $recommendation . '.';
            $history = $connection->prepare(
                'INSERT INTO HRS_RIWAYAT_STATUS_PELAMAR (
                    LAMARAN_ID, TAHAP_SELEKSI, STATUS_SELEKSI, TANGGAL_STATUS, KETERANGAN_PELAMAR
                ) VALUES (
                    :application_id, :stage, :status, CURRENT_TIMESTAMP, :note
                )'
            );
            $history->execute(
                [
                    'application_id' => $application_id,
                    'stage' => 'LOLOS' === $result ? 'INTERVIEW_USER' : 'INTERVIEW_HR',
                    'status' => $next_status,
                    'note' => $history_note,
                ]
            );

            $connection->commit();
            return [ 'success' => true ];
        } catch ( InvalidArgumentException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            return [ 'success' => false, 'error' => $exception->getMessage() ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'HR interview result save failed', $exception );
            return [ 'success' => false, 'error' => 'Hasil wawancara HR gagal disimpan ke Oracle.' ];
        }
    }

    /**
     * @return bool
     */
    private function validate_hr_interview_note_data(
        string $application_id,
        string $interview_date,
        string $rating,
        string $recommendation,
        string $interviewer,
        string $notes,
        string $department_name,
        int $department_account_id,
        string $department_notes
    ): bool {
        $date = DateTime::createFromFormat( '!Y-m-d', $interview_date );
        $interviewer = trim( $interviewer );
        $notes = trim( $notes );
        $department_name = trim( $department_name );
        $department_notes = trim( $department_notes );
        $interviewer_length = function_exists( 'mb_strlen' ) ? mb_strlen( $interviewer, 'UTF-8' ) : strlen( $interviewer );
        $note_length = function_exists( 'mb_strlen' ) ? mb_strlen( $notes, 'UTF-8' ) : strlen( $notes );
        $department_note_length = function_exists( 'mb_strlen' ) ? mb_strlen( $department_notes, 'UTF-8' ) : strlen( $department_notes );
        return ctype_digit( $application_id )
            && '0' !== $application_id
            && $date instanceof DateTime
            && $date->format( 'Y-m-d' ) === $interview_date
            && in_array( $rating, [ 'Sangat Baik', 'Baik', 'Cukup', 'Kurang' ], true )
            && in_array( $recommendation, [ 'Lanjut ke Tahap Berikutnya', 'Tidak Dilanjutkan' ], true )
            && '' !== $interviewer
            && 100 >= $interviewer_length
            && 2000 >= $note_length
            && 100 >= ( function_exists( 'mb_strlen' ) ? mb_strlen( $department_name, 'UTF-8' ) : strlen( $department_name ) )
            && 1000 >= $department_note_length;
    }

    /**
     * Ensure the selected recipient is an active User Department account in the chosen department.
     */
    private function validate_hr_interview_recipient( PDO $connection, string $department_name, int $department_account_id ): void {
        $recipient = $connection->prepare(
            'SELECT u.role_akses_id
             FROM HRS_ROLE_AKSES u
             LEFT JOIN HRS_DEPARTEMEN d ON d.departemen_id = u.departemen_id
             WHERE u.role_akses_id = :account_id
               AND u.peran = \'KEPALA_DEPARTEMEN\'
               AND UPPER(TRIM(u.status_akun)) = \'ACTIVE\'
               AND UPPER(TRIM(NVL(d.nama_departemen, u.nama_departemen))) = UPPER(TRIM(:department))'
        );
        $recipient->execute(
            [
                'account_id' => $department_account_id,
                'department' => $department_name,
            ]
        );
        if ( ! $recipient->fetchColumn() ) {
            throw new InvalidArgumentException( 'Pilih akun User Department aktif yang sesuai dengan departemen tujuan.' );
        }
    }

    private function insert_hr_interview_note_history(
        PDO $connection,
        string $application_id,
        string $interview_date,
        string $rating,
        string $recommendation,
        string $interviewer,
        string $notes,
        string $department_name,
        int $department_account_id,
        string $department_notes
    ): void {
        $history = $connection->prepare(
            'INSERT INTO HRS_RIWAYAT_WAWANCARA_HR (
                RIWAYAT_ID, LAMARAN_ID, TANGGAL_WAWANCARA, NAMA_PEWAWANCARA, PENILAIAN,
                REKOMENDASI, NAMA_DEPARTEMEN_TUJUAN, ROLE_AKSES_ID,
                CATATAN_WAWANCARA, CATATAN_REKOMENDASI, DIBUAT_PADA
            ) VALUES (
                SEQ_HRH_RIWAYAT.NEXTVAL, :application_id, TO_DATE(:interview_date, \'YYYY-MM-DD\'),
                :interviewer, :rating, :recommendation, :department_name, :account_id,
                :notes, :department_notes, CURRENT_TIMESTAMP
            )'
        );
        $history->execute(
            [
                'application_id' => $application_id,
                'interview_date' => $interview_date,
                'interviewer' => trim( $interviewer ),
                'rating' => $rating,
                'recommendation' => $recommendation,
                'department_name' => '' !== trim( $department_name ) ? trim( $department_name ) : null,
                'account_id' => $department_account_id > 0 ? $department_account_id : null,
                'notes' => '' !== trim( $notes ) ? trim( $notes ) : null,
                'department_notes' => '' !== trim( $department_notes ) ? trim( $department_notes ) : null,
            ]
        );
    }

    public function get_applications(): array {
        return $this->fetch_all(
            'SELECT l.lamaran_id AS "id",
                   l.detail_lowongan_id AS "vacancy_id",
                   l.profile_kandidat_id AS "applicant_id",
                   l.status_terkini AS "status",
                   l.status_akhir AS "final_status",
                   NVL(l.created_at, l.tanggal_melamar) AS "created_at",
                   NVL(d.judul_pekerjaan, k.judul_pekerjaan) AS "title",
                   NVL(k.nama_dealer, d.lokasi_penempatan) AS "location",
                   NVL(k.nama_dealer, d.lokasi_penempatan) AS "dealer",
                   d.kategori_kj AS "category",
                   d.nama_departemen AS "department",
                   NVL(p.nama_lengkap, p.nama_kandidat) AS "applicant_name",
                   NVL(p.email, pkl.email) AS "applicant_email",
                   NVL(p.nomor_hp, p.nomor_telepon) AS "applicant_phone",
                   p.provinsi AS "province",
                   (SELECT s.hasil_hr FROM HRS_STATUS_KANDIDAT s WHERE s.kandidat_id = p.kandidat_id AND UPPER(TRIM(s.nama_departemen)) = UPPER(TRIM(d.nama_departemen)) ORDER BY s.status_kandidat_id DESC FETCH FIRST 1 ROW ONLY) AS "hr_result",
                   (SELECT w.status_seleksi FROM HRS_HASIL_WAWANCARA_USER w WHERE w.lamaran_id = l.lamaran_id FETCH FIRST 1 ROW ONLY) AS "user_selection_status",
                   (SELECT w.rekomendasi FROM HRS_HASIL_WAWANCARA_USER w WHERE w.lamaran_id = l.lamaran_id FETCH FIRST 1 ROW ONLY) AS "user_recommendation",
                   (SELECT w.nilai_total FROM HRS_HASIL_WAWANCARA_USER w WHERE w.lamaran_id = l.lamaran_id FETCH FIRST 1 ROW ONLY) AS "user_score",
                   (SELECT TO_CHAR(w.tanggal_wawancara, \'YYYY-MM-DD\') FROM HRS_HASIL_WAWANCARA_USER w WHERE w.lamaran_id = l.lamaran_id FETCH FIRST 1 ROW ONLY) AS "user_interview_date",
                   (SELECT w.nama_pewawancara FROM HRS_HASIL_WAWANCARA_USER w WHERE w.lamaran_id = l.lamaran_id FETCH FIRST 1 ROW ONLY) AS "user_interviewer",
                   (SELECT w.catatan_wawancara FROM HRS_HASIL_WAWANCARA_USER w WHERE w.lamaran_id = l.lamaran_id FETCH FIRST 1 ROW ONLY) AS "user_interview_notes",
                   (SELECT r.keputusan_akhir FROM HRS_REKAPAN_KEPUTUSAN_HR r WHERE r.kandidat_id = p.kandidat_id AND UPPER(TRIM(r.nama_departement)) = UPPER(TRIM(d.nama_departemen)) ORDER BY r.rekapan_keputusan_id DESC FETCH FIRST 1 ROW ONLY) AS "hr_final_decision",
                   (SELECT MAX(r.tahap_seleksi) KEEP (DENSE_RANK LAST ORDER BY r.tanggal_status, r.riwayat_status_id) FROM HRS_RIWAYAT_STATUS_PELAMAR r WHERE r.lamaran_id = l.lamaran_id) AS "stage"
            FROM HRS_LAMARAN l
            LEFT JOIN HRS_DETAIL_INFO_LOWONGAN d ON d.detail_lowongan_id = l.detail_lowongan_id
            LEFT JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.kolom_pendaftaran_id = d.kolom_pendaftaran_id OR k.kolom_pendaftaran_id = l.detail_lowongan_id
            LEFT JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON pkl.profile_kandidat_login = l.profile_kandidat_id
            LEFT JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = pkl.kandidat_id
            ORDER BY NVL(l.created_at, l.tanggal_melamar) DESC, l.lamaran_id DESC'
        );
    }

    /** @return int|null */
    public function get_active_application_count(): ?int {
        $this->last_error = null;
        $rows = $this->fetch_all(
            'SELECT COUNT(*) AS "count"
             FROM HRS_LAMARAN l
             WHERE UPPER(TRIM(NVL(l.status_akhir, l.status_terkini))) NOT IN (
                \'DITERIMA\', \'ACCEPTED\', \'LOLOS\', \'FINAL\', \'COMPLETED\',
                \'DITOLAK\', \'REJECTED\', \'TIDAK DITERIMA\', \'TIDAK_DITERIMA\',
                \'TIDAK LOLOS\', \'TIDAK_LOLOS\', \'TOLAK\'
             )'
        );
        if ( $this->has_error() || empty( $rows[0] ) ) {
            return null;
        }

        return (int) $rows[0]['count'];
    }

    public function get_user_interview_candidates( string $department, int $account_id ): array {
        $department = trim( $department );
        if ( '' === $department || $account_id < 1 ) {
            return [];
        }

        $this->last_error = null;
        return $this->fetch_all(
            'WITH ranked_schedules AS (
                SELECT j.*,
                       ROW_NUMBER() OVER (PARTITION BY j.kandidat_id ORDER BY j.tanggal_wawancara DESC NULLS LAST, j.jadwal_id DESC) AS row_num
                FROM HRS_JADWAL_KANDIDAT j
            ),
            ranked_results AS (
                SELECT w.*,
                       ROW_NUMBER() OVER (PARTITION BY w.lamaran_id ORDER BY w.hasil_user DESC) AS row_num
                FROM HRS_HASIL_WAWANCARA_USER w
            ),
            ranked_decisions AS (
                SELECT r.*,
                       ROW_NUMBER() OVER (PARTITION BY r.kandidat_id, UPPER(TRIM(r.nama_departement)) ORDER BY r.rekapan_keputusan_id DESC) AS row_num
                FROM HRS_REKAPAN_KEPUTUSAN_HR r
            )
            SELECT l.lamaran_id AS "APPLICATION_ID",
                   p.kandidat_id AS "CANDIDATE_ID",
                   NVL(p.nama_lengkap, p.nama_kandidat) AS "CANDIDATE_NAME",
                   NVL(p.email, pkl.email) AS "CANDIDATE_EMAIL",
                   NVL(p.nomor_hp, p.nomor_telepon) AS "CANDIDATE_PHONE",
                   d.judul_pekerjaan AS "POSITION",
                   k.nama_dealer AS "DEALER",
                   NVL(w.status_seleksi, \'\') AS "INTERVIEW_STATUS",
                   CASE UPPER(NVL(r.keputusan_akhir, l.status_akhir))
                       WHEN \'DITERIMA\' THEN \'Diterima\'
                       WHEN \'ACCEPTED\' THEN \'Diterima\'
                       WHEN \'DITOLAK\' THEN \'Tidak Diterima\'
                       WHEN \'REJECTED\' THEN \'Tidak Diterima\'
                       ELSE \'\'
                   END AS "FINAL_DECISION",
                   j.hasil_hr AS "HR_RESULT",
                   hr.status_hasil AS "HR_INTERVIEW_RESULT",
                   hr.rekomendasi AS "HR_RECOMMENDATION",
                   hr.nama_pewawancara AS "HR_INTERVIEWER",
                   hr.catatan_wawancara AS "HR_INTERVIEW_NOTES",
                   TO_CHAR(j.tanggal_wawancara, \'YYYY-MM-DD\') AS "INTERVIEW_DATE",
                   j.jam_wawancara AS "INTERVIEW_TIME",
                   j.metode_wawancara AS "INTERVIEW_METHOD",
                   j.lokasi_wawancara AS "INTERVIEW_LOCATION",
                   w.nilai_total AS "SCORE",
                   w.rekomendasi AS "REKOMENDASI",
                   w.catatan_wawancara AS "INTERVIEW_NOTES",
                   w.nama_pewawancara AS "INTERVIEWER",
                   TO_CHAR(w.tanggal_wawancara, \'YYYY-MM-DD\') AS "INTERVIEW_RESULT_DATE",
                   TO_CHAR(w.submitted_at, \'YYYY-MM-DD HH24:MI:SS\') AS "RESULT_DATE",
                   w.nilai_penampilan AS "SCORE_PRESENTATION",
                   w.nilai_komunikasi AS "SCORE_COMMUNICATION",
                   w.nilai_motivasi AS "SCORE_MOTIVATION",
                   w.nilai_pengalaman AS "SCORE_EXPERIENCE",
                   w.nilai_keterampilan AS "SCORE_KNOWLEDGE",
                   w.nilai_kerjasama AS "SCORE_TEAMWORK",
                   w.nilai_problemsolving AS "SCORE_PROBLEM_SOLVING",
                   w.nilai_adaptasi AS "SCORE_ADAPTABILITY"
            FROM HRS_LAMARAN l
            JOIN HRS_DETAIL_INFO_LOWONGAN d ON d.detail_lowongan_id = l.detail_lowongan_id
            LEFT JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.kolom_pendaftaran_id = d.kolom_pendaftaran_id
            JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON pkl.profile_kandidat_login = l.profile_kandidat_id
            JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = pkl.kandidat_id
            JOIN HRS_HASIL_WAWANCARA_HR hr ON hr.lamaran_id = l.lamaran_id
            JOIN HRS_ROLE_AKSES assigned_account ON assigned_account.role_akses_id = hr.role_akses_id
            LEFT JOIN HRS_DEPARTEMEN assigned_department ON assigned_department.departemen_id = assigned_account.departemen_id
            LEFT JOIN ranked_schedules j ON j.kandidat_id = p.kandidat_id AND j.row_num = 1
            LEFT JOIN ranked_results w ON w.lamaran_id = l.lamaran_id AND w.row_num = 1
            LEFT JOIN ranked_decisions r ON r.kandidat_id = p.kandidat_id AND UPPER(TRIM(r.nama_departement)) = UPPER(TRIM(NVL(assigned_department.nama_departemen, assigned_account.nama_departemen))) AND r.row_num = 1
            WHERE UPPER(TRIM(NVL(assigned_department.nama_departemen, assigned_account.nama_departemen))) = UPPER(TRIM(:department))
              AND (
                    UPPER(TRIM(l.status_terkini)) = \'INTERVIEW_USER\'
                    OR UPPER(TRIM(l.status_akhir)) IN (\'DITERIMA\', \'ACCEPTED\', \'DITOLAK\', \'REJECTED\')
              )
              AND hr.status_hasil = \'LOLOS\'
              AND hr.role_akses_id = :account_id
              AND assigned_account.peran = \'KEPALA_DEPARTEMEN\'
              AND UPPER(TRIM(assigned_account.status_akun)) = \'ACTIVE\'
            ORDER BY NVL(l.created_at, l.tanggal_melamar) DESC, l.lamaran_id DESC',
            [
                'department' => $department,
                'account_id' => $account_id,
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_user_interview_results( string $department, int $account_id ): array {
        return array_values(
            array_filter(
                $this->get_user_interview_candidates( $department, $account_id ),
                static fn ( array $row ): bool => '' !== trim( (string) ( $row['INTERVIEW_STATUS'] ?? '' ) )
            )
        );
    }

    /**
     * Load applicant details only for a candidate forwarded to this department.
     *
     * @return array{application: array<string, mixed>, profile: array<string, mixed>, answers: array<int, array<string, mixed>>, history: array<int, array<string, mixed>>}|null
     */
    public function get_user_department_candidate_detail( string $application_id, int $account_id ): ?array {
        if ( ! ctype_digit( $application_id ) || '0' === $application_id || $account_id < 1 ) {
            return null;
        }

        $this->last_error = null;
        $rows = $this->fetch_all(
            'SELECT l.lamaran_id AS "application_id",
                    TO_CHAR(NVL(l.created_at, l.tanggal_melamar), \'YYYY-MM-DD HH24:MI:SS\') AS "application_date",
                    l.status_terkini AS "application_status",
                    l.status_akhir AS "final_status",
                    d.judul_pekerjaan AS "position",
                    k.nama_dealer AS "dealer",
                    p.kandidat_id AS "candidate_id",
                    NVL(p.nama_lengkap, p.nama_kandidat) AS "candidate_name",
                    NVL(p.email, pkl.email) AS "candidate_email",
                    NVL(p.nomor_hp, p.nomor_telepon) AS "candidate_phone",
                    p.jenis_kelamin AS "gender",
                    p.tempat_lahir AS "birth_place",
                    TO_CHAR(p.tanggal_lahir, \'YYYY-MM-DD\') AS "birth_date",
                    p.alamat_domisili AS "address",
                    p.kota AS "city",
                    p.provinsi AS "province",
                    p.pendidikan AS "education",
                    hr.status_hasil AS "hr_interview_result",
                    hr.rekomendasi AS "hr_recommendation",
                    hr.nama_pewawancara AS "hr_interviewer",
                    hr.catatan_wawancara AS "hr_interview_notes",
                    assigned_account.username AS "department_account_username",
                    assigned_account.email AS "department_account_email",
                    w.status_seleksi AS "user_interview_result",
                    w.rekomendasi AS "user_recommendation",
                    w.nilai_total AS "user_interview_score",
                    w.nama_pewawancara AS "user_interviewer",
                    w.catatan_wawancara AS "user_interview_notes",
                    TO_CHAR(w.tanggal_wawancara, \'YYYY-MM-DD\') AS "user_interview_date",
                    (SELECT h.nilai
                     FROM HRS_HASIL_TES_PSIKOLOGI h
                     WHERE h.kandidat_id = p.kandidat_id
                     ORDER BY h.dikumpulkan_pada DESC NULLS LAST, h.hasil_tes_psikologi_id DESC
                     FETCH FIRST 1 ROW ONLY) AS "psychotest_score",
                    pd.data_profil AS "profile_details"
             FROM HRS_LAMARAN l
             JOIN HRS_DETAIL_INFO_LOWONGAN d ON d.detail_lowongan_id = l.detail_lowongan_id
             LEFT JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.kolom_pendaftaran_id = d.kolom_pendaftaran_id
             JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON pkl.profile_kandidat_login = l.profile_kandidat_id
             JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = pkl.kandidat_id
             LEFT JOIN HRS_PROFIL_KANDIDAT_DETAIL pd ON pd.kandidat_id = p.kandidat_id
             JOIN HRS_HASIL_WAWANCARA_HR hr ON hr.lamaran_id = l.lamaran_id
             JOIN HRS_ROLE_AKSES assigned_account ON assigned_account.role_akses_id = hr.role_akses_id
             LEFT JOIN HRS_HASIL_WAWANCARA_USER w ON w.lamaran_id = l.lamaran_id
             WHERE l.lamaran_id = :application_id
               AND hr.status_hasil = \'LOLOS\'
               AND hr.role_akses_id = :account_id
               AND assigned_account.peran = \'KEPALA_DEPARTEMEN\'
               AND UPPER(TRIM(assigned_account.status_akun)) = \'ACTIVE\'
             FETCH FIRST 1 ROW ONLY',
            [
                'application_id' => $application_id,
                'account_id' => $account_id,
            ]
        );
        if ( empty( $rows[0] ) ) {
            return null;
        }

        $application = $rows[0];
        $decoded_profile_details = $this->decode_applicant_profile_details( $application['profile_details'] ?? '' );
        $profile_details = array_intersect_key(
            $decoded_profile_details,
            array_flip( [ 'education_level', 'institution', 'major', 'gpa', 'graduation_year', 'work_experiences' ] )
        );
        $profile_documents = is_array( $decoded_profile_details['documents'] ?? null ) ? $decoded_profile_details['documents'] : [];
        if ( is_array( $profile_documents['resume'] ?? null ) ) {
            $profile_details['documents'] = [ 'resume' => $profile_documents['resume'] ];
        }
        $profile_answers = is_array( $decoded_profile_details['application_answers'] ?? null ) ? $decoded_profile_details['application_answers'] : [];
        $profile_details['application_answers'] = array_intersect_key(
            $profile_answers,
            array_flip(
                array_filter(
                    array_keys( $profile_answers ),
                    static fn ( $key ): bool => is_string( $key ) && 1 === preg_match( '/\Apendidikan_formal_\d+_(?:tingkat|nama_sekolah|program_studi|ipk|tahun_lulus)\z/i', $key )
                )
            )
        );
        unset( $application['profile_details'] );
        $application_id = (string) ( $application['application_id'] ?? '' );

        return [
            'application' => $application,
            'profile' => [
                'name' => (string) ( $application['candidate_name'] ?? '' ),
                'email' => (string) ( $application['candidate_email'] ?? '' ),
                'phone' => (string) ( $application['candidate_phone'] ?? '' ),
                'gender' => (string) ( $application['gender'] ?? '' ),
                'birth_place' => (string) ( $application['birth_place'] ?? '' ),
                'birth_date' => (string) ( $application['birth_date'] ?? '' ),
                'address' => (string) ( $application['address'] ?? '' ),
                'city' => (string) ( $application['city'] ?? '' ),
                'province' => (string) ( $application['province'] ?? '' ),
                'education' => (string) ( $application['education'] ?? '' ),
                'profile_details' => $profile_details,
            ],
            'answers' => '' !== $application_id ? $this->get_department_profile_answers( $this->get_admin_application_answers( $application_id ) ) : [],
            'history' => '' !== $application_id ? $this->get_selection_history( $application_id ) : [],
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @return array{success: bool, error?: string}
     */
    public function save_user_interview_result( int $application_id, int $account_id, array $result ): array {
        $department = trim( (string) ( $result['department'] ?? '' ) );
        $interviewer = trim( (string) ( $result['interviewer'] ?? '' ) );
        $scores = is_array( $result['scores'] ?? null ) ? $result['scores'] : [];
        $score_keys = [ 'presentation', 'communication', 'motivation', 'experience', 'knowledge', 'teamwork', 'problem_solving', 'adaptability' ];
        $selection_status = trim( (string) ( $result['selection_status'] ?? '' ) );
        $recommendation = trim( (string) ( $result['recommendation'] ?? '' ) );
        $notes = trim( (string) ( $result['notes'] ?? '' ) );
        $valid_selection_statuses = [ 'Lolos Wawancara User', 'Tidak Lolos Wawancara User' ];
        $valid_recommendations = [ 'Sangat Baik', 'Baik', 'Dipertimbangkan', 'Tidak Dilanjutkan' ];

        if ( $application_id < 1 || $account_id < 1 || '' === $department || '' === $interviewer || ! in_array( $selection_status, $valid_selection_statuses, true ) || ! in_array( $recommendation, $valid_recommendations, true ) ) {
            return [ 'success' => false, 'error' => 'Data hasil wawancara tidak valid.' ];
        }
        foreach ( $score_keys as $key ) {
            if ( ! isset( $scores[ $key ] ) || ! is_numeric( $scores[ $key ] ) || (int) $scores[ $key ] < 1 || (int) $scores[ $key ] > 5 ) {
                return [ 'success' => false, 'error' => 'Nilai setiap aspek wawancara harus antara 1 dan 5.' ];
            }
        }
        $note_length = function_exists( 'mb_strlen' ) ? mb_strlen( $notes, 'UTF-8' ) : strlen( $notes );
        if ( 250 < $note_length ) {
            return [ 'success' => false, 'error' => 'Catatan wawancara maksimal 250 karakter.' ];
        }

        $score_total = (int) floor( array_sum( array_map( 'intval', $scores ) ) / ( count( $score_keys ) * 5 ) * 100 );
        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $assignment = $connection->prepare(
                'SELECT p.kandidat_id
                 FROM HRS_LAMARAN l
                 JOIN HRS_DETAIL_INFO_LOWONGAN d ON d.detail_lowongan_id = l.detail_lowongan_id
                 JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON pkl.profile_kandidat_login = l.profile_kandidat_id
                 JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = pkl.kandidat_id
                 JOIN HRS_HASIL_WAWANCARA_HR hr ON hr.lamaran_id = l.lamaran_id
                 JOIN HRS_ROLE_AKSES u ON u.role_akses_id = hr.role_akses_id
                 LEFT JOIN HRS_DEPARTEMEN assigned_department ON assigned_department.departemen_id = u.departemen_id
                 WHERE l.lamaran_id = :application_id
                   AND UPPER(TRIM(l.status_terkini)) = \'INTERVIEW_USER\'
                   AND hr.status_hasil = \'LOLOS\'
                   AND hr.role_akses_id = :account_id
                   AND u.peran = \'KEPALA_DEPARTEMEN\'
                   AND UPPER(TRIM(u.status_akun)) = \'ACTIVE\'
                   AND UPPER(TRIM(NVL(assigned_department.nama_departemen, u.nama_departemen))) = UPPER(TRIM(:department_name))
                 FOR UPDATE OF l.status_terkini'
            );
            $assignment->execute(
                [
                    'application_id' => $application_id,
                    'account_id' => $account_id,
                    'department_name' => $department,
                ]
            );
            $candidate_id = (int) $assignment->fetchColumn();
            if ( $candidate_id < 1 ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'Lamaran tidak diberikan kepada akun User Department ini atau tidak lagi dalam tahap wawancara.' ];
            }

            $status_lookup = $connection->prepare(
                'SELECT MAX(status_kandidat_id) FROM HRS_STATUS_KANDIDAT WHERE kandidat_id = :candidate_id AND UPPER(TRIM(nama_departemen)) = UPPER(TRIM(:department_name))'
            );
            $status_lookup->execute( [ 'candidate_id' => $candidate_id, 'department_name' => $department ] );
            $status_candidate_id = $status_lookup->fetchColumn();

            $update = $connection->prepare(
                'UPDATE HRS_HASIL_WAWANCARA_USER
                 SET lamaran_id = :application_id,
                     nama_pewawancara = :interviewer, nilai_penampilan = :presentation,
                     nilai_komunikasi = :communication, nilai_motivasi = :motivation,
                     nilai_pengalaman = :experience, nilai_keterampilan = :knowledge,
                     nilai_kerjasama = :teamwork, nilai_problemsolving = :problem_solving,
                     nilai_adaptasi = :adaptability, nilai_total = :total_score,
                     rekomendasi = :recommendation, catatan_wawancara = :notes,
                     tanggal_wawancara = CURRENT_DATE, submitted_at = CURRENT_DATE,
                     status_kandidat_id = :status_candidate_id, status_seleksi = :selection_status,
                     nama_kandidat = (SELECT NVL(p.nama_lengkap, p.nama_kandidat) FROM HRS_PROFIL_KANDIDAT p WHERE p.kandidat_id = :candidate_for_name)
                 WHERE hasil_user = (
                     SELECT MAX(hasil_user) FROM HRS_HASIL_WAWANCARA_USER
                     WHERE lamaran_id = :application_for_update
                 )'
            );
            $parameters = [
                'application_id' => $application_id,
                'interviewer' => $interviewer,
                'presentation' => (int) $scores['presentation'],
                'communication' => (int) $scores['communication'],
                'motivation' => (int) $scores['motivation'],
                'experience' => (int) $scores['experience'],
                'knowledge' => (int) $scores['knowledge'],
                'teamwork' => (int) $scores['teamwork'],
                'problem_solving' => (int) $scores['problem_solving'],
                'adaptability' => (int) $scores['adaptability'],
                'total_score' => $score_total,
                'recommendation' => $recommendation,
                'notes' => $notes,
                'status_candidate_id' => $status_candidate_id ? (int) $status_candidate_id : null,
                'selection_status' => $selection_status,
                'candidate_for_name' => $candidate_id,
                'application_for_update' => $application_id,
            ];
            $update->execute( $parameters );
            if ( 0 === $update->rowCount() ) {
                $insert = $connection->prepare(
                    'INSERT INTO HRS_HASIL_WAWANCARA_USER (
                        lamaran_id, kandidat_id, nama_pewawancara, nama_departemen, nilai_penampilan,
                        nilai_komunikasi, nilai_motivasi, nilai_pengalaman, nilai_keterampilan,
                        nilai_kerjasama, nilai_problemsolving, nilai_adaptasi, nilai_total,
                        rekomendasi, catatan_wawancara, tanggal_wawancara, submitted_at,
                        status_kandidat_id, status_seleksi, nama_kandidat
                    )
                    SELECT :application_id, p.kandidat_id, :interviewer, :department_name, :presentation,
                           :communication, :motivation, :experience, :knowledge, :teamwork,
                           :problem_solving, :adaptability, :total_score, :recommendation,
                           :notes, CURRENT_DATE, CURRENT_DATE, :status_candidate_id,
                           :selection_status, NVL(p.nama_lengkap, p.nama_kandidat)
                    FROM HRS_PROFIL_KANDIDAT p WHERE p.kandidat_id = :candidate_id'
                );
                $insert->execute(
                    [
                        'application_id' => $application_id,
                        'interviewer' => $interviewer,
                        'department_name' => $department,
                        'presentation' => (int) $scores['presentation'],
                        'communication' => (int) $scores['communication'],
                        'motivation' => (int) $scores['motivation'],
                        'experience' => (int) $scores['experience'],
                        'knowledge' => (int) $scores['knowledge'],
                        'teamwork' => (int) $scores['teamwork'],
                        'problem_solving' => (int) $scores['problem_solving'],
                        'adaptability' => (int) $scores['adaptability'],
                        'total_score' => $score_total,
                        'recommendation' => $recommendation,
                        'notes' => $notes,
                        'status_candidate_id' => $status_candidate_id ? (int) $status_candidate_id : null,
                        'selection_status' => $selection_status,
                        'candidate_id' => $candidate_id,
                    ]
                );
            }

            $update_candidate_status = $connection->prepare(
                'UPDATE HRS_STATUS_KANDIDAT SET status_kandidat = :selection_status
                 WHERE kandidat_id = :candidate_id
                   AND UPPER(TRIM(nama_departemen)) = UPPER(TRIM(:department_name))'
            );
            $update_candidate_status->execute(
                [
                    'selection_status' => $selection_status,
                    'candidate_id' => $candidate_id,
                    'department_name' => $department,
                ]
            );

            $history = $connection->prepare(
                'INSERT INTO HRS_RIWAYAT_STATUS_PELAMAR (lamaran_id, tahap_seleksi, status_seleksi, tanggal_status, keterangan_pelamar)
                 VALUES (:application_id, \'INTERVIEW_USER\', :selection_status, CURRENT_DATE, :note)'
            );
            $history->execute(
                [
                    'application_id' => $application_id,
                    'selection_status' => $selection_status,
                    'note' => 'Hasil User: ' . $selection_status . '; ' . $recommendation,
                ]
            );
            $connection->commit();
            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'User interview result save failed', $exception );
            return [ 'success' => false, 'error' => 'Hasil wawancara gagal disimpan ke Oracle.' ];
        }
    }

    /**
     * Save the HR's final decision, recruitment status, and applicant timeline atomically.
     *
     * @return array{success: bool, error?: string}
     */
    public function save_final_decision( string $application_id, string $decision, ?int $role_access_id = null ): array {
        $decision = strtoupper( trim( $decision ) );
        if ( ! ctype_digit( $application_id ) || '0' === $application_id || ! in_array( $decision, [ 'DITERIMA', 'DITOLAK' ], true ) || ( null !== $role_access_id && $role_access_id < 1 ) ) {
            return [ 'success' => false, 'error' => 'Keputusan akhir tidak valid.' ];
        }

        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $candidate_lookup = $connection->prepare(
                'SELECT p.kandidat_id
                 FROM HRS_LAMARAN l
                 JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON pkl.profile_kandidat_login = l.profile_kandidat_id
                 JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = pkl.kandidat_id
                 WHERE l.lamaran_id = :application_id'
            );
            $candidate_lookup->execute( [ 'application_id' => $application_id ] );
            $candidate_id = $candidate_lookup->fetchColumn();
            if ( ! $candidate_id ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'Lamaran tidak ditemukan.' ];
            }
            $candidate_lock = $connection->prepare( 'SELECT kandidat_id FROM HRS_PROFIL_KANDIDAT WHERE kandidat_id = :candidate_id FOR UPDATE' );
            $candidate_lock->execute( [ 'candidate_id' => (int) $candidate_id ] );
            if ( ! $candidate_lock->fetchColumn() ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'Profil kandidat tidak ditemukan.' ];
            }
            $application = $connection->prepare(
                'SELECT l.lamaran_id AS "application_id", p.kandidat_id AS "candidate_id",
                        NVL(p.nama_lengkap, p.nama_kandidat) AS "candidate_name",
                        d.nama_departemen AS "department_name",
                        UPPER(TRIM(l.status_terkini)) AS "application_status",
                        (SELECT w.hasil_user FROM HRS_HASIL_WAWANCARA_USER w
                         WHERE w.kandidat_id = p.kandidat_id
                           AND UPPER(TRIM(w.nama_departemen)) = UPPER(TRIM(d.nama_departemen))
                         ORDER BY w.hasil_user DESC FETCH FIRST 1 ROW ONLY) AS "user_result_id",
                        (SELECT w.nilai_total FROM HRS_HASIL_WAWANCARA_USER w
                         WHERE w.kandidat_id = p.kandidat_id
                           AND UPPER(TRIM(w.nama_departemen)) = UPPER(TRIM(d.nama_departemen))
                         ORDER BY w.hasil_user DESC FETCH FIRST 1 ROW ONLY) AS "user_score",
                        (SELECT w.rekomendasi FROM HRS_HASIL_WAWANCARA_USER w
                         WHERE w.kandidat_id = p.kandidat_id
                           AND UPPER(TRIM(w.nama_departemen)) = UPPER(TRIM(d.nama_departemen))
                         ORDER BY w.hasil_user DESC FETCH FIRST 1 ROW ONLY) AS "user_recommendation"
                 FROM HRS_LAMARAN l
                 JOIN HRS_DETAIL_INFO_LOWONGAN d ON d.detail_lowongan_id = l.detail_lowongan_id
                 JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON pkl.profile_kandidat_login = l.profile_kandidat_id
                 JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = pkl.kandidat_id
                 WHERE l.lamaran_id = :application_id'
            );
            $application->execute( [ 'application_id' => $application_id ] );
            $candidate = $application->fetch( PDO::FETCH_ASSOC );
            if ( ! $candidate ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'Lamaran tidak ditemukan.' ];
            }
            if ( ! in_array( (string) $candidate['application_status'], [ 'INTERVIEW_USER', 'FINAL', 'DITERIMA', 'DITOLAK' ], true ) ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'Keputusan akhir hanya dapat dibuat setelah kandidat mencapai tahap wawancara departemen.' ];
            }
            if ( empty( $candidate['user_result_id'] ) ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'Keputusan akhir hanya dapat dibuat setelah hasil wawancara User tersimpan.' ];
            }

            $existing_decision = $connection->prepare(
                'UPDATE HRS_REKAPAN_KEPUTUSAN_HR
                 SET nama_kandidat = :candidate_name, tanggal_wawancara = CURRENT_DATE,
                     total_nilai = :user_score, rekomendasi_user = :user_recommendation,
                     keputusan_akhir = :decision, total_status_rekomterima = :recommended,
                     diterima_hr = :accepted, menunggu_keputusan = 0,
                     role_akses_id = :role_access_id
                 WHERE rekapan_keputusan_id = (
                     SELECT MAX(rekapan_keputusan_id) FROM HRS_REKAPAN_KEPUTUSAN_HR
                     WHERE kandidat_id = :candidate_for_update
                       AND UPPER(TRIM(nama_departement)) = UPPER(TRIM(:department_for_update))
                 )'
            );
            $accepted = 'DITERIMA' === $decision ? 1 : 0;
            $parameters = [
                'candidate_name' => (string) $candidate['candidate_name'],
                'user_score' => (int) $candidate['user_score'],
                'user_recommendation' => (string) $candidate['user_recommendation'],
                'decision' => $decision,
                'recommended' => $accepted,
                'accepted' => $accepted,
                'role_access_id' => $role_access_id,
                'candidate_for_update' => (int) $candidate['candidate_id'],
                'department_for_update' => (string) $candidate['department_name'],
            ];
            $existing_decision->execute( $parameters );
            if ( 0 === $existing_decision->rowCount() ) {
                $insert_decision = $connection->prepare(
                    'INSERT INTO HRS_REKAPAN_KEPUTUSAN_HR (
                        kandidat_id, nama_kandidat, nama_departement, tanggal_wawancara,
                        total_nilai, rekomendasi_user, keputusan_akhir,
                        total_status_rekomterima, diterima_hr, menunggu_keputusan, role_akses_id
                    ) VALUES (
                        :candidate_id, :candidate_name, :department_name, CURRENT_DATE,
                        :user_score, :user_recommendation, :decision,
                        :recommended, :accepted, 0, :role_access_id
                    )'
                );
                $insert_decision->execute(
                    [
                        'candidate_id' => (int) $candidate['candidate_id'],
                        'candidate_name' => (string) $candidate['candidate_name'],
                        'department_name' => (string) $candidate['department_name'],
                        'user_score' => (int) $candidate['user_score'],
                        'user_recommendation' => (string) $candidate['user_recommendation'],
                        'decision' => $decision,
                        'recommended' => $accepted,
                        'accepted' => $accepted,
                        'role_access_id' => $role_access_id,
                    ]
                );
            }

            $status_update = $connection->prepare(
                'UPDATE HRS_LAMARAN
                 SET status_terkini = :decision, status_akhir = :final_status, updated_at = CURRENT_TIMESTAMP
                 WHERE lamaran_id = :application_id'
            );
            $status_update->execute(
                [
                    'decision' => $decision,
                    'final_status' => $decision,
                    'application_id' => $application_id,
                ]
            );
            if ( 1 !== $status_update->rowCount() ) {
                throw new RuntimeException( 'The application status was not updated.' );
            }

            $history = $connection->prepare(
                'INSERT INTO HRS_RIWAYAT_STATUS_PELAMAR (lamaran_id, tahap_seleksi, status_seleksi, tanggal_status, keterangan_pelamar)
                 VALUES (:application_id, \'FINAL\', :decision, CURRENT_DATE, :note)'
            );
            $history->execute(
                [
                    'application_id' => $application_id,
                    'decision' => $decision,
                    'note' => 'Keputusan akhir HR: ' . ( 'DITERIMA' === $decision ? 'Diterima.' : 'Tidak diterima.' ),
                ]
            );

            $connection->commit();
            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'Final recruitment decision save failed', $exception );
            return [ 'success' => false, 'error' => 'Keputusan akhir gagal disimpan ke Oracle.' ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_psychotest_question_bank(): array {
        $questions = [];
        foreach ( $this->load_psychotest_catalog( true ) as $test ) {
            foreach ( $test['questions'] as $question ) {
                $correct_answers = array_values(
                    array_map(
                        static fn ( array $choice ): string => (int) $choice['is_correct'] === 1
                            ? (string) $choice['text']
                            : '',
                        $question['choices']
                    )
                );
                $correct_answers = array_values( array_filter( $correct_answers, static fn ( string $answer ): bool => '' !== $answer ) );
                $questions[] = [
                    'id' => $question['id'],
                    'test_id' => $test['ID'],
                    'test' => $test['test_key'],
                    'subtest' => $test['subtest'] ?? '',
                    'prompt' => 'disc' === $test['test_key'] || 'papi' === $test['test_key']
                        ? implode( "\n", $question['items'] )
                        : $question['prompt'],
                    'type' => $question['type'],
                    'choices' => array_column( $question['choices'], 'text' ),
                    'answer' => implode( ' | ', $correct_answers ),
                    'status' => (int) $test['active'] === 1 ? 'active' : 'inactive',
                    'updated' => (string) $test['updated_at'],
                    'number' => $question['number'],
                ];
            }
        }
        return $questions;
    }

    /**
     * @param array<string, mixed> $question
     * @return array{success: bool, error?: string}
     */
    public function save_iq_question( array $question ): array {
        $question_id = absint( $question['id'] ?? 0 );
        $prompt = trim( (string) ( $question['prompt'] ?? '' ) );
        $subtest = sanitize_key( (string) ( $question['subtest'] ?? '' ) );
        $type = sanitize_key( (string) ( $question['type'] ?? '' ) );
        $choices = array_values( array_filter( (array) ( $question['choices'] ?? [] ), static fn ( $choice ): bool => is_string( $choice ) && '' !== trim( $choice ) ) );
        $answer = trim( (string) ( $question['answer'] ?? '' ) );
        if ( $question_id < 1 || '' === $prompt || 4000 < strlen( $prompt ) || ! in_array( $subtest, [ 'iq_numbers', 'iq_assembly', 'iq_words', 'iq_abstraction' ], true ) || ! in_array( $type, [ 'multiple_choice', 'multi_select' ], true ) || count( $choices ) < 2 || '' === $answer ) {
            return [ 'success' => false, 'error' => 'Data soal IQ tidak valid.' ];
        }

        $existing = $this->find_psychotest_question( $question_id );
        if ( ! $existing || 'iq' !== $existing['test_key'] || $subtest !== $existing['subtest'] || $type !== $existing['type'] || count( $choices ) !== count( $existing['choices'] ) ) {
            return [ 'success' => false, 'error' => 'Soal atau jumlah pilihan tidak sesuai dengan struktur tes.' ];
        }

        $answer_values = array_values( array_filter( array_map( 'trim', explode( '|', $answer ) ), static fn ( string $value ): bool => '' !== $value ) );
        if ( ! $answer_values || 2 < count( $answer_values ) || ( 'multiple_choice' === $type && 1 !== count( $answer_values ) ) ) {
            return [ 'success' => false, 'error' => 'Jawaban benar tidak sesuai dengan tipe soal.' ];
        }
        $correct_indexes = [];
        foreach ( $answer_values as $answer_value ) {
            $matching_indexes = [];
            foreach ( $existing['choices'] as $index => $choice ) {
                if ( $answer_value === (string) $choice['id'] || $answer_value === (string) $choice['text'] || ( (string) ( $index + 1 ) === $answer_value ) ) {
                    $matching_indexes[] = $index;
                }
            }
            if ( 1 !== count( $matching_indexes ) || in_array( $matching_indexes[0], $correct_indexes, true ) ) {
                return [ 'success' => false, 'error' => 'Jawaban benar harus cocok secara unik dengan pilihan.' ];
            }
            $correct_indexes[] = $matching_indexes[0];
        }

        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $update_question = $connection->prepare(
                'UPDATE HRS_PERTANYAAN_PSIKOTES SET TEKS_PERTANYAAN = TO_CLOB(:prompt) WHERE PERTANYAAN_PSIKOTES_ID = :question_id'
            );
            $update_question->execute( [ 'prompt' => $prompt, 'question_id' => $question_id ] );
            if ( 1 !== $update_question->rowCount() ) {
                throw new InvalidArgumentException( 'Question not found.' );
            }
            $update_choice = $connection->prepare(
                'UPDATE HRS_JAWABAN_PSIKOTES SET JAWABAN_TEKS = TO_CLOB(:answer_text), APAKAH_BENAR = :is_correct WHERE JAWABAN_PSIKOTES_ID = :answer_id AND PERTANYAAN_PSIKOTES_ID = :question_id'
            );
            foreach ( $existing['choices'] as $index => $choice ) {
                $update_choice->execute(
                    [
                        'answer_text' => trim( $choices[ $index ] ),
                        'is_correct' => in_array( $index, $correct_indexes, true ) ? 1 : 0,
                        'answer_id' => (int) $choice['id'],
                        'question_id' => $question_id,
                    ]
                );
                if ( 1 !== $update_choice->rowCount() ) {
                    throw new InvalidArgumentException( 'Question choice not found.' );
                }
            }
            $this->touch_psychotest( $connection, (int) $existing['test_id'] );
            $connection->commit();
            return [ 'success' => true ];
        } catch ( InvalidArgumentException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            return [ 'success' => false, 'error' => 'Soal atau pilihan jawaban tidak ditemukan.' ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'IQ question update failed', $exception );
            return [ 'success' => false, 'error' => 'Soal IQ gagal disimpan ke Oracle.' ];
        }
    }

    /**
     * @param array<string, mixed> $question
     * @return array{success: bool, error?: string}
     */
    public function save_pauli_question( array $question ): array {
        return $this->save_psychotest_prompt( $question, 'pauli' );
    }

    /**
     * @param array<string, mixed> $question
     * @return array{success: bool, error?: string}
     */
    public function save_personality_question( array $question ): array {
        $test_key = sanitize_key( (string) ( $question['test'] ?? '' ) );
        $items = array_values( array_filter( (array) ( $question['items'] ?? [] ), static fn ( $item ): bool => is_string( $item ) && '' !== trim( $item ) ) );
        $expected_items = 'disc' === $test_key ? 4 : 2;
        if ( ! in_array( $test_key, [ 'disc', 'papi' ], true ) || $expected_items !== count( $items ) ) {
            return [ 'success' => false, 'error' => 'Pernyataan tes kepribadian tidak valid.' ];
        }
        $question['test'] = $test_key;
        $question['prompt'] = wp_json_encode( array_map( 'trim', $items ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( ! is_string( $question['prompt'] ) || 4000 < strlen( $question['prompt'] ) ) {
            return [ 'success' => false, 'error' => 'Pernyataan tes terlalu panjang.' ];
        }
        return $this->save_psychotest_prompt( $question, $test_key );
    }

    /**
     * @param array<string, mixed> $question
     * @return array{success: bool, error?: string}
     */
    public function create_psychotest_question( array $question ): array {
        $test_key = sanitize_key( (string) ( $question['test'] ?? '' ) );
        $subtest = sanitize_key( (string) ( $question['subtest'] ?? '' ) );
        $type = sanitize_key( (string) ( $question['type'] ?? '' ) );
        $prompt = trim( (string) ( $question['prompt'] ?? '' ) );
        $choices = array_values( array_filter( (array) ( $question['choices'] ?? [] ), static fn ( $choice ): bool => is_string( $choice ) && '' !== trim( $choice ) ) );
        $items = array_values( array_filter( (array) ( $question['items'] ?? [] ), static fn ( $item ): bool => is_string( $item ) && '' !== trim( $item ) ) );
        $answer = trim( (string) ( $question['answer'] ?? '' ) );

        if ( '' === $prompt || 4000 < strlen( $prompt ) ) {
            return [ 'success' => false, 'error' => 'Isi soal tidak valid.' ];
        }

        $catalog = $this->load_psychotest_catalog( true );
        if ( $this->has_error() ) {
            return [ 'success' => false, 'error' => 'Bank soal tidak dapat dibaca dari Oracle.' ];
        }
        $test_id = 0;
        foreach ( $catalog as $test ) {
            if ( $test_key !== $test['test_key'] || ( 'iq' === $test_key && $subtest !== $test['subtest'] ) ) {
                continue;
            }
            $test_id = (int) $test['ID'];
            break;
        }
        if ( $test_id < 1 ) {
            return [ 'success' => false, 'error' => 'Jenis atau subtes psikotes tidak ditemukan di Oracle.' ];
        }

        $question_type = '';
        $encoded_prompt = $prompt;
        $correct_indexes = [];
        if ( 'iq' === $test_key ) {
            if ( ! in_array( $type, [ 'multiple_choice', 'multi_select' ], true )
                || count( $choices ) < 2
                || '' === $answer ) {
                return [ 'success' => false, 'error' => 'Soal IQ harus memiliki tipe, minimal dua pilihan, dan jawaban benar.' ];
            }
            $answer_values = array_values( array_filter( array_map( 'trim', explode( '|', $answer ) ), static fn ( string $value ): bool => '' !== $value ) );
            if ( ! $answer_values || 2 < count( $answer_values ) || ( 'multiple_choice' === $type && 1 !== count( $answer_values ) ) ) {
                return [ 'success' => false, 'error' => 'Jawaban benar tidak sesuai dengan tipe soal.' ];
            }
            foreach ( $answer_values as $answer_value ) {
                $matching_indexes = [];
                foreach ( $choices as $index => $choice ) {
                    if ( $answer_value === (string) $choice || (string) ( $index + 1 ) === $answer_value ) {
                        $matching_indexes[] = $index;
                    }
                }
                if ( 1 !== count( $matching_indexes ) || in_array( $matching_indexes[0], $correct_indexes, true ) ) {
                    return [ 'success' => false, 'error' => 'Jawaban benar harus cocok secara unik dengan pilihan.' ];
                }
                $correct_indexes[] = $matching_indexes[0];
            }
            $question_type = 'MULTI_SELECT' === strtoupper( $type ) ? 'MULTI_SELECT' : 'MULTIPLE_CHOICE';
        } elseif ( 'pauli' === $test_key ) {
            $question_type = 'NUMERIC';
        } elseif ( in_array( $test_key, [ 'disc', 'papi' ], true ) ) {
            $expected_items = 'disc' === $test_key ? 4 : 2;
            if ( $expected_items !== count( $items ) ) {
                return [ 'success' => false, 'error' => 'DISC memerlukan empat pernyataan dan PAPI Kostick memerlukan dua.' ];
            }
            $encoded_prompt = wp_json_encode( array_map( 'trim', $items ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            if ( ! is_string( $encoded_prompt ) || 4000 < strlen( $encoded_prompt ) ) {
                return [ 'success' => false, 'error' => 'Pernyataan tes terlalu panjang.' ];
            }
            $question_type = 'PAIRED';
        } else {
            return [ 'success' => false, 'error' => 'Jenis tes tidak valid.' ];
        }

        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $order_query = $connection->prepare(
                'SELECT NVL(MAX(DISPLAY_ORDER), 0) + 1 FROM HRS_PERTANYAAN_PSIKOTES WHERE TES_PSIKOLOGI_ID = :test_id'
            );
            $order_query->execute( [ 'test_id' => $test_id ] );
            $display_order = (int) $order_query->fetchColumn();
            $insert_question = $connection->prepare(
                'INSERT INTO HRS_PERTANYAAN_PSIKOTES (TES_PSIKOLOGI_ID, TEKS_PERTANYAAN, JENIS_PERTANYAAN, NILAI_PERTANYAAN, DISPLAY_ORDER) VALUES (:test_id, TO_CLOB(:prompt), :question_type, 1, :display_order) RETURNING PERTANYAAN_PSIKOTES_ID INTO :question_id'
            );
            $question_id = 0;
            $insert_question->bindValue( ':test_id', $test_id, PDO::PARAM_INT );
            $insert_question->bindValue( ':prompt', $encoded_prompt, PDO::PARAM_STR );
            $insert_question->bindValue( ':question_type', $question_type, PDO::PARAM_STR );
            $insert_question->bindValue( ':display_order', $display_order, PDO::PARAM_INT );
            $insert_question->bindParam( ':question_id', $question_id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 19 );
            $insert_question->execute();
            if ( $question_id < 1 ) {
                throw new RuntimeException( 'Oracle did not return a psychotest question ID.' );
            }

            if ( 'iq' === $test_key ) {
                $insert_choice = $connection->prepare(
                    'INSERT INTO HRS_JAWABAN_PSIKOTES (PERTANYAAN_PSIKOTES_ID, APAKAH_BENAR, URUTAN_TAMPILAN, JAWABAN_TEKS, NILAI, DIJAWAB_PADA) VALUES (:question_id, :is_correct, :display_order, TO_CLOB(:answer_text), :score, CURRENT_TIMESTAMP)'
                );
                foreach ( $choices as $index => $choice ) {
                    $is_correct = in_array( $index, $correct_indexes, true );
                    $insert_choice->execute(
                        [
                            'question_id' => $question_id,
                            'is_correct' => $is_correct ? 1 : 0,
                            'display_order' => $index + 1,
                            'answer_text' => trim( $choice ),
                            'score' => $is_correct ? 1 : 0,
                        ]
                    );
                }
            }

            $count_query = $connection->prepare(
                'SELECT COUNT(*) FROM HRS_PERTANYAAN_PSIKOTES WHERE TES_PSIKOLOGI_ID = :test_id'
            );
            $count_query->execute( [ 'test_id' => $test_id ] );
            $question_count = (int) $count_query->fetchColumn();
            if ( 'pauli' === $test_key ) {
                $question_count *= 900;
            }
            $update_test = $connection->prepare(
                'UPDATE HRS_TES_PSIKOLOGI SET JUMLAH_SOAL = :question_count, UPDATED_AT = CURRENT_TIMESTAMP WHERE TES_PSIKOLOGI_ID = :test_id'
            );
            $update_test->execute( [ 'question_count' => $question_count, 'test_id' => $test_id ] );
            if ( 1 !== $update_test->rowCount() ) {
                throw new RuntimeException( 'Psychotest question count could not be updated.' );
            }

            $connection->commit();
            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'Psychotest question creation failed', $exception );
            return [ 'success' => false, 'error' => 'Soal gagal ditambahkan ke bank Oracle.' ];
        }
    }

    /**
     * Save reviewed DISC or PAPI groups atomically.
     *
     * @param array<int, array{items: array<int, string>}> $questions
     * @return array{success: bool, error?: string}
     */
    public function create_psychotest_questions( string $test_key, array $questions ): array {
        if ( 'iq' === $test_key ) {
            return $this->create_iq_psychotest_questions( $questions );
        }
        if ( ! in_array( $test_key, [ 'disc', 'papi' ], true ) ) {
            return [ 'success' => false, 'error' => 'Impor hanya mendukung IQ, DISC, dan PAPI Kostick.' ];
        }

        $expected_items = 'disc' === $test_key ? 4 : 2;
        if ( ! $questions || self::MAX_PSYCHOTEST_IMPORT_QUESTIONS < count( $questions ) ) {
            return [ 'success' => false, 'error' => sprintf( 'Jumlah grup soal harus antara 1 sampai %d.', self::MAX_PSYCHOTEST_IMPORT_QUESTIONS ) ];
        }

        $encoded_questions = [];
        foreach ( $questions as $question ) {
            $items = $question['items'] ?? null;
            if ( ! is_array( $items ) || $expected_items !== count( $items ) ) {
                return [ 'success' => false, 'error' => sprintf( 'Setiap grup %s harus memiliki tepat %d pernyataan.', strtoupper( $test_key ), $expected_items ) ];
            }
            $items = array_values(
                array_map(
                    static fn ( $item ): string => is_scalar( $item ) ? trim( (string) $item ) : '',
                    $items
                )
            );
            if ( in_array( '', $items, true ) ) {
                return [ 'success' => false, 'error' => 'Pernyataan tidak boleh kosong.' ];
            }
            $encoded = wp_json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            if ( ! is_string( $encoded ) || 4000 < strlen( $encoded ) ) {
                return [ 'success' => false, 'error' => 'Satu grup pernyataan melebihi batas panjang soal.' ];
            }
            $encoded_questions[] = $encoded;
        }

        $test_id = 0;
        foreach ( $this->load_psychotest_catalog() as $test ) {
            if ( $test_key === ( $test['test_key'] ?? '' ) ) {
                $test_id = (int) ( $test['ID'] ?? 0 );
                break;
            }
        }
        if ( $test_id < 1 ) {
            return [ 'success' => false, 'error' => 'Jenis tes tidak ditemukan di Oracle.' ];
        }

        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $order_query = $connection->prepare(
                'SELECT NVL(MAX(DISPLAY_ORDER), 0) + 1 FROM HRS_PERTANYAAN_PSIKOTES WHERE TES_PSIKOLOGI_ID = :test_id'
            );
            $order_query->execute( [ 'test_id' => $test_id ] );
            $display_order = (int) $order_query->fetchColumn();
            $insert_question = $connection->prepare(
                'INSERT INTO HRS_PERTANYAAN_PSIKOTES (TES_PSIKOLOGI_ID, TEKS_PERTANYAAN, JENIS_PERTANYAAN, NILAI_PERTANYAAN, DISPLAY_ORDER) VALUES (:test_id, TO_CLOB(:prompt), :question_type, 1, :display_order) RETURNING PERTANYAAN_PSIKOTES_ID INTO :question_id'
            );
            foreach ( $encoded_questions as $encoded_prompt ) {
                $question_id = 0;
                $insert_question->bindValue( ':test_id', $test_id, PDO::PARAM_INT );
                $insert_question->bindValue( ':prompt', $encoded_prompt, PDO::PARAM_STR );
                $insert_question->bindValue( ':question_type', 'PAIRED', PDO::PARAM_STR );
                $insert_question->bindValue( ':display_order', $display_order, PDO::PARAM_INT );
                $insert_question->bindParam( ':question_id', $question_id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 19 );
                $insert_question->execute();
                if ( $question_id < 1 ) {
                    throw new RuntimeException( 'Oracle did not return a psychotest question ID.' );
                }
                $display_order++;
            }

            $count_query = $connection->prepare(
                'SELECT COUNT(*) FROM HRS_PERTANYAAN_PSIKOTES WHERE TES_PSIKOLOGI_ID = :test_id'
            );
            $count_query->execute( [ 'test_id' => $test_id ] );
            $update_test = $connection->prepare(
                'UPDATE HRS_TES_PSIKOLOGI SET JUMLAH_SOAL = :question_count, UPDATED_AT = CURRENT_TIMESTAMP WHERE TES_PSIKOLOGI_ID = :test_id'
            );
            $update_test->execute(
                [
                    'question_count' => (int) $count_query->fetchColumn(),
                    'test_id' => $test_id,
                ]
            );
            if ( 1 !== $update_test->rowCount() ) {
                throw new RuntimeException( 'Psychotest question count could not be updated.' );
            }

            $connection->commit();
            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'Psychotest question batch creation failed', $exception );
            return [ 'success' => false, 'error' => 'Soal gagal disimpan ke bank Oracle. Tidak ada perubahan batch yang disimpan.' ];
        }
    }

    /**
     * Save reviewed IQ questions atomically across their selected subtests.
     *
     * @param array<int, array<string, mixed>> $questions
     * @return array{success: bool, error?: string}
     */
    private function create_iq_psychotest_questions( array $questions ): array {
        $allowed_subtests = [ 'iq_numbers', 'iq_assembly', 'iq_words', 'iq_abstraction' ];
        if ( ! $questions || self::MAX_PSYCHOTEST_IMPORT_QUESTIONS < count( $questions ) ) {
            return [ 'success' => false, 'error' => sprintf( 'Jumlah soal IQ harus antara 1 sampai %d.', self::MAX_PSYCHOTEST_IMPORT_QUESTIONS ) ];
        }

        $tests_by_subtest = [];
        foreach ( $this->load_psychotest_catalog() as $test ) {
            if ( 'iq' === ( $test['test_key'] ?? '' ) && in_array( $test['subtest'] ?? '', $allowed_subtests, true ) ) {
                $tests_by_subtest[ $test['subtest'] ] = (int) $test['ID'];
            }
        }
        if ( $this->has_error() ) {
            return [ 'success' => false, 'error' => 'Bank soal IQ tidak dapat dibaca dari Oracle.' ];
        }

        $normalized_questions = [];
        foreach ( $questions as $question ) {
            $subtest = sanitize_key( (string) ( $question['subtest'] ?? '' ) );
            $prompt = trim( (string) ( $question['prompt'] ?? '' ) );
            $choices = array_values( array_map(
                static fn ( $choice ): string => is_scalar( $choice ) ? trim( (string) $choice ) : '',
                (array) ( $question['choices'] ?? [] )
            ) );
            $type = sanitize_key( (string) ( $question['type'] ?? '' ) );
            $answer_values = array_values( array_filter(
                array_map( 'trim', explode( '|', (string) ( $question['answer'] ?? '' ) ) ),
                static fn ( string $answer ): bool => '' !== $answer
            ) );
            if ( ! in_array( $subtest, $allowed_subtests, true ) || empty( $tests_by_subtest[ $subtest ] )
                || '' === $prompt || 4000 < strlen( $prompt )
                || 2 > count( $choices ) || 6 < count( $choices ) || in_array( '', $choices, true )
                || ! in_array( $type, [ 'multiple_choice', 'multi_select' ], true )
                || ! $answer_values || 2 < count( $answer_values )
                || ( 'multiple_choice' === $type && 1 !== count( $answer_values ) )
                || ( 'multi_select' === $type && 2 > count( $answer_values ) ) ) {
                return [ 'success' => false, 'error' => 'Soal IQ harus memiliki subtes, pertanyaan, 2–6 pilihan, dan kunci jawaban yang valid.' ];
            }
            $correct_indexes = [];
            foreach ( $answer_values as $answer ) {
                if ( ! ctype_digit( $answer ) || (int) $answer < 1 || (int) $answer > count( $choices )
                    || in_array( (int) $answer - 1, $correct_indexes, true ) ) {
                    return [ 'success' => false, 'error' => 'Kunci jawaban IQ harus menunjuk pilihan yang tersedia tanpa duplikasi.' ];
                }
                $correct_indexes[] = (int) $answer - 1;
            }
            $normalized_questions[] = [
                'test_id' => $tests_by_subtest[ $subtest ],
                'prompt' => $prompt,
                'choices' => $choices,
                'type' => $type,
                'correct_indexes' => $correct_indexes,
            ];
        }

        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $display_orders = [];
            $insert_choice = $connection->prepare(
                'INSERT INTO HRS_JAWABAN_PSIKOTES (PERTANYAAN_PSIKOTES_ID, APAKAH_BENAR, URUTAN_TAMPILAN, JAWABAN_TEKS, NILAI, DIJAWAB_PADA) VALUES (:question_id, :is_correct, :display_order, TO_CLOB(:answer_text), :score, CURRENT_TIMESTAMP)'
            );
            foreach ( $normalized_questions as $question ) {
                $test_id = $question['test_id'];
                if ( ! isset( $display_orders[ $test_id ] ) ) {
                    $order_query = $connection->prepare(
                        'SELECT NVL(MAX(DISPLAY_ORDER), 0) + 1 FROM HRS_PERTANYAAN_PSIKOTES WHERE TES_PSIKOLOGI_ID = :test_id'
                    );
                    $order_query->execute( [ 'test_id' => $test_id ] );
                    $display_orders[ $test_id ] = (int) $order_query->fetchColumn();
                }
                $insert_question = $connection->prepare(
                    'INSERT INTO HRS_PERTANYAAN_PSIKOTES (TES_PSIKOLOGI_ID, TEKS_PERTANYAAN, JENIS_PERTANYAAN, NILAI_PERTANYAAN, DISPLAY_ORDER) VALUES (:test_id, TO_CLOB(:prompt), :question_type, 1, :display_order) RETURNING PERTANYAAN_PSIKOTES_ID INTO :question_id'
                );
                $question_id = 0;
                $insert_question->bindValue( ':test_id', $test_id, PDO::PARAM_INT );
                $insert_question->bindValue( ':prompt', $question['prompt'], PDO::PARAM_STR );
                $insert_question->bindValue( ':question_type', 'multi_select' === $question['type'] ? 'MULTI_SELECT' : 'MULTIPLE_CHOICE', PDO::PARAM_STR );
                $insert_question->bindValue( ':display_order', $display_orders[ $test_id ], PDO::PARAM_INT );
                $insert_question->bindParam( ':question_id', $question_id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 19 );
                $insert_question->execute();
                if ( $question_id < 1 ) {
                    throw new RuntimeException( 'Oracle did not return an IQ question ID.' );
                }
                foreach ( $question['choices'] as $index => $choice ) {
                    $is_correct = in_array( $index, $question['correct_indexes'], true );
                    $insert_choice->execute(
                        [
                            'question_id' => $question_id,
                            'is_correct' => $is_correct ? 1 : 0,
                            'display_order' => $index + 1,
                            'answer_text' => $choice,
                            'score' => $is_correct ? 1 : 0,
                        ]
                    );
                }
                $display_orders[ $test_id ]++;
            }

            $update_test = $connection->prepare(
                'UPDATE HRS_TES_PSIKOLOGI SET JUMLAH_SOAL = :question_count, UPDATED_AT = CURRENT_TIMESTAMP WHERE TES_PSIKOLOGI_ID = :test_id'
            );
            foreach ( array_keys( $display_orders ) as $test_id ) {
                $count_query = $connection->prepare(
                    'SELECT COUNT(*) FROM HRS_PERTANYAAN_PSIKOTES WHERE TES_PSIKOLOGI_ID = :test_id'
                );
                $count_query->execute( [ 'test_id' => $test_id ] );
                $update_test->execute(
                    [
                        'question_count' => (int) $count_query->fetchColumn(),
                        'test_id' => $test_id,
                    ]
                );
                if ( 1 !== $update_test->rowCount() ) {
                    throw new RuntimeException( 'IQ question count could not be updated.' );
                }
            }

            $connection->commit();
            return [ 'success' => true ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'IQ question batch creation failed', $exception );
            return [ 'success' => false, 'error' => 'Soal IQ gagal disimpan ke bank Oracle. Tidak ada perubahan batch yang disimpan.' ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_iq_assessment(): array {
        $tests = array_values( array_filter( $this->load_psychotest_catalog(), static fn ( array $test ): bool => 'iq' === $test['test_key'] ) );
        usort( $tests, static fn ( array $first, array $second ): int => $first['order'] <=> $second['order'] );
        return $tests;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_pauli_assessment(): array {
        $tests = array_values( array_filter( $this->load_psychotest_catalog(), static fn ( array $test ): bool => 'pauli' === $test['test_key'] ) );
        if ( isset( $tests[0] ) ) {
            $tests[0]['QUESTION_COUNT'] = (int) $tests[0]['QUESTION_COUNT'] > 0
                ? (int) $tests[0]['QUESTION_COUNT']
                : count( $tests[0]['questions'] ) * 900;
        }
        return $tests;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function get_personality_assessments( bool $include_inactive = false ): array {
        $assessments = [];
        foreach ( $this->load_psychotest_catalog( $include_inactive ) as $test ) {
            if ( in_array( $test['test_key'], [ 'disc', 'papi' ], true ) ) {
                $assessments[ $test['test_key'] ] = $test;
            }
        }
        return $assessments;
    }

    /**
     * @param array<int, int> $response_ids
     * @return array<string, mixed>
     */
    public function get_personality_assessment_record( int $test_id, int $result_id, array $response_ids ): array {
        if ( $test_id < 1 ) {
            return [ 'success' => false, 'error' => 'Jenis tes tidak valid.' ];
        }
        $responses = [];
        $response_ids = array_values( array_unique( array_filter( array_map( 'absint', $response_ids ) ) ) );
        if ( $response_ids || $result_id > 0 ) {
            $placeholders = [];
            $parameters = [ 'test_id' => $test_id ];
            foreach ( $response_ids as $index => $response_id ) {
                $placeholder = 'response_' . $index;
                $placeholders[] = ':' . $placeholder;
                $parameters[ $placeholder ] = $response_id;
            }
            $response_filters = [];
            if ( $response_ids ) {
                $response_filters[] = 'r.RESPON_PSIKOTES_ID IN (' . implode( ', ', $placeholders ) . ')';
            }
            if ( $result_id > 0 ) {
                $response_filters[] = 'r.HASIL_TES_PSIKOLOGI_ID = :result_id';
                $parameters['result_id'] = $result_id;
            }
            $responses = $this->fetch_all(
                'SELECT r.RESPON_PSIKOTES_ID AS "id", r.PERTANYAAN_PSIKOTES_ID AS "question_id", r.HASIL AS "result", r.TEKS_JAWABAN AS "answer", r.NILAI_DIPEROLEH AS "score", TO_CHAR(r.DIJAWAB_PADA, \'YYYY-MM-DD HH24:MI:SS\') AS "answered_at" FROM HRS_RESPON_PSIKOTES r JOIN HRS_PERTANYAAN_PSIKOTES q ON q.PERTANYAAN_PSIKOTES_ID = r.PERTANYAAN_PSIKOTES_ID WHERE q.TES_PSIKOLOGI_ID = :test_id AND (' . implode( ' OR ', $response_filters ) . ') ORDER BY q.DISPLAY_ORDER, r.RESPON_PSIKOTES_ID',
                $parameters
            );
        }
        $result = [];
        if ( $result_id > 0 ) {
            $rows = $this->fetch_all(
                'SELECT HASIL_TES_PSIKOLOGI_ID AS "id", NILAI AS "score", TO_CHAR(MULAI_PADA, \'YYYY-MM-DD HH24:MI:SS\') AS "started_at", TO_CHAR(DIKUMPULKAN_PADA, \'YYYY-MM-DD HH24:MI:SS\') AS "completed_at" FROM HRS_HASIL_TES_PSIKOLOGI WHERE HASIL_TES_PSIKOLOGI_ID = :result_id AND TES_PSIKOLOGI_ID = :test_id',
                [ 'result_id' => $result_id, 'test_id' => $test_id ]
            );
            $result = $rows[0] ?? [];
        }
        if ( $this->has_error() ) {
            return [ 'success' => false, 'error' => 'Data hasil tes tidak dapat dibaca dari Oracle.' ];
        }
        return [ 'success' => true, 'responses' => $responses, 'result' => $result ];
    }

    public function create_proctoring_session( int $candidate_id, string $session_id ): bool {
        if ( $candidate_id < 1 || ! preg_match( '/\A[a-f0-9]{64}\z/', $session_id ) ) {
            return false;
        }

        $this->last_error = null;
        try {
            $statement = $this->get_connection()->prepare(
                'MERGE INTO HRS_PSY_PROCTOR_SESSIONS target
                 USING (SELECT :session_id AS session_id FROM DUAL) source
                 ON (target.SESSION_ID = source.session_id)
                 WHEN MATCHED THEN UPDATE SET target.CONSENT_VERSION = :matched_consent_version,
                     target.CONSENT_AT = CURRENT_TIMESTAMP,
                     target.RETENTION_UNTIL = CASE
                         WHEN target.COMPLETED_AT IS NOT NULL THEN target.RETENTION_UNTIL
                         ELSE GREATEST(target.RETENTION_UNTIL, ADD_MONTHS(CURRENT_TIMESTAMP, 12))
                     END,
                     target.UPDATED_AT = CURRENT_TIMESTAMP
                     WHERE target.KANDIDAT_ID = :candidate_id AND target.RETENTION_UNTIL > CURRENT_TIMESTAMP
                 WHEN NOT MATCHED THEN INSERT (SESSION_ID, KANDIDAT_ID, CONSENT_VERSION, IDENTITY_STATUS, CREATED_AT, RETENTION_UNTIL, UPDATED_AT)
                     VALUES (:insert_session_id, :insert_candidate_id, :insert_consent_version, \'PENDING\', CURRENT_TIMESTAMP, ADD_MONTHS(CURRENT_TIMESTAMP, 12), CURRENT_TIMESTAMP)'
            );
            $statement->execute(
                [
                    'session_id' => $session_id,
                    'candidate_id' => $candidate_id,
                    'matched_consent_version' => 'proctoring-v1',
                    'insert_session_id' => $session_id,
                    'insert_candidate_id' => $candidate_id,
                    'insert_consent_version' => 'proctoring-v1',
                ]
            );
            $rows = $this->fetch_all(
                'SELECT SESSION_ID FROM HRS_PSY_PROCTOR_SESSIONS
                 WHERE SESSION_ID = :session_id AND KANDIDAT_ID = :candidate_id
                   AND RETENTION_UNTIL > CURRENT_TIMESTAMP',
                [ 'session_id' => $session_id, 'candidate_id' => $candidate_id ]
            );
            return ! $this->has_error() && ! empty( $rows );
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_oracle_failure( 'Psychotest proctoring consent save failed', $exception );
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    public function get_proctoring_session( int $candidate_id, string $session_id ): ?array {
        if ( $candidate_id < 1 || ! preg_match( '/\A[a-f0-9]{64}\z/', $session_id ) ) {
            return null;
        }
        $this->last_error = null;
        $rows = $this->fetch_all(
            'SELECT SESSION_ID AS "session_id", KANDIDAT_ID AS "candidate_id", IDENTITY_STATUS AS "identity_status",
                    TO_CHAR(CONSENT_AT, \'YYYY-MM-DD HH24:MI:SS\') AS "consent_at",
                    TO_CHAR(RETENTION_UNTIL, \'YYYY-MM-DD HH24:MI:SS\') AS "retention_until"
             FROM HRS_PSY_PROCTOR_SESSIONS
             WHERE SESSION_ID = :session_id AND KANDIDAT_ID = :candidate_id
               AND RETENTION_UNTIL > CURRENT_TIMESTAMP',
            [ 'session_id' => $session_id, 'candidate_id' => $candidate_id ]
        );
        return $rows[0] ?? null;
    }

    public function save_proctoring_identity( int $candidate_id, string $session_id, ?string $file_key, bool $manual_review ): bool {
        if ( $candidate_id < 1 || ! preg_match( '/\A[a-f0-9]{64}\z/', $session_id )
            || ( null !== $file_key && ! preg_match( '/\A[a-f0-9]{32}\.jpg\z/', $file_key ) )
            || ( null === $file_key && ! $manual_review ) ) {
            return false;
        }

        $this->last_error = null;
        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $lock = $connection->prepare(
                'SELECT IDENTITY_STATUS FROM HRS_PSY_PROCTOR_SESSIONS
                 WHERE SESSION_ID = :session_id AND KANDIDAT_ID = :candidate_id
                   AND RETENTION_UNTIL > CURRENT_TIMESTAMP FOR UPDATE'
            );
            $lock->execute( [ 'session_id' => $session_id, 'candidate_id' => $candidate_id ] );
            if ( 'PENDING' !== (string) $lock->fetchColumn() ) {
                throw new RuntimeException( 'Proctoring session was not found.' );
            }
            $update = $connection->prepare(
                'UPDATE HRS_PSY_PROCTOR_SESSIONS SET IDENTITY_STATUS = :identity_status, UPDATED_AT = CURRENT_TIMESTAMP
                 WHERE SESSION_ID = :session_id AND KANDIDAT_ID = :candidate_id'
            );
            $update->execute(
                [
                    'identity_status' => $manual_review ? 'MANUAL_REVIEW' : 'CAPTURED',
                    'session_id' => $session_id,
                    'candidate_id' => $candidate_id,
                ]
            );
            if ( 1 !== $update->rowCount() ) {
                throw new RuntimeException( 'Proctoring identity status could not be updated.' );
            }
            $event = $connection->prepare(
                'INSERT INTO HRS_PSY_PROCTOR_EVENTS (SESSION_ID, EVENT_TYPE, FILE_KEY, EVENT_AT)
                 VALUES (:session_id, :event_type, :file_key, CURRENT_TIMESTAMP)'
            );
            $event->execute(
                [
                    'session_id' => $session_id,
                    'event_type' => $manual_review ? 'MANUAL_IDENTITY' : 'IDENTITY_CAPTURE',
                    'file_key' => $file_key,
                ]
            );
            $connection->commit();
            return true;
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'Psychotest identity evidence save failed', $exception );
            return false;
        }
    }

    /**
     * Store a signal and, while under the per-session lock, at most five alert images.
     *
     * @return array{success: bool, captured: bool}
     */
    public function save_proctoring_event( int $candidate_id, string $session_id, string $event_type, ?string $file_key ): array {
        $allowed_types = [
            'TAB_HIDDEN',
            'CAMERA_STOPPED',
            'CAMERA_UNAVAILABLE',
            'FACE_NOT_DETECTED',
            'MULTIPLE_FACES',
            'FACE_DETECTION_UNAVAILABLE',
            'MICROPHONE_AVAILABLE',
            'MICROPHONE_UNAVAILABLE',
        ];
        if ( $candidate_id < 1 || ! preg_match( '/\A[a-f0-9]{64}\z/', $session_id )
            || ! in_array( $event_type, $allowed_types, true )
            || ( null !== $file_key && ! preg_match( '/\A[a-f0-9]{32}\.jpg\z/', $file_key ) ) ) {
            return [ 'success' => false, 'captured' => false ];
        }

        $this->last_error = null;
        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $lock = $connection->prepare(
                'SELECT SESSION_ID FROM HRS_PSY_PROCTOR_SESSIONS
                 WHERE SESSION_ID = :session_id AND KANDIDAT_ID = :candidate_id
                   AND RETENTION_UNTIL > CURRENT_TIMESTAMP FOR UPDATE'
            );
            $lock->execute( [ 'session_id' => $session_id, 'candidate_id' => $candidate_id ] );
            if ( false === $lock->fetchColumn() ) {
                throw new RuntimeException( 'Proctoring session was not found.' );
            }
            $recent = $connection->prepare(
                'SELECT COUNT(*) FROM HRS_PSY_PROCTOR_EVENTS
                 WHERE SESSION_ID = :session_id AND EVENT_TYPE = :event_type
                   AND EVENT_AT > CURRENT_TIMESTAMP - INTERVAL \'15\' SECOND'
            );
            $recent->execute( [ 'session_id' => $session_id, 'event_type' => $event_type ] );
            if ( 0 < (int) $recent->fetchColumn() ) {
                $connection->commit();
                return [ 'success' => true, 'captured' => false ];
            }

            $captured = false;
            $stored_file_key = null;
            if ( null !== $file_key ) {
                $count = $connection->prepare(
                    'SELECT COUNT(*) FROM HRS_PSY_PROCTOR_EVENTS
                     WHERE SESSION_ID = :session_id AND EVENT_TYPE IN (
                        \'TAB_HIDDEN\', \'CAMERA_STOPPED\', \'CAMERA_UNAVAILABLE\',
                        \'FACE_NOT_DETECTED\', \'MULTIPLE_FACES\'
                     )
                       AND FILE_KEY IS NOT NULL'
                );
                $count->execute( [ 'session_id' => $session_id ] );
                if ( 5 > (int) $count->fetchColumn() ) {
                    $stored_file_key = $file_key;
                    $captured = true;
                }
            }
            $event = $connection->prepare(
                'INSERT INTO HRS_PSY_PROCTOR_EVENTS (SESSION_ID, EVENT_TYPE, FILE_KEY, EVENT_AT)
                 VALUES (:session_id, :event_type, :file_key, CURRENT_TIMESTAMP)'
            );
            $event->execute(
                [ 'session_id' => $session_id, 'event_type' => $event_type, 'file_key' => $stored_file_key ]
            );
            $connection->commit();
            return [ 'success' => true, 'captured' => $captured ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'Psychotest proctoring event save failed', $exception );
            return [ 'success' => false, 'captured' => false ];
        }
    }

    public function mark_proctoring_completed( int $candidate_id, string $session_id ): bool {
        if ( $candidate_id < 1 || ! preg_match( '/\A[a-f0-9]{64}\z/', $session_id ) ) {
            return false;
        }
        $this->last_error = null;
        try {
            $statement = $this->get_connection()->prepare(
                'UPDATE HRS_PSY_PROCTOR_SESSIONS
                 SET COMPLETED_AT = COALESCE(COMPLETED_AT, CURRENT_TIMESTAMP),
                     RETENTION_UNTIL = ADD_MONTHS(COALESCE(COMPLETED_AT, CURRENT_TIMESTAMP), 12),
                     UPDATED_AT = CURRENT_TIMESTAMP
                 WHERE SESSION_ID = :session_id AND KANDIDAT_ID = :candidate_id
                   AND RETENTION_UNTIL > CURRENT_TIMESTAMP'
            );
            $statement->execute( [ 'session_id' => $session_id, 'candidate_id' => $candidate_id ] );
            return 1 === $statement->rowCount();
        } catch ( PDOException | RuntimeException $exception ) {
            $this->log_oracle_failure( 'Psychotest proctoring retention update failed', $exception );
            return false;
        }
    }

    /**
     * @param array<int, int> $candidate_ids
     * @return array{sessions: array<int, array<string, mixed>>, events: array<int, array<string, mixed>>}
     */
    public function get_proctoring_reports( array $candidate_ids ): array {
        $candidate_ids = array_values( array_unique( array_filter( array_map( 'absint', $candidate_ids ) ) ) );
        if ( ! $candidate_ids ) {
            return [ 'sessions' => [], 'events' => [] ];
        }
        $this->last_error = null;
        $placeholders = [];
        $parameters = [];
        foreach ( $candidate_ids as $index => $candidate_id ) {
            $key = 'candidate_' . $index;
            $placeholders[] = ':' . $key;
            $parameters[ $key ] = $candidate_id;
        }
        $in_clause = implode( ', ', $placeholders );
        $sessions = $this->fetch_all(
            'SELECT SESSION_ID AS "session_id", KANDIDAT_ID AS "candidate_id", IDENTITY_STATUS AS "identity_status",
                    TO_CHAR(CREATED_AT, \'YYYY-MM-DD HH24:MI:SS\') AS "created_at",
                    TO_CHAR(COMPLETED_AT, \'YYYY-MM-DD HH24:MI:SS\') AS "completed_at",
                    TO_CHAR(RETENTION_UNTIL, \'YYYY-MM-DD HH24:MI:SS\') AS "retention_until"
             FROM HRS_PSY_PROCTOR_SESSIONS
             WHERE KANDIDAT_ID IN (' . $in_clause . ') AND RETENTION_UNTIL > CURRENT_TIMESTAMP',
            $parameters
        );
        $events = $this->fetch_all(
            'SELECT e.EVENT_ID AS "event_id", e.SESSION_ID AS "session_id", s.KANDIDAT_ID AS "candidate_id",
                    e.EVENT_TYPE AS "event_type", e.FILE_KEY AS "file_key",
                    e.ACTOR_USER_ID AS "actor_user_id", e.TARGET_EVENT_ID AS "target_event_id",
                    TO_CHAR(e.EVENT_AT, \'YYYY-MM-DD HH24:MI:SS\') AS "event_at"
             FROM HRS_PSY_PROCTOR_EVENTS e
             JOIN HRS_PSY_PROCTOR_SESSIONS s ON s.SESSION_ID = e.SESSION_ID
             WHERE s.KANDIDAT_ID IN (' . $in_clause . ') AND s.RETENTION_UNTIL > CURRENT_TIMESTAMP
             ORDER BY e.EVENT_AT DESC',
            $parameters
        );
        return [ 'sessions' => $sessions, 'events' => $events ];
    }

    /** @return array<string, mixed>|null */
    public function get_proctoring_image_record( int $candidate_id, string $session_id, int $event_id ): ?array {
        if ( $candidate_id < 1 || $event_id < 1 || ! preg_match( '/\A[a-f0-9]{64}\z/', $session_id ) ) {
            return null;
        }
        $this->last_error = null;
        $rows = $this->fetch_all(
            'SELECT e.EVENT_ID AS "event_id", e.SESSION_ID AS "session_id", e.FILE_KEY AS "file_key"
             FROM HRS_PSY_PROCTOR_EVENTS e
             JOIN HRS_PSY_PROCTOR_SESSIONS s ON s.SESSION_ID = e.SESSION_ID
             WHERE e.EVENT_ID = :event_id AND e.SESSION_ID = :session_id
               AND s.KANDIDAT_ID = :candidate_id AND e.FILE_KEY IS NOT NULL
               AND s.RETENTION_UNTIL > CURRENT_TIMESTAMP',
            [ 'event_id' => $event_id, 'session_id' => $session_id, 'candidate_id' => $candidate_id ]
        );
        return $rows[0] ?? null;
    }

    public function log_proctoring_image_access( string $session_id, int $admin_user_id, int $target_event_id ): bool {
        if ( ! preg_match( '/\A[a-f0-9]{64}\z/', $session_id ) || $admin_user_id < 1 || $target_event_id < 1 ) {
            return false;
        }
        $this->last_error = null;
        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $lock = $connection->prepare(
                'SELECT SESSION_ID FROM HRS_PSY_PROCTOR_SESSIONS
                 WHERE SESSION_ID = :session_id AND RETENTION_UNTIL > CURRENT_TIMESTAMP FOR UPDATE'
            );
            $lock->execute( [ 'session_id' => $session_id ] );
            if ( false === $lock->fetchColumn() ) {
                $connection->rollBack();
                return false;
            }
            $target = $connection->prepare(
                'SELECT EVENT_ID FROM HRS_PSY_PROCTOR_EVENTS
                 WHERE EVENT_ID = :target_event_id AND SESSION_ID = :target_session_id AND FILE_KEY IS NOT NULL'
            );
            $target->execute( [ 'target_event_id' => $target_event_id, 'target_session_id' => $session_id ] );
            if ( false === $target->fetchColumn() ) {
                $connection->rollBack();
                return false;
            }
            $statement = $connection->prepare(
                'INSERT INTO HRS_PSY_PROCTOR_EVENTS (SESSION_ID, EVENT_TYPE, EVENT_AT, ACTOR_USER_ID, TARGET_EVENT_ID)
                 VALUES (:session_id, \'IMAGE_ACCESS\', CURRENT_TIMESTAMP, :admin_user_id, :target_event_id)'
            );
            $statement->execute(
                [ 'session_id' => $session_id, 'admin_user_id' => $admin_user_id, 'target_event_id' => $target_event_id ]
            );
            $connection->commit();
            return 1 === $statement->rowCount();
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'Psychotest evidence access audit failed', $exception );
            return false;
        }
    }

    public function review_proctoring_identity( int $candidate_id, string $session_id, int $admin_user_id ): bool {
        if ( $candidate_id < 1 || $admin_user_id < 1 || ! preg_match( '/\A[a-f0-9]{64}\z/', $session_id ) ) {
            return false;
        }
        $this->last_error = null;
        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $lock = $connection->prepare(
                'SELECT IDENTITY_STATUS FROM HRS_PSY_PROCTOR_SESSIONS
                 WHERE SESSION_ID = :session_id AND KANDIDAT_ID = :candidate_id
                   AND RETENTION_UNTIL > CURRENT_TIMESTAMP FOR UPDATE'
            );
            $lock->execute( [ 'session_id' => $session_id, 'candidate_id' => $candidate_id ] );
            if ( 'MANUAL_REVIEW' !== (string) $lock->fetchColumn() ) {
                throw new RuntimeException( 'Proctoring identity is not awaiting manual review.' );
            }
            $update = $connection->prepare(
                'UPDATE HRS_PSY_PROCTOR_SESSIONS SET IDENTITY_STATUS = \'MANUAL_VERIFIED\', UPDATED_AT = CURRENT_TIMESTAMP
                 WHERE SESSION_ID = :session_id AND KANDIDAT_ID = :candidate_id'
            );
            $update->execute( [ 'session_id' => $session_id, 'candidate_id' => $candidate_id ] );
            if ( 1 !== $update->rowCount() ) {
                throw new RuntimeException( 'Manual identity status could not be updated.' );
            }
            $event = $connection->prepare(
                'INSERT INTO HRS_PSY_PROCTOR_EVENTS (SESSION_ID, EVENT_TYPE, EVENT_AT, ACTOR_USER_ID)
                 VALUES (:session_id, \'MANUAL_VERIFIED\', CURRENT_TIMESTAMP, :admin_user_id)'
            );
            $event->execute( [ 'session_id' => $session_id, 'admin_user_id' => $admin_user_id ] );
            $connection->commit();
            return true;
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'Psychotest manual identity review failed', $exception );
            return false;
        }
    }

    /** @return array<int, array{session_id: string, file_keys: array<int, string>}> */
    public function get_expired_proctoring_sessions(): array {
        $this->last_error = null;
        $sessions = $this->fetch_all(
            'SELECT SESSION_ID AS "session_id" FROM HRS_PSY_PROCTOR_SESSIONS WHERE RETENTION_UNTIL <= CURRENT_TIMESTAMP'
        );
        $expired = [];
        foreach ( $sessions as $session ) {
            $session_id = (string) ( $session['session_id'] ?? '' );
            if ( ! preg_match( '/\A[a-f0-9]{64}\z/', $session_id ) ) {
                continue;
            }
            $events = $this->fetch_all(
                'SELECT FILE_KEY AS "file_key" FROM HRS_PSY_PROCTOR_EVENTS
                 WHERE SESSION_ID = :session_id AND FILE_KEY IS NOT NULL',
                [ 'session_id' => $session_id ]
            );
            $expired[] = [
                'session_id' => $session_id,
                'file_keys' => array_values( array_map( 'strval', array_column( $events, 'file_key' ) ) ),
            ];
        }
        return $expired;
    }

    public function delete_expired_proctoring_session( string $session_id ): bool {
        if ( ! preg_match( '/\A[a-f0-9]{64}\z/', $session_id ) ) {
            return false;
        }
        $this->last_error = null;
        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $check = $connection->prepare(
                'SELECT SESSION_ID FROM HRS_PSY_PROCTOR_SESSIONS
                 WHERE SESSION_ID = :session_id AND RETENTION_UNTIL <= CURRENT_TIMESTAMP FOR UPDATE'
            );
            $check->execute( [ 'session_id' => $session_id ] );
            if ( false === $check->fetchColumn() ) {
                $connection->rollBack();
                return false;
            }
            $events = $connection->prepare( 'DELETE FROM HRS_PSY_PROCTOR_EVENTS WHERE SESSION_ID = :session_id' );
            $events->execute( [ 'session_id' => $session_id ] );
            $session = $connection->prepare( 'DELETE FROM HRS_PSY_PROCTOR_SESSIONS WHERE SESSION_ID = :session_id' );
            $session->execute( [ 'session_id' => $session_id ] );
            $connection->commit();
            return true;
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'Psychotest proctoring retention cleanup failed', $exception );
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_psychotest_results(): array {
        return $this->fetch_all(
            'SELECT "candidate_id", "candidate_name", "candidate_email", "test_id", "test_title", "result_id", "status", "started_at", "completed_at", "score", "response_count", "question_count" FROM (' .
                'SELECT p.KANDIDAT_ID AS "candidate_id", NVL(p.NAMA_LENGKAP, p.NAMA_KANDIDAT) AS "candidate_name", p.EMAIL AS "candidate_email", t.TES_PSIKOLOGI_ID AS "test_id", t.JUDUL AS "test_title", h.HASIL_TES_PSIKOLOGI_ID AS "result_id", \'complete\' AS "status", TO_CHAR(h.MULAI_PADA, \'YYYY-MM-DD HH24:MI:SS\') AS "started_at", TO_CHAR(h.DIKUMPULKAN_PADA, \'YYYY-MM-DD HH24:MI:SS\') AS "completed_at", h.NILAI AS "score", COUNT(r.RESPON_PSIKOTES_ID) AS "response_count", (SELECT COUNT(*) FROM HRS_PERTANYAAN_PSIKOTES q WHERE q.TES_PSIKOLOGI_ID = t.TES_PSIKOLOGI_ID) AS "question_count" FROM HRS_HASIL_TES_PSIKOLOGI h JOIN HRS_TES_PSIKOLOGI t ON t.TES_PSIKOLOGI_ID = h.TES_PSIKOLOGI_ID JOIN HRS_PROFIL_KANDIDAT p ON p.KANDIDAT_ID = h.KANDIDAT_ID LEFT JOIN HRS_RESPON_PSIKOTES r ON r.HASIL_TES_PSIKOLOGI_ID = h.HASIL_TES_PSIKOLOGI_ID AND r.KANDIDAT_ID = h.KANDIDAT_ID GROUP BY p.KANDIDAT_ID, p.NAMA_LENGKAP, p.NAMA_KANDIDAT, p.EMAIL, t.TES_PSIKOLOGI_ID, t.JUDUL, h.HASIL_TES_PSIKOLOGI_ID, h.MULAI_PADA, h.DIKUMPULKAN_PADA, h.NILAI ' .
                'UNION ALL ' .
                'SELECT p.KANDIDAT_ID AS "candidate_id", NVL(p.NAMA_LENGKAP, p.NAMA_KANDIDAT) AS "candidate_name", p.EMAIL AS "candidate_email", t.TES_PSIKOLOGI_ID AS "test_id", t.JUDUL AS "test_title", CAST(NULL AS NUMBER) AS "result_id", \'active\' AS "status", TO_CHAR(MIN(r.DIBUAT_PADA), \'YYYY-MM-DD HH24:MI:SS\') AS "started_at", CAST(NULL AS VARCHAR2(19)) AS "completed_at", SUM(r.NILAI_DIPEROLEH) AS "score", COUNT(r.RESPON_PSIKOTES_ID) AS "response_count", (SELECT COUNT(*) FROM HRS_PERTANYAAN_PSIKOTES q WHERE q.TES_PSIKOLOGI_ID = t.TES_PSIKOLOGI_ID) AS "question_count" FROM HRS_RESPON_PSIKOTES r JOIN HRS_PERTANYAAN_PSIKOTES q ON q.PERTANYAAN_PSIKOTES_ID = r.PERTANYAAN_PSIKOTES_ID JOIN HRS_TES_PSIKOLOGI t ON t.TES_PSIKOLOGI_ID = q.TES_PSIKOLOGI_ID JOIN HRS_PROFIL_KANDIDAT p ON p.KANDIDAT_ID = r.KANDIDAT_ID WHERE r.HASIL_TES_PSIKOLOGI_ID IS NULL GROUP BY p.KANDIDAT_ID, p.NAMA_LENGKAP, p.NAMA_KANDIDAT, p.EMAIL, t.TES_PSIKOLOGI_ID, t.JUDUL' .
                ') ORDER BY "started_at" DESC FETCH FIRST 250 ROWS ONLY'
        );
    }

    /**
     * @param array<int, int> $selected_answer_ids
     * @return array{success: bool, response_id?: int, error?: string}
     */
    public function save_iq_response( int $candidate_id, int $question_id, array $selected_answer_ids, int $response_id = 0 ): array {
        $selected_answer_ids = array_values( array_unique( array_filter( array_map( 'absint', $selected_answer_ids ) ) ) );
        if ( $candidate_id < 1 || $question_id < 1 ) {
            return [ 'success' => false, 'error' => 'Jawaban tes IQ tidak valid.' ];
        }

        $question_types = $this->fetch_all(
            'SELECT JENIS_PERTANYAAN AS "type" FROM HRS_PERTANYAAN_PSIKOTES WHERE PERTANYAAN_PSIKOTES_ID = :question_id',
            [ 'question_id' => $question_id ]
        );
        if ( $this->has_error() ) {
            return [ 'success' => false, 'error' => 'Tipe soal tes IQ tidak dapat dibaca dari Oracle.' ];
        }
        if ( ! isset( $question_types[0]['type'] ) ) {
            return [ 'success' => false, 'error' => 'Soal tes IQ tidak ditemukan.' ];
        }
        $maximum_answers = $this->psychotest_allows_multiple_answers( (string) $question_types[0]['type'] ) ? 2 : 1;
        if ( $maximum_answers < count( $selected_answer_ids ) ) {
            return [ 'success' => false, 'error' => 'Jumlah pilihan melebihi batas untuk soal ini.' ];
        }

        $choices = $this->fetch_all(
            'SELECT JAWABAN_PSIKOTES_ID AS "id", APAKAH_BENAR AS "is_correct", NILAI AS "score" FROM HRS_JAWABAN_PSIKOTES WHERE PERTANYAAN_PSIKOTES_ID = :question_id',
            [ 'question_id' => $question_id ]
        );
        $choices_by_id = [];
        foreach ( $choices as $choice ) {
            $choices_by_id[(int) $choice['id']] = $choice;
        }
        foreach ( $selected_answer_ids as $answer_id ) {
            if ( ! isset( $choices_by_id[ $answer_id ] ) ) {
                return [ 'success' => false, 'error' => 'Pilihan jawaban tidak sesuai dengan soal IQ.' ];
            }
        }

        $score = 0.0;
        foreach ( $selected_answer_ids as $answer_id ) {
            if ( 1 === (int) $choices_by_id[ $answer_id ]['is_correct'] ) {
                $score += (float) $choices_by_id[ $answer_id ]['score'];
            }
        }
        $answer_json = wp_json_encode( $selected_answer_ids );
        if ( ! is_string( $answer_json ) ) {
            return [ 'success' => false, 'error' => 'Jawaban tes IQ tidak dapat diproses.' ];
        }

        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $saved = $this->save_psychotest_response(
                $connection,
                $candidate_id,
                $question_id,
                $response_id,
                $answer_json,
                $score
            );
            $delete = $connection->prepare( 'DELETE FROM HRS_PILIHAN_RESPON_PSIKOTES WHERE RESPON_PSIKOTES_ID = :response_id' );
            $delete->execute( [ 'response_id' => $saved['response_id'] ] );
            if ( $selected_answer_ids ) {
                $insert = $connection->prepare(
                    'INSERT INTO HRS_PILIHAN_RESPON_PSIKOTES (RESPON_PSIKOTES_ID, JAWABAN_PSIKOTES_ID) VALUES (:response_id, :answer_id)'
                );
                foreach ( $selected_answer_ids as $answer_id ) {
                    $insert->execute( [ 'response_id' => $saved['response_id'], 'answer_id' => $answer_id ] );
                }
            }
            $connection->commit();
            return [ 'success' => true, 'response_id' => $saved['response_id'] ];
        } catch ( InvalidArgumentException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            return [ 'success' => false, 'error' => 'Respons tes IQ tidak dapat diperbarui.' ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'IQ response save failed', $exception );
            return [ 'success' => false, 'error' => 'Jawaban tes IQ gagal disimpan ke Oracle.' ];
        }
    }

    /**
     * @param array<int, int> $response_ids
     * @return array{success: bool, result_id?: int, error?: string}
     */
    public function complete_iq_subtest( int $candidate_id, int $test_id, string $started_at, array $response_ids ): array {
        return $this->complete_psychotest_result( $candidate_id, $test_id, $started_at, $response_ids );
    }

    /**
     * @param array<int, int> $numbers
     * @return array{success: bool, response_id?: int, error?: string}
     */
    public function save_pauli_column_response( int $candidate_id, int $test_id, int $question_id, int $response_id, string $answers, array $numbers, int $answer_width = 1 ): array {
        $numbers = array_values( array_map( 'absint', $numbers ) );
        $answer_count = count( $numbers ) - 1;
        if ( $candidate_id < 1 || $test_id < 1 || $question_id < 1
            || 900 !== $answer_count || ! in_array( $answer_width, [ 1, 3 ], true )
            || self::matches_pauli_answers( $answers, $answer_width, $answer_count ) === false
            || count( array_filter( $numbers, static fn ( int $number ): bool => $number > ( 1 === $answer_width ? 9 : 99 ) ) ) > 0 ) {
            return [ 'success' => false, 'error' => 'Kolom tes Pauli tidak valid.' ];
        }
        if ( ! $this->question_belongs_to_test( $test_id, $question_id ) ) {
            return [ 'success' => false, 'error' => 'Kolom tidak termasuk dalam tes Pauli.' ];
        }

        if ( 1 === $answer_width ) {
            $answer_json = wp_json_encode(
                [ 'numbers' => $numbers, 'answers' => $answers ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } else {
            $packed_numbers = '';
            $packed_answers = '';
            for ( $index = 0; $index <= $answer_count; $index++ ) {
                $packed_numbers .= pack( 'C', $numbers[ $index ] );
                if ( $index < $answer_count ) {
                    $answer = substr( $answers, $index * $answer_width, $answer_width );
                    $packed_answers .= pack( 'C', '---' === $answer ? 255 : (int) $answer );
                }
            }
            $answer_json = wp_json_encode(
                [
                    'format' => 'pauli-v2',
                    'numbers' => base64_encode( $packed_numbers ),
                    'answers' => base64_encode( $packed_answers ),
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }
        if ( ! is_string( $answer_json ) ) {
            return [ 'success' => false, 'error' => 'Jawaban tes Pauli tidak dapat diproses.' ];
        }
        $score = 0;
        for ( $index = 0; $index < $answer_count; $index++ ) {
            $answer = substr( $answers, $index * $answer_width, $answer_width );
            $expected = $numbers[ $index ] + $numbers[ $index + 1 ];
            if ( 1 === $answer_width ) {
                $expected %= 10;
            }
            $answer_value = ctype_digit( $answer ) ? (int) $answer : -1;
            if ( $answer_value === $expected || ( 3 === $answer_width && $answer_value === $expected % 10 ) ) {
                $score++;
            }
        }
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $saved = $this->save_psychotest_response( $connection, $candidate_id, $question_id, $response_id, $answer_json, (float) $score );
            $connection->commit();
            return [ 'success' => true, 'response_id' => $saved['response_id'] ];
        } catch ( InvalidArgumentException $exception ) {
            if ( isset( $connection ) && $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            return [ 'success' => false, 'error' => 'Respons kolom Pauli tidak dapat diperbarui.' ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( isset( $connection ) && $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'Pauli response save failed', $exception );
            return [ 'success' => false, 'error' => 'Jawaban tes Pauli gagal disimpan ke Oracle.' ];
        }
    }

    private static function matches_pauli_answers( string $answers, int $answer_width, int $answer_count ): bool {
        if ( $answer_count * $answer_width !== strlen( $answers ) || 1 !== preg_match( '/\A[0-9-]+\z/', $answers ) ) {
            return false;
        }
        for ( $index = 0; $index < $answer_count; $index++ ) {
            $answer = substr( $answers, $index * $answer_width, $answer_width );
            if ( str_repeat( '-', $answer_width ) === $answer ) {
                continue;
            }
            if ( ! ctype_digit( $answer ) || ( 3 === $answer_width && (int) $answer > 198 ) ) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<int, int> $response_ids
     * @return array{success: bool, result_id?: int, score?: int, error?: string}
     */
    public function complete_pauli_assessment( int $candidate_id, int $test_id, string $started_at, array $response_ids ): array {
        return $this->complete_psychotest_result( $candidate_id, $test_id, $started_at, $response_ids );
    }

    /**
     * @param array<string, int> $answer
     * @return array{success: bool, response_id?: int, error?: string}
     */
    public function save_personality_response( int $candidate_id, int $test_id, int $question_id, int $response_id, array $answer ): array {
        if ( $candidate_id < 1 || $test_id < 1 || $question_id < 1 || ! $this->question_belongs_to_test( $test_id, $question_id ) ) {
            return [ 'success' => false, 'error' => 'Jawaban tes kepribadian tidak valid.' ];
        }
        $answer_json = wp_json_encode( $answer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( ! is_string( $answer_json ) || 4000 < strlen( $answer_json ) ) {
            return [ 'success' => false, 'error' => 'Jawaban tes kepribadian tidak dapat diproses.' ];
        }
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $saved = $this->save_psychotest_response( $connection, $candidate_id, $question_id, $response_id, $answer_json, 0.0 );
            $connection->commit();
            return [ 'success' => true, 'response_id' => $saved['response_id'] ];
        } catch ( InvalidArgumentException $exception ) {
            if ( isset( $connection ) && $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            return [ 'success' => false, 'error' => 'Respons tes kepribadian tidak dapat diperbarui.' ];
        } catch ( PDOException | RuntimeException $exception ) {
            if ( isset( $connection ) && $connection instanceof PDO && $connection->inTransaction() ) {
                $connection->rollBack();
            }
            $this->log_oracle_failure( 'Personality response save failed', $exception );
            return [ 'success' => false, 'error' => 'Jawaban tes kepribadian gagal disimpan ke Oracle.' ];
        }
    }

    /**
     * @param array<int, int> $response_ids
     * @return array{success: bool, result_id?: int, error?: string}
     */
    public function complete_personality_assessment( int $candidate_id, int $test_id, int $started_at, array $response_ids ): array {
        return $this->complete_psychotest_result( $candidate_id, $test_id, gmdate( 'Y-m-d H:i:s', $started_at ), $response_ids );
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

    /**
     * Load an application-specific profile for the authorized HR download route.
     *
     * @return array{application: array<string, mixed>, profile: array<string, mixed>, answers: array<int, array<string, mixed>>, history: array<int, array<string, mixed>>}|null
     */
    public function get_admin_application_profile( string $application_id ): ?array {
        if ( ! ctype_digit( $application_id ) || '0' === $application_id ) {
            return null;
        }

        $rows = $this->fetch_all(
            'SELECT l.lamaran_id AS "application_id",
                    l.profile_kandidat_id AS "applicant_id",
                    TO_CHAR(NVL(l.created_at, l.tanggal_melamar), \'YYYY-MM-DD HH24:MI:SS\') AS "application_date",
                    l.status_terkini AS "application_status",
                    l.status_akhir AS "final_status",
                    d.judul_pekerjaan AS "position",
                    d.nama_departemen AS "department",
                    k.nama_dealer AS "dealer",
                    p.kandidat_id AS "candidate_id",
                    NVL(p.nama_lengkap, p.nama_kandidat) AS "candidate_name",
                    NVL(p.email, pkl.email) AS "candidate_email",
                    NVL(p.nomor_hp, p.nomor_telepon) AS "candidate_phone",
                    p.jenis_kelamin AS "gender",
                    p.tempat_lahir AS "birth_place",
                    TO_CHAR(p.tanggal_lahir, \'YYYY-MM-DD\') AS "birth_date",
                    p.alamat_domisili AS "address",
                    p.kota AS "city",
                    p.provinsi AS "province",
                    p.pendidikan AS "education",
                    hr.status_hasil AS "hr_interview_result",
                    hr.rekomendasi AS "hr_recommendation",
                    hr.nama_pewawancara AS "hr_interviewer",
                    hr.catatan_wawancara AS "hr_interview_notes",
                    (SELECT h.nilai FROM HRS_HASIL_TES_PSIKOLOGI h
                     WHERE h.kandidat_id = p.kandidat_id
                     ORDER BY h.dikumpulkan_pada DESC NULLS LAST, h.hasil_tes_psikologi_id DESC
                     FETCH FIRST 1 ROW ONLY) AS "psychotest_score",
                    w.status_seleksi AS "user_interview_result",
                    w.rekomendasi AS "user_recommendation",
                    w.nilai_total AS "user_interview_score",
                    w.nama_pewawancara AS "user_interviewer",
                    w.catatan_wawancara AS "user_interview_notes",
                    pd.data_profil AS "profile_details"
             FROM HRS_LAMARAN l
             JOIN HRS_DETAIL_INFO_LOWONGAN d ON d.detail_lowongan_id = l.detail_lowongan_id
             LEFT JOIN HRS_KOLOM_LANDING_PENDAFTARAN k ON k.kolom_pendaftaran_id = d.kolom_pendaftaran_id
             JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON pkl.profile_kandidat_login = l.profile_kandidat_id
             JOIN HRS_PROFIL_KANDIDAT p ON p.kandidat_id = pkl.kandidat_id
             LEFT JOIN HRS_PROFIL_KANDIDAT_DETAIL pd ON pd.kandidat_id = p.kandidat_id
             LEFT JOIN HRS_HASIL_WAWANCARA_HR hr ON hr.lamaran_id = l.lamaran_id
             LEFT JOIN HRS_HASIL_WAWANCARA_USER w ON w.lamaran_id = l.lamaran_id
             WHERE l.lamaran_id = :application_id
             FETCH FIRST 1 ROW ONLY',
            [ 'application_id' => $application_id ]
        );
        if ( empty( $rows[0] ) ) {
            return null;
        }

        $application = $rows[0];
        $profile_details = $this->decode_applicant_profile_details( $application['profile_details'] ?? '' );
        unset( $application['profile_details'] );

        return [
            'application' => $application,
            'profile' => [
                'name' => (string) ( $application['candidate_name'] ?? '' ),
                'email' => (string) ( $application['candidate_email'] ?? '' ),
                'phone' => (string) ( $application['candidate_phone'] ?? '' ),
                'gender' => (string) ( $application['gender'] ?? '' ),
                'birth_place' => (string) ( $application['birth_place'] ?? '' ),
                'birth_date' => (string) ( $application['birth_date'] ?? '' ),
                'address' => (string) ( $application['address'] ?? '' ),
                'city' => (string) ( $application['city'] ?? '' ),
                'province' => (string) ( $application['province'] ?? '' ),
                'education' => (string) ( $application['education'] ?? '' ),
                'profile_details' => $profile_details,
            ],
            'answers' => $this->get_admin_application_answers( $application_id ),
            'history' => $this->get_selection_history( $application_id ),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function get_admin_application_answers( string $application_id ): array {
        if ( ! ctype_digit( $application_id ) || '0' === $application_id ) {
            return [];
        }

        // Prefer the direct application link; retain the legacy lookup for older snapshots.
        $answers = $this->fetch_all(
            'SELECT a.FIELD_KEY AS "field_key", a.FIELD_TITLE AS "field_title", a.FIELD_TYPE AS "field_type", a.ANSWER_VALUE AS "answer_value", a.FILE_NAME AS "file_name" FROM RECRUIT_FORM_ANSWERS a JOIN RECRUIT_FORM_SUBMISSIONS s ON s.SUBMISSION_ID = a.SUBMISSION_ID WHERE s.LAMARAN_ID = :application_id ORDER BY a.SUBMISSION_ID DESC, a.FIELD_KEY',
            [ 'application_id' => $application_id ]
        );
        if ( ! empty( $answers ) ) {
            return $answers;
        }

        $answers = $this->fetch_all(
            'SELECT a.FIELD_KEY AS "field_key", a.FIELD_TITLE AS "field_title", a.FIELD_TYPE AS "field_type", a.ANSWER_VALUE AS "answer_value", a.FILE_NAME AS "file_name" FROM RECRUIT_FORM_ANSWERS a JOIN RECRUIT_FORM_SUBMISSIONS s ON s.SUBMISSION_ID = a.SUBMISSION_ID JOIN HRS_LAMARAN l ON l.LAMARAN_ID = :application_id AND s.SOURCE_VACANCY_ID = l.DETAIL_LOWONGAN_ID JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON pkl.PROFILE_KANDIDAT_LOGIN = l.PROFILE_KANDIDAT_ID WHERE LOWER(TRIM(s.APPLICANT_EMAIL)) = LOWER(TRIM(pkl.EMAIL)) ORDER BY a.SUBMISSION_ID DESC, a.FIELD_KEY',
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
                    'field_type'   => (string) ( $ans['type'] ?? 'text' ),
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
                    'field_type'   => 'text',
                    'answer_value' => is_scalar( $v ) ? (string) $v : ( is_array( $v ) ? json_encode( $v ) : '' ),
                    'file_name'    => null,
                ];
            }
            return $formatted;
        }

        return [];
    }

    /**
     * Limit department profile answers to education and requested document fields.
     *
     * @param array<int, array<string, mixed>> $answers
     * @return array<int, array<string, mixed>>
     */
    private function get_department_profile_answers( array $answers ): array {
        return array_values(
            array_filter(
                $answers,
                static function ( array $answer ): bool {
                    $key = (string) ( $answer['field_key'] ?? '' );
                    if ( preg_match( '/\Apendidikan_formal_\d+_(?:tingkat|nama_sekolah|program_studi|ipk|tahun_lulus)\z/i', $key ) ) {
                        return true;
                    }

                    $title = (string) ( $answer['field_title'] ?? '' );
                    return 'file' === strtolower( trim( (string) ( $answer['field_type'] ?? '' ) ) )
                        && preg_match( '/\b(?:CV|curriculum vitae|resume|KTP|ijazah|sertifikat|certificate|transkrip)\b/i', $title );
                }
            )
        );
    }

    /**
     * Load one submitted file only when the active department account owns the assignment.
     *
     * @return array{file_name: string, mime_type: string, file_size: int, file_content: string}|null
     */
    public function get_user_department_application_document( string $application_id, int $account_id, string $field_key ): ?array {
        if (
            ! ctype_digit( $application_id )
            || '0' === $application_id
            || $account_id < 1
            || ! preg_match( '/\A[a-zA-Z0-9_]{1,100}\z/', $field_key )
        ) {
            return null;
        }

        $rows = $this->fetch_all(
            'SELECT a.FILE_NAME AS "file_name",
                    a.FILE_MIME_TYPE AS "mime_type",
                    a.FILE_SIZE AS "file_size",
                    a.FILE_CONTENT AS "file_content"
             FROM HRS_LAMARAN l
             JOIN HRS_HASIL_WAWANCARA_HR hr ON hr.lamaran_id = l.lamaran_id
             JOIN HRS_ROLE_AKSES u ON u.role_akses_id = hr.role_akses_id
             JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl ON pkl.profile_kandidat_login = l.profile_kandidat_id
             JOIN RECRUIT_FORM_SUBMISSIONS s
               ON s.lamaran_id = l.lamaran_id
               OR (
                    s.lamaran_id IS NULL
                    AND s.source_vacancy_id = l.detail_lowongan_id
                    AND LOWER(TRIM(s.applicant_email)) = LOWER(TRIM(pkl.email))
               )
             JOIN RECRUIT_FORM_ANSWERS a ON a.submission_id = s.submission_id
             WHERE l.lamaran_id = :application_id
               AND hr.status_hasil = \'LOLOS\'
               AND hr.role_akses_id = :account_id
               AND u.peran = \'KEPALA_DEPARTEMEN\'
               AND UPPER(TRIM(u.status_akun)) = \'ACTIVE\'
               AND a.field_type = \'file\'
               AND REGEXP_LIKE(a.field_title, \'(CV|CURRICULUM VITAE|RESUME|KTP|IJAZAH|SERTIFIKAT|CERTIFICATE|TRANSKRIP)\', \'i\')
               AND a.field_key = :field_key
               AND a.file_content IS NOT NULL
             ORDER BY CASE WHEN s.lamaran_id = l.lamaran_id THEN 0 ELSE 1 END, s.submission_id DESC
             FETCH FIRST 1 ROW ONLY',
            [
                'application_id' => $application_id,
                'account_id' => $account_id,
                'field_key' => $field_key,
            ]
        );
        if ( empty( $rows[0] ) ) {
            return null;
        }

        return [
            'file_name' => (string) ( $rows[0]['file_name'] ?? '' ),
            'mime_type' => (string) ( $rows[0]['mime_type'] ?? ''),
            'file_size' => (int) ( $rows[0]['file_size'] ?? 0 ),
            'file_content' => (string) ( $rows[0]['file_content'] ?? ''),
        ];
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
        if ( ! ctype_digit( $application_id ) || '0' === $application_id || '' === trim( $status ) ) {
            return [ 'success' => false, 'error' => 'Status lamaran tidak valid.' ];
        }
        $normalized = strtoupper( trim( $status ) );
        if ( in_array( $normalized, [ 'ACCEPTED', 'LOLOS', 'DITERIMA' ], true ) ) {
            $normalized = 'DITERIMA';
        } elseif ( in_array( $normalized, [ 'REJECTED', 'TIDAK_LOLOS', 'FAILED', 'DITOLAK' ], true ) ) {
            $normalized = 'DITOLAK';
        } elseif ( in_array( $normalized, [ 'ADMINISTRASI', 'SCREENING', 'SUBMITTED' ], true ) ) {
            $normalized = 'ADMINISTRASI';
        }
        $allowed_statuses = [ 'SUBMITTED', 'SCREENING', 'ADMINISTRASI', 'PSIKOTES', 'INTERVIEW_HR', 'INTERVIEW_USER', 'FINAL', 'DITERIMA', 'DITOLAK' ];
        if ( ! in_array( $normalized, $allowed_statuses, true ) ) {
            return [ 'success' => false, 'error' => 'Status lamaran tidak dikenal.' ];
        }

        $stage = strtoupper( trim( $stage ) );
        if ( 'DITERIMA' === $normalized || ( 'DITOLAK' === $normalized && 'FINAL' === $stage ) ) {
            return [ 'success' => false, 'error' => 'Simpan keputusan akhir melalui menu Keputusan Akhir agar hasil HR, departemen, dan tracking tetap sinkron.' ];
        }

        $stage_map = [
            'SUBMITTED'      => 'ADMINISTRASI',
            'SCREENING'      => 'ADMINISTRASI',
            'ADMINISTRASI'   => 'ADMINISTRASI',
            'PSIKOTES'       => 'PSIKOTES',
            'INTERVIEW_HR'   => 'INTERVIEW_HR',
            'INTERVIEW_USER' => 'INTERVIEW_USER',
            'FINAL'          => 'FINAL',
            'DITERIMA'       => 'FINAL',
            'DITOLAK'        => 'FINAL',
        ];
        $effective_stage = '' !== trim( $stage ) ? strtoupper( trim( $stage ) ) : ( $stage_map[ $normalized ] ?? 'ADMINISTRASI' );

        $note = trim( $note );
        if ( 'INTERVIEW_USER' === $normalized && '' === $note ) {
            $note = 'Lolos HR; lanjut wawancara departemen. Jadwal diinformasikan departemen.';
        }
        $connection = null;
        try {
            $connection = $this->get_connection();
            $connection->beginTransaction();
            $current_status_query = $connection->prepare(
                'SELECT UPPER(TRIM(STATUS_TERKINI)) FROM HRS_LAMARAN WHERE LAMARAN_ID = :application_id FOR UPDATE'
            );
            $current_status_query->execute( [ 'application_id' => $application_id ] );
            $current_status = $current_status_query->fetchColumn();
            if ( false === $current_status ) {
                throw new InvalidArgumentException( 'Application not found.' );
            }
            if ( ! in_array( $current_status, [ 'SUBMITTED', 'SCREENING', 'ADMINISTRASI', 'PSIKOTES', 'INTERVIEW_HR', 'INTERVIEW_USER' ], true ) ) {
                $connection->rollBack();
                return [ 'success' => false, 'error' => 'Tahap seleksi lamaran ini sudah diputuskan.' ];
            }
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
                    'stage' => strtoupper( function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $effective_stage ) : trim( strip_tags( $effective_stage ) ) ),
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

    private function get_application_token_column_expression(): ?string {
        if ( null === $this->application_token_column_exists ) {
            try {
                $statement = $this->get_connection()->prepare(
                    'SELECT COUNT(*) FROM USER_TAB_COLUMNS WHERE TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
                );
                $statement->execute( [ 'table_name' => 'HRS_LAMARAN', 'column_name' => 'APPLICATION_TOKEN' ] );
                $this->application_token_column_exists = 0 < (int) $statement->fetchColumn();
            } catch ( PDOException | RuntimeException $exception ) {
                $this->last_error = $exception->getMessage();
                $this->log_error( 'Application token schema lookup failed', $exception );
                return null;
            }
        }

        return $this->application_token_column_exists ? 'l.application_token' : 'CAST(NULL AS VARCHAR2(64))';
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
            $rows = [];
            // Read CLOB locators before fetching another row; PDO_OCI can reuse them.
            while ( false !== ( $row = $statement->fetch( PDO::FETCH_ASSOC ) ) ) {
                foreach ( $row as &$value ) {
                    if ( is_resource( $value ) || ( is_object( $value ) && method_exists( $value, 'load' ) ) ) {
                        $value = $this->oracle_lob_to_string( $value );
                    }
                }
                unset( $value );
                $rows[] = $row;
            }
            return $rows;
        } catch ( PDOException | RuntimeException $exception ) {
            $this->last_error = $exception->getMessage();
            $this->log_error( 'Recruitment Oracle query failed', $exception );
            return [];
        }
    }

                /**
                 * @return array<int, array<string, mixed>>
                 */
                private function load_psychotest_catalog( bool $include_inactive = false ): array {
                    $active_filter = $include_inactive ? '' : 'WHERE NVL(t.IS_ACTIVE, 0) = 1';
                    $rows = $this->fetch_all(
                        'SELECT t.TES_PSIKOLOGI_ID AS "test_id", t.JUDUL AS "test_title", t.PETUNJUK_PENGERJAAN AS "instructions", t.DURASI_PENGERJAAN AS "duration", t.JUMLAH_SOAL AS "question_count", t.IS_ACTIVE AS "test_active", t.URUTAN_TAMPILAN AS "test_order", TO_CHAR(t.UPDATED_AT, \'YYYY-MM-DD HH24:MI:SS\') AS "updated_at", q.PERTANYAAN_PSIKOTES_ID AS "question_id", q.TEKS_PERTANYAAN AS "question_text", q.JENIS_PERTANYAAN AS "question_type", q.DISPLAY_ORDER AS "question_order", a.JAWABAN_PSIKOTES_ID AS "choice_id", a.JAWABAN_TEKS AS "choice_text", a.APAKAH_BENAR AS "choice_is_correct", a.URUTAN_TAMPILAN AS "choice_order", a.NILAI AS "choice_score" FROM HRS_TES_PSIKOLOGI t LEFT JOIN HRS_PERTANYAAN_PSIKOTES q ON q.TES_PSIKOLOGI_ID = t.TES_PSIKOLOGI_ID LEFT JOIN HRS_JAWABAN_PSIKOTES a ON a.PERTANYAAN_PSIKOTES_ID = q.PERTANYAAN_PSIKOTES_ID ' . $active_filter . ' ORDER BY NVL(t.URUTAN_TAMPILAN, t.TES_PSIKOLOGI_ID), t.TES_PSIKOLOGI_ID, NVL(q.DISPLAY_ORDER, q.PERTANYAAN_PSIKOTES_ID), q.PERTANYAAN_PSIKOTES_ID, NVL(a.URUTAN_TAMPILAN, a.JAWABAN_PSIKOTES_ID), a.JAWABAN_PSIKOTES_ID'
                    );
                    $tests = [];
                    foreach ( $rows as $row ) {
                        $test_id = absint( $row['test_id'] ?? 0 );
                        if ( $test_id < 1 ) {
                            continue;
                        }
                        if ( ! isset( $tests[ $test_id ] ) ) {
                            $title = (string) ( $row['test_title'] ?? '' );
                            $test_key = $this->psychotest_test_key( $title );
                            if ( '' === $test_key ) {
                                continue;
                            }
                            $tests[ $test_id ] = [
                                'ID' => $test_id,
                                'TITLE' => $title,
                                'INSTRUCTIONS' => (string) ( $row['instructions'] ?? '' ),
                                'DURATION' => absint( $row['duration'] ?? 0 ),
                                'QUESTION_COUNT' => absint( $row['question_count'] ?? 0 ),
                                'active' => absint( $row['test_active'] ?? 0 ),
                                'test_key' => $test_key,
                                'order' => absint( $row['test_order'] ?? $test_id ),
                                'updated_at' => (string) ( $row['updated_at'] ?? '' ),
                                'subtest' => 'iq' === $test_key
                                    ? $this->psychotest_iq_subtest( $title, count( array_filter( $tests, static fn ( array $test ): bool => 'iq' === $test['test_key'] ) ) )
                                    : '',
                                'questions_by_id' => [],
                            ];
                        }

                        $question_id = absint( $row['question_id'] ?? 0 );
                        if ( $question_id < 1 ) {
                            continue;
                        }
                        if ( ! isset( $tests[ $test_id ]['questions_by_id'][ $question_id ] ) ) {
                            $question_text = (string) ( $row['question_text'] ?? '' );
                            $prompt = $this->decode_psychotest_prompt( $question_text );
                            $items = in_array( $tests[ $test_id ]['test_key'], [ 'disc', 'papi' ], true )
                                ? $this->decode_psychotest_items( $question_text )
                                : [];
                            $tests[ $test_id ]['questions_by_id'][ $question_id ] = [
                                'id' => $question_id,
                                'prompt' => $prompt,
                                'items' => $items,
                                'type' => $this->psychotest_question_type( (string) ( $row['question_type'] ?? '' ), $tests[ $test_id ]['test_key'] ),
                                'number' => absint( $row['question_order'] ?? $question_id ),
                                'choices' => [],
                            ];
                        }
                        $choice_id = absint( $row['choice_id'] ?? 0 );
                        if ( $choice_id > 0 ) {
                            $tests[ $test_id ]['questions_by_id'][ $question_id ]['choices'][] = [
                                'id' => $choice_id,
                                'text' => (string) ( $row['choice_text'] ?? '' ),
                                'is_correct' => absint( $row['choice_is_correct'] ?? 0 ),
                                'score' => (float) ( $row['choice_score'] ?? 0 ),
                            ];
                        }
                    }

                    foreach ( $tests as &$test ) {
                        $questions = array_values( $test['questions_by_id'] );
                        usort( $questions, static fn ( array $first, array $second ): int => $first['number'] <=> $second['number'] );
                        foreach ( $questions as $index => &$question ) {
                            usort( $question['choices'], static fn ( array $first, array $second ): int => $first['id'] <=> $second['id'] );
                            $question['number'] = $index + 1;
                        }
                        unset( $question );
                        $test['questions'] = $questions;
                        unset( $test['questions_by_id'] );
                        $test['QUESTION_COUNT'] = 'pauli' === $test['test_key']
                            ? count( $questions ) * 900
                            : count( $questions );
                        if ( 'pauli' === $test['test_key'] ) {
                            $test['DURATION'] = count( $questions ) * 3;
                        }
                    }
                    unset( $test );
                    usort( $tests, static fn ( array $first, array $second ): int => $first['order'] <=> $second['order'] );
                    return array_values( $tests );
                }

                private function psychotest_test_key( string $title ): string {
                    $title = strtoupper( $title );
                    foreach ( [ 'pauli' => 'PAULI', 'disc' => 'DISC', 'papi' => 'PAPI' ] as $key => $needle ) {
                        if ( false !== strpos( $title, $needle ) ) {
                            return $key;
                        }
                    }
                    return false !== strpos( $title, 'IQ' ) ? 'iq' : '';
                }

                private function psychotest_iq_subtest( string $title, int $index ): string {
                    $title = strtoupper( $title );
                    $subtests = [
                        'iq_numbers' => [ 'BERHITUNG', 'ANGKA', 'NUMBER' ],
                        'iq_assembly' => [ 'GABUNGAN', 'BAGIAN', 'ASSEMBLY' ],
                        'iq_words' => [ 'HUBUNGAN', 'KATA', 'WORD' ],
                        'iq_abstraction' => [ 'ABSTRAKSI', 'NONVERBAL', 'ABSTRACTION' ],
                    ];
                    foreach ( $subtests as $key => $keywords ) {
                        foreach ( $keywords as $keyword ) {
                            if ( false !== strpos( $title, $keyword ) ) {
                                return $key;
                            }
                        }
                    }
                    if ( preg_match( '/(?:IQ\s*[-:]?\s*)?([1-4])(?:\D|$)/', $title, $matches ) ) {
                        return array_keys( $subtests )[ (int) $matches[1] - 1 ] ?? '';
                    }
                    return array_keys( $subtests )[ $index ] ?? '';
                }

                private function psychotest_question_type( string $type, string $test_key ): string {
                    if ( in_array( $test_key, [ 'disc', 'papi' ], true ) ) {
                        return 'paired';
                    }
                    if ( 'pauli' === $test_key ) {
                        return 'numeric';
                    }
                    return $this->psychotest_allows_multiple_answers( $type ) ? 'multi_select' : 'multiple_choice';
                }

                private function psychotest_allows_multiple_answers( string $type ): bool {
                    return in_array( strtolower( trim( $type ) ), [ 'multi_select', 'multiple_select' ], true );
                }

                private function decode_psychotest_prompt( string $text ): string {
                    $decoded = json_decode( $text, true );
                    if ( is_array( $decoded ) && isset( $decoded['prompt'] ) && is_string( $decoded['prompt'] ) ) {
                        return trim( $decoded['prompt'] );
                    }
                    return trim( $text );
                }

                /**
                 * @return array<int, string>
                 */
                private function decode_psychotest_items( string $text ): array {
                    $decoded = json_decode( $text, true );
                    if ( is_array( $decoded ) ) {
                        $values = isset( $decoded['items'] ) && is_array( $decoded['items'] )
                            ? $decoded['items']
                            : $decoded;
                        $items = array_values( array_filter( $values, 'is_string' ) );
                    } else {
                        $items = preg_split( '/\r\n|\r|\n/', $text ) ?: [];
                    }
                    return array_values( array_filter( array_map( 'trim', $items ), static fn ( string $item ): bool => '' !== $item ) );
                }

                /**
                 * @return array<string, mixed>|null
                 */
                private function find_psychotest_question( int $question_id ): ?array {
                    foreach ( $this->load_psychotest_catalog( true ) as $test ) {
                        foreach ( $test['questions'] as $question ) {
                            if ( $question_id === (int) $question['id'] ) {
                                return [
                                    'test_id' => $test['ID'],
                                    'test_key' => $test['test_key'],
                                    'subtest' => $test['subtest'],
                                    'choices' => $question['choices'],
                                ];
                            }
                        }
                    }
                    return null;
                }

                /**
                 * @param array<string, mixed> $question
                 * @return array{success: bool, error?: string}
                 */
                private function save_psychotest_prompt( array $question, string $test_key ): array {
                    $question_id = absint( $question['id'] ?? 0 );
                    $prompt = trim( (string) ( $question['prompt'] ?? '' ) );
                    if ( $question_id < 1 || '' === $prompt || 4000 < strlen( $prompt ) ) {
                        return [ 'success' => false, 'error' => 'Isi soal tidak valid.' ];
                    }
                    $existing = $this->find_psychotest_question( $question_id );
                    if ( ! $existing || $test_key !== $existing['test_key'] ) {
                        return [ 'success' => false, 'error' => 'Soal tidak ditemukan pada jenis tes yang dipilih.' ];
                    }

                    $connection = null;
                    try {
                        $connection = $this->get_connection();
                        $connection->beginTransaction();
                        $update = $connection->prepare(
                            'UPDATE HRS_PERTANYAAN_PSIKOTES SET TEKS_PERTANYAAN = TO_CLOB(:prompt) WHERE PERTANYAAN_PSIKOTES_ID = :question_id'
                        );
                        $update->execute( [ 'prompt' => $prompt, 'question_id' => $question_id ] );
                        if ( 1 !== $update->rowCount() ) {
                            throw new InvalidArgumentException( 'Question not found.' );
                        }
                        $this->touch_psychotest( $connection, (int) $existing['test_id'] );
                        $connection->commit();
                        return [ 'success' => true ];
                    } catch ( InvalidArgumentException $exception ) {
                        if ( $connection instanceof PDO && $connection->inTransaction() ) {
                            $connection->rollBack();
                        }
                        return [ 'success' => false, 'error' => 'Soal tidak ditemukan.' ];
                    } catch ( PDOException | RuntimeException $exception ) {
                        if ( $connection instanceof PDO && $connection->inTransaction() ) {
                            $connection->rollBack();
                        }
                        $this->log_oracle_failure( 'Psychotest question update failed', $exception );
                        return [ 'success' => false, 'error' => 'Soal gagal disimpan ke Oracle.' ];
                    }
                }

                private function touch_psychotest( PDO $connection, int $test_id ): void {
                    $statement = $connection->prepare( 'UPDATE HRS_TES_PSIKOLOGI SET UPDATED_AT = CURRENT_TIMESTAMP WHERE TES_PSIKOLOGI_ID = :test_id' );
                    $statement->execute( [ 'test_id' => $test_id ] );
                }

                private function question_belongs_to_test( int $test_id, int $question_id ): bool {
                    $rows = $this->fetch_all(
                        'SELECT COUNT(*) AS "question_count" FROM HRS_PERTANYAAN_PSIKOTES WHERE PERTANYAAN_PSIKOTES_ID = :question_id AND TES_PSIKOLOGI_ID = :test_id',
                        [ 'question_id' => $question_id, 'test_id' => $test_id ]
                    );
                    return 1 === (int) ( $rows[0]['question_count'] ?? 0 );
                }

                /**
                 * @return array{expression: string, parameters: array<string, string>}
                 */
                private function create_clob_binding( string $name, string $value ): array {
                    $characters = preg_split( '//u', $value, -1, PREG_SPLIT_NO_EMPTY );
                    if ( false === $characters ) {
                        throw new InvalidArgumentException( 'Invalid UTF-8 assessment response.' );
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
                    if ( ! $chunks ) {
                        $chunks[] = '';
                    }

                    $parameters = [];
                    $expressions = [];
                    foreach ( $chunks as $index => $chunk ) {
                        $placeholder = $name . '_' . $index;
                        $parameters[ $placeholder ] = $chunk;
                        $expressions[] = 'TO_CLOB(:' . $placeholder . ')';
                    }

                    return [
                        'expression' => implode( ' || ', $expressions ),
                        'parameters' => $parameters,
                    ];
                }

                /**
                 * @return array{response_id: int}
                 */
                private function save_psychotest_response( PDO $connection, int $candidate_id, int $question_id, int $response_id, string $answer_json, float $score ): array {
                    if ( $candidate_id < 1 || $question_id < 1 || 4000 < strlen( $answer_json ) ) {
                        throw new InvalidArgumentException( 'Invalid assessment response.' );
                    }
                    $answer_binding = $this->create_clob_binding( 'answer', $answer_json );
                    if ( $response_id > 0 ) {
                        $statement = $connection->prepare(
                            'UPDATE HRS_RESPON_PSIKOTES SET HASIL = \'SAVED\', TEKS_JAWABAN = ' . $answer_binding['expression'] . ', NILAI_DIPEROLEH = :score, DIJAWAB_PADA = CURRENT_TIMESTAMP, DIPERBARUI_PADA = CURRENT_TIMESTAMP WHERE RESPON_PSIKOTES_ID = :response_id AND KANDIDAT_ID = :candidate_id AND PERTANYAAN_PSIKOTES_ID = :question_id AND HASIL_TES_PSIKOLOGI_ID IS NULL'
                        );
                        $statement->execute(
                            array_merge(
                                $answer_binding['parameters'],
                                [
                                    'score' => $score,
                                    'response_id' => $response_id,
                                    'candidate_id' => $candidate_id,
                                    'question_id' => $question_id,
                                ]
                            )
                        );
                        if ( 1 !== $statement->rowCount() ) {
                            throw new InvalidArgumentException( 'Response is not editable.' );
                        }
                        return [ 'response_id' => $response_id ];
                    }

                    $statement = $connection->prepare(
                        'INSERT INTO HRS_RESPON_PSIKOTES (KANDIDAT_ID, PERTANYAAN_PSIKOTES_ID, HASIL, TEKS_JAWABAN, NILAI_DIPEROLEH, DIJAWAB_PADA, DIBUAT_PADA, DIPERBARUI_PADA) VALUES (:candidate_id, :question_id, \'SAVED\', ' . $answer_binding['expression'] . ', :score, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING RESPON_PSIKOTES_ID INTO :response_id'
                    );
                    $new_response_id = 0;
                    $statement->bindValue( ':candidate_id', $candidate_id, PDO::PARAM_INT );
                    $statement->bindValue( ':question_id', $question_id, PDO::PARAM_INT );
                    foreach ( $answer_binding['parameters'] as $placeholder => $value ) {
                        $statement->bindValue( ':' . $placeholder, $value, PDO::PARAM_STR );
                    }
                    $statement->bindValue( ':score', $score );
                    $statement->bindParam( ':response_id', $new_response_id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 19 );
                    $statement->execute();
                    if ( $new_response_id < 1 ) {
                        throw new RuntimeException( 'Oracle did not return a response ID.' );
                    }
                    return [ 'response_id' => $new_response_id ];
                }

                /**
                 * @param array<int, int> $response_ids
                 * @return array{success: bool, result_id?: int, score?: int, error?: string}
                 */
                private function complete_psychotest_result( int $candidate_id, int $test_id, string $started_at, array $response_ids ): array {
                    $response_ids = array_values( array_unique( array_filter( array_map( 'absint', $response_ids ) ) ) );
                    if ( $candidate_id < 1 || $test_id < 1 || ! preg_match( '/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $started_at ) ) {
                        return [ 'success' => false, 'error' => 'Data penyelesaian tes tidak valid.' ];
                    }
                    $connection = null;
                    try {
                        $connection = $this->get_connection();
                        $connection->beginTransaction();
                        if ( $response_ids ) {
                            $placeholders = [];
                            $parameters = [ 'candidate_id' => $candidate_id, 'test_id' => $test_id ];
                            foreach ( $response_ids as $index => $response_id ) {
                                $placeholder = 'response_' . $index;
                                $placeholders[] = ':' . $placeholder;
                                $parameters[ $placeholder ] = $response_id;
                            }
                            $check = $connection->prepare(
                                'SELECT COUNT(*) AS "response_count", NVL(SUM(r.NILAI_DIPEROLEH), 0) AS "score" FROM HRS_RESPON_PSIKOTES r JOIN HRS_PERTANYAAN_PSIKOTES q ON q.PERTANYAAN_PSIKOTES_ID = r.PERTANYAAN_PSIKOTES_ID WHERE r.KANDIDAT_ID = :candidate_id AND q.TES_PSIKOLOGI_ID = :test_id AND r.RESPON_PSIKOTES_ID IN (' . implode( ', ', $placeholders ) . ') AND r.HASIL_TES_PSIKOLOGI_ID IS NULL'
                            );
                            $check->execute( $parameters );
                            $record = $check->fetch( PDO::FETCH_ASSOC );
                            if ( ! is_array( $record ) || count( $response_ids ) !== (int) $record['response_count'] ) {
                                throw new InvalidArgumentException( 'Assessment responses do not match this candidate and test.' );
                            }
                            $score = (float) $record['score'];
                        } else {
                            $score = 0.0;
                        }

                        $insert = $connection->prepare(
                            'INSERT INTO HRS_HASIL_TES_PSIKOLOGI (KANDIDAT_ID, MULAI_PADA, DIKUMPULKAN_PADA, NILAI, TES_PSIKOLOGI_ID) VALUES (:candidate_id, TO_TIMESTAMP(:started_at, \'YYYY-MM-DD HH24:MI:SS\'), CURRENT_TIMESTAMP, :score, :test_id) RETURNING HASIL_TES_PSIKOLOGI_ID INTO :result_id'
                        );
                        $result_id = 0;
                        $insert->bindValue( ':candidate_id', $candidate_id, PDO::PARAM_INT );
                        $insert->bindValue( ':started_at', $started_at, PDO::PARAM_STR );
                        $insert->bindValue( ':score', $score );
                        $insert->bindValue( ':test_id', $test_id, PDO::PARAM_INT );
                        $insert->bindParam( ':result_id', $result_id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 19 );
                        $insert->execute();
                        if ( $result_id < 1 ) {
                            throw new RuntimeException( 'Oracle did not return a result ID.' );
                        }

                        if ( $response_ids ) {
                            $update = $connection->prepare(
                                'UPDATE HRS_RESPON_PSIKOTES SET HASIL_TES_PSIKOLOGI_ID = :result_id WHERE KANDIDAT_ID = :candidate_id AND RESPON_PSIKOTES_ID IN (' . implode( ', ', $placeholders ) . ') AND HASIL_TES_PSIKOLOGI_ID IS NULL'
                            );
                            $update_parameters = [
                                'result_id' => $result_id,
                                'candidate_id' => $candidate_id,
                            ];
                            foreach ( $response_ids as $index => $response_id ) {
                                $update_parameters['response_' . $index] = $response_id;
                            }
                            $update->execute( $update_parameters );
                            if ( count( $response_ids ) !== $update->rowCount() ) {
                                throw new InvalidArgumentException( 'Assessment responses could not be linked to the result.' );
                            }
                        }
                        $connection->commit();
                        return [ 'success' => true, 'result_id' => $result_id, 'score' => (int) round( $score ) ];
                    } catch ( InvalidArgumentException $exception ) {
                        if ( $connection instanceof PDO && $connection->inTransaction() ) {
                            $connection->rollBack();
                        }
                        return [ 'success' => false, 'error' => 'Jawaban tes tidak cocok dengan sesi kandidat.' ];
                    } catch ( PDOException | RuntimeException $exception ) {
                        if ( $connection instanceof PDO && $connection->inTransaction() ) {
                            $connection->rollBack();
                        }
                        $this->log_oracle_failure( 'Psychotest result save failed', $exception );
                        return [ 'success' => false, 'error' => 'Hasil tes gagal disimpan ke Oracle.' ];
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
            try {
                $content = stream_get_contents( $value );
                if ( false === $content ) {
                    throw new RuntimeException( 'An Oracle LOB value could not be read.' );
                }
                return $content;
            } finally {
                fclose( $value );
            }
        }

        if ( is_object( $value ) && method_exists( $value, 'load' ) ) {
            try {
                return (string) $value->load();
            } finally {
                if ( method_exists( $value, 'free' ) ) {
                    $value->free();
                } elseif ( method_exists( $value, 'close' ) ) {
                    $value->close();
                }
            }
        }

        return is_scalar( $value ) ? (string) $value : '';
    }

    private function log_oracle_failure( string $context, Throwable $exception ): void {
        $this->last_error = $exception->getMessage();
        if ( $exception instanceof PDOException && is_array( $exception->errorInfo ) ) {
            $sql_state = (string) ( $exception->errorInfo[0] ?? $exception->getCode() );
            $oracle_code = (string) ( $exception->errorInfo[1] ?? '' );
            $oracle_message = trim( (string) ( $exception->errorInfo[2] ?? $exception->getMessage() ) );
            error_log( sprintf( '%s (SQLSTATE %s, Oracle error %s): %s', $context, $sql_state, $oracle_code, $oracle_message ) );
            return;
        }

        error_log( $context . ': ' . $exception->getMessage() );
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

    private function is_missing_schema_object( PDOException $exception ): bool {
        return false !== stripos( $exception->getMessage(), 'ORA-00942' )
            || false !== stripos( $exception->getMessage(), 'ORA-00904' );
    }

}