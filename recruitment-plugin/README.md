# Recruitment Plugin

WordPress plugin for public recruitment pages, Admin HR, and User Department.
See the [repository overview](../README.md) for current implementation status
and the next planned milestone.

## Key code areas

- `recruitment-plugin.php` — plugin bootstrap.
- `includes/class-plugin.php` — portal hooks, account flow, and WordPress
  session/capability bridge.
- `routes/class-router.php` — page registry and protected route checks.
- `admin/` and `public/` — portal screens and components.
- `assets/css/` and `assets/js/` — enqueued styles and scripts.
- `database/` — Oracle connection, query gateway, and migration files.
- `docs/` — local setup and development notes.

Follow the current code structure rather than introducing a parallel module
layout. The source code is authoritative for what is implemented.

## Persistence

WordPress and Oracle are separate stores. WordPress APIs own roles, session
bridges, and WordPress-managed state. Oracle access goes through
`database/connection/class-oracle-connection.php` and
`database/class-database.php`; pages must not create their own PDO connection
or execute Oracle SQL.

For account tables, gateway conventions, migration cautions, and current
schema-alignment limitations, see the [database guide](database/README.md).
For local PDO_OCI setup and safe connection checks, see
[`docs/oracle-env-setup.md`](docs/oracle-env-setup.md).

## Development

- Use prepared statements and bound parameters for Oracle queries.
- Keep authorization, nonce checks, and validation at the appropriate
  WordPress boundary.
- Add migrations only when required; the plugin does not apply migrations.
- Do not treat demo data or prototype screens as production records.
- When adding a screen, register its route and access rules and update
  navigation and Screen Explorer.
- Record material changes and their status in the repository
  [changelog](../CHANGELOG.md).
