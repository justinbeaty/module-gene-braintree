<?php

/**
 * Class Gene_Braintree_Model_System_Config_Source_Payment_Threedsecurecountries
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_System_Config_Source_Payment_Threedsecurecountries
{
    const ALL_COUNTRIES = 0;
    const SPECIFIC_COUNTRIES = 1;

    /**
     * Return options for 3D secure specific countries option
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => self::ALL_COUNTRIES, 'label' => Mage::helper('adminhtml')->__('All Countries')),
            array('value' => self::SPECIFIC_COUNTRIES, 'label' => Mage::helper('adminhtml')->__('Specific Countries')),
        );
    }
}
