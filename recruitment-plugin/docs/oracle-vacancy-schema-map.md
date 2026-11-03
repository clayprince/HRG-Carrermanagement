# Oracle vacancy and application schema map

Read-only metadata inspection of the configured Oracle schema on 2026-11-01.
No application rows, schema objects, or data were changed. This is an initial
map, not approval to implement writes or run DDL. Business rules and any
relationships not listed as database constraints still need confirmation.

## Confirmed workflow tables and columns

### User Department request / FPTK

`REQUEST_LOWONGAN` has 30 columns. The relevant request fields include:

- Identity and ownership: `REQUEST_ID` (not nullable), `DEPARTEMEN_ID`
  (not nullable), `NAMA_DEPARTEMEN`, `HR_ID`.
- Request tracking: `NO_FPTK`, `REQUEST_AT`.
- Position: `POSITION_IN_NEED`, `LEVEL_JABATAN`, `KATEGORI_KI`,
  `HIRING_USER_NAME`, `JUMLAH_KEBUTUHAN`, `STATUS_KARYAWAN`, `PRIORITAS`,
  `TARGET_PEMENUHAN`, `LOKASI_PENEMPATAN`.
- Requirement details: `ALASAN_KEBUTUHAN`, `TUJUAN_UTAMA_POSISI`,
  `TANGGUNG_JAWAB_UTAMA`, `OUTPUT_HARAPAN`, `DAMPAK_KEKOSONGAN`,
  `PENDIDIKAN_MINIMUM`, `PENGALAMAN_MINIMUM`, `SERTIFIKASI_WAJIB`,
  `HARDSKILL_WAJIB`, `SOFTSKILL_WAJIB`, `KRITERIA_TAMBAHAN`,
  `JURUSAN_PRIORITAS`, `BATAS_USIA`, `KEBUTUHAN_DINAS`, and
  `PERSYARATAN_KHUSUS_LAINNYA`.

Most request fields are nullable. Confirm which are mandatory in the business
process before deciding form validation rules.

`STATUS_REQUEST_LOWONGAN` has `STATUS_REQUEST_ID`, `REQUEST_ID`,
`POSISI_IN_NEED`, `JUMLAH_KEBUTUHAN`, `PRIORITAS`, `TANGGAL_PENGAJUAN`,
`STATUS_PENGAJUAN`, `CREATED_AT`, and `UPDATED_AT`. The request ID and several
status-summary fields are not nullable. Whether this table is a status history,
a current-state summary, or a separately maintained projection is not yet
verified. It has a foreign key from `REQUEST_ID` to `REQUEST_LOWONGAN`, and
check constraints for `JUMLAH_KEBUTUHAN`, `PRIORITAS`, and `STATUS_PENGAJUAN`.
The confirmed rules are `JUMLAH_KEBUTUHAN > 0`,
`PRIORITAS IN ('RENDAH', 'SEDANG', 'TINGGI')`, and
`STATUS_PENGAJUAN IN ('DIAJUKAN', 'DIPROSES', 'DISETUJUI', 'DITOLAK')`.

`STATUS_REQUEST_USER` contains `STATUS_LOWONGAN_ID`, `STATUS_KANDIDAT_ID`,
`REQUEST_ID`, `STATUS_PENGAJUAN`, `CATATAN_REVIEW`, `TANGGAL_DIBUAT`, and
`HR_ID`. Its foreign keys reference the candidate-status, request, and HR
primary keys, respectively. The meaning and lifecycle of this record still
need confirmation.

### HR vacancy detail

`DETAIL_INFO_LOWONGAN` exposes 19 columns, including:
`DETAIL_LOWONGAN_ID`, `REQUEST_LOWONGAN_ID`, `KOLOM_PENDAFTARAN_ID`,
`JUDUL_PEKERJAAN`, `DESKRIPSI_PEKERJAAN`, `TANGGUNG_JAWAB`, `NO_FPTK`,
`REQUEST_AT`, `DEPARTEMEN_ID`, `NAMA_DEPARTEMEN`, `LEVEL_JABATAN`,
`KATEGORI_KJ`, `JUMLAH_KEBUTUHAN`, `PRIORITAS`, `BATAS_LAMARAN`,
`LOKASI_PENEMPATAN`, `PENDIDIKAN_MINIMUM`, `PENGALAMAN_MINIMUM`, and
`SERTIFIKASI_WAJIB`.

