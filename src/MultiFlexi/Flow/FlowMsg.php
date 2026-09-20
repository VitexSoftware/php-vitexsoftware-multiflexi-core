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
 * Stable step message envelope passed between flow nodes.
 *
 * Shape is fixed so new node types extend fields rather than inventing
 * ad-hoc top-level keys. Mirrors the Node-RED msg idea without requiring NR.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
final class FlowMsg
{
    /**
     * @param array<string, mixed> $payload  Primary data (change record, job result, …)
     * @param array<string, mixed> $env      Environment overrides for the next RunTemplate
     * @param array<string, mixed> $produced CollectProducedData() from the last job
     * @param array<string, mixed> $meta     company_id, exitcode, trigger, correlation, …
     */
    public function __construct(
        public array $payload = [],
        public array $env = [],
        public array $produced = [],
        public array $meta = [],
    ) {
    }

    /**
     * @return array{payload: array, env: array, produced: array, meta: array}
     */
    public function toArray(): array
    {
        return [
            'payload' => $this->payload,
            'env' => $this->env,
            'produced' => $this->produced,
            'meta' => $this->meta,
        ];
    }

    public function toJson(): string
    {
        return (string) json_encode($this->toArray(), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    public static function fromJson(?string $json): self
    {
        if ($json === null || $json === '') {
            return new self();
        }

        $decoded = json_decode($json, true);

        if (!\is_array($decoded)) {
            return new self();
        }

        return self::fromArray($decoded);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            \is_array($data['payload'] ?? null) ? $data['payload'] : [],
            \is_array($data['env'] ?? null) ? $data['env'] : [],
            \is_array($data['produced'] ?? null) ? $data['produced'] : [],
            \is_array($data['meta'] ?? null) ? $data['meta'] : [],
        );
    }

    /**
     * Return a copy with merged env overrides (later wins).
     *
     * @param array<string, mixed> $env
     */
    public function withEnv(array $env): self
    {
        $copy = clone $this;
        $copy->env = array_merge($this->env, $env);

        return $copy;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function withMeta(array $meta): self
    {
        $copy = clone $this;
        $copy->meta = array_merge($this->meta, $meta);

        return $copy;
    }
}
