# Oracle ERD alignment

This map describes the project reference at
[`../../example&guide/updated.sql`](../../example&guide/updated.sql). It is not
confirmation that the external Oracle database has been migrated or matches
that file. Verify the target schema with its owner before relying on live
queries. Do not execute DDL or migrations without explicit authorization.

## Vacancies

The public and admin vacancy flows use these `HRS_*` tables:

- `HRS_REQUEST_LOWONGAN` stores the basic request and position metadata.
- `HRS_KOLOM_LANDING_PENDAFTARAN` stores the dealer, job category, summary,
  application deadline, display order, and publication flag.
- `HRS_DETAIL_INFO_LOWONGAN` links the request and landing records and stores
  the public vacancy details and requirements.

`HRS_DETAIL_INFO_LOWONGAN.KOLOM_PENDAFTARAN_ID` references the landing record;
`REQUEST_LOWONGAN_ID` references the request. The vacancy gateway creates
those three records in one transaction. `STATUS_KOLOM` is mapped as 0 =
Draft and 1 = Published. A vacancy's Oracle `DETAIL_LOWONGAN_ID` is the ID
used by public detail and application pages.

The form maps job category to `KATEGORI_KJ` and the selected placement region
to `LOKASI_PENEMPATAN`. The landing table's `NAMA_DEALER` is limited to 100
characters; `PERSYARATAN_UTAMA` is limited to 100. Verify the actual schema's
column limits if it differs from the reference file.

## Fixed application form

The builder displays the fixed form defined by `Recruitment_Application`; it
does not edit Oracle form definitions. Submission writes related data in one
transaction:

- `HRS_PROFIL_KANDIDAT` for candidate identity, contact, and photo.
- `HRS_PENDIDIKAN`, `HRS_PENGALAMAN_KERJA`, `HRS_KELUARGA`,
  `HRS_MINAT_DAN_KONSEP_PRIBADI`, and `HRS_PDP` for the submitted details and
  consent selections.
- `HRS_PROFILE_KANDIDAT_LOGIN` for the profile ID referenced by
  `HRS_LAMARAN`.
- `HRS_LAMARAN` and `HRS_RIWAYAT_STATUS_PELAMAR` for the application and its
  initial status.

`HRS_PROFIL_KANDIDAT.PENGALAMAN_KERJA_ID` and
`HRS_PENGALAMAN_KERJA.KANDIDAT_ID` form a cyclic relationship in the ERD. The
writer inserts the candidate first, inserts work experience when provided,
then updates the candidate's experience reference in the same transaction.
The photo is stored in the candidate BLOB column. The form does not currently
upload a CV or other documents.

The fixed form enforces the relevant `VARCHAR2` limits and numeric formats
before persistence. Email is limited to the ERD's 50-character profile/login
column.

## Accounts and other limitations

Applicant and portal login, account registration, password reset, and account
management are disabled. `HRS_PROFILE_KANDIDAT_LOGIN` has no password column;
`HRS_USERS_AD.KATA_SANDI` and `HRS_ROLE_AKSES.KATA_SANDI` are only 50
characters and cannot safely store PHP password hashes. The ERD does not
provide an account-status column. Do not enable these flows or apply draft
account migrations as a substitute for an approved schema change.

Some department-request and interview screens remain prototype features backed
by WordPress user metadata. They are not Oracle recruitment records. Legacy
`DAW_*` gateway methods are also not part of the vacancy or fixed application
flows and must be reconciled separately before use.

Migration 003's `RECRUIT_FORM_*` tables are not required for the fixed form.
Do not run it to resolve form submission against this ERD.
