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
 * Description of TriggerJenkins.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class Zabbix extends \MultiFlexi\CommonAction
{
    /**
     * Module Caption.
     *
     * @return string
     */
    public static function name()
    {
        return _('Zabbix');
    }

    /**
     * Module Description.
     *
     * @return string
     */
    public static function description()
    {
        return _('Send Job Output to Zabbix');
    }

    /**
     * Is this Action Suitable for Application.
     *
     * @param Application $app
     */
    #[\Override]
    public static function usableForApp($app): bool
    {
        return \is_object($app);
    }

    /**
     * Perform Action.
     */
    #[\Override]
    public function perform(\MultiFlexi\Job $job): void
    {
        $overrideHost = $job->getRunTemplate()->getCompany()->getDataValue('zabbix_host');
        $me = empty($overrideHost) ? \Ease\Shared::cfg('ZABBIX_HOST', gethostname()) : $overrideHost;

        $server = \Ease\Shared::cfg('ZABBIX_SERVER');

        if ($server) {
            $zabbixKey = 'zabbix_action-['.(empty($this->getDataValue('key') || ($this->getDataValue('key') === '{COMPANY_CODE}-{APP_CODE}-{RUNTEMPLATE_ID}-data')) ? $this->defaultKey() : $this->getDataValue('key')).']';
            $dataForZabbix = null;
            $metricsfile = $this->getDataValue('metricsfile');

            if (empty($metricsfile)) {
                $dataForZabbix = stripslashes((string) $job->getDataValue('stdout'));
            } else {
                if (file_exists($metricsfile)) {
                    $dataForZabbix = file_get_contents($metricsfile); // TODO: Use Executor
                } else {
                    $this->addStatusMessage(_('Required metrics file not found '), 'warning');
                }
            }

            if ($dataForZabbix) {
                $artifactor = new \MultiFlexi\Artifact();
                $artifactor->createArtifact($job->getMyKey(), $dataForZabbix, $metricsfile, file_exists((string) $metricsfile) ? mime_content_type($metricsfile) : 'application/json', sprintf(_('Reported to Zabbix: from %s sent to %s as %s '), $me, $server, $zabbixKey));

                if (\Ease\Shared::cfg('USE_ZABBIX_SENDER', false) && file_exists('/usr/bin/zabbix_sender')) {
                    $cmd = sprintf("zabbix_sender -v -z %s -s %s -k %s -o '%s'", $server, $me, $zabbixKey, addslashes($dataForZabbix));
                    $this->addStatusMessage($cmd, 'debug');
                    exec($cmd, $output, $return_var);
                    $this->addStatusMessage((string) $return_var.':'.implode("\n", $output), 'debug');
                } else {
                    $packet = new \MultiFlexi\Zabbix\Request\Packet();
                    $packet->addMetric((new \MultiFlexi\Zabbix\Request\Metric($zabbixKey, $dataForZabbix))->withHostname($me));
                    $zabbixSender = new \MultiFlexi\ZabbixSender($server);

                    try {
                        $zabbixSender->send($packet);
                        $this->addStatusMessage(sprintf(_('Job metric %s sent to %s as %s: %s'), $zabbixKey, $server, $me, json_encode($packet)), 'debug');
                    } catch (\Exception $exc) {
                    }
                }
            } else {
                $this->addStatusMessage(_('No Data for Zabbix provided'), 'warning');
            }
        } else {
            $this->addStatusMessage(_('No Zabbix server defined'), 'warning');
        }
    }

    public function defaultKey(): string
    {
        return $this->runtemplate->getCompany()->getDataValue('code').'-'.$this->runtemplate->getApplication()->getDataValue('code').'-'.$this->runtemplate->getMyKey().'-data';
    }
}
