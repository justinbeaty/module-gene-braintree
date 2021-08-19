<?php

/**
 * Class Gene_Braintree_Block_Adminhtml_Report_Transactions_Grid
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Adminhtml_Report_Transactions_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * We allow overriding of the search query
     */
    private $searchQuery = false;

    public function __construct()
    {
        parent::__construct();
        $this->setId('gene_braintree_settlement_grid');
        $this->setDefaultSort('qty_ordered');
        $this->setDefaultDir('DESC');

        // As we're building a custom collection the standard filters won't work
        $this->setPagerVisibility(false);
        $this->setFilterVisibility(false);
    }

    /**
     * Allow anyone invoking this grid to overriding the search query
     *
     * @param $query
     */
    public function setSearchQuery($query)
    {
        $this->searchQuery = $query;
    }

    /**
     * Build up a search query based on the users entries
     *
     * @return $this
     */
    protected function _prepareBraintreeSearchQuery()
    {
        // Has the search query been set already?
        if($this->searchQuery) {
            return $this->searchQuery;
        }

        $searchArray = array();

        // Init some times
        $to = new Datetime();
        $from = clone $to;
        $from = $from->modify("-24 hour");

        // If a from and to date are set modify things
        if(Mage::app()->getRequest()->getParam('from_date') && Mage::app()->getRequest()->getParam('to_date')) {
            $from = new DateTime(Mage::app()->getRequest()->getParam('from_date'));
            $to = new DateTime(Mage::app()->getRequest()->getParam('to_date'));
        }

        // We always want to be filtering by a date to some degree
        $searchArray[] = Braintree_TransactionSearch::createdAt()->between($from, $to);

        // Type search
        if($type = Mage::app()->getRequest()->getParam('type')) {
            $searchArray[] = Braintree_TransactionSearch::type()->is($type);
        }

        // Allow searching upon the status
        if($status = Mage::app()->getRequest()->getParam('status')) {
            $searchArray[] = Braintree_TransactionSearch::status()->is($status);
        }

        // Order ID searching can be helpful
        if($orderId = Mage::app()->getRequest()->getParam('order_id')) {
            $searchArray[] = Braintree_TransactionSearch::orderId()->is($orderId);
        }

        // Store the search query within the session
        Mage::getSingleton('adminhtml/session')->setBraintreeSearchQuery($searchArray);

        return $searchArray;
    }

    /**
     * Prepare the collection for the report
     *
     * @return $this|Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection()
    {
        // Add in a new collection
        $collection = new Varien_Data_Collection();

        // Init the wrapper
        $wrapper = Mage::getModel('gene_braintree/wrapper_braintree');

        // Validate the credentials
        if($wrapper->validateCredentials()) {

            // Grab all transactions
            $transactions = Braintree_Transaction::search($this->_prepareBraintreeSearchQuery());

            // Retrieve the order IDs
            $orderIds = array();
            /* @var $transaction Braintree_Transaction */
            foreach ($transactions as $transaction) {
                $orderIds[] = $transaction->orderId;
            }

            // Retrieve all of the orders from a collection
            $orders = Mage::getResourceModel('sales/order_collection')->addAttributeToFilter('increment_id', array('in' => $orderIds));

            /* @var $transaction Braintree_Transaction */
            foreach ($transactions as $transaction) {
                $transaction = (array) $transaction;
                $transaction = current($transaction);

                // Create a new varien object
                $transactionItem = new Varien_Object();
                $transactionItem->setData($transaction);

                // Grab the Magento order from the previously built collection
                /* @var $magentoOrder Mage_Sales_Model_Order */
                $magentoOrder = $orders->getItemByColumnValue('increment_id', $transaction['orderId']);

                // Set the Magento Order ID into the collection
                // Not all transactions maybe coming from Magento
                if ($magentoOrder && $magentoOrder->getId()) {
                    $transactionItem->setMagentoOrderId($magentoOrder->getId());
                    $transactionItem->setOrderStatus($magentoOrder->getStatus());
                } else {
                    $transactionItem->setOrderStatus('<em>Unknown</em>');
                }

                // Add the item into the collection
                $collection->addItem($transactionItem);
            }

        } else {

            // If the Braintree details aren't valid take them to the configuration page
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('gene_braintree')->__('You must enter valid details into the Braintree v.zero - Configuration payment method before viewing transactions.'));

            // Send the users on their way
            Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl('/system_config/edit/section/payment') . '#payment_gene_braintree-head');
            Mage::app()->getResponse()->sendResponse();

            // Stop processing this method
            return false;
        }

        $this->setCollection($collection);
        parent::_prepareCollection();
        return $this;
    }

    /**
     * Prepare the columns we're wanting to display
     *
     * @return $this
     *
     * @throws Exception
     */
    protected function _prepareColumns()
    {
        $helper = Mage::helper('gene_braintree');

        $this->addColumn('id', array(
            'header' => $helper->__('ID'),
            'index'  => 'id',
            'width' => 120,
            'filter' => false,
            'sortable' => false
        ));

        $this->addColumn('created_at', array(
            'header' => $helper->__('Transaction Date'),
            'index'  => 'created_at',
            'type' => 'datetime',
            'frame_callback' => array($this, 'handleDate'),
            'filter' => false,
            'sortable' => false
        ));

        $this->addColumn('orderId', array(
            'header' => $helper->__('Magento Order ID'),
            'index'  => 'orderId',
            'width' => 120,
            'filter' => false,
            'sortable' => false
        ));

        $this->addColumn('order_status', array(
            'header' => $helper->__('Magento Status'),
            'index'  => 'order_status',
            'type'  => 'options',
            'options' => Mage::getSingleton('sales/order_config')->getStatuses(),
            'filter' => false,
            'sortable' => false
        ));

        $this->addColumn('merchantAccountId', array(
            'header' => $helper->__('Merchant Account ID'),
            'index'  => 'merchantAccountId',
            'filter' => false,
            'sortable' => false
        ));

        $this->addColumn('type', array(
            'header' => $helper->__('Type'),
            'index'  => 'type',
            'frame_callback' => array($this, 'handleType'),
            'filter' => false,
            'sortable' => false
        ));

        $this->addColumn('payment_information', array(
            'header' => $helper->__('Payment Information'),
            'index'  => 'payment_information',
            'frame_callback' => array($this, 'handlePaymentInformation'),
            'filter' => false,
            'sortable' => false
        ));

        $this->addColumn('amount', array(
            'header' => $helper->__('Amount'),
            'index'  => 'amount',
            'type' => 'number',
            'frame_callback' => array($this, 'handleAmount'),
            'filter' => false,
            'sortable' => false
        ));

        $this->addColumn('currencyIsoCode', array(
            'header' => $helper->__('Currency'),
            'index'  => 'currencyIsoCode',
            'filter' => false,
            'sortable' => false
        ));

        $this->addColumn('status', array(
            'header' => $helper->__('Braintree Status'),
            'type' => 'options',
            'options' => $helper->getStatusesAsArray(),
            'index'  => 'status',
            'filter' => false,
            'sortable' => false
        ));

        // Allow the admin to export this viewed data
        $this->addExportType('*/*/exportCsv', $helper->__('CSV'));
        $this->addExportType('*/*/exportExcel', $helper->__('Excel XML'));

        return parent::_prepareColumns();
    }

    /**
     * Format the amount into the currency of the transaction
     *
     * @param $value
     * @param $row
     * @param $column
     * @param $isExport
     *
     * @return string
     * @throws Zend_Currency_Exception
     */
    public function handleAmount($value, $row, $column, $isExport)
    {
        return Mage::app()->getLocale()->currency($row['currencyIsoCode'])->toCurrency($value);
    }

    /**
     * Return the date object as a timestamp
     *
     * @param $value
     * @param $row
     * @param $column
     * @param $isExport
     *
     * @return int
     */
    public function handleDate($value, $row, $column, $isExport)
    {
        /* @var $date DateTime */
        $date = $row['createdAt'];

        return $date->format('r');
    }

    /**
     * Upper case the first letter of the type
     *
     * @param $value
     * @param $row
     * @param $column
     * @param $isExport
     *
     * @return string
     */
    public function handleType($value, $row, $column, $isExport)
    {
        return ucfirst($value);
    }

    /**
     * Display payment information regarding the transaction
     *
     * @param $value
     * @param $row
     * @param $column
     * @param $isExport
     *
     * @return string
     */
    public function handlePaymentInformation($value, $row, $column, $isExport)
    {
        // Grab the image associated with this payment
        $image = false;
        if(!$isExport) {
            $image = '<img height="26" align="left" src=' . (isset($row['paymentInstrumentType']) && $row['paymentInstrumentType'] == 'paypal_account' ? $row['paypal']['imageUrl'] : $row['creditCard']['imageUrl']) . '" />&nbsp;&nbsp;';
        }

        // Display the actual payment information
        $response = false;
        if(isset($row['paymentInstrumentType']) && $row['paymentInstrumentType'] == 'paypal_account') {
            $response = $image . $row['paypal']['payerEmail'];
        } else if(isset($row['paymentInstrumentType']) && $row['paymentInstrumentType'] == 'credit_card') {
            $response = $image . $row['creditCard']['bin'] . '******' . $row['creditCard']['last4'];
        }

        return (!$isExport ? '<span style="line-height: 26px;">' : '') . $response . (!$isExport ? '</span>' : '');
    }

    /**
     * If an item has a Magento Order ID take them to the sales order view screen
     *
     * @param $row
     *
     * @return string
     */
    public function getRowUrl($row)
    {
        if($row->getMagentoOrderId()) {
            return $this->getUrl('*/sales_order/view', array('order_id' => $row->getMagentoOrderId()));
        }
        return false;
    }

}