# Database and persistence

The plugin uses separate WordPress and Oracle stores. WordPress APIs own
WordPress users, roles, capabilities, options, and session bridges. Oracle
stores recruitment data only where the feature is explicitly integrated with
the Oracle gateway. Do not treat the databases as interchangeable.

## Oracle access

- `connection/class-oracle-connection.php` owns configuration and the shared,
  lazy PDO_OCI connection.
- `class-database.php` is the Oracle query gateway. Add Oracle CRUD there and
  use existing service/caller patterns.
- Pages and components must not open PDO connections, embed SQL, or silently
  substitute files, sessions, JSON, or `$wpdb` for an Oracle operation.
- Use prepared statements, bound parameters, and transactions for related
  multi-record writes. Return and report errors using existing gateway
  conventions.
- The Oracle gateway converts CLOB values while fetching each row. Keep this
  row-by-row conversion: PDO_OCI may reuse LOB locators when results are
  fetched in bulk, causing distinct question text to appear identical.

The connection reads settings in this order: PHP constants, process
environment variables, the file pointed to by `RECRUITMENT_DOTENV_PATH`, then
the WordPress `recruitment_oracle_config` option. Keep credentials outside the
repository and never print, log, commit, or share their values. Setup and
read-only connection testing are documented in
[`../docs/oracle-env-setup.md`](../docs/oracle-env-setup.md). The ERD-based
vacancy/application alignment note is in
[`../docs/oracle-vacancy-schema-map.md`](../docs/oracle-vacancy-schema-map.md);
it is not a live-schema verification.

## Account persistence

- Applicants authenticate against `HRS_PROFILE_KANDIDAT_LOGIN`, linked to
  their personal data in `HRS_PROFIL_KANDIDAT`. Migration
  [`migrations/009_candidate_account_auth.sql`](migrations/009_candidate_account_auth.sql)
  adds the password and account status fields. With explicit user approval, it
  was applied to the Oracle schema connected to this workspace on 2026-11-08.
  It is not applied automatically in other environments.
- Admin HR and Kepala Departemen authenticate against `HRS_ROLE_AKSES`.
  `PERAN` must be `ADMIN_HR` or `KEPALA_DEPARTEMEN`; email and username are
  stored in the same account row.
- The first HR account can be created from the WordPress administrator-only
  setup form under Recruitment > Pengelolaan Akun User. The entered password
  is hashed before it is written to `HRS_ROLE_AKSES`; no default credential is
  committed to the repository.
- Department accounts use `DEPARTEMEN_ID` and `NAMA_DEPARTEMEN` in the same
  `HRS_ROLE_AKSES` row.
- New account passwords use PHP `password_hash()` and are checked with
  `password_verify()`. WordPress users are restricted session/capability
  bridges, not copies of Oracle passwords.
- Applicant signup and edit-profile store extended education, work history,
  family, interests, and private document metadata in
  `HRS_PROFIL_KANDIDAT_DETAIL`. With explicit user approval, migration
  [`migrations/010_applicant_profile_details.sql`](migrations/010_applicant_profile_details.sql)
  was applied to the Oracle schema connected to this workspace on 2026-11-08.
  It is not applied automatically in other environments.
  Registration rolls back the account transaction when this table is
  unavailable, so no incomplete account is left behind.
- Applicant photos, resumes, and portfolios are stored under the WordPress
  uploads directory in `daw-applicant-private`, with access limited to the
  owning authenticated applicant through the plugin. The directory uses
  Apache deny rules; deployments using Nginx or another server must also deny
  direct web access to this directory.
- Migration `005_unified_portal_accounts.sql` adds `PERAN`, `STATUS_AKUN`, and
  sufficient password/email widths to `HRS_ROLE_AKSES`, and classifies existing
  rows by whether they have a department.
- Account listing, status changes, and HR password changes use
  `HRS_ROLE_AKSES` through `Recruitment_Database`. No password hashes are
  returned to screens.

## Schema alignment and migrations

The current Oracle schema uses tables such as `PROFIL_KANDIDAT`,
`DETAIL_INFO_LOWONGAN`, `HRS_ROLE_AKSES`, `HRS_USERS_AD`, and `HRS_DEPARTEMEN`. Some existing
vacancy/application methods in `class-database.php` still query legacy
`DAW_*` tables and have not been reconciled with that schema. Inspect the live
schema and each caller before building CRUD on those methods; do not assume
their SQL is compatible with the current Oracle database.
- Admin HR and Kepala Departemen portal accounts use `HRS_ROLE_AKSES`.
  Passwords are stored as PHP hashes and verified with `password_verify()`;
  WordPress users serve only as session/capability bridges.
- The supplied base ERD defines `KATA_SANDI` and `EMAIL` as `VARCHAR2(50
  CHAR)` and does not define portal role or status columns. Portal account
  login and management therefore require the schema changes in
  `migrations/005_unified_portal_accounts.sql`. Department profile fields and
  feature permissions require `migrations/007_department_account_management.sql`.
