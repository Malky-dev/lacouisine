# Account Lifecycle API V1 — G2 Design

## Status and Authority

- Gate: `G2_design`;
- decision status: human-approved;
- implementation status: not implemented;
- remaining gate conditions: dependency-security prerequisite and independent
  implementation-readiness review;
- correction loop: `3/3`, awaiting final QA and security review;
- production email provider: deliberately unspecified.

This document is the authoritative G2 architecture, data, migration, and
security design for the product contract in `REQUIREMENTS.md`. It does not
describe currently available behavior.

## Architectural Boundaries

- Each operation uses a dedicated input DTO and a dedicated application service.
- Controllers translate HTTP input and output only; services own validation,
  authorization-sensitive state changes, and transactional boundaries.
- Personal endpoints derive the account exclusively from the authenticated JWT.
  They never accept a target user ID and never reuse administrator voters.
- `/api/v1/me/*` is strictly separated from administrator
  `/api/v1/users/{id}` routes and policies.
- API authentication remains stateless Bearer JWT. API authentication cookies
  are not introduced.
- When the frontend is on another origin, CORS uses an environment-configured
  allowlist. Wildcard origins are not part of this design.
- CSRF protection remains enabled for session-based Symfony web flows. Bearer
  API calls do not use session-CSRF tokens.
- Existing V1 success and error envelopes remain authoritative.

## Endpoint Design Map

| Endpoint | DTO responsibility | Service responsibility | Authentication |
|---|---|---|---|
| `POST /api/v1/auth/register` | username, email, password, terms acceptance | rate limit, generic registration outcome, account creation, dispatch request | public |
| `POST /api/v1/auth/email-verification` | opaque proof | hash lookup, expiry and binding checks, atomic consumption | public |
| `POST /api/v1/me/email-verification` | no target identity | authenticated resend limit and dispatch request | Bearer JWT |
| `PATCH /api/v1/me` | allowed fields and conditional current password | current-password step-up, uniqueness, verification reset, JWT invalidation | Bearer JWT |
| `PATCH /api/v1/me/password` | current and new passwords | current-password step-up, password replacement, JWT invalidation | Bearer JWT |
| `DELETE /api/v1/me` | current password | step-up and reuse of atomic account-deletion behavior | Bearer JWT |

Successful public registration returns a generic `202 Accepted` response. Its
body and observable state must not disclose whether the supplied email already
exists. Registration never returns a JWT or verification proof.

The frontend receives the verification link, extracts the opaque proof, and
submits it in the JSON body of `POST /api/v1/auth/email-verification`. The API
verification endpoint requires neither a web session nor a JWT.

## Verification Challenge Model

The challenge table maintains exactly zero or one row per user. Its core schema
and invariants are:

- `user_id` as both primary key and foreign key with database cascade deletion;
- unique `dispatch_id BINARY(16)` as the non-secret dispatch generation;
- ASCII state `pending` or `issued`;
- nullable unique `proof_hash BINARY(32)`;
- bound normalized email as ASCII with maximum length 254;
- UTC `requested_at`, `issued_at`, `expires_at`, and `delivered_at` as
  microsecond-precision datetimes;
- nullable `delivered_dispatch_id BINARY(16)` identifying the last delivered
  generation;
- indexes on `(state, requested_at)` and `(state, expires_at)`.

Pending rows have null proof, bound email, issue time, and expiry. Issued rows
have all four values and an expiry strictly later than issuance. Every operation
locks the User row before its Challenge row.

The transmitted proof is URL-safe and contains at least 256 bits of
cryptographically secure random entropy. Only its hash is persisted. The
plaintext proof is never returned by the API, serialized into Messenger,
persisted, or logged.

### Dispatch and issuance flow

1. The request or resend service applies the approved rate limit.
2. In one database transaction it creates or updates the user's single
   challenge to a pending-dispatch state with a fresh 128-bit non-secret
   dispatch ID and `requested_at`.
3. After that state is durable, it dispatches a Messenger command containing
   only the immutable user ID and dispatch ID. It contains neither plaintext
   proof nor email address.
