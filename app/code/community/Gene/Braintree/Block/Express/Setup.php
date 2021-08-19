<?php

/**
 * Class Gene_Braintree_Block_Express_Button
 *
 * @author Aidan Threadgold <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Express_Setup extends Gene_Braintree_Block_Express_Abstract
{
    /**
     * Braintree token
     *
     * @var string
     */
    protected $_token = null;

    /**
     * Get braintree token
     *
     * @return string
     */
    public function getToken()
    {
        if ($this->_token === null) {
            $this->_token = Mage::getModel('gene_braintree/wrapper_braintree')->init()->generateToken();
        }

        return $this->_token;
    }

    /**
     * Shall we do a single use payment?
     *
     * @return string
     */
    public function getSingleUse()
    {
        // We prefer to do future payments, so anything else is future
        if ((Mage::getSingleton('gene_braintree/paymentmethod_paypal')->getPaymentType() ==
                Gene_Braintree_Model_Source_Paypal_Paymenttype::GENE_BRAINTREE_PAYPAL_SINGLE_PAYMENT) ||
            (!Mage::getSingleton('customer/session')->isLoggedIn())
        ) {
            return 'true';
        }

        return 'false';
    }

    /**
     * Get the current product
     *
     * @return mixed
     */
    public function getProduct()
    {
        return Mage::registry('current_product');
    }

    /**
     * Registry entry to determine if block has been instantiated yet
     *
     * @return bool
     */
    public function hasBeenSetup()
    {
        if (Mage::registry('gene_braintree_btn_loaded')) {
            return true;
        }

        return false;
    }

    /**
     * Registry entry to mark this block as instantiated
     *
     * @param string $html
     *
     * @return string
     */
    public function _afterToHtml($html)
    {
        if (!$this->hasBeenSetup()) {
            Mage::register('gene_braintree_btn_loaded', true);
        }

        return $html;
    }

    /**
     * Get payment Environment
     *
     * @return string
     */
    public function getEnv()
    {
        return Mage::getStoreConfig('payment/gene_braintree/environment');
    }

    /**
     * Get payment Environment
     *
     * @return string
     */
    public function getProductTotals()
    {
        $cart = Mage::getModel('checkout/cart')->getQuote();
        $cartTotal = 0;
        foreach ($cart->getAllItems() as $item) {
            $cartTotal += ($item->getProduct()->getPrice() * $item->getQty() );
        }
        return $cartTotal;
    }
}
