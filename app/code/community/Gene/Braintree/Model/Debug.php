<?php

/**
 * Class Gene_Braintree_Model_Debug
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Debug extends Mage_Core_Model_Abstract
{

    /**
     * Is the debugging enabled?
     */
    const GENE_BRAINTREE_DEBUG = 'payment/gene_braintree/debug';

    /**
     * Where shall we store the debugging information
     */
    const GENE_BRAINTREE_DEBUG_FILE = 'gene_braintree.log';

    /**
     * Log any data passed to this method in the debug file
     *
     * @param $data
     */
    static public function log($data)
    {
        // Check the debug flag in the admin
        if(Mage::getStoreConfigFlag(self::GENE_BRAINTREE_DEBUG)) {

            // If the data is an exception convert it to a string
            if($data instanceof Exception) {
                $data = $data->getMessage() . $data->getTraceAsString();
            }

            // Use the built in logging function
            Mage::log($data, null, self::GENE_BRAINTREE_DEBUG_FILE, true);
        }
    }

}