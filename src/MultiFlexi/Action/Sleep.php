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
class Sleep extends \MultiFlexi\CommonAction
{
    /**
     * Module Caption.
     */
    public static function name(): string
    {
        return _('Sleep');
    }

    /**
     * Module Description.
     */
    public static function description(): string
    {
        return _('delay for a specified amount of time');
    }

    /**
     * Perform Action.
     */
    public function perform(\MultiFlexi\Job $job): void
    {
        $this->addStatusMessage(sprintf(_('Sleepeng for %s seconds'), (int) $this->getDataValue('seconds')));
        sleep((int) $this->getDataValue('seconds'));
    }

    /**
     * Is this Action Suitable for Application.
     *
     * @param Application $app
     */
    #[\Override]
    public static function usableForApp($app): bool
    {
        return true;
    }

    #[\Override]
    public function initialData(string $mode): array
    {
        return [
            'seconds' => '60',
        ];
    }
}
