<?php

/**
 * Class Gene_Braintree_Block_Express_Abstract
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Express_Abstract extends Mage_Core_Block_Template
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

    /**
     * Get store currency code.
     *
     * @return string
     */
    public function getStoreCurrency()
    {
        return Mage::app()->getStore()->getCurrentCurrencyCode();
    }

    /**
     * Get the store locale.
     *
     * @return string
     */
    public function getStoreLocale()
    {
        return Mage::app()->getLocale()->getLocaleCode();
    }

    /**
     * Get button styling configuration settings as an array
     * @param $scope
     * @return array
     */
    public function getStyleConfigArray($scope)
    {
        return Mage::helper('gene_braintree')->getStyleConfigArray($scope);
    }

    /**
     * Get button styling configuration settings
     * @param $scope
     * @return string
     */
    public function getStyleConfig($scope)
    {
        return Mage::helper('gene_braintree')->getStyleConfig($scope);
    }

    /**
     * Button funding options
     * @return string
     */
    public function getFunding()
    {
        $funding = Mage::getStoreConfig('payment/gene_braintree_paypal/disabled_funding');
        $funding = explode(",", $funding);
        $disallowed = $allowed = array();

        // Credit (only for USD currencies)
        // We don't explicitly disable this as it causes a JS error with the button
        if (!(in_array("credit", $funding) || $this->getStoreCurrency() != "USD")) {
            $allowed[] = "'credit'";
        }

        // Cards
        if (in_array("card", $funding)) {
            $disallowed[] = "'card'";
        }

        // German ELV
        if (in_array("elv", $funding)) {
            $disallowed[] = "'elv'";
        }

        $return = array();
        if ($disallowed) {
            $return[] = 'disallowed: [' . implode(",", $disallowed) . ']';
        } else {
            $return[] = 'disallowed: []';
        }
        if ($allowed) {
            $return[] = 'allowed: [' . implode(",", $allowed) . ']';
        } else {
            $return[] = 'allowed: []';
        }

        if ($return) {
            return implode(",", $return);
        }
        return '';
    }
}
