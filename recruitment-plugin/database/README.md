# Database

This plugin uses two separate persistence systems. WordPress's own database
(configured by `DB_*` in `wp-config.php`) stores WordPress users, roles,
options, and other WordPress-managed data. Oracle stores recruitment-domain
records that are explicitly integrated with the Oracle gateway. Do not treat
these databases as interchangeable.

## Structure

```text
database/
  connection/
    class-oracle-connection.php  # Oracle config and shared lazy PDO_OCI connection
  class-database.php             # Domain queries used by pages and services
  migrations/                    # Versioned Oracle schema changes
```

`Recruitment_Oracle_Connection` owns Oracle configuration and provides one lazy
PDO connection shared for the request. `Recruitment_Database` owns the
recruitment queries and transaction operations. New Oracle CRUD should follow
this gateway and the existing service/caller pattern. Pages and components
must not create PDO connections, keep database state in PHP sessions/files,
or embed SQL. The legacy `includes/class-database.php` is a compatibility
wrapper; use `database/class-database.php` for Oracle work.

Configuration is read in priority order from PHP constants, process
environment variables, the `.env` file named by `RECRUITMENT_DOTENV_PATH`, and
the `recruitment_oracle_config` WordPress option.

`wp-config.php` should provide the path pointer
`RECRUITMENT_DOTENV_PATH`; the Oracle credential values belong in the external
env file or another approved secret source, not in this repository. Check only
that the pointer is configured when diagnosing setup; do not print, log,
commit, or copy its local value or the secret values into prompts or
documentation.

Supported settings:

```text
RECRUITMENT_ORACLE_DSN
RECRUITMENT_ORACLE_USER
RECRUITMENT_ORACLE_PASSWORD
```

The PHP `pdo_oci` extension and valid Oracle settings are required for database
operations. If either is unavailable, database operations report an error;
there is no JSON or session-backed database fallback.

## Applicant account credentials

Applicant passwords are stored as PHP `password_hash()` values in
`AKUN_PELAMAR`; plaintext passwords are never written to Oracle or logs. The
`PROFIL_KANDIDAT` table has no credential columns, so migration
`migrations/002_applicant_accounts.sql` adds the separate account table.
Registration and login require this migration to be approved and applied by
the Oracle owner. The application does not run schema migrations automatically.

WordPress users remain a session-and-capability bridge for applicant routes.
Their WordPress passwords are randomized; applicant credentials are verified
against Oracle.

## HR and department accounts

The HR portal authenticates against `HR_USER.NAMA_PENGGUNA` and
`HR_USER.PASSWORD`; `NAMA_PENGGUNA` is the HR email identifier. Department
accounts authenticate against `USERS_AD.USERNAME` or `USERS_AD.EMAIL` and
`USERS_AD.KATA_SANDI`. New passwords are stored using PHP
`password_hash()`; plaintext passwords are not accepted. The HR account form
creates department accounts in Oracle and uses the identity-generated
`USERDEPARTEMEN_ID`. WordPress users are created only as restricted
session/capability bridges after Oracle verifies the account.

The department account form reads department choices from `DEPARTEMEN`.
Populate that table before creating department accounts.

## Adding or changing Oracle CRUD

- Inspect the current schema, gateway methods, service payloads, and callers
  before changing data access. Follow existing naming and result conventions.
- Put parameterized Oracle SQL and transaction handling in
  `Recruitment_Database`; use prepared statements and bound values.
- Keep authorization, nonce verification, input validation, and user-facing
  error handling at the appropriate WordPress request/service boundary.
- Use WordPress APIs (`wp_insert_user()`, `wp_update_user()`, user meta,
  `$wpdb` where applicable) for WordPress-managed records, not the Oracle
  connection.
- Add or change Oracle migrations only when the requested feature needs a
  schema change. Never apply a migration to a shared or production database
  without explicit authorization.
- Surface failures using established error handling; do not silently fall
  back to files, sessions, JSON, or another database.

For local Oracle setup, driver checks, and safe connection testing, see
[`../docs/oracle-env-setup.md`](../docs/oracle-env-setup.md).

## Application Payload Contract

`includes/class-application.php` normalizes the public application form into a
database-ready payload. The page does not create a connection or execute SQL.
The payload is grouped into:

```text
application   vacancy_id, status
applicant     identity and contact fields
education     repeatable education rows
work_history  repeatable employment rows
family        marital and family relationship fields
preferences   salary, benefits, and known employee fields
consents      PDP consent flags
photo         uploaded file metadata for the storage layer
```

The application service passes this payload to the database gateway, which
persists it in Oracle.

## Application Token Migration

Run `migrations/001_application_token.sql` once against Oracle before enabling
production submissions. It adds `daw_applications.application_token` and a
unique index used to protect token uniqueness during concurrent submissions.