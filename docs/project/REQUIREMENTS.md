# Account Lifecycle API V1 — Approved Product Requirements

## Document Status

- Product gate: `G1_product` approved;
- delivery status: approved contract, not yet implemented;
- API version: V1;
- G2 design: approved, pending its dependency-security prerequisite and an
  independent implementation-readiness review;
- production email provider: deferred human decision.
- G2 correction: `3/3`, awaiting final independent QA and security review.

This document is the authoritative product contract for the Account Lifecycle
API V1 increment. The approved implementation design is defined separately in
`ACCOUNT_LIFECYCLE_G2_DESIGN.md`.

## Objective

Deliver a complete autonomous account lifecycle through API V1 while preserving
the existing Symfony web registration and authentication flows during the
progressive separation of the frontend and data layers.

## Target Users

- an anonymous visitor creating an account;
- an authenticated user whose email address is not yet verified;
- an authenticated user managing their own account;
- an administrator or super administrator using the same personal-account
  capabilities without gaining exceptions to their safeguards;
- a separated frontend consuming the V1 JSON contract.

## Included Scope

- the six account-lifecycle API endpoints defined in this document;
- API DTOs, controllers, services, policies, routes, and V1 error contracts;
- required Doctrine changes and reversible migrations;
- the email-verification link and proof flow;
- security controls, rate limiting, and abuse cases;
- compatibility with the current stateless JWT authentication flow;
- non-destructive smoke coverage and implementation-ready automated test
  requirements;
- operational and developer documentation;
- validation, failure-recovery, and rollback guidance.

## Excluded Scope

- replacement or redesign of the current frontend;
- social login or third-party identity providers;
- forgotten-password recovery;
- account creation by administrators;
- a new account-administration interface;
- legal retention or historical evidence of terms acceptance;
- verification-proof mechanisms other than the approved G2 design;
- selection of a production email provider;
- production deployment or production data manipulation;
- paid external services without explicit human approval;
- broad refactoring unrelated to the account lifecycle.

## Constraints

- minimize changes to the existing frontend;
- keep current web routes functional during the transition;
- preserve backward compatibility unless a breaking change is explicitly
  approved;
- preserve the existing V1 success and error envelope conventions;
- do not disclose whether an email address already belongs to an account;
- never log or return plaintext passwords, password hashes, verification
  secrets, JWT secrets, private keys, or email contents containing a usable
  verification proof;
- require every new password to contain 12–4096 characters without trimming;
  outside the test environment, reject passwords reported compromised by
  Symfony `NotCompromisedPassword`; tests skip only that external compromise
  check and retain every structural validation;
- keep every current-password confirmation untrimmed, nonblank, and at most
  4096 characters;
- derive personal-account resources from the authenticated identity rather than
  a client-supplied user identifier;
- reuse existing business services instead of duplicating account-deletion
  rules;
- make rate-limit values configurable by environment;
- keep development, test, and production email transports independently
  configurable;
- all generated files must be valid UTF-8;
- no secret, real credential, personal data, or production URL may be committed.

## Approved Business Rules

1. The increment exposes exactly the six account-lifecycle endpoints specified
   under `Endpoint Contracts`.
2. Public registration accepts `username`, `email`, `password`, and
   `termsAccepted`.
3. Registration succeeds only when `termsAccepted` is explicitly `true`;
   acceptance is required but is not historically retained by this increment.
4. A newly created account is unverified and has only the base `ROLE_USER`
   permission; clients cannot submit roles or a verification status.
5. Registration does not issue a JWT automatically.
6. An unverified user may use the existing login flow; account representations
   continue to expose `verified=false` until verification succeeds.
7. Public registration responses do not reveal whether the submitted email
   address is already registered.
8. A registration request for an existing email does not modify the existing
   account or its credentials.
9. When a registration request is eligible to create an account, the system
   initiates delivery of an email-verification link.
10. Verification links target a frontend route configured independently for
    each environment; no production URL is fixed by this contract.
11. A verification proof remains valid for 60 minutes from issuance.
12. A valid proof can be submitted without a Symfony session or JWT and verifies
    exactly the account for which it was issued.
13. Expired, altered, mismatched, previously consumed, or superseded proofs do
    not change any account; a proof for a deleted account cannot verify a later
    account that reuses the same identifiers.
14. An authenticated unverified user may request a new verification email; the
    response is generic and safe to repeat.
