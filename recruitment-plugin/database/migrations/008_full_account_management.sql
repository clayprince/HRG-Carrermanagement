-- DAW Recruitment - Full Oracle account-management schema
-- Target schema: DAW_RECRUITMENT
-- This script is idempotent and may be run again safely.
--
-- Changes:
--   1. HRS_ROLE_AKSES.STATUS_AKUN with ACTIVE default and status constraint.
--   2. HRS_ROLE_AKSES.NAMA_LENGKAP and JABATAN.
--   3. HRS_ROLE_AKSES_FITUR for per-account feature permissions.
--   4. The 12 approved department names in HRS_DEPARTEMEN.
--
-- This script does not change passwords, email, usernames, roles, or existing
-- account rows. Oracle DDL performs implicit commits.

DECLARE
    table_count NUMBER;
    column_count NUMBER;
    constraint_count NUMBER;
    status_type USER_TAB_COLUMNS.DATA_TYPE%TYPE;
    status_length USER_TAB_COLUMNS.CHAR_LENGTH%TYPE;
    status_nullable USER_TAB_COLUMNS.NULLABLE%TYPE;
    status_default VARCHAR2(4000);
    constraint_table USER_CONSTRAINTS.TABLE_NAME%TYPE;
    constraint_type USER_CONSTRAINTS.CONSTRAINT_TYPE%TYPE;
    constraint_status USER_CONSTRAINTS.STATUS%TYPE;
    constraint_condition VARCHAR2(4000);
BEGIN
    SELECT COUNT(*)
    INTO table_count
    FROM USER_TABLES
    WHERE TABLE_NAME = 'HRS_ROLE_AKSES';

    IF table_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20001, 'Required table HRS_ROLE_AKSES does not exist.');
    END IF;

    SELECT COUNT(*)
    INTO table_count
    FROM USER_TABLES
    WHERE TABLE_NAME = 'HRS_DEPARTEMEN';

    IF table_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20002, 'Required table HRS_DEPARTEMEN does not exist.');
    END IF;

    SELECT COUNT(*)
    INTO column_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_ROLE_AKSES'
      AND COLUMN_NAME = 'STATUS_AKUN';

    IF column_count = 0 THEN
        EXECUTE IMMEDIATE
            'ALTER TABLE HRS_ROLE_AKSES ADD (STATUS_AKUN VARCHAR2(8 CHAR) DEFAULT ''ACTIVE'' NOT NULL)';
    ELSE
        SELECT DATA_TYPE, CHAR_LENGTH, NULLABLE, DATA_DEFAULT
        INTO status_type, status_length, status_nullable, status_default
        FROM USER_TAB_COLUMNS
        WHERE TABLE_NAME = 'HRS_ROLE_AKSES'
          AND COLUMN_NAME = 'STATUS_AKUN';

        IF status_type <> 'VARCHAR2'
           OR status_length <> 8
           OR status_nullable <> 'N'
           OR UPPER(TRIM(status_default)) <> '''ACTIVE''' THEN
            RAISE_APPLICATION_ERROR(
                -20003,
                'Existing HRS_ROLE_AKSES.STATUS_AKUN definition is incompatible.'
            );
        END IF;
    END IF;

    SELECT COUNT(*)
    INTO constraint_count
    FROM USER_CONSTRAINTS
    WHERE CONSTRAINT_NAME = 'HRS_ROLE_AKSES_STATUS_CK';

    IF constraint_count = 0 THEN
        EXECUTE IMMEDIATE
            'ALTER TABLE HRS_ROLE_AKSES ADD CONSTRAINT HRS_ROLE_AKSES_STATUS_CK CHECK (STATUS_AKUN IN (''ACTIVE'', ''INACTIVE''))';
    ELSE
        SELECT TABLE_NAME, CONSTRAINT_TYPE, STATUS, SEARCH_CONDITION
        INTO constraint_table, constraint_type, constraint_status, constraint_condition
        FROM USER_CONSTRAINTS
        WHERE CONSTRAINT_NAME = 'HRS_ROLE_AKSES_STATUS_CK';

        IF constraint_table <> 'HRS_ROLE_AKSES'
           OR constraint_type <> 'C'
           OR constraint_status <> 'ENABLED'
           OR UPPER(REGEXP_REPLACE(TRIM(constraint_condition), '[[:space:]]+', ' '))
              <> 'STATUS_AKUN IN (''ACTIVE'', ''INACTIVE'')' THEN
            RAISE_APPLICATION_ERROR(
                -20004,
                'Existing HRS_ROLE_AKSES_STATUS_CK definition is incompatible.'
            );
        END IF;
    END IF;

    SELECT COUNT(*)
    INTO column_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_ROLE_AKSES'
      AND COLUMN_NAME = 'NAMA_LENGKAP';

    IF column_count = 0 THEN
        EXECUTE IMMEDIATE
            'ALTER TABLE HRS_ROLE_AKSES ADD (NAMA_LENGKAP VARCHAR2(100 CHAR))';
    END IF;

    SELECT COUNT(*)
    INTO column_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_ROLE_AKSES'
      AND COLUMN_NAME = 'JABATAN';

    IF column_count = 0 THEN
        EXECUTE IMMEDIATE
            'ALTER TABLE HRS_ROLE_AKSES ADD (JABATAN VARCHAR2(100 CHAR))';
    END IF;

    SELECT COUNT(*)
    INTO table_count
    FROM USER_TABLES
    WHERE TABLE_NAME = 'HRS_ROLE_AKSES_FITUR';

    IF table_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE HRS_ROLE_AKSES_FITUR (
                ROLE_AKSES_ID NUMBER(19,0) NOT NULL,
                FITUR_KEY VARCHAR2(50 CHAR) NOT NULL,
                DIIZINKAN NUMBER(1,0) DEFAULT 0 NOT NULL,
                CONSTRAINT PK_HRS_ROLE_AKSES_FITUR
                    PRIMARY KEY (ROLE_AKSES_ID, FITUR_KEY),
                CONSTRAINT FK_HRS_ROLE_AKSES_FITUR
                    FOREIGN KEY (ROLE_AKSES_ID)
                    REFERENCES HRS_ROLE_AKSES (ROLE_AKSES_ID)
                    ON DELETE CASCADE
            )
        ]';
    END IF;
