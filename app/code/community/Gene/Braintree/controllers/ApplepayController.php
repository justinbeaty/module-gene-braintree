<?php
/**
 * Class Gene_Braintree_ApplepayController
 *
 * @author Dave Macaulay <dave@gene.co.uk>
 */
class Gene_Braintree_ApplepayController extends Mage_Core_Controller_Front_Action
{
    /**
     * Setup our Express model with required data
     *
     * @return Gene_Braintree_Model_Applepay_Express
     */
    protected function _setupExpress()
    {
        // Retrieve the express model
        /* @var Gene_Braintree_Model_Applepay_Express $express */
        $express = Mage::getModel('gene_braintree/applepay_express');

        // If the customer has opted for buying a product we need to build a fresh quote
        if ($productId = $this->getRequest()->getParam('productId')) {
            $express->setProductId($productId);
            try {
                $express->initProductQuote($this->getRequest()->getParam('productForm'));
            } catch (Exception $e) {
                return $this->errorAction(Mage::helper('gene_braintree')->__('We\'re unable to load that product.'));
            }
        }

        return $express;
    }

    /**
     * Return a client token to the browser
     *
     * @return Gene_Braintree_ApplepayController
     */
    public function clientTokenAction()
    {
        try {
            return $this->_returnJson(array(
                'success' => true,
                'client_token' => Mage::getSingleton('gene_braintree/wrapper_braintree')->init()->generateToken()
            ));
        } catch (Exception $e) {
            return $this->_returnJson(array(
                'success' => false,
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Fetch the shipping methods available to a certain address
     */
    public function fetchShippingMethodsAction()
    {
        $express = $this->_setupExpress();

        // Retrieve the countryId from the request
        $countryId = $this->getRequest()->getParam('countryCode', false);
        if (!$countryId && ($countryName = $this->getRequest()->getParam('country', false))) {
            $countryId = $express->getCountryIdFromName($countryName);
        }

        // Pass over the shipping destination
        $express->setCity((string) $this->getRequest()->getParam('city'));
        $express->setPostcode((string) $this->getRequest()->getParam('postalCode'));
        $express->setCountryId($countryId);

        // Retrieve the shipping rates available for this quote
        $rates = $express->getShippingRates();

        // Build up our response
        $response = array(
            'success' => true,
            'rates' => $rates
        );
        $response = $express->getAdditionalResponse($response);

        return $this->_returnJson($response);
    }

    /**
     * Submit an Apple Pay transaction
     *
     * @return bool
     */
    public function submitAction()
    {
        $express = $this->_setupExpress();

        $nonce = $this->getRequest()->getParam('nonce', false);
        $billingAddress = $this->getRequest()->getParam('billingAddress', false);

        // Pull and decode the shipping address
        $shippingAddress = $this->getRequest()->getParam('shippingAddress', false);
        $shippingAddress = Mage::helper('core')->jsonDecode($shippingAddress);

        // Handle virtual vs physical quotes
        if ($express->getQuote()->isVirtual()) {
            if (!$nonce || !$billingAddress) {
                return $this->errorAction('Billing address & payment method required.');
            }
        } else {
            $shippingMethod = $this->getRequest()->getParam('shippingMethod', false);
            if (!$shippingAddress || !$nonce || !$shippingMethod) {
                return $this->errorAction('Shipping address, shipping method & payment method required.');
            }

            $express->setShippingAddress($shippingAddress);
            $express->setShippingMethod($shippingMethod);
        }

        $express->setBillingAddress($billingAddress);

        // Is an email address present in the request
        if (isset($shippingAddress['emailAddress'])) {
            $express->setCustomerEmail($shippingAddress['emailAddress']);
        }

        // Set the payment method nonce
        $express->setNonce($nonce);

        // Submit the express checkout
        try {
            $express->submit();
            return $this->_returnJson(array('success' => true));
        } catch (Mage_Core_Exception $e) {
            return $this->errorAction($e->getMessage());
        } catch (Exception $e) {
            return $this->errorAction($e->getMessage());
        }
    }

    /**
     * Return an error to the user
     *
     * @param $message
     *
     * @return Gene_Braintree_ApplepayController
     */
    public function errorAction($message)
    {
        return $this->_returnJson(array(
            'success' => false,
            'message' => $message
        ));
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
        $this->getResponse()->setHeader('Content-type', 'application/json');

        return $this;
    }
}