15. Verification-email resend is limited to one accepted request every 60
    seconds and five accepted requests per rolling hour for the relevant
    account.
16. Public registration is limited to five attempts per rolling hour for the
    originating IP address.
17. Rate-limit thresholds and proof lifetime are environment-configurable while
    retaining the approved defaults in this document.
18. Personal profile update accepts only `username`, `email`, and
    `currentPassword`; omitted profile fields remain unchanged, and the correct
    current password is required for every effective username or email change.
19. Submitting profile values identical to the stored values produces no
    verification-state or email-delivery side effect.
20. An effective email change immediately marks the account unverified and
    initiates delivery of a new verification link to the new address. Every
    effective username or email change invalidates all previously issued JWTs.
21. Personal profile update cannot change roles, verification status, password,
    account identity, or administrative fields.
22. Password change requires the correct current password and a valid new
    password; it never accepts a target user identifier.
23. Self-deletion requires the correct current password and deletes the account
    immediately, without a grace period; the operation applies equally to
    ordinary users, administrators, and super administrators acting on
    themselves.
24. Self-deletion atomically makes every owned recipe private, detaches its
    creator, and deletes the account; after success, its username and email may
    be reused.

## MoSCoW Priorities

### Must

- deliver all six endpoint contracts and all approved business rules;
- prevent public account enumeration through response content and observable
  state changes;
- enforce the approved verification lifetime and abuse limits with values
  configurable by environment;
- require the current password for effective username or email changes,
  password change, and self-deletion;
- invalidate previously issued JWTs after an effective username, email, or
  password change;
- preserve atomic recipe privatization and creator detachment on deletion;
- preserve the existing web flows and V1 response conventions;
- provide automated coverage for anonymous, authenticated, invalid, expired,
  duplicated, rate-limited, and deleted-account scenarios;
- validate the Symfony container, Doctrine mapping, schema, migrations, UTF-8,
  and temporary-data cleanup.

### Should

- make verification resend responses generic, idempotent from the client's
  perspective, and safe to retry;
- document proof lifetime, rate limits, email transport configuration, and
  recovery after an email-delivery failure;
- preserve the established baseline of 52 passing API smoke checks while adding
  the new account-lifecycle coverage.

### Could

- no optional behavior is approved for the MVP increment.

### Won't

- forgotten-password recovery;
- social login or third-party identity providers;
- account creation by an administrator or a new administration interface;
- legal retention of terms-acceptance history;
- selection of a production email provider;
- frontend redesign or replacement.

## Endpoint Contracts

All endpoints use JSON and the existing API V1 envelope conventions. The
approved proof, persistence, authentication, and security design is defined in
`ACCOUNT_LIFECYCLE_G2_DESIGN.md`.

### `POST /api/v1/auth/register`

**Authentication:** public.

**Input:** `username`, `email`, `password`, and `termsAccepted=true`. Roles,
verification status, and user identifiers are forbidden account-creation
inputs.

**Behavior:** creates one unverified `ROLE_USER` account when the username and
email are available and the input is valid, hashes the password, and initiates
verification-email delivery. It does not authenticate the caller or issue a
JWT. The public response must not reveal whether the email already existed.
Terms refusal, invalid input, username conflict, and rate limiting follow the V1
error contract without exposing sensitive values. The submitted password is
never trimmed and must satisfy the approved new-password policy.

### `POST /api/v1/auth/email-verification`

**Authentication:** public; no Symfony session or JWT is required.

**Input:** the opaque, URL-safe verification proof supplied to the frontend by
the verification link. Its lifecycle and security invariants are defined in
`ACCOUNT_LIFECYCLE_G2_DESIGN.md`.

**Behavior:** verifies exactly the account bound to a valid, unexpired proof.
Expired, altered, mismatched, consumed, superseded, or orphaned proofs leave all
accounts unchanged and return a V1 error. Repeating a successful proof cannot
verify another account or apply an additional state change.

### `POST /api/v1/me/email-verification`

**Authentication:** required; the target is the authenticated account.

**Input:** no target user identifier. No email address is required because the
current account supplies the destination.

**Behavior:** for an unverified account, requests a fresh verification email to
the stored address. The operation has a generic retry-safe response, enforces a
60-second interval and five accepted resends per rolling hour, and does not
disclose email-delivery internals. Requests from an already verified account do
not alter account state.

### `PATCH /api/v1/me`

**Authentication:** required; the target is the authenticated account.

