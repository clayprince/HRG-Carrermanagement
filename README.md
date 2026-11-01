# Recruitment Plugin Architecture & Development Guide

This document is the permanent development guide for this project. It exists to help both human developers and future AI coding assistants understand the intended architecture, role boundaries, and safe modification rules before making changes.

Important: this README is documentation only. The current application implementation is the source of truth and must remain intact unless a developer explicitly requests a feature change.

---

## 1. Purpose of this project

This project is a recruitment management system with three main operational areas:

1. Public Site
2. Admin HR
3. User Department

These areas are logically separated and must remain separated in both behavior and implementation. They share recruitment data, but they do not share the same responsibilities or permission boundaries.

The goal is to support:

- modular development
- parallel work by multiple developers
- clear role-based access control
- maintainable project structure
- consistent UI behavior with the current implementation
- reduced risk of accidental feature conflicts

---

## 2. Documentation and implementation

Documentation describes the intended architecture and developer workflow; it
does not replace the implementation as the source of truth. Treat proposed or
conceptual workflows in this README as plans unless the current code confirms
they are implemented.

For each task, follow the requested scope, inspect the relevant code and its
existing changes, and preserve unrelated behavior. Documentation updates alone
do not authorize application changes, and documentation must not impose a
blanket restriction against code changes when a user explicitly requests them.

---

## 3. Current system areas

### 3.1 Public Site

The Public Site is the interface for applicants and candidates.

Its responsibilities include:

- viewing available vacancies
- searching and filtering vacancies
- viewing vacancy details
- viewing job requirements
- submitting applications
- completing the application form
- uploading required documents/information
- receiving an application code
- tracking application status
- viewing recruitment stages
- viewing psychotest information
- viewing offline psychotest information when applicable
- viewing interview information
- selecting interview slots when applicable
- viewing final recruitment status

The Public Site must not expose internal HR information such as:

- internal HR notes
- private interviewer comments
- confidential psychotest interpretation
- internal recommendations
- internal ranking
- internal HR decisions
- other applicants' data

### 3.2 Admin HR

Admin HR is the central recruitment management area.

HR manages the recruitment process across vacancy creation, candidate review, screening, interviews, and final decisions.

Key responsibilities include:

- reviewing manpower and vacancy requests
- reviewing requirement alignment information
- approving, revising, or rejecting requests
- creating and editing vacancies
- publishing and closing vacancies
- viewing active applicants
- searching and filtering applicants
- reviewing application data and requirement alignment
- checking matched or unmatched requirements
- performing administration selection
- approving or rejecting candidates
- moving candidates through recruitment stages
- viewing applicant details
- managing psychotest settings and question banks
- managing online and offline psychotest workflows
- scheduling offline psychotests
- inputting manual offline psychotest results
- reviewing psychotest status and outcomes
- generating psychotest reports
- managing HR interviews
- recording HR interview comments and results
- deciding whether a candidate continues
- recommending appropriate User Departments
- recommending one or multiple departments when applicable
- reviewing User Department interview results and comments
- reviewing recommendations and selection status
- continuing a candidate toward final recruitment decisions
- managing applicant database entries
- handling interview scheduling, dates, slots, locations, and links
- sending recruitment emails with filters and templates
- monitoring email status and resending unread emails
- creating, editing, activating, deactivating, and deleting User Department accounts where appropriate
- controlling which departments each User Department account can access

HR responsibilities are broader than the Public Site or User Department role. HR acts as the central coordinator and gatekeeper.

### 3.3 User Department

User Department represents a department head or hiring user. This role is intentionally limited and is not another HR account.

User Department has three main functions.

#### Function 1: Request opening a vacancy

User Department can submit a manpower or vacancy request based on a requirement alignment form. This may include:

- department
- position
- position level
- category
- hiring user
- recruiter PIC
- number required
- employment status
- request type
- recruitment priority
- target fulfillment
- work location
- reason for request
- position purpose
- main responsibilities
- expected output
- candidate education
- preferred major
- experience
- certification
- technical competencies
- behavioral competencies
- mandatory requirements
- preferred requirements
- recruitment / sourcing strategy
- assessment requirements
- interview stages
- other requirement alignment information

This request is reviewed by HR and may be:

