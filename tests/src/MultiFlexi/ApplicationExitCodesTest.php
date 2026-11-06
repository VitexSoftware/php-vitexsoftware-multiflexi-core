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
use MultiFlexi\Job;

/**
 * Tests for exit code definitions (app_exit_codes table):
 * import from app JSON, localized lookup with fallback, listing and export.
 */
class ApplicationExitCodesTest extends \PHPUnit\Framework\TestCase
{
    private const TEST_UUID = '123e4567-e89b-42d3-a456-426614174999';

    private int $appId = 0;

    private ?int $jobId = null;

    protected function setUp(): void
    {
        $spec = [
            '$schema' => 'https://multiflexi.eu/schema/application.json',
            'name' => 'Exit Codes Test App',
            'description' => 'Temporary application used by ApplicationExitCodesTest',
            'executable' => '/bin/true',
            'environment' => new \stdClass(),
            'uuid' => self::TEST_UUID,
            'version' => '1.0.0',
            'schemaVersion' => '3.4.0',
            'exitCodes' => [
                '0' => [
                    'description' => ['en' => 'Success', 'cs' => 'Úspěch'],
                    'severity' => 'success',
                    'retry' => false,
                ],
                '7' => [
                    'description' => ['en' => 'App-specific failure'],
                    'severity' => 'error',
                    'retry' => true,
                ],
            ],
        ];

        $jsonFile = tempnam(sys_get_temp_dir(), 'mfapp').'.json';
        file_put_contents($jsonFile, (string) json_encode($spec));

        $app = new Application();
        $app->importAppJson($jsonFile);
        unlink($jsonFile);

        $found = (new Application())->listingQuery()->where('uuid', self::TEST_UUID)->fetch();
        $this->assertNotFalse($found, 'Test application was not imported');
        $this->appId = (int) $found['id'];
    }

    protected function tearDown(): void
    {
        $pdo = (new Application())->getFluentPDO()->getPdo();

        if ($this->jobId !== null) {
            $pdo->prepare('DELETE FROM job WHERE id = :id')->execute([':id' => $this->jobId]);
            $this->jobId = null;
        }

        if ($this->appId > 0) {
            // app_exit_codes rows are removed by the FK ON DELETE CASCADE
            $app = new Application();
            $app->loadFromSQL($this->appId);
            $app->deleteFromSQL();
            $this->appId = 0;
        }
    }

    public function testExitCodeDescriptionLocalized(): void
    {
        $app = new Application($this->appId);
        $this->assertSame('Úspěch', $app->exitCodeDescription(0, 'cs'));
        $this->assertSame('Success', $app->exitCodeDescription(0, 'en'));
    }

    public function testExitCodeDescriptionFallsBackToEnglish(): void
    {
        $app = new Application($this->appId);
        // code 7 is documented in English only — any other language must fall back to en
        $this->assertSame('App-specific failure', $app->exitCodeDescription(7, 'de'));
        $this->assertSame('App-specific failure', $app->exitCodeDescription(7, 'cs'));
    }

    public function testExitCodeDescriptionUndocumented(): void
    {
        $app = new Application($this->appId);
        $this->assertSame(_('Undocumented'), $app->exitCodeDescription(99, 'en'));
    }

    public function testExitCodeDescriptionFallsBackToGenericSysexits(): void
    {
        $app = new Application($this->appId);
        // code 127 is not documented by the test app — generic sysexits meaning applies
        $this->assertFalse($app->hasExitCode(127));
        $this->assertSame('Command not found', $app->exitCodeDescription(127, 'en'));
        $this->assertTrue($app->hasExitCode(7));
    }

    public function testGetExitCodes(): void
    {
        $app = new Application($this->appId);
        $codes = $app->getExitCodes('en');

        $this->assertArrayHasKey(0, $codes);
        $this->assertArrayHasKey(7, $codes);
        $this->assertSame('Success', $codes[0]['description']);
        $this->assertSame('success', $codes[0]['severity']);
        $this->assertFalse($codes[0]['retry']);
        $this->assertSame('App-specific failure', $codes[7]['description']);
        $this->assertSame('error', $codes[7]['severity']);
        $this->assertTrue($codes[7]['retry']);
    }

    public function testGetExitCodesLocalized(): void
    {
        $app = new Application($this->appId);
        $codes = $app->getExitCodes('cs');
        $this->assertSame('Úspěch', $codes[0]['description']);
        // no Czech translation for code 7 — falls back to English
        $this->assertSame('App-specific failure', $codes[7]['description']);
    }

    public function testJobGetExitCodeDescription(): void
    {
        $pdo = (new Application())->getFluentPDO()->getPdo();
        $companyId = (int) $pdo->query('SELECT id FROM company ORDER BY id LIMIT 1')->fetchColumn();
        $userId = (int) $pdo->query('SELECT id FROM user ORDER BY id LIMIT 1')->fetchColumn();

        $stmt = $pdo->prepare('INSERT INTO job (app_id, company_id, launched_by, exitcode) VALUES (:app, :company, :user, 7)');
        $stmt->execute([':app' => $this->appId, ':company' => $companyId, ':user' => $userId]);
        $this->jobId = (int) $pdo->lastInsertId();

        $job = new Job($this->jobId);
        $this->assertSame('App-specific failure', $job->getExitCodeDescription('en'));
        $this->assertSame('App-specific failure', $job->getExitCodeDescription('fr'));
    }

    public function testAppJsonExportContainsExitCodes(): void
    {
        $app = new Application($this->appId);
        $export = json_decode($app->getAppJson(), true);

        $this->assertArrayHasKey('exitCodes', $export);
        $this->assertSame('3.4.0', $export['schemaVersion']);
        $this->assertEqualsCanonicalizing(['en' => 'Success', 'cs' => 'Úspěch'], $export['exitCodes']['0']['description']);
        $this->assertSame('success', $export['exitCodes']['0']['severity']);
        $this->assertFalse($export['exitCodes']['0']['retry']);
        $this->assertTrue($export['exitCodes']['7']['retry']);
    }
}
