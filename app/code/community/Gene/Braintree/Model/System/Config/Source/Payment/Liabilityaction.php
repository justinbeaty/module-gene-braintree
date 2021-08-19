<?php

/**
 * Class Gene_Braintree_Model_System_Config_Source_Payment_Liabilityaction
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_System_Config_Source_Payment_Liabilityaction
{
    const BLOCK = 1;
    const FRAUD = 2;
    const PROCESS = 3;

    /**
     * Return options for 3D secure specific countries option
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => self::BLOCK, 'label' => Mage::helper('adminhtml')->__('Request Alternative Payment Method')),
            array('value' => self::FRAUD, 'label' => Mage::helper('adminhtml')->__('Accept & Mark as Fraud')),
            array('value' => self::PROCESS, 'label' => Mage::helper('adminhtml')->__('Accept')),
        );
    }
}
