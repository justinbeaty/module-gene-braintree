<?php

/**
 * Class Gene_Braintree_Block_Braintree
 */
class Gene_Braintree_Block_Braintree extends Mage_Core_Block_Template
{
    public function getEnvironment()
    {
        return Mage::getStoreConfig('payment/gene_braintree/environment');
    }
}