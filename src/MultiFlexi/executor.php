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
 * @author vitex
 */
interface executor
{
    public static function name(): string;

    public static function description(): string;

    /**
     * Launch given command.
     *
     * @return int exit code
     */
    public function launch(string $command): ?int;

    /**
     * Launch the job.
     */
    public function launchJob(): void;

    /**
     * Get error output from the last executed command.
     */
    public function getErrorOutput(): string;

    /**
     * Get output from the last executed command.
     */
    public function getOutput(): string;

    /**
     * Get exit code from the last executed command.
     */
    public function getExitCode(): int;

    /**
     * Store logs of the execution.
     */
    public function storeLogs(): void;

    /**
     * Get command line used for execution.
     */
    public function commandline(): string;

    /**
     * Get PID of the running process.
     */
    public function getPid(): ?int;

    /**
     * Get environment configuration fields.
     */
    public function getEnvironment(): ConfigFields;

    /**
     * Can this Executor execute given application ?
     *
     * @param Application $app
     */
    public static function usableForApp($app): bool;

    /**
     * Logo for Launcher.
     */
    public static function logo(): string;

    public function setJob(Job $job): void;
}
