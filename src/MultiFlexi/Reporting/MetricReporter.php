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

namespace MultiFlexi\Reporting;

/**
 * Fan-out reporter that forwards metric events to all registered sinks.
 *
 * Sinks are registered via addSink(). Each sink decides independently
 * whether it is enabled, so disabling OTEL does not affect Zabbix and vice versa.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class MetricReporter extends \Ease\Sand implements MetricSinkInterface
{
    /** @var MetricSinkInterface[] */
    private array $sinks = [];

    /**
     * Register a reporting backend.
     */
    public function addSink(MetricSinkInterface $sink): void
    {
        $this->sinks[] = $sink;
        $this->addStatusMessage(
            sprintf(_('MetricReporter: registered sink %s'), $sink::class),
            'debug',
        );
    }

    /**
     * Remove all registered sinks.
     */
    public function clearSinks(): void
    {
        $this->sinks = [];
    }

    /**
     * @return MetricSinkInterface[]
     */
    public function getSinks(): array
    {
        return $this->sinks;
    }

    #[\Override]
    public function recordJobStart(
        int $jobId,
        int $appId,
        string $appName,
        int $companyId,
        string $companyName,
        int $runtemplateId,
        string $runtemplateName,
    ): void {
        foreach ($this->sinks as $sink) {
            if (!$sink->isEnabled()) {
                continue;
            }

            try {
                $sink->recordJobStart(
                    $jobId,
                    $appId,
                    $appName,
                    $companyId,
                    $companyName,
                    $runtemplateId,
                    $runtemplateName,
                );
            } catch (\Throwable $e) {
                $this->addStatusMessage(
                    sprintf(_('MetricReporter sink %s failed on recordJobStart: %s'), $sink::class, $e->getMessage()),
                    'error',
                );
            }
        }
    }

    #[\Override]
    public function recordJobEnd(int $exitCode, float $duration, array $attributes): void
    {
        foreach ($this->sinks as $sink) {
            if (!$sink->isEnabled()) {
                continue;
            }

            try {
                $sink->recordJobEnd($exitCode, $duration, $attributes);
            } catch (\Throwable $e) {
                $this->addStatusMessage(
                    sprintf(_('MetricReporter sink %s failed on recordJobEnd: %s'), $sink::class, $e->getMessage()),
                    'error',
                );
            }
        }
    }

    #[\Override]
    public function flush(): void
    {
        foreach ($this->sinks as $sink) {
            if (!$sink->isEnabled()) {
                continue;
            }

            try {
                $sink->flush();
            } catch (\Throwable $e) {
                $this->addStatusMessage(
                    sprintf(_('MetricReporter sink %s failed on flush: %s'), $sink::class, $e->getMessage()),
                    'error',
                );
            }
        }
    }

    /**
     * True when at least one sink is enabled.
     */
    #[\Override]
    public function isEnabled(): bool
    {
        foreach ($this->sinks as $sink) {
            if ($sink->isEnabled()) {
                return true;
            }
        }

        return false;
    }
}
