<?php

/**
 * Class Gene_Braintree_Block_Paypal_Saved
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Paypal_Saved extends Mage_Core_Block_Template
{
    /**
     * @var array
     */
    protected $_savedDetails;

    /**
     * Set the template
     */
    protected function _construct()
    {
        parent::_construct();

        $this->setTemplate('gene/braintree/paypal/saved.phtml');
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
     * @return array
     */
    public function getSavedDetails()
    {
        if (!$this->_savedDetails) {
            $this->_savedDetails = Mage::getSingleton('gene_braintree/saved')->getSavedMethodsByType(Gene_Braintree_Model_Saved::SAVED_PAYPAL_ID);
        }
        return $this->_savedDetails;
    }

}