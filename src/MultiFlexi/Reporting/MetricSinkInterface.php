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
 * Contract for all metric reporting backends (OTEL, Zabbix, Prometheus, etc.).
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
interface MetricSinkInterface
{
    /**
     * Record a job start event.
     *
     * @param array<string,mixed> $attributes Additional key-value attributes
     */
    public function recordJobStart(
        int $jobId,
        int $appId,
        string $appName,
        int $companyId,
        string $companyName,
        int $runtemplateId,
        string $runtemplateName,
    ): void;

    /**
     * Record a job end event.
     *
     * @param array<string,mixed> $attributes Additional key-value attributes (app_id, company_id, …)
     */
    public function recordJobEnd(int $exitCode, float $duration, array $attributes): void;

    /**
     * Push any buffered metrics to the backend immediately.
     */
    public function flush(): void;

    /**
     * Whether this sink is configured and active.
     */
    public function isEnabled(): bool;
}
