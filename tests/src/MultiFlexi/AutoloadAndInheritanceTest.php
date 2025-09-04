<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Autoload/inheritance coverage for classes that don't yet have dedicated tests.
 *
 * This test ensures that classes can be autoloaded and (where applicable)
 * extend or implement the expected base types without invoking constructors
 * that may depend on external services.
 */
final class AutoloadAndInheritanceTest extends TestCase
{
    /**
     * @return array<string, array{class: string, expected: 'exists'|'exception'|'extends_common_action'|'extends_common_executor'|'extends_credential_common'|'interface'|'external_optional'}>
     */
    public static function classMatrix(): array
    {
        return [
            // Core helpers
            'Requirement' => ['class' => \MultiFlexi\Requirement::class, 'expected' => 'exists'],
            'ScheduleLister' => ['class' => \MultiFlexi\ScheduleLister::class, 'expected' => 'exists'],
            'RunTplCreds' => ['class' => \MultiFlexi\RunTplCreds::class, 'expected' => 'exists'],
            'Topics' => ['class' => \MultiFlexi\Topics::class, 'expected' => 'exists'],
            'Topic' => ['class' => \MultiFlexi\Topic::class, 'expected' => 'exists'],
            'TopicManger' => ['class' => \MultiFlexi\TopicManger::class, 'expected' => 'exists'],
            'DatabaseEngine' => ['class' => 'MultiFlexi\\DatabaseEngine', 'expected' => 'interface'],
            'platformCompany' => ['class' => 'MultiFlexi\\platformCompany', 'expected' => 'interface'],

            // Env namespace
            'Env\\Application' => ['class' => \MultiFlexi\Env\Application::class, 'expected' => 'exists'],
            'Env\\Company' => ['class' => \MultiFlexi\Env\Company::class, 'expected' => 'exists'],
            'Env\\EaseLogger' => ['class' => \MultiFlexi\Env\EaseLogger::class, 'expected' => 'exists'],
            'Env\\MultiFlexi' => ['class' => \MultiFlexi\Env\MultiFlexi::class, 'expected' => 'exists'],
            'Env\\RunTemplate' => ['class' => \MultiFlexi\Env\RunTemplate::class, 'expected' => 'exists'],

            // Actions (should extend CommonAction)
            'Action\\CustomCommand' => ['class' => \MultiFlexi\Action\CustomCommand::class, 'expected' => 'extends_common_action'],
            'Action\\Github' => ['class' => \MultiFlexi\Action\Github::class, 'expected' => 'extends_common_action'],
            'Action\\LaunchJob' => ['class' => \MultiFlexi\Action\LaunchJob::class, 'expected' => 'extends_common_action'],
            'Action\\RedmineIssue' => ['class' => \MultiFlexi\Action\RedmineIssue::class, 'expected' => 'extends_common_action'],
            'Action\\Reschedule' => ['class' => \MultiFlexi\Action\Reschedule::class, 'expected' => 'extends_common_action'],
            'Action\\Sleep' => ['class' => \MultiFlexi\Action\Sleep::class, 'expected' => 'extends_common_action'],
            'Action\\Stop' => ['class' => \MultiFlexi\Action\Stop::class, 'expected' => 'extends_common_action'],
            'Action\\TriggerJenkins' => ['class' => \MultiFlexi\Action\TriggerJenkins::class, 'expected' => 'extends_common_action'],
            'Action\\WebHook' => ['class' => \MultiFlexi\Action\WebHook::class, 'expected' => 'extends_common_action'],
            'Action\\Zabbix' => ['class' => \MultiFlexi\Action\Zabbix::class, 'expected' => 'extends_common_action'],

            // Executors (should extend CommonExecutor)
            'Executor\\Azure' => ['class' => \MultiFlexi\Executor\Azure::class, 'expected' => 'extends_common_executor'],
            'Executor\\Docker' => ['class' => \MultiFlexi\Executor\Docker::class, 'expected' => 'extends_common_executor'],
            'Executor\\Kubernetes' => ['class' => \MultiFlexi\Executor\Kubernetes::class, 'expected' => 'extends_common_executor'],
            'Executor\\Native' => ['class' => \MultiFlexi\Executor\Native::class, 'expected' => 'extends_common_executor'],
            'Executor\\Podman' => ['class' => \MultiFlexi\Executor\Podman::class, 'expected' => 'extends_common_executor'],

            // Credential types (should extend CredentialType\Common or implement interface)
            'CredentialType\\AbraFlexi' => ['class' => \MultiFlexi\CredentialType\AbraFlexi::class, 'expected' => 'extends_credential_common'],
            'CredentialType\\Common' => ['class' => \MultiFlexi\CredentialType\Common::class, 'expected' => 'extends_credential_common'],
            'CredentialType\\Csas' => ['class' => \MultiFlexi\CredentialType\Csas::class, 'expected' => 'extends_credential_common'],
            'CredentialType\\EnvFile' => ['class' => \MultiFlexi\CredentialType\EnvFile::class, 'expected' => 'extends_credential_common'],
            'CredentialType\\FioBank' => ['class' => \MultiFlexi\CredentialType\FioBank::class, 'expected' => 'extends_credential_common'],
            'CredentialType\\Office365' => ['class' => \MultiFlexi\CredentialType\Office365::class, 'expected' => 'extends_credential_common'],
            'CredentialType\\RaiffeisenBank' => ['class' => \MultiFlexi\CredentialType\RaiffeisenBank::class, 'expected' => 'extends_credential_common'],
            'CredentialType\\SQLServer' => ['class' => \MultiFlexi\CredentialType\SQLServer::class, 'expected' => 'extends_credential_common'],
            'CredentialType\\VaultWarden' => ['class' => \MultiFlexi\CredentialType\VaultWarden::class, 'expected' => 'extends_credential_common'],
            'CredentialType\\mServer' => ['class' => \MultiFlexi\CredentialType\mServer::class, 'expected' => 'extends_credential_common'],

            // Zabbix subcomponents
            'Zabbix\\Request\\Metric' => ['class' => \MultiFlexi\Zabbix\Request\Metric::class, 'expected' => 'exists'],
            'Zabbix\\Request\\Packet' => ['class' => \MultiFlexi\Zabbix\Request\Packet::class, 'expected' => 'exists'],
            'Zabbix\\Response' => ['class' => \MultiFlexi\Zabbix\Response::class, 'expected' => 'exists'],
            'Zabbix\\Exception\\ZabbixNetworkException' => ['class' => \MultiFlexi\Zabbix\Exception\ZabbixNetworkException::class, 'expected' => 'exception'],
            'Zabbix\\Exception\\ZabbixResponseException' => ['class' => \MultiFlexi\Zabbix\Exception\ZabbixResponseException::class, 'expected' => 'exception'],

            // Misc
            'BitwardenServiceDelegate' => ['class' => 'MultiFlexi\\BitwardenServiceDelegate', 'expected' => 'external_optional'],
        ];
    }