**Input:** at least one of `username` or `email`, plus `currentPassword` whenever
the submitted values produce an effective change. Roles, verification state,
new password, and user identifiers are forbidden.

**Behavior:** updates only supplied allowed fields after validation. Unchanged
values have no side effect and require no password confirmation. An effective
username or email change requires the correct current password and invalidates
all previously issued JWTs. An effective email change also sets
`verified=false` and initiates verification delivery to the new address.
Existing roles and all other account fields remain unchanged. Conflicts follow
the V1 error contract without exposing another account's data.

### `PATCH /api/v1/me/password`

**Authentication:** required; the target is the authenticated account.

**Input:** `currentPassword` and `newPassword`.

**Behavior:** replaces the stored password only when the current password is
correct and the untrimmed new password contains 12–4096 characters and satisfies
the approved compromise check outside test. Success
invalidates all previously issued JWTs. Failure leaves the password unchanged
and never returns either password or its hash.

### `DELETE /api/v1/me`

**Authentication:** required; the target is the authenticated account.

**Input:** `currentPassword` and no target user identifier.

**Behavior:** after current-password verification, immediately and atomically
makes owned recipes private, detaches their creator, and deletes the account.
Failure rolls back the complete operation. The rule applies when the caller is
an administrator or super administrator deleting their own account. After
success, the former username and email may be registered again, but an old
verification proof cannot affect the new account.

## Approved G2 HTTP Contract

Successful responses are fixed as follows:

| Endpoint | Status and body |
|---|---|
| `POST /api/v1/auth/register` | `202` with `{"data":{"message":"If the request can be completed, verification instructions will be sent."}}` |
| `POST /api/v1/auth/email-verification` | `200` with `{"data":{"verified":true}}` |
| `POST /api/v1/me/email-verification` | `202` with `{"data":{"message":"If verification is required, verification instructions will be sent."}}` |
| `PATCH /api/v1/me` | `200` with `data.id`, `data.username`, `data.email`, `data.roles`, and `data.verified` |
| `PATCH /api/v1/me/password` | `204` with an empty body |
| `DELETE /api/v1/me` | `204` with an empty body |

Registration returns the same status, body, and cache/privacy headers for a new
or existing email and never returns an account ID, JWT, or proof.

The approved V1 errors for this increment are:

- `400 BAD_REQUEST`: `The request is invalid.`;
- `400 INVALID_VERIFICATION_PROOF`: `The verification proof is invalid or expired.`;
- existing `401` authentication codes, including generic `INVALID_TOKEN`;
- `403 CURRENT_PASSWORD_INVALID`: `The current password is invalid.`;
- `403 SELF_SERVICE_ROUTE_REQUIRED`: `Use the personal account endpoint for this operation.`;
- existing `409 CONFLICT`;
- `413 PAYLOAD_TOO_LARGE`: `The request payload is too large.`;
- `415 UNSUPPORTED_MEDIA_TYPE`: `The content type is not supported.`;
- existing `422 VALIDATION_ERROR` with field violations;
- `429 RATE_LIMITED`: `Too many requests.`, with integer `Retry-After` of at least one;
- the existing generic `500` contract.

Login and all six account-lifecycle endpoints, including their error responses,
use `Cache-Control: no-store`, `Pragma: no-cache`, and
`Referrer-Policy: no-referrer`. Error bodies and rate-limit headers disclose no
account existence, limiter key, count, identity, credential, or proof.

## Approved Administrative Amendment

Administrative `GET` of the actor's own account remains allowed. Administrative
`PATCH` or `DELETE /api/v1/users/{id}` where actor and target are the same returns
`403 SELF_SERVICE_ROUTE_REQUIRED` before mutation; administrators use the
personal endpoints and their current-password safeguards instead.

Administrative target mutations share the same state invariants as personal
mutations: every effective username, email, or password change increments
`auth_version` exactly once and invalidates prior JWTs. An effective email change
also marks the target unverified, supersedes its challenge, and enqueues
verification. Supplying an email change with `isVerified=true` in the same
request returns `422 VALIDATION_ERROR`. Verification-only and role-only changes
do not increment `auth_version`; verification-only changes invalidate the
current verification challenge. The existing atomic deletion service remains
unchanged. Any administrative password reset uses the same untrimmed 12–4096
new-password policy and compromise check as registration and personal password
change.

## Approved Limiter Consumption Semantics

