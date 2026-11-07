-- DAW Recruitment - Candidate Authentication & Account Management Schema
-- Target Schema: DAW_RECRUITMENT
-- Script ini bersifat idempoten dan aman dijalankan berulang kali.
--
-- Arsitektur Eksekusi:
--   FASE A: PRE-VALIDATION (Strict, Read-Only, Tanpa DDL)
--     1. Validasi keberadaan tabel target HRS_PROFILE_KANDIDAT_LOGIN.
--     2. Validasi keberadaan dan tipe kolom EMAIL.
--     3. Validasi tipe, panjang karakter, CHAR_USED semantics ('C'), dan nullability kolom KATA_SANDI (jika sudah ada).
--     4. Validasi tipe, panjang karakter, CHAR_USED semantics ('C'), NOT NULL, dan default value STATUS_AKUN (jika sudah ada).
--     5. Validasi nama, tipe, status ENABLED, status VALIDATED, dan search condition CK_HRS_PKL_STATUS_AKUN (jika sudah ada).
--     6. Validasi integritas data: cek duplikasi email format canonical (LOWER(TRIM(EMAIL))).
--     7. Validasi nama, tipe, status ENABLED, status VALIDATED, NOT DEFERRABLE, DEFERRED IMMEDIATE, dan kolom tunggal EMAIL pada posisi 1 UK_HRS_PKL_EMAIL (jika sudah ada).
--     Jika SALAH SATU validasi gagal, script BERHENTI SEKETIKA sebelum ada DDL yang dijalankan.
--
--   FASE B: DDL EXECUTION (Hanya dijalankan jika seluruh FASE A sukses 100%)
--     1. ADD KATA_SANDI jika belum ada.
--     2. ADD STATUS_AKUN jika belum ada.
--     3. ADD CONSTRAINT CK_HRS_PKL_STATUS_AKUN jika belum ada.
--     4. ADD CONSTRAINT UK_HRS_PKL_EMAIL jika belum ada.
--
-- PENTING:
-- Script ini TIDAK menghapus data, TIDAK memodifikasi data existing, TIDAK mengubah tabel lain,
-- dan TIDAK membuat tabel duplikat.
-- Catatan Transaksi: Oracle DDL menjalankan implicit commit secara otomatis sebelum dan setelah setiap perintah DDL.
-- Pemisahan Fase A dan Fase B menjamin tidak ada DDL parsial yang tereksekusi akibat kegagalan validasi logika.

DECLARE
    -- Counter & Flag variabel
    v_table_count         NUMBER := 0;
    v_email_col_count     NUMBER := 0;
    v_col_count           NUMBER := 0;
    v_constraint_count    NUMBER := 0;
    v_duplicate_count     NUMBER := 0;

    -- Action flags (ditentukan pada Fase A, dieksekusi pada Fase B)
    v_add_kata_sandi      BOOLEAN := FALSE;
    v_add_status_akun     BOOLEAN := FALSE;
    v_add_ck_status_akun  BOOLEAN := FALSE;
    v_add_uk_email        BOOLEAN := FALSE;

    -- Variabel penampung inspeksi metadata kolom
    v_col_type            USER_TAB_COLUMNS.DATA_TYPE%TYPE;
    v_col_length          USER_TAB_COLUMNS.CHAR_LENGTH%TYPE;
    v_col_char_used       USER_TAB_COLUMNS.CHAR_USED%TYPE;
    v_col_nullable        USER_TAB_COLUMNS.NULLABLE%TYPE;
    v_col_default         VARCHAR2(4000);

    -- Variabel penampung inspeksi metadata constraint
    v_const_table         USER_CONSTRAINTS.TABLE_NAME%TYPE;
    v_const_type          USER_CONSTRAINTS.CONSTRAINT_TYPE%TYPE;
    v_const_status        USER_CONSTRAINTS.STATUS%TYPE;
    v_const_validated     USER_CONSTRAINTS.VALIDATED%TYPE;
    v_const_deferrable    USER_CONSTRAINTS.DEFERRABLE%TYPE;
    v_const_deferred      USER_CONSTRAINTS.DEFERRED%TYPE;
    v_const_condition     VARCHAR2(4000);
    v_col_position        USER_CONS_COLUMNS.POSITION%TYPE;
