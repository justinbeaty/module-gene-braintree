<?php

/**
 * Class Braintree_Payments_Model_Source_Environment
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Source_Environment
{
    const SANDBOX = 'sandbox';
    const PRODUCTION = 'production';

    /**
     * Display both sandbox and production values
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => self::SANDBOX,
                'label' => Mage::helper('gene_braintree')->__('Sandbox'),
            ),
            array(
                'value' => self::PRODUCTION,
                'label' => Mage::helper('gene_braintree')->__('Production')
            )
        );
    }
}
