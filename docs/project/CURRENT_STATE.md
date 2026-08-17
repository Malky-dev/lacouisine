# La Couisine — Current State

## Delivery State

The API foundation was merged into `develop` through pull request `#1` on
2026-08-17. The merge commit is `0baece7`.

## Available API Capabilities

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
