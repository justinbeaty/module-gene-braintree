<?php

/**
 * Class Gene_Braintree_Model_Source_Cctype
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Source_Cctype extends Mage_Payment_Model_Source_Cctype
{
    /**
     * Allowed credit card types
     * This list includes a separate entry for Maestro
     *
     * @return array
     */
    public function getAllowedTypes()
    {
        return array(
            'VI',
            'MC',
            'AE',
            'DI',
            'JCB',
            'OT',
            'ME'
        );
    }
}
