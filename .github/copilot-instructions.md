<!-- Use this file to provide workspace-specific custom instructions to Copilot. For more details, visit https://code.visualstudio.com/docs/copilot/copilot-customization#_use-a-githubcopilotinstructionsmd-file -->

## MultiFlexi Framework Guidelines

This is the MultiFlexi Core library - a multi-tenant application management platform with advanced job scheduling, credential management, and extensible action system.

### Code Standards
- All code comments should be written in English
- All messages, including error messages, should be written in English
- All code should be written in PHP 8.4 or later
- All code should follow the PSR-12 coding standard
- Always include comprehensive docblocks for functions and classes, describing their purpose, parameters, and return types
- Always include type hints for function parameters and return types
- Use meaningful variable names that describe their purpose
- Avoid using magic numbers or strings; instead, define constants for them
- Handle exceptions properly and provide meaningful error messages
- Ensure code is secure and does not expose any sensitive information

### MultiFlexi Architecture Patterns

#### Core Base Classes
- **Engine**: Base class for database-backed entities with ORM functionality
- **DBEngine**: Extended Engine with advanced database operations and DataTables integration
- **CommonAction**: Base class for all Action classes that perform automated tasks

#### Key Namespaces and Patterns
- `MultiFlexi\`: Core framework classes (Company, Application, Job, User, etc.)
- `MultiFlexi\Action\`: Action classes that extend CommonAction (ToDo, WebHook, Github, etc.)
- `MultiFlexi\CredentialType\`: Credential type implementations
- `MultiFlexi\Env\`: Environment-specific configurations
- `MultiFlexi\Executor\`: Job execution engines
- `MultiFlexi\Zabbix\`: Zabbix monitoring integration

#### Action Class Guidelines
When creating Action classes:
- Extend `CommonAction` base class
- Implement `perform()` method for main execution logic
- Use `$this->runtemplate` to access job context and configuration
- Use `$this->environment` for environment variables
- Return appropriate success/failure status codes
- Support configuration through `loadOptions()` method
- Handle credential access through the credential system

#### Database and ORM
- Extend `Engine` or `DBEngine` for database-backed entities
- Use `$this->myTable` to specify table name
- Use `$this->keyword` for primary key field name
- Use `$this->nameColumn` for display name field
- Implement proper relationships using the ORM patterns

#### Configuration and Credentials
- Use `ConfigField`, `ConfigFields` classes for dynamic configuration
- Implement credential types by extending appropriate base classes
- Support credential providers through the `Requirement` system
- Use secure storage patterns for sensitive data

### Testing Requirements
- Use PHPUnit for all tests
- Follow PSR-12 coding standard in tests
- Create comprehensive test coverage for new classes
- When creating new class or updating existing class, always create or update its PHPUnit test files
- Place tests in `tests/src/MultiFlexi/` matching the source structure
- Use proper mocking for external dependencies
- Test both success and failure scenarios

### Internationalization
- Use the i18n library for internationalization
- Always use the `_()` functions for strings that need to be translated
- Ensure all user-facing messages are translatable
- Keep translation keys descriptive and organized

### Documentation
- Use Markdown format for documentation
- Maintain comprehensive README.md with class overviews
- Document configuration options and usage examples
- Keep commit messages in imperative mood and concise

### Dependencies and Compatibility
- Ensure compatibility with latest PHP version and all dependencies
- Key dependencies: ease-fluentpdo, ease-html, json-schema, bitwarden-php
- Consider performance implications in all code
- Maintain backward compatibility when possible

### Security Considerations
- Never expose sensitive information in logs or error messages
- Use proper credential management through the framework
- Validate all inputs and sanitize outputs
- Follow secure coding practices for database operations