`PERSYARATAN_LOWONGAN` exposes `PERSYARATAN_LOWONGAN_ID`,
`JUDUL_PEKERJAAN`, `NAMA_PERSYARATAN`, `JENIS_PERSYARATAN`, timestamps,
`KATEGORI_PERSYARATAN`, `JENIS_PERSYARATAN_2`, and `HR_ID`. The exact
relationship from requirements to a vacancy is not evident from the inspected
column names or constraints. The only inspected foreign key is `HR_ID` to
`HR_USER`; confirm how requirements are linked to a vacancy before implementing
requirement CRUD.

The verified key relationships are:

- `REQUEST_LOWONGAN`: primary key `REQUEST_ID`; foreign keys `DEPARTEMEN_ID`
  to `DEPARTEMEN` and `HR_ID` to `HR_USER`.
- `STATUS_REQUEST_LOWONGAN`: primary key `STATUS_REQUEST_ID`; foreign key
  `REQUEST_ID` to `REQUEST_LOWONGAN`.
- `DETAIL_INFO_LOWONGAN`: primary key `DETAIL_LOWONGAN_ID`; foreign keys
  `REQUEST_LOWONGAN_ID` to `REQUEST_LOWONGAN`, `KOLOM_PENDAFTARAN_ID` to
  `KOLOM_LANDING_PENDAFTARAN`, and `DEPARTEMEN_ID` to `DEPARTEMEN`.

The `NO_FPTK` columns differ in maximum length: 14 characters in
`REQUEST_LOWONGAN` and 15 in `DETAIL_INFO_LOWONGAN`. Confirm the canonical FPTK
format, generator, and uniqueness rules before creating IDs or copying them
between tables.

### Applicant and application-related records

`PROFIL_KANDIDAT` includes `KANDIDAT_ID`, candidate identity/contact fields,
`POSISI_DILAMAR`, `DEALER`, `TANGGAL_LAMARAN`, education and selection fields,
and several `BLOB`/`CLOB` document or address columns.

`STATUS_PELAMAR` includes `KANDIDAT_ID`, `JUDUL_PEKERJAAN`, `STATUS_ID`,
`TRACKING_CODE`, `SUMBER_LAMARAN`, `CV_RESUME`, and `UPDATED_AT`. Confirm
whether one row represents an application, a status snapshot, or tracking
history before modeling application CRUD. Its primary key is
`STATUS_PELAMAR_ID`, with a foreign key from `KANDIDAT_ID` to
`PROFIL_KANDIDAT`.

`PROFIL_KANDIDAT` has primary key `KANDIDAT_ID`, plus foreign keys to
`PENGALAMAN_KERJA` and `STATUS_KANDIDAT`. The inspected metadata did not show a
direct FK from `STATUS_PELAMAR` to a vacancy.

The inspected schema's table list did not include `AKUN_PELAMAR`. The
applicant-account migration file therefore remains pending and is not part of
this schema snapshot.

### Form configuration

The form-related metadata is:

- `BAGIAN_PENDAFTARAN`: `BAGIAN_PENDAFTARAN_ID`, `NAMA_SECTION`, `DESKRIPSI`,
  `DISPLAY_ORDER`, `CREATED_AT`, `UPDATE_AT`, and `HR_ID`. Primary key:
  `BAGIAN_PENDAFTARAN_ID`; `HR_ID` references `HR_USER`.
- `KOLOM_LANDING_PENDAFTARAN`: `KOLOM_PENDAFTARAN_ID`, `JUDUL_PEKERJAAN`,
  `DEPARTEMEN_ID`, `NAMA_DEPARTEMEN`, `NAMA_DEALER`, `KATEGORI_KJ`,
  `DESKRIPSI_PEKERJAAN`, `PERSYARATAN_UTAMA`, `BATAS_LAMARAN`,
  `DIPOSTING_PADA`, `STATUS_KOLOM`, and `URUTAN_TAMPIL`. Primary key:
  `KOLOM_PENDAFTARAN_ID`; `DEPARTEMEN_ID` references `DEPARTEMEN`.
- `PILIHAN_FIELDS_PENDAFTARAN`: `PILIHAN_PENDAFTARAN_ID`, `FIELD_ID`,
  `NILAI`, `LABEL`, `URUTAN_TAMPILAN`, and `BAGIAN_PENDAFTARAN_ID`. Primary
  key: `PILIHAN_PENDAFTARAN_ID`; `BAGIAN_PENDAFTARAN_ID` references
  `BAGIAN_PENDAFTARAN`. No FK was found for `FIELD_ID`.

