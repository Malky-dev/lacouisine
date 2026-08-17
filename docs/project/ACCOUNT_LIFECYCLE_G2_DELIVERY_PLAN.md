# Account Lifecycle API V1 — G2 Delivery Plan

## Status

- Product contract: approved;
- G2 decisions: approved and documented;
- correction loop: `3/3`, awaiting final QA and security review;
- implementation: not started;
- G3 entry: awaiting dependency remediation and independent readiness review.

This plan orders the approved work without authorizing production work,
dependency installation, Git actions, or feature implementation before its
entry gates pass.

## Branch and Pull Request Order

1. Create `fix/dependency_security_updates` from `develop`.
2. Reproduce a sanitized Composer security audit.
3. Apply compatible dependency updates needed to remove every applicable
   critical or high runtime or development/CI advisory.
4. Independently review security impact and run the complete regression
   baseline.
5. Merge that branch into `develop` through a pull request only after approval.
6. Create `feature/account_lifecycle_api` from the remediated `develop`.

Dependency remediation and feature behavior remain separate for review,
rollback, and regression attribution. A required major upgrade or Symfony
incompatibility stops the work for human approval.

Symfony RateLimiter installation is approved if it is not already a direct
dependency. CORS uses the approved native subscriber; no CORS dependency is
selected or approved.

## Ordered Workstreams

### `SEC-BASELINE-001` — Dependency security prerequisite

**Owner:** implementation engineer.  
**Reviewers:** security engineer, then QA reviewer.

Evidence required:

- sanitized audit before and after remediation;
- exact dependency changes and compatibility notes;
- zero applicable critical or high advisories across runtime and development/CI,
  with an applicability and remediation rationale for every advisory;
- explicit coverage of Twig, Symfony runtime/email/web packages, PHPUnit, and
  other development/CI packages;
- complete web and API regression results;
- rollback to the pre-update lock file demonstrated or documented.

Stop if an upgrade is incompatible, materially expands scope, or leaves an
applicable critical or high advisory unresolved.

### `ACC-DATA-001` — Schema and migrations

**Owner:** data engineer.  
**Dependency:** remediated `develop`.

Deliver the exact normalized-email, authentication-version, and challenge schema
from the G2 design. Migration sequence:

1. expand with nullable ASCII-binary `email_normalized`, `auth_version=0`, and an
   empty challenge table;
2. deploy compatibility dual-write through the shared canonicalizer while new
   account routes remain disabled;
3. run `sf app:accounts:backfill-email-normalized --dry-run --batch-size=500`,
   then the same command with `--apply`;
4. use keyset batches of 500 and a transaction per batch, emitting counts and
   last ID only;
5. abort on mismatch, invalid address, or collision without automatic repair;
6. gate all registration and identity writes through the shared service;
7. assert complete coverage, then add `UNIQ_USER_EMAIL_NORMALIZED`, proof and
   dispatch uniqueness, and `NOT NULL`;
8. reconcile mapping/schema and only then enable routes.

No production data is inspected or corrected without human authorization.

Pre-contract rollback may remove unused additive structures only after proving
that they contain no required data. Post-contract rollback first disables routes
and hands off an export of normalized values, versions, and challenge state. It
retains new columns until old-code compatibility is established; no automatic
down migration destroys challenge rows. `auth_version` is never decremented.

### `ACC-AUTH-001` — JWT identity and invalidation

**Owner:** implementation engineer.  
**Dependency:** `ACC-DATA-001`.

Implement Lexik `user_id_claim: sub`, the dedicated payload-aware API JWT
provider, exact claim validation, DB reload by primary key, version comparison,
exactly-once transactional increments for effective password/username/email
mutations, and immediate rejection of legacy tokens. Preserve the entity
username provider for web and JSON login. Record the one-time global logout and
the key-rotation/forced-reauth requirement for emergency rollback. Implement the
global-only observable login limiter, hidden account lock, constant-cost dummy
verification, success reset, and indistinguishable failure contract.

### `ACC-VERIFY-001` — Registration and verification

**Owner:** implementation engineer.  
**Dependencies:** `ACC-DATA-001`, direct RateLimiter availability, and the
dependency prerequisite.

