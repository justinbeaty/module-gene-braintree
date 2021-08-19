<?php

/**
 * Class Gene_Braintree_CheckoutController
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_CheckoutController extends Mage_Core_Controller_Front_Action
{
    /**
     * Return a client token to the browser
     *
     * @return \Gene_Braintree_CheckoutController
     */
    public function clientTokenAction()
    {
        try {
            if (Mage::app()->getRequest()->getParam('store')) {
                $storeId = (int) Mage::app()->getRequest()->getParam('store');
            } else {
                $storeId = Mage::app()->getStore()->getStoreId();
            }

            return $this->_returnJson(array(
                'success' => true,
                'client_token' => Mage::getSingleton('gene_braintree/wrapper_braintree')->init($storeId)->generateToken()
            ));
        } catch (Exception $e) {
            return $this->_returnJson(array(
                'success' => false,
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * The front-end is requesting the grand total of the quote
     *
     * @return bool
     */
    public function quoteTotalAction()
    {
        // Grab the quote
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getSingleton('checkout/type_onepage')->getQuote();

        // Retrieve the billing information from the quote
        $billingName = $quote->getBillingAddress()->getName();
        $billingPostcode = $quote->getBillingAddress()->getPostcode();
        $billingCountryId = $quote->getBillingAddress()->getCountryId();

        // Has the request supplied the billing address ID?
        if ($addressId = $this->getRequest()->getParam('addressId') &&
            Mage::getSingleton('customer/session')->isLoggedIn()
        ) {
            // Retrieve the address
            $billingAddress = $quote->getCustomer()->getAddressById($addressId);

            // If the address loads override the values
            if ($billingAddress && $billingAddress->getId()) {
                $billingName = $billingAddress->getName();
                $billingPostcode = $billingAddress->getPostcode();
                $billingCountryId = $billingAddress->getCountryId();
            }

        }

        // Currency mapping
        if (Mage::getSingleton('gene_braintree/wrapper_braintree')->hasMappedCurrencyCode()) {
            $grandTotal = $quote->getGrandTotal();
            $currencyCode = $quote->getQuoteCurrencyCode();
        } else {
            $grandTotal = $quote->getBaseGrandTotal();
            $currencyCode = $quote->getBaseCurrencyCode();
        }

        // Build up our JSON response
        $jsonResponse = array(
            'billingName'      => $billingName,
            'billingPostcode'  => $billingPostcode,
            'billingCountryId' => $billingCountryId,
            'grandTotal'       => Mage::helper('gene_braintree')->formatPrice($grandTotal),
            'currencyCode'     => $currencyCode,
            'threeDSecure'     => Mage::getSingleton('gene_braintree/paymentmethod_creditcard')->is3DEnabled()
        );

        return $this->_returnJson($jsonResponse);
    }

    /**
     * Tokenize the card tokens via Ajax
     *
     * @return bool
     */
    public function tokenizeCardAction()
    {
        // Are tokens set in the request
        if ($tokens = $this->getRequest()->getParam('tokens')) {
            // Build up our response
            $jsonResponse = array(
                'success' => true,
                'tokens'  => array()
            );

            // Json decode the tokens
            $tokens = Mage::helper('core')->jsonDecode($tokens);
            if (is_array($tokens)) {
                // Loop through each token and tokenize it again
                foreach ($tokens as $token) {
                    $jsonResponse['tokens'][$token] = Mage::getSingleton('gene_braintree/wrapper_braintree')
                        ->getThreeDSecureVaultNonce($token);
                }

                // Set the response
                return $this->_returnJson($jsonResponse);
            }
        }
    }

    /**
     * Vault the nonce with it's billing details, then convert it back into a nonce
     *
     * @return bool
     */
    public function vaultToNonceAction()
    {
        // Check we have a nonce in the request
        if ($nonce = $this->getRequest()->getParam('nonce')) {
            // Retrieve the billing address
            if (!$this->getRequest()->getParam('billing')) {
                return $this->_returnJson(array(
                    'success' => false,
                    'error'   => 'Billing address is not present'
                ));
            }

            // Pull the billing address from the multishipping experience
            if ($this->getRequest()->getParam('billing') == 'multishipping') {
                $billing = Mage::getSingleton('checkout/type_multishipping')->getQuote()->getBillingAddress();
            } elseif ($this->getRequest()->getParam('billing') == 'quote') {
                $billing = Mage::getSingleton('checkout/session')->getQuote()->getBillingAddress();
            } else {
                $billing = $this->getRequest()->getParam('billing');
            }

            // Create a new payment method in the vault
            $wrapper = Mage::getSingleton('gene_braintree/wrapper_braintree');
            $wrapper->init();

            // Retrieve and convert the billing address
            $billingAddress = $wrapper->convertBillingAddress($billing);

            $token = false;

            // Store the vaulted nonce in the session
            Mage::getSingleton('checkout/session')->setVaultedNonce($nonce);

            try {
                if ($wrapper->checkIsCustomer()) {
                    $response = $wrapper->storeInVault($nonce, $billingAddress);
                    if (isset($response->success) && $response->success == true &&
                        isset($response->paymentMethod->token)
                    ) {
                        $token = $response->paymentMethod->token;
                        Mage::getSingleton('checkout/session')->setTemporaryPaymentToken($token);
                    }
                } else {
                    $response = $wrapper->storeInGuestVault($nonce, $billingAddress);
                    if (isset($response->success) &&
                        $response->success == true &&
                        isset($response->customer->creditCards) &&
                        count($response->customer->creditCards) >= 1
                    ) {
                        // Store this customers ID in the session so we can remove the customer at the end of the
                        // checkout
                        if (isset($response->customer->id)) {
                            Mage::getSingleton('checkout/session')->setGuestBraintreeCustomerId(
                                $response->customer->id
                            );
                        }

                        $method = $response->customer->creditCards[0];
                        if (isset($method->token)) {
                            $token = $method->token;
                            Mage::getSingleton('checkout/session')->setGuestPaymentToken($token);
                        }

                    }
                }
            } catch (Exception $e) {
                return $this->_returnJson(array(
                    'success' => false,
                    'error'   => $e->getMessage()
                ));
            }

            // Was the request to store this in the vault a success?
            if ($token) {
                // Build up our response
                $response = array(
                    'success' => true,
                    'nonce'   => $wrapper->getThreeDSecureVaultNonce($token)
                );

            } else {
                // Return a different message for declined cards
                if (isset($response->transaction->status)) {
                    // Return a custom response for processor declined messages
                    if ($response->transaction->status == Braintree_Transaction::PROCESSOR_DECLINED) {
                        return $this->_returnJson(array(
                            'success' => false,
                            'error'   => Mage::helper('gene_braintree')->__(
                                'Your transaction has been declined, please try another payment method or contacting ' .
                                'your issuing bank.'
                            )
                        ));
                    }
                }

                return $this->_returnJson(array(
                    'success' => false,
                    'error'   => Mage::helper('gene_braintree')->__(
                        '%s. Please try again or attempt refreshing the page.',
                        $wrapper->parseMessage($response->message)
                    )
                ));

            }

            return $this->_returnJson($response);
        }

        return $this->_returnJson(array('success' => false));
    }

    /**
     * Return JSON to the browser
     *
     * @param $array
     *
     * @return $this
     */
    protected function _returnJson($array)
    {
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($array));
        $this->getResponse()->setHeader('Content-Type', 'application/json', true);

        return $this;
    }
}
