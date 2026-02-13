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
     * Perform Reschedule Action: Schedule the current job's RunTemplate for future execution.
     *
     * @param \MultiFlexi\Job $job Current job instance
     *
     * @throws \Ease\Exception if scheduling fails
     */
    public function perform(\MultiFlexi\Job $job): void
    {
        // Determine when to reschedule (default: +1 hour, can be customized via options)
        $options = $this->getData();
        $delay = $options['delay'] ?? 3600; // seconds
        $scheduled = new \DateTime();
        $scheduled->modify('+'.(int) $delay.' seconds');

        $runTemplate = $job->runTemplate;

        if (!$runTemplate || !$runTemplate->getMyKey()) {
            $this->addStatusMessage(_('No valid RunTemplate found for rescheduling.'), 'error');

            return;
        }

        $executor = $job->getDataValue('executor') ?? 'Native';
        $scheduleType = 'reschedule';

        $newJob = new \MultiFlexi\Job();

        try {
            $newJob->prepareJob($runTemplate, $job->environment(), $scheduled, $executor, $scheduleType);
            $this->addStatusMessage(sprintf(_('RunTemplate #%d rescheduled for %s.'), $runTemplate->getMyKey(), $scheduled->format('Y-m-d H:i:s')), 'success');
        } catch (\Exception $ex) {
            $this->addStatusMessage(_('Failed to reschedule RunTemplate: ').$ex->getMessage(), 'error');
        }
    }

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
     * @param \MultiFlexi\Application $app
     */
    public static function usableForApp($app): bool
    {
        return \is_object($app);
    }
}