- approved
- sent back for revision
- rejected

User Department does not directly publish vacancies.

#### Function 2: User Interview

User Department can access candidates only when:

1. the candidate has completed or passed the relevant HR interview stage
2. HR has assigned or recommended the candidate to that User Department

User Department can:

- view permitted candidate details
- download applicant profile
- review relevant recruitment information
- conduct the user interview
- write interview comments
- submit interview results
- recommend the candidate
- recommend another department when appropriate

#### Function 3: Candidate data access

User Department can only see candidates assigned to its department.

It may view:

- candidate profile
- application information required for interview
- relevant HR interview result and status
- interview information

It must not see unrelated candidates or unrelated applicant data, and it must not automatically receive:

- private HR notes
- confidential psychological interpretation
- confidential internal assessment
- complete HR database access
- HR configuration details

---

## 4. Page Explorer / Screen Explorer

The existing Page Explorer or Screen Explorer is an important development shortcut. It allows developers to navigate directly to implemented pages and supports parallel development.

This rule must be preserved conceptually:

- Developer A works on one page
- Developer B works on another page
- Developer C should not need to wait for Page A or Page B to be completed before accessing Page C

Therefore, every future page must automatically be added to the Page Explorer when it is created.

The expected flow for each new page is:

1. create the page
2. create or register the route
3. apply the correct role restriction
4. add the page to the Page Explorer
5. provide a direct shortcut
6. ensure the shortcut points to the real implemented page

This is documentation-only guidance for the current task. The current Page Explorer must not be modified now.

### Conceptual organization

- Public Site
  - Home
  - Career / Job Vacancies
  - Job Detail
  - Application Form
  - Application Tracking
  - Other Public Pages
- Admin HR
  - Dashboard
  - Recruitment
  - Applicant Detail
  - Administration Selection
  - Psychotest
  - HR Interview
  - User Interview Results
  - Vacancy
  - Application Form Management
  - Interview Schedule
  - Email Recruitment
  - Applicant Database
  - User Department Account Management
- User Department
  - Dashboard
  - Request Vacancy
  - Candidate User Interview
  - User Interview Results

This is a conceptual structure, not a mandate to create pages right now.

---

## 5. Parallel development rule

This project is designed to allow multiple developers to work in parallel.

A developer should not be blocked by another unfinished page. If a page depends on another page that is still incomplete:

- create the required page independently
- use compatible data structures
- use mock or placeholder data when appropriate
- create its route
- add it to the Page Explorer
- preserve future integration compatibility

The development model should avoid unnecessary waiting and dependence chains.

---

## 6. Single source of truth

There must be one central candidate/application record.

Do not duplicate applicants across roles.

Conceptually:

Candidate
↓
Public Application
↓
HR Recruitment
↓
HR Interview
↓
HR Department Recommendation
↓
User Department Interview
↓
Final Decision

Different roles should access the same underlying candidate record according to permissions.

---

## 7. Critical role access logic

User Department access must be controlled by HR. It must not be inferred only from the candidate's originally applied position.

Example:

- Candidate applies for Sales Executive
- HR interview result recommends Marketing
- Candidate becomes visible to the Marketing User Department
- Candidate is not automatically visible to Sales

The permission chain is:

Candidate
↓
HR Interview
↓
HR Recommendation
↓
Recommended Department
↓
User Department Access

A User Department account may have access to one or multiple departments if HR explicitly grants that access.

---

## 8. HR → User Department routing

The HR interview result should conceptually contain:

- candidate
- HR interview result
- HR notes
- recommendation
- next stage
- recommended department(s)
- assigned User Department(s)
- interviewer
- date

This data controls the next recruitment stage.

Example:

- Candidate: Budi Santoso
- HR Result: Sangat Baik
- Next Stage: User Interview
- Recommended Department: Sales

Result: Budi becomes visible to the appropriate Sales User Department.

---

## 9. User account access

HR manages User Department accounts.

A User account can have:

- name
- email
- department
- role
- account status
- department access list

Example:

- User: Andi
- Access: Sales, Marketing
- Andi can access candidates assigned to Sales or Marketing
- Andi cannot access candidates assigned only to Service

Account status can be:

- Active
- Inactive

Historical interview records must remain intact even if an account becomes inactive.

---