4. The worker reloads the user and locks the current challenge. A missing user,
   already verified user, missing challenge, or mismatched dispatch ID is a
   no-op.
5. For a matching current dispatch, the worker generates a 32-byte random proof,
   encodes it as unpadded base64url in memory, stores its binary SHA-256 hash,
   binds it to the current normalized email, and records `issued_at` and a
   60-minute `expires_at` atomically before email delivery. Issuance clears
   `delivered_at` and `delivered_dispatch_id` for that generation.
6. The worker builds the environment-configured frontend link in memory and
   sends the email without logging proof-bearing content. Only after Mailer
   reports success does it record `delivered_at` and the delivered dispatch
   generation, and only when the challenge still carries that dispatch ID.

Delivery is at-least-once, not exactly-once. A retry for the current dispatch may
generate a new proof, replace the previous hash, and send another email; only
the latest proof remains valid. A stale retry cannot replace a newer dispatch
because the locked dispatch ID must match.

The database commit that records pending-dispatch state and the subsequent
Messenger dispatch are not atomic. This design introduces no transactional
outbox and makes no exactly-once claim. A process failure after the commit but
before successful command dispatch can leave pending state without guaranteed
downstream progress. The request service must handle an observed dispatch
exception explicitly and without exposing account state, but it cannot eliminate
the unobservable commit-before-dispatch crash window.

Recovery consists of bounded explicit dispatch-failure handling, authenticated
login and resend, and operational detection of stale pending challenges. Newly
registered unverified users can use this recovery because the approved product
contract permits login before verification. Detection and diagnostics expose
neither identity, email, proof, nor another personal value. Retried or manually
recovered dispatches retain dispatch-ID matching, so a stale command cannot
supersede newer state.

Database commit and external email delivery also cannot form one atomic
transaction. If send or acknowledgement fails, bounded Messenger retries apply.
Exhausted retries leave authenticated resend as the recovery path. No design
claim is made that exactly one command is queued or exactly one email is
delivered.

### Verification consumption

The verification service performs a preliminary binary-hash lookup only to find
a candidate, then starts a transaction and locks User followed by Challenge. It
rechecks the hash, dispatch generation, issued state, binding, and expiry. An
expiry with `now >= expires_at` is invalid. It also confirms that the user is
still unverified.
Marking the user verified and consuming the challenge occur in one transaction.
Concurrent, expired, superseded, altered, mismatched, orphaned, and already
consumed proofs cannot change account state.

## User and JWT Model

### Normalized email

`email_normalized VARCHAR(254)` with ASCII binary collation is the canonical
value used for uniqueness comparisons and proof binding. The final schema
requires `UNIQ_USER_EMAIL_NORMALIZED` and non-null storage. The runtime,
validation, and backfill rule is defined under `Email Canonicalization V1`.

### Authentication version

`auth_version` is a non-negative integer initialized to `0`. New
JWTs identify the account by immutable user ID and carry the current
`auth_version`.

Every authenticated API request reloads the user by immutable ID and rejects the
JWT when its `auth_version` differs from the stored value. `auth_version` is
incremented exactly once in the same transaction as every effective password,
username, or email mutation. No-op username/email, role-only,
verification-only, and algorithm-only password rehash operations do not
increment it because authorization and verification use DB-reloaded state.

At cutover, every legacy JWT containing only a username is rejected. No
compatibility period is approved. This deliberately causes a one-time global
logout and requires all users to authenticate again.

## Profile and Sensitive Operations

An effective username or email change requires the correct `currentPassword`.
The same confirmation remains mandatory for password change and self-deletion.
Possession of a JWT alone is insufficient.

All new passwords, whether supplied during API/web registration, personal
change, or administrative reset, remain untrimmed and contain 12–4096
characters. Symfony `NotCompromisedPassword` applies outside the test
environment. Test deliberately skips only that network/external compromise
check while retaining length, presence, DTO, and every other structural rule.
Current-password inputs remain untrimmed, nonblank, and at most 4096 characters.

