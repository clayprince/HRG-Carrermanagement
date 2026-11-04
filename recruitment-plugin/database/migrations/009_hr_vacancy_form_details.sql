-- Run only after review and approval by the Oracle owner.
-- Stores HR vacancy-form fields that do not have dedicated ERD columns.
DECLARE
    column_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO column_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_REQUEST_LOWONGAN'
      AND COLUMN_NAME = 'HR_FORM_DETAILS';

    IF column_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE HRS_REQUEST_LOWONGAN ADD (HR_FORM_DETAILS CLOB)';
    END IF;
END;
/