## 10. Modular code architecture

The project must remain modular and maintainable.

Future code should keep concerns separated:

- pages
- components
- assets
- routes
- services
- data models
- authentication
- authorization
- utilities
- shared components

Conceptual structure:

```text
project/
├── public/
│   ├── pages/
│   ├── components/
│   ├── assets/
│   └── routes/
├── admin/
│   ├── pages/
│   ├── components/
│   ├── assets/
│   └── routes/
├── user-department/
│   ├── pages/
│   ├── components/
│   ├── assets/
│   └── routes/
├── shared/
│   ├── components/
│   ├── assets/
│   ├── utilities/
│   └── types/
├── services/
├── auth/
├── permissions/
└── README.md
```

Important: this is a conceptual guideline. If the current project already uses a different clean structure, do not restructure it just to match the diagram. Preserve the existing architecture.

### Database boundaries

The WordPress database configured by `DB_*` in `wp-config.php` is separate from
the Oracle database used for recruitment-domain records. Use WordPress APIs for
WordPress users, roles, options, and plugin-managed WordPress data. For Oracle
recruitment records, follow the shared connection and data gateway in
[`recruitment-plugin/database/README.md`](recruitment-plugin/database/README.md);
the file-specific AI rules are in
[`.github/instructions/oracle-database.instructions.md`](.github/instructions/oracle-database.instructions.md);
do not create a second connection or put Oracle SQL in page templates.

Oracle credentials are held outside the repository. `wp-config.php` provides
the `RECRUITMENT_DOTENV_PATH` pointer to the local configuration file; never
copy credentials, print them in logs, or include their values in code or
documentation.

---

## 11. Component reuse

Before creating a new component, check whether a current component already provides the needed functionality.

Prefer reusable components when appropriate, such as:

- CandidateCard
- CandidateDetail
- StatusBadge
- FilterBar
- SearchBar
- DownloadButton
- Modal
- FormField
- SectionHeader
- DataTable

Do not duplicate functionality unnecessarily, but also do not over-abstract unrelated UI just to reduce the number of files.

---

## 12. Asset management

Keep assets organized and preserve the current structure when it already exists.

Conceptual structure:

```text
assets/
├── images/
├── icons/
├── logos/
├── fonts/
└── ...
```

If the current project already contains another asset organization, preserve it instead of moving or renaming assets.

---

## 13. Routing rules

Every future new page should have:

1. a page component
2. a route
3. a role restriction
4. a Page Explorer entry
5. appropriate navigation when needed
6. a clear relationship to the information architecture

Do not create random naming conventions. Follow the existing project routing style.

---

## 14. Role-based routing

Future protected routes must respect role permissions.

Conceptually:

- Public → Public pages only
- HR → HR pages and permitted shared pages
- User Department → User Department pages and permitted candidate data

Important: hiding a menu item is not sufficient authorization.

Protected data should also validate authorization at the route and data-access level. A User Department user must not gain HR access just by typing an HR URL manually.

---

## 15. UI preservation rule

The current UI and design system are already approved.

Future AI coding assistants must not redesign the application unless the developer explicitly asks for a redesign.

When creating a new page:

- inspect the existing pages
- reuse existing components
- reuse existing spacing
- reuse typography
- reuse colors
- reuse buttons
- reuse cards
- reuse forms
- follow the existing design language

Do not introduce unrelated:

- gradients
- excessive colors
- excessive rounded cards
- glassmorphism
- gaming-style UI
- excessive animations
- new design systems

A new page should look like it belongs to the current application.

---

## 16. Domain and application separation

The project should remain architecturally ready for separate application areas.

Conceptually:

- Public: domain.com
- Admin HR: admin.domain.com
- User Department: user.domain.com

The exact production domain may differ. Do not hardcode production domains during development. Use configurable environment variables when appropriate.

Conceptual configuration:

- PUBLIC_APP_URL
- ADMIN_APP_URL
- USER_APP_URL

If the current project already uses another routing or domain approach, preserve it.

---

## 17. Future AI coding process

Before modifying code, future AI coding assistants should:

1. read this README
2. identify the affected system area:
   - Public
   - Admin HR
   - User Department
