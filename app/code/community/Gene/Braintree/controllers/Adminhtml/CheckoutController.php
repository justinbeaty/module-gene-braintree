<?php

class Gene_Braintree_Adminhtml_CheckoutController extends Mage_Adminhtml_Controller_Action
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
     * Return a client token to the browser
     *
     * @return \Gene_Braintree_CheckoutController
     */
    public function clientTokenAction()
    {
        try {
            return $this->_returnJson(array(
                'success' => true,
                'client_token' => Mage::getSingleton('gene_braintree/wrapper_braintree')->init()->generateToken()
            ));
        } catch (Exception $e) {
            return $this->_returnJson(array(
                'success' => false,
                'error' => $e->getMessage()
            ));
        }
    }
}
