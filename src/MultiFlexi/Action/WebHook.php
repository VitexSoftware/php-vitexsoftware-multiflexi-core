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
class WebHook extends \MultiFlexi\CommonAction
{
    /**
     * Module Caption.
     *
     * @return string
     */
    public static function name()
    {
        return _('WebHook');
    }

    /**
     * Module Description.
     *
     * @return string
     */
    public static function description()
    {
        return _('Post Job output to URI');
    }

    /**
     * Perform Action.
     */
    public function perform(\MultiFlexi\Job $job): void
    {
        $uri = $this->getDataValue('uri');

        if ($uri) {
            $payload = stripslashes($this->runtemplate->getDataValue('stdout'));

            $this->addStatusMessage(_('Perform begin'));
            // $exitCode = $this->job->executor->launch($command);
            $ch = curl_init();
            curl_setopt($ch, \CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);

            // set URL and other appropriate options
            curl_setopt($ch, \CURLOPT_URL, $uri);
            curl_setopt($ch, \CURLOPT_HEADER, 0);

            // grab URL and pass it to the browser
            $this->addStatusMessage((string) curl_exec($ch), 'debug');

            // close cURL resource, and free up system resources
            curl_close($ch);
            $this->addStatusMessage(_('Perform done'));
        }
    }

    /**
     * Is this Action Suitable for Application.
     *
     * @param Application $app
     */
    public static function usableForApp($app): bool
    {
        return true;
    }
}
