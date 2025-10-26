# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- Fixed array to string conversion for `requirements` and `topics` fields in `Application::importAppJson()`
  - Arrays are now properly converted to comma-separated strings (e.g., `["mServer", "SQLServer"]` → `"mServer,SQLServer"`)
  - Prevents "Array" string being stored in database during JSON imports
  - Reimporting existing JSON files will now correctly update these fields with proper values