BEGIN
    ----------------------------------------------------------------------------
    -- FASE A: PRE-VALIDATION (HANYA PEMERIKSAAN METADATA & DATA, TANPA DDL)
    ----------------------------------------------------------------------------

    -- A.1. Validasi Keberadaan Tabel Target
    SELECT COUNT(*)
    INTO v_table_count
    FROM USER_TABLES
    WHERE TABLE_NAME = 'HRS_PROFILE_KANDIDAT_LOGIN';

    IF v_table_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20001, 'Pre-validation failed: Required table HRS_PROFILE_KANDIDAT_LOGIN does not exist.');
    END IF;

    -- A.2. Validasi Keberadaan dan Tipe Kolom EMAIL
    SELECT COUNT(*)
    INTO v_email_col_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_PROFILE_KANDIDAT_LOGIN'
      AND COLUMN_NAME = 'EMAIL';

    IF v_email_col_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20002, 'Pre-validation failed: Column EMAIL does not exist in HRS_PROFILE_KANDIDAT_LOGIN.');
    END IF;

    SELECT DATA_TYPE
    INTO v_col_type
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_PROFILE_KANDIDAT_LOGIN'
      AND COLUMN_NAME = 'EMAIL';

    IF v_col_type <> 'VARCHAR2' THEN
        RAISE_APPLICATION_ERROR(-20003, 'Pre-validation failed: Column EMAIL must be of type VARCHAR2.');
    END IF;

    -- A.3. Validasi Definisi Kolom KATA_SANDI (jika sudah ada)
    SELECT COUNT(*)
    INTO v_col_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_PROFILE_KANDIDAT_LOGIN'
      AND COLUMN_NAME = 'KATA_SANDI';

    IF v_col_count = 0 THEN
        v_add_kata_sandi := TRUE;
    ELSE
        SELECT DATA_TYPE, CHAR_LENGTH, CHAR_USED, NULLABLE
        INTO v_col_type, v_col_length, v_col_char_used, v_col_nullable
        FROM USER_TAB_COLUMNS
        WHERE TABLE_NAME = 'HRS_PROFILE_KANDIDAT_LOGIN'
          AND COLUMN_NAME = 'KATA_SANDI';

        IF v_col_type <> 'VARCHAR2'
           OR v_col_char_used <> 'C'
           OR v_col_length < 255
           OR v_col_nullable <> 'Y' THEN
            RAISE_APPLICATION_ERROR(
                -20004,
                'Pre-validation failed: Existing KATA_SANDI definition is incompatible (must be VARCHAR2(>=255 CHAR) NULL).'
            );
        END IF;
    END IF;

    -- A.4. Validasi Definisi Kolom STATUS_AKUN (jika sudah ada)
    SELECT COUNT(*)
    INTO v_col_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_PROFILE_KANDIDAT_LOGIN'
      AND COLUMN_NAME = 'STATUS_AKUN';

    IF v_col_count = 0 THEN
        v_add_status_akun := TRUE;
    ELSE
        SELECT DATA_TYPE, CHAR_LENGTH, CHAR_USED, NULLABLE, DATA_DEFAULT
        INTO v_col_type, v_col_length, v_col_char_used, v_col_nullable, v_col_default
        FROM USER_TAB_COLUMNS
        WHERE TABLE_NAME = 'HRS_PROFILE_KANDIDAT_LOGIN'
          AND COLUMN_NAME = 'STATUS_AKUN';

        IF v_col_type <> 'VARCHAR2'
           OR v_col_char_used <> 'C'
           OR v_col_length < 20
           OR v_col_nullable <> 'N'
           OR UPPER(TRIM(v_col_default)) <> '''ACTIVE''' THEN
            RAISE_APPLICATION_ERROR(
                -20005,
                'Pre-validation failed: Existing STATUS_AKUN definition is incompatible (must be VARCHAR2(>=20 CHAR) DEFAULT ''ACTIVE'' NOT NULL).'
            );
        END IF;
    END IF;

    -- A.5. Validasi Definisi Check Constraint CK_HRS_PKL_STATUS_AKUN (jika sudah ada)
    SELECT COUNT(*)
    INTO v_constraint_count
    FROM USER_CONSTRAINTS
    WHERE CONSTRAINT_NAME = 'CK_HRS_PKL_STATUS_AKUN';

    IF v_constraint_count = 0 THEN
        v_add_ck_status_akun := TRUE;
    ELSE
        SELECT TABLE_NAME, CONSTRAINT_TYPE, STATUS, VALIDATED, SEARCH_CONDITION
        INTO v_const_table, v_const_type, v_const_status, v_const_validated, v_const_condition
        FROM USER_CONSTRAINTS
        WHERE CONSTRAINT_NAME = 'CK_HRS_PKL_STATUS_AKUN';

        IF v_const_table <> 'HRS_PROFILE_KANDIDAT_LOGIN'
           OR v_const_type <> 'C'
           OR v_const_status <> 'ENABLED'
           OR v_const_validated <> 'VALIDATED'
           OR UPPER(REGEXP_REPLACE(TRIM(v_const_condition), '[[:space:]]+', ' '))
              NOT IN ('STATUS_AKUN IN (''ACTIVE'', ''INACTIVE'')', 'STATUS_AKUN IN (''ACTIVE'',''INACTIVE'')') THEN
            RAISE_APPLICATION_ERROR(
                -20006,
                'Pre-validation failed: Existing CK_HRS_PKL_STATUS_AKUN definition is incompatible (must be ENABLED, VALIDATED, and match condition).'
            );
        END IF;
    END IF;

    -- A.6. Validasi Duplikasi Data Email Existing
    -- Memeriksa format canonical case-insensitive untuk memastikan kebersihan data
    SELECT COUNT(*)
    INTO v_duplicate_count
    FROM (
        SELECT LOWER(TRIM(EMAIL)) AS canonical_email
        FROM HRS_PROFILE_KANDIDAT_LOGIN
        WHERE EMAIL IS NOT NULL
        GROUP BY LOWER(TRIM(EMAIL))
        HAVING COUNT(*) > 1
    );

    IF v_duplicate_count > 0 THEN
        RAISE_APPLICATION_ERROR(
            -20007,
            'Pre-validation failed: Duplicate emails found in HRS_PROFILE_KANDIDAT_LOGIN. Clean data before migrating.'
        );
    END IF;

    -- A.7. Validasi Definisi Unique Constraint UK_HRS_PKL_EMAIL (jika sudah ada)
    SELECT COUNT(*)
    INTO v_constraint_count
    FROM USER_CONSTRAINTS
    WHERE CONSTRAINT_NAME = 'UK_HRS_PKL_EMAIL';

    IF v_constraint_count = 0 THEN
        v_add_uk_email := TRUE;
    ELSE
        SELECT TABLE_NAME, CONSTRAINT_TYPE, STATUS, VALIDATED, DEFERRABLE, DEFERRED
        INTO v_const_table, v_const_type, v_const_status, v_const_validated, v_const_deferrable, v_const_deferred
        FROM USER_CONSTRAINTS
        WHERE CONSTRAINT_NAME = 'UK_HRS_PKL_EMAIL';

        IF v_const_table <> 'HRS_PROFILE_KANDIDAT_LOGIN'
           OR v_const_type <> 'U'
           OR v_const_status <> 'ENABLED'
           OR v_const_validated <> 'VALIDATED'
           OR v_const_deferrable <> 'NOT DEFERRABLE'
           OR v_const_deferred <> 'IMMEDIATE' THEN
            RAISE_APPLICATION_ERROR(
                -20008,
                'Pre-validation failed: Existing UK_HRS_PKL_EMAIL is incompatible (must be ENABLED, VALIDATED, NOT DEFERRABLE, IMMEDIATE on target table).'
            );
        END IF;

        -- Pastikan constraint mengikat kolom EMAIL pada tabel yang benar dan pada posisi 1
        SELECT COUNT(*), MAX(POSITION)
        INTO v_col_count, v_col_position
        FROM USER_CONS_COLUMNS
        WHERE CONSTRAINT_NAME = 'UK_HRS_PKL_EMAIL'
          AND TABLE_NAME = 'HRS_PROFILE_KANDIDAT_LOGIN'
          AND COLUMN_NAME = 'EMAIL';

        IF v_col_count <> 1 OR v_col_position <> 1 THEN
            RAISE_APPLICATION_ERROR(
                -20009,
                'Pre-validation failed: Existing UK_HRS_PKL_EMAIL must bind column EMAIL at position 1.'
            );
        END IF;

        -- Pastikan constraint hanya mengikat satu kolom (bukan composite unique)
        SELECT COUNT(*)
        INTO v_col_count
        FROM USER_CONS_COLUMNS
        WHERE CONSTRAINT_NAME = 'UK_HRS_PKL_EMAIL';

        IF v_col_count <> 1 THEN
            RAISE_APPLICATION_ERROR(
                -20010,
                'Pre-validation failed: Existing UK_HRS_PKL_EMAIL covers more than one column.'
            );
        END IF;
    END IF;

    ----------------------------------------------------------------------------
    -- FASE B: DDL EXECUTION (HANYA DIEKSEKUSI JIKA SELURUH FASE A BERHASIL 100%)
    ----------------------------------------------------------------------------

    -- B.1. Tambahkan kolom KATA_SANDI jika belum ada
    IF v_add_kata_sandi THEN
        EXECUTE IMMEDIATE
            'ALTER TABLE HRS_PROFILE_KANDIDAT_LOGIN ADD (KATA_SANDI VARCHAR2(255 CHAR))';
    END IF;

    -- B.2. Tambahkan kolom STATUS_AKUN jika belum ada
    IF v_add_status_akun THEN
        EXECUTE IMMEDIATE
            'ALTER TABLE HRS_PROFILE_KANDIDAT_LOGIN ADD (STATUS_AKUN VARCHAR2(20 CHAR) DEFAULT ''ACTIVE'' NOT NULL)';
    END IF;

    -- B.3. Tambahkan Check Constraint CK_HRS_PKL_STATUS_AKUN jika belum ada
    IF v_add_ck_status_akun THEN
        EXECUTE IMMEDIATE
            'ALTER TABLE HRS_PROFILE_KANDIDAT_LOGIN ADD CONSTRAINT CK_HRS_PKL_STATUS_AKUN CHECK (STATUS_AKUN IN (''ACTIVE'', ''INACTIVE''))';
    END IF;

    -- B.4. Tambahkan Unique Constraint UK_HRS_PKL_EMAIL jika belum ada
    IF v_add_uk_email THEN
        EXECUTE IMMEDIATE
            'ALTER TABLE HRS_PROFILE_KANDIDAT_LOGIN ADD CONSTRAINT UK_HRS_PKL_EMAIL UNIQUE (EMAIL)';
    END IF;

END;
/

-- Safe validation: query ini hanya membaca struktur dan constraint tanpa mengubah data apapun.
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHAR_LENGTH, CHAR_USED, NULLABLE, DATA_DEFAULT
FROM USER_TAB_COLUMNS
WHERE TABLE_NAME = 'HRS_PROFILE_KANDIDAT_LOGIN'
  AND COLUMN_NAME IN ('KATA_SANDI', 'STATUS_AKUN', 'EMAIL')
ORDER BY COLUMN_NAME;

SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE, STATUS, VALIDATED, DEFERRABLE, DEFERRED, SEARCH_CONDITION
FROM USER_CONSTRAINTS
WHERE TABLE_NAME = 'HRS_PROFILE_KANDIDAT_LOGIN'
  AND CONSTRAINT_NAME IN ('CK_HRS_PKL_STATUS_AKUN', 'UK_HRS_PKL_EMAIL');
