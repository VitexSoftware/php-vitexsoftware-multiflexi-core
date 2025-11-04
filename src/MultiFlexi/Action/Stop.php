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
class Stop extends \MultiFlexi\CommonAction
{
    /**
     * Module Caption.
     *
     * @return string
     */
    public static function name()
    {
        return _('Stop');
    }

    /**
     * Module Description.
     *
     * @return string
     */
    public static function description()
    {
        return _('Stop periodical Task');
    }

    /**
     * Perform Action.
     */
    public function perform(\MultiFlexi\Job $job): void
    {
        if ($this->runtemplate->setState(false)) {
            $this->addStatusMessage(sprintf(_('Periodic executing of «%s» for ❮%s❯ stop 🛑'), $this->runtemplate->application->getRecordName(), $this->runtemplate->company->getRecordName()), 'success');
        } else {
            $this->addStatusMessage(_('Stopped module failed'), 'error');
        }
    }

    /**
     * Is this Action Suitable for Application.
     */
    public static function usableForApp(\MultiFlexi\Application $app): bool
    {
        return \is_object($app);
    }
}
