<?php

/**
 * Class Gene_Braintree_Block_Creditcard_Threedsecure
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Creditcard_Threedsecure extends Mage_Core_Block_Template
{
    /**
     * Only render if the payment method is active and 3D secure is enabled
     *
     * @return string
     */
    protected function _toHtml()
    {
        // Check the payment method is active
        if (Mage::getSingleton('gene_braintree/paymentmethod_creditcard')->isAvailable()) {

            // Due to the introduction of the 3Ds threshold we need this block to always be present
            return parent::_toHtml();
        }

        return '';
    }
}