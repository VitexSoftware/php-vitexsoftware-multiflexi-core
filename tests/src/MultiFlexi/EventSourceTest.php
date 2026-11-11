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

use MultiFlexi\EventSource;
use PHPUnit\Framework\TestCase;

/**
 * Tenant isolation of an event source sharing one adapter database.
 */
class EventSourceTest extends TestCase
{
    private string $dbFile;

    protected function setUp(): void
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 'evsrc');
        $pdo = new \PDO('sqlite:'.$this->dbFile);
        $pdo->exec('CREATE TABLE changesapi (id INTEGER PRIMARY KEY, serverurl TEXT)');
        $pdo->exec('CREATE TABLE changes_cache (id INTEGER PRIMARY KEY, inversion INTEGER, source INTEGER, evidence TEXT, context TEXT)');
        $pdo->exec("INSERT INTO changesapi VALUES (1, 'https://abra.example:5434/c/firma_a'), (2, 'https://abra.example:5434/c/firma_b')");
        // The SAME inversion in two companies
        $pdo->exec("INSERT INTO changes_cache VALUES (10, 100, 1, 'faktura-vydana', '{\"x\":\"a\"}'), (11, 100, 2, 'faktura-vydana', NULL), (12, 101, 2, 'faktura-vydana', NULL)");
    }

    protected function tearDown(): void
    {
        @unlink($this->dbFile);
    }

    public function testTenantScopedSourceSeesOnlyOwnChanges(): void
    {
        $source = $this->source('https://abra.example:5434/c/firma_a');
        $changes = $source->getUnprocessedChanges();

        $this->assertCount(1, $changes);
        $this->assertSame(10, (int) $changes[0]['id']);
        $this->assertSame('a', $changes[0]['x'], 'context JSON is merged into the row');
    }

    public function testUnscopedSourceSeesAllCompanies(): void
    {
        $this->assertCount(3, $this->source('')->getUnprocessedChanges());
    }

    public function testWipeByRowIdKeepsOtherTenantWithSameInversion(): void
    {
        $source = $this->source('https://abra.example:5434/c/firma_a');
        $this->assertTrue($source->wipeCacheRecord(100, 10));

        $remaining = $this->source('')->getUnprocessedChanges();
        $this->assertSame([11, 12], array_map(static fn (array $r): int => (int) $r['id'], $remaining));
    }

    public function testWipeByInversionFallbackWithoutRowId(): void
    {
        $source = $this->source('');
        $this->assertTrue($source->wipeCacheRecord(100));
        $this->assertCount(1, $source->getUnprocessedChanges());
    }

    private function source(string $serverUrl): EventSource
    {
        $source = new EventSource();
        $source->takeData([
            'id' => 1,
            'name' => 'test',
            'db_connection' => 'sqlite',
            'db_database' => $this->dbFile,
            'enabled' => 1,
            'last_processed_id' => 0,
            'server_url' => $serverUrl,
        ]);

        return $source;
    }
}
