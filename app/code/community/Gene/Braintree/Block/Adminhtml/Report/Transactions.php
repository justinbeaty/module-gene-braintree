<?php

/**
 * Class Gene_Braintree_Block_Adminhtml_Report_Transactions
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Adminhtml_Report_Transactions extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'gene_braintree';
        $this->_controller = 'adminhtml_report_transactions';
        $this->_headerText = Mage::helper('gene_braintree')->__('Braintree Transactions');

        parent::__construct();

        $this->_removeButton('add');
    }
}