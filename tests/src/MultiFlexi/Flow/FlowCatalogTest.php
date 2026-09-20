<?php

declare(strict_types=1);

namespace Test\MultiFlexi\Flow;

use MultiFlexi\Flow\FlowExecutableCatalog;
use MultiFlexi\Flow\FlowMsg;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MultiFlexi\Flow\FlowExecutableCatalog
 * @covers \MultiFlexi\Flow\FlowMsg
 */
final class FlowCatalogTest extends TestCase
{
    public function testExecutableCatalogAcceptsKnownTypes(): void
    {
        $errors = FlowExecutableCatalog::validateNodes([
            ['id' => 'a', 'type' => 'multiflexi-event'],
            ['id' => 'b', 'type' => 'multiflexi-runtemplate'],
            ['id' => 'c', 'type' => 'switch'],
            ['id' => 'd', 'type' => 'comment'],
        ]);

        $this->assertSame([], $errors);
    }

    public function testExecutableCatalogRejectsFunctionNode(): void
    {
        $errors = FlowExecutableCatalog::validateNodes([
            ['id' => 'a', 'type' => 'function'],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('function', $errors[0]);
    }

    public function testFlowMsgRoundTrip(): void
    {
        $msg = new FlowMsg(
            payload: ['evidence' => 'banka'],
            env: ['DOCID' => '123'],
            produced: ['result' => ['matched_documents' => ['1']]],
            meta: ['exitcode' => 0],
        );

        $copy = FlowMsg::fromJson($msg->toJson());

        $this->assertSame('banka', $copy->payload['evidence']);
        $this->assertSame('123', $copy->env['DOCID']);
        $this->assertSame(0, $copy->meta['exitcode']);
    }
}
