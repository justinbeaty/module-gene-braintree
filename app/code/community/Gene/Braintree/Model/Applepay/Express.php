<?php

/**
 * Class Gene_Braintree_Model_Applepay_Express
 *
 * @author Paul Canning <paul.canning@gene.co.uk>
 * @author Dave Macaulay <dave@gene.co.uk>
 */
class Gene_Braintree_Model_Applepay_Express extends Mage_Core_Model_Abstract
{
    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $quote;

    /**
     * Retrieve the quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        if ($this->quote === null) {
            if ($quoteId = Mage::getSingleton('checkout/session')->getApplePayProductQuoteId()) {
                $this->quote = Mage::getModel('sales/quote')->load($quoteId);
            } else {
                $this->quote = Mage::getSingleton('checkout/session')->getQuote();
            }
        }

        return $this->quote;
    }

    /**
     * Build up a new product quote
     *
     * @param $productForm
     * @return Mage_Sales_Model_Quote
     * @throws Mage_Core_Model_Store_Exception
     */
    public function initProductQuote($productForm)
    {
        // Verify our quote contains the product being added
        if (Mage::getSingleton('checkout/session')->getApplePayProductQuoteId()) {
            $quote = $this->getQuote();

            /* @var $item Mage_Sales_Model_Quote_Item */
            foreach ($quote->getAllVisibleItems() as $item) {
                if ($item->getProductId() == $this->getProductId() && $quote->getItemsCount() == 1) {
                    return $quote;
                }
            }
        }

        // Ensure the product exists
        $product = Mage::getModel('catalog/product')->load($this->getProductId());
        if (!$product->getId()) {
            return $this->errorAction(Mage::helper('gene_braintree')->__('We\'re unable to load that product.'));
        }

        // Build a new quote
        $quote = Mage::getModel('sales/quote')->setStoreId(Mage::app()->getStore()->getId());

        // Build up the add request
        $formData = array();
        parse_str($productForm, $formData);
        $request = new Varien_Object($formData);

        // Attempt to add the product into the quote
        $item = $quote->addProduct($product, $request);

        // Fire the event for adding a product to cart
        Mage::dispatchEvent('checkout_cart_product_add_after', array('quote_item' => $item, 'product' => $product));

        // Collect totals
        $quote->unsTotalsCollectedFlag()->collectTotals();

        // Save the quote
        $quote->save();

        Mage::getSingleton('checkout/session')->setApplePayProductQuoteId($quote->getId());

        $this->quote = $quote;
        return $quote;
    }

    /**
     * Convert the incoming Apple Pay address into a Magento address
     *
     * @param $address
     * @return array
     */
    protected function convertToMagentoAddress($address)
    {
        if (is_string($address)) {
            $address = Mage::helper('core')->jsonDecode($address);
        }

        // Retrieve the countryId from the request
        $countryId = strtoupper($address['countryCode']);
        if ((!$countryId || empty($countryId)) && ($countryName = $address['country'])) {
            $countryCollection = Mage::getModel('directory/country')->getCollection();
            foreach ($countryCollection as $country) {
                if ($countryName == $country->getName()) {
                    $countryId = strtoupper($country->getCountryId());
                    break;
                }
            }
        }

        $magentoAddress = array(
            'street' => implode("\n", $address['addressLines']),
            'firstname' => $address['givenName'],
            'lastname' => $address['familyName'],
            'city' => $address['locality'],
            'country_id' => $countryId,
            'postcode' => $address['postalCode'],
            'telephone' => isset($address['phoneNumber']) ? $address['phoneNumber'] : '0000000000'
        );

        // Determine if a region is required for the selected country
        if (Mage::helper('directory')->isRegionRequired($countryId) && isset($address['administrativeArea'])) {
            if ($regionId = $this->getRegionId($address, $magentoAddress)) {
                $magentoAddress['region_id'] = $regionId;
            }
        }

        return $magentoAddress;
    }

