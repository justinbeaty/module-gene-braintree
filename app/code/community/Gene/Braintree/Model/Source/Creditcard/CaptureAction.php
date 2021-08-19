<?php

/**
 * Class Gene_Braintree_Model_Source_Creditcard_CaptureAction
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Source_Creditcard_CaptureAction
{
    const CAPTURE_ACTION_XML_PATH = 'payment/gene_braintree_creditcard/capture_action';

    const CAPTURE_INVOICE = 'invoice';
    const CAPTURE_SHIPMENT = 'shipment';

    /**
     * Possible actions on order place
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => self::CAPTURE_INVOICE,
                'label' => Mage::helper('gene_braintree')->__('Invoice')
            ),
            array(
                'value' => self::CAPTURE_SHIPMENT,
                'label' => Mage::helper('gene_braintree')->__('Shipment')
            ),
        );
    }
}
