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

namespace MultiFlexi\CredentialProtoType;

/**
 * Description of Common.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class Common extends \MultiFlexi\CredentialProtoType implements \MultiFlexi\credentialTypeInterface
{
    public function takeData($data): int
    {
        $this->setData($data);
        $fields = $this->getFluentPDO()->from('credential_prototype_field')->where(['credential_prototype_id' => $this->getDataValue('id')])->fetchAll('keyword');
        $imported = 0;

        foreach ($fields as $code => $fieldData) {
            $field = new \MultiFlexi\ConfigField($code, $fieldData['type'], $fieldData['name'], $fieldData['description'], $fieldData['hint']);
            $field->setDefaultValue($fieldData['default_value'])->setRequired((bool) $fieldData['required']);
            $this->fieldsProvided()->addField($field);
            ++$imported;
        }

        return $imported;
    }

    // put your code here
    #[\Override]
    public function prepareConfigForm(): void
    {
    }

    #[\Override]
    public function description(): string
    {
        return _('Non specialised credential type');
    }

    #[\Override]
    public function uuid(): string
    {
        return '';
    }

    #[\Override]
    public function logo(): string
    {
        return 'CommonCredentialType.svg';
    }

    #[\Override]
    public function name(): string
    {
        return _('Common type');
    }
}
