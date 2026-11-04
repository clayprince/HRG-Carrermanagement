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

The connection reads settings in this order: PHP constants, process
environment variables, the file pointed to by `RECRUITMENT_DOTENV_PATH`, then
the WordPress `recruitment_oracle_config` option. Keep credentials outside the
repository and never print, log, commit, or share their values. Setup and
read-only connection testing are documented in
[`../docs/oracle-env-setup.md`](../docs/oracle-env-setup.md). The initial
read-only vacancy/application schema map is in
[`../docs/oracle-vacancy-schema-map.md`](../docs/oracle-vacancy-schema-map.md).

## Account persistence

- Applicants authenticate against `AKUN_PELAMAR`. The table is defined by
  `migrations/002_applicant_accounts.sql`; this migration has not been applied
  automatically and requires review and execution by the authorized Oracle
  owner before Oracle-backed applicant registration can work.
- HR authenticates against `HR_USER`; `NAMA_PENGGUNA` is the email identifier.
- User Department accounts authenticate against `USERS_AD` and use the
  department relation from `DEPARTEMEN`.
- New account passwords use PHP `password_hash()` and are checked with
  `password_verify()`. WordPress users are restricted session/capability
  bridges, not copies of Oracle passwords.
- The inspected account tables did not contain an account status column.
  Migration `004_portal_account_status.sql` adds `STATUS_AKUN` to `HR_USER` and
  `USERS_AD`, defaulting existing rows to `ACTIVE`. The migration is a draft
  and has not been applied; the portal login/status-management code requires
  it before it can operate against this schema.
- User Department account listing and status changes use `USERS_AD` through
  `Recruitment_Database`. HR password changes update `HR_USER.PASSWORD` after
  verifying the current password. No password hashes are returned to screens.

## Schema alignment and migrations

The current Oracle schema uses tables such as `PROFIL_KANDIDAT`,
`DETAIL_INFO_LOWONGAN`, `HR_USER`, `USERS_AD`, and `DEPARTEMEN`. Some existing
vacancy/application methods in `class-database.php` still query legacy
`DAW_*` tables and have not been reconciled with that schema. Inspect the live
schema and each caller before building CRUD on those methods; do not assume
their SQL is compatible with the current Oracle database.

The dynamic application form uses `BAGIAN_PENDAFTARAN` plus the new
`RECRUIT_FORM_*` tables defined in
[`migrations/003_dynamic_application_forms.sql`](migrations/003_dynamic_application_forms.sql).
Migration 003 was applied to the Oracle schema connected to this workspace on
2026-11-02 with explicit authorization. Migrations are not run automatically,
so review and apply it separately in other environments. The builder and
public submission flow require these Oracle objects. Vacancy publication
remains backed by published WordPress `daw_vacancy` posts, and the application
page rejects demo fallback vacancies.

Read-only verification confirmed `SELECT 1 FROM DUAL`, all five form schema
tables/objects, and a successful application-form read. The legacy WordPress
form option was absent, so the builder's default form was initialized in
Oracle (5 sections, 22 questions) and read back successfully. The local
WordPress site currently has no published `daw_vacancy` posts, so a live
application submission cannot yet be tested.

`migrations/001_application_token.sql` alters the legacy
`DAW_APPLICATIONS` table. Treat it as a legacy migration: do not run it against
the current Oracle schema unless the database owner confirms that the target
schema contains the expected table and approves the change. Migration
`002_applicant_accounts.sql` is likewise a file only; it is not applied by
application code.
Migration `004_portal_account_status.sql` is also only a reviewed draft and
must not be applied until the Oracle owner approves it.

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

The User Department request and interview-rating UI currently stores those
records in WordPress user meta. Candidate lists, results, and various summary
values are prototype data. They are not Oracle CRUD and must not be presented
as live recruitment records.

Other legacy application and vacancy gateway methods still contain
`DAW_*`-table operations; they need schema reconciliation before being treated
as aligned with the current Oracle schema. The dynamic form submission path is
separate and writes only after migration `003` is applied.
