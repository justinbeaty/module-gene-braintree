<?php

/**
 * Class Gene_Braintree_Block_Adminhtml_Report_Transactions_Search
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Adminhtml_Report_Transactions_Search extends Mage_Core_Block_Template
{
    /**
     * Return the from date
     *
     * @return string
     */
    public function getFromDate()
    {
        if($fromDate = Mage::app()->getRequest()->getParam('from_date')) {
            return $fromDate;
        }

        return $this->formateDateBraintree(strtotime('-24 hour'));
    }

    /**
     * Return the to date
     *
     * @return string
     */
    public function getToDate()
    {
        if($toDate = Mage::app()->getRequest()->getParam('to_date')) {
            return $toDate;
        }

        return $this->formateDateBraintree(time());
    }

    /**
     * Return all of the possible statuses
     *
     * @return array
     */
    public function getStatusesAsArray()
    {
        // Add in a show all option
        $all[''] = 'Show All';

        // Grab all the statuses
        $statuses = Mage::helper('gene_braintree')->getStatusesAsArray();

        // Combine them
        return array_merge($all, $statuses);
    }

    /**
     * Return the types as an array
     *
     * @return array
     */
    public function getTypesAsArray()
    {
        return array(
            '' => 'All',
            'sale' => 'Sale',
            'credit' => 'Credit'
        );
    }

    /**
     * Return the selected status
     *
     * @return mixed
     */
    public function getSelectedType()
    {
        return Mage::app()->getRequest()->getParam('type');
    }

    /**
     * Return the selected status
     *
     * @return mixed
     */
    public function getSelectedStatus()
    {
        return Mage::app()->getRequest()->getParam('status');
    }

    /**
     * Return the selected status
     *
     * @return mixed
     */
    public function getOrderId()
    {
        return Mage::app()->getRequest()->getParam('order_id');
    }

    /**
     * Format the date for display on the Braintree search form
     *
     * @param $date
     *
     * @return string
     */
    public function formateDateBraintree($date)
    {
        return date('d-m-Y G:i', $date);
    }
}