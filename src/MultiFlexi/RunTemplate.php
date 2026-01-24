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

use MultiFlexi\Zabbix\Request\Metric as ZabbixMetric;
use MultiFlexi\Zabbix\Request\Packet as ZabbixPacket;

/**
 * @author vitex
 */
class RunTemplate extends \MultiFlexi\DBEngine
{
    public Application $application;

    /**
     * Default limit for listing queries.
     */
    public ?int $limit = null;

    /**
     * @var array the environment for the run template
     */
    private array $environment;

    /**
     * @param mixed $identifier
     */
    public function __construct($identifier = null, array $options = [])
    {
        $this->nameColumn = 'name';
        $this->myTable = 'runtemplate';
        $this->lastModifiedColumn = 'DatSave';
        $this->createColumn = 'DatCreate';
        parent::__construct($identifier, $options);
    }

    /**
     * Get id by App & Company.
     *
     * SELECT runtemplate.id, runtemplate.interv, runtemplate.prepared, apps.name AS app, company.name AS company   FROM runtemplate LEFT JOIN apps ON runtemplate.app_id=apps.id LEFT JOIN company ON runtemplate.company_id=company.id;
     *
     * @deprecated since version 2.0
     */
    public function runTemplateID(int $appId, int $companyId): int
    {
        $runTemplateId = (int) $this->listingQuery()->where('company_id='.$companyId.' AND app_id='.$appId)->select('id', true)->fetchColumn();

        return $runTemplateId ? $runTemplateId : $this->dbsync(['app_id' => $appId, 'company_id' => $companyId, 'interv' => 'n']);
    }

    /**
     * Set APP State.
     */
    public function setState(bool $state): bool
    {
        if ($state === false) {
            $this->setDataValue('interv', 'n');
        } else {
            $this->setDataValue('cron', '');
        }

        $changed = $this->dbsync();

        if (\Ease\Shared::cfg('ZABBIX_SERVER')) {
            $this->notifyZabbix($this->getData());
        }

        return $changed;
    }

    public function performInit(): void
    {
        $app = new Application((int) $this->getDataValue('app_id'));

        //        $this->setEnvironment();
        if (empty($app->getDataValue('setup')) === false) {
            $this->setDataValue('prepared', 0);
            $this->dbsync();
        }
        //        $app->runInit();
    }

    /**
     * Delete record ignoring interval.
     *
     * @param mixed $data
     */
    public function deleteFromSQL($data = null): int
    {
        if (null === $data) {
            $data = $this->getData();
        }

        $arnold = new \MultiFlexi\ActionConfig();
        $arnold->deleteFromSQL(['runtemplate_id' => $this->getMyKey()]);

        $configurator = new \MultiFlexi\Configuration();
        $configurator->deleteFromSQL(['runtemplate_id' => $this->getMyKey()]);

        $rtpl = new \MultiFlexi\RunTplCreds();
        $rtpl->deleteFromSQL(['runtemplate_id' => $this->getMyKey()]);
        $rtpl->setmyTable('runtemplate_topics');
        $rtpl->deleteFromSQL(['runtemplate_id' => $this->getMyKey()]);

        return (int) parent::deleteFromSQL($data);
    }

    public function getCompanyEnvironment()
    {
        $connectionData = $this->getAppInfo();

        if ($connectionData['type']) {
            $platformHelperClass = '\\MultiFlexi\\'.$connectionData['type'].'\\Company';
            $platformHelper = new $platformHelperClass($connectionData['company_id'], $connectionData);

            return $platformHelper->getEnvironment();
        }

        return [];
    }

    /**
     * Get company templates.
     */
    public function getCompanyTemplates(int $companyId): \Envms\FluentPDO\Queries\Select
    {
        return $this->listingQuery()
            ->select(['apps.name AS app_name', 'apps.description', 'apps.homepage', 'apps.uuid'])
            ->leftJoin('apps ON apps.id = runtemplate.app_id')
            ->where('company_id', $companyId);
    }