    /**
     * Retrieve the region_id based on various items
     *
     * @param $address
     * @param $magentoAddress
     * @return bool|mixed
     */
    protected function getRegionId($address, $magentoAddress)
    {
        $region = Mage::getResourceModel('directory/region_collection')
            ->addFieldToFilter('country_id', $magentoAddress['country_id'])
            ->addFieldToFilter(
                array('code', 'default_name'),
                array(
                    array('eq' => $address['administrativeArea']),
                    array('eq' => $address['administrativeArea'])
                )
            );

        // Check we have a region
        if ($region->count() >= 1) {
            return $region->getFirstItem()->getId();
        }

        return false;
    }

    /**
     * Retrieve the shipping rates for the Apple Pay session
     *
     * @return array
     */
    public function getShippingRates()
    {
        $quote = $this->getQuote();

        // Recollect totals
        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

        $shippingAddress = $quote->getShippingAddress();

        // Set the values on the quotes shipping address
        $shippingAddress->setCity((string) $this->getCity());
        $shippingAddress->setPostcode((string) $this->getPostcode());
        $shippingAddress->setCountryId((string) strtoupper($this->getCountryId()));

        // Recollect all totals for the quote
        $quote->setTotalsCollectedFlag(false);
        $quote->getBillingAddress();
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->collectTotals();
        $quote->save();

        // Pull out the shipping rates
        $shippingRates = $shippingAddress
            ->collectShippingRates()
            ->getGroupedAllShippingRates();

        $rates = [];
        $currentRate = false;

        /* @var $shippingRate Mage_Sales_Model_Quote_Address_Rate */
        foreach ($shippingRates as $carrier => $groupRates) {
            foreach ($groupRates as $shippingRate) {
                // Is this the current selected shipping method?
                if ($quote->getShippingAddress()->getShippingMethod() == $shippingRate->getCode()) {
                    $currentRate = $this->convertShippingRate($shippingRate);
                } else {
                    $rates[] = $this->convertShippingRate($shippingRate);
                }
            }
        }

        // Add the current shipping rate first
        if ($currentRate) {
            array_unshift($rates, $currentRate);
        }

        return $rates;
    }

    /**
     * Convert a shipping rate into a consumable shipping rate
     *
     * @param $shippingRate
     * @return array
     */
    protected function convertShippingRate($shippingRate)
    {
        // Don't show the same information twice
        $detail = $shippingRate->getMethodTitle();
        if ($shippingRate->getCarrierTitle() == $detail) {
            $detail = '';
        }

        return array(
            'label' => $shippingRate->getCarrierTitle(),
            'amount' =>
                (float) number_format(Mage::helper('core')->currency($shippingRate->getPrice(), false, false), 2),
            'detail' => $detail,
            'identifier' => $shippingRate->getCode()
        );
    }

    /**
     * Add any additional response parameters
     *
     * @param $response
     * @return mixed
     */
    public function getAdditionalResponse($response)
    {
        $quote = $this->getQuote();

        // Hand over the total if a product is present
        if ($this->getProductId()) {
            // Pass the total to the express system
            $response['total'] = number_format($quote->getGrandTotal() - $quote->getShippingAddress()->getShippingAmount(), 2);
        }

        // Hand over a discount
        $totals = $quote->getTotals();
        if (isset($totals['discount']) && $totals['discount']->getValue() < 0) {
            $label = Mage::helper('gene_braintree')->__('Discount%s', ($quote->getCouponCode() ? ' (' . $quote->getCouponCode() . ')' : ''));
            $response['items'][] = array(
                'label' => $label,
                'amount' => number_format($totals['discount']->getValue(), 2)
            );
        }

        return $response;
    }

    /**
     * Retrieve the country ID from the name
     *
     * @param $countryName
     * @return bool|string
     */
    public function getCountryIdFromName($countryName)
    {
        $countryCollection = Mage::getModel('directory/country')->getCollection();
        foreach ($countryCollection as $country) {
            if ($countryName == $country->getName()) {
                return $country->getCountryId();
                break;
            }
        }

        return false;
    }

