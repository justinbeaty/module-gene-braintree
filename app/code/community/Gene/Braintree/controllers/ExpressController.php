<?php

/**
 * Class Gene_Braintree_ExpressController
 *
 * @author Aidan Threadgold <braintreesupport@gene.co.uk> & Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_ExpressController extends Mage_Core_Controller_Front_Action
{

    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote;

    /**
     * Prevent access if disabled
     */
    public function preDispatch()
    {
        if (!Mage::getStoreConfig('payment/gene_braintree_paypal/express_active')) {
            $this->setFlag('', 'no-dispatch', true);

            return;
        }

        parent::preDispatch();
    }

    /**
     * Load the quote based on the session data or create a new one
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote()
    {
        if ($this->_quote) {
            return $this->_quote;
        }

        // Use the cart quote
        if (Mage::getSingleton('core/session')->getBraintreeExpressSource() == 'cart') {
            $this->_quote = Mage::getSingleton('checkout/session')->getQuote();
        } // Create the quote a'new
        else {
            $store = Mage::app()->getStore();
            $this->_quote = Mage::getModel('sales/quote')->setStoreId($store->getId());
            $quoteId = Mage::getSingleton('core/session')->getBraintreeExpressQuote();

            if ($quoteId) {
                $this->_quote = $this->_quote->load($quoteId);
            } else {
                $this->_quote->reserveOrderId();
            }
        }

        return $this->_quote;
    }

    /**
     * Set up the quote based on Paypal's response.
     *
     * @return Mage_Core_Controller_Varien_Action|void
     * @throws Exception
     */
    public function authorizationAction()
    {
        parse_str($this->getRequest()->getParam('form_data'), $formData);

        // Retrieve the form_key from the request
        $formKey = $this->getRequest()->getParam(
            'form_key',
            (isset($formData['form_key']) ? $formData['form_key'] : false)
        );

        // Validate form key
        if (Mage::getSingleton('core/session')->getFormKey() != $formKey) {
            Mage::getSingleton('core/session')->addError(Mage::helper('gene_braintree')->__('We were unable to start the express checkout.'));

            return $this->_redirect("braintree/express/error");
        }

        // Clean up
        Mage::getSingleton('core/session')->setBraintreeExpressQuote(null);
        Mage::getSingleton('core/session')->setBraintreeNonce(null);

        // Where the user came from - product or cart page
        Mage::getSingleton('core/session')->setBraintreeExpressSource(
            $this->getRequest()->getParam('source', 'product')
        );

        $paypal = json_decode($this->getRequest()->getParam('paypal'), true);
        // Check for a valid nonce
        if (!isset($paypal['nonce']) || empty($paypal['nonce'])) {
            Mage::getSingleton('core/session')->addError(Mage::helper('gene_braintree')->__('We were unable to process the response from PayPal. Please try again.'));

            return $this->_redirect("braintree/express/error");
        }

        // Check paypal sent an address
        if (!isset($paypal['details']['shippingAddress']) || !isset($paypal['details']['email'])) {
            Mage::getSingleton('core/session')->addError(Mage::helper('gene_braintree')->__('Please provide a shipping address.'));

            return $this->_redirect("braintree/express/error");
        }

        Mage::getModel('core/session')->setBraintreeNonce($paypal['nonce']);
        $paypalData = $paypal['details'];
        $quote = $this->_getQuote();

        // Pass the customer into the quote
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $quote->setCustomer(Mage::getSingleton('customer/session')->getCustomer());
        } else {
            // Save the email address
            $quote->setCustomerEmail($paypalData['email']);

            // Should we link guest orders to customers if they match?
            if (Mage::getStoreConfigFlag('payment/gene_braintree_paypal/express_link_guest')) {
                $customer = Mage::getModel('customer/customer')
                    ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
                    ->loadByEmail($paypalData['email']);
                if ($customer && $customer->getId()) {
                    $quote->setCustomer($customer);
                }
            }
        }

        // Is this express checkout request coming from the product page?
        if (isset($formData['product']) && isset($formData['qty'])) {
            $product = Mage::getModel('catalog/product')->load($formData['product']);
            if (!$product->getId()) {
                Mage::getSingleton('core/session')->addError(
                    Mage::helper('gene_braintree')->__('We\'re unable to load that product.')
                );

                return $this->_redirect("braintree/express/error");
            }

            // Build up the add request
            $request = new Varien_Object($formData);

            // Attempt to add the product into the quote
            try {
                $quote->addProduct($product, $request);
            } catch (Exception $e) {
                Mage::getSingleton('core/session')->addError(
                    Mage::helper('gene_braintree')->__(
                        'Sorry, we were unable to process your request. Please try again.'
                    )
                );

                return $this->_redirect("braintree/express/error");
            }
        }

        // Build the address
        if (isset($paypalData['firstName']) && isset($paypalData['lastName'])) {
            $firstName = $paypalData['firstName'];
            $lastName = $paypalData['lastName'];
        } elseif (isset($paypalData['shippingAddress']['recipientName'])) {
            list($firstName, $lastName) = explode(" ", $paypalData['shippingAddress']['recipientName'], 2);
        }

        // Retrieve the street
        $street = $paypalData['shippingAddress']['line1'];
        if (isset($paypalData['shippingAddress']['line2'])) {
            $street .= ' ' . $paypalData['shippingAddress']['line2'];
        }

        $address = Mage::getModel('sales/quote_address');
        $address->setFirstname($firstName)
            ->setLastname($lastName)
            ->setStreet($street)
            ->setCity($paypalData['shippingAddress']['city'])
            ->setCountryId($paypalData['shippingAddress']['countryCode'])
            ->setPostcode($paypalData['shippingAddress']['postalCode'])
            ->setTelephone(isset($paypalData['phone']) ? $paypalData['phone'] : '00000000000');

        // Determine if a region is required for the selected country
        if (Mage::helper('directory')->isRegionRequired($address->getCountryId()) && isset($paypalData['shippingAddress']['state'])) {
            if ($regionId = $this->getRegionId($address, $paypalData['shippingAddress']['state'])) {
                $address->setRegionId($regionId);
            }
        }

        // Save the addresses
        $quote->setShippingAddress($address);
        $quote->setBillingAddress($address);

        // Store quote id in session
        $quote->save();
        Mage::getSingleton('core/session')->setBraintreeExpressQuote($quote->getId());

        // redirect to choose shipping method
        return $this->_redirect("braintree/express/shipping");
    }

    /**
     * Retrieve the region_id based on various items
     *
     * @param $address
     * @param $regionId
     *
     * @return bool|mixed
     */
    protected function getRegionId($address, $regionId)
    {
        $region = Mage::getResourceModel('directory/region_collection')
            ->addFieldToFilter('country_id', $address->getCountryId())
            ->addFieldToFilter(
                array('code', 'default_name'),
                array(
                    array('eq' => $regionId),
                    array('eq' => $regionId)
                )
            );

        // Check we have a region
        if ($region->count() >= 1) {
            return $region->getFirstItem()->getId();
        }

        return false;
    }

    /**
     * Display shipping methods for the user to select.
     *
     * @return Mage_Core_Controller_Varien_Action
     * @throws Exception
     */
    public function shippingAction()
    {
        $quote = $this->_getQuote();
        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

        // Recollect all totals for the quote
        $quote->setTotalsCollectedFlag(false);
        $quote->getBillingAddress();
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->collectTotals();
        $quote->save();

        // Pull out the shipping rates
        $shippingRates = $quote->getShippingAddress()
            ->collectShippingRates()
            ->getAllShippingRates();

        // Save the shipping method
        $submitShipping = $this->getRequest()->getParam('submit_shipping');
        if (!empty($submitShipping)) {
            // If the quote is virtual process the order without a shipping method
            if ($quote->isVirtual()) {
                return $this->_redirect("braintree/express/process");
            }

            // Check the shipping rate we want to use is available
            $method = $this->getRequest()->getParam('shipping_method');
            if (!empty($method) && $quote->getShippingAddress()->getShippingRateByCode($method)) {
                $quote->getShippingAddress()->setShippingMethod($method);
                $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

                // Redirect to confirm payment
                return $this->_redirect("braintree/express/process");
            }

            // Missing a valid shipping method
            Mage::getSingleton('core/session')->addWarning(Mage::helper('gene_braintree')->__('Please select a shipping method.'));
        }

        // Build up the totals block
        /* @var $totals Mage_Checkout_Block_Cart_Totals */
        $totals = $this->getLayout()->createBlock('checkout/cart_totals')
            ->setTemplate('checkout/cart/totals.phtml')
            ->setCustomQuote($this->_getQuote());

        // View to select shipping method
        $block = $this->getLayout()->createBlock('gene_braintree/express_checkout')
            ->setChild('totals', $totals)
            ->setTemplate('gene/braintree/express/shipping_details.phtml')
            ->setShippingRates($shippingRates)
            ->setQuote($quote);

        $this->getResponse()->setBody($block->toHtml());
    }

    /**
     * Saving a shipping action will update the quote and then provide new totals
     *
     * @return \Mage_Core_Controller_Varien_Action|string
     */
    public function saveShippingAction()
    {
        $quote = $this->_getQuote();
        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

        // collect shipping rates
        $quote->getShippingAddress()->removeAllShippingRates();
        $quote->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates();

        // Save the shipping method
        $submitShipping = $this->getRequest()->getParam('submit_shipping');
        if (!empty($submitShipping)) {
            // Check the shipping rate we want to use is available
            $method = $this->getRequest()->getParam('shipping_method');
            if (!empty($method) && $quote->getShippingAddress()->getShippingRateByCode($method)) {
                $quote->getShippingAddress()->setShippingMethod($method);
                $quote->setTotalsCollectedFlag(false)->collectTotals()->save();
            }
        }

        return $this->_returnJson(array(
            'success' => true,
            'totals'  => $this->_returnTotals()
        ));
    }

    /**
     * Allow customers to add coupon codes into their orders
     *
     * @return \Gene_Braintree_ExpressController|\Zend_Controller_Response_Abstract
     */
    public function saveCouponAction()
    {
        $quote = $this->_getQuote();
        $couponCode = $this->getRequest()->getParam('coupon');
        $oldCoupon = $quote->getCouponCode();

        // Don't try and re-apply the already applied coupon
        if ($couponCode == $oldCoupon) {
            // Just alert the front-end the response was a success
            return $this->_returnJson(array(
                'success' => true
            ));
        }

        // If the user is trying to remove the coupon code allow them to
        if ($this->getRequest()->getParam('remove')) {
            $couponCode = '';
        }

        // Build our response in an array to be returned as JSON
        $response = array(
            'success' => false
        );

        try {
            $codeLength = strlen($couponCode);
            $isCodeLengthValid = $codeLength && $codeLength <= Mage_Checkout_Helper_Cart::COUPON_CODE_MAX_LENGTH;

            $this->_getQuote()->getShippingAddress()->setCollectShippingRates(true);
            $this->_getQuote()->setCouponCode($isCodeLengthValid ? $couponCode : '')
                ->collectTotals()
                ->save();

            if ($codeLength) {
                if ($isCodeLengthValid && $couponCode == $this->_getQuote()->getCouponCode()) {
                    $response['success'] = true;
                    $response['message'] = $this->__('Coupon code "%s" was applied.', Mage::helper('core')->escapeHtml($couponCode));
                } else {
                    $response['success'] = false;
                    $response['message'] = $this->__('Coupon code "%s" is not valid.', Mage::helper('core')->escapeHtml($couponCode));
                }
            } else {
                // The coupon has been removed successfully
                $response['success'] = true;
            }

        } catch (Mage_Core_Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        } catch (Exception $e) {
            $response['success'] = false;
            $response['message'] = $this->__('Cannot apply the coupon code.');
            Mage::logException($e);
        }

        // Include the totals HTML in the response
        $response['totals'] = $this->_returnTotals();

        return $this->_returnJson($response);
    }

    /**
     * Take the payment.
     */
    public function processAction()
    {
        $quote = $this->_getQuote();
        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

        // Handle free orders via coupon codes
        if ($quote->getGrandTotal() == 0) {
            $paymentMethod = $quote->getPayment();
            $paymentMethod->setMethod('free');
            $quote->setPayment($paymentMethod);
        } else {
            $paymentMethod = $quote->getPayment();
            $paymentMethod->setMethod('gene_braintree_paypal');
            $paymentMethod->setAdditionalInformation('payment_method_nonce', Mage::getModel('core/session')->getBraintreeNonce());
            $quote->setPayment($paymentMethod);
        }

        // Convert quote to order
        $convert = Mage::getSingleton('sales/convert_quote');

        /* @var $order Mage_Sales_Model_Order */
        $order = $convert->toOrder($quote);
        $order->setShippingAddress($convert->addressToOrderAddress($quote->getShippingAddress()));
        $order->setBillingAddress($convert->addressToOrderAddress($quote->getBillingAddress()));
        $order->setPayment($convert->paymentToOrderPayment($quote->getPayment()));

        // Add the items
        foreach ($quote->getAllItems() as $item) {
            $order->addItem($convert->itemToOrderItem($item));
        }

        // Set the order as complete
        /* @var $service Mage_Sales_Model_Service_Quote */
        $service = Mage::getModel('sales/service_quote', $order->getQuote());
        try {
            $service->submitAll();
        } catch (Mage_Core_Exception $e) {
            $this->errorAction($e->getMessage());

            return false;
        } catch (Exception $e) {
            $this->errorAction($e->getMessage());

            return false;
        }
        $order = $service->getOrder();

        // Send the new order email
        $order->sendNewOrderEmail();

        // Cleanup
        Mage::getSingleton('core/session')->setBraintreeExpressQuote(null);
        Mage::getSingleton('core/session')->setBraintreeNonce(null);
        Mage::getSingleton('core/session')->setBraintreeExpressSource(null);

        // Redirect to thank you page
        Mage::getSingleton('checkout/session')->setLastSuccessQuoteId($quote->getId());
        Mage::getSingleton('checkout/session')->setLastQuoteId($quote->getId());
        Mage::getSingleton('checkout/session')->setLastOrderId($order->getId());
        $this->getResponse()->setBody('complete');
    }

    /**
     * Display an error to the user
     *
     * @param bool|false $errorMessage
     */
    public function errorAction($errorMessage = false)
    {
        // View to select shipping method
        /* @var $block Gene_Braintree_Block_Express_Checkout */
        $block = $this->getLayout()->createBlock('gene_braintree/express_checkout')
            ->setTemplate('gene/braintree/express/error.phtml');

        if ($errorMessage) {
            $block->getLayout()->getMessagesBlock()->addError($errorMessage);
        }

        $this->getResponse()->setBody($block->toHtml());
    }

    /**
     * Return the totals in the Ajax response
     *
     * @return \Zend_Controller_Response_Abstract
     */
    protected function _returnTotals()
    {
        // Build up the totals block
        /* @var $totals Mage_Checkout_Block_Cart_Totals */
        $totals = $this->getLayout()->createBlock('checkout/cart_totals')
            ->setTemplate('checkout/cart/totals.phtml')
            ->setCustomQuote($this->_getQuote());

        // Set the body in the response
        return $totals->toHtml();
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
