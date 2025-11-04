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
 * Description of RedmineIssue.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class ToDo extends \MultiFlexi\CommonAction
{
    /**
     * Module Caption.
     *
     * @return string
     */
    public static function name()
    {
        return _('ToDo Issue');
    }

    /**
     * Module Description.
     *
     * @return string
     */
    public static function description()
    {
        return _('Make ToDo issue using Job output');
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

    /**
     * Perform Action - create ToDo issue using Job output.
     */
    public function perform(\MultiFlexi\Job $job): void
    {
        $title = $this->runtemplate->application->getRecordName().' problem';
        $body = 'JOB ID: '.$job->getMyKey()."\n\n";
        $body .= 'Command: '.$job->getDataValue('command')."\n\n";
        $body .= 'ExitCode: '.$job->getDataValue('exitcode')."\n\n";
        $body .= "\nStdout:\n```\n".stripslashes($job->getDataValue('stdout'))."\n```";
        $body .= "\nSterr:\n```\n".stripslashes($job->getDataValue('stderr'))."\n```\n\n";
        $body .= 'MultiFlexi: '.\Ease\Shared::appName().' '.\Ease\Shared::appVersion()."\n\n";
    }
}
