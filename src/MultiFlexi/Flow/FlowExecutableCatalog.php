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

namespace MultiFlexi\Flow;

/**
 * Node types the MultiFlexi eventor interpreter can execute.
 *
 * Deploy rejects (or strips) anything outside this catalog so accountants
 * cannot draw graphs that would silently fail at runtime.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
final class FlowExecutableCatalog
{
    /**
     * Interpreter capability semver shipped with this core package.
     */
    public const INTERPRETER_CAPABILITY = '1.0.0';

    /**
     * Types that start a flow run when matched.
     */
    public const TRIGGER_TYPES = [
        'multiflexi-event',
    ];

    /**
     * Types stored and executed by FlowInterpreter.
     */
    public const EXECUTABLE = [
        'multiflexi-event',
        'multiflexi-runtemplate',
        'multiflexi-map',
        'multiflexi-artifact',
        'multiflexi-company',
        'multiflexi-application',
        'multiflexi-credential',
        'switch',
        'delay',
        'link in',
        'link out',
        'catch',
    ];

    /**
     * Design-only Node-RED types ignored on Deploy (no runtime effect).
     */
    public const IGNORABLE = [
        'tab',
        'subflow',
        'comment',
        'junction',
        'group',
        'status',
        'debug',
        'complete',
    ];

    public static function isExecutable(string $type): bool
    {
        return \in_array($type, self::EXECUTABLE, true);
    }

    public static function isIgnorable(string $type): bool
    {
        return \in_array($type, self::IGNORABLE, true);
    }

    public static function isTrigger(string $type): bool
    {
        return \in_array($type, self::TRIGGER_TYPES, true);
    }

    /**
     * Validate a Deploy node list.
     *
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<string> Error messages (empty = ok)
     */
    public static function validateNodes(array $nodes): array
    {
        $errors = [];

        foreach ($nodes as $i => $node) {
            $type = (string) ($node['type'] ?? '');

            if ($type === '') {
                $errors[] = sprintf('nodes[%d]: missing type', $i);

                continue;
            }

            if (self::isIgnorable($type)) {
                continue;
            }

            if (!self::isExecutable($type)) {
                $errors[] = sprintf(
                    'nodes[%d] type "%s" is not executable by MultiFlexi eventor (capability %s)',
                    $i,
                    $type,
                    self::INTERPRETER_CAPABILITY,
                );
            }

            if (!isset($node['id']) || (string) $node['id'] === '') {
                $errors[] = sprintf('nodes[%d]: missing id', $i);
            }
        }

        return $errors;
    }
}