- Migration `006_safe_account_status_migration.sql` is an alternative,
  guarded status-column migration for owner review. Do not apply it together
  with migration 005 without first reconciling their overlapping changes.
- The account UI does not expose password hashes. Review and approve the
  required migrations against the target Oracle schema before execution; the
  plugin does not run them automatically.
- Legacy applicant-account methods that query `AKUN_PELAMAR` are not used by
  the current Oracle applicant portal. Applicant login and registration use
  `HRS_PROFILE_KANDIDAT_LOGIN` and require migration
  `009_candidate_account_auth.sql`.

## Psychometric assessment linking

- Assessment sessions are associated with the authenticated Oracle candidate
  profile ID. Progress is kept in a non-autoloaded WordPress option scoped to
  that profile and assessment; answers and completed result records are
  written to Oracle through `Recruitment_Database`.
- Migration `012_psychotest_candidate_results.sql` adds the candidate and
  completed-result links used by assessment writes and result review. It must
  be reviewed and applied by the authorized Oracle owner; the plugin does not
  run it automatically.
- HR can add and edit IQ, Pauli, DISC, and PAPI questions. IQ remains divided
  into four subtests with the existing fixed time limits; additional questions
  use those same limits. Pauli presents one vertical sum at a time for 60
  minutes, with 20 timed lanes and an automatic lane change every three
  minutes. Each lane supports up to 900 answers; the applicant can use the
  on-screen keypad or keyboard, and Oracle stores the generated number sequence,
  submitted digits, and correct-answer score. The Pauli bank must contain at
  least 20 questions to provide the fixed one-hour session. The applicant
  psychotest flow has three stages: IQ, Pauli, and Personality. Personality is
  completed in order as two separately persisted subtests: DISC (24 groups of
  four statements, one "most" and one distinct "least" choice per group), then
  PAPI Kostick (90 pairs, one required choice per pair). These question counts
  and response shapes follow the supplied company reference PDFs. Questions
  retain Oracle display order, and neither personality subtest has a hard time
  limit; the displayed elapsed-time reminders do not end a session.
- Active assessment sessions keep their original question order when HR adds
  questions; new questions are included in sessions started afterward.
- Psychometric routes require an authenticated applicant portal session.

## Schema alignment and migrations

The updated ERD in [`../../example&guide/updated.sql`](../../example&guide/updated.sql)
uses the `HRS_*` table names. The public application form saves candidate
profile, education, work experience, family, personal-interest, PDP, login
profile, application, and status-history records in one Oracle transaction.
The admin form builder also stores its question configuration and additional
answers in the Oracle tables created by migration 003. Core questions remain
mapped to the `HRS_*` applicant columns; admins can edit their labels and
required state, but not remove them or change their answer types. Additional
questions are stored as answer snapshots in `RECRUIT_FORM_ANSWERS`.

The changelog records migration 003 as applied to the Oracle schema verified
on 2026-11-02. That does not prove it exists in another target schema. The
plugin does not run migrations automatically; verify the target schema before
using the form builder or applying any migration.

The current `HRS_*` ERD does not include the dynamic-form tables or the
section `IS_ACTIVE` column. Migration
`migrations/008_dynamic_application_forms_hrs.sql` adds those objects for that
schema. It was applied to the connected local Oracle schema on 2026-11-04 with
the database owner's approval. The migration is idempotent for the added
column and tables; this does not prove it exists in another target schema. It
does not update or delete applicant records.

Migration `migrations/009_hr_vacancy_form_details.sql` adds
`HRS_REQUEST_LOWONGAN.HR_FORM_DETAILS` as a CLOB for form values that do not
have dedicated columns in the supplied ERD. With the database owner's
approval, it was applied and verified on the connected local Oracle schema on
2026-11-04. This does not prove that the column exists in another target schema.

## Reconciliation of supplied Oracle queries

The supplied changes are represented by migrations in this directory. Match
them to the target schema before applying anything; the migration filename
prefixes are not unique, and the plugin does not execute these files.

- Dynamic application forms: use
  `migrations/008_dynamic_application_forms_hrs.sql` for a schema with
  `HRS_BAGIAN_PENDAFTARAN`. `migrations/003_dynamic_application_forms.sql`
  and `migrations/011_application_form_schema_compatibility.sql` instead use
  `BAGIAN_PENDAFTARAN` without the `HRS_` prefix; migration 011 only adds
  compatibility columns and checks for the tables created by migration 003.
  Do not apply both form variants to the same target without confirming the
  intended table relationships.
- HR vacancy-form details and extended applicant-profile data are separate
  changes: use `migrations/009_hr_vacancy_form_details.sql` for
  `HRS_REQUEST_LOWONGAN.HR_FORM_DETAILS` and
  `migrations/010_applicant_profile_details.sql` for
  `HRS_PROFIL_KANDIDAT_DETAIL`.
