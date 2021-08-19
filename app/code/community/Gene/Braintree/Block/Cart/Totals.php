<?php

/**
 * Class Gene_Braintree_Block_Cart_Totals
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Cart_Totals extends Mage_Checkout_Block_Cart_Totals
{

    /**
     * Check if we have display grand total in base currency
     *
     * @return bool
     */
    public function needDisplayBaseGrandtotal()
    {
        // If we have a mapped currency code never display base grand total
        if (Mage::getSingleton('gene_braintree/wrapper_braintree')->hasMappedCurrencyCode()) {
            return false;
        }

        return parent::needDisplayBaseGrandtotal();
    }
}