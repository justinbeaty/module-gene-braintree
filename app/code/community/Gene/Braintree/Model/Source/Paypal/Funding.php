<?php

/**
 * Class Gene_Braintree_Model_Source_Paypal_Funding
 * @author Aidan Threadgold <aidan@gene.co.uk>
 */
class Gene_Braintree_Model_Source_Paypal_Funding
{

    /**
     * Return the array of options
     * @return array
     */
    public function getArray()
    {
        return array(
            'credit' => Mage::helper('gene_braintree')->__('PayPal Credit'),
            'card' => Mage::helper('gene_braintree')->__('PayPal Guest Checkout Credit Card Icons'),
            'elv' => Mage::helper('gene_braintree')->__('Elektronisches Lastschriftverfahren – German ELV')
        );
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $response = array();
        foreach($this->getArray() as $key => $value) {
            $response[] = array(
                'value' => $key,
                'label' => $value
            );
        }
        return $response;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getArray();
    }

}
