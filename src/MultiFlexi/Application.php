<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MultiFlexi;

/**
 * Description of Application.
 *
 * @author vitex
 */
class Application extends DBEngine
{
    public ?string $lastModifiedColumn;
    public Company $company;
    public static string $appSchema = __DIR__.'/../../multiflexi.app.schema.json';

    /**
     * @param mixed $identifier
     * @param array $options
     */
    public function __construct($identifier = null, $options = [])
    {
        $this->keyword = 'app';
        $this->myTable = 'apps';
        $this->createColumn = 'DatCreate';
        $this->lastModifiedColumn = 'DatUpdate';
        $this->keyword = 'app';
        $this->nameColumn = 'name';
        parent::__construct($identifier, $options);
        $this->company = new Company();
    }

    /**
     * @return \MultiFlexi\Company
     */
    public function getCompany()
    {
        return $this->company;
    }

    /**
     * Check data before accepting.
     */
    #[\Override]
    public function takeData(array $data): int
    {
        unset($data['$schema'], $data['produces']);
        // TODO: Process somehow in future
        $data['enabled'] = \array_key_exists('enabled', $data) ? (($data['enabled'] === 'on') || ($data['enabled'] === 1)) : 0;

        if (\array_key_exists('name', $data) && empty($data['name'])) {
            $this->addStatusMessage(_('Name is empty'), 'warning');
        }

        if (\array_key_exists('imageraw', $_FILES) && !empty($_FILES['imageraw']['name'])) {
            $uploadfile = sys_get_temp_dir().'/'.basename($_FILES['imageraw']['name']);

            if (move_uploaded_file($_FILES['imageraw']['tmp_name'], $uploadfile)) {
                $data['image'] = 'data:'.mime_content_type($uploadfile).';base64,'.base64_encode(file_get_contents($uploadfile));
                unlink($uploadfile);
                unset($data['imageraw']);
            }
        }

        //        if ((\array_key_exists('uuid', $data) === false) || empty($data['uuid'])) {
        //            $data['uuid'] = \Ease\Functions::guidv4();
        //        }

        if ((\array_key_exists('code', $data) === false) || empty($importData['code'])) {
            //            $data['code'] = substr(substr(strtoupper($data['executable'] ? basename($data['executable']) : $data['name']), -7), 0, 6);
        }

        $data['image'] = array_key_exists('image', $data) ? strval($data['image']) : '';
        
        unset($data['class']);

        return parent::takeData($data);
    }

    public function getCode()
    {
        $data = $this->getData();

        return substr(strtoupper($data['executable'] ? basename($data['executable']) : $data['name']), 0, -6);
    }

    public function getUuid()
    {
        return \Ease\Functions::guidv4();
    }

    /**
     * Check command's availbility.
     *
     * @param string $command
     *
     * @return bool check result
     */
    public function checkExcutable($command)
    {
        //        new \Symfony\Component\Process\ExecutableFinder(); TODO

        $status = true;

        if ($command[0] === '/') {
            if (file_exists($command) === false) {
                $this->addStatusMessage(sprintf(_('Executable %s does not exist'), $command), 'warning');
                $status = false;
            }
        } else {
            $executable = self::findBinaryInPath($command);

            if (empty($executable)) {
                $this->addStatusMessage(sprintf(_('Executable %s does not exist in search PATH %s'), $command, getenv('PATH')), 'warning');
                $status = false;
            } else {
                if (is_executable($executable) === false) {
                    $this->addStatusMessage(sprintf(_('file %s is not executable'), $command), 'warning');
                    $status = false;
                }
            }
        }

        return $status;
    }

    /**
     * Find real path for given binary name.
     *
     * @param string $binary full realpath
     *
     * @return string
     */
    public static function findBinaryInPath($binary)
    {
        $found = null;

        if ($binary[0] === '/') {
            $found = file_exists($binary) && is_executable($binary) ? $binary : null;
        } else {
            foreach (strstr(getenv('PATH'), ':') ? explode(':', getenv('PATH')) : [getenv('PATH')] as $pathDir) {
                $candidat = ((substr($pathDir, -1) === '/') ? $pathDir : $pathDir.'/').$binary;

                if (file_exists($candidat) && is_executable($candidat)) {
                    $found = $candidat;

                    break;
                }
            }
        }

        return $found;
    }

    /**
     * @param string $binary
     *
     * @return bool
     */
    public static function doesBinaryExist($binary)
    {
        return ($binary[0] === '/') ? file_exists($binary) : self::isBinaryInPath($binary);
    }

    /**
     * @param string $binary
     *
     * @return bool
     */
    public static function isBinaryInPath($binary)
    {
        return !empty(self::findBinaryInPath($binary));
    }

    /**
     * For "platform" return applications by config fields.
     *
     * @param string $platform AbraFlexi|Pohoda
     *
     * @return array
     */
    public function getPlatformApps($platform)
    {
        $platformApps = [];
        $confField = new Conffield();

        foreach ($this->listingQuery() as $appId => $appInfo) {
            $appConfFields = $confField->appConfigs($appInfo['id']);
            $appConfs = array_keys($appConfFields);

            if (preg_grep('/^'.strtoupper($platform).'_.*/', $appConfs)) {
                $platformApps[$appId] = $appInfo;
            }
        }

        return $platformApps;
    }

