<?php

/**
 * Class Gene_Braintree_SavedController
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_SavedController extends Mage_Core_Controller_Front_Action
{

    /**
     * Retrieve customer session object
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     * Validate that the user is logged in
     */
    public function preDispatch()
    {
        parent::preDispatch();

        if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $this->setFlag('', 'no-dispatch', true);
        }
    }

    /**
     * Show the listing page of saved payment information
     *
     * @return \Mage_Core_Controller_Varien_Action
     */
    public function indexAction()
    {
        $this->loadLayout();

        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');

        $this->getLayout()->getBlock('head')->setTitle($this->__('Saved Payment Information'));

        return $this->renderLayout();
    }

    /**
     * Action to allow users to delete payment methods
     *
     * @return Mage_Core_Controller_Varien_Action
     *
     * @throws Exception
     */
    public function removeAction()
    {
        // Init the payment method
        $paymentMethod = $this->_initPaymentMethod();

        // Method returns false if a redirect is going to occur
        if ($paymentMethod === false) {
            return false;
        }

        // Remove the payment method
        Braintree_PaymentMethod::delete($paymentMethod->token);

        // Inform the user of the great news
        $this->_getSession()->addSuccess($this->__('Saved payment has been successfully deleted.'));

        return $this->_redirectReferer();
    }

    /**
     * Allow a user to edit details of a payment method
     *
     * @return bool|\Mage_Core_Controller_Varien_Action
     */
    public function editAction()
    {
        // Init the payment method
        $paymentMethod = $this->_initPaymentMethod();

        // Method returns false if a redirect is going to occur
        if ($paymentMethod === false) {
            return false;
        }

        // Store the payment method in the registry
        Mage::register('current_payment_method', $paymentMethod);

        $this->loadLayout();

        $navigationBlock = $this->getLayout()->getBlock('customer_account_navigation');
        if ($navigationBlock) {
            $navigationBlock->setActive('braintree/saved');
        }

        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');

        $this->getLayout()->getBlock('head')->setTitle($this->__('Edit Payment Method'));

        return $this->renderLayout();
    }

    /**
     * Saving the payment methods update
     *
     * @return bool|\Mage_Core_Controller_Varien_Action
     */
    public function saveAction()
    {
        // Init the payment method
        $paymentMethod = $this->_initPaymentMethod();

        // Method returns false if a redirect is going to occur
        if ($paymentMethod === false) {
            return false;
        }

        // Build up our billing address array
        $billing = $this->getRequest()->getParam('billing');
        $billing['firstname'] = $this->getRequest()->getParam('firstname');
        $billing['middlename'] = $this->getRequest()->getParam('middlename');
        $billing['lastname'] = $this->getRequest()->getParam('lastname');

        // Create the Braintree address array
        $address = Mage::getModel('customer/address')->addData($billing);
        $braintreeAddress = Mage::helper('gene_braintree')->convertToBraintreeAddress($address);

        // Retrieve the new payment information
        $payment = $this->getRequest()->getParam('payment');

        // Build up the array of updates we're wanting to complete
        $updateMethod = array(
            'billingAddress' => $braintreeAddress,
            'expirationMonth' => $payment['cc_exp_month'],
            'expirationYear' => $payment['cc_exp_year']
        );

        try {
            // Update the payment method
            $result = Braintree_PaymentMethod::update($this->getRequest()->getParam('id'), $updateMethod);
            if ($result->success == true) {
                $this->_getSession()->addSuccess($this->__('The payment method has been updated successfully.'));
                return $this->_redirect('*/*/index');
            } else {
                $this->_getSession()->addSuccess($this->__('An error has occurred whilst updating your payment method: ' . $result->message));
                return $this->_redirect('*/*/index');
            }
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('An error has occurred whilst trying to update the payment method: %s', $e->getMessage()));
            return $this->_redirect('*/*/index');
        }

    }

    /**
     * Init the payment method
     *
     * @return \Mage_Core_Controller_Varien_Action|object
     */
    protected function _initPaymentMethod()
    {
        // Check we've received a payment ID
        $token = $this->getRequest()->getParam('id');
        if (!$token) {
            $this->_getSession()->addError($this->__('You have to select a saved payment method to conduct this action.'));

            $this->_redirect('braintree/saved/index');
            return false;
        }

        // Grab a new instance of the wrapper
        $wrapper = Mage::getSingleton('gene_braintree/wrapper_braintree');

        // Init the Braintree wrapper
        $wrapper->init();

        // Load the payment method from Braintree
        try {
            $paymentMethod = Braintree_PaymentMethod::find($token);
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('The requested payment method cannot be found.'));

            $this->_redirectReferer();
            return false;
        }

        // Check that this is the users payment method, we have to use a custom method as Braintree don't return the PayPal customer ID
        if (!$wrapper->customerOwnsMethod($paymentMethod)) {
            $this->_getSession()->addError($this->__('You do not have permission to modify this payment method.'));

            $this->_redirectReferer();
            return false;
        }

        return $paymentMethod;
    }

}