# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/get-skipper/skipper-php/compare/v1.3.0...HEAD
[1.3.0]: https://github.com/get-skipper/skipper-php/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/get-skipper/skipper-php/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/get-skipper/skipper-php/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/get-skipper/skipper-php/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/get-skipper/skipper-php/releases/tag/v1.0.0
