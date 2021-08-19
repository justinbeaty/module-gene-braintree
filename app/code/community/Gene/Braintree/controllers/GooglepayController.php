<?php
/**
 * Class Gene_Braintree_GooglepayController
 *
 * @author Paul Canning <paul.canning@gene.co.uk>
 */
class Gene_Braintree_GooglepayController extends Mage_Core_Controller_Front_Action
{
    /**
     * @var Mage_Sales_Model_Quote $_quote
     */
    protected $_quote;

    /**
     * Return a client token to the browser
     *
     * @return Gene_Braintree_GooglepayController
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
     * @return Mage_Core_Controller_Varien_Action
     * @throws Mage_Core_Model_Store_Exception
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

            return $this->_redirect('braintree/googlepay/error', ['_secure' => Mage::app()->getFrontController()->getRequest()->isSecure()]);
        }

        // Clean up
        Mage::getSingleton('core/session')->setBraintreeExpressQuote(null);
        Mage::getSingleton('core/session')->setBraintreeNonce(null);

        // Where the user came from - product or cart page
        Mage::getSingleton('core/session')->setBraintreeExpressSource(
            $this->getRequest()->getParam('source', 'product')
        );

        $googlepay = json_decode($this->getRequest()->getParam('googlepay'), true);

        // Check for a valid nonce
        if (!isset($googlepay['nonce']) || empty($googlepay['nonce'])) {
            Mage::getSingleton('core/session')->addError(Mage::helper('gene_braintree')->__('We were unable to process the response from Google Pay. Please try again.'));

            return $this->_redirect('braintree/googlepay/error', ['_secure' => Mage::app()->getFrontController()->getRequest()->isSecure()]);
        }

        // Check googlepay sent an address
        if (!isset($googlepay['shippingAddress'], $googlepay['email'])) {
            Mage::getSingleton('core/session')->addError(Mage::helper('gene_braintree')->__('Please provide a shipping address.'));
            return $this->_redirect('braintree/googlepay/error', ['_secure' => Mage::app()->getFrontController()->getRequest()->isSecure()]);
        }

        Mage::getModel('core/session')->setBraintreeNonce($googlepay['nonce']);

        $quote = $this->getQuote();

        // Pass the customer into the quote
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $quote->setCustomer(Mage::getSingleton('customer/session')->getCustomer());
        } else {
            // Save the email address
            $quote->setCustomerEmail($googlepay['email']);
        }

        // Is this express checkout request coming from the product page?
        if (isset($formData['product'], $formData['qty'])) {
            $product = Mage::getModel('catalog/product')->load($formData['product']);
            if (!$product->getId()) {
                Mage::getSingleton('core/session')->addError(
                    Mage::helper('gene_braintree')->__("We're unable to load that product.")
                );

                return $this->_redirect('braintree/googlepay/error', ['_secure' => Mage::app()->getFrontController()->getRequest()->isSecure()]);
            }

            // Build up the add request
            $request = new Varien_Object($formData);

            // Attempt to add the product into the quote
            try {
                $quote->addProduct($product, $request);
            } catch (Exception $e) {
                Mage::getSingleton('core/session')->addError(
                    Mage::helper('gene_braintree')->__('Sorry, we were unable to process your request. Please try again.')
                );

                return $this->_redirect('braintree/googlepay/error', ['_secure' => Mage::app()->getFrontController()->getRequest()->isSecure()]);
            }
        }

        // Build the address
        $billingAddress = $googlepay['paymentMethodData']['info']['billingAddress'];
        $shippingAddress = $googlepay['shippingAddress'];

        if (isset($billingAddress['name'])) {
            list($firstName, $lastName) = explode(' ', $billingAddress['name'], 2);
        }

        // Retrieve the street
        $street = $shippingAddress['address1'];
        if (!empty($shippingAddress['address2'])) {
            $street .= ' ' . $shippingAddress['address2'];
        }
        if (!empty($shippingAddress['address3'])) {
            $street .= ' ' . $shippingAddress['address3'];
        }

        $address = Mage::getModel('sales/quote_address');
        $address->setFirstname($firstName)
            ->setLastname($lastName)
            ->setStreet($street)
            ->setCity($shippingAddress['locality'])
            ->setCountryId($shippingAddress['countryCode'])
            ->setPostcode($shippingAddress['postalCode'])
            ->setTelephone(isset($billingAddress['phoneNumber']) ?: '00000000000');

        // Determine if a region is required for the selected country
        if (
            isset($shippingAddress['administrativeArea'])
            && ($regionId = $this->getRegionId($address, $shippingAddress['administrativeArea']))
            && Mage::helper('directory')->isRegionRequired($address->getCountryId())
        ) {
            $address->setRegionId($regionId);
        }

        // Save the addresses
        $quote->setShippingAddress($address);
        $quote->setBillingAddress($address);

        // Store quote id in session
        $quote->save();
        Mage::getSingleton('core/session')->setBraintreeExpressQuote($quote->getId());

        // redirect to choose shipping method
        return $this->_redirect('braintree/googlepay/shipping', ['_secure' => Mage::app()->getFrontController()->getRequest()->isSecure()]);
    }

    /**
     * @return Mage_Core_Controller_Varien_Action
     * @throws Mage_Core_Model_Store_Exception
     */
    public function shippingAction()
    {
        $quote = $this->getQuote();
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
                return $this->_redirect('braintree/googlepay/process', ['_secure' => Mage::app()->getFrontController()->getRequest()->isSecure()]);
            }

