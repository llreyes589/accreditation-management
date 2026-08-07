# Architecture

> Planning document for the PSP Training Accreditation Management System.
> Source of truth: `PROJECT_CONTEXT.md`. This document describes the _proposed_
> technical architecture. No PSP-specific accreditation rules are hard-coded here;
> where a rule must exist, it is described as **configurable** and flagged as an
> open question.

## 1. Assumptions

- **A1 — Web application.** The deliverable is a "secure web system" (per context).
  No native-mobile or desktop-client requirement is stated for MVP.
- **A2 — Single deployment.** One instance serves all user roles. Multi-tenant
  isolation between institutions is achieved via data scoping (institution_id),
  not separate deployments. _(Confirm.)_
- **A3 — Relational database.** Migrations (per dev rule "use migrations for
  database changes") imply a schema-managed RDBMS (e.g. MySQL). The exact
  engine is a technical decision, not a PSP rule.
- **A4 — Server-rendered or SPA front end.** Not specified. This document
  proposes a thin client against a JSON API; the exact front-end framework is
  left open.
- **A5 — No payment or billing module** is in scope for MVP.
- **A6 — The system is the system of record** for accreditation and resident
  portfolio data; it integrates with external HR/EMR systems only if/when
  integration requirements are confirmed (out of MVP scope unless flagged).
- **A7 — Audit logging is append-only and tamper-evident** (per "important
  actions must be audit logged" and "do not delete historical accreditation
  records"). Audit rows are never updated or deleted.

## 2. Proposed High-Level Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                        Web Client (browser)                   │
│  - Role-aware UI; renders only what the user's role permits   │
└───────────────┬──────────────────────────────────────────────┘
                │ HTTPS (TLS)
┌───────────────▼──────────────────────────────────────────────┐
│                  API / Application Server                      │
│  ┌────────────┐  ┌──────────────┐  ┌───────────────────────┐ │
│  │ Auth & RBAC│  │  Workflow /   │  │   Document / File      │ │
│  │  Service   │  │  Domain Svc  │  │   Storage Service      │ │
│  └────────────┘  └──────────────┘  └───────────────────────┘ │
│  ┌────────────┐  ┌──────────────┐  ┌───────────────────────┐ │
│  │ Audit Log  │  │ Checklist /  │  │   Notification Svc     │ │
│  │  Service   │  │  Config Svc  │  │   (optional MVP)       │ │
│  └────────────┘  └──────────────┘  └───────────────────────┘ │
└───────────────┬──────────────────────────────────────────────┘
                │
┌───────────────▼──────────────────────────────────────────────┐
│   Persistence layer                                          │
│   - Relational DB (migrations-managed)  — structured data    │
│   - Object/File store (documents)        — versioned blobs   │
│   - Audit store (append-only)            — separate, locked  │
└──────────────────────────────────────────────────────────────┘
```

## 3. Layers / Tiers

| Layer                | Responsibility                                                                                                    | Notes                                                                    |
| -------------------- | ----------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------ |
| Client               | Render UI, submit actions                                                                                         | No authorization decisions made client-side; client only hides controls. |
| API gateway / server | Route, authenticate, enforce RBAC, orchestrate services                                                           | All authorization enforced here, server-side.                            |
| Domain services      | Institution, Program, Application, Inspection, Finding, CorrectiveAction, Decision, Resident, Checklist, Document | Stateless business logic.                                                |
| Config service       | Serve the configurable compliance checklist and other editable rules                                              | Backed by DB, editable by authorized roles.                              |
| Audit service        | Record every important action immutably                                                                           | Writes only.                                                             |
| Storage              | Versioned document blobs + metadata                                                                               | De-identified patient data handling at write time.                       |
| Persistence          | Relational schema (migrations) + append-only audit + file store                                                   |                                                                          |

## 4. Configurable Business Rules vs Fixed Technical Behavior

**Configurable (data-driven, editable, NOT hard-coded):**

- Compliance checklist items, weights, and pass/fail thresholds
  (context: "checklist requirements must be configurable").
- Which roles may perform which workflow transitions (role-permission matrix).
- Document retention / versioning policy parameters.
- Accreditation validity period and renewal timing.
- Severity taxonomy for findings (e.g. minor/major/critical labels).

**Fixed technical behavior (engineered once, not configurable):**

- Authentication, password hashing, session/token management.
- Server-side RBAC enforcement (the _mechanism_; the _assignments_ are configurable).
- Append-only audit logging of important actions.
- Document versioning mechanics (every overwrite creates a new version).
- Prohibition on deleting historical accreditation records.
- De-identification requirement being enforced at the data layer.

## 5. Technology Decisions (proposed, not mandated)

| Concern              | Proposed                                                                   | Status             |
| -------------------- | -------------------------------------------------------------------------- | ------------------ |
| Language / framework | Not yet selected                                                           | **Open**           |
| Database             | PostgreSQL (migration-managed)                                             | Proposal; **open** |
| Auth                 | Hashed credentials + server-side sessions or JWT; MFA for privileged roles | Proposal; **open** |
| File storage         | Local/object store with versioned keys                                     | Proposal; **open** |
| Audit store          | Same RDBMS, dedicated append-only table with no UPDATE/DELETE path         | Proposal           |

## 6. Unresolved Questions

- **Q1.** Which application framework/language stack should be used?
- **Q2.** Is multi-institution data isolation a hard compliance boundary
  requiring separate schemas/tenants, or is row-level scoping sufficient?
- **Q3.** Will the system need to integrate with external systems (HR, EMR,
  national registry) in MVP or later?
- **Q4.** Do documents need virus scanning / file-type allow-listing at upload?
- **Q5.** Is there a required retention period for accreditation and resident
  records (legal/compliance-driven)?
- **Q6.** Should the audit log be cryptographically hashed (chain) for
  tamper-evidence, or is append-only access control sufficient?

## 7. Out of Scope (for MVP)

- Billing / fees.
- Public-facing reporting portals.
- External system integrations beyond what MVP workflows require.
- Advanced analytics / dashboards (basic views only).
