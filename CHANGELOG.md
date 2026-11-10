# Changelog

This file records material, verified project changes and guides future human
and AI contributors. It is not a substitute for source review or a database
migration log.

## 2026-11-10

### Psychotest camera and microphone checks

- Made the device check explain when an insecure HTTP origin blocks camera
  access, and reset readiness if the camera disconnects after permission.
- Added an explicit microphone availability check. Only the permission/device
  status is reported to HR; no audio is recorded or stored.
- Kept separate camera, tab, and microphone event reporting from suppressing
  one another during the same short interval.
- Runtime camera validation still requires the local site to be served through
  HTTPS or localhost.

### Oracle department schema

- Documented that `HRS_DETAIL_INFO_LOWONGAN` uses `DEPARTEMENT_ID` and
  `NAMA_DEPARTEMENT`, distinct from `HRS_DEPARTEMEN` and the landing table's
  `DEPARTEMEN` spelling. Application tracking and listing queries use the
  verified detail-table name.

### Public vacancy listing and application submission

- Corrected public vacancy queries and inserts to use the verified Oracle
  detail-table department columns, restoring Oracle-published vacancies to the
  careers page.
- Decoded synthetic WordPress vacancy IDs before resolving the post and
  department during application submission.
- Made partial Oracle listing failures visible while retaining available
  WordPress vacancy cards as a fallback.

## 2026-11-09

### Psychotest proctoring foundation

- Added explicit consent, private selfie/KTP capture, a manual identity-review
  path, and camera-only monitoring for tab changes and camera disconnects.
- Added a five-alert-image-per-session limit, Administrator-only evidence
  downloads with access audit, and scheduled one-year retention cleanup.
- Added Oracle migration 015 for review; it has not been applied. Face-count,
  gaze, and phone/device detection remain out of scope for this phase.

### Recruitment data flow to admin and User Department

- User Department applicant and interview details now read the scoped Oracle
  application, profile, form-answer, and selection-history data instead of
  showing hardcoded candidate examples.
- HR status updates return to the same application after saving, including
  when a candidate has multiple applications.
- No database migration was introduced. Private applicant uploads are not
  exposed as download links in User Department.

## 2026-11-08

### Pauli legacy session handling

- Fixed expansion of legacy Pauli columns so the updated number sequence and
  answer payload are persisted for sessions that have not expired.
- Expired legacy sessions are kept unchanged for HR review rather than
  auto-saving the remaining lanes as blank and completing the assessment.

### Admin HR login

- Re-enabled the Oracle-backed Admin HR login and password-reset entry point
  after confirming the connected schema has the required account fields.
- HR can sign in with either the account email or username; account passwords
  remain verified as hashes in Oracle.

### Personality assessment stage

- Grouped DISC and PAPI Kostick under one applicant-facing Personality stage;
  the overall sequence and progress now contain three stages: IQ, Pauli, and
  Personality.
- Aligned new personality sessions to the supplied PDFs: 24 DISC groups of
  four statements with distinct most/least selections, followed by 90 required
  PAPI pairs with one selection each. Sessions use the Oracle display order.
- Kept DISC and PAPI result records separate in Oracle while grouping their
  presentation in applicant progress and HR result headings.

### Pauli online assessment

- Replaced the Pauli worksheet with a one-sum-at-a-time interface, keyboard
  and on-screen keypad input, a one-hour timer, and 20 three-minute lanes.
- Expanded each lane to 900 answers while retaining saved responses when an
  in-progress legacy session is upgraded. Oracle stores the source numbers,
  submitted digits, and correct-answer score.
- HR settings now show the fixed Pauli duration and lane count. The applicant
  portal requires a 20-question Pauli bank before unlocking the assessment.
- Verified that an HR-created synthetic Pauli question appears in the
  Oracle-backed assessment catalog, then removed the fixture. No participant
  assessment session was submitted or modified.

### Psychotest question PDF import

- Added an HR-only PDF extraction preview for IQ, DISC, and PAPI Kostick. IQ
  imports support labeled A–F choices and answer keys for one selected IQ
  subtest per PDF; HR can review and edit extracted text before saving.
  Extraction alone never writes to the question bank.
- Added automatic grouping from sequential extracted statement lines when a
  PDF does not preserve blank lines. New personality questions are included in
  future sessions while retaining the minimum 24 DISC / 90 PAPI question bank.
- Save reviewed IQ questions and their answer choices together in one Oracle
  transaction; map answer keys to the stored correct choices.
- Added PDF type, size, page-count, text-layer, and participant-report checks.
  Scanned PDFs remain unsupported, and likely participant result reports are
  rejected without displaying their extracted text.
- Validated four-statement DISC groups and two-statement PAPI groups, then save
  accepted groups through one Oracle transaction. A synthetic PDF was
  extracted, grouped into two DISC questions, saved to Oracle, and verified in
  HR and applicant catalogs; the PDF and question fixtures were then removed.
  IQ, Pauli, and DISC question visibility was also verified with temporary
  records, then cleaned up.

### Interview and final-decision synchronization

- HR status changes to a final outcome now use the same Oracle transaction as
  the Final Decision screen, updating the HR decision record, application
  status, and applicant timeline together.
- Final outcomes require the candidate to have reached department interview
  and have a saved User Department result. Department interview results can be
  recorded from the HR-advanced candidate list without a separate Oracle
  schedule row.
- Kept the next-step applicant tracking note within the Oracle history
  column's 100-character limit.
- Verified the HR-to-department interview, saved department result, final HR
  decision, and applicant tracking flow against the local disposable Oracle
  database using synthetic accounts and a vacancy. The decision appeared in
  HR, department, and tracking views; all fixture records were removed.

## 2026-11-06

### Applicant registration and login

- Fixed direct-plugin account pages to bootstrap the local WordPress runtime,
  submit to the standalone front controller, and invoke the applicant
  registration/login handler instead of silently re-rendering the form.
- Applied migration `010_applicant_profile_details.sql` to the connected
  Oracle schema with explicit user approval. The details table is present.
- Fixed Oracle CLOB writes by binding bounded UTF-8 string chunks, preserving
  extended profile data larger than 4 KB. Missing-table registration failures
  now produce a clear setup error instead of a generic failure.
- Verified browser signup with synthetic identity and document uploads,
  automatic session creation, a subsequent browser login, and large Unicode
  profile save/read/update. Synthetic accounts, linked profile data, and
  uploaded files were removed after testing.

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
