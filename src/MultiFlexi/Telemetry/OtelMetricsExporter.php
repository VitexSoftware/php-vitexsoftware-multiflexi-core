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

namespace MultiFlexi\Telemetry;

use MultiFlexi\Application;
use MultiFlexi\Company;
use MultiFlexi\Job;
use MultiFlexi\Reporting\MetricSinkInterface;
use MultiFlexi\RunTemplate;
use OpenTelemetry\API\Metrics\ObserverInterface;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\SDK\Common\Export\Http\PsrTransportFactory;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;

/**
 * OpenTelemetry Metrics Exporter for MultiFlexi.
 *
 * Exports job execution metrics, application stats, and company stats
 * to OpenTelemetry-compatible backends (Prometheus, Grafana, etc.)
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class OtelMetricsExporter extends \Ease\Sand implements MetricSinkInterface
{
    /**
     * Allow-listed metric attribute keys.
     *
     * Job-instance-unique fields (job_id, pid, begin/end/scheduled timestamps, raw
     * result data, result file paths, ...) must never end up here: each distinct
     * attribute combination becomes its own permanent Prometheus/OTel series, so
     * per-job-unique values turn every counter into an ever-growing set of
     * one-sample series that rate()/increase() can never aggregate over.
     */
    private const ALLOWED_ATTRIBUTE_KEYS = [
        'app_id',
        'app_name',
        'company_id',
        'company_name',
        'runtemplate_id',
        'runtemplate_name',
        'exitcode',
    ];

    /**
     * MeterProvider instance.
     */
    private ?MeterProvider $meterProvider = null;

    /**
     * Meter instance for creating metrics.
     */
    private mixed $meter;

    /**
     * Counter: Total jobs executed.
     */
    private mixed $jobsTotal;

    /**
     * Counter: Successful jobs.
     */
    private mixed $jobsSuccess;

    /**
     * Counter: Failed jobs.
     */
    private mixed $jobsFailed;

    /**
     * Histogram: Job execution duration.
     */
    private mixed $jobDuration;

    /**
     * Is OpenTelemetry enabled.
     */
    private bool $enabled = false;

    /**
     * Initialize OpenTelemetry Metrics Exporter.
     */
    public function __construct()
    {
        $this->enabled = (bool) \Ease\Shared::cfg('OTEL_ENABLED', false);

        if (!$this->enabled) {
            $this->addStatusMessage(_('OpenTelemetry metrics export is disabled'), 'debug');

            return;
        }

        if (!class_exists('OpenTelemetry\\SDK\\Metrics\\MeterProvider')) {
            $this->addStatusMessage(_('OpenTelemetry SDK not installed. Install: composer require open-telemetry/sdk open-telemetry/exporter-otlp'), 'warning');
            $this->enabled = false;

            return;
        }

        try {
            $this->initializeMeterProvider();
            $this->initializeMetrics();
            $this->addStatusMessage(_('OpenTelemetry metrics exporter initialized'), 'debug');
        } catch (\Exception $e) {
            $this->addStatusMessage(sprintf(_('Failed to initialize OpenTelemetry: %s'), $e->getMessage()), 'error');
            $this->enabled = false;
        }
    }

    /**
     * Record job start event.
     *
     * @param int    $jobId           Job ID
     * @param int    $appId           Application ID
     * @param string $appName         Application name
     * @param int    $companyId       Company ID
     * @param string $companyName     Company name
     * @param int    $runtemplateId   RunTemplate ID
     * @param string $runtemplateName RunTemplate name
     */
    public function recordJobStart(
        int $jobId,
        int $appId,
        string $appName,
        int $companyId,
        string $companyName,
        int $runtemplateId,
        string $runtemplateName,
    ): void {
        if (!$this->enabled) {
            return;
        }

        $this->jobsTotal->add(1, $this->filterAttributes([
            'app_id' => $appId,
            'app_name' => $appName,
            'company_id' => $companyId,
            'company_name' => $companyName,
            'runtemplate_id' => $runtemplateId,
            'runtemplate_name' => $runtemplateName,
        ]));

        $this->addStatusMessage(sprintf(_('OTel: Recorded job start #%d'), $jobId), 'debug');
    }

    /**
     * Record job end event with exit code and duration.
     *
     * @param int   $exitCode   Job exit code
     * @param float $duration   Execution duration in seconds
     * @param array $attributes Additional attributes (app_id, company_id, etc.)
     */
    public function recordJobEnd(int $exitCode, float $duration, array $attributes): void
    {
        if (!$this->enabled) {
            return;
        }

        // Add exitcode to attributes
        $attributes['exitcode'] = $exitCode;

        $attributes = $this->filterAttributes($attributes);

        // Record success or failure
        if ($exitCode === 0) {
            $this->jobsSuccess->add(1, $attributes);
        } else {
            $this->jobsFailed->add(1, $attributes);
        }

        // Record duration
        $this->jobDuration->record($duration, $attributes);

        $this->addStatusMessage(sprintf(
            _('OTel: Recorded job end, exitcode=%d, duration=%.2fs'),
            $exitCode,
            $duration,
        ), 'debug');
    }

    /**
     * Force flush metrics to exporter.
     */
    public function flush(): void
    {
        if (!$this->enabled || null === $this->meterProvider) {
            return;
        }

        try {
            $this->meterProvider->forceFlush();
            $this->addStatusMessage(_('OTel: Metrics flushed'), 'debug');
        } catch (\Exception $e) {
            $this->addStatusMessage(sprintf(_('Error flushing metrics: %s'), $e->getMessage()), 'error');
        }
    }

    /**
     * Shutdown metrics provider.
     */
    public function shutdown(): void
    {
        if (!$this->enabled || null === $this->meterProvider) {
            return;
        }

        try {
            $this->meterProvider->shutdown();
            $this->addStatusMessage(_('OTel: Metrics provider shutdown'), 'debug');
        } catch (\Exception $e) {
            $this->addStatusMessage(sprintf(_('Error shutting down metrics: %s'), $e->getMessage()), 'error');
        }
    }

    /**
     * Check if OpenTelemetry is enabled and available.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Strip any attribute not on the low-cardinality allow-list.
     *
     * Guards against job-instance-unique values (job_id, pid, timestamps, raw
     * result payloads, file paths, ...) being attached as metric labels, which
     * would otherwise turn every job execution into its own permanent,
     * never-repeating Prometheus series.
     *
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    private function filterAttributes(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(self::ALLOWED_ATTRIBUTE_KEYS));
    }

    /**
     * Initialize MeterProvider with OTLP exporter.
     */
    private function initializeMeterProvider(): void
    {
        $endpoint = \Ease\Shared::cfg('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://localhost:4318');
        $protocol = \Ease\Shared::cfg('OTEL_EXPORTER_OTLP_PROTOCOL', 'http/json');

        // Append metrics path for HTTP/JSON protocol
        if ($protocol === 'http/json' || $protocol === 'http/protobuf') {
            if (!str_ends_with($endpoint, '/v1/metrics')) {
                $endpoint = rtrim($endpoint, '/').'/v1/metrics';
            }
        }

        $transport = (new PsrTransportFactory())->create(
            $endpoint,
            'application/json',
        );

        // Delta temporality: each PHP process (job/CLI invocation) is short-lived and
        // starts its own Counter/Histogram at zero, so a Cumulative export would report
        // "total since this process started" (always 1) instead of an ever-growing total.
        // Requires a deltatocumulative processor in the OTel Collector's metrics
        // pipeline (see VSAnsible roles/services/otel_collector) to turn these
        // per-process deltas into one true monotonic counter per series identity -
        // the Prometheus exporter itself does not accumulate Delta input.
        $exporter = new MetricExporter($transport, Temporality::DELTA);
        $reader = new ExportingReader($exporter);

        $this->meterProvider = MeterProvider::builder()
            ->addReader($reader)
            ->build();

        $serviceName = \Ease\Shared::cfg('OTEL_SERVICE_NAME', 'multiflexi');
        $this->meter = $this->meterProvider->getMeter($serviceName);
    }

    /**
     * Initialize all metrics (counters, histograms, gauges).
     */
    private function initializeMetrics(): void
    {
        // Counters
        $this->jobsTotal = $this->meter->createCounter(
            'multiflexi.jobs.total',
            'jobs',
            'Total number of jobs executed',
        );

        $this->jobsSuccess = $this->meter->createCounter(
            'multiflexi.jobs.success',
            'jobs',
            'Number of successful jobs (exitcode=0)',
        );

        $this->jobsFailed = $this->meter->createCounter(
            'multiflexi.jobs.failed',
            'jobs',
            'Number of failed jobs (exitcode≠0)',
        );

        // Histogram for job duration
        $this->jobDuration = $this->meter->createHistogram(
            'multiflexi.job.duration',
            'seconds',
            'Job execution duration in seconds',
        );

        // Observable Gauges for real-time metrics
        $this->meter->createObservableGauge(
            'multiflexi.jobs.running',
            'jobs',
            'Currently running jobs',
        )->observe(function (ObserverInterface $observer): void {
            $count = $this->getRunningJobsCount();
            $observer->observe($count);
        });

        $this->meter->createObservableGauge(
            'multiflexi.applications.total',
            'applications',
            'Total number of applications',
        )->observe(function (ObserverInterface $observer): void {
            $count = $this->getApplicationsCount();
            $observer->observe($count);
        });

        $this->meter->createObservableGauge(
            'multiflexi.applications.enabled',
            'applications',
            'Number of enabled applications',
        )->observe(function (ObserverInterface $observer): void {
            $count = $this->getEnabledApplicationsCount();
            $observer->observe($count);
        });

        $this->meter->createObservableGauge(
            'multiflexi.companies.total',
            'companies',
            'Total number of companies',
        )->observe(function (ObserverInterface $observer): void {
            $count = $this->getCompaniesCount();
            $observer->observe($count);
        });

        $this->meter->createObservableGauge(
            'multiflexi.runtemplates.total',
            'runtemplates',
            'Total number of run templates',
        )->observe(function (ObserverInterface $observer): void {
            $count = $this->getRuntemplatesCount();
            $observer->observe($count);
        });
    }

    /**
     * Get count of currently running jobs.
     */
    private function getRunningJobsCount(): int
    {
        try {
            $jobber = new Job();

            return $jobber->listingQuery()
                ->where('begin IS NOT NULL')
                ->where('end IS NULL')
                ->count();
        } catch (\Exception $e) {
            $this->addStatusMessage(sprintf(_('Error counting running jobs: %s'), $e->getMessage()), 'debug');

            return 0;
        }
    }

    /**
     * Get total count of applications.
     */
    private function getApplicationsCount(): int
    {
        try {
            $app = new Application();

            return $app->listingQuery()->count();
        } catch (\Exception $e) {
            $this->addStatusMessage(sprintf(_('Error counting applications: %s'), $e->getMessage()), 'debug');

            return 0;
        }
    }

    /**
     * Get count of enabled applications.
     */
    private function getEnabledApplicationsCount(): int
    {
        try {
            $app = new Application();

            return $app->listingQuery()->where('enabled', 1)->count();
        } catch (\Exception $e) {
            $this->addStatusMessage(sprintf(_('Error counting enabled applications: %s'), $e->getMessage()), 'debug');

            return 0;
        }
    }

    /**
     * Get total count of companies.
     */
    private function getCompaniesCount(): int
    {
        try {
            $company = new Company();

            return $company->listingQuery()->count();
        } catch (\Exception $e) {
            $this->addStatusMessage(sprintf(_('Error counting companies: %s'), $e->getMessage()), 'debug');

            return 0;
        }
    }

    /**
     * Get total count of run templates.
     */
    private function getRuntemplatesCount(): int
    {
        try {
            $runtemplate = new RunTemplate();

            return $runtemplate->listingQuery()->count();
        } catch (\Exception $e) {
            $this->addStatusMessage(sprintf(_('Error counting runtemplates: %s'), $e->getMessage()), 'debug');

            return 0;
        }
    }
}
