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
 * Per-node step within a flow_run.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class FlowRunStep extends DBEngine
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_WAITING_JOB = 'waiting_job';
    public const STATUS_WAITING_DELAY = 'waiting_delay';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public ?string $createColumn = 'created';

    public ?string $lastModifiedColumn = 'modified';

    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'flow_run_step';
        $this->keyColumn = 'id';
        parent::__construct($identifier, $options);
    }
}
