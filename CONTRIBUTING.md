# Contributing to skipper-php

Thank you for your interest in contributing!

---

## Requirements

- PHP 8.1+
- Composer
- A Google Cloud service account (for integration testing against a real spreadsheet)

## Setup

```bash
git clone https://github.com/get-skipper/skipper-php.git
cd skipper-php
composer install
```

## Running tests

```bash
composer test
```

## Linting

This project uses [PHP CS Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) with PSR-12 + PHP 8.1 migration rules.

```bash
# Check for violations (dry-run)
composer lint

# Auto-fix violations
composer lint:fix
```

Linting must pass before a pull request can be merged.

---

## Commit messages

All commits **must** follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

```
type(scope): short description

[optional body]

[optional footer]
```

### Types

| Type | When to use |
|------|-------------|
| `feat` | A new feature or integration |
| `fix` | A bug fix |
| `docs` | Documentation changes only |
| `refactor` | Code change that neither fixes a bug nor adds a feature |
| `test` | Adding or updating tests |
| `chore` | Build process, dependency updates, tooling |

### Scopes

Use the framework or component name as scope:

| Scope | Applies to |
|-------|------------|
| `core` | `src/Core/` |
| `phpunit` | `src/PHPUnit/` |
| `pest` | `src/Pest/` |
| `behat` | `src/Behat/` |
| `mink` | `src/Mink/` |
| `codeception` | `src/Codeception/` |
| `phpspec` | `src/PHPSpec/` |
| `kahlan` | `src/Kahlan/` |

### Examples

```
feat(phpunit): add support for parallel worker cache sharing
fix(behat): correct test ID format for scenario outlines
docs(pest): document describe() block ID format
refactor(core): extract SheetsClient authentication into separate method
test(core): add edge cases for TestIdHelper::normalize()
chore: update google/apiclient to ^2.16
```

---

## Changelog

This project uses [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format in `CHANGELOG.md`.

Every pull request that changes user-facing behaviour **must** include a `CHANGELOG.md` entry under `[Unreleased]`.

### Categories

| Category | When to use |
|----------|-------------|
| `Added` | New features or env vars |
| `Changed` | Changes to existing behaviour |
| `Deprecated` | Features that will be removed in a future release |
| `Removed` | Features removed in this release |
| `Fixed` | Bug fixes |
| `Security` | Security-related fixes |

### Example entry

```markdown
## [Unreleased]

### Added
- `SKIPPER_FAIL_OPEN` env var: run all tests instead of crashing when the API is unreachable and no cache exists (default: `true`).
```

Releases are cut by a maintainer: the `[Unreleased]` section is renamed to the new version with its release date, and a new empty `[Unreleased]` section is added at the top.

---

## Pull requests

1. Fork the repository and create a branch:
   ```bash
   git checkout -b feat/my-feature
   ```

2. Make your changes. Ensure:
   - `composer test` passes
   - `composer lint` passes
   - New functionality has corresponding unit tests

3. Commit using Conventional Commits format (see above).

4. Open a pull request with a clear title and description.

---

## Project structure

```
src/
├── Core/          # Shared logic: SheetsClient, SkipperResolver, SheetsWriter, etc.
├── PHPUnit/       # PHPUnit 10/11/12 extension
├── Pest/          # Pest v2/v3 plugin (built on PHPUnit integration)
├── Behat/         # Behat 3.x extension + context
├── Mink/          # Mink + Behat context
├── Codeception/   # Codeception 5 extension
├── PHPSpec/       # PHPSpec 7/8 extension + listener
└── Kahlan/        # Kahlan 5 plugin helper
tests/
└── Core/          # Unit tests for Core components
```

Each framework integration should:
- Initialize the resolver (or rehydrate from cache) before tests run
- Skip disabled tests using the framework's native skip mechanism
- Collect discovered test IDs for sync mode
- Call `SheetsWriter::sync()` after all tests finish (sync mode only)

See existing integrations for reference patterns.
