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

use MultiFlexi\ConfigField;
use MultiFlexi\ConfigFields;
use MultiFlexi\Macros;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MacrosTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-19 14:05:00');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function macroProvider(): array
    {
        return [
            'today' => ['{today}', '2026-11-19'],
            'yesterday' => ['{yesterday}', '2026-11-18'],
            'tomorrow' => ['{tomorrow}', '2026-11-20'],
            'offset minus' => ['{today-7}', '2026-11-12'],
            'offset plus' => ['{today+3}', '2026-11-22'],
            'now' => ['{now}', '2026-11-19 14:05:00'],
            'week_start' => ['{week_start}', '2026-11-16'],
            'week_end' => ['{week_end}', '2026-11-22'],
            'last_week_start' => ['{last_week_start}', '2026-11-09'],
            'last_week_end' => ['{last_week_end}', '2026-11-15'],
            'month_start' => ['{month_start}', '2026-11-01'],
            'month_end' => ['{month_end}', '2026-11-30'],
            'last_month_start' => ['{last_month_start}', '2026-10-01'],
            'last_month_end' => ['{last_month_end}', '2026-10-31'],
            'quarter_start' => ['{quarter_start}', '2026-10-01'],
            'quarter_end' => ['{quarter_end}', '2026-12-31'],
            'last_quarter_start' => ['{last_quarter_start}', '2026-07-01'],
            'last_quarter_end' => ['{last_quarter_end}', '2026-09-30'],
            'year_start' => ['{year_start}', '2026-01-01'],
            'year_end' => ['{year_end}', '2026-12-31'],
            'last_year_start' => ['{last_year_start}', '2025-01-01'],
            'last_year_end' => ['{last_year_end}', '2025-12-31'],
            'year' => ['{year}', '2026'],
            'month' => ['{month}', '11'],
            'day' => ['{day}', '19'],
            'last_month' => ['{last_month}', '2026-10'],
            'quarter' => ['{quarter}', '4'],
            'week' => ['{week}', '47'],
            'prev_workday' => ['{prev_workday}', '2026-11-18'],
            'format' => ['{yesterday|d.m.Y}', '18.11.2026'],
            'offset and format' => ['{today-1|Ymd}', '20261118'],
            'range' => ['{last_month_start}>{last_month_end}', '2026-10-01>2026-10-31'],
            'embedded' => ['report-{yesterday}.json', 'report-2026-11-18.json'],
            'unknown lowercase' => ['{foo}', '{foo}'],
            'env reference' => ['{ACCOUNT_NUMBER}', '{ACCOUNT_NUMBER}'],
            'json' => ['{"a":1}', '{"a":1}'],
            'plain' => ['yesterday', 'yesterday'],
        ];
    }

    #[DataProvider('macroProvider')]
    public function testResolve(string $template, string $expected): void
    {
        $this->assertSame($expected, Macros::resolve($template, $this->now));
    }

    public function testTimestamp(): void
    {
        $this->assertSame((string) $this->now->getTimestamp(), Macros::resolve('{timestamp}', $this->now));
    }

    public function testLastMonthAtMonthEnd(): void
    {
        $now = new \DateTimeImmutable('2026-03-31 08:00:00');
        $this->assertSame('2026-02', Macros::resolve('{last_month}', $now));
        $this->assertSame('2026-02-28', Macros::resolve('{last_month_end}', $now));
    }

    public function testPrevWorkdayOnMonday(): void
    {
        $monday = new \DateTimeImmutable('2026-11-23 06:00:00');
        $this->assertSame('2026-11-20', Macros::resolve('{prev_workday}', $monday));
    }

    public function testQuarterBoundaries(): void
    {
        $january = new \DateTimeImmutable('2026-01-15');
        $this->assertSame('2025-10-01', Macros::resolve('{last_quarter_start}', $january));
        $this->assertSame('2025-12-31', Macros::resolve('{last_quarter_end}', $january));
        $this->assertSame('1', Macros::resolve('{quarter}', $january));
    }

    public function testResolveFields(): void
    {
        $fields = new ConfigFields('test');
        $fields->addField((new ConfigField('REPORT_SCOPE', 'string'))->setValue('{yesterday}'));
        $fields->addField((new ConfigField('IMPORT_SCOPE', 'string'))->setDefaultValue('{last_month_start}>{last_month_end}'));
        $fields->addField((new ConfigField('ACCOUNT', 'string'))->setValue('123456'));

        $resolved = Macros::resolveFields($fields, $this->now);

        $this->assertSame('2026-11-18', $fields->getField('REPORT_SCOPE')->getValue());
        $this->assertSame('2026-10-01>2026-10-31', $fields->getField('IMPORT_SCOPE')->getValue());
        $this->assertSame('123456', $fields->getField('ACCOUNT')->getValue());
        $this->assertEquals(['REPORT_SCOPE' => '{yesterday}', 'IMPORT_SCOPE' => '{last_month_start}>{last_month_end}'], $resolved);
    }
}
