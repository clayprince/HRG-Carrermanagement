-- REVIEW ONLY: do not run until the Oracle owner approves the initial status
-- policy for existing HRS_ROLE_AKSES rows.
--
-- This migration intentionally does not:
--   * change PERAN, including NULL roles;
--   * delete or merge duplicate accounts;
--   * add unique constraints;
--   * insert or update account rows.
--
-- The ACTIVE default applies to existing rows when the column is added and to
-- future rows that omit STATUS_AKUN. This is a business policy that requires
-- approval from the database owner. Rows with NULL PERAN remain unable to
-- authenticate until their role is reviewed separately.

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
                -20002,
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
                -20003,
                'Existing HRS_ROLE_AKSES_STATUS_CK definition is incompatible.'
            );
        END IF;
    END IF;
END;
/

-- Oracle DDL performs an implicit commit. No explicit data commit is needed:
-- this migration contains no INSERT, UPDATE, DELETE, or MERGE.

-- Validation before approval/execution:
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    DATA_TYPE,
    CHAR_LENGTH,
    NULLABLE,
    DATA_DEFAULT
FROM USER_TAB_COLUMNS
WHERE TABLE_NAME = 'HRS_ROLE_AKSES'
  AND COLUMN_NAME = 'STATUS_AKUN';

SELECT
    TABLE_NAME,
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    STATUS,
    SEARCH_CONDITION
FROM USER_CONSTRAINTS
WHERE CONSTRAINT_NAME = 'HRS_ROLE_AKSES_STATUS_CK';

-- Validation after approval/execution:
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    DATA_TYPE,
    CHAR_LENGTH,
    NULLABLE,
    DATA_DEFAULT
FROM USER_TAB_COLUMNS
WHERE TABLE_NAME = 'HRS_ROLE_AKSES'
  AND COLUMN_NAME = 'STATUS_AKUN';

SELECT
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    SEARCH_CONDITION
FROM USER_CONSTRAINTS
WHERE TABLE_NAME = 'HRS_ROLE_AKSES'
  AND CONSTRAINT_NAME = 'HRS_ROLE_AKSES_STATUS_CK';

SELECT
    ROLE_AKSES_ID,
    EMAIL,
    USERNAME,
    PERAN,
    DEPARTEMEN_ID,
    STATUS_AKUN
FROM HRS_ROLE_AKSES
ORDER BY ROLE_AKSES_ID;

-- Manual rollback, only with Oracle owner approval:
-- ALTER TABLE HRS_ROLE_AKSES
-- DROP CONSTRAINT HRS_ROLE_AKSES_STATUS_CK;
--
-- ALTER TABLE HRS_ROLE_AKSES
-- DROP COLUMN STATUS_AKUN;
--
-- Oracle DDL performs implicit commits. This rollback removes the schema
-- objects created by this migration but cannot restore unrelated transactions
-- or any later data written using STATUS_AKUN.