            // Check the shipping rate we want to use is available
            $method = $this->getRequest()->getParam('shipping_method');
            if (!empty($method) && $quote->getShippingAddress()->getShippingRateByCode($method)) {
                $quote->getShippingAddress()->setShippingMethod($method);
                $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

                // Redirect to confirm payment
                return $this->_redirect('braintree/googlepay/process', ['_secure' => Mage::app()->getFrontController()->getRequest()->isSecure()]);
            }

            // Missing a valid shipping method
            Mage::getSingleton('core/session')->addWarning(Mage::helper('gene_braintree')->__('Please select a shipping method.'));
        }

        // Build up the totals block
        /* @var $totals Mage_Checkout_Block_Cart_Totals */
        $totals = $this->getLayout()->createBlock('checkout/cart_totals')
            ->setTemplate('checkout/cart/totals.phtml')
            ->setCustomQuote($this->getQuote());

        // View to select shipping method
        $block = $this->getLayout()->createBlock('gene_braintree/googlepay_express_checkout')
            ->setChild('totals', $totals)
            ->setTemplate('gene/braintree/googlepay/shipping_details.phtml')
            ->setShippingRates($shippingRates)
            ->setQuote($quote);

        $this->getResponse()->setBody($block->toHtml());
    }

    /**
     * @return bool
     * @throws Mage_Core_Model_Store_Exception
     */
    public function processAction()
    {
        $quote = $this->getQuote();
        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

        // Handle free orders via coupon codes
        if ($quote->getGrandTotal() === 0) {
            $paymentMethod = $quote->getPayment();
            $paymentMethod->setMethod('free');
            $quote->setPayment($paymentMethod);
        } else {
            $paymentMethod = $quote->getPayment();
            $paymentMethod->setMethod('gene_braintree_googlepay');
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
        /* @var  Gene_Braintree_Block_Express_Checkout $block */
        $block = $this->getLayout()
            ->createBlock('gene_braintree/googlepay_express_checkout')
            ->setTemplate('gene/braintree/googlepay/express/error.phtml');

        if ($errorMessage) {
            $block->getLayout()->getMessagesBlock()->addError($errorMessage);
        }

        $this->getResponse()->setBody($block->toHtml());
    }

    /**
     * @return mixed
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function getQuote()
    {
        if ($this->_quote) {
            return $this->_quote;
        }

        // Use the cart quote
        if (Mage::getSingleton('core/session')->getBraintreeExpressSource() === 'cart') {
            $this->_quote = Mage::getSingleton('checkout/session')->getQuote();
        } else {
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
     * @param $address
     * @param $regionId
     * @return bool
     */
    protected function getRegionId($address, $regionId)
    {
        $region = Mage::getResourceModel('directory/region_collection')
            ->addFieldToFilter('country_id', $address->getCountryId())
            ->addFieldToFilter(
                ['code', 'default_name'],
                [
                    ['eq' => $regionId],
                    ['eq' => $regionId]
                ]
            );

        // Check we have a region
        if ($region->count() >= 1) {
            return $region->getFirstItem()->getId();
        }

        return false;
    }

    /**
     * @param $array
     * @return $this
     */
    protected function _returnJson($array)
    {
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($array));
        $this->getResponse()->setHeader('Content-type', 'application/json');

        return $this;
    }
}
