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
 * Description of CredentialProtoTypeField
 *
 * @author vitex
 */
class CredentialProtoTypeField extends \MultiFlexi\DBEngine
{
    use \Ease\recordkey;

    /**
     * Database table name
     * @var string
     */
    public string $myTable = 'credential_prototype_field';
    
    /**
     * Name column for display purposes
     * @var string  
     */
    public string $nameColumn = 'name';

    public function __construct($init = null)
    {
        parent::__construct($init);
    }

    /**
     * Get fields for a specific credential prototype
     *
     * @param CredentialProtoType $prototype
     * @return ConfigFields
     */
    public function getProtoTypeFields(CredentialProtoType $prototype): ConfigFields
    {
        $fields = new ConfigFields();

        foreach ($this->listingQuery()->where(['credential_prototype_id' => $prototype->getMyKey()]) as $fieldData) {
            $field = new ConfigField($fieldData['keyword'], $fieldData['type'], $fieldData['keyword'], $fieldData['description']);
            $field->setHint($fieldData['hint'])->setDefaultValue($fieldData['default_value'])->setRequired($fieldData['required']);
            $fields->addField($field);
        }

        return $fields;
    }

    /**
     * List all fields for a credential prototype
     *
     * @param int $prototypeId
     * @return array
     */
    public function listFields(int $prototypeId): array
    {
        return $this->listingQuery()->where(['credential_prototype_id' => $prototypeId])->fetchAll();
    }
}