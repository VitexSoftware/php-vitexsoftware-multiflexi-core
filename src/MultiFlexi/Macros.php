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

namespace MultiFlexi;

/**
 * Built-in date macros for configuration values.
 *
 * Lowercase macros such as {yesterday} are resolved to concrete values when a
 * Job is created, so the stored job environment contains e.g. 2026-11-18
 * instead of the keyword. Uppercase {ENV_KEY} references are NOT handled here
 * (see ConfigFields::applyMacros()) and unknown names are left untouched.
 *
 * Syntax: {name}, {name+N} / {name-N} (day offset), {name|php-date-format}
 */
final class Macros
{
    /**
     * Matches {name}, {name-7}, {name+3}, {name|d.m.Y}, {name-1|d.m.Y}.
     */
    public const PATTERN = '/\{([a-z][a-z_]*)([+-]\d+)?(?:\|([^{}]+))?\}/';

    /**
     * Resolve all known macros in given string.
     */
    public static function resolve(string $value, ?\DateTimeImmutable $now = null): string
    {
        if (!str_contains($value, '{')) {
            return $value;
        }

        $now ??= new \DateTimeImmutable();

        return (string) preg_replace_callback(
            self::PATTERN,
            static function (array $match) use ($now): string {
                $resolved = self::expand($match[1], (int) ($match[2] ?? 0), $match[3] ?? '', $now);

                return $resolved ?? $match[0];
            },
            $value,
        );
    }

    /**
     * Resolve macros in all field values (value, or default value when value is unset).
     *
     * @return array<string, string> field code => macro template that was resolved
     */
    public static function resolveFields(ConfigFields $fields, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $resolved = [];

        foreach ($fields->getFields() as $code => $field) {
            if (!$field instanceof ConfigField) {
                continue;
            }

            $template = $field->getValue() ?? $field->getDefaultValue();

            if ($template === null || !preg_match(self::PATTERN, $template)) {
                continue;
            }

            $value = self::resolve($template, $now);

            if ($value !== $template) {
                $field->setValue($value);
                $resolved[(string) $code] = $template;
            }
        }

        return $resolved;
    }

    /**
     * List of supported macro names.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return [
            'today', 'yesterday', 'tomorrow', 'now', 'timestamp',
            'week_start', 'week_end', 'last_week_start', 'last_week_end',
            'month_start', 'month_end', 'last_month_start', 'last_month_end',
            'quarter_start', 'quarter_end', 'last_quarter_start', 'last_quarter_end',
            'year_start', 'year_end', 'last_year_start', 'last_year_end',
            'year', 'month', 'day', 'last_month', 'quarter', 'week', 'prev_workday',
        ];
    }

    /**
     * Expand single macro; null when the name is unknown.
     */
    private static function expand(string $name, int $offsetDays, string $format, \DateTimeImmutable $now): ?string
    {
        $today = $now->setTime(0, 0);
        $quarterStart = $today->setDate((int) $today->format('Y'), intdiv((int) $today->format('n') - 1, 3) * 3 + 1, 1);

        switch ($name) {
            case 'quarter':
                $date = $today->modify(sprintf('%+d days', $offsetDays));

                return (string) (intdiv((int) $date->format('n') - 1, 3) + 1);
            case 'prev_workday':
                $date = $today->modify('-1 day');

                while ((int) $date->format('N') > 5) {
                    $date = $date->modify('-1 day');
                }

                $default = 'Y-m-d';

                break;

            default:
                $definition = match ($name) {
                    'today' => [$today, 'Y-m-d'],
                    'yesterday' => [$today->modify('-1 day'), 'Y-m-d'],
                    'tomorrow' => [$today->modify('+1 day'), 'Y-m-d'],
                    'now' => [$now, 'Y-m-d H:i:s'],
                    'timestamp' => [$now, 'U'],
                    'week_start' => [$today->modify('monday this week'), 'Y-m-d'],
                    'week_end' => [$today->modify('sunday this week'), 'Y-m-d'],
                    'last_week_start' => [$today->modify('monday last week'), 'Y-m-d'],
                    'last_week_end' => [$today->modify('sunday last week'), 'Y-m-d'],
                    'month_start' => [$today->modify('first day of this month'), 'Y-m-d'],
                    'month_end' => [$today->modify('last day of this month'), 'Y-m-d'],
                    'last_month_start' => [$today->modify('first day of last month'), 'Y-m-d'],
                    'last_month_end' => [$today->modify('last day of last month'), 'Y-m-d'],
                    'quarter_start' => [$quarterStart, 'Y-m-d'],
                    'quarter_end' => [$quarterStart->modify('+2 months')->modify('last day of this month'), 'Y-m-d'],
                    'last_quarter_start' => [$quarterStart->modify('-3 months'), 'Y-m-d'],
                    'last_quarter_end' => [$quarterStart->modify('-1 day'), 'Y-m-d'],
                    'year_start' => [$today->setDate((int) $today->format('Y'), 1, 1), 'Y-m-d'],
                    'year_end' => [$today->setDate((int) $today->format('Y'), 12, 31), 'Y-m-d'],
                    'last_year_start' => [$today->setDate((int) $today->format('Y') - 1, 1, 1), 'Y-m-d'],
                    'last_year_end' => [$today->setDate((int) $today->format('Y') - 1, 12, 31), 'Y-m-d'],
                    'year' => [$today, 'Y'],
                    'month' => [$today, 'm'],
                    'day' => [$today, 'd'],
                    'last_month' => [$today->modify('first day of last month'), 'Y-m'],
                    'week' => [$today, 'W'],
                    default => null,
                };

                if ($definition === null) {
                    return null;
                }

                [$date, $default] = $definition;
        }

        if ($offsetDays !== 0) {
            $date = $date->modify(sprintf('%+d days', $offsetDays));
        }

        return $date->format($format !== '' ? $format : $default);
    }
}