    /**
     * Obtain list of applications supporting given platform.
     *
     * @param string $platform
     *
     * @return \Envms\FluentPDO\Query
     */
    public function getAvailbleApps($platform)
    {
        return $this->listingQuery()->where('enabled', true);
    }

    /**
     * Export Application and its Fields definiton as Json.
     *
     * @return string Json
     */
    public function getAppJson()
    {
        $appData = $this->getData();

        if ($this->getMyKey()) {
            $confField = new Conffield();
            $appData['environment'] = $confField->getAppConfigs($this)->getEnvArray();
        } else {
            $appData['environment'] = [];
        }

        foreach ($appData['environment'] as $fieldName => $filedProperties) {
            unset($appData['environment'][$fieldName]['id'], $appData['environment'][$fieldName]['keyname'], $appData['environment'][$fieldName]['app_id']);
        }

        unset($appData['id'], $appData['DatCreate'], $appData['DatUpdate'], $appData['enabled']);

        $appData['multiflexi'] = \Ease\Shared::appVersion();

        $appData['version'] = '2.0.0'; // Set a default version, can be updated later
        // Only include artifacts if a valid pattern is present in the current data
        $artifactsPattern = $this->getDataValue('artifacts');

        if (!empty($artifactsPattern) && \is_string($artifactsPattern)) {
            $appData['artifacts'] = [
                'pattern' => $artifactsPattern,
            ];
        }

        return json_encode($appData, \JSON_PRETTY_PRINT);
    }

    /**
     * valid filename for current App Json.
     *
     * @return string
     */
    public function jsonFileName()
    {
        return strtolower(trim(preg_replace('#\W+#', '_', (string) $this->getRecordName()), '_')).'.multiflexi.app.json';
    }

    /**
     * @param string $jsonFile
     *
     * @return array<string> errors
     */
    public function validateAppJson($jsonFile): array
    {
        $violations = [];
        $data = json_decode(file_get_contents($jsonFile));
        $validator = new \JsonSchema\Validator();
        $validator->validate($data, (object) ['$ref' => 'file://'.realpath(self::$appSchema)]);

        if (!$validator->isValid()) {
            foreach ($validator->getErrors() as $error) {
                $violations[] = sprintf("[%s] %s\n", $error['property'], $error['message']);
            }
        }

        return $violations;
    }

    /**
     * import Json App Definition file.
     *
     * @param string $jsonFile
     */
    public function importAppJson($jsonFile): array
    {
        $fields = [];

        // Validate JSON against schema before import
        $schemaFile = self::$appSchema;

        if (!file_exists($schemaFile)) {
            throw new \RuntimeException(_('Schema file not found: ').$schemaFile);
        }

        $appSpecRaw = file_get_contents($jsonFile);

        if (empty($appSpecRaw)) {
            throw new \RuntimeException(_('App definition file is empty: ').$jsonFile);
        }

        $appSpec = json_decode($appSpecRaw, true);

        if (json_last_error() !== \JSON_ERROR_NONE) {
            throw new \RuntimeException(_('Invalid JSON: ').json_last_error_msg());
        }

        // Extract localized fields
        $localizedFields = ['name', 'title', 'description'];
        $defaultLang = 'en'; // Default language
        $translations = [];

        foreach ($localizedFields as $field) {
            if (isset($appSpec[$field])) {
                if (\is_string($appSpec[$field])) {
                    // Legacy string format
                    $fields[$field] = $appSpec[$field];
                } elseif (\is_array($appSpec[$field])) {
                    // Localized object format
                    $fields[$field] = $appSpec[$field][$defaultLang] ?? reset($appSpec[$field]);

                    foreach ($appSpec[$field] as $lang => $value) {
                        $translations[$lang][$field] = $value;
                    }
                }
            }
        }

        // Extract non-localized fields
        $nonLocalizedFields = ['executable', 'setup', 'cmdparams', 'deploy', 'homepage', 'requirements', 
                               'ociimage', 'version', 'code', 'uuid', 'topics', 'resultfile'];
        foreach ($nonLocalizedFields as $field) {
            if (isset($appSpec[$field])) {
                $fields[$field] = $appSpec[$field];
            }
        }

        // Set defaults for required fields if not present
        if (!isset($fields['deploy'])) {
            $fields['deploy'] = ''; // Empty string as default
        }
        if (!isset($fields['homepage'])) {
            $fields['homepage'] = ''; // Empty string as default
        }
        if (!isset($fields['requirements'])) {
            $fields['requirements'] = ''; // Empty string as default
        }
        if (!isset($fields['topics'])) {
            $fields['topics'] = ''; // Empty string as default
        }

        // Handle artifacts field (convert array to string if needed)
        if (isset($appSpec['artifacts'])) {
            if (\is_array($appSpec['artifacts'])) {
                // Convert artifacts array to a pattern string
                $patterns = [];
                foreach ($appSpec['artifacts'] as $artifact) {
                    if (isset($artifact['path'])) {
                        $patterns[] = $artifact['path'];
                    }
                }
                $fields['artifacts'] = implode(',', $patterns);
            } else {
                $fields['artifacts'] = $appSpec['artifacts'];
            }
        }

        // Handle environment configurations
        if (isset($appSpec['environment']) && \is_array($appSpec['environment'])) {
            // We'll handle environment import after saving the app
            $environmentConfigs = $appSpec['environment'];
        }

        // Check if app already exists by name
        $existingApp = null;
        if (isset($fields['name'])) {
            $existingApp = $this->getFluentPDO()
                ->from('apps')
                ->where('name', $fields['name'])
                ->fetch();
        }

        if ($existingApp) {
            // Update existing app
            $appId = $existingApp['id'];
            $this->setMyKey($appId);
            $this->takeData($fields);
            $this->saveToSQL();
        } else {
            // Create new app
            $this->takeData($fields);
            $appId = $this->saveToSQL();
        }

        // Save translations to app_translations table
        foreach ($translations as $lang => $data) {
            $this->getFluentPDO()
                ->insertInto('app_translations', array_merge($data, [
                    'app_id' => $appId,
                    'lang' => $lang,
                ]))
                ->onDuplicateKeyUpdate($data)
                ->execute();
        }

        // Import environment configurations if present
        if (isset($environmentConfigs)) {
            $this->importEnvironmentConfigs($appId, $environmentConfigs);
        }

        return $fields;
    }

