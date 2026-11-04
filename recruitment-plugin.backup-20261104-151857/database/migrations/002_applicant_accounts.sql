-- Run only after the Oracle owner approves this schema change.
BEGIN
    EXECUTE IMMEDIATE '
        CREATE TABLE akun_pelamar (
            email VARCHAR2(254 CHAR) NOT NULL,
            nama_lengkap VARCHAR2(150 CHAR) NOT NULL,
            password_hash VARCHAR2(255 CHAR) NOT NULL,
            status_akun VARCHAR2(10 CHAR) DEFAULT ''ACTIVE'' NOT NULL,
            dibuat_pada TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP NOT NULL,
            diperbarui_pada TIMESTAMP(6),
            CONSTRAINT akun_pelamar_pk PRIMARY KEY (email),
            CONSTRAINT akun_pelamar_status_ck CHECK (status_akun IN (''ACTIVE'', ''INACTIVE''))
        )';
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE != -00955 THEN
            RAISE;
        END IF;
END;
/
