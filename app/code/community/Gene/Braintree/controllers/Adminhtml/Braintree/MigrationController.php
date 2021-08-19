<?php

/**
 * Class Gene_Braintree_Adminhtml_Braintree_MigrationController
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Adminhtml_Braintree_MigrationController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Check current user permission on resource and privilege
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/order');
    }

    /**
     * Run the migration based on the users choice
     *
     * @return $this
     */
    public function runAction()
    {
        // If the system shouldn't run the migration don't allow this controller to initialize
        if (!Mage::helper('gene_braintree')->canRunMigration()) {
            $this->norouteAction();
            return $this;
        }

        $actions = $this->getRequest()->getParam('migration');

        /* @var $migration Gene_Braintree_Model_Migration */
        $migration = Mage::getModel('gene_braintree/migration');

        // Pass the users choices through to the migration model
        $migration->setRunConfiguration(
            (isset($actions['configuration-settings']) && $actions['configuration-settings'] == 'on')
        );
        $migration->setRunCustomerData(
            (isset($actions['customer-data']) && $actions['customer-data'] == 'on')
        );
        $migration->setDisableLegacy(
            (isset($actions['disable-legacy']) && $actions['disable-legacy'] == 'on'),
            (isset($actions['remove-legacy']) && $actions['remove-legacy'] == 'on')
        );
        $migration->setRunOrderTransactionInfo(
            (isset($actions['order-transaction-info']) && $actions['order-transaction-info'] == 'on')
        );

        // Run the migration process
        $result = $migration->process();

        // Add a success message into the session
        $this->_getSession()->addSuccess(Mage::helper('gene_braintree')->__('We have successfully migrated you from the Braintree Payments extension to the new Gene Braintree extension.'));

        // Return a JSON response to the browser
        return $this->_returnJson(array_merge(array(
            'success' => true
        ), $result->debug()));
    }

    /**
     * Cancelling the migration will ensure it doesn't appear again
     *
     * @return \Gene_Braintree_Adminhtml_Braintree_MigrationController
     */
    public function cancelAction()
    {
        // Update the configuration to log that the migration is complete
        $config = Mage::getConfig();
        $config->saveConfig(Gene_Braintree_Helper_Data::MIGRATION_COMPLETE, 1);
        $config->cleanCache();

        return $this->_returnJson(array('success' => true));
    }

    /**
     * Return JSON to the browser
     *
     * @param $array
     *
     * @return $this
     */
    protected function _returnJson($array)
    {
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($array));
        $this->getResponse()->setHeader('Content-type', 'application/json');

        return $this;
    }

}