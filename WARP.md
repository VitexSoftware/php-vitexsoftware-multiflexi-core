# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

Project overview
- This repository provides the core PHP library for MultiFlexi (flexible, multi-tenant orchestration). It contains core entities (Engine, Company*, Application, Credential*, Config*, Job/Scheduler/Runner, Logger, Requirement, Token, Topic*, User, FileStore, Platform*), plus environment and executor components.
- Primary namespaces map to PSR-4 autoload paths:
  - MultiFlexi\ → src/MultiFlexi/
  - MultiFlexi\Env\ → src/MultiFlexi/Env
  - MultiFlexi\Action\ → src/MultiFlexi/Action
  - MultiFlexi\Zabbix\ → src/MultiFlexi/Zabbix
  - MultiFlexi\Executor\ → src/MultiFlexi/Executor
  - MultiFlexi\CredentialType\ → src/MultiFlexi/CredentialType
- Tests are under tests/ with PSR-4 dev autoload Test\MultiFlexi\ → tests/src/MultiFlexi/.

High-level architecture
- Data/ORM base: Engine is the base for DB-backed entities. DBEngine/DatabaseEngine provide table definitions and rendering, integrating with DataTables.
- Domain model:
  - Company, CompanyApp, CompanyEnv, CompanyJob represent tenants, their apps, environments, and scheduled jobs.
  - Application encapsulates app metadata/config; Requirement discovers dependencies/credential providers.
  - Credential*, Credata model credential types, fields, and storage.
  - Config* classes define dynamic config fields and helpers for forms.
  - Environmentor manages environment variables for execution contexts.
- Execution & scheduling: Job, Runner, RunTemplate, RunTplCreds, ScheduleLister, Scheduler orchestrate templates, credentials, and job execution.
- Logging & integration: Logger with LogToSQL and LogToZabbix; Zabbix integration lives under MultiFlexi\Zabbix\.
- Security/auth: Token for authentication; User for accounts/permissions.
- Auxiliary: Topic/Topics/TopicManager for topic-based messaging; FileStore for file persistence; platformCompany/platformServer for host/platform integration.
- Internationalization: Strings are intended to be translatable via an i18n library (use _() helpers in code).

Common development commands
- Install dependencies
  - composer install

- Run the full test suite (PHPUnit)
  - vendor/bin/phpunit
  - Configuration: phpunit.xml (bootstrap tests/bootstrap.php, strict settings, coverage includes src/)

- Run a single test or test method
  - By filter: vendor/bin/phpunit --filter 'ClassName::testMethod'
  - By path: vendor/bin/phpunit tests/src/MultiFlexi/ClassNameTest.php

- Static analysis (PHPStan)
  - vendor/bin/phpstan analyse -c phpstan.neon.dist

- Code style (PHP-CS-Fixer)
  - Dry-run check: vendor/bin/php-cs-fixer fix --dry-run --diff .
  - Apply fixes: vendor/bin/php-cs-fixer fix .
  - Note: If a project-specific config is added (e.g., .php-cs-fixer.php), PHP-CS-Fixer will pick it up automatically.

- Composer normalization
  - composer normalize --dry-run
  - Apply normalization: composer normalize

- Rector (if/when a rector.php is added)
  - Dry-run: vendor/bin/rector process src --dry-run
  - Apply: vendor/bin/rector process src

Repository conventions and rules worth knowing
- From .github/copilot-instructions.md (important excerpts):
  - Use PHP 8.4+ and follow PSR-12.
  - Write code and messages in English; include docblocks for functions/classes with types.
  - Use _() for translatable strings (i18n).
  - Always create/update PHPUnit tests when creating/updating classes.

Paths and layout
- Source: src/MultiFlexi/ (plus subnamespaces Env/, Action/, Zabbix/, Executor/, CredentialType/)
- Tests: tests/src/MultiFlexi/
- Config: phpunit.xml, phpstan.neon.dist
- Packaging (optional/maintainer-focused): debian/

Notes
- There is no Makefile in this repo; use Composer binaries under vendor/bin/ as shown above.
- Minimum-stability is dev; this library depends on vitexsoftware/ease-fluentpdo and justinrainbow/json-schema.

