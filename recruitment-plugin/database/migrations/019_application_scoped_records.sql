-- Run only after the Oracle owner reviews and approves the target schema.
-- Adds application links to candidate-level schedules, psychometric results,
-- and final-decision records. Ambiguous legacy data remains unlinked.
DECLARE
    object_count NUMBER;
    column_type VARCHAR2(128);
    column_precision NUMBER;
    column_scale NUMBER;
    column_nullable VARCHAR2(1);
BEGIN
    SELECT COUNT(*) INTO object_count
    FROM USER_TABLES
    WHERE TABLE_NAME IN (
        'HRS_LAMARAN',
        'HRS_JADWAL_KANDIDAT',
        'HRS_HASIL_TES_PSIKOLOGI',
        'HRS_REKAPAN_KEPUTUSAN_HR',
        'HRS_PROFILE_KANDIDAT_LOGIN',
        'HRS_PROFIL_KANDIDAT',
        'HRS_DETAIL_INFO_LOWONGAN',
        'HRS_KOLOM_LANDING_PENDAFTARAN'
    );
    IF object_count <> 8 THEN
        RAISE_APPLICATION_ERROR(-20091, 'Required application, candidate, vacancy, schedule, psychometric, or decision tables are missing.');
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM (
        SELECT 'HRS_LAMARAN' AS table_name, 'LAMARAN_ID' AS column_name FROM DUAL
        UNION ALL SELECT 'HRS_LAMARAN', 'PROFILE_KANDIDAT_ID' FROM DUAL
        UNION ALL SELECT 'HRS_LAMARAN', 'DETAIL_LOWONGAN_ID' FROM DUAL
        UNION ALL SELECT 'HRS_PROFILE_KANDIDAT_LOGIN', 'PROFILE_KANDIDAT_LOGIN' FROM DUAL
        UNION ALL SELECT 'HRS_PROFILE_KANDIDAT_LOGIN', 'KANDIDAT_ID' FROM DUAL
        UNION ALL SELECT 'HRS_PROFIL_KANDIDAT', 'KANDIDAT_ID' FROM DUAL
        UNION ALL SELECT 'HRS_DETAIL_INFO_LOWONGAN', 'DETAIL_LOWONGAN_ID' FROM DUAL
        UNION ALL SELECT 'HRS_DETAIL_INFO_LOWONGAN', 'NAMA_DEPARTEMEN' FROM DUAL
        UNION ALL SELECT 'HRS_DETAIL_INFO_LOWONGAN', 'KOLOM_PENDAFTARAN_ID' FROM DUAL
        UNION ALL SELECT 'HRS_KOLOM_LANDING_PENDAFTARAN', 'KOLOM_PENDAFTARAN_ID' FROM DUAL
        UNION ALL SELECT 'HRS_KOLOM_LANDING_PENDAFTARAN', 'NAMA_DEPARTEMEN' FROM DUAL
        UNION ALL SELECT 'HRS_REKAPAN_KEPUTUSAN_HR', 'REKAPAN_KEPUTUSAN_ID' FROM DUAL
        UNION ALL SELECT 'HRS_REKAPAN_KEPUTUSAN_HR', 'KANDIDAT_ID' FROM DUAL
        UNION ALL SELECT 'HRS_REKAPAN_KEPUTUSAN_HR', 'NAMA_DEPARTEMENT' FROM DUAL
    ) required_column
    WHERE NOT EXISTS (
        SELECT 1
        FROM USER_TAB_COLUMNS c
        WHERE c.TABLE_NAME = required_column.table_name
          AND c.COLUMN_NAME = required_column.column_name
    );
    IF object_count <> 0 THEN
        RAISE_APPLICATION_ERROR(-20096, 'Required columns for application-link validation or safe backfill are missing.');
    END IF;

    SELECT COUNT(*) INTO object_count
    FROM USER_CONSTRAINTS c
    JOIN USER_CONS_COLUMNS cc
      ON cc.CONSTRAINT_NAME = c.CONSTRAINT_NAME
     AND cc.TABLE_NAME = c.TABLE_NAME
    WHERE c.TABLE_NAME = 'HRS_LAMARAN'
      AND c.CONSTRAINT_TYPE = 'P'
      AND c.STATUS = 'ENABLED'
      AND c.VALIDATED = 'VALIDATED'
      AND cc.COLUMN_NAME = 'LAMARAN_ID';
    IF object_count <> 1 THEN
        RAISE_APPLICATION_ERROR(-20092, 'HRS_LAMARAN must have an enabled primary key on LAMARAN_ID.');
    END IF;

    FOR item IN (
        SELECT 'HRS_JADWAL_KANDIDAT' AS table_name FROM DUAL
        UNION ALL
        SELECT 'HRS_HASIL_TES_PSIKOLOGI' FROM DUAL
        UNION ALL
        SELECT 'HRS_REKAPAN_KEPUTUSAN_HR' FROM DUAL
    ) LOOP
        SELECT COUNT(*) INTO object_count
        FROM USER_TAB_COLUMNS
        WHERE TABLE_NAME = item.table_name
          AND COLUMN_NAME = 'LAMARAN_ID';
        IF object_count = 0 THEN
            EXECUTE IMMEDIATE
                'ALTER TABLE ' || item.table_name || ' ADD (LAMARAN_ID NUMBER(19,0))';
        ELSE
            SELECT DATA_TYPE, DATA_PRECISION, DATA_SCALE, NULLABLE
            INTO column_type, column_precision, column_scale, column_nullable
            FROM USER_TAB_COLUMNS
            WHERE TABLE_NAME = item.table_name
              AND COLUMN_NAME = 'LAMARAN_ID';
            IF column_type <> 'NUMBER'
                OR NVL(column_precision, -1) <> 19
                OR NVL(column_scale, 0) <> 0
                OR column_nullable <> 'Y' THEN
                RAISE_APPLICATION_ERROR(-20093, item.table_name || '.LAMARAN_ID must be nullable NUMBER(19,0).');
            END IF;
        END IF;
    END LOOP;

    FOR item IN (
        SELECT 'HRS_JADWAL_KANDIDAT' AS table_name, 'FK_HRS_JK_LAMARAN' AS constraint_name FROM DUAL
        UNION ALL
        SELECT 'HRS_HASIL_TES_PSIKOLOGI', 'FK_HRS_HTP_LAMARAN' FROM DUAL
        UNION ALL
        SELECT 'HRS_REKAPAN_KEPUTUSAN_HR', 'FK_HRS_RKH_LAMARAN' FROM DUAL
    ) LOOP
        SELECT COUNT(*) INTO object_count
        FROM USER_CONSTRAINTS
        WHERE CONSTRAINT_NAME = item.constraint_name;
        IF object_count = 0 THEN
            EXECUTE IMMEDIATE
                'ALTER TABLE ' || item.table_name ||
                ' ADD CONSTRAINT ' || item.constraint_name ||
                ' FOREIGN KEY (LAMARAN_ID) REFERENCES HRS_LAMARAN (LAMARAN_ID)';
        ELSE
            SELECT COUNT(*) INTO object_count
            FROM USER_CONSTRAINTS c
            JOIN USER_CONS_COLUMNS cc
              ON cc.CONSTRAINT_NAME = c.CONSTRAINT_NAME
             AND cc.TABLE_NAME = c.TABLE_NAME
            JOIN USER_CONSTRAINTS parent
              ON parent.CONSTRAINT_NAME = c.R_CONSTRAINT_NAME
            JOIN USER_CONS_COLUMNS parent_column
              ON parent_column.CONSTRAINT_NAME = parent.CONSTRAINT_NAME
             AND parent_column.TABLE_NAME = parent.TABLE_NAME
            WHERE c.TABLE_NAME = item.table_name
              AND c.CONSTRAINT_NAME = item.constraint_name
              AND c.CONSTRAINT_TYPE = 'R'
              AND c.STATUS = 'ENABLED'
              AND c.VALIDATED = 'VALIDATED'
              AND cc.COLUMN_NAME = 'LAMARAN_ID'
              AND parent.TABLE_NAME = 'HRS_LAMARAN'
              AND parent_column.COLUMN_NAME = 'LAMARAN_ID';
            IF object_count <> 1 THEN
                RAISE_APPLICATION_ERROR(-20094, item.constraint_name || ' exists but is not a validated application foreign key.');
            END IF;
        END IF;
    END LOOP;

    FOR item IN (
        SELECT 'HRS_JADWAL_KANDIDAT' AS table_name, 'IX_HRS_JK_LAMARAN' AS index_name FROM DUAL
        UNION ALL
        SELECT 'HRS_HASIL_TES_PSIKOLOGI', 'IX_HRS_HTP_LAMARAN' FROM DUAL
        UNION ALL
        SELECT 'HRS_REKAPAN_KEPUTUSAN_HR', 'IX_HRS_RKH_LAMARAN' FROM DUAL
    ) LOOP
        SELECT COUNT(*) INTO object_count
        FROM USER_INDEXES
        WHERE INDEX_NAME = item.index_name;
        IF object_count = 0 THEN
            SELECT COUNT(*) INTO object_count
            FROM USER_IND_COLUMNS
            WHERE TABLE_NAME = item.table_name
              AND COLUMN_NAME = 'LAMARAN_ID'
              AND COLUMN_POSITION = 1;
            IF object_count = 0 THEN
                EXECUTE IMMEDIATE
                    'CREATE INDEX ' || item.index_name ||
                    ' ON ' || item.table_name || ' (LAMARAN_ID)';
            END IF;
        ELSE
            SELECT COUNT(*) INTO object_count
            FROM USER_IND_COLUMNS
            WHERE TABLE_NAME = item.table_name
              AND INDEX_NAME = item.index_name
              AND COLUMN_NAME = 'LAMARAN_ID'
              AND COLUMN_POSITION = 1;
            IF object_count <> 1 THEN
                RAISE_APPLICATION_ERROR(-20095, item.index_name || ' exists but does not index LAMARAN_ID.');
            END IF;
        END IF;
    END LOOP;

    EXECUTE IMMEDIATE q'[
        MERGE INTO HRS_REKAPAN_KEPUTUSAN_HR target
        USING (
            SELECT source.REKAPAN_KEPUTUSAN_ID, application_map.LAMARAN_ID
            FROM HRS_REKAPAN_KEPUTUSAN_HR source
            JOIN (
                SELECT p.KANDIDAT_ID,
                       UPPER(TRIM(NVL(d.NAMA_DEPARTEMEN, k.NAMA_DEPARTEMEN))) AS DEPARTMENT_NAME,
                       MIN(l.LAMARAN_ID) AS LAMARAN_ID
                FROM HRS_LAMARAN l
                JOIN HRS_PROFILE_KANDIDAT_LOGIN pkl
                  ON pkl.PROFILE_KANDIDAT_LOGIN = l.PROFILE_KANDIDAT_ID
                JOIN HRS_PROFIL_KANDIDAT p
                  ON p.KANDIDAT_ID = pkl.KANDIDAT_ID
                JOIN HRS_DETAIL_INFO_LOWONGAN d
                  ON d.DETAIL_LOWONGAN_ID = l.DETAIL_LOWONGAN_ID
                LEFT JOIN HRS_KOLOM_LANDING_PENDAFTARAN k
                  ON k.KOLOM_PENDAFTARAN_ID = d.KOLOM_PENDAFTARAN_ID
                  OR k.KOLOM_PENDAFTARAN_ID = l.DETAIL_LOWONGAN_ID
                GROUP BY p.KANDIDAT_ID,
                         UPPER(TRIM(NVL(d.NAMA_DEPARTEMEN, k.NAMA_DEPARTEMEN)))
                HAVING COUNT(DISTINCT l.LAMARAN_ID) = 1
            ) application_map
              ON application_map.KANDIDAT_ID = source.KANDIDAT_ID
             AND application_map.DEPARTMENT_NAME = UPPER(TRIM(source.NAMA_DEPARTEMENT))
            WHERE source.LAMARAN_ID IS NULL
              AND source.KANDIDAT_ID IS NOT NULL
        ) mapped
          ON (target.REKAPAN_KEPUTUSAN_ID = mapped.REKAPAN_KEPUTUSAN_ID)
        WHEN MATCHED THEN
            UPDATE SET target.LAMARAN_ID = mapped.LAMARAN_ID
    ]';
END;
/
