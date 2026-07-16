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

use Cron\CronExpression;
use MultiFlexi\Reporting\JobReport;
use MultiFlexi\Zabbix\Request\Metric as ZabbixMetric;
use MultiFlexi\Zabbix\Request\Packet as ZabbixPacket;

/**
 * Description of Job.
 *
 * @author vitex
 */
class Job extends DBEngine
{
    public const string SCHEDULE_TYPE_ADHOC = 'adhoc';
    public const string SCHEDULE_TYPE_COMMAND_LINE = 'CommandLine';

    public executor $executor;
    public static array $intervalCode = [
        'y' => 'yearly',
        'm' => 'monthly',
        'w' => 'weekly',
        'd' => 'daily',
        'h' => 'hourly',
        'i' => 'minutly',
        'n' => 'disabled',
        'c' => 'custom',
    ];
    public static array $intervalSecond = [
        'n' => '0',
        'i' => '60',
        'h' => '3600',
        'd' => '86400',
        'w' => '604800',
        'm' => '2629743',
        'y' => '31556926',
    ];
    public static $intervalZabbix = [
        'n' => '0',
        'i' => 'm0-59',
        'h' => 'h0-23',
        'd' => 'm0',
        'w' => 'wd1',
        'm' => 'md1',
        'y' => '31556926',
    ];
    protected ?ZabbixSender $zabbixSender = null;
    protected ?Application $application = null;
    protected ?Company $company = null;
    protected ?RunTemplate $runTemplate;
    protected ?User $user = null;

    /**
     * Environment for Current Job.
     */
    private ConfigFields $environment;

    /**
     * Executed command line.
     */
    // private string $commandline;
    private JobReport $reporter;

    /**
     * Job Object.
     *
     * @param int   $identifier
     * @param array $options
     */
    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'job';
        $this->nameColumn = '';
        $this->runTemplate = new RunTemplate();
        $this->environment = new ConfigFields(_('Job Env'));

        $this->reporter = new JobReport();

        if (\Ease\Shared::cfg('ZABBIX_SERVER')) {
            $this->zabbixSender = new ZabbixSender(\Ease\Shared::cfg('ZABBIX_SERVER'));
        }

