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
class ChainRuntemplate extends \MultiFlexi\CommonAction
{
    /**
     * Module Caption.
     */
    public static function name(): string
    {
        return _('Chain Runtemplate');
    }

    /**
     * Module Description.
     */
    public static function description(): string
    {
        return _('Run Another RunTemplate after current');
    }

    /**
     * Is this Action Suitable for Application.
     *
     * @param \MultiFlexi\Application $app
     */
    public static function usableForApp(\MultiFlexi\Application $app): bool
    {
        return \is_object($app);
    }

        /**
         * Perform ChainRuntemplate Action: Schedule another RunTemplate after current job.
         *
         * @param \MultiFlexi\Job $job Current job instance
         *
         * @return void
         * @throws \Ease\Exception if scheduling fails
         */
    public function perform(\MultiFlexi\Job $job): void
    {
        // Retrieve the chosen RunTemplate ID from action options
        $options = $this->getData();
        $rtid = $options['RunTemplate']['rtid'] ?? null;
        if (!$rtid) {
            $this->addStatusMessage(_('No RunTemplate selected for chaining.'), 'error');
            return;
        }

        // Load the RunTemplate to be scheduled
        $nextRunTemplate = new \MultiFlexi\RunTemplate($rtid);
        if (!$nextRunTemplate->getMyKey()) {
            $this->addStatusMessage(sprintf(_('RunTemplate #%d not found.'), $rtid), 'error');
            return;
        }

        // Schedule the new job to run immediately after current job
        $scheduled = new \DateTime();
        $executor = $job->getDataValue('executor') ?? 'Native';
        $scheduleType = 'chained';

        $newJob = new \MultiFlexi\Job();
        try {
            $newJob->prepareJob($nextRunTemplate, $job->environment(), $scheduled, $executor, $scheduleType);
            $this->addStatusMessage(sprintf(_('Chained RunTemplate #%d scheduled.'), $rtid), 'success');
        } catch (\Exception $ex) {
            $this->addStatusMessage(_('Failed to schedule chained RunTemplate: ').$ex->getMessage(), 'error');
        }
    }
}