    /**
     * Get localized data value for a given key.
     */
    public function getLocalizedDataValue(string $key): ?string
    {
        $localizedData = $this->getDataValue('localized');

        return $localizedData[$key] ?? $this->getDataValue($key);
    }

    /**
     * Application Requirements as Array.
     *
     * @return array<string>
     */
    public function getRequirements(): array
    {
        $reqs = (string) $this->getDataValue('requirements');

        return \strlen($reqs) ? (strstr($reqs, ',') ? explode(',', $reqs) : [$reqs]) : [];
    }

    /**
     * Import environment configurations from app JSON.
     *
     * @param int $appId
     * @param array $environmentConfigs
     */
    protected function importEnvironmentConfigs(int $appId, array $environmentConfigs): void
    {
        $defaultLang = 'en';
        
        foreach ($environmentConfigs as $key => $config) {
            // Prepare data for conffield table
            $configData = [
                'app_id' => $appId,
                'keyname' => $key,
                'type' => $config['type'] ?? 'string',
                'defval' => $this->convertDefval($config['defval'] ?? '', $config['type'] ?? 'string'),
                'required' => isset($config['required']) ? (int)$config['required'] : 0,
            ];

            // Handle localized description field
            if (isset($config['description'])) {
                if (\is_string($config['description'])) {
                    // Legacy string format
                    $configData['description'] = $config['description'];
                } elseif (\is_array($config['description'])) {
                    // Localized object format - use default language for main table
                    $configData['description'] = $config['description'][$defaultLang] ?? reset($config['description']);
                }
            } else {
                $configData['description'] = '';
            }

            // Insert or update configuration field definition
            try {
                $configId = $this->getFluentPDO()
                    ->insertInto('conffield', $configData)
                    ->execute();
            } catch (\PDOException $e) {
                if ($e->getCode() == '23000') { // Duplicate entry
                    // Update existing record
                    $this->getFluentPDO()
                        ->update('conffield')
                        ->set($configData)
                        ->where('app_id', $appId)
                        ->where('keyname', $key)
                        ->execute();
                    
                    // Get the ID of the existing record
                    $existing = $this->getFluentPDO()
                        ->from('conffield')
                        ->where('app_id', $appId)
                        ->where('keyname', $key)
                        ->fetch();
                    if ($existing) {
                        $configId = $existing['id'];
                    }
                } else {
                    throw $e;
                }
            }

            // TODO: Save localized descriptions when conffield_translations table is created
            // For now, we're using the default language description in the main table
        }
    }

    /**
     * Convert default value based on type.
     *
     * @param mixed $defval
     * @param string $type
     * @return string
     */
    protected function convertDefval($defval, string $type): string
    {
        switch ($type) {
            case 'bool':
            case 'boolean':
                return $defval === true || $defval === 'true' || $defval === 1 || $defval === '1' ? '1' : '0';
            case 'int':
            case 'integer':
                return (string) (int) $defval;
            case 'float':
            case 'double':
                return (string) (float) $defval;
            default:
                return (string) $defval;
        }
    }

    /**
     * Load application image from file.
     *
     * @param string $uuid Application UUID
     * @param string $prefix Path prefix where to look for the image
     * @return bool Success
     */
    public function loadImage($uuid, $prefix): bool
    {
        $imageFile = $prefix.$this->getDataValue('uuid').'.svg';

        if (file_exists($imageFile)) {
            $this->setDataValue('image', 'data:image/svg+xml;base64,'.base64_encode(file_get_contents($imageFile)));
            $result = true;
        } else {
            $result = false;
        }

        return $result;
    }
}
