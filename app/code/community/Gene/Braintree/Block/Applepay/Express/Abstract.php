<?php

/**
 * Class Gene_Braintree_Block_Applepay_Express_Abstract
 *
 * @author Dave Macaulay <dave@gene.co.uk>
 * @author Paul Canning <paul.canning@gene.co.uk>
 */
class Gene_Braintree_Block_Applepay_Express_Abstract extends Mage_Core_Block_Template
{
    const GENE_BRAINTREE_APPLEPAY_ACTIVE = 'payment/gene_braintree_applepay/active';
    const GENE_BRAINTREE_APPLEPAY_EXPRESS_ACTIVE = 'payment/gene_braintree_applepay/express_active';
    const GENE_BRAINTREE_APPLEPAY_EXPRESS_PDP = 'payment/gene_braintree_applepay/express_pdp';
    const GENE_BRAINTREE_APPLEPAY_EXPRESS_CART = 'payment/gene_braintree_applepay/express_cart';

    /**
     * Retrieve the current quote
     *
     * @return \Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    /**
     * Get the current product
     *
     * @return bool|Mage_Catalog_Model_Product
     */
    public function getProduct()
    {
        return Mage::registry('current_product');
    }

    /**
     * Is the express mode enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return Mage::getStoreConfig(self::GENE_BRAINTREE_APPLEPAY_ACTIVE)
            && Mage::getStoreConfig(self::GENE_BRAINTREE_APPLEPAY_EXPRESS_ACTIVE);
    }

    /**
     * Is Express enabled on product pages?
     *
     * @return bool
     */
    public function isEnabledPdp()
    {
        return $this->isEnabled() && Mage::getStoreConfig(self::GENE_BRAINTREE_APPLEPAY_EXPRESS_PDP);
    }

    /**
     * Is express enabled in the cart?
     *
     * @return bool
     */
    public function isEnabledCart()
    {
        return $this->isEnabled() && Mage::getStoreConfig(self::GENE_BRAINTREE_APPLEPAY_EXPRESS_CART);
    }
}
