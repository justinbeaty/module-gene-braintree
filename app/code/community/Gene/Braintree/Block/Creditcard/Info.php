<?php

/**
 * Class Gene_Braintree_Block_Creditcard_Info
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Creditcard_Info extends Gene_Braintree_Block_Info
{

    /**
     * Use a custom template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('gene/braintree/creditcard/info.phtml');
    }

    /**
     * Prepare information specific to current payment method
     *
     * @param null | array $transport
     *
     * @return Varien_Object
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        // Get the original transport data
        $transport = parent::_prepareSpecificInformation($transport);

        // Only display this information if it's a single invoice
        if ($this->isSingleInvoice() || ($this->getInfo()->getCcLast4() && $this->getInfo()->getCcType())) {

            // Build up the data we wish to pass through
            $data = array(
                $this->__('Card Number (Last 4)') => $this->getInfo()->getCcLast4(),
                $this->__('Credit Card Type')     => $this->getInfo()->getCcType()
            );

        } else {

            // Never leave an empty array
            $data = array();
        }

        // Check we're in the admin area
        if (Mage::app()->getStore()->isAdmin()) {

            // Include the transaction statuses
            $this->includeLiveDetails($data);

            // Only include extra information when viewing a single invoice
            if ($this->isSingleInvoice()) {

                // What additional information should we show
                $additionalInfoHeadings = array(
                    'avsErrorResponseCode'         => $this->__('AVS Error Response Code'),
                    'avsPostalCodeResponseCode'    => $this->__('AVS Postal Response Code'),
                    'avsStreetAddressResponseCode' => $this->__('AVS Street Address Response Code'),
                    'cvvResponseCode'              => $this->__('CVV Response Code'),
                    'gatewayRejectionReason'       => $this->__('Gateway Rejection Reason'),
                    'processorAuthorizationCode'   => $this->__('Processor Autorization Code'),
                    'processorResponseCode'        => $this->__('Processor Response Code'),
                    'processorResponseText'        => $this->__('Processor Response Text'),
                    'threeDSecure'                 => $this->__('3D Secure')
                );

                // Add any of the data that we've recorded into the view
                foreach ($additionalInfoHeadings as $key => $heading) {
                    if ($infoData = $this->getInfo()->getAdditionalInformation($key)) {
                        $data[$heading] = $infoData;
                    }
                }

            }

        }

        // Add the data to the class variable
        $transport->setData(array_merge($data, $transport->getData()));
        $this->_paymentSpecificInformation = $transport->getData();

        // And return it
        return $transport;
    }

}