    /**
     * Get apps for given company sorted by.
     *
     * @return array<array>
     */
    public function getCompanyRunTemplatesByInterval(int $companyId)
    {
        $runtemplates = [
            'c' => [],
            'i' => [],
            'h' => [],
            'd' => [],
            'w' => [],
            'm' => [],
            'y' => [],
        ];

        foreach ($this->getCompanyTemplates($companyId)->fetchAll() as $template) {
            $runtemplates[$template['interv']][$template['id']] = $template;
        }

        return $runtemplates;
    }

    public static function getIntervalEmoji(string $interval): string
    {
        $emojis = [
            'c' => '🔵',
            'n' => '🔴',
            'i' => '⏳',
            'h' => '🕰️',
            'd' => '☀️',
            'w' => '📅',
            'm' => '🌛',
            'y' => '🎆',
            '' => '',
        ];

        return \array_key_exists($interval, $emojis) ? $emojis[$interval] : '';
    }

    /**
     * @return array
     */
    public function getAppEnvironment()
    {
        $appInfo = $this->getAppInfo() ?: ['company_id' => null, 'app_id' => null];
        $jobber = new Job();
        $jobber->company = new Company((int) $appInfo['company_id']);
        $jobber->application = new Application((int) $appInfo['app_id']);
        $jobber->runTemplate = $this;

        return $jobber->getFullEnvironment();
    }

    public function loadEnvironment(): void
    {
        $environment = $this->getRuntemplateEnvironment();

        if ($environment instanceof \MultiFlexi\ConfigFields) {
            $environment = $environment->getFields();
        }

        $this->setEnvironment($environment);
    }

    /**
     * @return array
     */
    public function getAppInfo()
    {
        return $this->listingQuery()
            ->select(['apps.*'])
            ->select(['apps.id AS apps_id'])
            ->select(['apps.name AS app_name'])
            ->select(['runtemplate.name AS runtemplate_name'])
            ->select(['company.*'])
            ->select(['servers.*'])
            ->select(['c.config_type AS type'])
            ->where([$this->getMyTable().'.'.$this->getKeyColumn() => $this->getMyKey()])
            ->leftJoin('apps ON apps.id = runtemplate.app_id')
            ->leftJoin('company ON company.id = runtemplate.company_id')
            ->leftJoin('servers ON servers.id = company.server')
            ->leftJoin('configuration c ON c.runtemplate_id = runtemplate.id')
            ->fetch();
    }

    /**
     * All RunTemplates for GivenCompany.
     */
    public function getRunTemplatesForCompany(int $companyID): array
    {
        $query = $this->listingQuery()
            ->select(['app_id', 'interv', 'id'])
            ->where(['company_id' => $companyID]);

        return $query->fetchAll() ?: [];
    }

    /**
     * All Active RunTemplates for GivenCompany.
     */
    public function getActiveRunTemplatesForCompany(int $companyID): array
    {
        $templates = $this->getRunTemplatesForCompany($companyID);

        return array_filter($templates, static function ($template) {
            return $template['active'] ?? false;
        });
    }

    /**
     * Set Provision state.
     *
     * @param null|int $status 0: Unprovisioned, 1: provisioned,
     *
     * @return bool save status
     */
    public function setProvision($status)
    {
        return $this->dbsync(['prepared' => $status]);
    }

    public function setPeriods(int $companyId, array $runtemplateIds, string $interval): void
    {
        foreach ($runtemplateIds as $runtemplateId) {
            $this->updateToSQL(['interv' => $interval], ['id' => $runtemplateId]);
            //                if (\Ease\Shared::cfg('ZABBIX_SERVER')) {
            //                    $this->notifyZabbix(['id' => $appInserted, 'app_id' => $appId, 'company_id' => $companyId, 'interv' => $interval]);
            //                }
            //                $this->addStatusMessage(sprintf(_('Application %s in company %s assigned to interval %s'), $appId, $companyId, $interval));
        }
    }

