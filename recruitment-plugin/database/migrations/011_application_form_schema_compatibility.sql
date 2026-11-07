-- Run manually after reviewing the Oracle schema. This migration is not
-- executed by the plugin and does not remove or truncate existing data.
--
-- Migration 003 assumes that BAGIAN_PENDAFTARAN already contains the
-- descriptive columns used by the form reader and writer. Older schemas do
-- not always contain those columns, which causes ORA-00904 when the form is
-- opened.
DECLARE
    column_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO column_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'BAGIAN_PENDAFTARAN'
      AND COLUMN_NAME = 'DESKRIPSI';
    IF column_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE BAGIAN_PENDAFTARAN ADD (DESKRIPSI VARCHAR2(1000 CHAR))';
    END IF;

    SELECT COUNT(*) INTO column_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'BAGIAN_PENDAFTARAN'
      AND COLUMN_NAME = 'DISPLAY_ORDER';
    IF column_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE BAGIAN_PENDAFTARAN ADD (DISPLAY_ORDER NUMBER(10,0) DEFAULT 0)';
    END IF;

    SELECT COUNT(*) INTO column_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'BAGIAN_PENDAFTARAN'
      AND COLUMN_NAME = 'UPDATE_AT';
    IF column_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE BAGIAN_PENDAFTARAN ADD (UPDATE_AT TIMESTAMP(6) DEFAULT SYSTIMESTAMP)';
    END IF;

    SELECT COUNT(*) INTO column_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'BAGIAN_PENDAFTARAN'
      AND COLUMN_NAME = 'CREATED_AT';
    IF column_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE BAGIAN_PENDAFTARAN ADD (CREATED_AT TIMESTAMP(6) DEFAULT SYSTIMESTAMP)';
    END IF;
END;
/

-- These checks make the required form objects explicit. If one of these
-- objects is absent, run 003_dynamic_application_forms.sql before this file.
DECLARE
    object_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO object_count
    FROM USER_TABLES
    WHERE TABLE_NAME = 'RECRUIT_FORM_FIELDS';
    IF object_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20011, 'RECRUIT_FORM_FIELDS is missing; run migration 003_dynamic_application_forms.sql first.');
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_TABLES
    WHERE TABLE_NAME = 'RECRUIT_FORM_OPTIONS';
    IF object_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20012, 'RECRUIT_FORM_OPTIONS is missing; run migration 003_dynamic_application_forms.sql first.');
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_TABLES
    WHERE TABLE_NAME = 'RECRUIT_FORM_SUBMISSIONS';
    IF object_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20013, 'RECRUIT_FORM_SUBMISSIONS is missing; run migration 003_dynamic_application_forms.sql first.');
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_TABLES
    WHERE TABLE_NAME = 'RECRUIT_FORM_ANSWERS';
    IF object_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20014, 'RECRUIT_FORM_ANSWERS is missing; run migration 003_dynamic_application_forms.sql first.');
    END IF;
END;
/
