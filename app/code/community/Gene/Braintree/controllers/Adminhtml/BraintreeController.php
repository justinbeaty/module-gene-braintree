<?php

/**
 * Class Gene_Braintree_Adminhtml_BraintreeController
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Adminhtml_BraintreeController extends Mage_Adminhtml_Controller_Action
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
     * Settlement report from Braintree
     */
    public function transactionsAction()
    {
        $this->loadLayout();

        $this->_title(Mage::helper('gene_braintree')->__('Braintree Transactions'));
        $this->_setActiveMenu('report/braintree_transactions');

        $this->renderLayout();
    }

    /**
     * Prepare the export into the browser
     *
     * @param string $type
     *
     * @return bool
     */
    protected function _prepareExport($type = 'csv')
    {
        // Validate the search query session is set
        if($searchQuery = Mage::getSingleton('adminhtml/session')->getBraintreeSearchQuery()) {

            // Grab the grid block
            $grid = $this->getLayout()->createBlock('gene_braintree/adminhtml_report_transactions_grid');

            // Set the search query within the grid
            $grid->setSearchQuery($searchQuery);

            // Force the file to download in the browser
            $this->_prepareDownloadResponse('braintree-transactions.' . $type, ($type == 'xml' ? $grid->getExcelFile() : $grid->getCsvFile()));

            return false;
        }

        // Otherwise take them back
        $this->_redirectReferer();
    }

    /**
     * Process a request to export the current transactions to a CSV
     */
    public function exportCsvAction()
    {
        return $this->_prepareExport('csv');
    }

    /**
     * Process a request to export the current transaction to an Excel Document
     */
    public function exportExcelAction()
    {
        return $this->_prepareExport('xml');
    }

    /**
     * Validate the inputted configuration via Ajax
     */
    public function validateConfigAction()
    {
        // Grab the post data from the request
        $postData = Mage::app()->getRequest()->getPost();

        // Assign the merchant account ID to false
        $merchantAccountId = false;

        // Check the form contains the valid data we need
        if(isset($postData['groups']['gene_braintree']['fields'])) {

            // Assign it for easy access
            $braintreeConfig = $postData['groups']['gene_braintree']['fields'];

            // Validate the required variables are set before trying to access them
            if(isset($braintreeConfig['environment']) ) {

                // Required fields to validate
                if( $braintreeConfig['environment']['value'] == 'production' && isset($braintreeConfig['merchant_id']) && isset($braintreeConfig['public_key']) && isset($braintreeConfig['private_key']) ||
                    $braintreeConfig['environment']['value'] == 'sandbox' && isset($braintreeConfig['sandbox_merchant_id']) && isset($braintreeConfig['sandbox_public_key']) && isset($braintreeConfig['sandbox_private_key']) ) {

                    // Setup the various configuration variables
                    Braintree\Configuration::environment($braintreeConfig['environment']['value']);
                    Braintree\Configuration::sslVersion(6);

                    // Production keys
                    if ($braintreeConfig['environment']['value'] == 'production') {
                        Braintree\Configuration::merchantId($braintreeConfig['merchant_id']['value']);
                        Braintree\Configuration::publicKey($braintreeConfig['public_key']['value']);
                        Braintree\Configuration::privateKey($braintreeConfig['private_key']['value']);
                        $merchantAccountId = (isset($braintreeConfig['merchant_account_id']['value']) ? $braintreeConfig['merchant_account_id']['value'] : false);
                    } else if ($braintreeConfig['environment']['value'] == 'sandbox') {
                        Braintree\Configuration::merchantId($braintreeConfig['sandbox_merchant_id']['value']);
                        Braintree\Configuration::publicKey($braintreeConfig['sandbox_public_key']['value']);
                        Braintree\Configuration::privateKey($braintreeConfig['sandbox_private_key']['value']);
                        $merchantAccountId = (isset($braintreeConfig['sandbox_merchant_account_id']['value']) ? $braintreeConfig['sandbox_merchant_account_id']['value'] : false);
                    }
                }
            }
        }

        // Do the validation within the wrapper
        Mage::app()->getResponse()->setBody(Mage::getModel('gene_braintree/wrapper_braintree')->validateCredentials(
            true,
            true,
            $merchantAccountId
        ));
    }

}
