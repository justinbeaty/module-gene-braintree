<?php

/* @var $installer Gene_Braintree_Model_Entity_Setup */
$installer = $this;
$installer->startSetup();

// Update the white list for AnattaDesign Awesome Checkout
if (Mage::helper('core')->isModuleEnabled('AnattaDesign_AwesomeCheckout')) {
    $whiteListConfigXmlPath = 'awesomecheckout/advanced/whitelisted_css_js';
    $whiteList = array(
        'gene/braintree/vzero-min.js',
        'gene/braintree/vzero-paypal-min.js',
        'gene/braintree/vzero-integration-min.js',
        'css/gene/braintree/awesomecheckout.css'
    );

    // Update values on the default scope
    if ($currentWhiteList = $this->getStoreConfig($whiteListConfigXmlPath)) {
        $currentWhiteListArray = explode("\n", $currentWhiteList);
        if (is_array($currentWhiteListArray) && count($currentWhiteListArray) > 0) {
            $whiteList = array_merge($currentWhiteListArray, $whiteList);
        }
    }

    // Save the new default config values
    Mage::getConfig()->saveConfig(
        $whiteListConfigXmlPath,
        implode("\n", $whiteList),
        'default',
        0
    );

    // Loop through the stores and ensure they're all up to date
    $stores = Mage::getModel('core/store')->getCollection();
    foreach ($stores as $store) {

        // Update values on the default scope
        if ($currentWhiteList = $this->getStoreConfig($whiteListConfigXmlPath, $store)) {
            $currentWhiteListArray = explode("\n", $currentWhiteList);
            if (is_array($currentWhiteListArray) && count($currentWhiteListArray) > 0) {
                $whiteList = array_merge($currentWhiteListArray, $whiteList);
            }
        }

        // Save the new default config values
        Mage::getConfig()->saveConfig(
            $whiteListConfigXmlPath,
            implode("\n", $whiteList),
            'stores',
            $store->getId()
        );
    }

    // Clean the cache
    Mage::getConfig()->cleanCache();
}

$installer->endSetup();