For an effective email change, the profile transaction updates the email and its
normalized value, sets the account unverified, increments `auth_version`, and
creates a new pending verification dispatch. The email command is dispatched
only after durable state. An effective username change updates the username and
increments `auth_version` in one transaction. A password change stores the new
hash and increments `auth_version` in one transaction.

Self-deletion reuses the existing account-deletion service boundary: owned
recipes become private, their creator is detached, and the account is removed
in one transaction. Cascade deletion removes the verification challenge. Any
failure rolls back all database effects.

## Abuse and Privacy Controls

- Symfony RateLimiter uses configurable cache-backed storage without
  Doctrine-backed limiter tables or event records.
- Public registration defaults to five attempts per rolling hour per source IP.
- Authenticated resend defaults to one accepted request per 60 seconds and five
  accepted requests per rolling hour per account.
- Thresholds and limiter storage are configured per environment. Tests use
  isolated resettable storage and an injected clock; development uses the
  current single-node local cache. Multi-node production requires a shared
  atomic cache adapter and readiness evidence, but no provider is selected here.
- Limiter keys contain no raw email, username, proof, or other personal value;
  any derived key uses a non-reversible, application-scoped construction.
- Public registration content and timing must not expose account existence.
- Messages, logs, traces, errors, and responses contain no usable proof,
  plaintext password, password hash, email content, JWT key, or private key.
- Rate-limited and duplicate public requests cause no forbidden account or
  credential change.

## Email Worker and Recovery

Messenger handles verification delivery with bounded retries. The worker owns
proof generation and email construction; the API request does not wait for SMTP.
The worker must be operated and monitored in environments using the asynchronous
transport. Test environments intercept messages and email and never send them to
real recipients.

A failed delivery does not roll back a completed registration or profile email
change. Recovery is a later worker retry or the authenticated resend endpoint.
Stale commands are safe no-ops, and a newer dispatch always wins.

A stale pending challenge is detected operationally from non-sensitive age and
state information. This is only a proxy for absent downstream progress; it
cannot distinguish work that was never queued from work that is queued,
backlogged, or in flight. The remedy is a new authenticated resend, not an
invented outbox or reconstruction of the original plaintext proof.

## JWT and Lexik Integration

JWT claims are fixed as follows:

- `sub` is mandatory and is the positive decimal-string representation of the
  persisted `User.id`;
- `auth_version` is mandatory and is an integer greater than or equal to zero;
- `username` is retained for information and emergency rollback only;
- roles remain in the token for compatibility, but authorization uses the
  DB-reloaded User;
- normal `iat` and `exp` claims remain.

Lexik uses `sub` as `user_id_claim`. The existing `app_user_provider` entity
provider keyed by username remains the provider for web authentication and JSON
login. The API firewall keeps `provider: app_user_provider`, with
`json_login.provider: app_user_provider`; `jwt.provider` is the dedicated
`api_jwt_provider` service implementing `PayloadAwareUserProviderInterface`.

On `JWT_CREATED`, the listener requires a persisted User, overwrites `sub` with
its ID, and adds `username` and `auth_version`. The payload-aware JWT provider
strictly validates claim types, loads the User by primary key, compares the
stored version, and returns that reloaded entity. No separate `JWT_DECODED`
database lookup is used.

Missing or malformed `sub` or `auth_version`, a deleted user, a version
mismatch, or a legacy username-only token returns generic `401 INVALID_TOKEN`.
Expiration retains `TOKEN_EXPIRED`; a bad signature uses `INVALID_TOKEN`. The
cutover rejects all legacy tokens and logs every user out once.

Rollback retains the informational username claim and new database columns.
Old code does not enforce `auth_version`; therefore an emergency rollback after
identity or password mutations requires JWT-key rotation or another forced
reauthentication and remains bounded by the configured JWT lifetime.

## HTTP, Errors, and Cache Privacy

The exact success statuses and bodies are defined in `REQUIREMENTS.md`. All six
endpoints require JSON. A safe API-problem interface maps domain and transport
failures without exposing internal exception messages or sensitive values.

