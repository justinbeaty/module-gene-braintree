<?php

/**
 * Class Gene_Braintree_Block_Form
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Paypal extends Mage_Payment_Block_Form
{

    /**
     * Store this so we don't load it multiple times
     */
    private $_savedDetails = false;

    /**
     * Internal constructor. Set template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('gene/braintree/paypal.phtml');
    }

    /**
     * Generate and return a token
     *
     * @return mixed
     */
    public function getClientToken()
    {
        return Mage::getModel('gene_braintree/wrapper_braintree')->init()->generateToken();
    }

    /**
     * Shall we do a single use payment?
     *
     * @return string
     */
    public function getSingleUse()
    {
        // We prefer to do future payments, so anything else is future
        if (Mage::getSingleton('gene_braintree/paymentmethod_paypal')->getPaymentType() == Gene_Braintree_Model_Source_Paypal_Paymenttype::GENE_BRAINTREE_PAYPAL_SINGLE_PAYMENT) {
            return 'true';
        }

        return 'false';
    }

    /**
     * Does this customer have saved accounts?
     *
     * @return mixed
     */
    public function hasSavedDetails()
    {
        if (Mage::getSingleton('customer/session')->isLoggedIn() || Mage::app()->getStore()->isAdmin()) {
            if ($this->getSavedDetails()) {
                return sizeof($this->getSavedDetails());
            }
        }

        return false;
    }

    /**
     * Return the saved accounts
     *
     * @return bool
     */
    public function getSavedDetails()
    {
        if (!$this->_savedDetails) {
            $this->_savedDetails = Mage::getSingleton('gene_braintree/saved')->getSavedMethodsByType(Gene_Braintree_Model_Saved::SAVED_PAYPAL_ID);
        }

        return $this->_savedDetails;
    }

    /**
     * Get the saved child HTML
     *
     * @return string
     */
    public function getSavedChildHtml()
    {
        $html = $this->getChildHtml('saved', false);
        $this->unsetChild('saved');

        return $html;
    }

    /**
     * Is the vault enabled? Meaning we can save PayPal
     *
     * @return mixed
     */
    public function canSavePayPal()
    {
        if ($this->getMethod()->isVaultEnabled()
            && (Mage::getSingleton('customer/session')->isLoggedIn()
                || Mage::getSingleton('checkout/type_onepage')->getCheckoutMethod() == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER)
        ) {
            return true;
        }

        return false;
    }

}