    public function notifyZabbix(array $jobInfo)
    {
        $zabbixSender = new ZabbixSender(\Ease\Shared::cfg('ZABBIX_SERVER'));
        $hostname = \Ease\Shared::cfg('ZABBIX_HOST');
        $company = new Company($jobInfo['company_id']);
        $application = new Application($jobInfo['app_id']);

        $packet = new ZabbixPacket();
        $packet->addMetric((new ZabbixMetric('job-['.$company->getDataValue('code').'-'.$application->getDataValue('code').'-'.$jobInfo['id'].'-interval]', $jobInfo['interv']))->withHostname($hostname));

        try {
            $zabbixSender->send($packet);
        } catch (\Exception $ex) {
        }

        $packet = new ZabbixPacket();
        $packet->addMetric((new ZabbixMetric('job-['.$company->getDataValue('code').'-'.$application->getDataValue('code').'-'.$jobInfo['id'].'-interval_seconds]', (string) Job::codeToSeconds($jobInfo['interv'])))->withHostname($hostname));

        try {
            $result = $zabbixSender->send($packet);
        } catch (\Exception $ex) {
        }

        return $result;
    }

    /**
     * Return only key=>value pairs.
     *
     * @return array
     */
    public static function stripToValues(array $envData)
    {
        $env = [];

        foreach ($envData as $key => $data) {
            $env[$key] = $data['value'];
        }

        return $env;
    }

    /**
     * Actions available with flag when performed in case of success of failure.
     *
     * @return array<array>
     */
    public function getPostActions()
    {
        $actions = [];
        $s = $this->getDataValue('success') ? unserialize($this->getDataValue('success')) : [];
        $f = $this->getDataValue('fail') ? unserialize($this->getDataValue('fail')) : [];

        foreach ($s as $action => $enabled) {
            $actions[$action]['success'] = $enabled;
        }

        foreach ($f as $action => $enabled) {
            $actions[$action]['fail'] = $enabled;
        }

        return $actions;
    }

    public function getApplication(): Application
    {
        $appId = $this->getDataValue('app_id');

        if (isset($this->application) === false) {
            $this->application = new Application($appId);
        }

        if ($this->application->getMyKey() !== $appId) {
            $this->application->loadFromSQL($appId);
        }

        return $this->application;
    }

    /**
     * @return \MultiFlexi\Company
     */
    public function getCompany()
    {
        return new Company($this->getDataValue('company_id'));
    }

    public function getRuntemplateEnvironment(): ConfigFields
    {
        $runtemplateEnv = new ConfigFields(sprintf(_('RunTemplate %s'), $this->getRecordName()));
        $configurator = new Configuration();
        $cfg = $configurator->listingQuery()->select(['name', 'value', 'config_type'], true)->where(['runtemplate_id' => $this->getMyKey()])->fetchAll('name');

        foreach ($cfg as $conf) {
            $field = new ConfigField($conf['name'], \MultiFlexi\Conffield::fixType($conf['config_type']), $conf['name']);
            $field->setValue($conf['value']);
            $runtemplateEnv->addField($field);
        }

        return $runtemplateEnv;
    }

    public function setRuntemplateEnvironment(ConfigFields $env): bool
    {
        $companies = new Company((int) $this->getDataValue('company_id'));
        $app = new Application((int) $this->getDataValue('app_id'));

        $configurator = new \MultiFlexi\Configuration([
            'runtemplate_id' => $this->getMyKey(),
            'app_id' => $app->getMyKey(),
            'company_id' => $companies->getMyKey(),
        ], ['autoload' => false]);

        if ($configurator->takeData($env->getEnvArray()) && null !== $configurator->saveToSQL()) {
            $configurator->addStatusMessage(_('Config fields Saved').' '.implode(',', array_keys($env->getEnvArray())), 'success');
            $result = true;
        } else {
            $configurator->addStatusMessage(_('Error saving Config fields'), 'error');
            $result = false;
        }

        return $result;
    }

    public function setEnvironment(array $properties): bool
    {
        $companies = new Company((int) $this->getDataValue('company_id'));
        $app = new Application((int) $this->getDataValue('app_id'));

        $configurator = new \MultiFlexi\Configuration([
            'runtemplate_id' => $this->getMyKey(),
            'app_id' => $app->getMyKey(),
            'company_id' => $companies->getMyKey(),
        ], ['autoload' => false]);

        if ($app->checkRequiredFields($properties, true) && $configurator->takeData($properties) && null !== $configurator->saveToSQL()) {
            $configurator->addStatusMessage(_('Config fields Saved').' '.implode(',', array_keys($properties)), 'success');
            $result = true;
        } else {
            $configurator->addStatusMessage(_('Error saving Config fields'), 'error');
            $result = false;
        }

        return $result;
    }

