-- Applicant registration and edit-profile data not represented by the
-- existing HRS_PROFIL_KANDIDAT columns is stored in this linked JSON document.
-- Run only after the Oracle owner reviews and approves the schema change.
BEGIN
    EXECUTE IMMEDIATE '
        CREATE TABLE HRS_PROFIL_KANDIDAT_DETAIL (
            KANDIDAT_ID NUMBER NOT NULL,
            DATA_PROFIL CLOB NOT NULL,
            DIBUAT_PADA TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP NOT NULL,
            DIPERBARUI_PADA TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP NOT NULL,
            CONSTRAINT PK_HRS_PROFIL_KANDIDAT_DETAIL PRIMARY KEY (KANDIDAT_ID),
            CONSTRAINT FK_HRS_PROFIL_KANDIDAT_DETAIL FOREIGN KEY (KANDIDAT_ID)
                REFERENCES HRS_PROFIL_KANDIDAT (KANDIDAT_ID) ON DELETE CASCADE
        )';
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE != -00955 THEN
            RAISE;
        END IF;
END;
/