- malformed JSON maps to `400 BAD_REQUEST`;
- an invalid, expired, mismatched, consumed, superseded, or unknown proof maps
  to the same `400 INVALID_VERIFICATION_PROOF`;
- wrong current password maps to `403 CURRENT_PASSWORD_INVALID`;
- personal mutation through the actor's own admin target maps to
  `403 SELF_SERVICE_ROUTE_REQUIRED`;
- a body above 16 KiB maps to `413 PAYLOAD_TOO_LARGE`;
- non-JSON content maps to `415 UNSUPPORTED_MEDIA_TYPE`;
- DTO violations map to the existing `422 VALIDATION_ERROR` contract;
- public limiter rejection maps to `429 RATE_LIMITED` with an integer
  `Retry-After` of at least one and no counter, key, or existence hint. For
  login, only exhaustion of the global trusted-client-IP bucket, including the
  corresponding `TooManyLoginAttempts` path, may produce that response.

Login and all six endpoints attach `Cache-Control: no-store`,
`Pragma: no-cache`, and `Referrer-Policy: no-referrer` to successes and errors.

## Administrative Mutation Invariants

`GET /api/v1/users/{id}` may return the actor's own account. Administrative
PATCH or DELETE where actor equals target returns
`SELF_SERVICE_ROUTE_REQUIRED` before mutation. Administrators use `/me` and its
current-password safeguards for their own sensitive operations.

For a different target, effective administrative username, email, and password
mutations increment `auth_version` exactly once transactionally. Effective email
change also sets unverified state, supersedes the current challenge, and
requests delivery. Email change combined with `isVerified=true` is a validation
error. Verification-only mutation invalidates any challenge but does not change
`auth_version`; role-only mutation also leaves it unchanged. Deletion continues
to use the existing transactional deletion service.

## Limiter Matrix and Storage

Limiter keys use HMAC-SHA-256 with a dedicated secret. Raw IP addresses,
usernames, emails, proofs, and JWTs are never limiter keys. Client IP derivation
trusts only explicitly configured proxy CIDRs, never a wildcard.

| Scope | Limit and key | Consumption and response |
|---|---|---|
| login account, internal only | 5 failed attempts per rolling 15 minutes, HMAC of immutable user ID | known-account failures only; lock stays hidden and always returns generic `401`; success resets |
| login global, public | 25 failed attempts per rolling 15 minutes, HMAC of trusted client IP | every failed login; only this bucket produces public `429` and `Retry-After`, starting with the 26th failure |
| registration | 5 attempts per rolling hour, HMAC of trusted client IP | every attempt, including invalid or duplicate; sixth is `429` |
| public proof global | 20 failures per rolling 15 minutes, HMAC of trusted client IP | invalid proofs only |
| public proof subject | 5 failures per rolling 15 minutes, HMAC of immutable user ID | only when proof hash resolves to a candidate; success resets it |
| resend | one accepted issuance per 60 seconds and 5 per rolling hour, HMAC of immutable user ID | accepted issuance only; already verified generic `202` consumes none |
| current password | 5 wrong attempts per rolling 15 minutes, HMAC of immutable user ID | shared by identity, password, and deletion; wrong is `403`, blocked is `429`, success resets |

An unmatched proof uses only the global IP limiter because an opaque proof does
not encode a subject. Every multi-bucket path consumes sequentially before any
domain or database mutation: global/trusted-client-IP first, then the applicable
account bucket. If a later bucket rejects, the process crashes, or a later
database operation fails, earlier consumption is deliberately retained and
never automatically refunded. This is the approved safe-availability tradeoff.
For a public limiter rejection, `Retry-After` reports the applicable blocking
time; the internal login account bucket never produces that header or a public
rejection. Storage errors fail closed unless security explicitly approves
another mode. Values remain configurable. Each bucket is atomic only to the
extent guaranteed by its configured cache adapter.

Every failed login consumes the trusted-client-IP global bucket first. Only
after credentials resolve a known account does the internal account bucket
consume using an HMAC of immutable user ID. An unknown username consumes only
the IP bucket. Known-account failures consume both in that order. A successful
known-account login resets its internal account bucket.

