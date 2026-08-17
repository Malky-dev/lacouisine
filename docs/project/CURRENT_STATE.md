# La Couisine — Current State

## Delivery State

The API foundation was merged into `develop` through pull request `#1` on
2026-08-17. The merge commit is `0baece7`.

The Account Lifecycle API V1 product contract has completed `G1_product`. Its
G2 architecture, data, migration, security, HTTP, and delivery decisions are
approved and documented, but the feature is not implemented. `G2_design`
remains pending the separate dependency-security prerequisite and independent
final QA and security review of G2 correction `3/3`.

Authoritative planning documents:

- `REQUIREMENTS.md` for the approved product behavior;
- `ACCOUNT_LIFECYCLE_G2_DESIGN.md` for the approved technical design;
- `ACCOUNT_LIFECYCLE_G2_DELIVERY_PLAN.md` for prerequisites, sequencing,
  validation, rollback, and evidence gates.

## Available API Capabilities

Only the capabilities listed below are currently implemented. The proposed
account registration, email verification, profile update, password change, and
self-deletion endpoints are not yet available.

- versioned `/api/v1` JSON contract;
- uniform error responses and field-level validation errors;
- stateless JWT login and authenticated `/me` endpoint;
- public paginated recipe and category reads;
- protected category management for managers;
- recipe creation, partial update, deletion, ownership, and visibility;
- dedicated recipe thumbnail upload and deletion;
- creator listing for owned public and private recipes;
- protected user listing, detail, update, and deletion for administrators;
- hierarchical roles including `ROLE_SUPER_ADMIN`;
- safe user deletion that privatizes and detaches owned recipes.

## Validation Baseline

The last complete API smoke run reported:

```text
Passed : 52
Failed : 0
Skipped: 0
```

The Symfony container lint and Doctrine schema validation also passed before
the merge.

## Existing Validation Commands

User-facing commands must use the local `sf` alias:

```powershell
sf cache:clear
sf lint:container
sf doctrine:schema:validate
sf debug:router
.\tests\scripts\api_smoke_test.ps1 -Username "<username>" -Password "<password>"
```

Never place real credentials in the script defaults or commit them to Git.

## Known Transitional Constraints

- existing Twig/web routes must remain operational during the API migration;
- automated unit and integration tests are planned for a dedicated branch;
- current smoke tests exercise the running local application and may create
  temporary data that must always be cleaned up;
- `.vscode/` is local-only and intentionally untracked;
- API secrets and JWT keys remain in ignored local environment files.

## Approved but Unimplemented Account-Lifecycle Decisions

- opaque, URL-safe, one-time email-verification proofs with at least 256 bits of
  entropy, persisted only as hashes;
- one active verification challenge per account with atomic replacement and
  consumption, 60-minute expiry, and cascade deletion;
- asynchronous email delivery through Messenger without plaintext proofs or
  email addresses in queued commands;
- normalized unique email persistence introduced through an
  expand/backfill/check/contract migration;
- JWT identity based on immutable user ID plus `auth_version`;
- invalidation of existing JWTs after password, username, or email changes;
- rejection of legacy username-only JWTs at cutover, causing a one-time global
  logout;
- current-password confirmation for effective username or email changes;
- Bearer JWT for API authentication, a configured CORS allowlist, and no API
  authentication cookies; CSRF remains enabled for session-based web flows;
- Symfony RateLimiter with privacy-safe keys and no Doctrine-backed limiter
  table or event record;
- strict separation between personal `/api/v1/me/*` behavior and administrator
  `/api/v1/users/{id}` routes and voters;
- a separate `fix/dependency_security_updates` pull request must remove every
  applicable critical or high runtime or development/CI advisory before feature
  implementation, with an explicit applicability and remediation rationale for
  every advisory;
- email uniqueness uses the approved V1 canonicalization algorithm, not SQL
  `LOWER()` or provider-specific aliasing;
- the API uses the approved status/body/error/header contract, native exact-origin
  CORS handling, JSON-only DTO validation, and a 16 KiB request-body limit;
- rate limits use privacy-safe HMAC keys and configurable cache-backed Symfony
  RateLimiter storage; multi-node production requires a shared atomic cache
  adapter selected and evidenced at release readiness;
- Messenger health and stale-pending detection are operational requirements;
  no transactional outbox or exactly-once guarantee is approved.
- every new password uses an untrimmed 12–4096-character policy and
  `NotCompromisedPassword` outside test; test skips only that external check;
- limiter consumption is sequential and fail-closed, IP/global before account;
  partial quota consumption is intentionally retained without refunds; only the
  global login IP bucket can expose `429` or `Retry-After`, while the internal
  account lock remains a generic constant-cost `401` path;
- the privacy-safe health detector uses stale pending state as a proxy, plus
  undelivered issued challenges and matching failed messages; it cannot infer
  whether work was never queued, backlogged, or in flight;
- failed-message inspection uses only the custom aggregate command
  `sf app:email-verification:failed-messages --format=json`.
