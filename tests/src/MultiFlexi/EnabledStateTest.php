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

namespace Test\MultiFlexi;

use MultiFlexi\Application;
use MultiFlexi\Company;
use MultiFlexi\ConfigFields;
use MultiFlexi\Job;
use MultiFlexi\JobDeniedException;
use MultiFlexi\RunTemplate;

/**
 * Disabled companies run nothing, disabled applications are not started automatically.
 *
 * Needs a migrated database; every record created here is removed again.
 */
class EnabledStateTest extends \PHPUnit\Framework\TestCase
{
    private Company $company;
    private Application $app;
    private RunTemplate $runTemplate;

    protected function setUp(): void
    {
        $uniq = bin2hex(random_bytes(4));

        $this->company = new Company();
        $this->company->takeData(['name' => 'EnabledTest '.$uniq, 'slug' => strtoupper($uniq), 'email' => '', 'ic' => '', 'logo' => '']);
        $this->company->insertToSQL();

        $this->app = new Application();
        $this->app->takeData(['name' => 'EnabledTest '.$uniq, 'executable' => '/bin/true', 'deploy' => '', 'homepage' => '', 'requirements' => '', 'tags' => '', 'description' => '', 'uuid' => \Ease\Functions::guidv4()]);
        $this->app->insertToSQL();

        $this->runTemplate = new RunTemplate();
        $id = $this->runTemplate->insertToSQL(['name' => 'EnabledTest '.$uniq, 'app_id' => $this->app->getMyKey(), 'company_id' => $this->company->getMyKey(), 'interv' => 'n', 'active' => 1]);
        $this->runTemplate = new RunTemplate((int) $id);
    }

    protected function tearDown(): void
    {
        (new Job())->getFluentPDO()->deleteFrom('job')->where('runtemplate_id', $this->runTemplate->getMyKey())->execute();

        foreach (['runtemplate' => $this->runTemplate, 'apps' => $this->app, 'company' => $this->company] as $table => $record) {
            $record->getFluentPDO()->deleteFrom($table)->where('id', $record->getMyKey())->execute();
        }
    }

    public function testNewRecordsAreEnabled(): void
    {
        $this->assertEquals(1, (new Company((int) $this->company->getMyKey()))->getDataValue('enabled'));
        $this->assertEquals(1, (new Application((int) $this->app->getMyKey()))->getDataValue('enabled'));
        $this->assertNull($this->runTemplate->automaticRunDenial());
    }

    public function testDisabledApplicationIsDeniedAutomaticRunOnly(): void
    {
        $this->app->updateToSQL(['enabled' => 0], ['id' => $this->app->getMyKey()]);
        $this->assertNotNull($this->runTemplate->automaticRunDenial());
        $this->assertTrue($this->runTemplate->isCompanyEnabled());
    }

    public function testDisabledCompanyRefusesEveryNewJob(): void
    {
        $this->company->updateToSQL(['enabled' => 0], ['id' => $this->company->getMyKey()]);
        $this->assertNotNull($this->runTemplate->automaticRunDenial());

        foreach (['adhoc', Job::SCHEDULE_TYPE_ADHOC_CLI, Job::SCHEDULE_TYPE_EVENT, 'chained', 'daily'] as $type) {
            foreach (['prepareJob', 'newJob'] as $method) {
                try {
                    (new Job())->{$method}($this->runTemplate, new ConfigFields(''), new \DateTime(), 'Native', $type);
                    $this->fail($method.' must refuse '.$type);
                } catch (JobDeniedException $exc) {
                    $this->assertStringContainsString((string) $this->company->getMyKey(), $exc->getMessage());
                }
            }
        }
    }

    public function testQueuedJobIsClosedWithoutLaunch(): void
    {
        $job = new Job();
        $user = $job->getFluentPDO()->from('user')->orderBy('id')->limit(1)->fetch();
        $id = $job->getFluentPDO()->insertInto('job', [
            'app_id' => $this->app->getMyKey(), 'company_id' => $this->company->getMyKey(), 'runtemplate_id' => $this->runTemplate->getMyKey(),
            'env' => serialize(new ConfigFields('')), 'command' => '/bin/true', 'executor' => 'Native', 'launched_by' => $user['id'],
        ])->execute();
        $this->assertFalse((new Job((int) $id))->denyWhenCompanyDisabled());

        $this->company->updateToSQL(['enabled' => 0], ['id' => $this->company->getMyKey()]);
        (new Job((int) $id))->performJob();
        $row = $job->getFluentPDO()->from('job')->where('id', $id)->fetch();
        $this->assertEquals(Job::EXIT_DENIED, $row['exitcode']);
        $this->assertNotEmpty($row['block_reason']);
        $this->assertNotEmpty($row['end']);
    }
}
