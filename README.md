# DAW Recruitment

WordPress recruitment plugin with three distinct user areas: public/applicant,
Admin HR, and User Department (department head). This README summarizes the
current implementation and points contributors to focused technical guidance.
The source code is authoritative when behavior and documentation differ.

## Current state

- Portal logins for applicants, HR, and User Department are verified against
  Oracle account tables; WordPress roles and users provide restricted sessions
  and capabilities.
- The User Department portal has dashboard, vacancy request, interview,
  applicant, and interview-results views. Candidate records and much of the
  displayed activity are still prototype data. Department requests and
  interview ratings currently use WordPress user meta, not Oracle.
- Oracle-backed applicant accounts use `AKUN_PELAMAR`, which requires the
  pending migration described in the [database guide](recruitment-plugin/database/README.md).
- Some vacancy, application, and legacy recruitment gateway methods still
  query `DAW_*` tables. They have not yet been reconciled with the current
  Oracle schema; do not assume those flows are production-ready.
- No migration is automatically applied by the plugin. Oracle DDL must be
  reviewed and applied by the authorized database owner.

## System boundaries

| Area | Purpose | Access boundary |
| --- | --- | --- |
| Public / Applicant | Public vacancies, applicant account, application and status pages | Applicants must use the applicant portal |
| Admin HR | Recruitment operations and account administration | HR capability; not granted to department users |
| User Department | Department requests and assigned interview work | Department capability; must not access HR routes or unrelated candidate data |

Keep authorization checks on protected routes and data operations; hiding a
navigation item is not access control. Do not expose private HR notes or
confidential assessment data to department users.

## Architecture and data

- WordPress owns WordPress users, roles, capabilities, options, and portal
  session bridges.
- Oracle owns only recruitment records explicitly integrated with the Oracle
  gateway. Use `Recruitment_Oracle_Connection` and
  `Recruitment_Database`; do not create page-level connections or embed SQL in
  templates.
- Oracle schema changes belong in reviewed migration files. Never apply DDL
  to a shared database without explicit authorization.
- New screens should follow the existing route registry, role boundary,
  navigation, and Screen Explorer patterns.

See the [plugin guide](recruitment-plugin/README.md), [database guide](recruitment-plugin/database/README.md),
[Oracle local setup](recruitment-plugin/docs/oracle-env-setup.md),
[initial Oracle vacancy schema map](recruitment-plugin/docs/oracle-vacancy-schema-map.md),
and [Oracle database contributor instructions](.github/instructions/oracle-database.instructions.md).

## Next planned milestone (not implemented)

The next major feature area is vacancy posting and its intake workflow:

1. HR form builder to configure application form sections and question fields.
2. HR vacancy creation and User Department vacancy-request forms.
3. HR vacancy list and User Department request list.
4. HR handling of applications and FPTK workflows.

Treat this as a roadmap, not a description of existing CRUD. Before removing
prototype data or implementing persistence, confirm the Oracle schema mapping,
ownership of each workflow, statuses, and authorization rules.

## Contributor workflow

1. Read this overview, the [changelog](CHANGELOG.md), and the focused docs for
   the area being changed.
2. Inspect current callers, routes, capabilities, data gateway methods, and
   relevant schema before coding. Do not infer production behavior from
   screenshots or demo records.
3. Keep WordPress persistence and Oracle persistence separate; use the existing
   gateway for Oracle and surface database errors rather than silently falling
   back to another store.
4. Keep mock data clearly identified and never present it as live Oracle data.
5. Add new screens to the route registry, authorization boundary, navigation,
   and Screen Explorer as applicable.
6. Update `CHANGELOG.md` for material changes, preserving the distinction
   between implemented behavior, known limitations, and planned work.
7. Keep credentials outside the repository. Never copy secrets into source,
   documentation, logs, commits, or AI prompts.
