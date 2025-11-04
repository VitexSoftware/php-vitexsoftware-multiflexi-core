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

namespace MultiFlexi\Action;

/**
 * Description of Reschedule.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class Reschedule extends \MultiFlexi\CommonAction
{
    /**
     * Module Caption.
     */
    public static function name(): string
    {
        return _('Reschedule');
    }

    /**
     * Module Description.
     *
     * @return string
     */
    public static function description()
    {
        return _('Reschedule job to be executed later again');
    }

    /**
     * Is this Action Suitable for Application.
     *
     * @param Application $app
     */
    public static function usableForApp($app): bool
    {
        return \is_object($app);
    }
}
