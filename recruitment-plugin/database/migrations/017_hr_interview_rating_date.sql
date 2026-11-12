-- Run only after the Oracle owner reviews and approves the target schema.
-- Requires migration 016_hr_interview_department_assignment.sql.
-- Adds HR interview ratings, recommendations, and append-only note history.
DECLARE
    object_count NUMBER;
    next_sequence_value NUMBER;
BEGIN
    SELECT COUNT(*) INTO object_count
    FROM USER_TABLES
    WHERE TABLE_NAME IN ('HRS_LAMARAN', 'HRS_ROLE_AKSES', 'HRS_HASIL_WAWANCARA_HR');
    IF object_count <> 3 THEN
        RAISE_APPLICATION_ERROR(-20071, 'Required tables HRS_LAMARAN, HRS_ROLE_AKSES, and HRS_HASIL_WAWANCARA_HR must exist in the current schema. Apply migration 016 first.');
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_CONSTRAINTS
    WHERE TABLE_NAME = 'HRS_HASIL_WAWANCARA_HR'
      AND CONSTRAINT_NAME = 'PK_HRS_HASIL_WAWANCARA_HR'
      AND STATUS = 'ENABLED';
    IF object_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20072, 'The HR interview assignment primary key from migration 016 is missing or disabled.');
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_HASIL_WAWANCARA_HR'
      AND COLUMN_NAME = 'TANGGAL_WAWANCARA';
    IF object_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE HRS_HASIL_WAWANCARA_HR ADD (TANGGAL_WAWANCARA DATE)';
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_HASIL_WAWANCARA_HR'
      AND COLUMN_NAME = 'PENILAIAN';
    IF object_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE HRS_HASIL_WAWANCARA_HR ADD (PENILAIAN VARCHAR2(20 CHAR))';
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_HASIL_WAWANCARA_HR'
      AND COLUMN_NAME = 'CATATAN_REKOMENDASI';
    IF object_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE HRS_HASIL_WAWANCARA_HR ADD (CATATAN_REKOMENDASI VARCHAR2(1000 CHAR))';
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'HRS_HASIL_WAWANCARA_HR'
      AND COLUMN_NAME = 'NAMA_DEPARTEMEN_TUJUAN';
    IF object_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE HRS_HASIL_WAWANCARA_HR ADD (NAMA_DEPARTEMEN_TUJUAN VARCHAR2(100 CHAR))';
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_CONSTRAINTS
    WHERE TABLE_NAME = 'HRS_HASIL_WAWANCARA_HR'
      AND CONSTRAINT_NAME = 'CK_HRH_PENILAIAN';
    IF object_count = 0 THEN
        EXECUTE IMMEDIATE '
            ALTER TABLE HRS_HASIL_WAWANCARA_HR
            ADD CONSTRAINT CK_HRH_PENILAIAN CHECK (
                PENILAIAN IS NULL OR PENILAIAN IN (''Sangat Baik'', ''Baik'', ''Cukup'', ''Kurang'')
            )';
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_TABLES
    WHERE TABLE_NAME = 'HRS_RIWAYAT_WAWANCARA_HR';
    IF object_count = 0 THEN
        EXECUTE IMMEDIATE '
            CREATE TABLE HRS_RIWAYAT_WAWANCARA_HR (
                RIWAYAT_ID NUMBER(19,0) NOT NULL,
                LAMARAN_ID NUMBER(19,0) NOT NULL,
                TANGGAL_WAWANCARA DATE NOT NULL,
                NAMA_PEWAWANCARA VARCHAR2(100 CHAR) NOT NULL,
                PENILAIAN VARCHAR2(20 CHAR) NOT NULL,
                REKOMENDASI VARCHAR2(80 CHAR) NOT NULL,
                NAMA_DEPARTEMEN_TUJUAN VARCHAR2(100 CHAR),
                ROLE_AKSES_ID NUMBER(19,0),
                CATATAN_WAWANCARA VARCHAR2(2000 CHAR),
                CATATAN_REKOMENDASI VARCHAR2(1000 CHAR),
                DIBUAT_PADA TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP NOT NULL,
                CONSTRAINT PK_HRH_RIWAYAT PRIMARY KEY (RIWAYAT_ID),
                CONSTRAINT FK_HRH_RIWAYAT_LAMARAN FOREIGN KEY (LAMARAN_ID)
                    REFERENCES HRS_LAMARAN (LAMARAN_ID),
                CONSTRAINT FK_HRH_RIWAYAT_AKSES FOREIGN KEY (ROLE_AKSES_ID)
                    REFERENCES HRS_ROLE_AKSES (ROLE_AKSES_ID),
                CONSTRAINT CK_HRH_RIWAYAT_NILAI CHECK (
                    PENILAIAN IN (''Sangat Baik'', ''Baik'', ''Cukup'', ''Kurang'')
                ),
                CONSTRAINT CK_HRH_RIWAYAT_REK CHECK (
                    REKOMENDASI IN (''Lanjut ke Tahap Berikutnya'', ''Tidak Dilanjutkan'')
                )
            )';
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_SEQUENCES
    WHERE SEQUENCE_NAME = 'SEQ_HRH_RIWAYAT';
    IF object_count = 0 THEN
        EXECUTE IMMEDIATE
            'SELECT NVL(MAX(RIWAYAT_ID), 0) + 1 FROM HRS_RIWAYAT_WAWANCARA_HR'
            INTO next_sequence_value;
        EXECUTE IMMEDIATE
            'CREATE SEQUENCE SEQ_HRH_RIWAYAT START WITH '
            || TO_CHAR(next_sequence_value, 'FM9999999999999999999')
            || ' INCREMENT BY 1 NOCYCLE';
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_INDEXES
    WHERE INDEX_NAME = 'IX_HRH_RIWAYAT_APP';
    IF object_count = 0 THEN
        EXECUTE IMMEDIATE '
            CREATE INDEX IX_HRH_RIWAYAT_APP
            ON HRS_RIWAYAT_WAWANCARA_HR (LAMARAN_ID, DIBUAT_PADA, RIWAYAT_ID)';
    END IF;
END;
/