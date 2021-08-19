<?php

/**
 * Class Gene_Braintree_Block_Applepay
 *
 * @author Dave Macaulay <dave@gene.co.uk>
 */
class Gene_Braintree_Block_Applepay extends Mage_Payment_Block_Form
{
    /**
     * Internal constructor. Set template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('gene/braintree/applepay.phtml');
    }

    /**
     * Generate and return a token
     *
     * @return mixed
     */
    public function getClientToken()
    {
        return Mage::getModel('gene_braintree/wrapper_braintree')->init()->generateToken();
    }
}
