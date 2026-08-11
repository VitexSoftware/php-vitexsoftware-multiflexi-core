# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- `Application::deleteFromSQL()` now refuses to delete (throws `\RuntimeException`)
  while any RunTemplate is still assigned to the app, instead of silently
  cascading to delete those RunTemplates itself. Callers must remove the
  RunTemplates first via `RunTemplate::deleteFromSQL()`, which already
  cascades their jobs/config/credential bindings safely.

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