Limiter buckets are consumed sequentially before any domain or database
mutation and fail closed on storage error. Where a flow defines both global/IP
and account buckets, the global/trusted-client-IP bucket is first and the
account-specific bucket follows only when a candidate account is known. If the
later bucket rejects, the process crashes, or the later database operation
fails, earlier quota consumption is retained and never automatically refunded.
This is an approved security-over-availability tradeoff.

For login, every failed attempt consumes the trusted-client-IP global bucket.
It is the only login bucket allowed to produce externally observable
`429 RATE_LIMITED` or `Retry-After`; the first 25 failed attempts per rolling 15
minutes return the existing generic `401 INVALID_CREDENTIALS`, and the 26th is
globally blocked. For credentials resolving a known account, an internal bucket
keyed through HMAC-SHA-256 from immutable user ID records failures and locks
after five per rolling 15 minutes. Its state is never exposed.

Unknown usernames and requests against an internally locked account both run an
equivalent constant-cost dummy password verification and return exactly the same
generic `401` status, body, and headers until the global IP bucket blocks. Known
wrong credentials before and after internal lock and unknown-user attempts are
externally indistinguishable, including bounded timing and absence of
`Retry-After`. A successful known-account authentication resets its internal
bucket. No public body, header, caller-visible metric, or log reveals internal
count, remaining quota, retry time, lock state, or account existence. The design
does not assume username normalization matches database collation.

## Measurable Acceptance Criteria

1. A valid public registration creates exactly one account with the submitted
   username and email.
2. The created account stores a password hash rather than the submitted
   plaintext password; the untrimmed password contains 12–4096 characters and
   passes `NotCompromisedPassword` outside test, while test skips only that
   external compromise check.
3. The created account returns `ROLE_USER` only and has `verified=false`.
4. A registration request without `termsAccepted=true` creates no account and
   follows the V1 validation-error contract.
5. Registration accepts no client-supplied role, verification status, or user
   identifier.
6. A successful registration response contains no JWT, password, password hash,
   verification proof, or email-delivery secret.
7. Public responses for a new email and an already registered email do not
   disclose which condition occurred.
8. Submitting an already registered email leaves the existing account,
   password, roles, and verification status unchanged.
9. The sixth registration attempt from one IP within a rolling hour is rejected
   or safely suppressed by the configured limit and creates no account.
10. An unverified account can authenticate through the existing login flow and
    `/api/v1/me` reports `verified=false`. Known-wrong, internally locked, and
    unknown-user login failures execute equivalent verification work and remain
    identical generic `401` responses without `Retry-After` through the 25th
    global-IP failure; only the 26th global-IP failure returns public `429`, and
    a successful known-account login resets its hidden account bucket.
11. A valid verification proof submitted within 60 minutes marks exactly its
    bound account verified.
12. Verification succeeds without an existing Symfony session or JWT.
13. An expired verification proof returns a V1 error and changes no account.
14. An altered or account-mismatched proof returns a V1 error and changes no
    account.
15. Reusing a consumed or superseded proof applies no additional state change.
16. A proof issued for a deleted account cannot verify a later account that
    reuses the former username or email.
17. An authenticated unverified user can request a verification resend without
    submitting a user identifier or email address.
18. A second resend request within 60 seconds is rejected or safely suppressed
    and does not initiate another accepted delivery.
19. The sixth resend attempt for one account within a rolling hour is rejected
    or safely suppressed by the configured limit and is not accepted.
20. Registration and resend limit values and the proof lifetime can be changed
    through environment configuration without application-code changes.
21. `PATCH /api/v1/me` changes only the authenticated account's supplied
    `username` and/or `email`, and every effective change requires the correct
    `currentPassword`.
22. Attempting to update roles, verification status, password, account identity,
    or administrative fields through `PATCH /api/v1/me` is rejected and changes
    no forbidden field.
23. Submitting unchanged username and email values requires no password
    confirmation, leaves verification status unchanged, initiates no
    verification delivery, and does not invalidate JWTs.
24. An effective username or email change invalidates all previously issued
    JWTs; an effective email change also sets `verified=false`, preserves all
    roles, and initiates verification delivery to the new address.
25. `PATCH /api/v1/me/password` changes the password only when
    untrimmed `currentPassword` is correct and untrimmed `newPassword` contains
    12–4096 characters and passes the environment-appropriate compromise check;
    success invalidates all previously issued JWTs, while failure leaves the old
    password usable and the new password unusable. Any administrative target
    password reset enforces the same new-password policy and JWT invalidation.
