<?php

/**
 * Class Gene_Braintree_Model_System_Config_Backend_Currency
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_System_Config_Backend_Currency extends Mage_Core_Model_Config_Data
{

    /**
     * Json decode the value
     */
    protected function _afterLoad()
    {
        if (!is_array($this->getValue())) {
            $value = $this->getValue();
            $this->setValue(empty($value) ? false : Mage::helper('core')->jsonDecode($value, Zend_Json::TYPE_OBJECT));
        }
    }

    /**
     * Json encode the value to be stored in the database
     */
    protected function _beforeSave()
    {
        if (is_array($this->getValue())) {
            $this->setValue(Mage::helper('core')->jsonEncode($this->getValue()));
        }
    }

}