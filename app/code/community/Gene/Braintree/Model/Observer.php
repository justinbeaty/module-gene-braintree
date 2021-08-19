<?php

/**
 * Class Gene_Braintree_Model_Observer
 */
class Gene_Braintree_Model_Observer
{
    /**
     * Update each invoice with its corresponding transaction ids
     *
     * @param Varien_Event_Observer $observer Observer
     *
     * @return Varien_Event_Observer
     */
    public function updateInvoiceTransactionId(Varien_Event_Observer $observer)
    {
        try {
            $invoice = $observer->getEvent()->getInvoice();
            $method = $invoice->getOrder()->getPayment()->getMethodInstance()->getCode();

            if ($method != 'gene_braintree_paypal') {
                return $observer;
            }

            if (Mage::app()->getRequest()->getRequestedControllerName() == "sales_order_creditmemo") {
                return $observer;
            }

            if (Mage::app()->getRequest()->getRequestedControllerName() == "sales_order_invoice"
                && Mage::app()->getRequest()->getActionName() == "view") {
                return $observer;
            }

            $allLastInvoiceTransIds = explode(
                ",",
                $invoice->getOrder()->getPayment()
                    ->getAdditionalInformation()['last_invoice_trans_id']
            );
            $latestInvoiceTransIdKey = max(array_keys($allLastInvoiceTransIds));
            $latestInvoiceTransId = explode(
                "-",
                $allLastInvoiceTransIds[$latestInvoiceTransIdKey]
            );

            $invoice->setData('transaction_id', $latestInvoiceTransId[1]);
        } catch (\Exception $e) {
            Gene_Braintree_Model_Debug::log('updateInvoiceTransactionId failed ' . $e->getMessage());
        }

        return $observer;

    }

    /**
     * Detect which checkout is in use and add a new layout handle
     *
     * @param Varien_Event_Observer $observer
     *
     * @return $this
     */
    public function addLayoutHandle(Varien_Event_Observer $observer)
    {
        /* @var $action Mage_Core_Controller_Varien_Action */
        $action = $observer->getAction();

        /* @var $layout Mage_Core_Model_Layout */
        $layout = $observer->getLayout();

        // We only want to run this action on the checkout
        if($action->getFullActionName() == 'checkout_onepage_index') {

            // Attempt to detect Amasty_Scheckout
            if (Mage::helper('core')->isModuleEnabled('Amasty_Scheckout')) {
                $layout->getUpdate()->addHandle('amasty_onestep_checkout');
            }

            // Attempt to detect Unicode OP Checkout
            if (Mage::helper('core')->isModuleEnabled('Uni_Opcheckout') && Mage::helper('opcheckout')->isActive()) {
                $layout->getUpdate()->addHandle('unicode_onestep_checkout');
            }

            // Detect the Oye one step checkout
            if(Mage::helper('core')->isModuleEnabled('Oye_Checkout') && Mage::helper('oyecheckout')->isOneStepLayout()) {
                $layout->getUpdate()->addHandle('oye_onestep_checkout');
            }
        }

        // As some 3rd party checkouts use the same handles, and URL we have to dynamically add new handles
        if($action->getFullActionName() == 'onestepcheckout_index_index') {

            // Attempt to detect Magestore_Onestepcheckout
            if (Mage::helper('core')->isModuleEnabled('Magestore_Onestepcheckout')) {
                if(Mage::helper('onestepcheckout')->enabledOnestepcheckout()) {
                    $layout->getUpdate()->addHandle('magestore_onestepcheckout_index');
                }
            }

            // Attempt to detect Idev_OneStepCheckout
            if (Mage::helper('core')->isModuleEnabled('Idev_OneStepCheckout')) {
                $layout->getUpdate()->addHandle('idev_onestepcheckout_index');
            }
        }

        return $this;
    }

    /**
     * Store the generated customer ID if it's present in the session
     *
     * @param \Varien_Event_Observer $observer
     *
     * @return $this
     */
    public function completeCheckout(Varien_Event_Observer $observer)
    {
        // Do we have a customer ID within the session?
        if(Mage::getSingleton('checkout/session')->getBraintreeCustomerId() &&
            Mage::getSingleton('checkout/type_onepage')->getCheckoutMethod() == Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER) {

            // Get the customer
            $customer = Mage::getSingleton('customer/session')->getCustomer();

            // Save the Braintree customer ID
            $customer->setBraintreeCustomerId(Mage::getSingleton('checkout/session')->getBraintreeCustomerId())->save();
        }

        // Perform any cleaning up required
        Gene_Braintree_Model_Wrapper_Braintree::cleanUp();

        // Unset the ID from the session
        Mage::getSingleton('checkout/session')->unsetData('braintree_customer_id');

        return $this;
    }

