# La Couisine — Project Context

## Product

La Couisine is a personal Symfony application for managing and sharing cooking
recipes. The current modernization goal is to separate the frontend from the
data layer progressively through a versioned API while preserving the existing
web application during the transition.

## Technology

- PHP 8.2 or newer;
- Symfony 7.3;
- Doctrine ORM and Doctrine Migrations;
- MariaDB/MySQL;
- stateless JWT authentication with LexikJWTAuthenticationBundle;
- VichUploaderBundle for recipe thumbnails;
- PowerShell smoke tests for the current API validation layer.

## Branching Model

- `main` is production and receives only `develop`;
- `develop` is the integration and validation branch;
- work is performed on prefixed branches such as `feature/`, `bugfix/`,
  `fix/`, or `chore/`;
- branch words use `_` as separator;
- work branches are merged into `develop` through pull requests;
- commits use concise English Conventional Commit messages under fifteen words.

## Product Roles

- `ROLE_USER`: authenticated base user;
- `ROLE_CREATOR`: creates and manages owned recipes;
- `ROLE_MANAGER`: inherits creator permissions and manages content except users
  and recipes owned by other creators;
- `ROLE_ADMIN`: inherits manager permissions and administers non-admin users;
- `ROLE_SUPER_ADMIN`: inherits administrator permissions and may administer
  administrators.

## Core Business Rules

- guests may read public recipes and categories;
- recipes are public by default but may be private;
- a recipe may be updated or deleted only by its creator or an administrator;
- deleting a user makes all owned recipes private and detaches their creator;
- administrators cannot modify or delete other administrators;
- administrators may delete their own account;
- only super administrators may administer other administrators.

## Human Owner Preferences

The human owner prefers to control consequential actions. Agents may inspect
the repository and run safe validation autonomously, but must respect the
approval boundaries defined in `AGENTS.md` and the orchestration workflow.
