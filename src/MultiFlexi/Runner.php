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
 * Description of Runner.
 *
 * @author vitex
 */
class Runner extends \Ease\Sand
{
    public function __construct()
    {
        $this->setObjectName();
    }

    /**
     * SystemD service name.
     *
     * @param string $service
     *
     * @return bool
     */
    public static function isServiceActive($service)
    {
        return trim((string) shell_exec("systemctl is-active {$service}")) === 'active';
    }

    /**
     * Get the status of a systemd service.
     *
     * @param string $service Service name (e.g. "ssh.service")
     *
     * @return string One of: "active", "inactive", "failed", "unknown"
     */
    public static function getServiceStatus(string $service): string
    {
        // Check if the service is active
        exec('systemctl is-active '.escapeshellarg($service), $output, $isActiveCode);

        if ($isActiveCode === 0) {
            return 'active';
        }

        // Check if the service exists at all
        exec('systemctl status '.escapeshellarg($service).' 2>&1', $statusOutput, $statusCode);

        if ($statusCode === 4) {
            return 'unknown'; // Service does not exist
        }

        // The service exists but is not active; return detailed status
        return trim(implode("\n", $output));
    }
}
