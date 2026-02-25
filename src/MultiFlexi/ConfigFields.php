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
 * Description of ConfigFields.
 *
 * @author Vitex <info@vitexsoftware.cz>
 */
class ConfigFields extends \Ease\Molecule implements \Countable, \Iterator
{
    /**
     * @var array<string, ConfigField>
     */
    private array $fields = [];
    private string $name = '';

    public function __construct(string $name = '')
    {
        $this->setName($name);
    }

    /**
     * @return array<string, ConfigField> all Configuration fields
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Add Field into stack.
     *
     * When a field with the same code already exists, only the value,
     * source and logo are updated. Definition-level flags (required,
     * secret, multiline, expiring, hint, note, description, etc.) from
     * the original field are preserved.
     */
    public function addField(ConfigField $field): self
    {
        if (empty($field->getSource())) {
            $field->setSource($this->name);
        }

        $code = $field->getCode();

        if (isset($this->fields[$code])) {
            $existing = $this->fields[$code];

            $existing->setValue($field->getValue());

            if (!empty($field->getSource())) {
                $existing->setSource($field->getSource());
            }

            if ($field->getLogo() !== null) {
                $existing->setLogo($field->getLogo());
            }
        } else {
            $this->fields[$code] = $field;
            ksort($this->fields);
        }

        return $this;
    }

    /**
     * Summary of getField.
     *
     * @return null|ConfigField[]
     */
    public function getField(string $name): ?ConfigField
    {
        return \array_key_exists($name, $this->fields) ? $this->fields[$name] : null;
    }

    public function &getFieldByCode(string $code): ?ConfigField
    {
        $field = \array_key_exists($code, $this->fields) ? $this->fields[$code] : null;

        if ($field && empty($field->getSource()) && $this->getName()) {
            $field->setSource($this->getName());
        }

        return $field;
    }

    /**
     * @return array<string, string>
     */
    public function getEnvArray(): array
    {
        $fields = [];

        foreach ($this->fields as $field) {
            $fields[$field->getCode()] = $field->getValue();
        }

        return $fields;
    }

    // \Iterator interface implementation

    public function current(): mixed
    {
        return current($this->fields);
    }

    public function key(): mixed
    {
        return key($this->fields);
    }

    public function next(): void
    {
        next($this->fields);
    }

    public function rewind(): void
    {
        reset($this->fields);
    }

    public function valid(): bool
    {
        return key($this->fields) !== null;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        $this->setObjectName($this->name);

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Add multiple ConfigFields.
     */
    public function addFields(self $configs): self
    {
        foreach ($configs as $config) {
            $this->addField($config);
        }

        return $this;
    }

    /**
     * Use only values taken.
     */
    public function takeValues(self $configs): self
    {
        foreach ($configs as $config) {
            if ($this->getField($config->getName())) {
                $this->getField($config->getName())->setValue($config->getValue());
            }
        }

        return $this;
    }

    /**
     * Field names list.
     *
     * @return array<string>
     */
    public function getFieldNames(): array
    {
        return array_keys($this->fields);
    }

    public function arrayToValues(array $values): self
    {
        foreach ($values as $code => $value) {
            if ($this->getFieldByCode($code)) {
                $this->getFieldByCode($code)->setValue($value);
            }
        }

        return $this;
    }

    /**
     * Gives you number of ConfigFields.
     */
    public function count(): int
    {
        return \count($this->fields);
    }

    public function applyMacros(): self
    {
        foreach ($this->getFields() as $field) {
            $fieldValue = $field->getValue();

            if (null === $fieldValue) {
                $fieldValue = $field->getDefaultValue();
            }

            if ($fieldValue) {
                if ($fieldValue && preg_match('/({[A-Z_]*})/', $fieldValue)) {
                    $field->setValue(self::applyMarcros($fieldValue, $this));

                    $hydrated = $fieldValue;

                    foreach ($this->getFields() as $envKey => $envField) {
                        $value = method_exists($envField, 'getValue') ? $envField->getValue() : '';
                        $hydrated = str_replace('{'.$envKey.'}', (string) $value, $hydrated);
                    }

                    $field->setValue($hydrated);
                }
            }
        }

        return $this;
    }

    /**
     * Populate template by values from environment.
     */
    public static function applyMarcros(string $template, self $fields): string
    {
        $hydrated = $template;

        foreach ($fields->getFields() as $envKey => $envField) {
            $value = method_exists($envField, 'getValue') ? $envField->getValue() : '';
            $hydrated = str_replace('{'.$envKey.'}', (string) $value, $hydrated);
        }

        return $hydrated;
    }

    /**
     * Change All Fields source string.
     */
    public function setSources(string $sourceIdent): self
    {
        foreach (array_keys($this->getFields()) as $envKey) {
            $this->getField($envKey)->setSource($sourceIdent);
        }

        return $this;
    }
}
