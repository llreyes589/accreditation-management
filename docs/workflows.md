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

States (proposed, configurable labels): `DRAFT → SUBMITTED → UNDER_COMPLETENESS_REVIEW →
RETURNED_FOR_REVISION → COMPLETE_FOR_INSPECTION → INSPECTION_SCHEDULED →
INSPECTION_COMPLETE → DECISION_PENDING → DECIDED → (renewal cycle)`.

Steps:
1. Institution Admin creates Application in `DRAFT`; adds Program(s).
2. Completes configurable ChecklistResponses; uploads versioned Documents.
3. Submits → `SUBMITTED`. Audit: "application submitted by X".
4. **Completeness Review** (see §2.1): a PSP/CART reviewer performs an
   administrative and documentary check only — it never determines compliance,
   eligibility, or an accreditation decision. It may return the application to
   `RETURNED_FOR_REVISION` with mandatory comments, or mark it
   `COMPLETE_FOR_INSPECTION`.
5. Reviewer schedules Inspection → `INSPECTION_SCHEDULED`.
6. Inspector conducts inspection (see Inspection workflow).
7. On inspection close → `INSPECTION_COMPLETE` → `DECISION_PENDING`.
8. Authorized human records Decision (see Decision workflow).

**Configurable:** which roles may return to DRAFT; required checklist completion
before SUBMIT; whether inspection is mandatory.

**Fixed:** state transitions enforced server-side; audit on every transition;
documents versioned on upload.

### 2.1 Workflow: Completeness Review (administrative & documentary check)

Actors: PSP Administrator / CART Reviewer (review), Institution Administrator /
Training Officer (revise + resubmit).

This is an **administrative and documentary check only**. It must not determine
final compliance, accreditation eligibility, or approval, replace the inspection
process, create an accreditation decision, or automatically schedule an
inspection. (An extension point for future inspection scheduling is left in the
service.)

Allowed transitions (server-enforced):

- `SUBMITTED → UNDER_COMPLETENESS_REVIEW` (reviewer begins)
- `UNDER_COMPLETENESS_REVIEW → RETURNED_FOR_REVISION` (reviewer returns; **reviewer
  comment required**)
- `UNDER_COMPLETENESS_REVIEW → COMPLETE_FOR_INSPECTION` (reviewer marks complete;
  **blocked while any blocking item remains**)
- `RETURNED_FOR_REVISION → SUBMITTED` (applicant resubmits)
- `RETURNED_FOR_REVISION → WITHDRAWN` (only if the existing withdrawal policy
  permits it)

Completeness calculation reuses the existing checklist and document calculators
(`ApplicationProgressCalculator` + `DocumentCompletenessService`) via
`ApplicationCompletenessService`. A blocking item is any outstanding mandatory
checklist response, or a required supporting document that is missing, rejected,
or has a replacement requested. The reviewer detail page surfaces a unified
deficiency / missing-item list.

Every status transition appends a `CompletenessReview` history row (the
completeness report) and an audit-log entry. Reviewer comments on return are
stored on the application and shown to the applicant.

**Fixed:** transitions enforced server-side; reviewer comment mandatory on
return; cannot mark complete while blocking items remain; every transition
audited; the completeness review creates no accreditation decision.

## 3. Workflow: Inspection Scheduling & Checklist (this milestone)

> Scope boundary: this milestone covers **scheduling** an inspection and
> recording a **checklist-based inspection report**. It deliberately does **not**
> implement findings, corrective actions, or accreditation decisions. Those are a
> separate, future module (see §4). The inspection only records evidence and
> results for later review; it never translates a non-compliant criterion into an
> automatic accreditation rejection.

Actors:

- **PSP Administrator / CART Reviewer** — create, schedule, assign inspectors,
  reschedule, cancel, return for revision, and complete inspections.
- **Assigned Inspector** (LEAD_INSPECTOR / INSPECTOR / OBSERVER) — view and
  conduct their assigned inspection: start it, record criterion results and
  notes, link evidence, save drafts, and submit.
- **Institution users** — view only their own institution's inspection schedule
  and authorized review outputs; never edit inspection criteria.
- **Read-only Auditor** — view history; cannot modify.

Entities: `Inspection`, `InspectionInspector`, `InspectionCriterion`,
`InspectionEvidence`, `InspectionReview`. The inspection lifecycle is driven by
the `InspectionStatus` enum, enforced server-side in `InspectionService` and
`InspectionPolicy`.

`InspectionStatus` transitions (server-enforced):

- `DRAFT → SCHEDULED` (created + eligible + inspectors assigned)
- `SCHEDULED → IN_PROGRESS` (assigned inspector / authorized admin starts)
- `SCHEDULED → RESCHEDULED` (re-schedule, preserves history)
- `SCHEDULED → CANCELLED` (reason required)
- `RESCHEDULED → SCHEDULED`
- `IN_PROGRESS → SUBMITTED_FOR_REVIEW` (every applicable criterion has a result)
- `SUBMITTED_FOR_REVIEW → IN_PROGRESS` (returned by authorized reviewer, comment
  required)
- `SUBMITTED_FOR_REVIEW → COMPLETED` (reviewer summary required)
- `IN_PROGRESS → CANCELLED` (authorized, reason required)
- `COMPLETED` / `CANCELLED` are terminal.

Eligibility (an inspection may be created only when):

- the application is authorized for the actor and belongs to an active program;
- `status === COMPLETE_FOR_INSPECTION`;
- no unresolved blocking completeness items;
- no already-active inspection exists;
- a valid (sectioned) checklist version is frozen on the application.

Excluded (hard-blocked, never via hidden buttons): `DRAFT`, `SUBMITTED`,
`RETURNED_FOR_REVISION`, or any application with blocking completeness items.

**Snapshot rule (historical integrity).** On creation, inside a DB transaction,
the applicable checklist requirements of the frozen `ChecklistVersion` are copied
into `InspectionCriterion` rows — including `section_code`, `section_name`,
`requirement_code`, `title`, `description`, `severity`, `response_type`, and
ordering. `source_requirement_id` is retained for traceability only; the
snapshot columns are the source of truth, so later edits/publishes/retires of the
live requirement never alter the inspection record.

**Checklist result rules (configurable where the checklist architecture allows).**

- Every applicable criterion must have a result before submission.
- `NOT_APPLICABLE` requires an explanation.
- `NONCOMPLIANT`, `PARTIALLY_COMPLIANT`, `NOT_OBSERVED` require inspector notes.
- Incomplete records are distinct from non-compliant criteria (a warning, never
  silently hidden).

Application status integration: on scheduling the application stays
`COMPLETE_FOR_INSPECTION` (no accreditation decision is created). On completion
the inspection result is preserved and an `FINDINGS_ISSUED` extension point is
left for the future findings module; this milestone performs no findings or
corrective-action creation.

Audit logging covers: inspection created, checklist snapshot created, inspector
assigned/removed, scheduled, rescheduled, cancelled, started, criterion result
changed, criterion note changed, evidence linked/unlinked, submitted, returned
for revision, completed. Extension points (events) are stubbed for future
notifications (email/SMS intentionally not implemented).

## 4. Workflow: Inspection Findings & Corrective Actions (FUTURE module — not in this milestone)

> The sections below describe the **future** findings / corrective-action module.
> They are documented for continuity but are **out of scope** for the inspection
> scheduling & checklist milestone and are not implemented yet.

### 4.1 Workflow: Findings & Corrective Action

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
