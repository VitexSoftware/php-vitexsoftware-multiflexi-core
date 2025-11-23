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
 * Description of Defaults.
 *
 * @author Vitex <info@vitexsoftware.cz>
 */
class Defaults
{
    public static string $MULTIFLEXI_TMP;
    public function __construct()
    {
        self::$MULTIFLEXI_TMP = file_exists('/var/lib/multiflexi/tmp') ? '/var/lib/multiflexi/tmp' : sys_get_temp_dir();
    }
}
