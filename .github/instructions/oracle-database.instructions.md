---
description: "Use when adding or changing Oracle or WordPress database CRUD, data access, persistence, or migrations in the Recruitment Plugin."
applyTo: "recruitment-plugin/database/**, recruitment-plugin/includes/class-application.php, recruitment-plugin/admin/pages/**, recruitment-plugin/public/**, recruitment-plugin/services/**"
---

# Recruitment Plugin database guidance

- Keep the WordPress database and Oracle separate. WordPress users, roles, and
  options use WordPress APIs and the database configured by `DB_*`. Oracle
  recruitment records use `Recruitment_Oracle_Connection` and
  `Recruitment_Database` in `recruitment-plugin/database/`.
- `wp-config.php` supplies `RECRUITMENT_DOTENV_PATH`, a pointer to local
  configuration. Check that the pointer is defined if setup diagnosis requires
  it, but resolve database settings through the existing connection class.
  Never print or persist the local path or inspect, print, copy, or commit the
  external file's credential values.
- Before implementing CRUD, inspect the current Oracle schema, gateway methods,
  service payloads, callers, and this database README. Extend existing patterns
  rather than creating another connection or persistence path.
- Put Oracle queries in `Recruitment_Database`, use PDO prepared statements
  with bound parameters, and use transactions for multi-record writes.
- Keep request validation, nonce checks, capability checks, and user-facing
  errors at the appropriate WordPress boundary. Do not rely on hidden UI
  controls as authorization.
- Add or modify schema migrations only when the requested change requires it.
  Do not execute migrations against shared or production databases without
  explicit approval.
- Do not add a silent fallback to `$wpdb`, files, sessions, or JSON for an
  Oracle operation. Report unavailable configuration/driver/database using
  existing error handling.
- See `recruitment-plugin/database/README.md` and
  `recruitment-plugin/docs/oracle-env-setup.md` for the maintained details.