END;
/

MERGE INTO HRS_DEPARTEMEN target
USING (
    SELECT 'Management Development' nama FROM dual UNION ALL
    SELECT 'Retail' FROM dual UNION ALL
    SELECT 'HC3' FROM dual UNION ALL
    SELECT 'Network Development' FROM dual UNION ALL
    SELECT 'Sales & Marketing' FROM dual UNION ALL
    SELECT 'Technical Service' FROM dual UNION ALL
    SELECT 'Part Sales' FROM dual UNION ALL
    SELECT 'Promotion' FROM dual UNION ALL
    SELECT 'Supply Chain' FROM dual UNION ALL
    SELECT 'HR & GA' FROM dual UNION ALL
    SELECT 'IT' FROM dual UNION ALL
    SELECT 'FAT' FROM dual
) source
ON (UPPER(target.NAMA_DEPARTEMEN) = UPPER(source.nama))
WHEN NOT MATCHED THEN
    INSERT (NAMA_DEPARTEMEN, STATUS_DEPARTEMEN)
    VALUES (source.nama, 1);

COMMIT;

-- Safe validation: these queries expose structure and counts only.
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHAR_LENGTH, NULLABLE, DATA_DEFAULT
FROM USER_TAB_COLUMNS
WHERE TABLE_NAME = 'HRS_ROLE_AKSES'
  AND COLUMN_NAME IN ('STATUS_AKUN', 'NAMA_LENGKAP', 'JABATAN')
ORDER BY COLUMN_NAME;

SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE, STATUS, SEARCH_CONDITION
FROM USER_CONSTRAINTS
WHERE TABLE_NAME = 'HRS_ROLE_AKSES'
  AND CONSTRAINT_NAME = 'HRS_ROLE_AKSES_STATUS_CK';

SELECT TABLE_NAME
FROM USER_TABLES
WHERE TABLE_NAME = 'HRS_ROLE_AKSES_FITUR';

SELECT COUNT(*) AS APPROVED_DEPARTMENT_COUNT
FROM HRS_DEPARTEMEN
WHERE UPPER(NAMA_DEPARTEMEN) IN (
    'MANAGEMENT DEVELOPMENT', 'RETAIL', 'HC3', 'NETWORK DEVELOPMENT',
    'SALES & MARKETING', 'TECHNICAL SERVICE', 'PART SALES', 'PROMOTION',
    'SUPPLY CHAIN', 'HR & GA', 'IT', 'FAT'
);
