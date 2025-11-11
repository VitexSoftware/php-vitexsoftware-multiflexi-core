# php-vitexsoftware-multiflexi-core

MultiFlexi's Core classes

![MultiFlexi-Chan](multiflexi-chan.png?raw=true)

## Core Classes Overview

The MultiFlexi core provides a rich set of PHP classes for managing flexible, multi-tenant application environments. Below is a summary of the main classes organized by their functional areas:

### Core Base Classes

- **Engine**: Base class for database-backed entities, providing common ORM and data management functionality
- **DBEngine**: Extended Engine with advanced database operations, DataTables integration, and web UI support
- **CommonAction**: Base class for all Action classes that perform automated tasks and integrations
- **CommonExecutor**: Base class for job execution environments

### Entity Management

- **Company, CompanyApp, CompanyEnv, CompanyJob**: Manage company profiles, their assigned applications, environments, and job scheduling
- **Application**: Represents an application within the MultiFlexi platform, including configuration and metadata
- **User**: Manages user accounts, authentication, and permissions
- **Customer**: Handles customer relationship management

### Job Execution & Scheduling

- **Job**: Core job entity with execution tracking and status management
- **Runner**: Executes jobs using various execution environments
- **RunTemplate**: Templates for job execution with parameter substitution
- **RunTplCreds**: Manages credential assignment for job templates
- **Scheduler**: Handles job scheduling and timing
- **ScheduleLister**: Lists and manages scheduled jobs

### Action System

Actions extend `CommonAction` and provide automated integrations and workflows:

- **ToDo**: Creates task items from job outputs with smart priority assignment
- **WebHook**: HTTP webhook notifications and integrations
- **Github**: GitHub repository interactions and automation
- **Zabbix**: Zabbix monitoring system integration
- **RedmineIssue**: Redmine issue tracking integration
- **TriggerJenkins**: Jenkins CI/CD pipeline triggering
- **LaunchJob**: Launches other MultiFlexi jobs
- **CustomCommand**: Executes custom shell commands
- **Sleep**: Introduces delays in job workflows
- **Stop**: Stops job execution
- **Reschedule**: Reschedules jobs with new timing

### Credential Management

- **Credential**: Secure credential storage with encryption
- **CredentialType**: Base class for credential type definitions
- **CredentialConfigFields**: Dynamic configuration for credential types
- **CredentialProtoType**: Prototype pattern for credential implementations
- **Credata**: Manages credential data records

#### Built-in Credential Types

- **AbraFlexi**: Czech ERP system integration
- **Office365**: Microsoft Office 365 services
- **VaultWarden**: Bitwarden/VaultWarden password manager
- **SQLServer**: Microsoft SQL Server database
- **FioBank**: Fio Bank API integration
- **RaiffeisenBank**: Raiffeisen Bank services
- **Csas**: Česká spořitelna bank integration
- **EnvFile**: Environment file credential provider

### Execution Environments

- **Native**: Direct local execution
- **Docker**: Docker container execution
- **Podman**: Podman container execution
- **Kubernetes**: Kubernetes cluster execution
- **Azure**: Azure cloud execution

### Configuration System

- **ConfigField**: Individual configuration field definitions
- **ConfigFields**: Collections of configuration fields
- **ConfigFieldWithHelper**: Configuration fields with helper text and validation
- **Configuration**: Application configuration management
- **ModConfig**: Module-specific configuration
- **Environmentor**: Environment variable management

### Logging & Monitoring

- **Logger**: Centralized logging with multiple backends
- **LogToSQL**: Database logging backend
- **LogToZabbix**: Zabbix monitoring integration
- **Zabbix**: Complete Zabbix monitoring namespace with metrics and alerting

### Utility Classes

- **Token**: Authentication token management
- **Topic, Topics, TopicManager**: Topic-based messaging and categorization
- **FileStore**: File storage and retrieval operations
- **Requirement**: Dependency and requirement management
- **platformCompany, platformServer**: Platform-specific integrations

All classes follow PSR-12 coding standards, include comprehensive docblocks, and are well-tested with PHPUnit. Internationalization is supported via the i18n library, and all user-facing strings are translatable.

For more details, see the source code in the `src/MultiFlexi/` directory and the corresponding unit tests in `tests/src/MultiFlexi/`.

### MultiFlexi

multiflexi-core is heart of [MultiFlexi](https://multiflexi.eu) suite.
See the full list of ready-to-run applications within the MultiFlexi platform on the [application list page](https://www.multiflexi.eu/apps.php).

[![MultiFlexi](https://github.com/VitexSoftware/MultiFlexi/blob/main/doc/multiflexi-app.svg)](https://www.multiflexi.eu/)

## Architecture & Usage

### Framework Architecture

MultiFlexi follows a modular architecture with clear separation of concerns:

- **Engine-based ORM**: All database entities extend `Engine` or `DBEngine` for consistent data access patterns
- **Action-based Integrations**: Actions extend `CommonAction` to provide reusable automation components
- **Credential Management**: Secure, typed credential system with provider-specific implementations
- **Multi-tenant Design**: Complete isolation between companies with shared infrastructure
- **Extensible Execution**: Support for multiple execution environments (Docker, Kubernetes, Azure, etc.)

### Creating Custom Actions

To create a custom action, extend the `CommonAction` base class:

```php
<?php
namespace MultiFlexi\Action;

class MyCustomAction extends \MultiFlexi\CommonAction
{
    public function perform(): int
    {
        // Access job context
        $job = $this->runtemplate;
        
        // Access environment variables
        $config = $this->environment;
        
        // Perform your action logic
        // ...
        
        // Return success (0) or failure (1+)
        return 0;
    }
}
```

### Using the Credential System

Credentials are managed through the typed credential system:

```php
<?php
// Create a credential for a specific service
$credential = new \MultiFlexi\Credential();
$credential->setData([
    'name' => 'My API Key',
    'type' => 'office365',
    'username' => 'user@example.com',
    'password' => 'secure_password'
]);

// Use in actions through the requirement system
$requirement = new \MultiFlexi\Requirement();
$requirement->setCredentialType('office365');
```

### Database Entity Patterns

All database entities follow consistent patterns:

```php
<?php
class MyEntity extends \MultiFlexi\Engine
{
    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'my_entities';
        $this->keyword = 'id';
        $this->nameColumn = 'name';
        parent::__construct($identifier, $options);
    }
}
```
