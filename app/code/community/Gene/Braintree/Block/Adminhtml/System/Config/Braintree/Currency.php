<?php

/**
 * Class Gene_Braintree_Block_Adminhtml_System_Config_Braintree_Currency
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Adminhtml_System_Config_Braintree_Currency
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Return the currency table HTML for the element
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->getCurrencyTableHtml($element);
    }

    /**
     * Inform the user there version will not work
     * @return string
     */
    protected function getCurrencyTableHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $currencies = array();

        /* @var $adminConfigData Mage_Adminhtml_Model_Config_Data */
        $adminConfigData = Mage::getSingleton('adminhtml/config_data');

        // Determine the available currencies for the current scope
        if ($adminConfigData->getScope() == 'default') {
            $currencies = Mage::app()->getStore(0)->getAvailableCurrencyCodes();
        } else if ($adminConfigData->getScope() == 'websites') {
            /* @var $website Mage_Core_Model_Website */
            $website = Mage::getModel('core/website')->load($adminConfigData->getWebsite(), 'code');
            if ($website->getId()) {
                /* @var $store Mage_Core_Model_Store */
                foreach ($website->getStores() as $store) {
                    $currencies = array_merge($currencies, $store->getAvailableCurrencyCodes());
                }
            }
        } else if ($adminConfigData->getScope() == 'stores') {
            $currencies = Mage::app()->getStore($adminConfigData->getStore())->getAvailableCurrencyCodes();
        }

        // Retrieve the values
        $values = $element->getValue();

        // Build our response
        $response = '<input type="hidden" id="payment_gene_braintree_multi_currency_mapping" />
        <table width="100%" cellspacing="6" cellpadding="4">
            <tr>
                <th width="35%">' . $this->__('Currency Code') . '</th>
                <th width="65%">' . $this->__('Merchant Account ID') . '</th>
            </tr>';

        // Loop through each currency and add a value
        foreach($currencies as $currency) {
            $response .= '<tr>
                <td> ' . $currency . '</td>
                <td><input class="input-text" type="text" name=" ' . $element->getName() . '[' . $currency . ']" style="width: 100%;" value="'. (isset($values->$currency) ? $values->$currency : '') . '" /></td>
            </tr>';
        }

        $response .= '</table>';

        return $response;
    }
}
