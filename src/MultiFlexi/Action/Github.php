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
class Github extends \MultiFlexi\CommonAction
{
    /**
     * Module Caption.
     *
     * @return string
     */
    public static function name()
    {
        return _('Github Issue');
    }

    /**
     * Module Description.
     *
     * @return string
     */
    public static function description()
    {
        return _('Make Github issue using Job output');
    }

    /**
     * Is this Action Situable for Application.
     */
    public static function usableForApp(Application $app): bool
    {
        return (null === strstr($app->getDataValue('homepage'), 'github.com')) === false;
    }

    /**
     * Perform Action.
     */
    public function perform(\MultiFlexi\Job $job): void
    {
        $token = $this->getDataValue('token');
        $headerValue = ' Bearer '.$token;
        $header = [
            'Authorization:'.$headerValue,
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ];
        $title = $this->runtemplate->application->getRecordName().' problem';
        $body = 'JOB ID: '.$job->getMyKey()."\n\n";

        $body .= 'Command: '.$job->getDataValue('command')."\n\n";
        $body .= 'ExitCode: '.$job->getDataValue('exitcode')."\n\n";

        $body .= "\nStdout:\n```\n".stripslashes($job->getDataValue('stdout'))."\n```";
        $body .= "\nSterr:\n```\n".stripslashes($job->getDataValue('stderr'))."\n```\n\n";

        $body .= 'MultiFlexi: '.\Ease\Shared::appName().' '.\Ease\Shared::appVersion()."\n\n";

        $labels = ['Bug'];
        $data = ['title' => $title, 'body' => $body, 'labels' => $labels];

        $data_string = json_encode($data);

        $ch = curl_init();

        $userRepo = parse_url($this->runtemplate->application->getDataValue('homepage'), \PHP_URL_PATH);

        curl_setopt($ch, \CURLOPT_URL, 'https://api.github.com/repos'.$userRepo.'/issues');
        curl_setopt($ch, \CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, \CURLOPT_USERAGENT, \Ease\Shared::appName().' '.\Ease\Shared::appVersion());
        curl_setopt($ch, \CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true); // return content as a string from curl_exec
        curl_setopt($ch, \CURLOPT_VERBOSE, (bool) \Ease\Shared::cfg('API_DEBUG', false)); // For debugging

        $response = curl_exec($ch);

        $curlInfo = curl_getinfo($ch);
        $curlInfo['when'] = microtime();

        $this->addStatusMessage($response, $curlInfo['http_code'] === 200 ? 'success' : 'error');
        curl_close($ch);
    }
}
