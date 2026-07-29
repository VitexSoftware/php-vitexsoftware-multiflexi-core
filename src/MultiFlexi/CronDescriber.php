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
 * Translates a RunTemplate's interval code / cron expression into a short,
 * human-readable, localized sentence (e.g. "Every 3 minutes.").
 *
 * Only a bounded set of common cron shapes is recognized; anything else
 * yields null so no misleading text is shown.
 */
final class CronDescriber
{
    /**
     * @param string $intervCode RunTemplate 'interv' column value (y/m/w/d/h/i/n/c)
     * @param string $cron       RunTemplate 'cron' column value, used only when $intervCode === 'c'
     */
    public static function describe(string $intervCode, string $cron): ?string
    {
        if ($intervCode !== 'c') {
            return self::describeFixedInterval($intervCode);
        }

        return self::describeCron(trim($cron));
    }

    private static function describeFixedInterval(string $code): ?string
    {
        switch ($code) {
            case 'i':
                return ngettext('Every minute.', 'Every %d minutes.', 1);
            case 'h':
                return ngettext('Every hour.', 'Every %d hours.', 1);
            case 'd':
                return _('Every day.');
            case 'w':
                return _('Every week.');
            case 'm':
                return _('Every month.');
            case 'y':
                return _('Every year.');

            default:
                return null;
        }
    }

    private static function describeCron(string $cron): ?string
    {
        if ($cron === '') {
            return null;
        }

        $fields = preg_split('/\s+/', $cron);

        if (\count($fields) !== 5) {
            return null;
        }

        [$minute, $hour, $dom, $month, $dow] = $fields;

        // * * * * *  ->  every minute
        if ($minute === '*' && $hour === '*' && $dom === '*' && $month === '*' && $dow === '*') {
            return ngettext('Every minute.', 'Every %d minutes.', 1);
        }

        // */N * * * *  ->  every N minutes
        if (preg_match('/^\*\/(\d+)$/', $minute, $m) && $hour === '*' && $dom === '*' && $month === '*' && $dow === '*') {
            $n = (int) $m[1];

            return sprintf(ngettext('Every minute.', 'Every %d minutes.', $n), $n);
        }

        // 0 */N * * *  ->  every N hours
        if ($minute === '0' && preg_match('/^\*\/(\d+)$/', $hour, $m) && $dom === '*' && $month === '*' && $dow === '*') {
            $n = (int) $m[1];

            return sprintf(ngettext('Every hour.', 'Every %d hours.', $n), $n);
        }

        // M H */N * *  ->  every N days at HH:MM
        if (ctype_digit($minute) && ctype_digit($hour) && preg_match('/^\*\/(\d+)$/', $dom, $m) && $month === '*' && $dow === '*') {
            $n = (int) $m[1];

            return sprintf(ngettext('Every day at %2$02d:%3$02d.', 'Every %1$d days at %2$02d:%3$02d.', $n), $n, (int) $hour, (int) $minute);
        }

        // M H * * *  ->  every day at HH:MM
        if (ctype_digit($minute) && ctype_digit($hour) && $dom === '*' && $month === '*' && $dow === '*') {
            return sprintf(_('Every day at %02d:%02d.'), (int) $hour, (int) $minute);
        }

        // M H1,H2,...,Hn * * *  ->  at minute M past hour H1, H2, ..., and Hn
        if (ctype_digit($minute) && preg_match('/^\d+(?:,\d+)+$/', $hour) && $dom === '*' && $month === '*' && $dow === '*') {
            $hours = array_map('intval', explode(',', $hour));

            return sprintf(_('At minute %d past hour %s.'), (int) $minute, self::joinList($hours));
        }

        // M * * * *  ->  every hour at minute M
        if (ctype_digit($minute) && $hour === '*' && $dom === '*' && $month === '*' && $dow === '*') {
            return sprintf(_('Every hour at minute %d.'), (int) $minute);
        }

        return sprintf(_('Ugly cron %s'), $cron);
    }

    /**
     * Joins a list of integers into a human-readable, comma-separated
     * enumeration with "and" before the last item (e.g. "5, 10, 16, and 21").
     *
     * @param int[] $items
     */
    private static function joinList(array $items): string
    {
        $items = array_map('strval', $items);

        if (\count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        if ($items === []) {
            return $last;
        }

        return sprintf(_('%s, and %s'), implode(', ', $items), $last);
    }
}
