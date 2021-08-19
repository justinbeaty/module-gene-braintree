<?php

/**
 * Class Gene_Braintree_Model_Source_Creditcard_PaymentAction
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Source_Creditcard_PaymentAction
{
    const PAYMENT_ACTION_XML_PATH = 'payment/gene_braintree_creditcard/payment_action';

    /**
     * Possible actions on order place
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE,
                'label' => Mage::helper('gene_braintree')->__('Authorize')
            ),
            array(
                'value' => Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE,
                'label' => Mage::helper('gene_braintree')->__('Authorize & Capture')
            ),
        );
    }
}
