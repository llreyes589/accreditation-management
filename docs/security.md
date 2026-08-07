# Security

> Security model for the PSP Training Accreditation Management System.
> Aligned with `PROJECT_CONTEXT.md` principles: role-based access, protected
> resident/clinical info, de-identified patient data, audit logging, no
> production credentials in dev.

## 1. Assumptions

- **A1.** All authorization is enforced **server-side**; the client may hide
  controls but cannot grant access.
- **A2.** The system handles sensitive personal data (residents, trainees) and
  potentially patient-derived content → treat as regulated/private by default.
- **A3.** "Read-only auditor" implies a least-privilege role that can view but
  not mutate.
- **A4.** Security controls below are **fixed technical behavior**; the
  *assignments* of who-gets-which-role are configurable.
- **A5.** Dev/test environments use seed data only; no production credentials
  (per dev rule).

## 2. Authentication

- **Mechanism (fixed):** hashed passwords (bcrypt/argon2), constant-time
  comparison, rate-limited login, session tokens or signed JWTs with short
  expiry + refresh.
- **MFA (proposed):** required for privileged roles (PSP Admin, CART Reviewer,
  approver). **Open** — confirm which roles mandate MFA.
- **Account lifecycle:** lockout after N failed attempts (configurable N);
  admin-initiated disable.
- **Password policy:** minimum length/strength — configurable; recommend
  enforced complexity + expiry.

## 3. Authorization (RBAC)

- Eight roles defined in context: PSP Administrator, CART Reviewer, Inspector,
  Institution Administrator, Training Officer, Consultant, Resident,
  Read-only Auditor.
- **Fixed:** RBAC enforcement engine + the rule "no client-side authz."
- **Configurable:** the permission matrix mapping each role → allowed actions
  and data scopes.
- **Data scoping:** users are scoped to their Institution (except PSP/CART/
  Inspector/auditor who may span institutions — **open**). Enforced via
  institution_id filters on every query.
- **Principle of least privilege:** Resident and Read-only Auditor get minimal
  access; Consultant access is time/scoped (**open** how consultancy access
  is granted).

## 4. Data Protection

- **Resident & clinical information:** access restricted to authorized roles
  within the resident's institution. Encrypted at rest (recommend TDE /
  column-level for sensitive fields).
- **Patient information de-identification (fixed requirement):** any content
  that could contain patient data is de-identified **before** persistence.
  - The *definition* of PHI and the *scrubbing rules* are **configurable/
    confirmed policy**, not hard-coded.
  - A `deidentified_flag` marks compliant records; non-compliant uploads are
    rejected or quarantined.
- **Transport:** TLS 1.2+ for all traffic (fixed).

## 5. Audit Logging (fixed, append-only)

- Every important action appended to `AuditLog` with: actor, action type,
  target entity + id, before/after summary, timestamp, source IP.
- No UPDATE/DELETE paths on audit data.
- Optional hash-chaining for tamper-evidence (**open**).
- Auditor role can read audit log; no role can edit it.

## 6. Document & File Security

- Versioned, immutable document blobs (fixed mechanic).
- File-type allow-list + size limits (configurable; recommend scan/allow-list).
- Access controlled by document ownership + role scope.
- Storage encryption at rest.

## 7. Secure Development Practices

- Migrations for all schema changes (dev rule).
- No production credentials in repo/env; secrets via env/secret manager
  (dev rule).
- Tests for every workflow including authz tests (dev rule).
- Input validation + output encoding to prevent injection.
- Dependency scanning in CI (proposed).

## 8. Configurable vs Fixed

**Configurable:**
- Permission matrix (role → action/scope).
- Password policy parameters, lockout thresholds.
- Which roles require MFA.
- PHI definition + de-identification rules.
- File allow-list / size limits.
- Retention periods.

**Fixed:**
- Auth mechanism, hashing, session handling.
- Server-side RBAC enforcement.
- De-identification requirement (enforced).
- Append-only audit.
- TLS, encryption at rest.
- No-delete on historical accreditation records.

## 9. Unresolved Questions

- **Q1.** Which roles require MFA, and is there an organizational IdP (SSO/SAML/
  OIDC) to integrate with?
- **Q2.** Do consultants get standing access or per-case/time-bound access?
- **Q3.** Is there a data residency / jurisdiction requirement for storage?
- **Q4.** What is the legally required retention period for resident and
  accreditation records?
- **Q5.** Should the audit log be cryptographically chained, or is access
  control sufficient for the threat model?
- **Q6.** Are there mandated encryption standards (e.g. FIPS) to comply with?
- **Q7.** Who defines and approves the de-identification ruleset?
