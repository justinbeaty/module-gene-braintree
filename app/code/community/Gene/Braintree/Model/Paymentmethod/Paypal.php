<?php

/**
 * Class Gene_Braintree_Model_Paymentmethod_Paypal
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Paymentmethod_Paypal extends Gene_Braintree_Model_Paymentmethod_Abstract
{
    /**
     * Setup block types
     *
     * @var string
     */
    protected $_formBlockType = 'gene_braintree/paypal';
    protected $_infoBlockType = 'gene_braintree/paypal_info';

    /**
     * Set the code
     *
     * @var string
     */
    protected $_code = 'gene_braintree_paypal';

    /**
     * Payment Method features
     *
     * @var bool
     */
    protected $_isGateway = false;
    protected $_canOrder = false;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true; 
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = true;
    protected $_isInitializeNeeded = false;
    protected $_canFetchTransactionInfo = false;
    protected $_canReviewPayment = true;
    protected $_canCreateBillingAgreement = false;
    protected $_canManageRecurringProfiles = false;

    /**
     * Verify that the module has been setup
     *
     * @param null $quote
     *
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        // Check Magento's internal methods allow us to run
        if (parent::isAvailable($quote)) {
            // Validate the configuration is okay
            if ($this->_getWrapper()->validateCredentialsOnce()) {
                // This method is only active in the admin if the vault is enabled
                if (Mage::app()->getStore()->isAdmin() && $this->isVaultEnabled()) {
                    return true;
                } elseif (Mage::app()->getStore()->isAdmin()) {
                    return false;
                }

                return true;
            }
        } else {
            // Otherwise it's a no
            return false;
        }
    }

    /**
     * Place Braintree specific data into the additional information of the payment instance object
     *
     * @param   mixed $data
     *
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();
        $info->setAdditionalInformation('paypal_payment_method_token', $data->getData('paypal_payment_method_token'))
            ->setAdditionalInformation('payment_method_nonce', $data->getData('payment_method_nonce'))
            ->setAdditionalInformation('save_paypal', $data->getData('save_paypal'))
            ->setAdditionalInformation('device_data', $data->getData('device_data'));

        return $this;
    }

    /**
     * Return the PayPal payment type
     *
     * @return mixed
     */
    public function getPaymentType()
    {
        $object = new Varien_Object();
        $object->setType($this->_getConfig('payment_type'));

        // Specific event for this method
        Mage::dispatchEvent('gene_paypal_get_payment_type', array('object' => $object));

        return $object->getType();
    }

    /**
     * Is the vault enabled?
     *
     * @return bool
     */
    public function isVaultEnabled()
    {
        $object = new Varien_Object();
        $object->setResponse(($this->getPaymentType() == Gene_Braintree_Model_Source_Paypal_Paymenttype::GENE_BRAINTREE_PAYPAL_FUTURE_PAYMENTS && $this->_getConfig('use_vault')));

        // Specific event for this method
        Mage::dispatchEvent('gene_braintree_paypal_is_vault_enabled', array('object' => $object));

        // General event if we want to enforce saving of all payment methods
        Mage::dispatchEvent('gene_braintree_is_vault_enabled', array('object' => $object));

        return $object->getResponse();
    }

    /**
     * Should we save this method in the database?
     *
     * @param \Varien_Object $payment
     * @param                $skipMultishipping
     *
     * @return mixed
     */
    public function shouldSaveMethod($payment, $skipMultishipping = false)
    {
        if ($skipMultishipping === false) {
            // We must always save the method for multi shipping requests
            if ($payment->getMultiShipping() && !$this->_getOriginalToken()) {
                return true;
            } elseif ($this->_getOriginalToken()) {
                // If we have an original token, there is no need to save the same payment method again
                return false;
            }
        }

        // Retrieve whether or not we should save the card from the info instance
        $savePaypal = $this->getInfoInstance()->getAdditionalInformation('save_paypal');

        $object = new Varien_Object();
        $object->setResponse(($this->isVaultEnabled() && $savePaypal == 1));

        // Specific event for this method
        Mage::dispatchEvent('gene_braintree_paypal_should_save_method', array(
            'object' => $object,
            'payment' => $payment
        ));

        // General event if we want to enforce saving of all payment methods
        Mage::dispatchEvent('gene_braintree_save_method', array('object' => $object, 'payment' => $payment));

        return $object->getResponse();
    }

    /**
     * Return the payment method token from the info instance
     *
     * @return null|string
     */
    public function getPaymentMethodToken()
    {
        $pToken = $this->getInfoInstance()->getAdditionalInformation('paypal_payment_method_token');
        if (!empty($pToken)) {
            return $pToken;
        }

        return $this->getInfoInstance()->getAdditionalInformation('token');
    }

    /**
     * Return the payment method nonce from the info instance
     *
     * @return null|string
     */
    public function getPaymentMethodNonce()
    {
        return $this->getInfoInstance()->getAdditionalInformation('payment_method_nonce');
    }

    /**
     * Psuedo _authorize function so we can pass in extra data
     *
     * @param \Varien_Object $payment
     * @param                $amount
     * @param bool|false     $shouldCapture
     * @param bool|false     $token
     *
     * @return $this
     * @throws \Mage_Core_Exception
     */
    protected function _authorize(Varien_Object $payment, $amount, $shouldCapture = false, $token = false)
    {
        // Confirm that we have a nonce from Braintree
        // We cannot utilise the validate() function as these checks need to happen at the capture point
        if (!$this->getPaymentMethodNonce()) {
            if (!$this->getPaymentMethodToken()) {
                Mage::throwException(
                    $this->_getHelper()->__('There has been an issue processing your PayPal payment, please try again.')
                );
            }
        } elseif (!$this->getPaymentMethodNonce()) {
            Mage::throwException(
                $this->_getHelper()->__('There has been an issue processing your PayPal payment, please try again.')
            );
        }

        // Init the environment
        $this->_getWrapper()->init();

        // Retrieve the amount we should capture
        $amount = $this->_getWrapper()->getCaptureAmount($payment->getOrder(), $amount);

        // Attempt to create the sale
        try {
            // Build up the sale array
            $saleArray = $this->_getWrapper()->buildSale(
                $amount,
                $this->_buildPaymentRequest($token),
                $payment->getOrder(),
                $shouldCapture,
                $this->getInfoInstance()->getAdditionalInformation('device_data'),
                $this->shouldSaveMethod($payment)
            );

            // Detect a custom payee email from the configuration
            if ($email = $this->hasCustomPayeeEmail()) {
                $saleArray['options']['payeeEmail'] = $email;
            }

            // Attempt to make the sale, firstly dispatching an event
            $result = $this->_getWrapper()->makeSale(
                $this->_dispatchSaleArrayEvent('gene_braintree_paypal_sale_array', $saleArray, $payment)
            );

        } catch (Exception $e) {
            // If we're in developer mode return the message error
            if (Mage::getIsDeveloperMode()) {
                return $this->_processFailedResult($e->getMessage());
            }

            // Dispatch an event for when a payment fails
            Mage::dispatchEvent('gene_braintree_paypal_failed_exception', array('payment' => $payment, 'exception' => $e));

            return $this->_processFailedResult($this->_getHelper()->__('We were unable to complete your purchase through PayPal, please try again or an alternative payment method.'), $e);
        }

        // Log the result
        Gene_Braintree_Model_Debug::log(array('result' => $result));

        // If the sale has failed
        if ($result->success != true) {
            // Dispatch an event for when a payment fails
            Mage::dispatchEvent('gene_braintree_paypal_failed', array('payment' => $payment, 'result' => $result));

            return $this->_processFailedResult($this->_getHelper()->__('%s. Please try again or attempt refreshing the page.', rtrim($result->message, '.')));
        }

        $this->_processSuccessResult($payment, $result, $amount);

        return $this;
    }

    /**
     * Capture the payment on the checkout page
     *
     * @param Varien_Object $payment
     * @param float         $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    protected function _captureAuthorized(Varien_Object $payment, $amount)
    {
        // Has the payment already been authorized?
        if ($payment->getCcTransId()) {
            // Convert the capture amount to the correct currency
            $captureAmount = $this->_getWrapper()->getCaptureAmount($payment->getOrder(), $amount);

            // Check to see if the transaction has already been captured
            $lastTransactionId = $payment->getLastTransId();
            if ($lastTransactionId) {
                try {
                    $this->_getWrapper()->init($payment->getOrder()->getStoreId());
                    $transaction = Braintree\Transaction::find($lastTransactionId);

                    // Has the transaction already been settled? or submitted for the settlement?
                    // Also treat settling transaction as being process. Case #828048
                    if (isset($transaction->id) &&
                        (
                            $transaction->status == Braintree\Transaction::SUBMITTED_FOR_SETTLEMENT ||
                            $transaction->status == Braintree\Transaction::SETTLED ||
                            $transaction->status == Braintree\Transaction::SETTLING
                        )
                    ) {
                        // Do the capture amounts match?
                        if ($captureAmount == $transaction->amount) {
                            // We can just approve the invoice
                            $this->_updateKountStatus($payment, 'A');
                            $payment->setStatus(self::STATUS_APPROVED);

                            return $this;
                        }
                    }
                } catch (Exception $e) {
                    // Unable to load transaction, so process as below
                }
            }

            // Has the authorization already been settled? Partial invoicing
            if ($this->authorizationUsed($payment)
                && !isset($transaction->partialSettlementTransactionIds)
            ) {
                // Set the token as false
                $token = false;

                // Was the original payment created with a token?
                if ($additionalInfoToken = $payment->getAdditionalInformation('token')) {
                    try {
                        // Init the environment
                        $this->_getWrapper()->init($payment->getOrder()->getStoreId());

                        // Attempt to find the token
                        Braintree\PaymentMethod::find($additionalInfoToken);

                        // Set the token if a success
                        $token = $additionalInfoToken;

                    } catch (Exception $e) {
                        $token = false;
                    }

                }

                // If we managed to find a token use that for the capture
                if ($token) {
                    $result = $this->_getWrapper()->init(
                        $payment->getOrder()->getStoreId()
                    )->submitForPartialSettlement($payment->getCcTransId(), $captureAmount);
                } else {
                    // Attempt to clone the transaction
                    $result = $this->_getWrapper()->init(
                        $payment->getOrder()->getStoreId()
                    )->cloneTransaction($lastTransactionId, $captureAmount);
                }

            } else {
                // Ensure first payment isn't intended to allow for future payments (partial invoicing)
                if ($captureAmount == $payment->getAmountAuthorized()) {
                    $result = $this->_getWrapper()->init(
                        $payment->getOrder()->getStoreId()
                    )->submitForSettlement($payment->getCcTransId(), $captureAmount);
                } else {
                    $result = $this->_getWrapper()->init(
                        $payment->getOrder()->getStoreId()
                    )->submitForPartialSettlement($payment->getCcTransId(), $captureAmount);
                }

                // Log the result
                Gene_Braintree_Model_Debug::log(array('capture:submitForSettlement' => $result));
            }

            if ($result->success) {
                $this->_updateKountStatus($payment, 'A');
                $this->_processSuccessResult($payment, $result, $amount);
            } elseif ($result->errors->deepSize() > 0) {
                // Clean up
                Gene_Braintree_Model_Wrapper_Braintree::cleanUp();

                Mage::throwException($this->_getWrapper()->parseErrors($result->errors->deepAll()));
            } else {
                // Clean up
                Gene_Braintree_Model_Wrapper_Braintree::cleanUp();

                Mage::throwException(
                    $result->transaction->processorSettlementResponseCode.':
                    '.$result->transaction->processorSettlementResponseText
                );
            }

        } else {
            // Otherwise we need to do an auth & capture at once
            $this->_authorize($payment, $amount, true);
        }

        return $this;
    }

    /**
     * Does the current store have a custom payee email attached?
     *
     * @return bool|mixed
     */
    protected function hasCustomPayeeEmail()
    {
        if ($this->_getConfig('payee_email_active') && ($email = $this->_getConfig('payee_email'))) {
            return $email;
        }

        return false;
    }

    /**
     * Build up the payment request
     *
     * @param $token
     *
     * @return array
     */
    protected function _buildPaymentRequest($token)
    {
        // Build our payment array with either our token, or nonce
        $paymentArray = array();

        // If we have an original token use that for the subsequent requests
        if ($originalToken = $this->_getOriginalToken()) {
            $paymentArray['paymentMethodToken'] = $originalToken;

            return $paymentArray;
        }

        if ($this->getPaymentMethodToken() && $this->getPaymentMethodToken() != 'other') {
            $paymentArray['paymentMethodToken'] = $this->getPaymentMethodToken();
        } else {
            $paymentArray['paymentMethodNonce'] = $this->getPaymentMethodNonce();
        }

        // If a token is present in the request use that
        if ($token) {
            // Remove this unneeded data
            unset($paymentArray['paymentMethodNonce']);

            // Send the token as the payment array
            $paymentArray['paymentMethodToken'] = $token;
        }

        return $paymentArray;
    }

    /**
     * Authorize the requested amount
     *
     * @param \Varien_Object $payment
     * @param float          $amount
     *
     * @return \Gene_Braintree_Model_Paymentmethod_Paypal
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        return $this->_authorize($payment, $amount, false);
    }

    /**
     * Process capturing of a payment
     *
     * @param \Varien_Object $payment
     * @param float          $amount
     *
     * @return \Mage_Payment_Model_Abstract
     */
    public function capture(Varien_Object $payment, $amount)
    {
        return $this->_captureAuthorized($payment, $amount);
    }

    /**
     * Process a successful result from the sale request
     *
     * @param Varien_Object               $payment
     * @param Braintree\Result\Successful $result
     * @param                             $amount
     *
     * @return Varien_Object
     */
    protected function _processSuccessResult(Varien_Object $payment, $result, $amount)
    {
        // Pass an event if the payment was a success
        Mage::dispatchEvent('gene_braintree_paypal_success', array(
            'payment' => $payment,
            'result' => $result,
            'amount' => $amount
        ));

        // Set some basic things
        $payment->setStatus(self::STATUS_APPROVED)
            ->setIsTransactionClosed(0)
            ->setAmount($amount)
            ->setShouldCloseParentTransaction(false);

        if ($payment->getLastTransId()) {
            $payment->setCcTransId($payment->getLastTransId())
                ->setTransactionId($payment->getLastTransId());
        } else {
            $payment->setCcTransId($result->transaction->id)
                ->setLastTransId($result->transaction->id)
                ->setTransactionId($result->transaction->id);
        }

        $updatedLastTransIds = $this->_getUpdatedTransactionId($payment, $result);

        // Set the additional information about the customers PayPal account
        $payment->setAdditionalInformation(
            array(
                'paypal_email'     => $result->transaction->paypal['payerEmail'],
                'payment_id'       => $result->transaction->paypal['paymentId'],
                'authorization_id' => $result->transaction->paypal['authorizationId'],
                'last_invoice_trans_id' => $updatedLastTransIds,
            )
        );

        // Handle any fraud response from Braintree
        $this->handleFraud($result, $payment);

        // Store the PayPal token if we have one
        if (isset($result->transaction->paypal['token']) && !empty($result->transaction->paypal['token'])) {
            $payment->setAdditionalInformation('token', $result->transaction->paypal['token']);

            // If the transaction is part of a multi shipping transaction store the token for the next order
            if ($payment->getMultiShipping() && !$this->_getOriginalToken()) {
                $this->_setOriginalToken($result->transaction->paypal['token']);

                // If we shouldn't have this method saved, add it into the session to be removed once the request is
                // complete
                if (!$this->shouldSaveMethod($payment, true)) {
                    Mage::getSingleton('checkout/session')->setTemporaryPaymentToken(
                        $result->transaction->paypal['token']
                    );
                }
            }
        }

        return $payment;
    }

    /**
     * Get transaction id prefixed with incremented value
     *
     * @param Varien_Object $payment
     * @param $result
     * @return null|string
     */
    protected function _getUpdatedTransactionId(
        Varien_Object $payment,
        $result
    ) {
        $updatedLastTransIds = null;
        $additionalInformation = $payment->getAdditionalInformation();

        if (isset($additionalInformation['last_invoice_trans_id'])) {
            $allLastInvoiceTransIds = explode(",", $additionalInformation['last_invoice_trans_id']);
            $latestInvoiceTransIdKey = max(array_keys($allLastInvoiceTransIds));
            $latestInvoiceTransId = $allLastInvoiceTransIds[$latestInvoiceTransIdKey];
            $allLastInvoiceTransIds[] = ($latestInvoiceTransId + 1) . '-' .  $result->transaction->id;
            $updatedLastTransIds = implode(",", $allLastInvoiceTransIds);
            return $updatedLastTransIds;
        }

        $updatedLastTransIds++;
        $updatedLastTransIds = $updatedLastTransIds . '-' . $result->transaction->id;

        return $updatedLastTransIds;
    }

}
