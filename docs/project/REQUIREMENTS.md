# La Couisine — Next Delivery Brief

## Objective

Deliver the autonomous account lifecycle through API V1 while preserving the
existing Symfony web registration and authentication flows during migration.

## Required Outcomes

The delivery should cover, subject to product and architecture approval:

1. public account registration through the API;
2. email verification compatible with a frontend separated from Symfony;
3. authenticated profile update through `/api/v1/me`;
4. authenticated password change with appropriate identity verification;
5. authenticated self-service account deletion;
6. continued enforcement of recipe privatization and creator detachment when an
   account is deleted;
7. consistent V1 response and error contracts;
8. non-destructive smoke coverage and implementation-ready automated test
   requirements.

## Included Scope

- API DTOs, controllers, services, policies, routes, and error contracts;
- required Doctrine changes and reversible migrations;
- email-verification link and token flow design;
- security controls, rate limiting, and abuse cases;
- compatibility with the current JWT authentication flow;
- operational and developer documentation;
- validation and rollback guidance.

## Excluded Scope

- replacement of the current frontend;
- social login or third-party identity providers;
- production deployment;
- production data manipulation;
- paid external services without explicit human approval;
- broad refactoring unrelated to the account lifecycle.

## Constraints

- minimize changes to the existing frontend;
- keep current web routes functional during the transition;
- do not expose whether an email exists when this creates an account-enumeration
  risk;
- never log or return passwords, verification secrets, JWT secrets, or private
  keys;
- use existing business services rather than duplicating deletion rules;
- preserve backward compatibility unless a breaking change is explicitly
  approved;
- all generated files must be valid UTF-8;
- no secrets or real credentials may be committed.

## Initial Acceptance Criteria

- approved product requirements and API contracts exist before implementation;
- architecture and security decisions are explicit and reviewed independently;
- anonymous, authenticated, expired-token, invalid-token, duplicate-account,
  and deletion scenarios are covered;
- Symfony container lint succeeds;
- Doctrine mapping and schema validation succeed;
- migrations include a safe rollback path where technically possible;
- API smoke tests pass without residual temporary data;
- durable documentation describes setup, verification, failure recovery, and
  remaining risks;
- merging and production actions remain subject to human approval.

## Questions for Gate G0/G1

The fleet must obtain a human decision before implementation when these points
materially affect the design:

- whether login is allowed before email verification;
- verification-link destination and frontend base URL;
- verification-token lifetime and resend policy;
- whether password change requires the current password;
- whether self-deletion is immediate or uses a grace period;
- whether deleted accounts may later reuse the same username or email;
- account-registration and verification rate limits;
- email transport expectations for development, test, and production.
