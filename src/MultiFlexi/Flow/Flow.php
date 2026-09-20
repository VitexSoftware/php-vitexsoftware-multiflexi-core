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
 * Logical automation graph (Node-RED tab) with a current immutable version.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class Flow extends DBEngine
{
    public string $nameColumn = 'name';

    public ?string $createColumn = 'created';

    public ?string $lastModifiedColumn = 'modified';

    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'flow';
        $this->keyColumn = 'id';
        parent::__construct($identifier, $options);
    }
}