Unknown usernames always run an equivalent constant-cost dummy password
verification. When the internal account bucket is locked, the request also runs
that dummy verification instead of revealing lock state. Known-account wrong
credentials before and after the fifth account failure and unknown usernames
return the exact existing generic `401 INVALID_CREDENTIALS` status, body, and
headers, without `Retry-After`, until the global bucket blocks. Timing
distributions remain bounded and externally indistinguishable. The internal
count, remaining quota, retry time, lock state, and account existence never
appear in caller-visible bodies, headers, logs, or metrics. The design makes no
claim that username canonicalization matches database collation.

Login-failure logging does not include internal user ID, account-bucket scope,
count, lock state, or retry time and does not distinguish known from unknown
username. `rate_limit.exceeded` is emitted for the global public login block, not
for the hidden internal account lock.

There are no Doctrine-backed limiter tables or event records. Tests use an
injected clock and isolated resettable cache. Development may use local
single-node cache. Any multi-node production topology requires a proven shared
atomic cache adapter before release; this design selects no provider or external
service.

## CORS and Bearer Boundary

CORS is handled by a native subscriber with no new dependency. Allowed origins
are exact environment-configured scheme, host, and port tuples. Wildcard and
`null` origins are forbidden.

- methods: `GET`, `POST`, `PATCH`, `DELETE`, `OPTIONS`;
- request headers: `Authorization`, `Content-Type`, `Accept` only;
- exposed response header: `Retry-After`;
- credentials are false and `Access-Control-Allow-Credentials` is omitted;
- preflight max age is 600 seconds;
- an allowed preflight returns `204`, echoes the exact origin, and varies on
  `Origin`, `Access-Control-Request-Method`, and
  `Access-Control-Request-Headers`;
- any request carrying a disallowed origin returns `403` without an
  allow-origin header;
- actual successes and errors for an allowed origin echo that origin;
- requests without `Origin`, including CLI and native clients, remain allowed.

`OPTIONS /api/v1` is public before authentication. JWTs are accepted only from
the Bearer authorization header; query-string and cookie JWTs are disabled.

## DTO and Validation Contract

All DTO deserialization disables extra attributes. Unknown fields are
validation errors. Username and email strings are trimmed; passwords are never
trimmed. Validation violations never echo sensitive submitted values.

- registration: username length 3–180, valid email maximum 255, untrimmed
  password length 12–4096, terms exactly true, and
  `NotCompromisedPassword` outside test;
- public proof: required, maximum 2048, compact URL-safe syntax;
- resend: absent body or empty JSON object only;
- profile: presence-aware optional username/email, explicit null invalid, at
  least one present, and `currentPassword` conditional on an effective change;
- password change: untrimmed nonblank current password maximum 4096 and
  untrimmed new password length 12–4096 with `NotCompromisedPassword` outside
  test;
- deletion: nonblank current password maximum 4096.

Administrative reset input uses the same untrimmed 12–4096 new-password policy
and environment-specific compromise check. Test skips no structural constraint.

## Email Canonicalization V1

One immutable `CanonicalEmail` value and one `EmailCanonicalizer` are shared by
API and web registration, `/me`, administration, proof binding, validation, and
backfill. There is no independent normalized-email setter.

The algorithm is exact:

1. reject malformed UTF-8;
2. normalize to NFC;
3. trim Unicode White_Space only from both ends;
4. reject control or format characters and any internal whitespace;
5. require exactly one `@` and nonempty local and domain parts;
6. require an ASCII dot-atom local part of at most 64 bytes and lowercase it,
   making local parts case-insensitive by product decision;
7. convert the domain using IDNA UTS-46 non-transitional processing with STD3,
   BIDI, and CONTEXTJ checks;
8. reject a trailing dot, empty label, label above 63 bytes, or IDNA error;
9. lowercase the ASCII domain and require the complete canonical address to be
   at most 254 bytes.

