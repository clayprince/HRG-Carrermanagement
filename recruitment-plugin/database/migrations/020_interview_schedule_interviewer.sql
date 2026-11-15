-- Run only after the Oracle owner verifies migrations 018 and 019.
-- Adds the required interviewer name to each HR/User schedule slot.
DECLARE
    object_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO object_count
    FROM USER_TABLES
    WHERE TABLE_NAME = 'HRS_INTERVIEW_SCHEDULE';
    IF object_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20086, 'Apply migration 018 before adding schedule interviewer names.');
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_INTERVIEW_SCHEDULE'
      AND COLUMN_NAME = 'INTERVIEWER_NAME';
    IF object_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE HRS_INTERVIEW_SCHEDULE ADD (INTERVIEWER_NAME VARCHAR2(100 CHAR))';
    END IF;
END;
/
