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
    /**
     * Scratch directory for job artifacts and temp files.
     * Initialised eagerly so static access works without constructing.
     */
    public static string $MULTIFLEXI_TMP;

    public static function init(): void
    {
        self::$MULTIFLEXI_TMP = file_exists('/var/lib/multiflexi/tmp')
            ? '/var/lib/multiflexi/tmp'
            : sys_get_temp_dir();
    }

    public function __construct()
    {
        self::init();
    }
}

Defaults::init();