        parent::__construct($identifier, $options);
        $this->setObjectName();
    }

    /**
     * Create New Job Record in database.
     *
     * @param ConfigFields $environment  Environment prepared for Job execution
     * @param \DateTime    $scheduled    Schedule Timestamp
     * @param string       $executor     Chosen Executor class name
     * @param string       $scheduleType Schedule type Info
     *
     * @return int new job ID
     */
    public function newJob(RunTemplate $runtemplate, ConfigFields $environment, \DateTime $scheduled, $executor = 'Native', $scheduleType = 'adhoc')
    {
        $this->runTemplate = $runtemplate;
        $this->application = $this->getRunTemplate()->getApplication();
        $this->company = $this->getRunTemplate()->getCompany();

        // Ensure we always have a valid user ID
        $launchedByUserId = \Ease\Shared::user()->getMyKey();

        if (!$launchedByUserId) {
            throw new \Ease\Exception(_('Cannot create job without authenticated user. User ID is required.'));
        }

        $companyId = $this->getRunTemplate()->getDataValue('company_id');

        if (empty($companyId) && $this->company) {
            $companyId = $this->company->getMyKey();
        }

        $this->setData([
            'runtemplate_id' => $runtemplate->getMyKey(),
            'company_id' => $companyId,
            'app_id' => $this->getRunTemplate()->getDataValue('app_id'),
            'env' => \serialize($environment),
            'exitcode' => null,
            'schedule' => $scheduled->format('Y-m-d H:i:s'),
            'schedule_type' => $scheduleType,
            'executor' => $executor,
            'launched_by' => $launchedByUserId,
            'task_id' => $this->getDataValue('task_id'),
        ], true);

        if (null === $this->getDataValue('company_id')) {
            $this->addStatusMessage(sprintf(_('Creating Job for Runtemplate #%d with NULL company_id. RT data: %s'), $runtemplate->getMyKey(), json_encode($runtemplate->getData())), 'warning');
        }

        $jobId = $this->insertToSQL();

        $environment->addField((new ConfigField('MULTIFLEXI_JOB_ID', 'integer', _('Job ID'), _('Number of job'), '', (string) $jobId))->setSource(\Ease\Euri::fromObject($this)));

        $this->updateToSQL(['env' => serialize($environment), 'command' => $this->getCmdline()], ['id' => $jobId]);

        $this->reporter->setDataValue('phase', 'created');
        $this->reporter->setDataValue('job_id', $jobId);
        $this->reporter->setDataValue('app_id', $this->getApplication()->getMyKey());
        $this->reporter->setDataValue('app_name', $this->getApplication()->getDataValue('name'));
        $this->reporter->setDataValue('company_id', $this->getRunTemplate()->getDataValue('company_id'));
        $this->reporter->setDataValue('company_name', $this->getCompany()->getDataValue('name'));
        $this->reporter->setDataValue('company_code', $this->getCompany()->getDataValue('slug'));
        $this->reporter->setDataValue('runtemplate_id', $runtemplate->getMyKey());
        $this->reporter->setDataValue('runtemplate_name', $this->getRuntemplate()->getRecordName());
        $this->reporter->setDataValue('executor', $executor);

        if (\Ease\Shared::cfg('ZABBIX_SERVER')) {
            $this->reportToZabbix('multiflexi.job.lld', null, true);
        }

        return $jobId;
    }

    public function restoreEnvironment(string $env): void
    {
        if (\Ease\Functions::isSerialized($env)) {
            $envUnserialized = unserialize($env) ?: new ConfigFields('');

            if (\is_object($envUnserialized)) {
                $this->environment->addFields($envUnserialized);
            } else {
                $this->addStatusMessage(_('Envirnoment unserialization Error'), 'error');
            }
        }
    }

    public function updateEnvironment(ConfigFields $environment): void
    {
        if (empty($this->environment)) {
            //   $this->loadJobEnvironment();
        }

        $this->environment->addFields($environment);
        $this->storeEnvironment($this->environment);
    }

    public function storeEnvironment(ConfigFields $environment): int
    {
        return $this->saveToSQL(['env' => serialize($environment)], ['id' => $this->getMyKey()]);
    }

    /**
     * Begin the Job.
     *
     * @return int Job ID
     */
    public function runBegin()
    {
        $appId = $this->getApplication()->getMyKey();
        $companyId = $this->getCompany()->getMyKey();
        $this->setObjectName();
        $sqlLogger = LogToSQL::singleton();
        $sqlLogger->setCompany($companyId);
        $sqlLogger->setApplication($appId);

        if (null === $this->runTemplate) {
            throw new \Ease\Exception(_('No RunTemplate prepared'));
        }

        if ($this->runTemplate->getMyKey() === 0) {
            throw new \Ease\Exception(_('No RunTemplate prepared'));
        }

        $this->environmentCheck();
        $this->ensureFiles();
        // TODO: Refresh Expirable Credentials here
        self::sanitizeResultFile($this->environment, $this->application);
        $this->environment->applyMacros();

        if (isset($this->executor) === false) {
            $executorClass = '\\MultiFlexi\\Executor\\'.$this->getDataValue('executor');
            $this->executor = new $executorClass($this);
        } else {
            $this->executor->setJob($this);
        }

        $jobId = $this->getMyKey();

        $this->reporter->setDataValue('phase', 'jobStart');
        $this->reporter->setDataValue('job_id', $this->getMyKey());
        $this->reporter->setDataValue('executor', $this->getDataValue('executor'));
        $this->reporter->setDataValue('begin', (new \DateTime())->format('Y-m-d H:i:s'));
        $this->reporter->setDataValue('interval', $this->getRuntemplate()->getDataValue('cron'));
        $this->reporter->setDataValue('interval_seconds', Scheduler::codeToSeconds($this->getRuntemplate()->getDataValue('interv')));
        $this->reporter->setDataValue('app_name', $this->getApplication()->getRecordName());
        $this->reporter->setDataValue('app_id', $this->getApplication()->getMyKey());
        $this->reporter->setDataValue('runtemplate_id', $this->getRuntemplate()->getMyKey());
        $this->reporter->setDataValue('runtemplate_name', $this->getRuntemplate()->getRecordName());
        $this->reporter->setDataValue('launched_by_id', (int) \Ease\Shared::user()->getMyKey());
        $this->reporter->setDataValue('launched_by', empty(\Ease\Shared::user()->getUserLogin()) ? 'cron' : \Ease\Shared::user()->getUserLogin());

        $this->reporter->setDataValue('scheduled', $this->getDataValue('schedule'));
        $this->reporter->setDataValue('schedule_type', $this->getDataValue('schedule_type'));
        $this->reporter->setDataValue('company_id', $this->getCompany()->getMyKey());
        $this->reporter->setDataValue('company_name', $this->getCompany()->getDataValue('name'));
        $this->reporter->setDataValue('company_code', $this->getCompany()->getDataValue('slug'));

        if ($this->getRuntemplate()->getDataValue('cron')) {
            $cron = new CronExpression((string) $this->getRuntemplate()->getDataValue('cron'));
            $startTime = $cron->getNextRunDate(new \DateTime(), 0, true);
        }

        if (\Ease\Shared::cfg('ZABBIX_SERVER')) {
            $this->reportToZabbix('job-['.$this->getCompany()->getDataValue('slug').'-'.$this->getApplication()->getDataValue('code').'-'.$this->getRuntemplate()->getMyKey().']');
        }

        /* Preserve used Envirnoment */
        $beginNow = (new \DateTime())->format('Y-m-d H:i:s');

        $this->updateToSQL([
            'id' => $this->getMyKey(),
            'env' => serialize($this->environment),
            'command' => $this->executor->commandline(),
            'runtemplate_id' => $this->getRuntemplate()->getMyKey(),
            'begin' => new \Envms\FluentPDO\Literal(\Ease\Shared::cfg('DB_CONNECTION') === 'sqlite' ? "date('now')" : 'NOW()'),
        ]);

        // updateToSQL() writes 'begin' straight to the DB via a raw SQL literal,
        // it never touches $this->data, so getDataValue('begin') stayed null for
        // the rest of the process (crashing the OTel export in runEnd()).
        $this->setDataValue('begin', $beginNow);

        // OpenTelemetry metrics export
        if (\Ease\Shared::cfg('OTEL_ENABLED') && class_exists('\\MultiFlexi\\Telemetry\\OtelMetricsExporter')) {
            try {
                $otelExporter = new \MultiFlexi\Telemetry\OtelMetricsExporter();
                $otelExporter->recordJobStart(
                    $jobId,
                    $this->getApplication()->getMyKey(),
                    $this->getApplication()->getRecordName(),
                    $this->getCompany()->getMyKey(),
                    $this->getCompany()->getRecordName(),
                    $this->getRuntemplate()->getMyKey(),
                    $this->getRuntemplate()->getRecordName(),
                );
            } catch (\Throwable $e) {
                $this->addStatusMessage(sprintf(_('OTel export failed: %s'), $e->getMessage()), 'debug');
            }
        }

        return $jobId;
    }

    /**
     * Action at Job run finish.
     *
     * @param string $stdout Job Output
     * @param string $stderr Job error output
     *
     * @return int
     */
    public function runEnd(int $statusCode, string $stdout, string $stderr)
    {
        $sqlLogger = LogToSQL::singleton();
        $sqlLogger->setCompany(0);
        $sqlLogger->setApplication(0);

        foreach ($this->application->getResultFiles() as $resultFile) {
            $description = $this->getArtifactDescription($resultFile);
            $this->storeJobArtifact($resultFile, $description);
        }

        $resultfile = $this->environment->getFieldByCode('RESULT_FILE') ? $this->environment->getFieldByCode('RESULT_FILE')->getValue() : '';

        $this->reporter->setDataValue('phase', 'jobDone');
        $this->reporter->setDataValue('job_id', $this->getMyKey());
        $this->reporter->setDataValue('data', file_exists($resultfile) ? file_get_contents($resultfile) : '');
        $this->reporter->setDataValue('version', $this->getApplication()->getDataValue('version'));
        $this->reporter->setDataValue('exitcode', $statusCode);
        $this->reporter->setDataValue('exitcode_description', $this->executor->meaning().' '.$this->getApplication()->exitCodeDescription($statusCode));
        $this->reporter->setDataValue('scheduled', $this->getDataValue('schedule'));
        $this->reporter->setDataValue('end', (new \DateTime())->format('Y-m-d H:i:s'));
        $this->reporter->setDataValue('runtemplate_id', $this->getRuntemplate()->getMyKey());
        $this->reporter->setDataValue('result_file', $resultfile);
        $this->reporter->setDataValue('pid', $this->executor->getPid());

        if (\Ease\Shared::cfg('ZABBIX_SERVER')) {
            $this->reportToZabbix('job-['.$this->getCompany()->getDataValue('slug').'-'.$this->getApplication()->getDataValue('code').'-'.$this->getRuntemplate()->getMyKey().']');
        }

        $this->setData([
            'pid'      => $this->executor->getPid(),
            'command'  => $this->executor->commandline(),
            'exitcode' => $statusCode,
        ]);

        $this->performActions($statusCode === 0 ? 'success' : 'fail');

        // OpenTelemetry metrics export
        if (\Ease\Shared::cfg('OTEL_ENABLED') && class_exists('\\MultiFlexi\\Telemetry\\OtelMetricsExporter')) {
            try {
                $beginValue = $this->getDataValue('begin');
                $begin = $beginValue ? new \DateTime($beginValue) : new \DateTime();
                $end = new \DateTime();
                $duration = $end->getTimestamp() - $begin->getTimestamp();

                $otelExporter = new \MultiFlexi\Telemetry\OtelMetricsExporter();
                $otelExporter->recordJobEnd($statusCode, (float) $duration, $this->reporter->getData());
                $otelExporter->flush();
            } catch (\Throwable $e) {
                $this->addStatusMessage(sprintf(_('OTel export failed: %s'), $e->getMessage()), 'debug');
            }
        }

        // Prepare runtemplate updates
        $rtUpdate = [];

        // Only update scheduling timestamps for cron-scheduled jobs
        // Ad-hoc jobs (manually triggered from web/CLI/API) must not affect automatic scheduling
        $scheduleType = $this->getDataValue('schedule_type');

        if ($scheduleType !== self::SCHEDULE_TYPE_ADHOC && $scheduleType !== self::SCHEDULE_TYPE_COMMAND_LINE) {
            $rtUpdate['next_schedule'] = null;
            $rtUpdate['last_schedule'] = $this->getRunTemplate()->getDataValue('next_schedule');
        }

        // Always update job counters (for all job types)
        if ($statusCode) {
            $rtUpdate['failed_jobs_count'] = $this->getRunTemplate()->getDataValue('failed_jobs_count') + 1;
        } else {
            $rtUpdate['successfull_jobs_count'] = $this->getRunTemplate()->getDataValue('successfull_jobs_count') + 1;
        }

        if (!empty($rtUpdate)) {
            $this->getRunTemplate()->updateToSQL($rtUpdate, ['id' => $this->getRunTemplate()->getMyKey()]);
        }

        // TODO
        //        if (file_exists($resultfile)) {
        //            unlink($resultfile);
        //        }

        $result = $this->updateToSQL([
            'pid'         => $this->executor->getPid(),
            'end'         => new \Envms\FluentPDO\Literal(\Ease\Shared::cfg('DB_CONNECTION') === 'sqlite' ? "date('now')" : 'NOW()'),
            'app_version' => $this->getApplication()->getDataValue('version'),
            'exitcode'    => $statusCode,
        ], ['id' => $this->getMyKey()]);

        $this->updateTaskState($statusCode);

        return $result;
    }

    /**
     * Update the parent Task state after job completion.
     * Schedules a retry job when the budget allows.
     */
    private function updateTaskState(int $statusCode): void
    {
        $taskId = (int) $this->getDataValue('task_id');

        if ($taskId === 0) {
            return;
        }

        $task = new Task($taskId);

        if ($statusCode === 0) {
            $task->fulfill($this);

            return;
        }

        $task->incrementAttempts();

        $nextRetry = $task->getNextRetryTime();

        if ($nextRetry !== null) {
            $retryJob = new self();
            $retryJob->setDataValue('task_id', $taskId);
            $retryJob->prepareJob(
                $this->getRunTemplate(),
                $this->environment,
                $nextRetry,
                $this->getDataValue('executor') ?? 'Native',
                $this->getDataValue('schedule_type') ?? 'cron',
            );
        } else {
            $task->markFailed();
        }
    }

    /**
     * Check App Provisioning state.
     */
    public function isProvisioned(int $runtemplateId): bool
    {
        $appCompany = new RunTemplate($runtemplateId);
        $appInfo = $appCompany->getAppInfo();

        return (bool) $appInfo['prepared'];
    }

    /**
     * @see https://datatables.net/examples/advanced_init/column_render.html
     *
     * @return string Column rendering
     */
    public function columnDefs()
    {
        return <<<'EOD'

"columnDefs": [
           // { "visible": false,  "targets": [ 0 ] }
        ]
,

EOD;
    }

    /**
     * Prepare Job for run.
     *
     * @param RunTemplate  $runTemplate RunTempate to use
     * @param ConfigFields $envOverride use to change default env [env with info]
     * @param \DateTime    $scheduled   Time to launch
     * @param string       $executor    Executor Class Name
     *
     * @return string ????
     */
    public function prepareJob(RunTemplate $runTemplate, ConfigFields $envOverride, \DateTime $scheduled, string $executor = 'Native', string $scheduleType = 'adhoc'): string
    {
        $outline = '';
        $this->runTemplate = $runTemplate;
        $appId = $this->getRunTemplate()->getDataValue('app_id');
        $companyId = $this->getRunTemplate()->getDataValue('company_id');

        $this->application = $this->getRunTemplate()->getApplication();
        LogToSQL::singleton()->setApplication($appId);

        $this->company = $this->getRunTemplate()->getCompany();
        $this->setDataValue('executor', $executor);

        $this->setupEnvironment($envOverride);
        $createdJobId = null;
        $restoreNextSchedule = $scheduleType !== self::SCHEDULE_TYPE_ADHOC && $scheduleType !== self::SCHEDULE_TYPE_COMMAND_LINE;
        $previousNextSchedule = $restoreNextSchedule ? $runTemplate->getDataValue('next_schedule') : null;
        $pdo = $this->getPdo();
        $transactionStarted = false;

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $transactionStarted = true;
        }

        try {
            $createdJobId = $this->newJob($runTemplate, $this->environment, $scheduled, $executor, $scheduleType);
            $this->loadFromSQL($createdJobId);

            $this->reporter->setDataValue('phase', 'prepared');
            $this->reporter->setDataValue('job_id', $this->getMyKey());
            $this->reporter->setDataValue('app_id', $appId);
            $this->reporter->setDataValue('app_name', $this->getApplication()->getDataValue('name'));
            $this->reporter->setDataValue('begin', null);
            $this->reporter->setDataValue('end', null);
            $this->reporter->setDataValue('scheduled', $scheduled->format('Y-m-d H:i:s'));
            $this->reporter->setDataValue('schedule_type', $scheduleType);
            $this->reporter->setDataValue('company_id', $companyId);
            $this->reporter->setDataValue('company_name', $this->getCompany()->getDataValue('name'));
            $this->reporter->setDataValue('company_code', $this->getCompany()->getDataValue('code'));
            $this->reporter->setDataValue('runtemplate_id', $runTemplate->getMyKey());
            $this->reporter->setDataValue('exitcode', null);
            $this->reporter->setDataValue('executor', $executor);
            $this->reporter->setDataValue('launched_by_id', (int) \Ease\Shared::user()->getMyKey());
            $this->reporter->setDataValue('launched_by', empty(\Ease\Shared::user()->getUserLogin()) ? 'cron' : \Ease\Shared::user()->getUserLogin());
            $this->reporter->setDataValue('interval', $runTemplate->getDataValue('interv'));
            $this->reporter->setDataValue('interval_seconds', Scheduler::codeToSeconds($runTemplate->getDataValue('interv')));

            if (\Ease\Shared::cfg('ZABBIX_SERVER')) {
                // TODO $this->reportToZabbix('job-['.$this->getCompany()->getDataValue('slug').'-'.$this->getApplication()->getDataValue('code').'-'.$this->getRuntemplate()->getMyKey().']');
            }

            // Automatically schedule the job for execution
            $this->scheduleJobRun($scheduled);

            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($createdJobId !== null && !$transactionStarted) {
                try {
                    $this->deleteFromSQL(['id' => $createdJobId]);
                } catch (\Throwable) {
                    $this->addStatusMessage(sprintf(_('Failed to clean up orphaned job #%d'), $createdJobId), 'error');
                }
            }

            if ($restoreNextSchedule) {
                try {
                    $runTemplate->updateToSQL(['next_schedule' => $previousNextSchedule], ['id' => $runTemplate->getMyKey()]);
                } catch (\Throwable) {
                    $this->addStatusMessage(sprintf(_('Failed to restore next_schedule for runtemplate #%d'), $runTemplate->getMyKey()), 'error');
                }
            }

            throw $exception;
        }

        return $outline;
    }

    /**
     * Schedule job execution.
     *
     * @return int schedule ID
     */
    public function scheduleJobRun(\DateTime $when): int
    {
        $this->addStatusMessage(_('Scheduling job').': '.$when->format('Y-m-d H:i:s'));
        $scheduler = new Scheduler();

        return $scheduler->addJob($this, $when);
    }

    /**
     * Report a credential availability failure to all configured observability channels
     * (SQL log, Zabbix, OpenTelemetry) without actually running the job.
     *
     * Called from the executor when a Phase 2 check blocks or defers a job.
     */
    public function reportCredentialBlocked(\MultiFlexi\CredentialCheckResult $result, string $credentialName): void
    {
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        $this->reporter->setDataValue('phase', 'credentialBlocked');
        $this->reporter->setDataValue('job_id', $this->getMyKey());
        $this->reporter->setDataValue('begin', $now);
        $this->reporter->setDataValue('end', $now);
        $this->reporter->setDataValue('exitcode', 75); // EX_TEMPFAIL
        $this->reporter->setDataValue('exitcode_description', sprintf(
            _('Credential %s not available (%s): %s'),
            $credentialName,
            $result->state->value,
            $result->message,
        ));

        $runTemplate = $this->getRuntemplate();

        if ($runTemplate !== null) {
            $this->reporter->setDataValue('runtemplate_id', $runTemplate->getMyKey());
            $this->reporter->setDataValue('runtemplate_name', $runTemplate->getRecordName());
        }

        $app     = $this->getApplication();
        $company = $this->getCompany();

        if ($app) {
            $this->reporter->setDataValue('app_id', $app->getMyKey());
            $this->reporter->setDataValue('app_name', $app->getRecordName());
        }

        if ($company) {
            $this->reporter->setDataValue('company_id', $company->getMyKey());
            $this->reporter->setDataValue('company_name', $company->getDataValue('name'));
            $this->reporter->setDataValue('company_code', $company->getDataValue('slug'));
        }

        $this->addStatusMessage(
            sprintf(_('Job #%d blocked by credential %s (%s): %s'), $this->getMyKey(), $credentialName, $result->state->value, $result->message),
            'warning',
        );

        if (\Ease\Shared::cfg('ZABBIX_SERVER') && $app && $company && $runTemplate) {
            $this->reportToZabbix('job-['.$company->getDataValue('slug').'-'.$app->getDataValue('code').'-'.$runTemplate->getMyKey().']');
        }

        if (\Ease\Shared::cfg('OTEL_ENABLED') && class_exists('\\MultiFlexi\\Telemetry\\OtelMetricsExporter')) {
            try {
                $otelExporter = new \MultiFlexi\Telemetry\OtelMetricsExporter();
                $otelExporter->recordJobEnd(75, 0.0, $this->reporter->getData());
                $otelExporter->flush();
            } catch (\Throwable $e) {
                $this->addStatusMessage(sprintf(_('OTel export failed: %s'), $e->getMessage()), 'debug');
            }
        }
    }

    /**
     * Send Job phase Message to Zabbix.
     *
     * @param string $itemKey destination zabbix item name
     *
     * @return bool send result
     */
    public function reportToZabbix(string $itemKey, ?string $overrideHost = null, bool $lldMode = false): bool
    {
        $packet = new ZabbixPacket();

        $companyHost = $this->getRunTemplate()->getCompany()->getDataValue('zabbix_host');

        $hostname = $overrideHost ?? $companyHost ?? \Ease\Shared::cfg('ZABBIX_HOST', gethostname());

        if ($lldMode) {
            $zabbixMetric = json_encode([array_change_key_case($this->reporter->getData(), \CASE_UPPER)]);
        } else {
            $zabbixMetric = json_encode($this->reporter->getData());
        }

        if ($zabbixMetric) {
            $packet->addMetric((new ZabbixMetric($itemKey, $zabbixMetric))->withHostname($hostname));

            // file_put_contents('/tmp/zabbix-' . $this->zabbixMessageData['phase'] .'-'. $this->getMyKey().'-'. time().'.json' , json_encode($this->zabbixMessageData));

            try {
                $result = $this->zabbixSender->send($packet);

                if ($this->debug) {
                    $this->addStatusMessage('Data Sent To Zabbix: '.$itemKey.' '.json_encode($this->reporter->getData()), 'debug');
                }
            } catch (\Exception $exc) {
                $result = false;
            }
        } else {
            $this->addStatusMessage('Problem Jsonizing of '.serialize($this->reporter->getData()), 'debug');
        }

        return (bool) $result;
    }

    /**
     * Perform Job.
     */
    public function performJob(): void
    {
        $this->runBegin();
        $this->executor->launchJob();
        $this->runEnd($this->executor->getExitCode(), $this->executor->getOutput(), $this->executor->getErrorOutput());
    }

    /**
     * Obtain Full Job Command Line.
     *
     * @return string command line
     */
    public function getCmdline()
    {
        return $this->application->getDataValue('executable').' '.$this->getCmdParams();
    }

    /**
     * Obtain Job Command Line Parameters.
     *
     * @return string command line parameters
     */
    public function getCmdParams()
    {
        $cmdparams = $this->application->getDataValue('cmdparams');

        foreach ($this->environment as $envKey => $field) {
            $value = $field->getValue();

            // Only replace if value is not empty and doesn't contain unresolved macros
            if ($value && !preg_match('/\{[A-Z_]+\}/', (string) $value)) {
                $cmdparams = str_replace('{'.$envKey.'}', str_replace(' ', '\\ ', (string) $value), (string) $cmdparams);
            }
        }

        return $cmdparams;
    }

    /**
     * Obtain Job Output.
     *
     * @return string job output
     */
    public function getOutput(): string
    {
        return (new JobOutputLine())->getOutputString((int) $this->getMyKey(), 'stdout');
    }

    /**
     * Obtain Job Error Output.
     *
     * @return string job StdErr
     */
    public function getErrorOutput(): string
    {
        return (new JobOutputLine())->getOutputString((int) $this->getMyKey(), 'stderr');
    }

    /**
     * Obtain Job output lines of any type (stdout, stderr, info, warning, …).
     */
    public function getOutputByType(string $type): string
    {
        return (new JobOutputLine())->getOutputString((int) $this->getMyKey(), $type);
    }

    public function cleanUp(): void
    {
        // TODO: Delete Uploaded files if any
    }

    /**
     * #Generate Job Launcher.
     *
     * @return string
     */
    public function launcherScript()
    {
        $launcher[] = '#!/bin/bash';
        $launcher[] = '';
        $launcher[] = '# '.\Ease\Shared::appName().' v'.\Ease\Shared::AppVersion().' job #'.$this->getMyKey().' launcher. Generated '.(new \DateTime())->format('Y-m-d H:i:s').' for company: '.$this->company->getDataValue('name');
        $launcher[] = '';
        $environment = $this->getDataValue('env') ? unserialize($this->getDataValue('env')) : [];
        asort($environment);

        foreach ($environment as $key => $envInfo) {
            $launcher[] = '';
            $launcher[] = '# Source '.$envInfo['source'];
            $launcher[] = 'export '.$key."='".$envInfo['value']."'";

            if (\array_key_exists('description', $envInfo)) {
                $launcher[] = '# '.$envInfo['description'];
            }
        }

        $launcher[] = '';
        $launcher[] = $this->application->getDataValue('executable').' '.$this->getCmdParams();

        return implode("\n", $launcher);
    }

    /**
     * Current Job Environment.
     *
     * @return array<string, string>
     */
    public function getEnv(): array
    {
        return $this->environment->getEnvArray();
    }

    /**
     * Gives Full Environment with Full info.
     *
     * @return array Environment with metadata
     */
    public function getModulesEnvironment(): ConfigFields
    {
        $jobEnvironment = new ConfigFields($this->getMyKey() ? \Ease\Euri::fromObject($this) : _('Job environment'));

        \Ease\Functions::loadClassesInNamespace('MultiFlexi\\Env');
        $injectors = \Ease\Functions::classesInNamespace('MultiFlexi\\Env');

        foreach ($injectors as $injector) {
            $injectorClass = '\\MultiFlexi\\Env\\'.$injector;
            $jobEnvironment->addFields((new $injectorClass($this))->getEnvironment());
        }

        return $jobEnvironment;
    }

    /**
     * Sanitize all result file paths in the job environment.
     *
     * Ensures that output file paths referenced by environment variables
     * point to the temp directory. Uses artifact definitions from the
     * application's app_artifacts table to identify which env vars
     * represent result files, with RESULT_FILE as a legacy fallback.
     *
     * @param ConfigFields     $jobEnvironment Job environment to sanitize
     * @param null|Application $application    Application with artifact definitions
     *
     * @return ConfigFields Sanitized job environment
     */
    public static function sanitizeResultFile(ConfigFields $jobEnvironment, ?Application $application = null): ConfigFields
    {
        $sanitizedCodes = [];

        // Legacy: always sanitize RESULT_FILE for backward compatibility
        $resultFileField = $jobEnvironment->getFieldByCode('RESULT_FILE');

        if ($resultFileField && !empty($resultFileField->getValue())) {
            $resultFileField->setValue(self::tmpfilepath($resultFileField->getValue()));
            $sanitizedCodes[] = 'RESULT_FILE';
        }

        // Sanitize env fields whose values match artifact path patterns
        if ($application && $application->getMyKey()) {
            $artifactDefs = $application->getFluentPDO()
                ->from('app_artifacts')
                ->where('app_id', $application->getMyKey())
                ->fetchAll();

            $artifactPatterns = array_filter(array_column($artifactDefs, 'path'));

            if (!empty($artifactPatterns)) {
                foreach ($jobEnvironment as $code => $field) {
                    if (\in_array($code, $sanitizedCodes, true)) {
                        continue;
                    }

                    $value = $field->getValue();

                    if (empty($value)) {
                        continue;
                    }

                    // Skip absolute paths (already resolved) and non-file types
                    if ($value[0] === \DIRECTORY_SEPARATOR) {
                        continue;
                    }

                    if ($field->getType() === 'file-path') {
                        continue; // file-path type is for input files
                    }

                    // Replace unresolved macros {VAR_NAME} with a generic placeholder for matching
                    $matchValue = preg_replace('/\{[A-Z_0-9]+\}/', 'PLACEHOLDER', $value);

                    foreach ($artifactPatterns as $pattern) {
                        if (@preg_match('/'.$pattern.'/', $matchValue) === 1) {
                            $field->setValue(self::tmpfilepath($value));
                            $sanitizedCodes[] = $code;

                            break;
                        }
                    }
                }
            }
        }

        return $jobEnvironment;
    }

    /**
     * Path to tmp/file.
     *
     * @param string $tmpfile raw tmp/file
     *
     * @return string sane tmp/file
     */
    public static function tmpfilepath(string $tmpfile): string
    {
        // Stream wrappers (php://stdout, file://, etc.) are valid PHP I/O targets; leave them as-is.
        if (str_contains($tmpfile, '://')) {
            return $tmpfile;
        }

        if ($tmpfile[0] !== \DIRECTORY_SEPARATOR) {
            $tmpfile = Defaults::$MULTIFLEXI_TMP.\DIRECTORY_SEPARATOR.$tmpfile;
        }

        return $tmpfile === sys_get_temp_dir() ? $tmpfile.\DIRECTORY_SEPARATOR.\Ease\Functions::randomString() : $tmpfile;
    }

    /**
     * Generate Environment for current Job.
     */
    public function compileEnv(): array
    {
        return $this->getModulesEnvironment()->applyMacros()->getEnvArray();
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function takeData(array $data): int
    {
        parent::takeData($data);

        if ($this->getDataValue('env')) {
            $this->restoreEnvironment($this->getDataValue('env'));
        }

        if ($this->getDataValue('executor')) {
            $executorClass = '\\MultiFlexi\\Executor\\'.$this->getDataValue('executor');

            if (class_exists($executorClass)) {
                $this->executor = new $executorClass($this);
            } else {
                $this->addStatusMessage(sprintf(_('Requested Executor %s not availble'), $executorClass), 'warning');
            }
        }

        return \count($data);
    }

    /**
     * export .env file content.
     */
    public function envFile(): string
    {
        $creds = $this->runTemplate->credentialsEnvironment();

        $launcher[] = '# '.\Ease\Shared::appName().' v'.\Ease\Shared::AppVersion().' Job 🏁 #'.$this->getMyKey().' environment. Generated '.(new \DateTime())->format('Y-m-d H:i:s').' for company: '.$this->getCompany()->getDataValue('name');
        $launcher[] = '';

        if ($this->getDataValue('env')) {
            foreach (unserialize($this->getDataValue('env')) as $configFieldName => $configFieldInfo) {
                $field = $creds->getFieldByCode($configFieldName);

                if (null === $field) {
                    $creds->addField(new ConfigField($configFieldName, 'string', $configFieldName, '', '', \is_array($configFieldInfo) ? (string) $configFieldInfo['value'] : $configFieldInfo->getValue()));
                } else {
                    if (\is_object($configFieldInfo)) {
                        $creds->addField($configFieldInfo);
                    } else {
                        $field->setValue($configFieldInfo['value']);
                        $field->setSource($configFieldInfo['source']);
                    }
                }
            }
        }

        foreach ($creds->getEnvArray() as $key => $value) {
            $launcher[$key] = $key."='".$value."'";
        }

        ksort($launcher);

        return implode("\n", $launcher);
    }

    /**
     * Perform Actions For any mode.
     *
     * @param mixed $mode
     * @param string success | fail
     */
    public function performActions($mode): void
    {
        $actions = $this->runTemplate->getDataValue($mode) ? unserialize($this->runTemplate->getDataValue($mode)) : [];
        $modConf = new ModConfig();
        $actConf = new \MultiFlexi\ActionConfig();
        $modConfigs = $actConf->getRuntemplateConfig($this->runTemplate->getMyKey())->where('mode', $mode)->fetchAll();

        foreach ($actions as $action => $enabled) {
            $actionClass = '\\MultiFlexi\\Action\\'.$action;

            if ($enabled && class_exists($actionClass)) {
                $actionHandler = new $actionClass($this->runTemplate);
                $actionHandler->setData($modConf->getModuleConf($action));

                foreach ($modConfigs as $modConfig) {
                    if ($action === $modConfig['module']) {
                        $actionHandler->setDataValue($modConfig['keyname'], $modConfig['value']);
                    }
                }

                $actionHandler->perform($this);
            }
        }
    }

    public function getNextJobId(bool $keepApp = true, bool $keepRuntemplate = true, bool $keepCompnay = true): int
    {
        $condition = [];

        if ($keepApp) {
            $condition['app_id'] = $this->getDataValue('app_id');
        }

        if ($keepRuntemplate) {
            $condition['runtemplate_id'] = $this->getDataValue('runtemplate_id');
        }

        if ($keepCompnay) {
            $condition['company_id'] = $this->getDataValue('company_id');
        }

        $nextJobId = $this->listingQuery()->select('id', true)->where($condition)->where('id > '.$this->getMyKey())->orderBy('id')->limit(1)->fetchColumn();

        return $nextJobId ? $nextJobId : 0;
    }

    public function getPreviousJobId(bool $keepApp = true, bool $keepRuntemplate = true, bool $keepCompnay = true): int
    {
        $condition = [];

        if ($keepApp) {
            $condition['app_id'] = $this->getDataValue('app_id');
        }

        if ($keepRuntemplate) {
            $condition['runtemplate_id'] = $this->getDataValue('runtemplate_id');
        }

        if ($keepCompnay) {
            $condition['company_id'] = $this->getDataValue('company_id');
        }

        $prevJobId = $this->listingQuery()->select('id', true)->where($condition)->where('id < '.$this->getMyKey())->orderBy('id DESC')->limit(1)->fetchColumn();

        return $prevJobId ? $prevJobId : 0;
    }

    public function getEnvironment(): ConfigFields
    {
        return $this->environment;
    }

    /**
     * Set PID of the running job.
     */
    public function setPid(int $pid): void
    {
        $this->setDataValue('pid', $pid);
        $this->reporter->setDataValue('pid', $pid);
    }

    public function setEnvironment(ConfigFields $environment): self
    {
        $this->environment = $environment;

        return $this;
    }

    public function todaysCond(string $column = 'begin'): string
    {
        $databaseType = $this->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        switch ($databaseType) {
            case 'mysql':
                $cond = ('DATE('.$column.') = CURDATE()');

                break;
            case 'sqlite':
                $cond = ('DATE('.$column.') = DATE(\'now\')');

                break;
            case 'pgsql':
                $cond = ('DATE('.$column.') = CURRENT_DATE');

                break;
            case 'sqlsrv':
                $cond = ('CAST('.$column.' AS DATE) = CAST(GETDATE() AS DATE)');

                break;

            default:
                throw new \Exception('Unsupported database type '.$databaseType);
        }

        return $cond;
    }

    public function getJobEnvironment(): ConfigFields
    {
        // Assembly Enviromnent from
        // 0 Current - default
        // 1 Company
        // 2 Runtemplate

        $jobEnvironment = new ConfigFields(sprintf(_('Job #%d'), $this->getMyKey()));
        $jobEnvironment->addFields($this->getCompany()->getEnvironment());
        $jobEnvironment->addFields($this->getRunTemplate()->getEnvironment());

        return $jobEnvironment;
    }

    public function setCompany(Company $company): void
    {
        $this->company = $company;
    }

    public function setApplication(Application $app): void
    {
        $this->application = $app;
    }

    public function setRunTemplate(RunTemplate $runtemplate): self
    {
        $this->runTemplate = $runtemplate;

        return $this;
    }

    public function isScheduled(): int
    {
        return \count($this->scheduledJobInfo());
    }

    public function scheduledJobInfo(): array
    {
        $scheduler = new Scheduler();

        return $scheduler->listingQuery()
            ->where('job', $this->getMyKey())
            ->leftJoin('job ON job.id = schedule.job')->select(['job.schedule_type'])
            ->leftJoin('runtemplate ON runtemplate.id = job.runtemplate_id')->select(['runtemplate.name AS runtemplate_name', 'runtemplate.id AS runtemplate_id', 'runtemplate.last_schedule'])
            ->orderBy('schedule.after')
            ->fetchAll();
    }

    /**
     * Delete job from database with proper runtemplate counter updates.
     *
     * This method:
     * 1. Determines if the job is orphaned (not started, not scheduled)
     * 2. For non-orphaned completed jobs, decrements appropriate runtemplate counters
     * 3. Deletes related schedule entries
     * 4. Deletes the job record
     *
     * Orphaned jobs: Jobs without begin time and no schedule entry
     * Non-orphaned jobs: Jobs that were executed (have begin time)
     *
     * @param null|array|int $data Optional data for deletion (inherited from parent)
     *
     * @return bool Success status
     */
    public function deleteFromSQL($data = null): bool
    {
        $jobId = (\is_int($data) ? $data : (\is_array($data) ? $this->getMyKey($data) : $this->getMyKey()));

        if (!$jobId) {
            $this->addStatusMessage(_('Cannot delete job without ID'), 'error');

            return false;
        }

        // Load current job data
        $this->loadFromSQL($jobId);

        $runtemplateId = $this->getDataValue('runtemplate_id');
        $exitcode = $this->getDataValue('exitcode');
        $begin = $this->getDataValue('begin');

        // Determine if job contributed to runtemplate statistics
        // Only jobs that were started (have begin time) AND completed (have exitcode) count
        $shouldUpdateCounters = ($begin !== null && $exitcode !== null);

        if ($shouldUpdateCounters && $runtemplateId) {
            try {
                // Load runtemplate to update counters
                $runTemplate = new RunTemplate($runtemplateId);

                if ($runTemplate->getMyKey()) {
                    $updates = [];

                    // Decrement appropriate counter based on exit code
                    if ((int) $exitcode === 0) {
                        $currentCount = (int) $runTemplate->getDataValue('successfull_jobs_count');

                        if ($currentCount > 0) {
                            $updates['successfull_jobs_count'] = $currentCount - 1;
                        }
                    } else {
                        $currentCount = (int) $runTemplate->getDataValue('failed_jobs_count');

                        if ($currentCount > 0) {
                            $updates['failed_jobs_count'] = $currentCount - 1;
                        }
                    }

                    if (!empty($updates)) {
                        $runTemplate->updateToSQL($updates, ['id' => $runtemplateId]);
                        $this->addStatusMessage(_('Updated runtemplate counters after job deletion'), 'debug');
                    }
                }
            } catch (\Exception $e) {
                $this->addStatusMessage(sprintf(_('Failed to update runtemplate counters: %s'), $e->getMessage()), 'warning');
                // Continue with deletion even if counter update fails
            }
        }

        // Delete related schedule entries
        try {
            $scheduler = new Scheduler();
            $scheduleEntries = $scheduler->listingQuery()->where('job', $jobId)->fetchAll();

            foreach ($scheduleEntries as $entry) {
                $scheduler->deleteFromSQL((int) $entry['id']);
            }

            if (!empty($scheduleEntries)) {
                $this->addStatusMessage(
                    sprintf(_('Deleted %d schedule entries'), \count($scheduleEntries)),
                    'debug',
                );
            }
        } catch (\Exception $e) {
            $this->addStatusMessage(
                sprintf(_('Failed to delete schedule entries: %s'), $e->getMessage()),
                'warning',
            );
            // Continue with job deletion even if schedule cleanup fails
        }

        // Finally, delete the job itself using parent method
        // Parent method returns int (number of deleted rows) or bool, convert to bool
        $result = parent::deleteFromSQL($data);

        return (bool) $result;
    }

    public function environmentCheck(): void
    {
        if ($this->environment->count() === 0) {
            throw new \RuntimeException(_('Empty Job Environment'));
        }
    }

    public function ensureFiles(): void
    {
        foreach ($this->environment as $field) {
            if ($field->getType() === 'file-path') {
                if (file_exists($field->getValue()) === false) {
                    $fileSource = $field->getSource();

                    if (\Ease\Euri::validate($fileSource) && strstr($fileSource, 'FileStore')) {
                        $fileStore = \Ease\Euri::toObject($fileSource);
                        $fileStore->restoreFile();
                    }
                }
            }
        }
    }

    public function setupEnvironment(?ConfigFields $envOverride = null): void
    {
        $this->environment->addFields($this->getRunTemplate()->getEnvironment());
        $this->environment->addFields($this->getModulesEnvironment());

        if ($envOverride) {
            $this->environment->addFields($envOverride);
        }
    }

    /**
     * Update or obtain Job Environment.
     *
     * @param null|ConfigFields $env Environment to add
     *
     * @return ConfigFields Current Job Environment
     */
    public function environment(?ConfigFields $env = null): ConfigFields
    {
        if ($env) {
            $this->environment->addFields($env);
        }

        return $this->environment;
    }

    public function getApplication(): ?Application
    {
        if (null === $this->application) {
            $this->application = new Application($this->getDataValue('app_id'));
        } elseif (null === $this->application->getMyKey() || empty($this->application->getRecordName())) {
            $this->application->loadFromSQL($this->getDataValue('app_id'));
        }

        return $this->application;
    }


    /**
     * Collect output data produced by this job, keyed by produces-name.
     *
     * Resolution by format:
     *   json  → decode RESULT_FILE or first matching artifact → return array
     *   text|url → return stdout string value (first 64 KB)
     *   file  → return the artifact content reference (content string)
     *
     * @return array<string, mixed>
     */
    public function collectProducedData(): array
    {
        $appId = $this->getApplication()->getMyKey();

        if (!$appId) {
            return [];
        }

        $producesRows = $this->getApplication()->getFluentPDO()
            ->from('app_produces')
            ->where('app_id', $appId)
            ->fetchAll();

        if (empty($producesRows)) {
            return [];
        }

        $result = [];

        $artifactor = new Artifact();

        foreach ($producesRows as $row) {
            $name = $row['name'];
            $format = $row['format'];
            $patterns = !empty($row['patterns_json']) ? json_decode($row['patterns_json'], true) : [];

            switch ($format) {
                case 'json':
                    $data = $this->resolveProducedJson($patterns, $artifactor);

                    if ($data !== null) {
                        $result[$name] = $data;
                    }

                    break;

                case 'text':
                case 'url':
                    $stdout = $this->getOutput();

                    if (!empty($stdout)) {
                        $result[$name] = substr($stdout, 0, 65536);
                    }

                    break;

                case 'file':
                    $artifact = $this->findArtifactByPatterns($patterns, $artifactor);

                    if ($artifact !== null) {
                        $result[$name] = $artifact['artifact'];
                    }

                    break;
            }
        }

        return $result;
    }

    /**
     * Decode the first artifact whose filename matches any of $patterns as JSON.
     * Falls back to RESULT_FILE if no artifact matches.
     *
     * @param array<string> $patterns
     *
     * @return null|array<mixed>
     */
    private function resolveProducedJson(array $patterns, Artifact $artifactor): ?array
    {
        if (!empty($patterns)) {
            $artifact = $this->findArtifactByPatterns($patterns, $artifactor);

            if ($artifact !== null) {
                $decoded = json_decode($artifact['artifact'], true);

                if (json_last_error() === \JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
        }

        // Fallback: RESULT_FILE stored as artifact with filename matching resultfile
        $resultfileField = $this->environment->getFieldByCode('RESULT_FILE');

        if ($resultfileField && !empty($resultfileField->getValue())) {
            $artifacts = $artifactor->getFluentPDO()
                ->from('artifacts')
                ->where('job_id', $this->getMyKey())
                ->where('filename', basename($resultfileField->getValue()))
                ->fetch();

            if ($artifacts && !empty($artifacts['artifact'])) {
                $decoded = json_decode($artifacts['artifact'], true);

                if (json_last_error() === \JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * Find the first artifact for this job whose filename matches any of $patterns.
     *
     * @param array<string> $patterns
     *
     * @return null|array<string, mixed>
     */
    private function findArtifactByPatterns(array $patterns, Artifact $artifactor): ?array
    {
        $jobArtifacts = $artifactor->getFluentPDO()
            ->from('artifacts')
            ->where('job_id', $this->getMyKey())
            ->fetchAll();

        foreach ($jobArtifacts as $artifact) {
            $filename = $artifact['filename'] ?? '';

            foreach ($patterns as $pattern) {
                if (!empty($pattern) && @preg_match('/'.$pattern.'/', $filename) === 1) {
                    return $artifact;
                }
            }
        }

        return null;
    }
    public function getRuntemplate(): ?RunTemplate
    {
        if (null === $this->runTemplate) {
            $this->runTemplate = new RunTemplate($this->getDataValue('runtemplate_id'));
        } elseif (null === $this->runTemplate->getMyKey() || empty($this->runTemplate->getRecordName())) {
            $this->runTemplate->loadFromSQL($this->getDataValue('runtemplate_id'));
        }

        return $this->runTemplate;
    }

    public function getCompany(): ?Company
    {
        if (null === $this->company) {
            $this->company = new Company($this->getDataValue('company_id'));
        } elseif (null === $this->company->getMyKey() || empty($this->company->getRecordName())) {
            $this->company->loadFromSQL($this->getDataValue('company_id'));
        }

        return $this->company;
    }

    public function getUser(): ?User
    {
        if (null === $this->user) {
            $this->user = new User($this->getDataValue('user_id'));
        } elseif (null === $this->user->getMyKey() || empty($this->getRecordName())) {
            $this->user->loadFromSQL($this->getDataValue('user_id'));
        }

        return $this->user;
    }

    public function getReporter(): JobReport
    {
        return $this->reporter;
    }

    /**
     * Get the localized description for a result file from artifact definitions.
     *
     * Matches the result file basename against artifact path patterns from
     * app_artifacts and returns the localized description from
     * app_artifact_translations. Falls back to a generic description.
     *
     * @param string $resultFile Full path to the result file
     * @param string $lang       Language code for localization
     *
     * @return string Localized artifact description
     */
    private function getArtifactDescription(string $resultFile, string $lang = 'en'): string
    {
        $appId = $this->application ? $this->application->getMyKey() : null;

        if ($appId) {
            $artifactDefs = $this->application->getFluentPDO()
                ->from('app_artifacts')
                ->where('app_id', $appId)
                ->fetchAll();

            $basename = basename($resultFile);

            foreach ($artifactDefs as $artifactDef) {
                $pattern = $artifactDef['path'] ?? '';

                if (empty($pattern)) {
                    continue;
                }

                if (@preg_match('/'.$pattern.'/', $basename) === 1) {
                    $translation = $this->application->getFluentPDO()
                        ->from('app_artifact_translations')
                        ->where('app_artifact_id', $artifactDef['id'])
                        ->where('lang', $lang)
                        ->fetch();

                    if ($translation && !empty($translation['description'])) {
                        return $translation['description'];
                    }

                    // Try fallback to 'en' if requested language not found
                    if ($lang !== 'en') {
                        $fallback = $this->application->getFluentPDO()
                            ->from('app_artifact_translations')
                            ->where('app_artifact_id', $artifactDef['id'])
                            ->where('lang', 'en')
                            ->fetch();

                        if ($fallback && !empty($fallback['description'])) {
                            return $fallback['description'];
                        }
                    }

                    break;
                }
            }
        }

        return sprintf(_('Result file from job execution: %s'), basename($resultFile));
    }

    private function storeJobArtifact(string $resultfile, string $description): void
    {
        $artifactor = new Artifact();

        // Create artifact for result file if it exists
        if (!empty($resultfile) && file_exists($resultfile)) {
            try {
                $resultContent = file_get_contents($resultfile);

                if ($resultContent !== false) {
                    $contentType = \function_exists('mime_content_type') ? mime_content_type($resultfile) : 'text/plain';
                    $artifactor->createArtifact(
                        $this->getMyKey(),
                        $resultContent,
                        basename($resultfile),
                        $contentType,
                        $description,
                    );
                }
            } catch (\Exception $e) {
                $this->addStatusMessage(sprintf(_('Failed to create artifact for result file %s: %s'), $resultfile, $e->getMessage()), 'warning');
            }
        }
    }
}
