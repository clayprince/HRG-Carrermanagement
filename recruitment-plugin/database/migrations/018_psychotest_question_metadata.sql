-- Run only after the Oracle owner reviews and approves the target schema.
-- Adds per-question explanations and active status for the psychotest bank.
DECLARE
    object_count NUMBER;
    column_type VARCHAR2(128);
    column_precision NUMBER;
    column_scale NUMBER;
    column_nullable VARCHAR2(1);
BEGIN
    SELECT COUNT(*) INTO object_count
    FROM USER_TABLES
    WHERE TABLE_NAME IN ('HRS_PERTANYAAN_PSIKOTES', 'HRS_TES_PSIKOLOGI');
    IF object_count <> 2 THEN
        RAISE_APPLICATION_ERROR(-20081, 'Psychotest question and test tables are required before this migration.');
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_PERTANYAAN_PSIKOTES'
      AND COLUMN_NAME = 'PEMBAHASAN';
    IF object_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE HRS_PERTANYAAN_PSIKOTES ADD (PEMBAHASAN CLOB)';
    ELSE
        SELECT DATA_TYPE INTO column_type
        FROM USER_TAB_COLUMNS
        WHERE TABLE_NAME = 'HRS_PERTANYAAN_PSIKOTES'
          AND COLUMN_NAME = 'PEMBAHASAN';
        IF column_type <> 'CLOB' THEN
            RAISE_APPLICATION_ERROR(-20082, 'HRS_PERTANYAAN_PSIKOTES.PEMBAHASAN must be a CLOB.');
        END IF;
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_PERTANYAAN_PSIKOTES'
      AND COLUMN_NAME = 'IS_ACTIVE';
    IF object_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE HRS_PERTANYAAN_PSIKOTES ADD (IS_ACTIVE NUMBER(1,0) DEFAULT 1 NOT NULL)';
    ELSE
        SELECT DATA_TYPE, DATA_PRECISION, DATA_SCALE, NULLABLE
        INTO column_type, column_precision, column_scale, column_nullable
        FROM USER_TAB_COLUMNS
        WHERE TABLE_NAME = 'HRS_PERTANYAAN_PSIKOTES'
          AND COLUMN_NAME = 'IS_ACTIVE';
        IF column_type <> 'NUMBER'
            OR column_precision IS NULL
            OR column_precision <> 1
            OR NVL(column_scale, 0) <> 0
            OR column_nullable <> 'N' THEN
            RAISE_APPLICATION_ERROR(-20083, 'HRS_PERTANYAAN_PSIKOTES.IS_ACTIVE must be NUMBER(1,0) NOT NULL.');
        END IF;
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_CONSTRAINTS
    WHERE TABLE_NAME = 'HRS_PERTANYAAN_PSIKOTES'
      AND CONSTRAINT_NAME = 'CK_HPS_IS_ACTIVE'
      AND CONSTRAINT_TYPE = 'C'
      AND STATUS = 'ENABLED';
    IF object_count = 0 THEN
        SELECT COUNT(*) INTO object_count
        FROM USER_CONSTRAINTS
        WHERE TABLE_NAME = 'HRS_PERTANYAAN_PSIKOTES'
          AND CONSTRAINT_NAME = 'CK_HPS_IS_ACTIVE';
        IF object_count > 0 THEN
            RAISE_APPLICATION_ERROR(-20084, 'CK_HPS_IS_ACTIVE exists but is not an enabled check constraint.');
        END IF;
        EXECUTE IMMEDIATE '
            ALTER TABLE HRS_PERTANYAAN_PSIKOTES
            ADD CONSTRAINT CK_HPS_IS_ACTIVE CHECK (IS_ACTIVE IN (0, 1))';
    END IF;
END;
/
