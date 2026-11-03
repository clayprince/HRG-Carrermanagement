# Changelog

This file records material, verified project changes and guides future human
and AI contributors. It is not a substitute for source review or a database
migration log.

## 2026-11-03

### Portal login and account management

- Portal login now requires an Oracle `STATUS_AKUN` of `ACTIVE` for HR and User
  Department accounts. Department account creation, listing, and status changes
  use the Oracle gateway; deactivation also revokes mapped WordPress bridge
  sessions. Passwords remain hashed and are not exposed to the UI.
- Added an Admin HR password-change form that verifies the current Oracle
  password and updates `HR_USER.PASSWORD` with a PHP password hash. Other
  WordPress sessions for that HR bridge are revoked after a successful change.
- Added migration `004_portal_account_status.sql` as an unapplied draft. It
  adds status columns and constraints to `HR_USER` and `USERS_AD`, defaulting
  existing accounts to active. Applying it requires separate Oracle-owner
  approval; the updated portal login/status flow requires the migration.
- Forgot-password/reset is not implemented: the existing settings screen says
  email settings are unavailable, and no HR reset-token or email flow exists.
  Configure and verify an email delivery/reset design before implementing it.
- The Oracle metadata audit read column definitions only; no account rows or
  credentials were read, and no database schema or data was changed.
- Unauthenticated direct requests to the HR profile and account-management
  admin pages redirected to the HR login in the local WordPress site.
- PHP/Oracle integration and real login/account/password scenarios remain
  untested because the PHP CLI has no PDO_OCI driver. SQL*Plus connectivity and
  the read-only account-column metadata query succeeded.

## 2026-11-02

### Oracle-backed form verification

- Verified the configured Oracle connection with read-only `SELECT 1 FROM DUAL`.
- Before migration, confirmed `BAGIAN_PENDAFTARAN` was present and the four
  `RECRUIT_FORM_*` tables were absent.
- With explicit user authorization, applied migration 003 to the connected
  Oracle schema. All five migration blocks completed; the four new tables and
  extended section table were confirmed, and the application-form read query
  succeeded.
- The legacy WordPress form option was absent, so initialized the Oracle form
  from the builder defaults: 5 sections and 22 questions. The saved
  configuration was read back successfully.
- No application or applicant rows were created. The local WordPress site has
  no published `daw_vacancy` posts, so live submission persistence remains
  untested until a real vacancy is published.

### Interview schedule design refinement

- Updated the HR interview schedule to follow the supplied two-stage reference:
  multi-date calendar selection followed by per-slot time, online/offline
  method, meeting details or location, and capacity configuration.
- Added expandable saved-schedule summaries and responsive calendar/configuration
  panels. Schedule changes remain browser-preview-only and are not persisted.

## 2026-11-01

### Read-only Oracle schema mapping

- Confirmed table and column metadata for `REQUEST_LOWONGAN`,
  `STATUS_REQUEST_LOWONGAN`, `DETAIL_INFO_LOWONGAN`,
  `PERSYARATAN_LOWONGAN`, `PROFIL_KANDIDAT`, `STATUS_PELAMAR`,
  `STATUS_REQUEST_USER`, and all three form-configuration tables.
- Verified PK/FK relationships for the main request, vacancy-detail, candidate,
  status, and form-configuration tables. `BY DEFAULT` identity generation and
  absence of triggers are confirmed for those workflow tables.
- Verified request status checks: positive headcount, priorities `RENDAH`,
  `SEDANG`, `TINGGI`, and statuses `DIAJUKAN`, `DIPROSES`, `DISETUJUI`,
  `DITOLAK`.
- The inspected Oracle schema has no generic question-definition table;
  `FIELD_ID` in `PILIHAN_FIELDS_PENDAFTARAN` has no FK. Full Oracle-backed
  builder persistence therefore uses the authored extension in
  `recruitment-plugin/database/migrations/003_dynamic_application_forms.sql`.
- Recorded findings and required follow-up checks in
  [`recruitment-plugin/docs/oracle-vacancy-schema-map.md`](recruitment-plugin/docs/oracle-vacancy-schema-map.md).
- Inspection was read-only; no Oracle rows or schema objects were modified.

### Implemented

- Replaced the application-form builder's WordPress-option writes with Oracle
  section, field, and option persistence. The previous WordPress form remains
  available only as a labeled draft for initial migration.
- Added public rendering, server-side validation, consent checks, and atomic
  answer/file submission for the active Oracle form. File uploads accept PDF,
  JPEG, and PNG up to 5 MB and are stored as Oracle BLOBs.
- Added migration `003_dynamic_application_forms.sql` for form definitions,
  submission snapshots, answers, and file BLOBs. It was applied to the
  connected Oracle schema on 2026-11-02 with explicit authorization; it is not
  automatically applied to other environments.
- Applicant, HR, and User Department portal authentication uses Oracle account
  records, with restricted WordPress role/capability sessions.
- Added the applicant account migration file for `AKUN_PELAMAR`; the migration
  has not been applied to Oracle.
- Added a User Department portal shell and prototype screens for Dashboard,
  Request Lowongan, Kandidat Wawancara User, Data Pelamar, and Hasil Wawancara
  User.
- Added Screen Explorer links for portal screens and the broader public,
  applicant, psychotest, HR, and User Department groups.

### Known limitations

- User Department candidate data and dashboard summaries include prototype
  records. Department vacancy requests and interview ratings currently persist
  in WordPress user meta, not Oracle.
- Vacancy publication still uses WordPress `daw_vacancy` posts; the public
  application route rejects demo fallback jobs. Other legacy vacancy and
  application gateway methods still query `DAW_*` tables and have not been
  reconciled with the current Oracle schema.
- `migrations/001_application_token.sql` targets legacy `DAW_APPLICATIONS`;
  do not apply it to a different schema without owner review.
- Oracle schema migrations are not run automatically. `002_applicant_accounts.sql`
  needs separate review/application by the authorized Oracle owner.

### Next planned milestone — vacancy posting and intake

1. Configure an active form through the HR builder and test a submission
   against a published vacancy without using demo data.
2. HR vacancy creation and User Department vacancy-request forms.
3. HR vacancy list and User Department request list.
4. HR application handling and FPTK workflows.

Before starting CRUD, confirm the current Oracle schema mapping, required
fields/statuses, record ownership, and authorization rules for each role.
Removing unrelated prototype data and replacing other persistence paths remain
future work; they were not part of this application-form implementation.

## Changelog maintenance for contributors

- Add a dated section for each material code, schema, security, or workflow
  change. Keep entries concise and factual.
- Separate **Implemented**, **Known limitations**, and **Planned** work. Never
  describe a roadmap item as completed.
- When status changes, update the relevant old limitation or plan and add the
  new verified state; do not silently rewrite history.
- Record migrations by filename and whether they are merely authored, approved,
  or applied. Never claim a migration ran unless that was verified.
- Mention affected areas (public, HR, User Department, WordPress, Oracle) and
  relevant docs or tests when useful.
- Do not include credentials, personal data, or sensitive database output.
- Update the relevant README when architecture or setup guidance changes, and
  keep the root README, focused database guide, and this log consistent.
- Treat screenshots, mock data, and UI prototypes as design references, not
  proof of production data behavior.
