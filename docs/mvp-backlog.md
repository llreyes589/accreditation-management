# MVP Backlog

> Ordered, granular backlog for the MVP defined in `PROJECT_CONTEXT.md`.
> Each item is tagged: **[Config]** configurable rule, **[Fixed]** technical
> behavior, or **[Open]** blocked on a human decision. Items under an Open
> dependency should not be finalized until the question is answered.

## 1. Foundation (must precede features)

1. **Project scaffold + CI** — repo structure, lint, test runner. [Fixed]
2. **Database + migrations framework** — chosen RDBMS, migration tooling. [Fixed]
   - *Open:* engine choice (see architecture Q1/Q2).
3. **Seed-data framework** — deterministic demo data, no production creds. [Fixed]
4. **Audit-log subsystem** — append-only store + write API. [Fixed]

## 2. Authentication & RBAC

5. **User accounts + login/logout** — hashed passwords, sessions. [Fixed]
6. **Role model + 8 roles seeded** — PSP Admin, CART Reviewer, Inspector,
   Inst Admin, Training Officer, Consultant, Resident, Auditor. [Fixed roles /
   Config matrix]
7. **Permission matrix (configurable)** — role → action/scope. [Config]
8. **Institution scoping** — enforce institution_id on queries. [Fixed]
9. **MFA for privileged roles** — *Open:* which roles. [Fixed mechanism /
   Open scope]
10. **Read-only Auditor role** — view-only across scoped data. [Fixed/Config]

## 3. Institution & Program Profiles

11. **Institution profile CRUD** — name, identifiers, status. [Fixed]
12. **Program profiles** — link to institution; program-type list. [Config list]
13. **Program type configuration** — Anatomic/Clinical/Combined + extensible.
    [Config]

## 4. Accreditation Application & Checklist

14. **Application entity + DRAFT→SUBMITTED lifecycle.** [Fixed]
15. **Configurable checklist engine** — items, categories, weights, thresholds.
    [Config] ← core requirement.
16. **Checklist responses per application.** [Fixed/Config]
17. **Document upload + versioning.** [Fixed mechanic]
18. **Review/triage transitions (CART).** [Config who/return-to-draft]

## 4.5 Completeness Review (administrative & documentary check)

> Implemented. Extends the existing `ApplicationStatus` workflow with
> `UNDER_COMPLETENESS_REVIEW`, `RETURNED_FOR_REVISION`, and
> `COMPLETE_FOR_INSPECTION`. A PSP/CART reviewer performs an administrative and
> documentary check only — it does not determine compliance, eligibility, or an
> accreditation decision, and does not schedule inspection.

19. **Completeness review status transitions (server-enforced).** [Fixed]
20. **Automated completeness calculation (checklist + documents).** [Fixed]
21. **Reviewer dashboard + application completeness detail page.** [Fixed]
22. **Missing-item / deficiency list + reviewer comments.** [Fixed]
23. **Return-for-revision workflow (comment required) + resubmission.** [Fixed]
24. **Mark-complete workflow (blocked while blocking items remain).** [Fixed]
25. **Completeness audit logging + completeness report history.** [Fixed]

## 5. Inspection, Findings, Corrective Actions

19. **Inspection entity + scheduling.** [Fixed]
20. **Findings + configurable severity.** [Config taxonomy]
21. **Corrective actions + status tracking.** [Config vocab/loops]
22. **Inspector verification flow.** [Fixed/Config]

## 6. Decision & Renewal

23. **Decision record + human approval (no auto-decision).** [Fixed requirement]
24. **Authorized-approver enforcement.** [Config matrix]
25. **Outcome vocabulary + validity period.** [Config]
26. **Renewal cycle (lightweight).** [Config timing]

## 7. Resident Profile & Portfolio

27. **Resident profile (institution-scoped).** [Fixed/Config fields]
28. **Portfolio items (versioned).** [Fixed mechanic]
29. **De-identification at write.** [Fixed requirement / Config rules]
30. **Resident self-entry vs Training-Officer-managed.** *Open.*

## 8. Cross-Cutting

31. **Audit logging on all important actions.** [Fixed]
32. **Tests for every workflow (authz + state).** [Fixed dev rule]
33. **Basic role-aware dashboards/views.** [Fixed/Config]
34. **Document/file security (allow-list, scan).** [Config params]

## 9. Explicitly Out of MVP

- Billing/fees.
- External HR/EMR integrations.
- Public reporting portal.
- Advanced analytics.

## 10. Open Questions Blocking Finalization

- Application submitted per Program vs Institution (domain Q1).
- User-role cross-institution scope (domain Q2; security Q2).
- Mandatory on-site inspection? (workflow Q2)
- Authorized decision-maker identity/delegation (workflow Q3).
- Renewal cadence (workflow Q5).
- MFA scope + SSO/IdP (security Q1).
- De-identification ruleset owner (security Q7).
- Record retention periods (security Q4).

## 11. Acceptance Criteria (MVP)

- A user can authenticate and only perform actions permitted by their role.
- An institution can submit an accreditation application with a configurable
  checklist and versioned documents.
- An inspection can record findings and track corrective actions to verification.
- A final accreditation decision requires a recorded human approver and cannot
  be auto-made.
- Resident data is institution-scoped and patient info is de-identified.
- Every important action is immutable in the audit log.
- No historical accreditation record can be deleted.
- All workflows have automated tests.
