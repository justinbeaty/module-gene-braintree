<?php

/**
 * Class Gene_Braintree_Model_Migration
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Migration extends Mage_Core_Model_Abstract
{
    /**
     * Should we run the configuration migration?
     *
     * @param $bool
     *
     * @return \Varien_Object
     */
    public function setRunConfiguration($bool)
    {
        return $this->setData('run_configuration', $bool);
    }

    /**
     * Should we run the customer data migration?
     *
     * @param $bool
     *
     * @return \Varien_Object
     */
    public function setRunCustomerData($bool)
    {
        return $this->setData('run_customer_data', $bool);
    }

    /**
     * Should we update legacy orders with a new payment method?
     *
     * @param $bool
     *
     * @return \Varien_Object
     */
    public function setRunOrderTransactionInfo($bool)
    {
        return $this->setData('run_order_transaction_info', $bool);
    }

    /**
     * Should we disable the legacy module?
     *
     * @param $bool
     * @param $deleteLegacy bool
     *
     * @return \Varien_Object
     */
    public function setDisableLegacy($bool, $deleteLegacy = false)
    {
        $this->setData('disable_legacy', $bool);
        $this->setData('delete_legacy', $deleteLegacy);

        // We have to update the orders if they're removing the legacy RocketWeb extension
        if ($deleteLegacy) {
            $this->setRunOrderTransactionInfo(true);
        }
    }

    /**
     * Process the migration, building up a results object
     *
     * @return \Varien_Object
     */
    public function process()
    {
        $result = new Varien_Object();

        if ($this->getData('run_configuration')) {
            $result->setConfiguration($this->_runConfiguration());
        }

        if ($this->getData('run_customer_data')) {
            $result->setCustomerData($this->_runCustomerData());
        }

        if ($this->getData('run_order_transaction_info')) {
            $result->setOrderTransactionInfo($this->_runOrderTransactionInfo());
        }

        if ($this->getData('disable_legacy')) {
            if ($this->getData('delete_legacy')) {
                $result->setDeleteLegacy($this->_deleteLegacy());
            } else {
                $result->setDisableLegacy($this->_disableLegacy());
            }
        }

        // Update the configuration to log that the migration is complete
        $config = Mage::getConfig();
        $config->saveConfig(Gene_Braintree_Helper_Data::MIGRATION_COMPLETE, 1);
        $config->cleanCache();

        return $result;
    }

    /**
     * Migrate the configuration from the older module
     *
     * @return bool
     */
    protected function _runConfiguration()
    {
        // The mapping from the Braintree_Payments configuration into the Gene_Braintree configuration
        $configurationMapping = array(
            'environment' => 'gene_braintree/environment'
        );

        // Sandbox details go into their own fields
        if (Mage::getStoreConfig('payment/braintree/environment') == Braintree_Payments_Model_Source_Environment::ENVIRONMENT_SANDBOX) {
            $configurationMapping = array_merge($configurationMapping, array(
                'merchant_id' => 'gene_braintree/sandbox_merchant_id',
                'merchant_account_id' => 'gene_braintree/sandbox_merchant_account_id',
                'public_key' => 'gene_braintree/sandbox_public_key',
                'private_key' => 'gene_braintree/sandbox_private_key'
            ));
        } else {
            $configurationMapping = array_merge($configurationMapping, array(
                'merchant_id' => 'gene_braintree/merchant_id',
                'merchant_account_id' => 'gene_braintree/merchant_account_id',
                'public_key' => 'gene_braintree/public_key',
                'private_key' => 'gene_braintree/private_key'
            ));
        }

        $configurationMapping = array_merge($configurationMapping, array(
            /* PayPal */
            'paypal_active' => 'gene_braintree_paypal/active',
            'paypal_title' => 'gene_braintree_paypal/title',
            'paypal_sort_order' => 'gene_braintree_paypal/sort_order',
            'paypal_payment_action' => 'gene_braintree_paypal/payment_action',
            'paypal_order_status' => 'gene_braintree_paypal/order_status',
            'paypal_allowspecific' => 'gene_braintree_paypal/allowspecific',
            'paypal_specificcountry' => 'gene_braintree_paypal/specificcountry',
            'shortcut_shopping_cart' => 'gene_braintree_paypal/express_cart',

            /* Credit Card */
            'active' => 'gene_braintree_creditcard/active',
            'title' => 'gene_braintree_creditcard/title',
            'sort_order' => 'gene_braintree_creditcard/sort_order',
            'payment_action' => 'gene_braintree_creditcard/payment_action',
            'capture_action' => 'gene_braintree_creditcard/capture_action',
            'order_status' => 'gene_braintree_creditcard/order_status',
            'use_vault' => 'gene_braintree_creditcard/use_vault',
            'cctypes' => 'gene_braintree_creditcard/cctypes',
            'three_d_secure' => 'gene_braintree_creditcard/threedsecure',
            'kount_id' => 'gene_braintree_creditcard/kount_merchant_id',
            'kount_environment' => 'gene_braintree_creditcard/kount_environment',
            'allowspecific' => 'gene_braintree_creditcard/allowspecific',
            'specificcountry' => 'gene_braintree_creditcard/specificcountry'
        ));

        /* @var $resource Mage_Core_Model_Resource */
        $resource = Mage::getModel('core/resource');
        $dbRead = $resource->getConnection('core_read');

        // Retrieve the entire config
        $config = Mage::getConfig();
        $config->cleanCache();

        // Retrieve all of the store ID's including the default
        $stores = Mage::getResourceModel('core/store_collection');

        /* @var $store Mage_Core_Model_Store */
        foreach ($stores as $store) {

            // Iterate through each field within a store updating it on the store view if it exists
            foreach ($configurationMapping as $legacyKey => $newKey)
            {
                // Convert the aliases into the full paths
                $legacyKey = 'payment/braintree/' . $legacyKey;
                $newKey = 'payment/' . $newKey;

                // Attempt to load the config directly on the store
                $readConfigData = $dbRead->select()
                    ->from($resource->getTableName('core/config_data'), 'value')
                    ->where('scope = ?', 'stores')
                    ->where('scope_id = ?', $store->getId())
                    ->where('path = ?', $legacyKey);

                $value = $dbRead->fetchOne($readConfigData);

                // If the data loads, we know this is set specifically on a store view
                if ($value !== false) {
                    $config->saveConfig($newKey, $store->getConfig($legacyKey), 'stores', $store->getId());
                }
            }
        }

        // Retrieve all of the website ID's
        $websites = Mage::getResourceModel('core/website_collection');

        /* @var $website Mage_Core_Model_Website */
        foreach ($websites as $website) {

            // Iterate through each field within a store updating it on the store view if it exists
            foreach ($configurationMapping as $legacyKey => $newKey)
            {
                // Convert the aliases into the full paths
                $legacyKey = 'payment/braintree/' . $legacyKey;
                $newKey = 'payment/' . $newKey;

                // Attempt to load the config directly on the store
                $readConfigData = $dbRead->select()
                    ->from($resource->getTableName('core/config_data'), 'value')
                    ->where('scope = ?', 'websites')
                    ->where('scope_id = ?', $website->getId())
                    ->where('path = ?', $legacyKey);

                $value = $dbRead->fetchOne($readConfigData);

                // If the data loads, we know this is set specifically on a store view
                if ($value !== false) {
                    $config->saveConfig($newKey, $website->getConfig($legacyKey), 'websites', $website->getId());
                }
            }
        }

        // Finally update the default configuration data
        foreach ($configurationMapping as $legacyKey => $newKey)
        {
            // Convert the aliases into the full paths
            $legacyKey = 'payment/braintree/' . $legacyKey;
            $newKey = 'payment/' . $newKey;

            if ($value = Mage::getStoreConfig($legacyKey, 0)) {
                $config->saveConfig($newKey, $value, 'default', 0);
            }
        }

        // Clean the cache
        $config->cleanCache();

        return true;
    }

    /**
     * Update customer accounts attribute braintree_customer_id with an MD5 of customer data, as per the Braintree_Payments
     * module
     *
     * @return int
     */
    protected function _runCustomerData()
    {
        // Retrieve all of the customers
        /* @var $customers Mage_Customer_Model_Resource_Customer_Collection */
        $customers = Mage::getResourceModel('customer/customer_collection');
        $entityTypeId = $customers->getEntity()->getEntityType()->getId();

        // Grab the braintree_customer_id attribute
        $attribute = $customers->getAttribute('braintree_customer_id');
        if ($attribute->getId()) {
            $updatedCount = 0;
            $tableUpdates = array();
            foreach ($customers as $customer)
            {
                if (!$customer->getData('braintree_customer_id')) {
                    $tableUpdates[] = array(
                        'entity_type_id' => $entityTypeId,
                        'attribute_id' => $attribute->getId(),
                        'entity_id' => $customer->getId(),
                        'value' => hash('sha256', $customer->getId() . '-' . $customer->getEmail())
                    );
                    ++$updatedCount;
                }
            }

            // Retrieve the table we need to update
            $updateTable = $customers->getTable('customer_entity_' . $attribute->getBackendType());

            $resource = Mage::getModel('core/resource');
            /* @var $dbWrite Magento_Db_Adapter_Pdo_Mysql */
            $dbWrite = $resource->getConnection('core_write');

            // Insert the new rows into the database
            $dbWrite->insertOnDuplicate($updateTable, $tableUpdates);

            return $updatedCount;
        }

        return false;
    }

    /**
     * Update legacy orders with new payment method codes to ensure info screen behaves
     *
     * @return bool
     */
    public function _runOrderTransactionInfo()
    {
        /* @var $resource Mage_Core_Model_Resource */
        $resource = Mage::getModel('core/resource');
        $dbWrite = $resource->getConnection('core_write');

        $dbWrite->update($resource->getTableName('sales/order_payment'), array('method' => 'braintree_legacy'), "method = 'braintree'");
        $dbWrite->update($resource->getTableName('sales/order_payment'), array('method' => 'braintree_paypal_legacy'), "method = 'braintree_paypal'");

        return true;
    }

    /**
     * Delete the legacy module from the merchants store
     *
     * @return bool
     */
    protected function _deleteLegacy()
    {
        // The legacy files that need to be removed
        $legacyFiles = array(
            'app/code/local/Braintree/',
            'app/design/adminhtml/default/default/layout/braintree.xml',
            'app/design/adminhtml/default/default/template/braintree/',
            'app/design/frontend/base/default/layout/braintree.xml',
            'app/design/frontend/base/default/template/braintree/',
            'app/etc/modules/Braintree_Payments.xml',
            'app/locale/en_US/Braintree_Payments.csv',
            'js/braintree/',
            'lib/Braintree/',
            'lib/Braintree.php',
            'lib/ssl/',
            'shell/braintreeIds.php',
            'skin/frontend/base/default/braintree/',
            'var/package/Braintree_Payments-2.0.0.xml'
        );

        // If we know the document root, we can remove these files
        if (isset($_SERVER['DOCUMENT_ROOT'])) {

            // Iterate through removing the directories, and unlinking the files
            foreach ($legacyFiles as $legacyFile)
            {
                $file = $_SERVER['DOCUMENT_ROOT'] . DS . $legacyFile;
                if (is_dir($file)) {
                    $this->_rmDir($file);
                } else if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        $resource = Mage::getModel('core/resource');
        /* @var $dbWrite Magento_Db_Adapter_Pdo_Mysql */
        $dbWrite = $resource->getConnection('core_write');

        // Delete the system configuration values
        $dbWrite->delete($resource->getTableName('core/config_data'), 'path LIKE "payment/braintree/%" OR path LIKE "payment/braintree_paypal/%"');

        return true;
    }

    /**
     * Remove a directory recursively
     *
     * @param $dir
     */
    protected function _rmDir($dir)
    {
        foreach (glob($dir . '/' . '*') as $file) {
            if (is_dir($file)) {
                $this->_rmDir($file);
            } else {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }

    /**
     * Disable the legacy modules
     *
     * @return bool
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function _disableLegacy()
    {
        $resource = Mage::getModel('core/resource');
        /* @var $dbWrite Magento_Db_Adapter_Pdo_Mysql */
        $dbWrite = $resource->getConnection('core_write');

        // Update all the paths to be disabled
        $dbWrite->update(
            $resource->getTableName('core/config_data'),
            array('value' => 0),
            'path = "payment/braintree/paypal_active" OR path = "payment/braintree/active" OR path = "payment/braintree_paypal/active"'
        );

        Mage::getConfig()->cleanCache();

        return true;
    }
}