Implement dedicated DTOs/services, the exact HTTP/error/cache contract,
privacy-safe cache limiters, pending dispatch, Messenger worker issuance,
fragment-based frontend proof handoff, atomic challenge replacement and
consumption, delivery observability, retention, and bounded recovery. Enforce
untrimmed 12–4096-character new passwords and
`NotCompromisedPassword` outside test while skipping only that external check in
test.

### `ACC-ME-001` — Personal profile and sensitive operations

**Owner:** implementation engineer.  
**Dependencies:** `ACC-AUTH-001`, `ACC-DATA-001`.

Implement current-password step-up and its shared limiter, no-op semantics,
email reverification, exact `auth_version` invariants, password change, and
self-deletion. Apply the approved admin self-service guard and target-mutation
rules, including the shared new-password policy for administrative reset.
Preserve route separation and reuse the existing atomic deletion service.

### `ACC-QA-001` — Independent verification

**Owner:** QA reviewer.  
**Security reviewer:** security engineer for abuse and identity cases.

Execute the matrix below against migrations, application behavior, concurrency,
web compatibility, and rollback. The feature does not pass G4 with an open
critical or high defect.

### `ACC-OPS-001` — Privacy-safe verification operations

**Owners:** implementation engineer for commands, DevOps for worker supervision,
SRE for health/alerts, maintainer for selective retry.  
**Dependency:** `ACC-VERIFY-001`.

Implement the exact aggregate health command and the exact read-only
`sf app:email-verification:failed-messages --format=json` command. Prove all
three health sources, sanitized output, scheduled thresholds, ownership, safe
selective retry, and human approval for destructive removal.

### `ACC-DOC-001` — Implemented behavior and operations

**Owner:** documentation writer.  
**Dependencies:** verified implementation and operational evidence.

Update API usage, environment configuration, worker operation, failed-message
recovery, deployment sequencing, rollback, and release notes from verified
behavior only.

## Transaction and Side-Effect Boundaries

| Operation | Atomic database boundary | External side effect |
|---|---|---|
| registration | account plus pending challenge state | command dispatch, then email |
| resend | current pending dispatch replacement | command dispatch, then email |
| worker issuance | dispatch match plus proof-hash issuance committed before send; delivered fields recorded after successful send | email send outside database transaction |
| verification | user verification plus challenge consumption | none |
| profile username change | username plus `auth_version` increment | none |
| profile email change | email, normalized email, unverified state, `auth_version`, pending dispatch | command dispatch, then email |
| password change | password hash plus `auth_version` increment | none |
| self-deletion | recipe privatization, creator detachment, account and challenge deletion | none |

No documentation or test may claim atomicity between a database commit and
Messenger command dispatch or external email delivery. No transactional outbox
is approved. A crash after pending state commits but before command dispatch can
leave pending state without guaranteed downstream progress. Explicit dispatch
exceptions receive bounded handling; the unobservable crash window is recovered
through operational detection of stale pending state plus authenticated login
and resend. Pending age is only a proxy and cannot identify queue state.
At-least-once handling, dispatch-ID comparison, bounded retry, and resend keep
retries and stale commands safe without an exactly-once claim.

## Validation Matrix

