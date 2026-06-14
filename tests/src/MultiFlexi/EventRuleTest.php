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

use MultiFlexi\EventRule;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MultiFlexi\EventRule — focusing on buildEnvOverrides() and resolveSelector().
 */
class EventRuleTest extends TestCase
{
    /**
     * Build an EventRule instance with a given env_mapping (no DB required).
     */
    private function makeRule(array $mapping): EventRule
    {
        $rule = new EventRule();
        $rule->setDataValue('env_mapping', json_encode($mapping));

        return $rule;
    }

    /**
     * @covers \MultiFlexi\EventRule::buildEnvOverrides
     */
    public function testFlatKeyExtraction(): void
    {
        $rule = $this->makeRule(['OUT' => 'someKey']);
        $source = ['someKey' => 'hello'];

        $result = $rule->buildEnvOverrides($source);

        $this->assertArrayHasKey('OUT', $result);
        $this->assertSame('hello', $result['OUT']);
    }

    /**
     * @covers \MultiFlexi\EventRule::buildEnvOverrides
     */
    public function testDotPathExtraction(): void
    {
        $rule = $this->makeRule(['OUT' => 'a.b']);
        $source = ['a' => ['b' => 'val']];

        $result = $rule->buildEnvOverrides($source);

        $this->assertArrayHasKey('OUT', $result);
        $this->assertSame('val', $result['OUT']);
    }

    /**
     * @covers \MultiFlexi\EventRule::buildEnvOverrides
     */
    public function testJsonPathExtraction(): void
    {
        $rule = $this->makeRule(['OUT' => '$.a.b']);
        $source = ['a' => ['b' => '42']];

        $result = $rule->buildEnvOverrides($source);

        $this->assertArrayHasKey('OUT', $result);
        $this->assertSame('42', $result['OUT']);
    }

    /**
     * @covers \MultiFlexi\EventRule::buildEnvOverrides
     */
    public function testMissingSelectorKeyAbsentFromResult(): void
    {
        $rule = $this->makeRule(['OUT' => 'nonexistent.key']);
        $source = ['something' => 'else'];

        $result = $rule->buildEnvOverrides($source);

        // The mapped key should not appear when selector cannot be resolved
        $this->assertArrayNotHasKey('OUT', $result);
    }

    /**
     * @covers \MultiFlexi\EventRule::buildEnvOverrides
     */
    public function testAtFileSelector(): void
    {
        $rule = $this->makeRule(['INPUT_FILE' => '@file:invoices']);
        $producedFiles = ['invoices' => '{"invoice": 1}'];

        $result = $rule->buildEnvOverrides([], $producedFiles);

        $this->assertArrayHasKey('INPUT_FILE', $result);
        $path = $result['INPUT_FILE'];

        // Result must be a path to an existing temp file
        $this->assertFileExists($path);

        // The file must contain the expected content
        $this->assertSame('{"invoice": 1}', file_get_contents($path));

        // Cleanup
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * @covers \MultiFlexi\EventRule::buildEnvOverrides
     */
    public function testAtFileSelectorWithArrayContent(): void
    {
        $rule = $this->makeRule(['RESULT' => '@file:data']);
        $producedFiles = ['data' => ['key' => 'value', 'num' => 42]];

        $result = $rule->buildEnvOverrides([], $producedFiles);

        $this->assertArrayHasKey('RESULT', $result);
        $path = $result['RESULT'];
        $this->assertFileExists($path);

        $decoded = json_decode(file_get_contents($path), true);
        $this->assertSame(['key' => 'value', 'num' => 42], $decoded);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * @covers \MultiFlexi\EventRule::buildEnvOverrides
     */
    public function testAtFileSelectorMissingProducedFile(): void
    {
        $rule = $this->makeRule(['INPUT' => '@file:missing']);
        $producedFiles = [];  // no 'missing' key

        $result = $rule->buildEnvOverrides([], $producedFiles);

        $this->assertArrayNotHasKey('INPUT', $result);
    }

    /**
     * @covers \MultiFlexi\EventRule::buildEnvOverrides
     */
    public function testEmptyMappingReturnsOnlyEventMetadata(): void
    {
        $rule = $this->makeRule([]);
        $source = ['evidence' => 'invoice', 'operation' => 'create', 'inversion' => '0', 'recordid' => '99'];

        $result = $rule->buildEnvOverrides($source);

        $this->assertArrayHasKey('EVENT_EVIDENCE', $result);
        $this->assertSame('invoice', $result['EVENT_EVIDENCE']);
        $this->assertSame('create', $result['EVENT_OPERATION']);
        $this->assertSame('0', $result['EVENT_INVERSION']);
        $this->assertSame('99', $result['EVENT_RECORD_ID']);
    }

    /**
     * @covers \MultiFlexi\EventRule::buildEnvOverrides
     */
    public function testDeepNestedDotPath(): void
    {
        $rule = $this->makeRule(['VAL' => 'a.b.c']);
        $source = ['a' => ['b' => ['c' => 'deep']]];

        $result = $rule->buildEnvOverrides($source);

        $this->assertSame('deep', $result['VAL']);
    }

    /**
     * Smoke test: getRulesForRunTemplate returns an array (may be empty without DB).
     *
     * @covers \MultiFlexi\EventRule::getRulesForRunTemplate
     */
    public function testGetRulesForRunTemplateReturnsArray(): void
    {
        try {
            $result = EventRule::getRulesForRunTemplate(999999);
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB unavailable: '.$e->getMessage());
        }
    }
}