`DETAIL_INFO_LOWONGAN.KOLOM_PENDAFTARAN_ID` also references
`KOLOM_LANDING_PENDAFTARAN`. These constraints do not establish the business
meaning, publication lifecycle, or whether form choices are attached to a
specific configurable question; confirm those before implementing writes.

The inspected 43-table schema inventory contains no generic question/field
definition table. `PILIHAN_FIELDS_PENDAFTARAN.FIELD_ID` has no FK constraint,
and none of the inspected Oracle columns stores the builder's question title,
answer type, or required flag. Migration
[`../database/migrations/003_dynamic_application_forms.sql`](../database/migrations/003_dynamic_application_forms.sql)
therefore drafts new `RECRUIT_FORM_*` tables for definitions, submissions,
answers, and BLOB uploads, while extending `BAGIAN_PENDAFTARAN` for active
sections. The migration was applied to the Oracle schema connected to this
workspace on 2026-11-02 with explicit authorization; it is not automatically
applied to other environments.

The HR builder and public application page now use those Oracle-backed
definitions and submission tables. The prior WordPress option is shown only
as a labeled initial draft when Oracle has no active questions. In environments
where migration 003 is not applied, those flows remain unavailable. A
read-only post-migration check confirmed the application-form query succeeds,
and the builder defaults were initialized in Oracle (5 sections, 22 questions)
because the legacy WordPress option was absent. The local site has no published
`daw_vacancy` post, so public submissions cannot yet be tested. The application
route requires a published post and rejects the demo vacancy fallback.

## Known department/account metadata

- `DEPARTEMEN`: `DEPARTEMEN_ID`, `NAMA_DEPARTEMEN`, `STATUS_DEPARTEMEN`.
- `USERS_AD`: `USERDEPARTEMEN_ID`, `DEPARTEMEN_ID`, `NAMA_DEPARTEMEN`,
  `USERNAME`, `EMAIL`, `KATA_SANDI`.
- Read-only account metadata inspected on 2026-11-03 confirmed `HR_USER` has
  `HR_ID`, `NAMA_PENGGUNA`, and `PASSWORD`, while `USERS_AD` has the columns
  above; neither account table has an account status column. `DEPARTEMEN` has
  `STATUS_DEPARTEMEN`, which is not an account status. Draft migration
  `database/migrations/004_portal_account_status.sql` adds `STATUS_AKUN` to
  both account tables, with existing accounts defaulted to `ACTIVE`. It has
  not been applied.

`STATUS_REQUEST_USER` contains `STATUS_LOWONGAN_ID`, `STATUS_KANDIDAT_ID`,
`REQUEST_ID`, `STATUS_PENGAJUAN`, `CATATAN_REVIEW`, `TANGGAL_DIBUAT`, and
`HR_ID`.

## Gaps before CRUD

Identity columns using `BY DEFAULT` were confirmed for `REQUEST_LOWONGAN`,
`STATUS_REQUEST_LOWONGAN`, `STATUS_REQUEST_USER`, `DETAIL_INFO_LOWONGAN`,
`PERSYARATAN_LOWONGAN`, `PROFIL_KANDIDAT`, `STATUS_PELAMAR`,
`BAGIAN_PENDAFTARAN`, `KOLOM_LANDING_PENDAFTARAN`, and
`PILIHAN_FIELDS_PENDAFTARAN`. No triggers were found on these inspected
workflow tables. Do not manually assign IDs without confirming the target
table's generation behavior.

The inspected constraints confirm the key relationships listed above, but do
not verify indexes or all business rules. Some broader metadata queries did not
complete; all unlisted relationships remain unverified.

Before writing CRUD:

1. Confirm request/FPTK lifecycle, permitted status transitions, and which
   actor creates or changes each record.
2. Confirm whether `DETAIL_INFO_LOWONGAN` is an HR-authored published vacancy,
   an approved request projection, or both.
3. Agree whether to add an Oracle question-definition table, keep dynamic
   question configuration in WordPress, or limit Oracle persistence to the
   section/choice fields represented by its existing tables. Also define how
   question answers map to candidate/profile/application storage.
4. Reconcile existing `DAW_*` gateway SQL with these Oracle tables before
   enabling production vacancy or application flows.

No sample applicant, candidate, or vacancy rows were read for this mapping.