- Candidate login is handled by
  `migrations/009_candidate_account_auth.sql`, which targets
  `HRS_PROFILE_KANDIDAT_LOGIN`. It is not interchangeable with the legacy
  `migrations/002_applicant_accounts.sql`, which creates `AKUN_PELAMAR`.
- HR and department account changes are split across migrations:
  `migrations/005_unified_portal_accounts.sql` adds `PERAN`, account status,
  and the larger email/password columns. Use either
  `migrations/007_department_account_management.sql` or
  `migrations/008_full_account_management.sql` for department profile fields,
  feature permissions, and default departments—not both. Migration 008 also
  verifies/adds account status, but does not add `PERAN` or widen email and
  password columns; it does not replace migration 005.
- The applied-state notes above describe a previously connected local schema,
  not the current state of another Oracle target. Verify target metadata and
  data first, obtain the Oracle owner's approval, and account for Oracle DDL's
  implicit commits before applying any migration.

Public vacancy listings and the admin create/status controls use the Oracle
vacancy tables. `STATUS_KOLOM` maps 0 to Draft and 1 to Published. Do not treat
legacy `DAW_*` queries or WordPress vacancy posts as the active source for the
public application flow.

In the connected schema, `HRS_DETAIL_INFO_LOWONGAN` names the department
column `NAMA_DEPARTEMEN` and does not contain a location column; the linked
`HRS_KOLOM_LANDING_PENDAFTARAN.NAMA_DEALER` supplies the displayed location.
Keep this mapping distinct from `NAMA_DEPARTEMENT` on
`HRS_REKAPAN_KEPUTUSAN_HR`.

When HR approves a User Department request, the plugin creates and publishes
the Oracle vacancy immediately. HR supplies any missing location, Dealer/AHASS,
and application deadline. Oracle creates the request, landing entry, and
details in one transaction; the WordPress request meta is then marked
`Lowongan Dibuka` and linked to the Oracle vacancy ID. If the Oracle write
succeeds but the WordPress link does not, retrying uses the FPTK to find the
existing vacancy rather than creating a duplicate.

HR-created vacancies use a six-step form and are saved as Draft; publishing
them is a separate action from the vacancy list.

`migrations/001_application_token.sql` alters the legacy
`DAW_APPLICATIONS` table. Treat it as a legacy migration: do not run it against
the current Oracle schema unless the database owner confirms that the target
schema contains the expected table and approves the change. Migration
`002_applicant_accounts.sql` is a legacy draft and is not used by the current
applicant authentication flow.
Migration `004_portal_account_status.sql` is also only a reviewed draft and
must not be applied until the Oracle owner approves it.

## Application tracking tokens

The active HRS application flow creates a unique `APPLICATION_TOKEN` for each
new `HRS_LAMARAN` record and links each new dynamic-form snapshot to its
`LAMARAN_ID`. The confirmation page displays the token, and the authenticated
tracking page resolves it only among applications owned by the signed-in
applicant. HR answer review prefers this direct application link; its older
email-and-vacancy lookup remains as a compatibility fallback for existing
snapshots. Tracking also detects when the token column is not installed yet
and continues listing existing applications without a token rather than
failing the entire page.

The active token flow requires
`migrations/013_application_tracking_tokens.sql`. It backfills tokens for
existing HRS applications and adds the optional submission link. With explicit
approval, this migration was applied to the Oracle schema connected to this
workspace on 2026-11-08 and verified to leave no HRS applications without a
token. It is not applied automatically to other target schemas; review and
apply it there before accepting new tokenized submissions. Oracle DDL commits
implicitly. Before applying it elsewhere, older applications remain visible
but cannot display or be searched by token. Existing form snapshots remain
unlinked and are handled by the legacy lookup.

## Adding database functionality

1. Confirm the current Oracle table/column mapping and business rules with the
   schema owner.
2. Trace the page, route, request handler, service, and gateway caller.
3. Add parameterized Oracle operations to `Recruitment_Database`, with
   validation, authorization, nonce handling, and explicit error reporting at
   the appropriate WordPress boundary.
4. Add a migration only when required and document its approval/application
   state. Never execute DDL against a shared or production database without
   explicit authorization.
5. Update the changelog with implemented behavior, limitations, and any
   follow-up migration or validation requirement.

## Current prototype persistence

The User Department request UI currently stores those records in WordPress
user meta. Candidate lists and interview ratings use Oracle CRUD only where
the gateway and target schema are aligned; remaining prototype values must not
be presented as live recruitment records.

Some legacy application and vacancy gateway methods still contain
`DAW_*`-table operations; they need schema reconciliation before being treated
as aligned with the current Oracle schema. Do not run migration 003 for the
fixed-form submission flow.
