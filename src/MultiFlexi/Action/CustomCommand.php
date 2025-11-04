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

use MultiFlexi\Env\Application;

/**
 * Description of CustomCommand.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class CustomCommand extends \MultiFlexi\CommonAction
{
    /**
     * Module Caption.
     *
     * @return string
     */
    public static function name()
    {
        return _('Custom Command');
    }

    /**
     * Module Description.
     *
     * @return string
     */
    public static function description()
    {
        return _('Run custom command');
    }

    /**
     * Is this Action suitable for Application.
     */
    public static function usableForApp(\MultiFlexi\Application $app): bool
    {
        return \is_object($app);
    }

    /**
     * Perform Action.
     */
    public function perform(\MultiFlexi\Job $job): void
    {
        $command = $this->getDataValue('command');
        $this->addStatusMessage(_('Custom Command begin'));
        $exitCode = $this->runtemplate->executor->launch($command);
        $this->addStatusMessage(_('Custom Command done'));
    }
}
