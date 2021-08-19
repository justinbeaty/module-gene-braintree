<?php

/**
 * Class Gene_Braintree_Block_Adminhtml_System_Config_Migration
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Adminhtml_System_Config_Migration extends Mage_Core_Block_Template
{


    /**
     * Only render the block if the migration should run
     *
     * @return string
     */
    public function _toHtml()
    {
        if ($this->_runMigration()) {
            return parent::_toHtml();
        }

        return '';
    }

    /**
     * The migration should only run if the Braintree Payments module is installed, the migration hasn't already been ran
     * and the Gene Braintree extension isn't configured
     *
     * @return bool
     */
    protected function _runMigration()
    {
        return Mage::app()->getRequest()->getParam('section') == 'payment'
            && Mage::helper('gene_braintree')->canRunMigration();
    }
}