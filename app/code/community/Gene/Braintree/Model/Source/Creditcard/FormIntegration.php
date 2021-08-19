<?php

/**
 * Class Gene_Braintree_Model_Source_Creditcard_FormIntegration
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Source_Creditcard_FormIntegration
{
    // kept for 1.0 to 2.0 migration
    const INTEGRATION_ACTION_XML_PATH = 'payment/gene_braintree_creditcard/form_integration';

    /**
     * Default presents security risks for exploited Magento sites. Hosted Fields ensures security regardless of the
     * stores own security.
     *
     * @deprecated deprecated since version 2.0.0, use hosted fields
     */
    const INTEGRATION_DEFAULT = 'default';

    /**
     * https://www.braintreepayments.com/en-gb/products-and-features/custom-ui/hosted-fields
     */
    const INTEGRATION_HOSTED = 'hosted';

    /**
     * Possible integrations for the credit card form
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => self::INTEGRATION_HOSTED,
                'label' => Mage::helper('gene_braintree')->__('Hosted Fields')
            )
        );
    }
}