| Area | Required evidence |
|---|---|
| Product contract | all 32 acceptance criteria plus approved profile/JWT/admin/HTTP amendments mapped to tests |
| Registration | generic `202`, no JWT/proof disclosure, terms validation, untrimmed 12–4096 password, compromise check outside test, duplicate-email indistinguishability, five-per-hour IP limit |
| Proof security | at least 256-bit URL-safe proof, hash-only persistence, 60-minute expiry, altered/expired/consumed/superseded/orphaned rejection |
| Challenge data | exact PK/FK, binary dispatch/hash, pending/issued nullability, UTC precision, delivery fields, indexes, User-then-Challenge locks, purge batches and concurrent single success |
| Async dispatch | command has only immutable user ID and non-secret dispatch ID; stale command no-op; retries invalidate older proofs; delivered fields update only after send; no exactly-once claim |
| Dispatch gap | dispatch exception is handled; stale pending age signals absent progress but cannot identify queue state; login and resend recover without exposing identity or proof |
| Concurrency | one active challenge per user, one-time consumption, unique normalized email, only latest dispatch/proof wins |
| Profile/admin | current password required for own effective changes; unchanged input has no side effect; target rules and self-service guard hold; email change reverifies; roles preserved |
| JWT | exact Lexik provider/claims, DB reload, version increments exactly once, no increments for no-op/role/verification/rehash, legacy rejection and rollback control |
| Password | untrimmed nonblank current maximum 4096; untrimmed new 12–4096 and compromise check outside test for personal/admin paths; hash storage and earlier JWT rejection |
| Deletion | incorrect password no-op; recipe privatization, detach, challenge cascade, and account deletion commit or roll back together |
| Authorization | `/me` cannot target another user; admin and super-admin receive no personal-operation bypass; admin routes retain voters |
| HTTP and DTO | exact statuses/bodies/codes/headers, JSON-only, 16 KiB cap, unknown-field rejection and exact constraints |
| Email canonicalization | malformed UTF-8, NFC/trim, forbidden characters, ASCII dot-atom, case folding, IDNA flags, length/label failures, collision authority, web/API/admin/backfill parity |
| Browser boundary | native exact-origin CORS/preflight, Bearer-header only, no API auth cookie, CSRF retained for session web routes |
| Privacy and abuse | sequential IP-before-account consumption, hidden known-user-ID account lock, global-only public login 429, constant-cost dummy verification, partial-consumption and failure cases, no refunds, adapter atomicity, HMAC keys, trusted proxies and fail-closed behavior |
| Logging | captured test sink across success/error/validation/auth/rate-limit/Messenger paths; exact 12 events and scalar allowlist; no sensitive value, body, header, context or Throwable serialization |
| Email failure | bounded retry, failed-message handling, authenticated resend recovery, test interception, no real recipient |
| Operations | supervised worker command, retry/failure transport, all three health sources, exit codes and sanitized output, five-minute schedule, custom safe inspection, selective retry and approval-gated removal |
| Migration | exact canonicalizer, dry-run/apply commands, keyset backfill, collision stop, dual-write, constraints, route gate and pre/post-contract rollback |
| Regression | existing 52 smoke checks, web registration/verification/login/admin journeys, expanded account smoke and automated tests |
| Static validation | `sf cache:clear`, `sf lint:container`, `sf doctrine:schema:validate`, migration status, strict UTF-8, `git diff --check` |
| Dependencies | zero applicable critical/high runtime and development/CI advisories, each with rationale, including Twig, Symfony boundaries and PHPUnit |

Tests that create accounts, challenges, messages, or recipes must clean all
temporary data even after a failed assertion. Security evidence must be
sanitized and contain no proof, credential, personal data, production URL, or
private key. Implementation tests must inject an observed dispatch exception and
simulate the commit-before-dispatch crash gap, then demonstrate safe stale-state
detection, authenticated resend recovery, and stale dispatch-ID rejection.

Password tests retain every structural rule in test while skipping only the
external `NotCompromisedPassword` lookup. Limiter tests use injected time and
cover sequential global/IP-before-account consumption, unknown versus known
login identity, later-bucket rejection, process/storage failure, database
failure after consumption, retained partial quota, no refund, and fail-closed
behavior.

Login QA compares every request across known-wrong, unknown, username casing and
equivalent input variants, before and after the fifth internal account failure
and through the 25th/26th global-IP transition. It compares status, byte-exact
generic error body, response headers, absence of `Retry-After` before the global
block, and bounded timing distributions. Tests assert constant-cost dummy
password verification for unknown and internally locked paths, hidden internal
bucket state, global-only `429`, and internal-bucket reset after successful
known-account authentication. Captured logs and metrics must not distinguish
known, unknown, or internally locked login attempts or expose internal account
scope, count, lock state, retry time, or user ID.

A captured test log sink must inspect success, error, validation,
authentication, rate-limit, and Messenger paths. It asserts absence of
plaintext password, password hash, proof, raw email, username, IP,
Authorization/JWT, verification URL, rendered email, secret, request body, and
serialized Throwable or sensitive exception/request context.

## Failure Recovery and Rollback

### Dependency remediation

Revert the dedicated dependency pull request or restore the reviewed lock file
only through the normal Git process, then rerun the complete baseline. Never mix
this rollback with feature migrations.

### Migration collision

Abort before adding the unique or non-null constraint. Produce only aggregate,
sanitized evidence. Data correction requires a separately authorized procedure.

### Worker or email outage

Keep the account or email change durable. Use bounded Messenger retries and
failed-message inspection without exposing proof-bearing content. Restore the
worker and let the user request a new verification dispatch. Do not promise that
only one email was delivered.

