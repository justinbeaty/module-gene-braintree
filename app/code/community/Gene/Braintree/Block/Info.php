<?php

/**
 * Class Gene_Braintree_Block_Info
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Info extends Mage_Payment_Block_Info
{
    /**
     * Store this within the model
     */
    private $_singleInvoice = null;

    /**
     * Return the currently viewed order
     *
     * @return \Mage_Sales_Model_Order|\Mage_Sales_Model_Order_Invoice|\Mage_Sales_Model_Order_Creditmemo
     */
    protected function getViewedObject()
    {
        // Return the invoice first
        if (Mage::registry('current_invoice')) {
            return Mage::registry('current_invoice');
        } else if (Mage::registry('current_creditmemo')) {
            return Mage::registry('current_creditmemo');
        } else if (Mage::registry('current_order')) {
            return Mage::registry('current_order');
        } else if (Mage::registry('current_shipment')) {
            return Mage::registry('current_shipment')->getOrder();
        }

        return false;
    }

    /**
     * Include live details
     *
     * @param $data
     */
    protected function includeLiveDetails(&$data)
    {
        // Retrieve the order
        $order = $this->getViewedObject();

        // Sometimes orders may not have transaction Id's
        $transactionIds = array();

        // If we're viewing a single invoice change the response
        if ($this->isSingleInvoice()) {
            // Transaction ID won't matter for customers
            $data[$this->__('Braintree Transaction ID')] = $this->getTransactionId();

            // Build an array of transaction ID's
            $transactionIds = array($this->getTransactionId());

        } elseif ($order) {

            /* @var $invoices Mage_Sales_Model_Resource_Order_Invoice_Collection */
            $invoices = $order->getInvoiceCollection();
            if($invoices->getSize() > 1) {
                // Build up our array
                foreach($invoices as $invoice) {
                    $transactionIds[] = $invoice->getTransactionId();
                }
            } else {
                // Transaction ID won't matter for customers
                $data[$this->__('Braintree Transaction ID')] = $this->getTransactionId();
            }

        }

        // Do we have any transaction ID's
        if(!empty($transactionIds)) {
            // Start a count
            $count = 1;

            // Iterate through transaction ID's
            foreach ($transactionIds as $transactionId) {
                // Add in another label
                if(count($transactionIds) > 1) {
                    $data[$this->__('Braintree Transaction #%d', $count)] = '';
                }

                // If the order contains more than one transaction show all statuses
                $label = $this->__('Status%s', (count($transactionIds) > 1 ? ' (' . $this->__('Transaction ID: ') . $transactionId . ')' : ''));

                // Add in the current status
                try {
                    $transaction = Mage::getModel('gene_braintree/wrapper_braintree')->init($this->getViewedObject()->getStoreId())->findTransaction($transactionId);
                    if ($transaction) {
                        $data[$label] = $this->convertStatus($transaction->status);
                    } else {
                        $data[$label] = $this->__('<span style="color:red;"><strong>Warning:</strong> Cannot load payment in Braintree.</span>');
                    }
                } catch (Exception $e) {
                    $data[$label] = $this->__('<span style="color:red;"><strong>Warning:</strong> Unable to connect to Braintree to load transaction.</span>');
                }

                ++$count;
            }
        }

        if(count($transactionIds) == 1 && isset($transaction)) {
            return $transaction;
        }

        return null;
    }

    /**
     * Are we viewing a single invoice?
     *
     * @return bool
     */
    protected function isSingleInvoice()
    {
        // Caching on the check
        if($this->_singleInvoice === null) {
            $this->_singleInvoice = $this->getViewedObject()
                && ($this->getViewedObject() instanceof Mage_Sales_Model_Order
                    && $this->getViewedObject()->getInvoiceCollection()->getSize() <= 1)
                || ($this->getViewedObject() instanceof Mage_Sales_Model_Order_Invoice)
                || ($this->getViewedObject() instanceof Mage_Sales_Model_Order_Creditmemo);
        }

        return $this->_singleInvoice;
    }

    /**
     * Return the transaction ID
     *
     * @return bool|string
     */
    protected function getTransactionId()
    {
        // If the viewed object is an order or it's an invoice but the order doesn't have any invoices
        if($this->getViewedObject() && $this->getViewedObject() instanceof Mage_Sales_Model_Order
            || ($this->getViewedObject() instanceof Mage_Sales_Model_Order_Invoice && !$this->getViewedObject()->getTransactionId()))
        {

            // Return the transaction ID from the info
            return $this->_getWrapper()->getCleanTransactionId($this->getInfo()->getLastTransId());

            // Else if we're viewing an invoice or a credit memo
        } else if($this->getViewedObject() && ($this->getViewedObject() instanceof Mage_Sales_Model_Order_Invoice
                || $this->getViewedObject() instanceof Mage_Sales_Model_Order_Creditmemo))
        {

            // If the creditmemo is being created it has no transaction ID
            if($this->getViewedObject() instanceof Mage_Sales_Model_Order_Creditmemo && !$this->getViewedObject()->getTransactionId()) {

                // If the creditmemo has an invoice use that transaction ID, otherwise we're viewing an order wide credit memo
                if($this->getViewedObject()->getInvoice() && $this->getViewedObject()->getInvoice()->getTransactionId()) {
                    return $this->_getWrapper()->getCleanTransactionId($this->getViewedObject()->getInvoice()->getTransactionId());
                } else {
                    return $this->_getWrapper()->getCleanTransactionId($this->getInfo()->getLastTransId());
                }
            }

            return $this->_getWrapper()->getCleanTransactionId($this->getViewedObject()->getTransactionId());
        } else if(!$this->getViewedObject()) {
            // If we don't have a viewed object just utilise the information in the model
            $info = $this->getData('info');
            if ($info instanceof Mage_Payment_Model_Info && $this->getInfo()->getLastTransId()) {
                return $this->_getWrapper()->getCleanTransactionId($this->getInfo()->getLastTransId());
            }
        }

        return false;
    }

    /**
     * Make the status nicer to read
     *
     * @param $status
     *
     * @return string
     */
    protected function convertStatus($status)
    {
        switch($status){
            case 'authorized':
                return '<span style="color: #40A500;"> ' . Mage::helper('gene_braintree')->__('Authorized') . '</span>';
                break;
            case 'submitted_for_settlement':
                return '<span style="color: #40A500;">' . Mage::helper('gene_braintree')->__('Submitted For Settlement') . '</span>';
                break;
            case 'settling':
                return '<span style="color: #40A500;">' . Mage::helper('gene_braintree')->__('Settling') . '</span>';
                break;
            case 'settled':
                return '<span style="color: #40A500;">' . Mage::helper('gene_braintree')->__('Settled') . '</span>';
                break;
            case 'voided':
                return '<span style="color: #ed4737;">' . Mage::helper('gene_braintree')->__('Voided') . '</span>';
                break;
        }

        return ucwords($status);
    }

    /**
     * Return the wrapper class
     *
     * @return Gene_Braintree_Model_Wrapper_Braintree
     */
    protected function _getWrapper()
    {
        return Mage::getSingleton('gene_braintree/wrapper_braintree');
    }

}