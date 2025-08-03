# php-vitexsoftware-multiflexi-core

MultiFlexi's Core classes

![MultiFlexi-Chan](multiflexi-chan.png?raw=true)

## Core Classes Overview

The MultiFlexi core provides a rich set of PHP classes for managing flexible, multi-tenant application environments. Below is a summary of the main classes and their responsibilities:

- **Engine**: Base class for database-backed entities, providing common ORM and data management functionality.
- **Company, CompanyApp, CompanyEnv, CompanyJob**: Manage company profiles, their assigned applications, environments, and job scheduling.
- **Application**: Represents an application within the MultiFlexi platform, including configuration and metadata.
- **Credential, CredentialType, CredentialConfigFields, CredentialProtoType**: Handle secure storage, typing, and configuration of credentials for applications and services.
- **Conffield, ConfigField, ConfigFields, ConfigFieldWithHelper**: Define and manage configuration fields for applications and modules, supporting dynamic forms and helpers.
- **Credata**: Manages credential data records, extending the Engine for secure storage.
- **DBEngine, DatabaseEngine**: Provide advanced database operations, including table definitions, data rendering, and integration with DataTables.
- **Environmentor**: Manages environment variables and settings for application execution.
- **Job, Runner, RunTemplate, RunTplCreds, ScheduleLister, Scheduler**: Orchestrate job execution, scheduling, templates, and credential assignment for automated workflows.
- **Logger, LogToSQL, LogToZabbix**: Centralized logging, with support for SQL and Zabbix integration.
- **Requirement**: Handles requirements and dependencies, including credential provider discovery.
- **Token**: Manages authentication tokens for secure access.
- **Topic, Topics, TopicManager**: Support for topic-based messaging and categorization within the platform.
- **User**: Manages user accounts, authentication, and permissions.
- **FileStore**: Handles file storage and retrieval operations.
- **platformCompany, platformServer**: Interfaces for platform-specific company and server integration.

All classes follow PSR-12 coding standards, include comprehensive docblocks, and are well-tested with PHPUnit. Internationalization is supported via the i18n library, and all user-facing strings are translatable.

For more details, see the source code in the `src/MultiFlexi/` directory and the corresponding unit tests in `tests/src/MultiFlexi/`.

### MultiFlexi

multiflexi-core is heart of [MultiFlexi](https://multiflexi.eu) suite.
See the full list of ready-to-run applications within the MultiFlexi platform on the [application list page](https://www.multiflexi.eu/apps.php).

[![MultiFlexi](https://github.com/VitexSoftware/MultiFlexi/blob/main/doc/multiflexi-app.svg)](https://www.multiflexi.eu/)
