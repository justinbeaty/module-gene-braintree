<?php

/**
 * Class Gene_Braintree_Model_Kount_Ens
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Kount_Ens extends Mage_Core_Model_Abstract
{
    const RESPONSE_DECLINE = 'D';
    const RESPONSE_APPROVE = 'A';
    const RESPONSE_REVIEW = 'R';
    const RESPONSE_ESCALATE = 'E';

    /**
     * Process an event
     *
     * @param $event
     *
     * @return bool
     */
    public function processEvent($event)
    {
        switch ($event['name']) {
            case 'WORKFLOW_STATUS_EDIT':
                return $this->_workflowStatusEdit($event);
                break;
        }

        // If we don't support the event, assume it was a success
        return true;
    }

    /**
     * Event handler for a workflow status edit
     *
     * @param $event
     *
     * @return bool
     */
    protected function _workflowStatusEdit($event)
    {
        if (($incrementId = $this->_getOrderIncrementId($event))
            && ($kountTransactionId = $this->_getKountTransactionId($event)))
        {
            $order = Mage::getModel('sales/order')->load($incrementId, 'increment_id');
            if ($order->getId()) {
                $payment = $order->getPayment();

                // Ensure we're modifying the order with the same Kount transaction ID
                if ($payment->getAdditionalInformation('kount_id') == $kountTransactionId) {

                    // Was the previous status review or escalate?
                    if ($event['old_value'] == self::RESPONSE_REVIEW || $event['old_value'] == self::RESPONSE_ESCALATE) {

                        // Is the new value approve or decline?
                        if ($event['new_value'] == self::RESPONSE_APPROVE) {
                            return $this->_approveOrder($order);
                        } else if ($event['new_value'] == self::RESPONSE_DECLINE) {
                            return $this->_declineOrder($order);
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Approve an order from Kount
     *
     * @param \Mage_Sales_Model_Order $order
     *
     * @return bool
     */
    protected function _approveOrder(Mage_Sales_Model_Order $order)
    {
        // Ensure the status has not moved from it's payment review state
        if ($order->getStatus() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW) {

            // Inform the system that this update is occurring from an ENS update
            Mage::register('kount_ens_update', true);

            $captured = 0;
            /* @var $invoice Mage_Sales_Model_Order_Invoice */
            foreach ($order->getInvoiceCollection() as $invoice) {

                try {
                    // Is the invoice pending?
                    if ($invoice->canCapture()) {

                        // The Braintree module won't attempt to capture money twice  if it's settling etc
                        $invoice->capture();
                        $invoice->getOrder()->setIsInProcess(true);
                        Mage::getModel('core/resource_transaction')
                            ->addObject($invoice)
                            ->addObject($invoice->getOrder())
                            ->save();

                        ++$captured;
                    }
                } catch (Exception $e) {
                    Gene_Braintree_Model_Debug::log('Approved Kount transaction failed to be captured in Magento: ' . $e->getMessage());
                    Gene_Braintree_Model_Debug::log($e);
                    return false;
                }

            }

            Mage::unregister('kount_ens_update');

            // The operation was only a success if one or more invoices are captured
            if ($captured > 0) {
                $order->addStatusHistoryComment('Order approved through Kount, pending invoice(s) captured.')->save();
                return true;
            }

        }

        return false;
    }

    /**
     * Decline an order in Magento
     *
     * If the payment is only voidable, we void the invoice cancelling the order. If the payment has settled we create
     * a credit memo and close the order that way.
     *
     * @param \Mage_Sales_Model_Order $order
     *
     * @return bool
     */
    protected function _declineOrder(Mage_Sales_Model_Order $order)
    {
        // Ensure the status has not moved from it's payment review state
        if ($order->getStatus() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW) {

            // Inform the system that this update is occurring from an ENS update
            Mage::register('kount_ens_update', true);

            // Retrieve the Braintree ID
            $braintreeId = $order->getPayment()->getCcTransId();

            try {
                /* @var $wrapper Gene_Braintree_Model_Wrapper_Braintree */
                $wrapper = Mage::getModel('gene_braintree/wrapper_braintree');
                $wrapper->init($order->getStoreId());

                $transaction = Braintree_Transaction::find($braintreeId);
                if ($transaction->id) {

                    // If the transaction is yet to settle we can void the transaction in Braintree
                    if ($transaction->status == Braintree_Transaction::AUTHORIZED || $transaction->status == Braintree_Transaction::SUBMITTED_FOR_SETTLEMENT) {
                        return $this->_voidOrder($order);
                    } else if ($transaction->status == Braintree_Transaction::SETTLED) {
                        return $this->_refundOrder($order);
                    }
                }
            } catch (Exception $e) {
                Gene_Braintree_Model_Debug::log('Declined Kount transaction failed to be declined in Magento: ' . $e->getMessage());
                Gene_Braintree_Model_Debug::log($e);
                return false;
            }

        }

        return false;
    }

    /**
     * Void an order
     *
     * @param \Mage_Sales_Model_Order $order
     *
     * @return bool
     */
    protected function _voidOrder(Mage_Sales_Model_Order $order)
    {
        // Void transaction
        $voided = 0;

        /* @var $invoice Mage_Sales_Model_Order_Invoice */
        foreach ($order->getInvoiceCollection() as $invoice) {

            try {
                // Void and cancel the invoice, voiding forces the authorization to be dropped
                $invoice->void();

                Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();

            } catch (Exception $e) {
                Gene_Braintree_Model_Debug::log('Declined Kount transaction failed to be voided in Magento: ' . $e->getMessage());
                Gene_Braintree_Model_Debug::log($e);
                return false;
            }

            ++$voided;
        }

        Mage::unregister('kount_ens_update');

        if ($voided > 0) {
            $order->setState(Mage_Sales_Model_Order::STATE_CANCELED);
            $order->addStatusHistoryComment('Order declined in Kount, order voided in Magento', Mage_Sales_Model_Order::STATE_CANCELED)->save();
            return true;
        }

        return false;
    }

    /**
     * Refund an order
     *
     * @param \Mage_Sales_Model_Order $order
     *
     * @return bool
     */
    protected function _refundOrder(Mage_Sales_Model_Order $order)
    {
        // Otherwise we will have to process a credit memo
        $refunded = 0;

        /* @var $service Mage_Sales_Model_Service_Order */
        $service = Mage::getModel('sales/service_order', $order);

        /* @var $invoice Mage_Sales_Model_Order_Invoice */
        foreach ($order->getInvoiceCollection() as $invoice) {

            try {
                // The invoice might not be marked as paid yet, however if the transaction has settled we have to credit memo
                if ($invoice->getState() != Mage_Sales_Model_Order_Invoice::STATE_PAID) {
                    // "Pay" the invoice
                    $invoice->pay();
                }

                if ($invoice->canRefund()) {
                    // Build up a credit memo for the invoice, and capture it online
                    $creditmemo = $service->prepareInvoiceCreditmemo($invoice);
                    $creditmemo->setRefundRequested(true)
                        ->setOfflineRequested(false) // Process the refund online
                        ->register();

                    Mage::getModel('core/resource_transaction')
                        ->addObject($creditmemo)
                        ->addObject($creditmemo->getOrder())
                        ->addObject($creditmemo->getInvoice())
                        ->save();

                    ++$refunded;
                }
            } catch (Exception $e) {
                Gene_Braintree_Model_Debug::log('Declined Kount transaction failed to be refunded in Magento: ' . $e->getMessage());
                Gene_Braintree_Model_Debug::log($e);
                return false;
            }

        }

        Mage::unregister('kount_ens_update');

        if ($refunded > 0) {
            $order->addStatusHistoryComment('Order declined in Kount, order refunded via Credit Memo in Magento')->save();
            return true;
        }

        return false;
    }

    /**
     * Retrieve the Kount transaction ID from the ENS request
     *
     * @param $event
     *
     * @return null
     */
    protected function _getKountTransactionId($event)
    {
        if (isset($event['key']['_value'])) {
            return $event['key']['_value'];
        }

        return null;
    }

    /**
     * Retrieve the order increment ID
     *
     * @param $event
     *
     * @return mixed
     */
    protected function _getOrderIncrementId($event)
    {
        if (isset($event['key']['_attribute']['order_number'])) {
            return $event['key']['_attribute']['order_number'];
        }

        return null;
    }

    /**
     * Is the IP a valid ENS server?
     *
     * @param $ip
     *
     * @return bool
     */
    public function isValidEnsIp($ip)
    {
        $validIps = explode(',', Mage::getStoreConfig('payment/gene_braintree_creditcard/kount_ens_ips'));
        if (is_array($validIps) && count($validIps) > 0) {
            $validIps = array_map('trim', $validIps);
            foreach ($validIps as $validIp) {
                if ($this->isIpInRange($ip, $validIp)) {
                    return true;
                }
            }

            return false;
        }

        // If no IP's are set allow from all
        return true;
    }

    /**
     * Determine whether an IP is within a range
     *
     * @param $ip
     * @param $range
     *
     * @return bool
     *
     * @author https://gist.github.com/tott/7684443
     */
    protected function isIpInRange($ip, $range)
    {
        if ( strpos( $range, '/' ) === false ) {
            $range .= '/32';
        }
        // $range is in IP/CIDR format eg 127.0.0.1/24
        list( $range, $netmask ) = explode( '/', $range, 2 );
        $range_decimal = ip2long( $range );
        $ip_decimal = ip2long( $ip );
        $wildcard_decimal = pow( 2, ( 32 - $netmask ) ) - 1;
        $netmask_decimal = ~ $wildcard_decimal;
        return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
    }

    /**
     * Validate that the Kount merchant ID is set upon one of the stores
     *
     * @param $merchantId
     *
     * @return bool
     */
    public function validateStoreForMerchantId($merchantId)
    {
        // Build up an array of all store ID's
        $storeIds = array_keys(Mage::app()->getStores());

        // Add the admin store, to be checked first
        array_unshift($storeIds, 0);

        // Iterate through each store, check if the merchant ID matches
        foreach ($storeIds as $storeId) {
            $storeMerchantId = Mage::getStoreConfig('payment/gene_braintree_creditcard/kount_merchant_id', $storeId);
            if (intval($storeMerchantId) == intval($merchantId)) {
                return true;
            }
        }

        return false;
    }
}