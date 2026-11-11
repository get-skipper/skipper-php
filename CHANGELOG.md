# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.0] - 2026-11-11

### Changed
- `SkipperResolver::initialize()` now returns where the entries came from: `SkipperResolver::SOURCE_API`, `SOURCE_CACHE` or `SOURCE_FAIL_OPEN`.
- **testIds changed** for PHPUnit data-provider tests, Pest tests and Codeception tests (see Fixed). Rows created by `SKIPPER_MODE=sync` with the old format become orphans; the next sync adds rows in the documented format.

### Fixed
- "Spreadsheet loaded" is logged only when the spreadsheet was actually fetched, not after a cache or fail-open fallback (#4).
- Cache and fail-open warnings are written to stderr instead of `error_log()`, so they reach the test output even when an `error_log` file is configured (#4).
- PHPUnit: data-provider tests get the documented ` with data set "..."` suffix (#5).
- Pest: testIds use the test description (`tests/Feature/auth.php > Auth > can login`) instead of the encoded `__pest_evaluable_...` method name (#5).
- Codeception: testIds use the user's class (`AuthCest`, `AuthTest`) instead of Codeception's `Cest` / `TestCaseWrapper` wrapper (#7).
- Codeception: disabled tests are now reported as skipped instead of as errors.

### Removed
- `GetSkipper\Pest\Plugin::skipperSetup()`: it relied on APIs that do not exist in Pest 2/3 and could not work. Register the PHPUnit extension in `phpunit.xml` instead (#6).

## [1.3.0] - 2026-05-03

### Added
- Quarantine debt report emitted on every PHPUnit run, showing scope and details of active quarantines.

### Changed
- Skipper artifacts now excluded from version control via gitignore.

## [1.2.0] - 2026-04-09

### Fixed
- `disabledUntil` date parsing now strictly enforces YYYY-MM-DD format; any other format (e.g. `"2026-4-1"`, `"04/01/2026"`, ISO-8601 with time) throws `\InvalidArgumentException` with the row number, instead of silently treating the test as enabled.
- Dates are now pinned to UTC throughout: parsed dates are anchored to `00:00:00 UTC +1 day` (so "disabled until 2026-04-01" expires at `2026-04-02T00:00:00Z`), and the `isTestEnabled()` comparison uses `now` in UTC — eliminating timezone-dependent re-enable timing across CI runners.

### Added
- `DisabledUntilParser` — standalone, testable class encapsulating strict date parsing and disabled-state evaluation.
- Unit tests covering valid date, malformed date (throws), null/empty, and cross-timezone consistency.

## [1.1.1] - 2026-03-28

### Changed
- `SheetsClient` is no longer `final`, and both `SkipperResolver` and `SheetsWriter` now accept an optional `SheetsClient` constructor parameter, enabling injection of test doubles without a real Google API connection.

### Added
- Unit tests for `SKIPPER_FAIL_OPEN`, `SKIPPER_CACHE_TTL`, and `SKIPPER_SYNC_ALLOW_DELETE` (`SkipperResolverInitializeTest`, `SheetsWriterTest`).

## [1.1.0] - 2026-03-28

### Added
- `SKIPPER_FAIL_OPEN` env var: when the API is unreachable and no valid cache exists, run all tests instead of crashing (default: `true`). Set to `false` to restore the previous behaviour of rethrowing the exception.
- `SKIPPER_CACHE_TTL` env var: after every successful fetch, skipper writes a local `.skipper-cache.json` file. On subsequent API failures the cache is used as a fallback if its age is within this TTL (default: `300` seconds).
- `SKIPPER_SYNC_ALLOW_DELETE` env var: in sync mode, orphaned rows are now only logged by default. Set to `true` to allow skipper to delete them from the spreadsheet (previous behaviour).

## [1.0.0] - 2026-03-26

### Added
- Initial release with support for PHPUnit (10/11/12), Pest (v2/v3), Behat (3.x), Codeception (5.x), PHPSpec (7/8), and Kahlan (5.x/6.x).
- Google Sheets-based test-gating via `disabledUntil` dates.
- Sync mode (`SKIPPER_MODE=sync`) to reconcile the spreadsheet with the discovered test suite.
- Reference sheets support for merging test entries from multiple sheets.
- Parallel test execution support via cross-process resolver cache (`SKIPPER_CACHE_FILE`, `SKIPPER_DISCOVERED_DIR`).
- Three credential formats: file path, base64 string, environment variable.

[Unreleased]: https://github.com/get-skipper/skipper-php/compare/v1.4.0...HEAD
[1.4.0]: https://github.com/get-skipper/skipper-php/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/get-skipper/skipper-php/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/get-skipper/skipper-php/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/get-skipper/skipper-php/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/get-skipper/skipper-php/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/get-skipper/skipper-php/releases/tag/v1.0.0
