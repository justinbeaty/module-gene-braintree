<?php

/**
 * Class Gene_Braintree_Model_Express_Assets
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Express_Assets extends Mage_Core_Model_Abstract
{
    /**
     * Is the express mode enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return Mage::getStoreConfig('payment/gene_braintree_paypal/active')
            && Mage::getStoreConfig('payment/gene_braintree_paypal/express_active');
    }

    /**
     * Is express enabled on the product page?
     *
     * @return bool
     */
    public function isEnabledPdp()
    {
        return $this->isEnabled() && Mage::getStoreConfig('payment/gene_braintree_paypal/express_pdp');
    }

    /**
     * Is express enabled in the cart?
     *
     * @return bool
     */
    public function isEnabledCart()
    {
        return $this->isEnabled() && Mage::getStoreConfig('payment/gene_braintree_paypal/express_cart');
    }
}
