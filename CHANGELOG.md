# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.11.0] - 2026-08-14

### Added
- `CronDescriber` class (moved from multiflexi-web5's `MultiFlexi\Ui` namespace into
  `MultiFlexi` so `multiflexi-scheduler` can use it too) — translates a RunTemplate
  interval code / cron expression into a short, localized sentence, including
  comma-separated hour lists (e.g. `1 5,10,16,21 * * *` → "At minute 1 past hour 5,
  10, 16, and 21.")
- `RunTemplate::cloneAs()` — cloning a RunTemplate now always creates the copy
  disabled (`active=false`), so a not-yet-reviewed copy of credentials/env/cron
  can never be picked up by the scheduler (#53)
- `CredentialState` enum (`Available`/`Degraded`/`Unavailable`/`Misconfigured`/`Unknown`),
  `CredentialCheckResult`, and `checkableCredentialInterface` — a live availability-check
  contract for credential prototypes. `CredentialProtoType` implements it with a
  no-op default returning `Unknown` (backward compatible — existing prototypes
  never block). `FioBank` implements `checkAvailability()`.
- `Job::reportCredentialBlocked()` — emits an availability-check failure to the
  SQL log, Zabbix (when `ZABBIX_SERVER` is configured), and OpenTelemetry (when
  `OTEL_ENABLED`).
- `Task` class with a state machine (open/running/fulfilled/fulfilled_late/failed/missed)
  and `materialize()`, `fulfill()`, `markFailed()`, `markMissed()`, `canRetry()`,
  `getNextRetryTime()`. `Job` carries `task_id` and drives Task state transitions
  in `runEnd()`. `RunTemplate` gains getters/setters for `deadline_offset`,
  `max_attempts`, `retry_backoff`, `retry_min_gap`, `allow_late`.
- Job-chaining support: `Application` persists `produces`/`consumes` from app.json
  import and exposes `getProduces()`/`getConsumes()`; `Job::collectProducedData()`
  resolves produced output by format; `EventRule::buildEnvOverrides()` generalized
  to accept any source array, plus `resolveSelector()` (dot-path/JSONPath/`@file:`)
  and `getRulesForRunTemplate()` for job-completed chain triggers.
- `CompanyUser` model for company/user assignment handling.
- Credential type SVG images (CommonCredentialType, Fio, Office365, SQLServer, env-file).
- `dragonmantank/cron-expression` is now a required dependency.

### Changed
- `Application::deleteFromSQL()` now refuses to delete (throws `\RuntimeException`)
  while any RunTemplate is still assigned to the app, instead of silently
  cascading to delete those RunTemplates itself. Callers must remove the
  RunTemplates first via `RunTemplate::deleteFromSQL()`, which already
  cascades their jobs/config/credential bindings safely.
- `Security\DataEncryption` wraps key-decryption failures in a dedicated
  `EncryptionUnavailableException` with a diagnostic message naming the affected
  key/version, instead of a bare `\RuntimeException` — makes the common
  "`ENCRYPTION_MASTER_KEY` changed/lost after the `encryption_keys` row was
  created" case actionable from the error alone.

### Fixed
- Fixed `RunTemplate::deleteFromSQL()` to correctly cascade-delete jobs (and their
  queue entries, output logs, and artifacts), action config, credential
  assignments, saved config values, and job-chaining event rules regardless of
  how it's called. Previously the cascade only worked when the object had been
  loaded with the target's own id; calling it with a condition array on an
  unloaded object (e.g. `CompanyApp::assignApps()`'s unassign path) silently
  skipped the cascade and then failed with an uncaught FK violation on
  `job.runtemplate_id` for any RunTemplate with job history.
  - Wrapped the cascade in a transaction so a failure partway rolls back
    cleanly instead of leaving orphaned dependents.
  - Deactivates the RunTemplate first and re-sweeps job cleanup, to close a
    race window against the scheduler daemon inserting a new job mid-delete.
- Fixed array to string conversion for `requirements` and `topics` fields in `Application::importAppJson()`
  - Arrays are now properly converted to comma-separated strings (e.g., `["mServer", "SQLServer"]` → `"mServer,SQLServer"`)
  - Prevents "Array" string being stored in database during JSON imports
  - Reimporting existing JSON files will now correctly update these fields with proper values
- Removed stale duplicate VaultWarden `CredentialProtoType` (owned by the standalone
  `multiflexi-vaultwarden` package; the copy here collided at install time) and
  leftover test files for credential types already split into their own add-on
  packages (`multiflexi-abraflexi`, `multiflexi-csas`, `multiflexi-raiffeisenbank`,
  `multiflexi-mserver`).
- `Company::takeData()` always set `enabled=true` when the key was present; now
  uses `!empty()` to respect the actual value.
- `ConfigField` type normalization in `Credential::takeData()` and
  `CredentialProtoType\Common::importFields()` via `Conffield::fixType()`.