    public static function actionIcons(?array $actions, array $properties = []): \Ease\Html\SpanTag
    {
        $icons = new \Ease\Html\SpanTag(null, $properties);

        if (\is_array($actions)) {
            foreach ($actions as $class => $status) {
                if ($status === true) {
                    $actionClass = '\\MultiFlexi\\Ui\\Action\\'.$class;
                    $icons->addItem(new \Ease\Html\ImgTag($actionClass::logo(), $actionClass::name(), ['title' => $actionClass::name()."\n".$actionClass::description(), 'style' => 'height: 15px;']));
                }
            }
        }

        return $icons;
    }

    /**
     * export .env file content.
     */
    public function envFile(): string
    {
        $launcher[] = '# runtemplate #'.$this->getMyKey().' environment '.$this->getRecordName();
        $launcher[] = '# '.\Ease\Shared::appName().' v'.\Ease\Shared::AppVersion().' Generated '.(new \DateTime())->format('Y-m-d H:i:s').' for company: '.$this->getCompany()->getDataValue('name');
        $launcher[] = '';

        foreach ($this->getEnvironment()->getEnvArray() as $key => $value) {
            $launcher[] = $key."='".$value."'";
        }

        return implode("\n", $launcher);
    }

    public function getAssignedCredentials(): array
    {
        $crdHlpr = new \MultiFlexi\RunTplCreds();

        return $crdHlpr->getCredentialsForRuntemplate($this->getMyKey())->fetchAll();
    }

    /**
     * @return array<string>
     */
    public function getRequirements(): array
    {
        return $this->getApplication()->getRequirements();
    }

    public function getEnvironment(): ConfigFields
    {
        $runTemplateFields = new ConfigFields(sprintf(_('RunTemplate #%s Environment'), $this->getMyKey()));

        $runTemplateFields->addFields($this->getApplication()->getEnvironment());

        $runTemplateFields->addFields($this->getRuntemplateEnvironment());

        $runTemplateFields->addFields($this->legacyCredentialsEnvironment());

        $runTemplateFields->addFields($this->credentialsEnvironment());

        return $runTemplateFields;
    }

    public function credentialsEnvironment(): ConfigFields
    {
        $runTemplateCredTypeFields = new ConfigFields(_('RunTemplate CredentialType Values'));

        foreach ($this->getCredentialsAssigned() as $requirement => $credentialData) {
            $credentor = new Credential($credentialData['credentials_id']);
            $runTemplateCredTypeFields->addFields($credentor->query());
        }

        return $runTemplateCredTypeFields;
    }

    public function getCredentialsAssigned(): array
    {
        $credentor = new RunTplCreds();

        return $credentor->listingQuery()->select(['credentials.name AS credential_name', 'credential_type.name AS credential_type_name', 'credential_type.class AS credential_type_class', 'credential_type.uuid AS credential_type_uuid', 'credential_type.logo AS credential_type_logo'])->where('runtemplate_id', $this->getMyKey())->leftJoin('credentials ON credentials.id = runtplcreds.credentials_id')->leftJoin('credential_type ON credential_type.id = credentials.credential_type_id')->fetchAll('credential_type_class');
    }

    public function isScheduled(\DateTime $startTime): bool
    {
        $scheduler = new Scheduler();
        $scheduledJobs = $scheduler->listingQuery()
            ->where('job', $this->getMyKey())
            ->where('after', $startTime->format('Y-m-d H:i:s'))
            ->fetchAll();

        return !empty($scheduledJobs);
    }

    public function getScheduledJobs(): array
    {
        $scheduler = new Scheduler();

        return $scheduler->listingQuery()
            ->where('job', $this->getMyKey())
            ->leftJoin('runtemplate ON runtemplate.id = job.runtemplate_id')
            ->select(['runtemplate.name AS runtemplate_name', 'runtemplate.id AS runtemplate_id', 'runtemplate.last_schedule'])
            ->fetchAll();
    }

    /**
     * Placeholder for legacy credentials environment.
     *
     * @return array
     */
    public function legacyCredentialsEnvironment(): \MultiFlexi\ConfigFields
    {
        return new \MultiFlexi\ConfigFields('Legacy Credentials');
        // Populate $configFields with necessary fields.
    }
}
