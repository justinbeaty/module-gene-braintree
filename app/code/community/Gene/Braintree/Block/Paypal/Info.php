<?php

/**
 * Class Gene_Braintree_Block_Paypal_Info
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Paypal_Info extends Gene_Braintree_Block_Info
{

    /**
     * Use a custom template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('gene/braintree/paypal/info.phtml');
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

        // Start out data array
        $data = array();

        // Build up the data we wish to pass through
        if ($this->getInfo()->getAdditionalInformation('paypal_email')) {
            $data[$this->__('PayPal Email')] = $this->getInfo()->getAdditionalInformation('paypal_email');
        }

        // Check we're in the admin area
        if (Mage::app()->getStore()->isAdmin()) {

            // Include live details for this transaction
            $transaction = $this->includeLiveDetails($data);

            // Insert these values if they're present
            if ($this->getInfo()->getAdditionalInformation('payment_id')) {
                $data[$this->__('Payment ID')] = $this->getInfo()->getAdditionalInformation('payment_id');
            }
            if ($this->getInfo()->getAdditionalInformation('authorization_id')) {
                $data[$this->__('Authorization ID')] = $this->getInfo()->getAdditionalInformation('authorization_id');
            }

            // If the additional information doens't contain certain data, than retrieve it from Braintree
            if ($transaction && $transaction instanceof Braintree\Transaction) {
                if (!isset($data[$this->__('PayPal Email')]) && isset($transaction->paypalDetails->payerEmail)) {
                    $data[$this->__('PayPal Email')] = $transaction->paypalDetails->payerEmail;
                }
                if (!isset($data[$this->__('Payment ID')]) && isset($transaction->paypalDetails->paymentId)) {
                    $data[$this->__('Payment ID')] = $transaction->paypalDetails->paymentId;
                }
                if (!isset($data[$this->__('Authorization ID')])
                    && isset($transaction->paypalDetails->authorizationId)
                ) {
                    $data[$this->__('Authorization ID')] = $transaction->paypalDetails->authorizationId;
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