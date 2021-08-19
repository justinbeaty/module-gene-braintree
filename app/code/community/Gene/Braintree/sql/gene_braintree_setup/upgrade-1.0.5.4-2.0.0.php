<?php

/* @var $installer Gene_Braintree_Model_Entity_Setup */
$installer = $this;
$installer->startSetup();

// The config paths that need to be transferred to sandbox
$transferConfig = array(
    Gene_Braintree_Model_Wrapper_Braintree::BRAINTREE_MERCHANT_ACCOUNT_ID_PATH => Gene_Braintree_Model_Wrapper_Braintree::BRAINTREE_SANDBOX_MERCHANT_ACCOUNT_ID_PATH,
    Gene_Braintree_Model_Wrapper_Braintree::BRAINTREE_PUBLIC_KEY_PATH => Gene_Braintree_Model_Wrapper_Braintree::BRAINTREE_SANDBOX_PUBLIC_KEY_PATH,
    Gene_Braintree_Model_Wrapper_Braintree::BRAINTREE_PRIVATE_KEY_PATH => Gene_Braintree_Model_Wrapper_Braintree::BRAINTREE_SANDBOX_PRIVATE_KEY_PATH,
    Gene_Braintree_Model_Wrapper_Braintree::BRAINTREE_MERCHANT_ID_PATH => Gene_Braintree_Model_Wrapper_Braintree::BRAINTREE_SANDBOX_MERCHANT_ID_PATH
);

// Update values on the default scope
if ($this->getStoreConfig(Gene_Braintree_Model_Wrapper_Braintree::BRAINTREE_ENVIRONMENT_PATH) == 'sandbox') {

    // Transfer the settings into the new sandbox fields
    foreach ($transferConfig as $productionPath => $sandboxPath) {
        Mage::getConfig()->saveConfig(
            $sandboxPath,
            $this->getStoreConfig($productionPath),
            'default',
            0
        );
    }

    // Move anyone using the default integration over to Hosted Fields
    if ($this->getStoreConfig(Gene_Braintree_Model_Source_Creditcard_FormIntegration::INTEGRATION_ACTION_XML_PATH) == Gene_Braintree_Model_Source_Creditcard_FormIntegration::INTEGRATION_DEFAULT) {
        Mage::getConfig()->saveConfig(
            Gene_Braintree_Model_Source_Creditcard_FormIntegration::INTEGRATION_ACTION_XML_PATH,
            Gene_Braintree_Model_Source_Creditcard_FormIntegration::INTEGRATION_HOSTED,
            'default',
            0
        );
    }
}

// Loop through the stores and ensure they're all up to date
$stores = Mage::getModel('core/store')->getCollection();
foreach ($stores as $store) {

    // Check to see if this store is in sandbox mode
    if ($this->getStoreConfig(Gene_Braintree_Model_Wrapper_Braintree::BRAINTREE_ENVIRONMENT_PATH, $store) == 'sandbox') {

        // Transfer the settings into the new sandbox fields
        foreach ($transferConfig as $productionPath => $sandboxPath) {

            // Only update those values which aren't inheriting correctly from default
            if ($this->getStoreConfig($sandboxPath, $store) != $this->getStoreConfig($productionPath, $store)) {
                Mage::getConfig()->saveConfig(
                    $sandboxPath,
                    $this->getStoreConfig($productionPath, $store),
                    'stores',
                    $store->getId()
                );
            }
        }
    }

    // Move anyone using the default integration over to Hosted Fields
    if ($this->getStoreConfig(Gene_Braintree_Model_Source_Creditcard_FormIntegration::INTEGRATION_ACTION_XML_PATH, $store) == Gene_Braintree_Model_Source_Creditcard_FormIntegration::INTEGRATION_DEFAULT) {
        Mage::getConfig()->saveConfig(
            Gene_Braintree_Model_Source_Creditcard_FormIntegration::INTEGRATION_ACTION_XML_PATH,
            Gene_Braintree_Model_Source_Creditcard_FormIntegration::INTEGRATION_HOSTED,
            'stores',
            $store->getId()
        );
    }
}

// Clean the cache
Mage::getConfig()->cleanCache();

$installer->endSetup();