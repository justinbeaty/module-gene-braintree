<?php

/**
 * Class Gene_Braintree_Block_Saved_Edit
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Saved_Edit extends Mage_Customer_Block_Address_Edit
{
    /**
     * @var null
     */
    protected $_address = null;

    /**
     * Set the _address to null after the parent has initialized
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $this->_address = null;
    }

    /**
     * Return the currently viewed payment method
     *
     * @return mixed
     */
    public function getPaymentMethod()
    {
        return Mage::registry('current_payment_method');
    }

    /**
     * Return the save URL
     *
     * @return string
     */
    public function getSaveUrl()
    {
        return $this->getUrl('*/*/save', array('_secure' => true));
    }

    /**
     * Return the back URL
     *
     * @return string
     */
    public function getBackUrl()
    {
        return $this->getUrl('*/*/index', array('_secure' => true));
    }

    /**
     * Return the Braintree address as a Magento address
     *
     * @return \Mage_Customer_Model_Address|null
     */
    public function getAddress()
    {
        if (is_null($this->_address)) {
            $paymentMethod = $this->getPaymentMethod();
            if (isset($paymentMethod->billingAddress)) {
                /* @var $billingAddress Braintree_Address */
                $billingAddress = $paymentMethod->billingAddress;
                $this->_address = Mage::helper('gene_braintree')->convertToMagentoAddress($billingAddress);
            }
        }

        return $this->_address;
    }

    /**
     * Return the correct title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->__('Edit Payment Method');
    }

    /**
     * Retrieve credit card expire months
     *
     * @return array
     */
    public function getCcMonths()
    {
        $months = $this->getData('cc_months');
        if (is_null($months)) {
            $months[0] = $this->__('Month');
            $months = array_merge($months, Mage::getSingleton('payment/config')->getMonths());
            $this->setData('cc_months', $months);
        }

        return $months;
    }

    /**
     * Retrieve credit card expire years
     *
     * @return array
     */
    public function getCcYears()
    {
        $years = $this->getData('cc_years');
        if (is_null($years)) {
            $years = Mage::getSingleton('payment/config')->getYears();
            $years = array(0 => $this->__('Year')) + $years;
            $this->setData('cc_years', $years);
        }

        return $years;
    }

    /**
     * Return the country ID
     *
     * @return mixed
     */
    public function getCountryId()
    {
        return $this->getAddress()->getCountry();
    }

}