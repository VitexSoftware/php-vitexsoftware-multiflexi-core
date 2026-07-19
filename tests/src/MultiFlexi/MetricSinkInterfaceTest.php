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

namespace Test\MultiFlexi;

use MultiFlexi\Reporting\MetricReporter;
use MultiFlexi\Reporting\MetricSinkInterface;
use MultiFlexi\Telemetry\OtelMetricsExporter;
use PHPUnit\Framework\TestCase;

final class MetricSinkInterfaceTest extends TestCase
{
    public function testInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(MetricSinkInterface::class));
    }

    public function testMetricReporterExists(): void
    {
        $this->assertTrue(class_exists(MetricReporter::class));
    }

    public function testMetricReporterImplementsInterface(): void
    {
        $this->assertTrue(is_a(MetricReporter::class, MetricSinkInterface::class, true));
    }

    public function testOtelMetricsExporterImplementsInterface(): void
    {
        $this->assertTrue(is_a(OtelMetricsExporter::class, MetricSinkInterface::class, true));
    }

    public function testMetricReporterStartsEmpty(): void
    {
        $reporter = new MetricReporter();
        $this->assertFalse($reporter->isEnabled());
        $this->assertCount(0, $reporter->getSinks());
    }

    public function testMetricReporterAddSink(): void
    {
        $reporter = new MetricReporter();
        $sink = $this->createMock(MetricSinkInterface::class);
        $sink->method('isEnabled')->willReturn(true);

        $reporter->addSink($sink);

        $this->assertCount(1, $reporter->getSinks());
        $this->assertTrue($reporter->isEnabled());
    }

    public function testMetricReporterClearSinks(): void
    {
        $reporter = new MetricReporter();
        $sink = $this->createMock(MetricSinkInterface::class);
        $reporter->addSink($sink);
        $reporter->clearSinks();

        $this->assertCount(0, $reporter->getSinks());
    }

    public function testMetricReporterDelegatesRecordJobStart(): void
    {
        $reporter = new MetricReporter();
        $sink = $this->createMock(MetricSinkInterface::class);
        $sink->method('isEnabled')->willReturn(true);
        $sink->expects($this->once())
            ->method('recordJobStart')
            ->with(1, 2, 'TestApp', 3, 'TestCompany', 4, 'TestRunTemplate');

        $reporter->addSink($sink);
        $reporter->recordJobStart(1, 2, 'TestApp', 3, 'TestCompany', 4, 'TestRunTemplate');
    }

    public function testMetricReporterDelegatesRecordJobEnd(): void
    {
        $reporter = new MetricReporter();
        $sink = $this->createMock(MetricSinkInterface::class);
        $sink->method('isEnabled')->willReturn(true);
        $sink->expects($this->once())
            ->method('recordJobEnd')
            ->with(0, 1.5, ['job_id' => 1]);

        $reporter->addSink($sink);
        $reporter->recordJobEnd(0, 1.5, ['job_id' => 1]);
    }

    public function testMetricReporterSkipsDisabledSinks(): void
    {
        $reporter = new MetricReporter();
        $sink = $this->createMock(MetricSinkInterface::class);
        $sink->method('isEnabled')->willReturn(false);
        $sink->expects($this->never())->method('recordJobStart');

        $reporter->addSink($sink);
        $reporter->recordJobStart(1, 2, 'App', 3, 'Co', 4, 'RT');
    }

    public function testMetricReporterIsolatesExceptions(): void
    {
        $reporter = new MetricReporter();

        $failingSink = $this->createMock(MetricSinkInterface::class);
        $failingSink->method('isEnabled')->willReturn(true);
        $failingSink->method('recordJobEnd')->willThrowException(new \RuntimeException('network down'));

        $healthySink = $this->createMock(MetricSinkInterface::class);
        $healthySink->method('isEnabled')->willReturn(true);
        $healthySink->expects($this->once())->method('recordJobEnd');

        $reporter->addSink($failingSink);
        $reporter->addSink($healthySink);

        $reporter->recordJobEnd(0, 1.0, []);
    }

    /**
     * Regression guard: OtelMetricsExporter must never turn job-instance-unique
     * fields (job_id, pid, begin/end/scheduled timestamps, raw result data,
     * result file paths, ...) into metric attributes. Each distinct attribute
     * combination becomes its own permanent Prometheus series, so per-job-unique
     * values silently turn every counter into an ever-growing set of one-sample
     * series that rate()/increase() can never aggregate over (this is exactly
     * what broke the "Total Jobs" / "Success Rate %" Grafana panels).
     */
    public function testOtelMetricsExporterFiltersHighCardinalityAttributes(): void
    {
        // Bypass the constructor: it reaches out to OTEL_ENABLED config / DB-backed
        // logging, none of which matters for testing the pure attribute filter.
        $exporter = (new \ReflectionClass(OtelMetricsExporter::class))->newInstanceWithoutConstructor();

        $method = new \ReflectionMethod(OtelMetricsExporter::class, 'filterAttributes');

        $rawJobReporterPayload = [
            'job_id' => 245171,
            'pid' => 1815291,
            'begin' => '2026-07-19 11:00:01',
            'end' => '2026-07-19 11:00:02',
            'scheduled' => '2026-07-19 11:00:00',
            'data' => '{"producer":"AbraFlexi","status":"success"}',
            'result_file' => '/var/lib/multiflexi/tmp/report.json',
            'exitcode_description' => 'OK Success',
            'version' => '1.4.0.170',
            'interval' => '0 * * * *',
            'interval_seconds' => 3600,
            'schedule_type' => 'custom',
            'launched_by' => 'multiflexi',
            'launched_by_id' => 5,
            'app_id' => 12,
            'app_name' => 'Email Sender',
            'company_id' => 1,
            'company_name' => 'VitexSoftware',
            'runtemplate_id' => 115,
            'runtemplate_name' => 'Email Sender',
            'exitcode' => 0,
        ];

        $filtered = $method->invoke($exporter, $rawJobReporterPayload);

        $this->assertSame([
            'app_id' => 12,
            'app_name' => 'Email Sender',
            'company_id' => 1,
            'company_name' => 'VitexSoftware',
            'runtemplate_id' => 115,
            'runtemplate_name' => 'Email Sender',
            'exitcode' => 0,
        ], $filtered);
    }
}
