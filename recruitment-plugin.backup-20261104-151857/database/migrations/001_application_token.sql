-- Run once against the existing Oracle recruitment schema.
BEGIN
    EXECUTE IMMEDIATE 'ALTER TABLE daw_applications ADD (application_token VARCHAR2(32 CHAR))';
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE != -01430 THEN
            RAISE;
        END IF;
END;
/

BEGIN
    EXECUTE IMMEDIATE 'CREATE UNIQUE INDEX daw_applications_token_uq ON daw_applications (application_token)';
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE != -00955 THEN
            RAISE;
        END IF;
END;
/