3. inspect the existing code
4. check existing pages
5. check existing components
6. check existing routes
7. check role permissions
8. check the Page Explorer
9. reuse existing components where appropriate
10. modify only the necessary scope

When creating a new page, the default expectation is:

New Page
↓
Route
↓
Role Access
↓
Page Explorer
↓
Design System Reuse
↓
Implementation

The developer should not separately need to request Page Explorer registration.

---

## 18. Do not break existing features

Future changes must avoid unnecessary modifications to unrelated functionality.

Existing features should remain working.

Do not remove or replace existing features unless explicitly requested by the developer.

Do not modify unrelated areas including:

- public pages
- HR pages
- User Department pages
- recruitment logic
- application forms
- candidate tracking
- psychotest workflows
- interview scheduling
- PDF generation
- email recruitment
- existing components
- existing assets

If shared code genuinely needs modification, make the smallest safe change possible.

---

## 19. Data flow

### Vacancy flow

User Department
↓
Requirement Alignment Request
↓
HR Review
↓
Approve / Revision / Reject
↓
Approved
↓
HR Creates Vacancy
↓
Public Vacancy Published
↓
Candidate Applies

### Candidate flow

Candidate
↓
Administration
↓
Psychotest
↓
HR Interview
↓
HR Decision
├── Not Continued
└── Continue to User Interview
↓
HR Recommended Department
↓
Assigned User Department
↓
User Interview
↓
User Interview Result
↓
HR Review
↓
Final Decision

---

## 20. Future-proofing

Do not hardcode the system around:

- one department
- one User Department account
- one HR user
- one candidate
- one vacancy

The system should be data-driven.

Conceptually:

User
→ role
→ department access[]

Candidate
→ HR assignment
→ recommended department[]

This allows the number of departments and User Department accounts to grow.

---

## 21. Preserve existing behavior

Keep changes limited to the requested scope and preserve existing behavior
outside it. Do not remove, replace, redesign, rename, restructure, or disable
features unless the request explicitly requires it. This applies to
documentation and application changes alike.

---

## 22. README maintenance rule

When future architectural decisions are made, update this README when appropriate.

However, documentation updates do not automatically permit application behavior changes. Documentation and implementation remain separate concerns.

---

## 23. Final AI principle

The purpose of this project architecture is to allow multiple developers to work in parallel, keep Public / HR / User Department responsibilities clearly separated, maintain secure role-based access, keep the code modular, and make the project easy to maintain.

The AI should prioritize:

1. existing working implementation
2. existing UI and design system
3. data integrity
4. role-based access
5. modular architecture
6. Page Explorer consistency
7. minimal-scope changes
8. backward compatibility

Never make a large structural change just because it is easier or faster.

---

## 24. Current project structure summary

The current repository includes a plugin-oriented structure with these main areas:

```text
DAW/
├── README.md
├── daw-recruitment.php
├── .github/
│   ├── copilot-instructions.md
│   └── instructions/
├── recruitment-plugin/
│   ├── admin/
│   │   ├── pages/
│   │   └── components/
│   ├── public/
│   │   ├── pages/
│   │   └── components/
│   ├── assets/
│   ├── components/
│   ├── database/
│   │   ├── connection/
│   │   └── migrations/
│   ├── docs/
│   ├── includes/
│   ├── pages/
│   ├── routes/
│   ├── templates/
│   ├── index.php
│   ├── recruitment-plugin.php
│   └── README.md
└── ...
```

The exact structure may evolve over time, but the architecture and separation-of-responsibility rules remain the same.

---

## 25. Final guidance

This README is the official project guidance for future development work.

Before making any code changes, a developer or AI assistant should:

- read this README
- read relevant folder documentation and `.github/instructions` guidance
- confirm which system area is being changed
- confirm the user role involved
- inspect the current implementation
- preserve the existing UI and route behavior
- keep the change minimal and scoped
- avoid introducing feature conflicts
- for Oracle CRUD, follow [`recruitment-plugin/database/README.md`](recruitment-plugin/database/README.md) and [the Oracle database instruction](.github/instructions/oracle-database.instructions.md)

The project should evolve without breaking the validated working behavior already present in the codebase.

The long-term objective is not a redesign. The long-term objective is a stable, modular, role-aware recruitment system that remains safe, maintainable, and compatible with its current implementation.
