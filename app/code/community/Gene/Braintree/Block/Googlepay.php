<?php

/**
 * Class Gene_Braintree_Block_Googlepay
 *
 * @author Paul Canning <paul.canning@gene.co.uk>
 */
class Gene_Braintree_Block_Googlepay extends Mage_Payment_Block_Form
{
    /**
     * Class Construct
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('gene/braintree/googlepay.phtml');
    }
}
