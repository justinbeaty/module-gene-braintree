<?php

/**
 * Class Gene_Braintree_Model_Source_Paypal_Locale
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Source_Paypal_Locale
{

    /**
     * Return the array of options
     * @return array
     */
    public function getArray()
    {
        return array(
            'en_AU' => Mage::helper('gene_braintree')->__('Australia'),
            'de_AT' => Mage::helper('gene_braintree')->__('Austria'),
            'en_BE' => Mage::helper('gene_braintree')->__('Belgium'),
            'en_CA' => Mage::helper('gene_braintree')->__('Canada'),
            'da_DK' => Mage::helper('gene_braintree')->__('Denmark'),
            'fr_FR' => Mage::helper('gene_braintree')->__('France'),
            'de_DE' => Mage::helper('gene_braintree')->__('Germany'),
            'en_GB' => Mage::helper('gene_braintree')->__('Great Britain & Ireland'),
            'zh_HK' => Mage::helper('gene_braintree')->__('Hong Kong'),
            'it_IT' => Mage::helper('gene_braintree')->__('Italy'),
            'nl_NL' => Mage::helper('gene_braintree')->__('Netherlands'),
            'no_NO' => Mage::helper('gene_braintree')->__('Norway'),
            'pl_PL' => Mage::helper('gene_braintree')->__('Poland'),
            'es_ES' => Mage::helper('gene_braintree')->__('Spain'),
            'sv_SE' => Mage::helper('gene_braintree')->__('Sweden'),
            'en_CH' => Mage::helper('gene_braintree')->__('Switzerland'),
            'tr_TR' => Mage::helper('gene_braintree')->__('Turkey'),
            'en_US' => Mage::helper('gene_braintree')->__('United States')
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
