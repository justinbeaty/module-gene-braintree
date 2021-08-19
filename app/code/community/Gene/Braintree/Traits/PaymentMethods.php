<?php

/**
 * Trait Gene_Braintree_Traits_PaymentMethods
 */
trait Gene_Braintree_Traits_PaymentMethods
{
    /**
     * @return mixed
     */
    public function getEnvironment()
    {
        return Mage::getStoreConfig('payment/gene_braintree/environment');
    }

    /**
     * @return string
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function getStoreCurrency()
    {
        return Mage::app()->getStore()->getCurrentCurrencyCode();
    }
}