26. `DELETE /api/v1/me` rejects an incorrect current password and leaves the
    account and all owned recipes unchanged.
27. Successful self-deletion removes the authenticated account, makes all its
    recipes private, detaches their creator, and commits those effects in one
    transaction.
28. Any failure during self-deletion rolls back account deletion, recipe
    visibility changes, and creator detachment together.
29. After successful deletion, the former username and email can be used by a
    new registration.
30. Automated account-lifecycle tests intercept or disable outbound email,
    expose no secrets, create no persistent fixture residue, and clean temporary
    data even after a failed assertion.
31. The existing 52-check smoke baseline and the current Symfony web
    registration, verification, login, and account-administration journeys have
    no regression.
32. The complete increment passes Symfony container lint, Doctrine mapping and
    schema validation, migration apply/rollback checks where technically
    possible, the expanded smoke suite, and strict UTF-8 validation.

## Approved Human Decisions

1. **Login before verification:** login remains allowed before email
   verification to preserve the current web journey; the account remains
   visibly unverified.
2. **Verification-link destination:** the link targets a frontend route whose
   base URL is configured per environment; the development URL will be supplied
   when available, and no production URL is approved here.
3. **Lifetime, resend, and limits:** a proof is valid for 60 minutes; accepted
   resends are separated by 60 seconds and limited to five per rolling hour;
   registration is limited to five attempts per rolling hour per IP; values are
   configurable.
4. **Sensitive-operation confirmation and JWT invalidation:** the correct
   current password is required for every effective username or email change,
   password change, or self-deletion; JWT possession alone is insufficient, and
   every effective username, email, or password change invalidates previously
   issued JWTs.
5. **Deletion and reuse:** self-deletion is immediate and has no grace period;
   the former username and email may be reused after deletion.
6. **Email transport:** development uses local SMTP at `localhost:1025`, tests
   use a null or intercepted transport, production uses an environment-specific
   `MAILER_DSN`, and production-provider selection remains deferred for explicit
   human approval.

## Safe Assumptions for G2

- `username` remains the login identifier.
- The approved new-password policy is the authoritative 12–4096-character
  structural baseline; only the external `NotCompromisedPassword` lookup is
  skipped in test.
- Terms acceptance is mandatory but is not historically retained by this
  increment.
- Existing API V1 data and error envelope conventions remain authoritative.
- Every personal endpoint determines its target exclusively from the
  authenticated identity.
- The frontend verification base URL is provided independently in each
  environment.
- Rate-limit keys, storage, proof lifecycle, JWT invalidation, and replay
  protection follow `ACCOUNT_LIFECYCLE_G2_DESIGN.md`.

## Residual Product Risks

- Clients may confuse successful authentication with a verified email address.
- Immediate account deletion is irreversible for the deleted account.
- Identifier reuse makes strict binding and invalidation of old verification
  proofs essential.
- Response timing and email-delivery side effects may create account-enumeration
  channels even when response bodies are generic.
- Login account-lock protection remains safe only while known-wrong,
  internally locked, and unknown-user paths preserve equivalent work, identical
  generic responses, and bounded timing.
- Email-transport failure can leave an account unverified until a successful
  resend.
- Web and API account journeys may diverge over time unless both remain covered
  by regression checks.
- Terms acceptance is required but not retained as legal evidence.
- The approved JWT cutover causes a one-time global logout and requires clients
  to authenticate again.
- Email uniqueness must be enforced against concurrent requests and existing
  data; application-level validation alone may be insufficient.
- Sequential fail-closed limiter checks may consume an earlier quota even when a
  later check, process, or database mutation fails; quotas are not refunded.

## G3 Entry Criteria

G3 may begin only when:

- the separate `fix/dependency_security_updates` pull request is merged into
  `develop` and eliminates every applicable critical or high runtime or
  development/CI advisory, with an explicit applicability/remediation rationale
  for each advisory;
- the dependency result is independently revalidated by security and QA;
- `ACCOUNT_LIFECYCLE_G2_DESIGN.md` and
  `ACCOUNT_LIFECYCLE_G2_DELIVERY_PLAN.md` pass independent
  implementation-readiness review;
- the feature branch starts from the remediated `develop` branch;
- no unapproved dependency, production provider, production operation, or scope
  extension is introduced.
