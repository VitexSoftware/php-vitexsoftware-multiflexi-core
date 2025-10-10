# MultiFlexi Development Standards

## JSON Schema Validation Rules

All files in the multiflexi/*.app.json directory must conform to the schema available at: <https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/multiflexi.app.schema.json>

When modifying or creating multiflexi/*.app.json files, always validate them against the schema before making changes. Use tools to check JSON schema compliance.

All produced reports must conform to the schema available at: <https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/multiflexi.report.schema.json>

When modifying JSON files or creating new multiflexi applications, always verify the JSON syntax and schema compliance as part of the development process.

## Validation Commands

To validate JSON syntax:

```bash
find multiflexi/ -name "*.json" -exec python3 -m json.tool {} \; -print
```

To validate against schema (requires jsonschema and requests packages):

```python
import json
import requests
import jsonschema
import glob

# Download schema
schema_url = "https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/multiflexi.app.schema.json"
schema = requests.get(schema_url).json()

# Validate files
for file_path in glob.glob("multiflexi/*.json"):
    with open(file_path, 'r') as f:
        data = json.load(f)
    jsonschema.validate(data, schema)
    print(f"✅ {file_path} is valid")
```