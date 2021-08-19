<?php

/**
 * Class Gene_Braintree_Model_Wrapper_Braintree
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Wrapper_Braintree extends Mage_Core_Model_Abstract
{

    CONST BRAINTREE_ENVIRONMENT_PATH = 'payment/gene_braintree/environment';
    CONST BRAINTREE_MERCHANT_ID_PATH = 'payment/gene_braintree/merchant_id';
    CONST BRAINTREE_MERCHANT_ACCOUNT_ID_PATH = 'payment/gene_braintree/merchant_account_id';
    CONST BRAINTREE_PUBLIC_KEY_PATH = 'payment/gene_braintree/public_key';
    CONST BRAINTREE_PRIVATE_KEY_PATH = 'payment/gene_braintree/private_key';

    CONST BRAINTREE_SANDBOX_MERCHANT_ID_PATH = 'payment/gene_braintree/sandbox_merchant_id';
    CONST BRAINTREE_SANDBOX_MERCHANT_ACCOUNT_ID_PATH = 'payment/gene_braintree/sandbox_merchant_account_id';
    CONST BRAINTREE_SANDBOX_PUBLIC_KEY_PATH = 'payment/gene_braintree/sandbox_public_key';
    CONST BRAINTREE_SANDBOX_PRIVATE_KEY_PATH = 'payment/gene_braintree/sandbox_private_key';

    const BRAINTREE_MULTI_CURRENCY = 'payment/gene_braintree/multi_currency_enable';
    const BRAINTREE_MULTI_CURRENCY_MAPPING = 'payment/gene_braintree/multi_currency_mapping';

    /**
     * Store whether or not the system has recently connected to the Braintree API successfully
     */
    const BRAINTREE_API_CONFIG_STATUS = 'payment/gene_braintree/api_config_status';

    /**
     * Store the customer
     *
     * @var Braintree_Customer
     */
    protected $_customer;

    /**
     * Store the Braintree ID
     *
     * @var int
     */
    protected $_braintreeId;

    /**
     * Used to track whether the payment methods are available
     *
     * @var bool
     */
    protected $_validated = null;

    /**
     * Store whether or not we've init the environment yet
     *
     * @var bool
     */
    protected $init = false;

    /**
     * Ensure our include path is included correctly
     */
    protected function _construct()
    {
        Gene_Braintree_Model_Observer::initIncludePath();
    }

    /**
     * Setup the environment
     *
     * @param null $store
     *
     * @return $this
     */
    public function init($store = null)
    {
        if (!$this->init) {

            // Setup the various configuration variables
            $environment = Mage::getStoreConfig(self::BRAINTREE_ENVIRONMENT_PATH, $store);
            Braintree_Configuration::environment($environment);
            Braintree_Configuration::sslVersion(6);

            if ($environment == Gene_Braintree_Model_Source_Environment::PRODUCTION) {
                Braintree_Configuration::merchantId(Mage::getStoreConfig(self::BRAINTREE_MERCHANT_ID_PATH, $store));
                Braintree_Configuration::publicKey(Mage::getStoreConfig(self::BRAINTREE_PUBLIC_KEY_PATH, $store));
                Braintree_Configuration::privateKey(Mage::getStoreConfig(self::BRAINTREE_PRIVATE_KEY_PATH, $store));
            } else {
                Braintree_Configuration::merchantId(Mage::getStoreConfig(self::BRAINTREE_SANDBOX_MERCHANT_ID_PATH, $store));
                Braintree_Configuration::publicKey(Mage::getStoreConfig(self::BRAINTREE_SANDBOX_PUBLIC_KEY_PATH, $store));
                Braintree_Configuration::privateKey(Mage::getStoreConfig(self::BRAINTREE_SANDBOX_PRIVATE_KEY_PATH, $store));
            }

            // Set our flag
            $this->_init = true;
        }

        return $this;
    }

    /**
     * Find a transaction within Braintree
     *
     * @param $transactionId
     *
     * @return object
     */
    public function findTransaction($transactionId)
    {
        return Braintree_Transaction::find($transactionId);
    }

    /**
     * If we're trying to charge a 3D secure card in the vault we need to build a special nonce
     *
     * @param $paymentMethodToken
     *
     * @return mixed
     */
    public function getThreeDSecureVaultNonce($paymentMethodToken)
    {
        $this->init();

        $result = Braintree_PaymentMethodNonce::create($paymentMethodToken);

        return $result->paymentMethodNonce->nonce;
    }

    /**
     * Try and load the Braintree customer from the stored customer ID
     *
     * @param $braintreeCustomerId
     *
     * @return Braintree_Customer
     */
    public function getCustomer($braintreeCustomerId)
    {
        // Try and load it from the customer
        if (!$this->_customer && !isset($this->_customer[$braintreeCustomerId])) {
            try {
                $this->_customer[$braintreeCustomerId] = Braintree_Customer::find($braintreeCustomerId);
            } catch (Exception $e) {
                return false;
            }
        }

        return $this->_customer[$braintreeCustomerId];
    }

    /**
     * Check to see whether this customer already exists
     *
     * @return bool|object
     */
    public function checkIsCustomer()
    {
        try {
            // Check to see that we can generate a braintree ID
            if ($braintreeId = $this->getBraintreeId()) {
                // Proxy this request to the other method which has caching
                return $this->getCustomer($braintreeId);
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Generate a server side token with the specified account ID
     *
     * @return mixed
     */
    public function generateToken()
    {
        // Use the class to generate the token
        return Braintree_ClientToken::generate(
            array("merchantAccountId" => $this->getMerchantAccountId())
        );
    }


    /**
     * Check a customer owns the method we're trying to modify
     *
     * @param $paymentMethod
     *
     * @return bool
     */
    public function customerOwnsMethod($paymentMethod)
    {
        // Grab the customer ID from the customers account
        $customerId = Mage::getSingleton('customer/session')->getCustomer()->getBraintreeCustomerId();

        // Detect which type of payment method we've got here
        if ($paymentMethod instanceof Braintree_PayPalAccount) {

            // Grab the customer
            $customer = $this->getCustomer($customerId);

            // Store all the tokens in an array
            $customerTokens = array();

            // Check the customer has PayPal Accounts
            if (isset($customer->paypalAccounts)) {

                /* @var $payPalAccount Braintree_PayPalAccount */
                foreach ($customer->paypalAccounts as $payPalAccount) {
                    if (isset($payPalAccount->token)) {
                        $customerTokens[] = $payPalAccount->token;
                    }
                }
            } else {
                return false;
            }

            // Check to see if this customer account contains this token
            if (in_array($paymentMethod->token, $customerTokens)) {
                return true;
            }

            return false;

        } else if (isset($paymentMethod->customerId) && $paymentMethod->customerId == $customerId) {

            return true;
        }

        return false;
    }

    /**
     * Retrieve the Braintree ID from Magento
     *
     * @return bool|string
     */
    protected function getBraintreeId()
    {
        // Retrieve the Braintree ID from the admin quote
        if (Mage::app()->getStore()->isAdmin()) {
            // Get the admin quote
            $quote = $this->getQuote();
            $customer = $quote->getCustomer();

            // Determine whether they have a Braintree customer ID already
            if ($brainteeId = $customer->getBraintreeCustomerId()) {
                return $brainteeId;
            }

            // If not let's create them one
            $brainteeId = $this->buildCustomerId();
            $customer->setBraintreeCustomerId($brainteeId)->save();
            return $brainteeId;
        }

        // Some basic caching
        if (!$this->_braintreeId) {
            // Is the customer already logged in
            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                // Retrieve the current customer
                $customer = Mage::getSingleton('customer/session')->getCustomer();

                // Determine whether they have a braintree customer ID already
                if ($brainteeId = $customer->getBraintreeCustomerId()) {
                    $this->_braintreeId = $customer->getBraintreeCustomerId();
                } else {
                    // If not let's create them one
                    $this->_braintreeId = $this->buildCustomerId();
                    $customer->setBraintreeCustomerId($this->_braintreeId)->save();
                }

            } else {
                if ((Mage::getSingleton('checkout/type_onepage')->getCheckoutMethod() == 'login_in' ||
                    Mage::getSingleton('checkout/type_onepage')->getCheckoutMethod() == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER)
                ) {
                    // Check to see if we've already generated an ID
                    if ($braintreeId = Mage::getSingleton('checkout/session')->getBraintreeCustomerId()) {
                        $this->_braintreeId = $braintreeId;
                    } else {
                        // If the user plans to register let's build them an ID and store it in their session
                        $this->_braintreeId = $this->buildCustomerId();
                        Mage::getSingleton('checkout/session')->setBraintreeCustomerId($this->_braintreeId);
                    }
                }
            }

        }

        return $this->_braintreeId;
    }

    /**
     * Return an admin configuration value, older versions of Magento don't implement getConfigDataValue
     *
     * @param $path
     *
     * @return mixed|\Varien_Simplexml_Element
     */
    protected function getAdminConfigValue($path)
    {
        // If we have the getConfigDataValue use that
        if (method_exists('Mage_Adminhtml_Model_Config_Data', 'getConfigDataValue')) {
            return Mage::getSingleton('adminhtml/config_data')->getConfigDataValue($path);
        }

        // Otherwise use the default amazing getStoreConfig
        return Mage::getStoreConfig($path);
    }

    /**
     * If a transaction has been voided it's transaction ID can change
     *
     * @param $transactionId
     *
     * @return string
     */
    public function getCleanTransactionId($transactionId)
    {
        return strtok($transactionId, '-');
    }

    /**
     * Validate the credentials inputted into the admin area
     *
     * @param bool|false $prettyResponse
     * @param bool|false $alreadyInit
     * @param bool|false $merchantAccountId
     * @param bool|false $throwException
     *
     * @return bool|string
     * @throws \Exception
     */
    public function validateCredentials(
        $prettyResponse = false, $alreadyInit = false, $merchantAccountId = false, $throwException = false
    ) {
        // Try to init the environment
        try {
            if (!$alreadyInit) {
                // If we're within the admin we want to grab these values from whichever store we're modifying
                if (Mage::app()->getStore()->isAdmin()) {
                    // Setup the various configuration variables
                    $environment = $this->getAdminConfigValue(self::BRAINTREE_ENVIRONMENT_PATH);
                    Braintree_Configuration::environment($environment);
                    Braintree_Configuration::sslVersion(6);

                    // Change logic based on environment
                    if ($environment == Gene_Braintree_Model_Source_Environment::PRODUCTION) {
                        Braintree_Configuration::merchantId($this->getAdminConfigValue(self::BRAINTREE_MERCHANT_ID_PATH));
                        Braintree_Configuration::publicKey($this->getAdminConfigValue(self::BRAINTREE_PUBLIC_KEY_PATH));
                        Braintree_Configuration::privateKey($this->getAdminConfigValue(self::BRAINTREE_PRIVATE_KEY_PATH));
                    } else if ($environment == Gene_Braintree_Model_Source_Environment::SANDBOX) {
                        Braintree_Configuration::merchantId($this->getAdminConfigValue(self::BRAINTREE_SANDBOX_MERCHANT_ID_PATH));
                        Braintree_Configuration::publicKey($this->getAdminConfigValue(self::BRAINTREE_SANDBOX_PUBLIC_KEY_PATH));
                        Braintree_Configuration::privateKey($this->getAdminConfigValue(self::BRAINTREE_SANDBOX_PRIVATE_KEY_PATH));
                    } else {
                        return $this->_updateApiStatus(false);
                    }
                } else {
                    $this->init();
                }
            }

            // Attempt to retrieve the gateway plans to check
            Braintree_ClientToken::generate();

        } catch (Exception $e) {
            // Do we want to rethrow the exception?
            if ($throwException) {
                throw $e;
            }

            $this->_updateApiStatus(false);

            // Otherwise give the user a little bit more information
            if ($prettyResponse) {
                return '<span style="color: red;font-weight: bold;" id="braintree-valid-config">' . Mage::helper('gene_braintree')->__('Invalid Credentials') . '</span><br />' . Mage::helper('gene_braintree')->__('Payments cannot be processed until this is resolved, due to this the methods will be hidden within the checkout');
            }

            // Otherwise return with a boolean
            return false;
        }

        // Check to see if we've been passed the merchant account ID?
        if (!$merchantAccountId) {
            if (Mage::app()->getStore()->isAdmin()) {
                // Setup the various configuration variables
                $environment = $this->getAdminConfigValue(self::BRAINTREE_ENVIRONMENT_PATH);

                if ($environment == Gene_Braintree_Model_Source_Environment::PRODUCTION) {
                    $merchantAccountId = $this->getAdminConfigValue(self::BRAINTREE_MERCHANT_ACCOUNT_ID_PATH);
                } else {
                    $merchantAccountId = $this->getAdminConfigValue(self::BRAINTREE_SANDBOX_MERCHANT_ACCOUNT_ID_PATH);
                }
            } else {
                $merchantAccountId = $this->getMerchantAccountId();
            }
        }

        // Validate the merchant account ID
        try {
            Braintree_Configuration::gateway()->merchantAccount()->find($merchantAccountId);
        } catch (Exception $e) {
            // Do we want to rethrow the exception?
            if ($throwException) {
                throw $e;
            }

            $this->_updateApiStatus(false);

            // Otherwise do we want a pretty response?
            if ($prettyResponse) {
                return '<span style="color: orange;font-weight: bold;" id="braintree-valid-config">' . Mage::helper('gene_braintree')->__('Invalid Merchant Account ID') . '</span><br />' . Mage::helper('gene_braintree')->__('Payments cannot be processed until this is resolved. We cannot find your merchant account ID associated with the other credentials you\'ve provided, please update this field');
            }

            // Finally return a boolean
            return false;
        }

        $this->_updateApiStatus(true);

        if ($prettyResponse) {
            return '<span style="color: green;font-weight: bold;" id="braintree-valid-config">' . Mage::helper('gene_braintree')->__('Valid Credentials') . '</span><br />' . Mage::helper('gene_braintree')->__('You\'re ready to accept payments via Braintree');
        }

        return true;
    }

    /**
     * Validate the credentials once, this is used during the payment methods available check
     *
     * @return bool
     */
    public function validateCredentialsOnce()
    {
        // Check to see if it's been validated yet
        if (is_null($this->_validated)) {
            // Check the Braintree lib version is above 2.32, as this is when 3D secure appeared
            if (Braintree_Version::get() < 2.32) {
                $this->_validated = false;
            } else {
                // Check that the module is fully setup
                if (!$this->_validateConfiguration()) {
                    // If not the payment methods aren't available
                    $this->_validated = false;

                } else {
                    // Check to see if the connection is valid
                    $this->_validated = $this->_getApiStatus();
                }
            }
        }

        return $this->_validated;
    }

    /**
     * Validate if the configuration is setup correctly
     *
     * @return bool
     */
    protected function _validateConfiguration()
    {
        // Retrieve the environment
        $environment = Mage::getStoreConfig(self::BRAINTREE_ENVIRONMENT_PATH);

        if ($environment == Gene_Braintree_Model_Source_Environment::PRODUCTION) {
            return Mage::getStoreConfig(Gene_Braintree_Model_Wrapper_Braintree::BRAINTREE_MERCHANT_ID_PATH)
                && Mage::getStoreConfig(Gene_Braintree_Model_Wrapper_Braintree::BRAINTREE_PUBLIC_KEY_PATH)
                && Mage::getStoreConfig(Gene_Braintree_Model_Wrapper_Braintree::BRAINTREE_PRIVATE_KEY_PATH);
        } elseif ($environment == Gene_Braintree_Model_Source_Environment::SANDBOX) {
            return Mage::getStoreConfig(Gene_Braintree_Model_Wrapper_Braintree::BRAINTREE_SANDBOX_MERCHANT_ID_PATH)
                && Mage::getStoreConfig(Gene_Braintree_Model_Wrapper_Braintree::BRAINTREE_SANDBOX_PUBLIC_KEY_PATH)
                && Mage::getStoreConfig(Gene_Braintree_Model_Wrapper_Braintree::BRAINTREE_SANDBOX_PRIVATE_KEY_PATH);
        }

        return false;
    }

    /**
     * Store a payment method nonce in the vault
     *
     * @param $nonce
     * @param $billingAddress
     *
     * @return bool
     */
    public function storeInVault($nonce, $billingAddress = false)
    {
        // Create the payment method with this data
        $paymentMethodCreate = array(
            'paymentMethodNonce' => $nonce,
            'options'            => array(
                'verifyCard'                    => true,
                'verificationMerchantAccountId' => $this->getMerchantAccountId()
            )
        );

        if ($customerId = $this->getBraintreeId()) {
            $paymentMethodCreate['customerId'] = $this->getBraintreeId();
        }

        // Include billing address information into the payment method
        if ($billingAddress) {
            // Add in the billing address
            $paymentMethodCreate['billingAddress'] = $billingAddress;

            // Pass over some extra details from the billing address
            $paymentMethodCreate['cardholderName'] = $billingAddress['firstName'] . ' ' . $billingAddress['lastName'];
        }

        // Dispatch an event to allow modification of the store in vault
        $object = new Varien_Object();
        $object->setAttributes($paymentMethodCreate);
        Mage::dispatchEvent('gene_braintree_store_in_vault', array('object' => $object));
        $paymentMethodCreate = $object->getAttributes();

        // Create a new billing method
        return Braintree_PaymentMethod::create($paymentMethodCreate);
    }

    /**
     * If the customer is not logged in, but we still need to vault, we're going to create a fake customer
     *
     * @param $nonce
     * @param $billingAddress
     *
     * @return \Braintree_Customer
     */
    public function storeInGuestVault($nonce, $billingAddress = false)
    {
        $guestCustomerCreate = array(
            'id'         => $this->getBraintreeId(),
            'creditCard' => array(
                'paymentMethodNonce' => $nonce,
                'options'            => array(
                    'verifyCard'                    => true,
                    'verificationMerchantAccountId' => $this->getMerchantAccountId()
                )
            )
        );

        // Include billing address information into the customer
        if ($billingAddress) {
            // Add in the billing address
            $guestCustomerCreate['creditCard']['cardholderName'] = $billingAddress['firstName'] . ' ' . $billingAddress['lastName'];
            $guestCustomerCreate['creditCard']['billingAddress'] = $billingAddress;

            // Make sure the customer is created with a first name and last name
            $guestCustomerCreate['firstName'] = $billingAddress['firstName'];
            $guestCustomerCreate['lastName'] = $billingAddress['lastName'];

            // Conditionally copy over these fields
            if (isset($billingAddress['email']) && !empty($billingAddress['email'])) {
                $guestCustomerCreate['email'] = $billingAddress['email'];
            }
            if (isset($billingAddress['company']) && !empty($billingAddress['company'])) {
                $guestCustomerCreate['company'] = $billingAddress['company'];
            }
            if (isset($billingAddress['phone']) && !empty($billingAddress['phone'])) {
                $guestCustomerCreate['phone'] = $billingAddress['phone'];
            }
        }

        // Dispatch an event to allow modification of the store in vault
        $object = new Varien_Object();
        $object->setAttributes($guestCustomerCreate);
        Mage::dispatchEvent('gene_braintree_store_in_guest_vault', array('object' => $object));
        $guestCustomerCreate = $object->getAttributes();

        return Braintree_Customer::create($guestCustomerCreate);
    }

    /**
     * Clean up any accounts or payment methods that were created temporarily
     *
     * @return boolean
     */
    public static function cleanUp()
    {
        Mage::dispatchEvent('gene_braintree_cleanup');

        // If a guest customer was created during the checkout we can remove them now
        if ($guestCustomerId = Mage::getSingleton('checkout/session')->getGuestBraintreeCustomerId()) {
            $wrapper = Mage::getSingleton('gene_braintree/wrapper_braintree');
            $wrapper->init()->deleteCustomer($guestCustomerId);
            Mage::getSingleton('checkout/session')->unsGuestBraintreeCustomerId();
            Mage::getSingleton('checkout/session')->unsGuestPaymentToken();
        }

        // Remove the temporary payment method
        if ($token = Mage::getSingleton('checkout/session')->getTemporaryPaymentToken()) {
            $wrapper = Mage::getSingleton('gene_braintree/wrapper_braintree');
            $wrapper->init()->deletePaymentMethod($token);
            Mage::getSingleton('checkout/session')->unsTemporaryPaymentToken();
        }

        Mage::getSingleton('checkout/session')->unsVaultedNonce();

        return false;
    }

    /**
     * Build up the sale request
     *
     * @param                        $amount
     * @param array                  $paymentDataArray
     * @param Mage_Sales_Model_Order $order
     * @param bool                   $submitForSettlement
     * @param bool                   $deviceData
     * @param bool                   $storeInVault
     * @param bool                   $threeDSecure
     * @param array                  $extra
     *
     * @return array
     *
     * @throws Mage_Core_Exception
     */
    public function buildSale(
        $amount,
        array $paymentDataArray,
        Mage_Sales_Model_Order $order,
        $submitForSettlement = true,
        $deviceData = false,
        $storeInVault = false,
        $threeDSecure = false,
        $extra = array()
    ) {
        // Check we always have an ID
        if (!$order->getIncrementId()) {
            Mage::throwException('Your order has become invalid, please try refreshing.');
        }

        // Store whether or not we created a new method
        $createdMethod = false;

        // Are we storing in the vault, from a guest customer account?
        if ($storeInVault && Mage::getSingleton('checkout/session')->getGuestBraintreeCustomerId() &&
            ($token = Mage::getSingleton('checkout/session')->getGuestPaymentToken())
        ) {
            if ($this->checkPaymentMethod($token)) {
                // Remove this from the session so it doesn't get deleted at the end of checkout
                Mage::getSingleton('checkout/session')->unsGuestBraintreeCustomerId();
                Mage::getSingleton('checkout/session')->unsGuestPaymentToken();

                // We no longer need this nonce
                unset($paymentDataArray['paymentMethodNonce']);

                // Instead use the token
                $paymentDataArray['paymentMethodToken'] = $token;

                // Create a flag for other methods
                $createdMethod = true;
            } else {
                // If the method doesn't exist, clear the token and re-build the sale
                Mage::getSingleton('checkout/session')->unsGuestPaymentToken();

                return $this->buildSale(
                    $amount,
                    $paymentDataArray,
                    $order,
                    $submitForSettlement,
                    $deviceData,
                    $storeInVault,
                    $threeDSecure,
                    $extra
                );
            }
        } elseif ($storeInVault && $this->checkIsCustomer() && isset($paymentDataArray['paymentMethodNonce'])) {
            // If the user is already a customer and wants to store in the vault we've gotta do something a bit special
            // Do we already have a saved token in the session?
            if ($token = Mage::getSingleton('checkout/session')->getTemporaryPaymentToken()) {
                if ($this->checkPaymentMethod($token)) {
                    // Is the submitted nonce different?
                    if (Mage::getSingleton('checkout/session')->getVaultedNonce() == $paymentDataArray['paymentMethodNonce']) {
                        // Remove this from the session so it doesn't get deleted at the end of checkout
                        Mage::getSingleton('checkout/session')->unsTemporaryPaymentToken();

                        // We no longer need this nonce
                        unset($paymentDataArray['paymentMethodNonce']);

                        // Instead use the token
                        $paymentDataArray['paymentMethodToken'] = $token;

                        // Create a flag for other methods
                        $createdMethod = true;

                    } else {
                        // Store it again with the 3Ds information
                        $storeInVault = true;
                    }

                } else {
                    // If the method doesn't exist, clear the token and re-build the sale
                    Mage::getSingleton('checkout/session')->unsTemporaryPaymentToken();

                    return $this->buildSale($amount, $paymentDataArray, $order, $submitForSettlement, $deviceData, $storeInVault, $threeDSecure, $extra);
                }

            } else {
                // Create the payment method with this data
                $paymentMethodCreate = array(
                    'customerId'         => $this->getBraintreeId(),
                    'paymentMethodNonce' => $paymentDataArray['paymentMethodNonce'],
                    'billingAddress'     => $this->buildAddress($order->getBillingAddress())
                );

                // Log the create array
                Gene_Braintree_Model_Debug::log(array('Braintree_PaymentMethod' => $paymentMethodCreate));

                // Create a new billing method
                $result = Braintree_PaymentMethod::create($paymentMethodCreate);

                // Log the response from Braintree
                Gene_Braintree_Model_Debug::log(array('Braintree_PaymentMethod:result' => $result));

                // Verify the storing of the card was a success
                if (isset($result->success) && $result->success == true) {
                    /* @var $paymentMethod Braintree_CreditCard */
                    $paymentMethod = $result->paymentMethod;
                    // Check to see if the token is set
                    if (isset($paymentMethod->token) && !empty($paymentMethod->token)) {
                        // We no longer need this nonce
                        unset($paymentDataArray['paymentMethodNonce']);

                        // Instead use the token
                        $paymentDataArray['paymentMethodToken'] = $paymentMethod->token;

                        // Create a flag for other methods
                        $createdMethod = true;
                    }
                } else {
                    Mage::throwException(Mage::helper('gene_braintree')->__('%s Please try again or attempt refreshing the page.', $result->message));
                }
            }
        }

        // Pass the version through in the channel parameter
        $channel = 'GeneVZero_' . Mage::getConfig()->getModuleConfig('Gene_Braintree')->version;

        // Build up the initial request parameters
        $request = array(
            'amount'            => $amount,
            'orderId'           => $order->getIncrementId(),
            'merchantAccountId' => $this->getMerchantAccountId($order),
            'channel'           => $channel,
            'options'           => array(
                'submitForSettlement' => $submitForSettlement,
                'storeInVault'        => $storeInVault
            )
        );

        // Input the allowed payment method info
        $allowedPaymentInfo = array('paymentMethodNonce', 'paymentMethodToken', 'token', 'cvv');
        foreach ($paymentDataArray as $key => $value) {
            if (in_array($key, $allowedPaymentInfo)) {
                if ($key == 'cvv') {
                    $request['creditCard']['cvv'] = $value;
                } else {
                    $request[$key] = $value;
                }
            } else {
                Mage::throwException($key . ' is not allowed within $paymentDataArray');
            }
        }

        // Include the customer if we're creating a new one
        if (!$this->checkIsCustomer() && (Mage::getSingleton('customer/session')->isLoggedIn() ||
                (Mage::getSingleton('checkout/type_onepage')->getCheckoutMethod() == 'login_in' || Mage::getSingleton('checkout/type_onepage')->getCheckoutMethod() == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER))
        ) {
            $request['customer'] = $this->buildCustomer($order);
        } elseif (!$this->checkIsCustomer() && Mage::app()->getStore()->isAdmin() && $storeInVault) {
            // Do we need to build a customer account from an admin request?
            $request['customer'] = $this->buildCustomer($order);
        } else {
            // If the customer exists but we aren't using the vault we want to pass a customer object with no ID
            $request['customer'] = $this->buildCustomer($order, false);
        }

        // Do we have any deviceData to send over?
        if ($deviceData) {
            $request['deviceData'] = $deviceData;
        }

        // Include the shipping address
        if ($order->getShippingAddress()) {
            $request['shipping'] = $this->buildAddress($order->getShippingAddress());
        }

        // Include the billing address
        if ($order->getBillingAddress()) {
            $request['billing'] = $this->buildAddress($order->getBillingAddress());
        }

        // Is 3D secure enabled?
        if ($threeDSecure !== false && !$createdMethod) {
            $request['options']['threeDSecure']['required'] = true;
        }

        // Include level 2 data if the user has provided a VAT ID
        if ($order->getBillingAddress()->getVatId()) {
            $request['taxAmount'] = Mage::helper('gene_braintree')->formatPrice($order->getTaxAmount());
            $request['taxExempt'] = true;
            $request['purchaseOrderNumber'] = $order->getIncrementId();
        }

        // If the order is being created in the admin, set the source as moto
        if (Mage::app()->getStore()->isAdmin()) {
            $request['transactionSource'] = 'moto';
        }

        // Any extra information we want to supply
        if (!empty($extra) && is_array($extra)) {
            $request = array_merge($request, $extra);
        }

        return $request;
    }

    /**
     * Check whether a payment method exists
     *
     * @param $token
     *
     * @return bool
     */
    public function checkPaymentMethod($token)
    {
        try {
            // Attempt to load the temporary payment method
            $paymentMethod = Braintree_PaymentMethod::find($token);
            if (isset($paymentMethod->token) && $paymentMethod->token == $token) {
                return true;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Attempt to make a sale using the Braintree PHP SDK
     *
     * @param $saleArray
     *
     * @return stdClass
     */
    public function makeSale($saleArray)
    {
        // Call the braintree library
        return Braintree_Transaction::sale(
            $saleArray
        );
    }

    /**
     * Submit a payment for settlement using the Braintree PHP SDK
     *
     * @param $transactionId
     * @param $amount
     *
     * @return object
     */
    public function submitForSettlement($transactionId, $amount)
    {
        // Attempt to submit for settlement
        $result = Braintree_Transaction::submitForSettlement($transactionId, $amount);

        return $result;
    }

    /**
     * Submit a payment for partial settlement using the Braintree PHP SDK
     *
     * @param $transactionId
     * @param $amount
     *
     * @return object
     */
    public function submitForPartialSettlement($transactionId, $amount)
    {
        // Attempt to submit for settlement
        $result = Braintree_Transaction::submitForPartialSettlement($transactionId, $amount);

        return $result;
    }

    /**
     * Build up the customer ID, a unique MD5 hash if guest - otherwise magento ID
     *
     * @return string
     */
    private function buildCustomerId()
    {
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            if ($customer && $customer->getId()) {
                return $customer->getId();
            }
        }
        return hash('md5', uniqid('braintree_', true));
    }

    /**
     * Convert the billing address into something Braintree can understand
     *
     * @param $address
     *
     * @return array
     */
    public function convertBillingAddress($address)
    {
        if ($address instanceof Mage_Sales_Model_Order_Address || $address instanceof Mage_Sales_Model_Quote_Address || $address instanceof Mage_Customer_Model_Address) {
            return $this->buildAddress($address);
        }

        // Otherwise we're most likely dealing with a raw request
        if (is_array($address)) {

            // Is the user using a saved address?
            if ($addressId = Mage::app()->getRequest()->getParam('billing_address_id', false)) {
                $savedAddress = Mage::getModel('customer/address')->load($addressId);
                if ($savedAddress->getId()) {
                    return $this->buildAddress($savedAddress);
                }
            }

            // Utilise built in functionality to
            $addressObject = Mage::getModel('sales/quote_address');
            $addressForm = Mage::getModel('customer/form');
            $addressForm->setFormCode('customer_address_edit')
                ->setEntityType('customer_address')
                ->setIsAjaxRequest(Mage::app()->getRequest()->isAjax());
            $addressForm->setEntity($addressObject);

            // Emulate request object
            $addressData = $addressForm->extractData($addressForm->prepareRequest($address));
            $addressObject->addData($addressData);

            return $this->buildAddress($addressObject);
        }

        return array();
    }

    /**
     * Build a Magento address model into a Braintree array
     *
     * @param Mage_Sales_Model_Order_Address|Mage_Sales_Model_Quote_Address|Mage_Customer_Model_Address $address
     *
     * @return array
     */
    private function buildAddress($address)
    {
        return Mage::helper('gene_braintree')->convertToBraintreeAddress($address);
    }

    /**
     * Return the correct merchant account ID for the order
     *
     * @param \Mage_Sales_Model_Order|null $order
     *
     * @return bool|mixed
     */
    public function getMerchantAccountId(Mage_Sales_Model_Order $order = null)
    {
        // If multi-currency is enabled use the mapped merchant account ID
        if ($currencyCode = $this->hasMappedCurrencyCode($order)) {

            // Return the mapped currency code
            return $currencyCode;
        }

        $environment = Mage::getStoreConfig(self::BRAINTREE_ENVIRONMENT_PATH, ($order ? $order->getStoreId() : null));
        if ($environment == Gene_Braintree_Model_Source_Environment::PRODUCTION) {
            $merchantAccountIdPath = self::BRAINTREE_MERCHANT_ACCOUNT_ID_PATH;
        } else if ($environment == Gene_Braintree_Model_Source_Environment::SANDBOX) {
            $merchantAccountIdPath = self::BRAINTREE_SANDBOX_MERCHANT_ACCOUNT_ID_PATH;
        } else {
            return false;
        }

        // Otherwise return the one from the store
        return Mage::getStoreConfig($merchantAccountIdPath, ($order ? $order->getStoreId() : null));
    }

    /**
     * Does the order have a mapped currency code?
     *
     * @param \Mage_Sales_Model_Order|null $order
     *
     * @return bool|string
     */
    public function hasMappedCurrencyCode(Mage_Sales_Model_Order $order = null)
    {
        // If multi-currency is enabled use the mapped merchant account ID
        if ($this->currencyMappingEnabled($order)) {

            // Retrieve the mapping from the config
            $mapping = Mage::helper('core')->jsonDecode(Mage::getStoreConfig(self::BRAINTREE_MULTI_CURRENCY_MAPPING, ($order ? $order->getStoreId() : false)));

            // Verify it decoded correctly
            if (is_array($mapping) && !empty($mapping)) {

                // If we don't have an order but have a selected currency code use that
                if (!$order && Mage::app()->getStore()->getCurrentCurrencyCode()) {
                    // Use the current set current code
                    $currency = Mage::app()->getStore()->getCurrentCurrencyCode();
                } elseif (!$order && $this->getQuote()->getQuoteCurrencyCode()) {
                    // If we haven't been given an order use the quote currency code
                    $currency = $this->getQuote()->getQuoteCurrencyCode();
                } else {
                    // Use the currency code for tomorrow
                    $currency = $order->getOrderCurrencyCode();
                }

                // Verify we have a mapping value for this currency
                if (isset($mapping[$currency]) && !empty($mapping[$currency])) {

                    // These should never have spaces in so make sure we trim it
                    return trim($mapping[$currency]);
                }
            }
        }

        return false;
    }

    /**
     * Determine whether or not currency mapping is enabled
     *
     * @param \Mage_Sales_Model_Order|null $order
     *
     * @return bool
     */
    public function currencyMappingEnabled(Mage_Sales_Model_Order $order = null)
    {
        return Mage::getStoreConfigFlag(self::BRAINTREE_MULTI_CURRENCY)
            && Mage::getStoreConfig(self::BRAINTREE_MULTI_CURRENCY_MAPPING);
    }

    /**
     * Get the current quote
     *
     * @return \Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        // If we're within the admin return the admin quote
        if (Mage::app()->getStore()->isAdmin()) {
            return Mage::getSingleton('adminhtml/session_quote')->getQuote();
        }

        return Mage::helper('checkout')->getQuote();
    }

    /**
     * If we have a mapped currency code we need to convert the currency
     *
     * @param \Mage_Sales_Model_Order $order
     * @param                         $amount
     *
     * @return string
     * @throws \Zend_Currency_Exception
     */
    public function getCaptureAmount(Mage_Sales_Model_Order $order = null, $amount)
    {
        // If we've got a mapped currency code the amount is going to change
        if ($this->hasMappedCurrencyCode($order)) {

            // If we don't have an order yet get the quote capture amount
            if ($order === null) {
                return $this->convertCaptureAmount($this->getQuote()->getBaseCurrencyCode(), $this->getQuote()->getQuoteCurrencyCode(), $amount);
            }

            // Convert the capture amount
            return $this->convertCaptureAmount($order->getBaseCurrencyCode(), $order->getOrderCurrencyCode(), $amount);
        }

        // Always make sure the number has two decimal places
        return Mage::helper('gene_braintree')->formatPrice($amount);
    }

    /**
     * @param $amount
     *
     * @return string
     * @throws \Zend_Currency_Exception
     */
    public function convertCaptureAmount($baseCurrencyCode, $orderQuoteCurrencyCode, $amount)
    {
        // Convert the current
        $convertedCurrency = Mage::helper('directory')->currencyConvert($amount, $baseCurrencyCode, $orderQuoteCurrencyCode);

        // Always make sure the number has two decimal places
        return Mage::helper('gene_braintree')->formatPrice($convertedCurrency);
    }

    /**
     * Build up the customers data onto an object
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return array
     */
    private function buildCustomer(Mage_Sales_Model_Order $order, $includeId = true)
    {
        $customer = array(
            'firstName' => $order->getCustomerFirstname(),
            'lastName'  => $order->getCustomerLastname(),
            'email'     => $order->getCustomerEmail(),
            'phone'     => $order->getBillingAddress()->getTelephone()
        );

        // Shall we include the customer ID?
        if ($includeId) {
            $customer['id'] = $this->getBraintreeId();
        }

        // Handle empty data with alternatives
        if (empty($customer['firstName'])) {
            $customer['firstName'] = $order->getBillingAddress()->getFirstname();
        }
        if (empty($customer['lastName'])) {
            $customer['lastName'] = $order->getBillingAddress()->getLastname();
        }
        if (empty($customer['email'])) {
            $customer['email'] = $order->getBillingAddress()->getEmail();
        }

        return $customer;
    }

    /**
     * Delete a customer within Braintree
     *
     * @param $customerId
     *
     * @return \Braintree_Result_Successful
     */
    public function deleteCustomer($customerId)
    {
        try {
            return Braintree_Customer::delete($customerId);
        } catch (Exception $e) {
            Gene_Braintree_Model_Debug::log($e);
        }

        return false;
    }

    /**
     * Delete a payment method within Braintree
     *
     * @param $token
     *
     * @return bool|\Braintree_Result_Successful
     */
    public function deletePaymentMethod($token)
    {
        try {
            return Braintree_PaymentMethod::delete($token);
        } catch (Exception $e) {
            Gene_Braintree_Model_Debug::log($e);
        }

        return false;
    }

    /**
     * Clone a transaction
     *
     * @param $transactionId
     * @param $amount
     *
     * @return bool|mixed
     */
    public function cloneTransaction($transactionId, $amount, $submitForSettlement = true)
    {
        // Attempt to clone the transaction
        try {
            $result = Braintree_Transaction::cloneTransaction($transactionId, array(
                'amount'  => $amount,
                'options' => array(
                    'submitForSettlement' => $submitForSettlement
                )
            ));

            return $result;

        } catch (Exception $e) {

            // Log the issue
            Gene_Braintree_Model_Debug::log(array('cloneTransaction' => $e));

            return false;
        }
    }

    /**
     * Parse Braintree errors as a string
     *
     * @param $braintreeErrors
     *
     * @return string
     */
    public function parseErrors($braintreeErrors)
    {
        $errors = array();
        foreach ($braintreeErrors as $error) {
            $errors[] = $error->code . ': ' . $this->parseMessage($error->message);
        }

        return implode(', ', $errors);
    }

    /**
     * Replace the word nonce with token as it could offend some of us british people
     *
     * @param $string
     *
     * @return mixed
     */
    public function parseMessage($string)
    {
        return str_replace('nonce', 'token', $string);
    }

    /**
     * Update the API status in the config
     *
     * @param $status
     * @param $storeId
     *
     * @return mixed
     */
    protected function _updateApiStatus($status, $storeId = 0)
    {
        $apiStatus = Mage::getModel('core/variable')->setStoreId($storeId)->loadByCode(self::BRAINTREE_API_CONFIG_STATUS);
        if ($apiStatus->getId()) {
            $apiStatus->setPlainValue($status)->save();
        } else {
            Mage::getModel('core/variable')->setData(array(
                'code'        => self::BRAINTREE_API_CONFIG_STATUS,
                'name'        => self::BRAINTREE_API_CONFIG_STATUS,
                'store_id'    => $storeId,
                'plain_value' => $status
            ))->save();
        }

        return $status;
    }

    /**
     * Get the API status from the core variable system
     *
     * @param bool|false $storeId
     *
     * @return bool|string
     * @throws \Exception
     */
    protected function _getApiStatus($storeId = false)
    {
        if ($storeId === false) {
            $storeId = Mage::app()->getStore()->getId();
        }
        $apiStatus = Mage::getModel('core/variable')->setStoreId($storeId)->loadByCode(self::BRAINTREE_API_CONFIG_STATUS)->getValue('text');
        if (!$apiStatus) {
            return $this->validateCredentials();
        }

        return (bool)$apiStatus;
    }
}
