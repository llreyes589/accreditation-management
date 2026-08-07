# Workflows

> End-to-end workflows for the MVP. Each workflow lists the actors, the steps,
> the configurable rules, the fixed technical guarantees, and the assumptions.
> PSP-specific decision criteria are NOT specified here — they are referenced as
> configurable checklist/severity data.

## 1. Assumptions

- **A1.** Workflow transitions are state-machine driven and **server-enforced**;
  the client cannot bypass a transition.
- **A2.** Every transition that changes state is an "important action" and is
  written to the audit log.
- **A3.** "Final accreditation decisions require authorized human approval"
  (context). The approval step is a human action; the system only records and
  enforces that it occurred by an authorized role.
- **A4.** Notifications (email/etc.) are desirable but optional for MVP.

## 2. Workflow: Accreditation Application

Actors: Institution Administrator / Training Officer (submit), CART Reviewer
(triage/review), PSP Administrator (oversight).

States (proposed, configurable labels): `DRAFT → SUBMITTED → UNDER_REVIEW →
INSPECTION_SCHEDULED → INSPECTION_COMPLETE → DECISION_PENDING → DECIDED →
(renewal cycle)`.

Steps:
1. Institution Admin creates Application in `DRAFT`; adds Program(s).
2. Completes configurable ChecklistResponses; uploads versioned Documents.
3. Submits → `SUBMITTED`. Audit: "application submitted by X".
4. CART Reviewer reviews responses/docs; may return to `DRAFT` with comments
   (configurable whether this is allowed).
5. Reviewer schedules Inspection → `INSPECTION_SCHEDULED`.
6. Inspector conducts inspection (see Inspection workflow).
7. On inspection close → `INSPECTION_COMPLETE` → `DECISION_PENDING`.
8. Authorized human records Decision (see Decision workflow).

**Configurable:** which roles may return to DRAFT; required checklist completion
before SUBMIT; whether inspection is mandatory.

**Fixed:** state transitions enforced server-side; audit on every transition;
documents versioned on upload.

## 3. Workflow: Inspection

Actors: PSP Administrator (assign), Inspector (conduct), Training Officer
(coordinate on-site).

Steps:
1. Inspection created against Application; Inspector assigned.
2. Inspector records Findings during/after site visit.
3. Each Finding assigned a configurable severity.
4. For each Finding, Training Officer / Institution submits Corrective Actions.
5. Inspector verifies Corrective Actions (status: OPEN → SUBMITTED → VERIFIED /
   REJECTED).
6. Inspection closed; outcomes feed Decision.

**Configurable:** severity taxonomy; SLA/due dates for corrective actions;
whether rejection loops are allowed.

**Fixed:** findings and corrective actions linked immutably to inspection; all
changes audited; no deletion of historical findings.

## 4. Workflow: Corrective Action

Actors: Training Officer / Institution Admin (propose), Inspector (verify).

Steps:
1. Created from a Finding; due date set.
2. Status tracked: `OPEN → IN_PROGRESS → SUBMITTED → VERIFIED/REJECTED`.
3. On REJECTED, returns to OPEN/IN_PROGRESS (configurable loop limit).

**Configurable:** status vocabulary, loop limits, default due-date windows.

**Fixed:** linkage to Finding is immutable; audit on each status change.

## 5. Workflow: Accreditation Decision

Actors: CART Reviewer / PSP Administrator (recommend), authorized approver
(human, final).

Steps:
1. System presents inspection outcomes + checklist + corrective action status.
2. An authorized human records the Decision (outcome: e.g. grant / deny /
   grant-with-conditions — **labels configurable**).
3. Human approval is recorded with approver id + timestamp (no auto-decision).
4. Decision persisted; historical decisions retained (no deletion).
5. If accredited, a renewal date is set (configurable validity period).

**Configurable:** outcome vocabulary; who is "authorized" to approve (role
matrix); validity period; renewal timing.

**Fixed:** decision requires a recorded human approver; decisions are
append-only history; cannot be deleted.

## 6. Workflow: Resident Profile & Portfolio

Actors: Resident (self-entry, limited), Training Officer (manage/verify),
Institution Admin (oversight), Read-only Auditor (view).

Steps:
1. Resident profile created (institution-scoped).
2. Resident / Training Officer add Portfolio items (versioned).
3. Any item containing patient info is de-identified before storage.
4. Training Officer verifies portfolio completeness.

**Configurable:** required portfolio item types; verification rules.

**Fixed:** de-identification enforced at write; versioning of portfolio items;
access limited by role + institution scope.

## 7. Workflow: Renewal (lightweight, MVP)

Actors: Institution Admin, CART Reviewer, approver.

Steps:
1. Near renewal date (configurable lead time), Institution submits renewal.
2. Reuse checklist + updated documents; optionally a focused inspection.
3. Decision recorded as a new Decision against the renewal cycle.

**Configurable:** renewal lead time; whether re-inspection is required.

## 8. Cross-Cutting: Audit Logging

Every important action (submit, transition, upload, decision, approval, role
change) appends an `AuditLog` row with actor, action, target, timestamp, and a
chain hash for tamper-evidence (hash chaining optional — **open**).

## 9. Unresolved Questions

- **Q1.** Can an Application be returned to DRAFT after SUBMITTED, and by whom?
- **Q2.** Is an on-site inspection mandatory for every application, or only on
  triggers (e.g. prior findings)?
- **Q3.** Who exactly is the "authorized human" for final decisions, and can
  that be delegated?
- **Q4.** Are corrective-action due dates legally/contractually fixed or
  internally set?
- **Q5.** What is the renewal cadence (e.g. annual, multi-year)?
- **Q6.** Should residents be able to edit their own portfolio, or is it
  Training-Officer-managed only?
