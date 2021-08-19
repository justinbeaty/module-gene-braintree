<?php

/**
 * Class Gene_Braintree_Kount_EnsController
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Kount_EnsController extends Mage_Core_Controller_Front_Action
{
    /**
     * Handle an incoming ENS request
     *
     * @return \Zend_Controller_Response_Abstract
     * @throws \Mage_Core_Exception
     */
    public function indexAction()
    {
        /* @var $ens Gene_Braintree_Model_Kount_Ens */
        $ens = Mage::getModel('gene_braintree/kount_ens');

        /* @var $http Mage_Core_Helper_Http */
        $http = Mage::helper('core/http');

        // Validate the IP address of the request
        if (!$ens->isValidEnsIp($http->getRemoteAddr())) {
            Gene_Braintree_Model_Debug::log('Invalid IP for ENS request: ' . $http->getRemoteAddr());
            Mage::throwException('Invalid ENS request.');
        }

        // Retrieve the XML sent in the HTTP POST request to the ResponseHandler
        $request = file_get_contents('php://input');
        if (!$request || strlen($request) == 0) {
            Mage::throwException('Invalid ENS request.');
        }

        // Log the ENS requests for later debugging
        Gene_Braintree_Model_Debug::log('Kount ENS Request:');
        Gene_Braintree_Model_Debug::log($request);

        try {
            // Parse the request into an array
            $xmlParser = new Mage_Xml_Parser();
            $events = $xmlParser->loadXML($request)->xmlToArray();

            // Ensure the events contain a value, and a merchant attribute
            if (!isset($events['events']['_value']) || !isset($events['events']['_attribute']['merchant'])) {
                Mage::throwException('Invalid ENS XML.');
            }

            // Validate the merchant ID against the Magento settings
            if (!$ens->validateStoreForMerchantId($events['events']['_attribute']['merchant'])) {
                Mage::throwException('Invalid Merchant ID provided.');
            }

        } catch (Exception $e) {
            Gene_Braintree_Model_Debug::log('Unable to parse ENS request into an array');
            Gene_Braintree_Model_Debug::log($e);
            Mage::throwException('Unable to parse ENS request into an array: ' . $e->getMessage());
        }

        $totalSuccess = 0;
        $totalFailed = 0;

        // Are we processing a single event?
        if (isset($events['events']['_value']['event']['name'])) {
            if ($ens->processEvent($events['events']['_value']['event'])) {
                ++$totalSuccess;
            } else {
                ++$totalFailed;
            }
        } else {
            // Or are there multiple events within the request?
            foreach ($events['events']['_value']['event'] as $event) {
                if ($ens->processEvent($event)) {
                    ++$totalSuccess;
                } else {
                    ++$totalFailed;
                }
            }
        }

        // Build an XML response for the ENS request
        $xmlResponse = <<<EXML
<?xml version="1.0" encoding="UTF-8"?>
<eventResponse successes="$totalSuccess" failures="$totalFailed">
</eventResponse>
EXML;

        // Send the response
        return $this->getResponse()
            ->clearHeaders()
            ->setHeader('Content-Type', 'text/xml')
            ->setBody($xmlResponse);
    }
}