Provider-specific dot or plus aliasing is forbidden. Unicode local parts and
SMTPUTF8 are unsupported; Unicode domains are accepted only through IDNA. The
presentation `User.email` remains NFC/trimmed `VARCHAR(255)`. Database uniqueness
on ASCII binary `email_normalized` is the concurrency authority; SQL `LOWER()` is
never used. A pre-flush invariant guard rejects divergence.

The web form uses an unmapped input/DTO and the same registration service so web
and API cannot bypass canonicalization or uniqueness.

## Structured Observability

Approved log fields are correlation ID, internal user ID, dispatch generation,
event name, limiter scope, retry-after, exception class, and worker ID. Bodies,
passwords, password hashes, proofs, signatures, raw email, username, IP,
authorization header, JWT, URL, rendered email, and secrets are forbidden.

`ApiExceptionSubscriber` may log only exception class, correlation ID, and those
previously approved scalar fields. It never passes, serializes, stores, or logs a
Throwable object, request body or payload, authentication-bearing headers, or
sensitive exception/request context.

Only these structured event names are approved:

- `account.registration.accepted`;
- `email_verification.issued`;
- `email_verification.delivered`;
- `email_verification.delivery_failed`;
- `email_verification.completed`;
- `account.identity_changed`;
- `account.password_changed`;
- `account.self_deleted`;
- `rate_limit.exceeded`;
- `authentication.login_failed`;
- `email_verification.rejected`;
- `account.current_password_rejected`.

## Worker Operations and Proof Frontend

The initial topology is one supervised worker process:

```powershell
sf messenger:consume async --time-limit=3600 --memory-limit=128M --failure-limit=3 --sleep=1 --no-interaction
```

Existing retry policy remains three retries with multiplier two, followed by the
failed transport. DevOps owns supervision and restart, SRE owns health and
alerts, and a maintainer owns authorized retry. Failed-message inspection and
retry must use safe commands documented in the delivery plan; destructive
failed-message removal remains approval-gated.

Challenge health maps the current generation to `dispatch_id`, last issuance to
`issued_at`, and last delivery to `delivered_at` plus delivered generation. It
aggregates exactly three sources: stale pending challenge older than threshold,
issued challenge with null `delivered_at` older than threshold, and a matching
`SendVerificationEmail` item in the failed transport. Pending age is only a
proxy signal and cannot distinguish work that was never queued from work that is
queued, backlogged, or in flight. No persisted dispatch marker, flag, timestamp,
or outbox is introduced. Run every five minutes:

```powershell
sf app:email-verification:health --warning-age=300 --critical-age=900 --format=json
```

Output contains aggregate counts and oldest ages only. Exit code `0` is healthy,
`1` indicates either challenge age above five minutes, and `2` indicates either
challenge age above fifteen minutes or a matching failed message. It exposes no
identity, message payload, proof, email, or secret.

The email link is:

```text
{FRONTEND_BASE_URL}/verify-email#proof=<urlencoded-proof>
```

The frontend reads the fragment only in memory, immediately calls
`history.replaceState` to remove it, submits the proof as JSON, and discards it.
It never stores or sends it to logs or analytics. The page and API use
`Referrer-Policy: no-referrer`; the API also uses `Cache-Control: no-store` and
`Pragma: no-cache`.

## Challenge Retention

Successful verification marks the account verified and deletes the challenge in
the same transaction; exact proof deletion is therefore safe. Expired challenges
are purged in batches of 500. Stale pending rows are retained through the retry
horizon, then may be purged after 24 hours. Issued rows expired for more than 24
hours generate operational alert evidence before purge. Legacy proofs are
invalid at cutover.

## Mutation and Migration Design

User state changes use intention-revealing methods that apply the shared
canonicalizer and maintain `auth_version` invariants across API, web, and
administrative paths. Effective username, email, and password mutations
increment the version exactly once within their state transaction. No-op
identity input, verification-only changes, role-only changes, and an
algorithm-only `upgradePassword` rehash do not increment it. The version is
never decremented.

Migration A expands the schema with nullable `email_normalized`, non-null
`auth_version` defaulting to zero, and an empty challenge table. During
compatibility deployment, every identity write dual-writes the canonical value
while new account-lifecycle routes remain disabled.

