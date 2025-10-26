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

        $data['image'] = \array_key_exists('image', $data) ? (string) ($data['image']) : '';

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
     * @return array<string> errors
     */
    public function validateAppJson(string $jsonFile): array
    {
        return self::validateJson($jsonFile, self::$appSchema);
    }

    /**
     * Remove application based on JSON file definition.
     *
     * @param string $jsonFile Path to the JSON file
     *
     * @return bool True if application was removed successfully
     */
    public function jsonAppRemove($jsonFile)
    {
        $success = true;
        $importData = json_decode(file_get_contents($jsonFile), true);

        if (\is_array($importData)) {
            $candidat = $this->listingQuery()->where('uuid', $importData['uuid']);

            if ($candidat->count()) {
                foreach ($candidat as $candidatData) {
                    $this->setMyKey($candidatData['id']);
                    $removed = $this->deleteFromSQL();

                    if (null === $removed) {
                        $success = false;
                    }

                    $this->addStatusMessage(sprintf(_('Application removal %d %s'), $candidatData['id'], $candidatData['name']), \is_int($removed) ? 'success' : 'error');
                }
            }
        }

        return $success;
    }

    /**
     * Delete application record from SQL including all related data.
     *
     * @param array|int $data
     *
     * @return null|int Number of deleted records or null on error
     */
    public function deleteFromSQL($data = null)
    {
        if (null === $data) {
            $data = $this->getData();
        }

        $appId = $this->getMyKey($data);

        // Delete company-app associations
        $a2c = $this->getFluentPDO()->deleteFrom('companyapp')->where('app_id', $appId)->execute();

        if ($a2c !== 0) {
            $this->addStatusMessage(sprintf(_('Unassigned from %d companies'), $a2c), null === $a2c ? 'error' : 'success');
        }

        // Get all runtemplates for this app
        $runtemplates = $this->getFluentPDO()->from('runtemplate')->where('app_id', $appId)->fetchAll();

        // Delete action configs for each runtemplate
        foreach ($runtemplates as $runtemplate) {
            $rt2ac = $this->getFluentPDO()->deleteFrom('actionconfig')->where('runtemplate_id', $runtemplate['id'])->execute();

            if ($rt2ac !== 0) {
                $this->addStatusMessage(sprintf(_('%s Action Config removal'), $runtemplate['name']), null === $rt2ac ? 'error' : 'success');
            }
        }

        // Delete all runtemplates for this app
        $runtemplater = new RunTemplate();

        foreach ($runtemplater->listingQuery()->where('app_id', $appId) as $runtemplateData) {
            $this->addStatusMessage(sprintf(_('#%d %s RunTemplate removal'), $runtemplateData['id'], $runtemplateData['name']), $runtemplater->deleteFromSQL($runtemplateData['id']) ? 'error' : 'success');
        }

        // Delete config fields
        $a2cf = $this->getFluentPDO()->deleteFrom('conffield')->where('app_id', $appId)->execute();

        if ($a2cf !== 0) {
            $this->addStatusMessage(sprintf(_('%d Config fields removed'), $a2cf), null === $a2cf ? 'error' : 'success');
        }

        // Delete configurations
        $a2cfg = $this->getFluentPDO()->deleteFrom('configuration')->where('app_id', $appId)->execute();

        if ($a2cfg !== 0) {
            $this->addStatusMessage(sprintf(_('%d Configurations removed'), $a2cfg), null === $a2cfg ? 'error' : 'success');
        }

        // Delete jobs
        $a2job = $this->getFluentPDO()->deleteFrom('job')->where('app_id', $appId)->execute();

        if ($a2job !== 0) {
            $this->addStatusMessage(sprintf(_('%d Jobs removed'), $a2job), null === $a2job ? 'error' : 'success');
        }

        // Finally delete the application itself
        return parent::deleteFromSQL($this->getMyKey($data));
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

        // Remove all keys starting with '$' to prevent SQL errors
        foreach (array_keys($appSpec) as $key) {
            if (str_starts_with($key, '$')) {
                unset($appSpec[$key]);
            }
        }

        // Also remove any other known non-database keys
        unset($appSpec['produces']);

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

        // Check if app already exists by UUID first, then by name as fallback
        $existingApp = null;

        if (isset($fields['uuid'])) {
            $existingApp = $this->getFluentPDO()
                ->from('apps')
                ->where('uuid', $fields['uuid'])
                ->fetch();
        }

        if (!$existingApp && isset($fields['name'])) {
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
            // Check if translation already exists
            $existingTranslation = $this->getFluentPDO()
                ->from('app_translations')
                ->where('app_id', $appId)
                ->where('lang', $lang)
                ->fetch();

            if ($existingTranslation) {
                // Update existing translation
                $this->getFluentPDO()
                    ->update('app_translations')
                    ->set($data)
                    ->where('app_id', $appId)
                    ->where('lang', $lang)
                    ->execute();
            } else {
                // Insert new translation
                $this->getFluentPDO()
                    ->insertInto('app_translations', array_merge($data, [
                        'app_id' => $appId,
                        'lang' => $lang,
                    ]))
                    ->execute();
            }
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
     * Check if all required environment fields have values.
     *
     * @param array $keysValues Additional key/value pairs to consider
     * @param bool  $verbose    Whether to add status messages
     *
     * @return bool True if all required fields have values
     */
    public function checkRequiredFields(array $keysValues = [], bool $verbose = false): bool
    {
        $ok = true;

        $confField = new Conffield();
        $appEnvironmentFields = $confField->getAppConfigs($this);

        foreach ($appEnvironmentFields as $fieldName => $field) {
            if ($field->isRequired() && empty($field->getValue())) {
                if ($verbose) {
                    $this->addStatusMessage(sprintf(_('The required configuration key `%s` was not filled'), $fieldName), 'warning');
                }

                $ok = false;
            }
        }

        return $ok;
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
     * Load application image from file.
     *
     * @param string $uuid   Application UUID
     * @param string $prefix Path prefix where to look for the image
     *
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

    /**
     * Import environment configurations from app JSON.
     */
    protected function importEnvironmentConfigs(int $appId, array $environmentConfigs): void
    {
        $defaultLang = 'en';

        // First, delete existing environment configurations for this app to ensure clean import
        $this->getFluentPDO()
            ->deleteFrom('conffield')
            ->where('app_id', $appId)
            ->execute();

        foreach ($environmentConfigs as $key => $config) {
            // Prepare data for conffield table
            $configData = [
                'app_id' => $appId,
                'keyname' => $key,
                'type' => $config['type'] ?? 'string',
                'defval' => $this->convertDefval($config['defval'] ?? '', $config['type'] ?? 'string'),
                'required' => isset($config['required']) ? (int) $config['required'] : 0,
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

            // Insert configuration field definition
            $configId = $this->getFluentPDO()
                ->insertInto('conffield', $configData)
                ->execute();

            // TODO: Save localized descriptions when conffield_translations table is created
            // For now, we're using the default language description in the main table
        }
    }

    /**
     * Convert default value based on type.
     *
     * @param mixed $defval
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
}
