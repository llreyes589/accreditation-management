# Domain Model

> Entities and relationships for the PSP Training Accreditation Management System.
> Derived from `PROJECT_CONTEXT.md`. PSP-specific accreditation criteria are
> deliberately NOT modeled as fixed fields — they live in the configurable
> checklist. Fields below are generic; thresholds, categories, and labels are
> configurable unless marked **[fixed]**.

## 1. Assumptions

- **A1.** An *Institution* may operate one or more *Programs* (one per program
  type: Anatomic, Clinical, Combined). A Program is the unit that is accredited.
- **A2.** A *Resident* belongs to an Institution and is tracked within a Program.
  Resident–program linkage may be many-to-many across time (rotations) but MVP
  models a primary enrollment. *(Confirm cardinality.)*
- **A3.** An *Accreditation Application* is submitted per Program (or per
  Institution cycle — **open**).
- **A4.** Inspections, Findings, and Corrective Actions attach to an Application
  or Program accreditation record.
- **A5.** Resident clinical information is protected; any patient-referencing
  content must be de-identified before storage.
- **A6.** All time-bound records store both a system timestamp **and** the acting
  user id for auditability.

## 2. Entity Overview

| Entity | Purpose | Key attributes (generic) |
|--------|---------|--------------------------|
| User | Account for any of the 8 roles | id, email, name, status, auth fields |
| Role | Access role | id, name, permissions (configurable) |
| Institution | Training institution | id, name, identifiers, status |
| Program | Accreditation unit within institution | id, institution_id, program_type, status |
| Application | Accreditation application submission | id, program_id, status, submitted_by, cycle |
| ChecklistItem | One configurable compliance requirement | id, category, description, weight, threshold |
| ChecklistResponse | Institution's answer/evidence per item | id, application_id, item_id, status, notes |
| Document | Versioned supporting file | id, owner_id, version, hash, deidentified_flag |
| Inspection | Scheduled/conducted site inspection | id, application_id, inspector_id, status, dates |
| Finding | Issue identified during inspection | id, inspection_id, severity (config), status |
| CorrectiveAction | Remediation plan/tracking | id, finding_id, due_date, status, verified_by |
| Decision | Accreditation decision record | id, application_id, outcome, approved_by, dates |
| Resident | Trainee profile | id, institution_id, status, deidentified_clinical |
| ResidentPortfolio | Resident's collected evidence/activities | id, resident_id, items (versioned) |
| AuditLog | Immutable record of important actions | id, actor_id, action, entity_ref, timestamp, hash |

## 3. Relationships (textual)

- Institution **1—N** Program.
- Program **1—N** Application (across cycles) *(cardinality open)*.
- Application **1—N** ChecklistResponse; ChecklistItem **1—N** ChecklistResponse.
- Application **1—N** Document (as supporting docs).
- Application **1—N** Inspection.
- Inspection **1—N** Finding.
- Finding **1—N** CorrectiveAction.
- Application **1—1** Decision (or 1—N history of decisions; **open**).
- Institution **1—N** Resident.
- Resident **1—N** ResidentPortfolio entry.
- User **N—N** Role (a user may hold one or more roles; **open** whether a user
  can hold roles across institutions).

## 4. Key Modeling Decisions

### Program types
The three program types (Anatomic, Clinical, Combined) are **configurable values**,
not hard-coded enums that carry business logic. Stored as a configurable list so
new program types can be added without code changes.

### Checklist (configurable core)
The compliance checklist is data, not code. Each `ChecklistItem` carries:
- a category/group,
- a textual requirement,
- an optional weight,
- a pass/fail or scored threshold,
- applicability rules (e.g. which program types it applies to).

This satisfies "checklist requirements must be configurable" and "do not
hard-code PSP requirements that may change."

### Documents are versioned
Every write to a Document creates a new version. `Document` rows are immutable
once written; "edit" = new version pointer. This satisfies "submitted documents
must be versioned."

### De-identification
Any field or attached content that could contain patient information is
de-identified before persistence. A `deidentified_flag` marks records that have
passed this step. The de-identification *rules* (what counts as PHI, how to
scrub) are a **configurable/confirmed policy**, not hard-coded in the model.

### Audit log is append-only
`AuditLog` has no UPDATE/DELETE paths in the data-access layer. Satisfies
"important actions must be audit logged" and "do not delete historical
accreditation records."

## 5. Configurable vs Fixed

**Configurable:**
- Program type list and labels.
- Checklist item definitions, categories, weights, thresholds, applicability.
- Finding severity taxonomy and labels.
- Document versioning/retention parameters.
- Role-to-permission assignments.

**Fixed (modeled structurally, not as data):**
- Existence of the entities above and their relationships.
- Versioning mechanic (immutability of document versions).
- Append-only audit.
- User↔Institution scoping for data isolation.

## 6. Unresolved Questions

- **Q1.** Is an Application submitted per Program, per Institution, or per
  Program-type group?
- **Q2.** Can a User hold roles across multiple Institutions, or is a user
  institution-scoped?
- **Q3.** What is the minimum resident profile (fields) required, and which are
  mandatory vs optional?
- **Q4.** What exactly constitutes "patient information" in resident portfolio
  items that must be de-identified, and who defines the scrubbing rules?
- **Q5.** Are accreditation Decisions a single record per cycle or a full history
  (recommendation: full history for audit)?
- **Q6.** Should ChecklistResponses allow free-text evidence only, or structured
  uploads + scores?