Backfill uses the exact runtime canonicalizer:

```powershell
sf app:accounts:backfill-email-normalized --dry-run --batch-size=500
sf app:accounts:backfill-email-normalized --apply --batch-size=500
```

It processes primary-key keyset batches of 500 with one transaction per batch
and reports only counts and last processed ID. Presentation/normalized
mismatches, invalid addresses, and canonical collisions abort without automatic
remediation or disclosure of original email values.

The contract gate enables registration and identity writes through the shared
service, asserts complete normalized coverage, adds
`UNIQ_USER_EMAIL_NORMALIZED` and `NOT NULL`, reconciles schema and mapping, then
enables routes. Pre-contract rollback may remove unused additive structures only
after evidence that no required data exists. Post-contract rollback first
disables routes and exports the new fields/challenges for handoff; it retains
columns until old code compatibility is established. No automatic down migration
deletes challenge rows, and rollback never decrements `auth_version`.

## Dependency Security Gate

The separate `fix/dependency_security_updates` pull request must reach zero
applicable critical or high advisories across runtime and development/CI. Every
advisory requires an explicit applicability and remediation rationale. Review
must explicitly cover Twig and Symfony runtime, email, and web boundaries plus
PHPUnit and other development/CI packages.

Symfony RateLimiter installation is approved when required, but dependency files
are outside this design-document task. Security and QA independently re-review
the remediation. No feature branch begins before that pull request is merged
into `develop` and its evidence passes.

## Approved Human Decisions

1. Use the opaque, one-time challenge model, `email_normalized`, `auth_version`,
   and no Doctrine limiter table.
2. Change JWT identity to immutable user ID plus `auth_version`, reject all
   username-only JWTs at cutover, and accept the one-time global logout.
3. Require `currentPassword` for every effective username or email change and
   invalidate earlier JWTs afterward.
4. Use asynchronous Messenger email delivery with an operated worker, bounded
   retries, and the approved proof-generation flow.
5. Complete dependency remediation in a separate pull request and add Symfony
   RateLimiter when required. CORS uses the approved native subscriber without
   another dependency.
6. Enforce untrimmed 12–4096-character new passwords and apply
   `NotCompromisedPassword` outside test while skipping only that external check
   in test.

No paid service, production provider, production operation, secret, or
production URL is approved by these decisions.

## Security Invariants

- A proof can verify only its bound user and current normalized email, once,
  within 60 minutes.
- Deleted or recreated accounts cannot be verified by an earlier proof.
- A stale dispatch or proof cannot supersede the current challenge.
- Database uniqueness protects normalized email under concurrency.
- A personal endpoint cannot target another account.
- Administrator voters cannot weaken personal-operation safeguards.
- A changed password, username, or email invalidates all earlier JWTs.
- API cookies and wildcard CORS origins are outside the design.
- Existing session web flows retain CSRF protection.

## Known Limitations and Residual Risks

- At-least-once delivery can produce multiple emails after retry; only the last
  proof works.
- Database state and external email delivery cannot be committed atomically.
- Pending state and Messenger command dispatch are not atomic; a crash between
  them requires stale-state detection and authenticated resend.
- A delivery outage can leave an account unverified until retry or resend.
- The cutover deliberately signs every user out once.
- Public response timing and downstream email effects require independent abuse
  testing to exclude practical enumeration channels.
- Sequential fail-closed limiting can consume earlier quota when a later limiter
  or database step fails; quotas are intentionally not refunded.
- Login protection deliberately hides internal account lock state behind generic
  constant-cost `401` responses; timing equivalence remains a security test
  obligation.
- Worker operation and failed-message recovery add an operational dependency.
- Existing email-normalization collisions may require human-owned remediation
  before the final uniqueness constraint can be installed.
- The production email provider remains undecided.

## Implementation Boundary

Implementation must not begin until the dependency prerequisite and independent
readiness review in `ACCOUNT_LIFECYCLE_G2_DELIVERY_PLAN.md` pass. Any major
dependency upgrade, unapproved CORS component, production provider, production
data access, or new material risk returns to human approval.
