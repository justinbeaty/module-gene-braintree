<?php

/**
 * Class Gene_Braintree_Block_Googlepay_Setup
 */
class Gene_Braintree_Block_Googlepay_Setup extends Mage_Core_Block_Template
{
    use Gene_Braintree_Traits_PaymentMethods;

    /**
     * @return bool
     */
    public function isActive()
    {
        /* @var $model Gene_Braintree_Model_Paymentmethod_Googlepay */
        $model = Mage::getModel('gene_braintree/paymentmethod_googlepay');
        return $model->isAvailable();
    }

    /**
     * @return mixed
     */
    public function getGoogleMerchantAccountID()
    {
        return Mage::getStoreConfig('payment/gene_braintree_googlepay/merchant_account_id');
    }

    /**
     * @return string
     */
    public function getAllowedCardNetworks()
    {
        $allowedCardNetworks = Mage::getStoreConfig('payment/gene_braintree_googlepay/accepted_cards');

        if ($allowedCardNetworks) {
            $allowedCardNetworks = explode(',', $allowedCardNetworks);
            $allowedCardNetworks = '["' . implode('", "', $allowedCardNetworks) . '"]';
            return $allowedCardNetworks;
        }

        return '';
    }
}
