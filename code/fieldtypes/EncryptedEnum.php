<?php

require_once 'EncryptedFieldTrait.php'; //Traits are not auto-loaded by SilverStripe

/**
 * Class EncryptedEnum
 * @package EncryptAtRest\Fieldtypes
 *
 * This class wraps around a Enum, storing the value in the database as an encrypted string in a varchar field, but
 * returning it to SilverStripe as a decrypted Enum object.
 */
class EncryptedEnum extends Enum
{
    use EncryptedFieldTrait;
    
    public $is_encrypted = true;
    /**
     * @var AtRestCryptoService
     */
    protected $service;

    public function __construct($name)
    {
        parent::__construct($name);
        $this->service = Injector::inst()->get('AtRestCryptoService');
    }

    public function setValue($value, $record = array())
    {
    	   $this->setEncryptionKeyFromRecord($record);
        if (array_key_exists($this->name, $record) && $value === null) {
            $this->value = $record[$this->name];
        } else {
            $this->value = $value;
        }
    }

    public function getDecryptedValue($value)
    {
        // Test if we're actually an encrypted value;
        if (ctype_xdigit($value) && strlen($value) > 130) {
            try {
                return $this->service->decrypt($value, $this->getEncryptionKey());
            } catch (Exception $e) {
                // We were unable to decrypt. Possibly a false positive, but return the unencrypted value
                return $value;
            }
        }
        return $value;
    }

    public function getValue()
    {
        return $this->getDecryptedValue($this->value);
    }

    public function requireField()
    {
        $values = array(
            'type'  => 'text',
            'parts' => array(
                'datatype'   => 'text',
//                'precision'  => $this->service->calculateRequiredFieldSize(strlen('Y-m-d H:i:s')),
                'null'       => 'not null',
                'default'    => $this->defaultVal,
                'arrayValue' => $this->arrayValue
            )
        );

        DB::require_field($this->tableName, $this->name, $values);
    }

    public function prepValueForDB($value)
    {
        $value = parent::prepValueForDB($value);
        $ciphertext = $this->service->encrypt($value, $this->getEncryptionKey());
        $this->value = $ciphertext;
        return $ciphertext;
    }

    /**
     * Returns the values of this enum as an array, suitable for insertion into
     * a {@link DropdownField}
     *
     * @param boolean
     *
     * @return array
     */
    public function enumValues($hasEmpty = true) {
        $this->enum = array();
        return ($hasEmpty)
            ? array_merge(array('' => ''), ArrayLib::valuekey($this->enum))
            : ArrayLib::valuekey($this->enum);
    }
}