    #[DataProvider('classMatrix')]
    public function testClassContracts(string $class, string $expected): void
    {
        // Ensure the class/interface can be autoloaded where applicable
        if ($expected === 'external_optional') {
            // Skip if external interface required by the class is not present
            if (!interface_exists('Jalismrs\\Bitwarden\\BitwardenServiceDelegate')) {
                $this->markTestSkipped('Skipping BitwardenServiceDelegate: external Jalismrs\\Bitwarden dependency not installed.');
                return;
            }
        }

        $exists = class_exists($class) || interface_exists($class);
        $this->assertTrue($exists, "Class or interface {$class} should exist and be autoloadable");

        switch ($expected) {
            case 'extends_common_action':
                $this->assertTrue(is_a($class, \MultiFlexi\CommonAction::class, true), sprintf('%s should extend %s', $class, \MultiFlexi\CommonAction::class));
                break;

            case 'extends_common_executor':
                $this->assertTrue(is_a($class, \MultiFlexi\CommonExecutor::class, true), sprintf('%s should extend %s', $class, \MultiFlexi\CommonExecutor::class));
                break;

            case 'extends_credential_common':
                $extendsCommon = is_a($class, \MultiFlexi\CredentialType\Common::class, true);
                $implementsIface = interface_exists('MultiFlexi\\credentialTypeInterface') && is_a($class, 'MultiFlexi\\credentialTypeInterface', true);
                $this->assertTrue($extendsCommon || $implementsIface, sprintf('%s should extend CredentialType\\Common or implement credentialTypeInterface', $class));
                break;

            case 'exception':
                $this->assertTrue(is_a($class, \Throwable::class, true), sprintf('%s should be an exception (extend Throwable)', $class));
                break;

            case 'interface':
                $this->assertTrue(interface_exists($class), sprintf('%s should be an interface', $class));
                break;

            case 'exists':
            default:
                // nothing else to assert beyond existence
                break;
        }
    }
}
