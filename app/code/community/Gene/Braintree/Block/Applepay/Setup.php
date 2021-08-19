<?php

/**
 * Class Gene_Braintree_Block_Applepay_Setup
 *
 * @author Dave Macaulay <dave@gene.co.uk>
 */
class Gene_Braintree_Block_Applepay_Setup extends Mage_Core_Block_Template
{
    /**
     * Is Apple Pay active?
     *
     * @return bool
     */
    public function isActive()
    {
        return Mage::getModel('gene_braintree/paymentmethod_applepay')->isAvailable();
    }

    /**
     * If one of the other methods is available than core setup is already present, otherwise we'll need to manually
     * initialise vzero
     *
     * @return bool
     */
    public function isCoreSetupPresent()
    {
        return Mage::getModel('gene_braintree/paymentmethod_creditcard')->isAvailable()
            || Mage::getModel('gene_braintree/paymentmethod_paypal')->isAvailable();
    }

    /**
     * Generate and return a token
     *
     * @return mixed
     */
    protected function getClientToken()
    {
        return Mage::getSingleton('gene_braintree/wrapper_braintree')->init()->generateToken();
    }

    /**
     * Generate url by route and parameters
     *
     * @param   string $route
     * @param   array $params
     * @return  string
     */
    public function getUrl($route = '', $params = array())
    {
        // Always force secure on getUrl calls
        if (!isset($params['_forced_secure'])) {
            $params['_forced_secure'] = true;
        }

        return parent::getUrl($route, $params);
    }

    /**
     * Only render if the payment method is active
     *
     * @return string
     */
    protected function _toHtml()
    {
        // Check the payment method is active, block duplicate rendering of this block
        if (($this->isActive()) && !Mage::registry('gene_applepay_' . $this->getTemplate())) {
            Mage::register('gene_applepay_' . $this->getTemplate(), true);

            return parent::_toHtml();
        }

        return '';
    }
}
