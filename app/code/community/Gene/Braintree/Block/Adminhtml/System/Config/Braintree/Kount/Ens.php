<?php

/**
 * Class Gene_Braintree_Model_System_Config_Backend_Kount_Ens
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Adminhtml_System_Config_Braintree_Kount_Ens extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Return the ENS URL
     *
     * @param \Varien_Data_Form_Element_Abstract $element
     *
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $url = $this->getUrl('braintree/kount_ens/', array('_secure' => true));
        return substr($url, 0, (strpos($url, "kount_ens/") + 10));
    }
}