    /**
     * Capture payment on shipment if set
     *
     * @param Varien_Event_Observer $observer
     *
     * @return $this
     */
    public function captureBraintreePayment(Varien_Event_Observer $observer)
    {
        /* @var $shipment Mage_Sales_Model_Order_Shipment */
        $shipment = $observer->getEvent()->getShipment();

        /* @var $order Mage_Sales_Model_Order */
        $order = $shipment->getOrder();

        // Should we capture the payment in shipment?
        if ($this->_shouldCaptureShipment($order)) {
            // Check the order can be invoiced
            if ($shipment->getTotalQty() && $order->canInvoice()) {
                $invoiceItems = array();
                /* @var $item Mage_Sales_Model_Order_Shipment_Item */
                foreach ($shipment->getAllItems() as $item) {
                    $invoiceItems[$item->getOrderItemId()] = $item->getQty();
                }
                foreach ($order->getAllVisibleItems() as $item) {
                    if (!isset($invoiceItems[$item->getId()])) {
                        $invoiceItems[$item->getId()] = 0;
                    }
                }

                /* @var $invoice Mage_Sales_Model_Order_Invoice */
                $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice($invoiceItems);

                // Set the requested capture case
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                $invoice->register();

                // Save the transaction
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());

                // Save the transaction
                $transactionSave->save();
            }
        }

        return $this;
    }

    /**
     * Add in the saved block
     *
     * @param \Varien_Event_Observer $observer
     */
    public function addSavedChild(Varien_Event_Observer $observer)
    {
        $block = $observer->getEvent()->getBlock();

        // Add the child block of saved to the credit card form
        if ($block instanceof Gene_Braintree_Block_Creditcard) {
            $saved = $block->getLayout()->createBlock('gene_braintree/creditcard_saved');
            $saved->setMethod($block->getMethod());
            $block->setChild('saved', $saved);
        }

        // Add the child block of saved to the PayPal payment method form
        if ($block instanceof Gene_Braintree_Block_Paypal) {
            $saved = $block->getLayout()->createBlock('gene_braintree/paypal_saved');
            $saved->setMethod($block->getMethod());
            $block->setChild('saved', $saved);
        }
    }

    /**
     * Should we capture the payment?
     *
     * @param \Mage_Sales_Model_Order $order
     *
     * @return bool
     */
    protected function _shouldCaptureShipment($order)
    {
        // Check the store configuration settings are set to capture shipment
        if(Mage::getStoreConfig(Gene_Braintree_Model_Source_Creditcard_PaymentAction::PAYMENT_ACTION_XML_PATH, $order->getStoreId()) == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE
            && Mage::getStoreConfig(Gene_Braintree_Model_Source_Creditcard_CaptureAction::CAPTURE_ACTION_XML_PATH, $order->getStoreId()) == Gene_Braintree_Model_Source_Creditcard_CaptureAction::CAPTURE_SHIPMENT)
        {
            return true;
        }
        return false;
    }

    /**
     * Check if compilation is enabled, if so copy over the certificates
     *
     * @return $this
     */
    public function checkCompilation()
    {
        // Determine whether the compiler has been enabled
        if(defined('COMPILER_INCLUDE_PATH')) {
            $certificates = array('api_braintreegateway_com.ca.crt');
            $compilerPath = COMPILER_INCLUDE_PATH;
            $directory = 'Braintree' . DS . 'braintree' . DS . 'braintree_php' . DS . 'lib' . DS . 'ssl' . DS;

            // Verify the SSL folder exists
            if(!is_dir($compilerPath . DS . '..' . DS . $directory)) {
                mkdir($compilerPath . DS . '..' . DS . $directory, 0777, true);
            }

            // Loop through each certificate and check whether it's in the includes directory, if not copy it!
            foreach($certificates as $file) {
                if(!file_exists($compilerPath . DS . '..' . DS . $directory . $file)) {
                    copy(
                        Mage::getBaseDir('lib') . DS . 'Gene' . DS . $directory . $file,
                        $compilerPath . DS . '..' . DS . $directory . $file
                    );
                }
            }
        }

        return $this;
    }

    /**
     * Unregister the original token from the request
     *
     * @return $this
     */
    public function resetMultishipping()
    {
        Mage::unregister(Gene_Braintree_Model_Paymentmethod_Abstract::BRAINTREE_ORIGINAL_TOKEN);

        return $this;
    }

    /**
     * Handle multi shipping orders
     *
     * @param \Varien_Event_Observer $observer
     *
     * @return $this
     */
    public function handleMultishipping(Varien_Event_Observer $observer)
    {
        /* @var $order Mage_Sales_Model_Order */
        $order = $observer->getEvent()->getOrder();

        // Let the payment method know the transaction is a multi shipping transaction
        // Braintree don't allow multiple transactions from one authorization, however they do allow the vaulting
        // of the initial transaction, then using the token from that transaction to take repeat payments.
        // Due to this the payment method needs to be aware if it's expecting to take multiple transactions from one
        // authorization.
        $order->getPayment()->setMultiShipping(true);

        return $this;
    }

    /**
     * Add the include path to the Gene/Braintree library folder
     *
     * @return $this
     */
    public function addIncludePath()
    {
        self::initIncludePath();

        return $this;
    }

    /**
     * Add the include path needed for the new location of the SDK
     */
    public static function initIncludePath()
    {
        require_once Mage::getBaseDir('lib') . DS . 'Gene' . DS . 'Braintree' . DS . 'braintree' . DS . 'braintree_php' . DS . 'vendor' . DS . 'autoload.php';
    }
}
