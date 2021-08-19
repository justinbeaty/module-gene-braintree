<?php

/**
 * Class Gene_Braintree_Block_Assets
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Assets extends Mage_Core_Block_Template
{
    /**
     * Version of Braintree SDK to be included
     */
    const SDK_VERSION = '3.48.0';

    /**
     * Record the current version
     *
     * @var null|string|float
     */
    protected $version = null;

    /**
     * An array of JavaScript to be included as assets
     *
     * @var array
     */
    protected $js = array();

    /**
     * Any external JavaScript to be included
     *
     * @var array
     */
    protected $externalJs = array();

    /**
     * Initialize template
     *
     */
    protected function _construct()
    {
        $this->setTemplate('gene/braintree/assets.phtml');
    }

    /**
     * Add internal JS
     *
     * @param $url
     *
     * @return $this
     */
    public function addJs($url)
    {
        $this->js[] = $url;
        return $this;
    }

    /**
     * Return the JS URLs
     *
     * @return array
     */
    public function getJs()
    {
        return array_unique($this->js);
    }

    /**
     * Add an external JS asset to the page
     *
     * @param $url
     *
     * @return $this
     */
    public function addExternalJs($url)
    {
        $this->externalJs[] = $url;
        return $this;
    }

    /**
     * Return the external JS scripts
     *
     * @return array
     */
    public function getExternalJs()
    {
        return array_unique($this->externalJs);
    }

    /**
     * Return the Braintree module version
     *
     * @return mixed
     */
    public function getModuleVersion()
    {
        if ($this->version === null) {
            if ($version = Mage::getConfig()->getModuleConfig('Gene_Braintree')->version) {
                $this->version = $version;
            } else {
                $this->version = false;
            }
        }

        return $this->version;
    }

    /**
     * Replace {MODULE_VERSION} with the current module version
     * Replace {SDK_VERSION} with the current require SDK version
     *
     * @param string $fileName
     *
     * @return string
     */
    public function getJsUrl($fileName = '')
    {
        $fileName = str_replace('{MODULE_VERSION}', $this->getModuleVersion(), $fileName);
        $fileName = str_replace('{SDK_VERSION}', self::SDK_VERSION, $fileName);

        // Detect if the filename as :// within it meaning it's an external URL
        if (strpos($fileName, '://') === false) {
            $cacheBust = '';
            if ($modifiedTime = $this->getAssetModifiedTime($fileName)) {
                $cacheBust = '?v=' . $modifiedTime;
            }
            return parent::getJsUrl($fileName) . $cacheBust;
        }

        return $fileName;
    }

    /**
     * Get the last time the file was modified
     *
     * @param $fileName
     *
     * @return bool|int
     */
    protected function getAssetModifiedTime($fileName)
    {
        $filePath = Mage::getBaseDir() . DS . 'js' . DS . ltrim($fileName, '/');
        if (file_exists($filePath)) {
            return filemtime($filePath);
        }

        return false;
    }

    /**
     * Determine whether or not assets are required for the current page
     *
     * @throws \Exception
     */
    protected function handleRequiresAssets()
    {
        // Build up the request string
        $request = $this->getRequest();
        $requestString =
            $request->getModuleName() . '_' . $request->getControllerName() . '_' . $request->getActionName();

        // Determine if we're viewing a product or cart and handle different logic
        if ($requestString == 'catalog_product_view') {
            return $this->checkAssetsForProduct();
        } elseif ($requestString == 'checkout_cart_index') {
            return $this->checkAssetsForCart();
        }

        // Otherwise assume the block has been included on the checkout
        return true;
    }

    /**
     * Do we need to include assets on the product view page?
     *
     * @return bool
     */
    protected function checkAssetsForProduct()
    {
        return Mage::helper('gene_braintree')->isExpressEnabled('catalog_product_view');
    }

    /**
     * Do we need to include assets on the cart page?
     *
     * @return bool
     */
    protected function checkAssetsForCart()
    {
        return Mage::helper('gene_braintree')->isExpressEnabled('checkout_cart_index');
    }

    /**
     * Determine whether setup is required to run int the admin or not
     *
     * @return bool
     */
    protected function isSetupRequiredInAdmin()
    {
        // First check if the method is enabled in the admin directly?
        if (Mage::helper('gene_braintree')->isSetupRequired()) {
            true;
        }

        // If it's not it might be enabled on a website level, payment methods cannot be disabled / enabled on store
        // level by in our extension.
        $websites = Mage::app()->getWebsites();
        /* @var $website Mage_Core_Model_Website */
        foreach ($websites as $website) {
            $defaultStoreId = $website->getDefaultStore()->getId();
            if (Mage::helper('gene_braintree')->isSetupRequired($defaultStoreId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does the module require setup and thus these assets?
     *
     * @return bool|string
     */
    protected function _toHtml()
    {
        // Handle the blocks inclusion differently in the admin
        if (Mage::app()->getStore()->isAdmin() && $this->isSetupRequiredInAdmin()) {
            return parent::_toHtml();
        } elseif (Mage::app()->getStore()->isAdmin()) {
            return false;
        }

        if (Mage::helper('gene_braintree')->isSetupRequired() && $this->handleRequiresAssets()) {
            return parent::_toHtml();
        }

        return false;
    }
}