### Commit-before-dispatch gap

Handle an observed Messenger dispatch exception through a bounded, explicit
failure path while preserving the generic public response contract. A process
failure after the pending-state commit but before command dispatch may be
unobservable to that request and leaves pending state without guaranteed
downstream progress. Detect stale pending challenges operationally using only
non-sensitive state and age metrics. This proxy cannot distinguish work that was
never queued from work that is queued, backlogged, or in flight. The user can
authenticate while unverified and request a new dispatch. The new dispatch ID
supersedes stale state; delayed commands remain safe no-ops. Do not add a
persisted dispatch marker, outbox, affected identities, or exactly-once claim.

### Worker supervision, health, and failed messages

DevOps owns one initially supervised worker and its restart policy:

```powershell
sf messenger:consume async --time-limit=3600 --memory-limit=128M --failure-limit=3 --sleep=1 --no-interaction
```

The existing retry strategy remains three retries with multiplier two followed
by the failed transport. SRE runs the following health command every five
minutes and owns alerts:

```powershell
sf app:email-verification:health --warning-age=300 --critical-age=900 --format=json
```

The detector covers exactly three sources: stale pending challenge older than
threshold, issued challenge with null `delivered_at`, and matching
`SendVerificationEmail` in the failed transport. Stale pending age is a proxy
that cannot identify whether work was never queued, queued, backlogged, or in
flight; no persisted dispatch marker is added. Its output contains aggregate
counts and oldest ages only.
Exit `0` is healthy; exit `1` means a challenge source is older than five
minutes; exit `2` means a challenge source is older than fifteen minutes or a
matching failed message.

A dedicated implementation workstream must provide this read-only safe command:

```powershell
sf app:email-verification:failed-messages --format=json
```

It filters only `SendVerificationEmail` and returns only transport message IDs,
counts, and ages. It never emits payload, serialized message, exception,
user/account identity, email, proof, or secret. The maintainer selects an ID from
that safe output, fixes SMTP or configuration, then retries exactly:

```powershell
sf messenger:failed:retry <id> --transport=failed --force
```

Stale retries are safe no-ops because dispatch generation must match. Removing a
failed message is destructive and remains human-approval-gated; no removal
command is prescribed.

### Feature rollback

Disable or revert feature routes and services before reversing their schema.
Because the JWT cutover rejects legacy tokens, rolling back authentication code
requires an explicit compatibility decision; do not silently re-enable old
username-only tokens. Preserve data until the previous application version can
read it or an approved export/backup exists.

### Self-deletion failure

Rely on the single database transaction: account, challenge, recipe visibility,
and creator relationships all remain unchanged when any database step fails.

## Evidence Gates

### G2 completion

- these G2 documents receive independent implementation-readiness approval;
- the dependency-security pull request is merged and independently revalidated;
- no applicable critical or high runtime or development/CI advisory remains,
  and every advisory has an explicit applicability/remediation rationale;
- migration collision handling and rollback are judged executable;
- no unapproved dependency or material design change remains.

### G3 completion

- each implementation workstream supplies changed artifacts and validations;
- migrations apply and roll back in an isolated environment;
- no secret or temporary test residue remains;
- the complete test and smoke baseline passes.

### G4 completion

- QA independently verifies the full matrix;
- security independently verifies identity, replay, enumeration, limiter, log,
  message, and CORS boundaries;
- no unresolved critical or high defect remains;
- residual risks are explicit and assigned.

## Residual Risks

- at-least-once delivery can send multiple emails, of which only the latest
  proof is valid;
- a crash between pending-state commit and command dispatch can require
  operational detection and user-initiated resend;
- database and external email effects are not atomically committed;
- worker outage can prolong the unverified state;
- the JWT cutover causes a one-time global logout;
- normalization collisions can block migration;
- enumeration can remain observable through timing or side effects despite
  generic response bodies;
- sequential fail-closed limiter checks may retain partial quota after later
  rejection, crash, storage failure, or database failure; no automatic refund is
  performed;
- internal login account locks remain deliberately hidden behind constant-cost
  generic `401` responses; bounded timing equivalence requires ongoing security
  validation;
- rollback across the JWT and schema cutovers requires coordinated application
  and database sequencing;
- the production email provider remains a future human decision.

None of these risks authorizes production work or acceptance of a new critical
or high risk.
