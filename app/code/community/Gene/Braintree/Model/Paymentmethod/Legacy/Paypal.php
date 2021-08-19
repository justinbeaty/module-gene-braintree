<?php

/**
 * Class Gene_Braintree_Model_Paymentmethod_Legacy_Paypal
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Paymentmethod_Legacy_Paypal extends Gene_Braintree_Model_Paymentmethod_Paypal
{
    /**
     * Set the code
     *
     * @var string
     */
    protected $_code = 'braintree_paypal_legacy';

    /**
     * This method is never available and only used by the RocketWeb orders
     *
     * @param null $quote
     *
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        return false;
    }
}