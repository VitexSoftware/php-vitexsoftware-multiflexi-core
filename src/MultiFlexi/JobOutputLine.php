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

use Ease\SQL\Engine;

/**
 * Stores and retrieves individual output lines for a Job.
 *
 * Each row represents one line (or chunk) written by an executor.
 * The `type` column is open-ended: 'stdout', 'stderr', 'info', 'warning', 'error', 'debug', …
 */
class JobOutputLine extends Engine
{
    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'job_output_lines';
        parent::__construct($identifier, $options);
    }

    /**
     * Append one output line for a job.
     *
     * @param int    $jobId ID of the owning job
     * @param int    $seq   Sequence number within the job (for ordering)
     * @param string $type  Line type: 'stdout' | 'stderr' | 'info' | 'warning' | 'error' | 'debug' | …
     * @param string $line  The actual output text (may contain ANSI codes)
     *
     * @return int Inserted row ID
     */
    public function addLine(int $jobId, int $seq, string $type, string $line): int
    {
        $driverName = $this->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $now = ($driverName === 'mysql')
            ? (new \DateTime())->format('Y-m-d H:i:s.u')
            : (new \DateTime())->format('Y-m-d H:i:s');

        return $this->insertToSQL([
            'job_id'     => $jobId,
            'seq'        => $seq,
            'type'       => $type,
            'line'       => $line,
            'created_at' => $now,
        ]);
    }

    /**
     * Retrieve all lines for a job of a given type, ordered by seq.
     *
     * @param int    $jobId Job ID
     * @param string $type  Line type filter ('stdout', 'stderr', etc.)
     *
     * @return array<array{id:int,seq:int,type:string,line:string,created_at:string}>
     */
    public function getLinesForJob(int $jobId, string $type): array
    {
        return $this->getFluentPDO()
            ->from($this->myTable)
            ->where('job_id', $jobId)
            ->where('type', $type)
            ->orderBy('seq ASC')
            ->fetchAll();
    }

    /**
     * Retrieve all lines for a job (all types), ordered by seq then id.
     *
     * @return array<array{id:int,seq:int,type:string,line:string,created_at:string}>
     */
    public function getAllLinesForJob(int $jobId): array
    {
        return $this->getFluentPDO()
            ->from($this->myTable)
            ->where('job_id', $jobId)
            ->orderBy('seq ASC, id ASC')
            ->fetchAll();
    }

    /**
     * Retrieve lines added after a given row ID (for SSE streaming).
     *
     * @return array<array{id:int,seq:int,type:string,line:string,created_at:string}>
     */
    public function getLinesSince(int $jobId, int $lastId): array
    {
        return $this->getFluentPDO()
            ->from($this->myTable)
            ->where('job_id', $jobId)
            ->where('id > ?', $lastId)
            ->orderBy('id ASC')
            ->fetchAll();
    }

    /**
     * Concatenate all lines of a given type for a job into a single string.
     */
    public function getOutputString(int $jobId, string $type): string
    {
        $lines = $this->getLinesForJob($jobId, $type);

        return implode('', array_column($lines, 'line'));
    }
}
