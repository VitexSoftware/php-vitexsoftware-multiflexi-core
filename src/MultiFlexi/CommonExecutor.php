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
 * Description of Executor.
 *
 * @author vitex
 */
abstract class CommonExecutor extends \Ease\Sand
{
    use \Ease\Logger\Logging;
    public Job $job;
    public string $stdin;
    public string $stdout;
    public string $stderr;
    public ConfigFields $environment;
    public array $outputCache = [];
    public ?int $pid = null;

    public function __construct(Job &$job)
    {
        // Initialize environment property first before any other operations
        $this->environment = new ConfigFields('Executor'.\Ease\Functions::baseClassName($this));
        $this->setObjectName();
        $this->setJob($job);
    }

    public function setJob(Job $job): void
    {
        /**
         * Ensure environment is initialized before use.
         */
        if (!isset($this->environment)) {
            $this->environment = new ConfigFields('Executor'.\Ease\Functions::baseClassName($this));
        }

        $this->job = &$job;
        $this->setObjectName($job->getMyKey().'@'.\Ease\Logger\Message::getCallerName($this));
        $this->environment->addFields($job->getEnvironment());
    }

    /**
     * Add Output line into cache and persist to job_output_lines table.
     *
     * @param mixed $line
     * @param mixed $type Line type: 'stdout' | 'stderr' | 'success' | 'error' | 'info' | 'warning' | 'debug' | …
     */
    public function addOutput($line, $type): void
    {
        $this->outputCache[microtime()] = ['line' => $line, 'type' => $type];

        $jobId = $this->job->getMyKey();

        if ($jobId) {
            try {
                (new JobOutputLine())->addLine($jobId, \count($this->outputCache), $type, (string) $line);
            } catch (\Throwable $e) {
                // Never let a DB write failure abort the running job
            }
        }
    }

    /**
     * Get Output cache as plaintext.
     */
    public function getOutputCachePlaintext()
    {
        $output = '';

        foreach ($this->outputCache as $line) {
            $output .= $line['line']."\n";
        }

        return $output;
    }

    public function getPid(): ?int
    {
        return $this->pid;
    }

    public function getEnvironment(): ConfigFields
    {
        return $this->environment;
    }
}
