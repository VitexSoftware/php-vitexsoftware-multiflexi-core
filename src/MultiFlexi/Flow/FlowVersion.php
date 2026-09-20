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
 * Immutable Deploy snapshot of a flow graph.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class FlowVersion extends DBEngine
{
    public ?string $createColumn = 'created';

    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'flow_version';
        $this->keyColumn = 'id';
        parent::__construct($identifier, $options);
    }
}
