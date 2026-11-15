-- Run only after the Oracle owner reviews and approves the target schema.
-- Existing events remain NULL because their original client metadata is unknown.
DECLARE
    column_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO column_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_PSY_PROCTOR_EVENTS'
      AND COLUMN_NAME = 'CLIENT_IP';
    IF column_count = 0 THEN
        EXECUTE IMMEDIATE
            'ALTER TABLE HRS_PSY_PROCTOR_EVENTS ADD (CLIENT_IP VARCHAR2(45 CHAR))';
    END IF;

    SELECT COUNT(*) INTO column_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_PSY_PROCTOR_EVENTS'
      AND COLUMN_NAME = 'CLIENT_USER_AGENT';
    IF column_count = 0 THEN
        EXECUTE IMMEDIATE
            'ALTER TABLE HRS_PSY_PROCTOR_EVENTS ADD (CLIENT_USER_AGENT VARCHAR2(512 CHAR))';
    END IF;
END;
/
