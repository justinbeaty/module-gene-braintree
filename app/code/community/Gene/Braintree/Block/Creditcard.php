<?php

/**
 * Class Gene_Braintree_Block_Creditcard
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Creditcard extends Mage_Payment_Block_Form_Cc
{
    /**
     * We can use the same token twice
     *
     * @var bool
     */
    protected $_token = false;

    /**
     * Set the template
     */
    protected function _construct()
    {
        parent::_construct();

        // The system now only supports Hosted Fields
        $this->setTemplate('gene/braintree/creditcard/hostedfields.phtml');
    }

    /**
     * Can we save the card?
     *
     * @return bool
     */
    protected function canSaveCard()
    {
        // Validate that the vault is enabled and that the user is either logged in or registering
        if ($this->getMethod()->isVaultEnabled()
            && (Mage::getSingleton('customer/session')->isLoggedIn()
                || Mage::getSingleton('checkout/type_onepage')->getCheckoutMethod() == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER)
        ) {
            return true;
        }

        // Is the vault enabled, and is the transaction occuring in the admin?
        if ($this->getMethod()->isVaultEnabled() && Mage::app()->getStore()->isAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Does this customer have saved accounts?
     *
     * @return mixed
     */
    public function hasSavedDetails()
    {
        if (Mage::getSingleton('customer/session')->isLoggedIn() || Mage::app()->getStore()->isAdmin()) {
            if ($this->getSavedDetails()) {
                return sizeof($this->getSavedDetails());
            }
        }

        return false;
    }

    /**
     * Return the saved accounts
     *
     * @return array
     */
    public function getSavedDetails()
    {
        if (!$this->_savedDetails) {
            $this->_savedDetails = Mage::getSingleton('gene_braintree/saved')->getSavedMethodsByType(Gene_Braintree_Model_Saved::SAVED_CREDITCARD_ID);
        }

        return $this->_savedDetails;
    }

    /**
     * Get the saved child HTML
     *
     * @return string
     */
    public function getSavedChildHtml()
    {
        $html = $this->getChildHtml('saved', false);
        $this->unsetChild('saved');

        return $html;
    }

    /**
     * is 3D secure enabled?
     *
     * @return mixed
     */
    protected function is3DEnabled()
    {
        return Mage::getModel('gene_braintree/paymentmethod_creditcard')->is3DEnabled();
    }

    /**
     * Return the original CC types
     *
     * @return array
     */
    public function getOriginalCcAvailableTypes()
    {
        return parent::getCcAvailableTypes();
    }

    /**
     * Convert the available types into something
     *
     * @return string
     */
    public function getCcAvailableTypes()
    {
        // Collect the types from the core method
        $types = parent::getCcAvailableTypes();

        // Grab the keys and encode
        return json_encode(array_keys($types));
    }

    /**
     * Return the card icon
     *
     * @param $cardType
     *
     * @return string
     */
    static public function getCardIcon($cardType)
    {
        // Convert the card type to lower case, no spaces
        switch (str_replace(' ', '', strtolower($cardType))) {
            case 'mastercard':
                return 'MC.png';
                break;
            case 'visa':
                return 'VI.png';
                break;
            case 'americanexpress':
            case 'amex':
                return 'AE.png';
                break;
            case 'discover':
                return 'DI.png';
                break;
            case 'jcb':
                return 'JCB.png';
                break;
            case 'maestro':
                return 'ME.png';
                break;
            case 'paypal':
                return 'PP.png';
                break;
        }

        // Otherwise return the standard card image
        return 'card.png';
    }

    /**
     * Generate and return a token
     *
     * @return mixed
     */
    protected function getClientToken()
    {
        if (!$this->_token) {
            $this->_token = Mage::getSingleton('gene_braintree/wrapper_braintree')->init()->generateToken();
        }

        return $this->_token;
    }

    /**
     * Config setting to show accepted cards on the checkout
     *
     * @return boolean
     */
    protected function showAcceptedCards()
    {
        return Mage::getModel('gene_braintree/paymentmethod_creditcard')->getConfigData('display_cctypes');
    }

    /**
     * Allowed payment cards
     *
     * @return array
     */
    protected function getAllowedCards()
    {
        $allowed = explode(",", Mage::getModel('gene_braintree/paymentmethod_creditcard')->getConfigData('cctypes'));
        $cards = array();

        foreach (Mage::getSingleton('payment/config')->getCcTypes() as $code => $name) {
            if (in_array($code, $allowed) && $code != 'OT') {
                $cards[] = array(
                    'value' => $code,
                    'label' => $name
                );
            }
        }

        return $cards;
    }

    /**
     * Hosted fields descriptor
     *
     * @return string
     */
    protected function getHostedDescriptor()
    {
        return Mage::getModel('gene_braintree/paymentmethod_creditcard')->getConfigData('hostedfields_descriptor');
    }

}