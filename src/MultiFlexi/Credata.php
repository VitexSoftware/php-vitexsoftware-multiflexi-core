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
 * Description of Credata.
 *
 * @author Vitex <info@vitexsoftware.cz>
 */
class Credata extends Engine
{
    /**
     * Credata constructor.
     *
     * @param mixed $identifier
     * @param array $options<string, mixed>
     */
    public function __construct($identifier = null, array $options = [])
    {
        $this->myTable = 'credata';
        parent::__construct($identifier, $options);
    }
}
