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

namespace MultiFlexi\Flow;

use MultiFlexi\DBEngine;

/**
 * One execution instance of a pinned flow_version.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class FlowRun extends DBEngine
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLING = 'cancelling';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'flow_run';
        $this->keyColumn = 'id';
        parent::__construct($identifier, $options);
    }
}
