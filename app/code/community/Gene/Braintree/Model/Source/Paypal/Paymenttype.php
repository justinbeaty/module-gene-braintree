<?php

/**
 * Class Gene_Braintree_Model_Source_Paypal_Paymenttype
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Source_Paypal_Paymenttype
{

    const GENE_BRAINTREE_PAYPAL_SINGLE_PAYMENT = 'single';
    const GENE_BRAINTREE_PAYPAL_FUTURE_PAYMENTS = 'future';

    /**
     * Return our options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => self::GENE_BRAINTREE_PAYPAL_SINGLE_PAYMENT,
                'label' => Mage::helper('gene_braintree')->__('Checkout'),
            ),
            array(
                'value' => self::GENE_BRAINTREE_PAYPAL_FUTURE_PAYMENTS,
                'label' => Mage::helper('gene_braintree')->__('Vault')
            )
        );
    }

}