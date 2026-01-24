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

use MultiFlexi\Application;

/**
 * Description of RedmineIssue.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class RedmineIssue extends \MultiFlexi\CommonAction
{
    /**
     * Module Caption.
     *
     * @return string
     */
    public static function name()
    {
        return _('Redmine Issue');
    }

    /**
     * Module Description.
     *
     * @return string
     */
    public static function description()
    {
        return _('Make Redmine issue using Job output');
    }

    /**
     * Is this Action Situable for Application.
     */
    #[\Override]
    public static function usableForApp(Application $app): bool
    {
        return \is_object($app);
    }

    /**
     * Perform Action - create Redmine issue using Job output.
     */
    #[\Override]
    public function perform(\MultiFlexi\Job $job): void
    {
        $token = $this->getDataValue('token');
        $redmineUrl = rtrim($this->getDataValue('url'), '/'); // e.g. https://redmine.example.com
        $projectId = $this->getDataValue('project_id'); // Redmine project identifier

        $title = $this->runtemplate->application->getRecordName().' problem';
        $body = 'JOB ID: '.$job->getMyKey()."\n\n";
        $body .= 'Command: '.$job->getDataValue('command')."\n\n";
        $body .= 'ExitCode: '.$job->getDataValue('exitcode')."\n\n";
        $body .= "\nStdout:\n```\n".stripslashes($job->getDataValue('stdout'))."\n```";
        $body .= "\nSterr:\n```\n".stripslashes($job->getDataValue('stderr'))."\n```\n\n";
        $body .= 'MultiFlexi: '.\Ease\Shared::appName().' '.\Ease\Shared::appVersion()."\n\n";

        $data = [
            'issue' => [
                'project_id' => $projectId,
                'subject' => $title,
                'description' => $body,
                'tracker_id' => 1, // 1 = Bug, adjust as needed
            ],
        ];
        $data_string = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, \CURLOPT_URL, $redmineUrl.'/issues.json');
        curl_setopt($ch, \CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Redmine-API-Key: '.$token,
        ]);
        curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, \CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_VERBOSE, (bool) \Ease\Shared::cfg('API_DEBUG', false));

        $response = curl_exec($ch);
        $curlInfo = curl_getinfo($ch);
        $curlInfo['when'] = microtime();

        $success = ($curlInfo['http_code'] >= 200 && $curlInfo['http_code'] < 300);
        $this->addStatusMessage($response, $success ? 'success' : 'error');
        curl_close($ch);
    }

    /**
     * Initial data for action configuration.
     *
     * @param string $mode Mode
     *
     * @return array Default configuration
     */
    #[\Override]
    public function initialData(string $mode): array
    {
        return [
            'token' => '',
            'url' => '',
            'project_id' => '',
        ];
    }
}