    /**
     * Set the shipping address into the model
     *
     * @param $shippingAddress
     * @return $this
     */
    public function setShippingAddress($shippingAddress)
    {
        $this->getQuote()->getShippingAddress()->addData($this->convertToMagentoAddress($shippingAddress));

        return $this;
    }

    /**
     * Set the billing address into the model
     *
     * @param $billingAddress
     * @return $this
     */
    public function setBillingAddress($billingAddress)
    {
        $this->getQuote()->getBillingAddress()->addData($this->convertToMagentoAddress($billingAddress));

        return $this;
    }

    /**
     * Set the shipping method
     *
     * @param $shippingMethod
     * @return $this
     */
    public function setShippingMethod($shippingMethod)
    {
        $quote = $this->getQuote();

        // Resolve issues with certain table rates modules
        $quote->setTotalsCollectedFlag(false);
        $quote->getBillingAddress();
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->collectTotals();
        $quote->save();

        // Set the shipping method on the address
        $quote->getShippingAddress()->setShippingMethod($shippingMethod);

        return $this;
    }

    /**
     * Submit the ApplePay order
     *
     * @return $this
     * @throws Exception
     */
    public function submit()
    {
        $quote = $this->getQuote();

        $quote->setCustomerFirstname($quote->getBillingAddress()->getFirstname());
        $quote->setCustomerLastname($quote->getBillingAddress()->getLastname());

        // Pass the customer into the quote
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $quote->setCustomer(Mage::getSingleton('customer/session')->getCustomer());
        } elseif ($email = $this->getCustomerEmail()) {
            // Save the email address
            $quote->setCustomerEmail($email);

            // Should we link guest orders to customers if they match?
            if (Mage::getStoreConfigFlag('payment/gene_braintree_applepay/express_link_guest')) {
                $customer = Mage::getModel('customer/customer')
                    ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
                    ->loadByEmail($email);
                if ($customer && $customer->getId()) {
                    $quote->setCustomer($customer);
                }
            }
        }

        // Recollect totals
        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

        // Handle free orders via coupon codes
        if ($quote->getGrandTotal() == 0) {
            $paymentMethod = $quote->getPayment();
            $paymentMethod->setMethod('free');
            $quote->setPayment($paymentMethod);
        } else {
            $paymentMethod = $quote->getPayment();
            $paymentMethod->setMethod('gene_braintree_applepay');
            $paymentMethod->setAdditionalInformation('payment_method_nonce', $this->getNonce());
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
        $service->submitAll();

        $order = $service->getOrder();

        // Send the new order email
        $order->sendNewOrderEmail();

        Mage::getSingleton('checkout/session')->unsApplePayProductQuoteId();

        // Update session in regards to completed order above
        Mage::getSingleton('checkout/session')->setLastSuccessQuoteId($quote->getId());
        Mage::getSingleton('checkout/session')->setLastQuoteId($quote->getId());
        Mage::getSingleton('checkout/session')->setLastOrderId($order->getId());

        return $this;
    }

    /**
     * Is the express mode enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        if (Mage::getStoreConfig('payment/gene_braintree_applepay/active')
            && Mage::getStoreConfig('payment/gene_braintree_applepay/express_active')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Is Express enabled on product pages?
     *
     * @return bool
     */
    public function isEnabledPdp()
    {
        if ($this->isEnabled() && Mage::getStoreConfig('payment/gene_braintree_applepay/express_pdp')) {
            return true;
        }

        return false;
    }

    /**
     * Is express enabled in the cart?
     *
     * @return bool
     */
    public function isEnabledCart()
    {
        if ($this->isEnabled() && Mage::getStoreConfig('payment/gene_braintree_applepay/express_cart')) {
            return true;
        }

        return false;
    }
}
