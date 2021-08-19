<?php

/**
 * Class Gene_Braintree_Model_Entity_Setup
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Entity_Setup extends Mage_Eav_Model_Entity_Setup
{
    /**
     * As Mage::getStoreConfig isn't initialized properly in upgrade scripts we have to directly query the database for
     * the correct values.
     *
     * This doesn't have support for website level configuration settings, as they're not used in the upgrade script.
     *
     * @param     $path
     * @param int $storeId
     *
     * @return null|string
     */
    public function getStoreConfig($path, $storeId = 0)
    {
        if ($storeId instanceof Mage_Core_Model_Store) {
            $storeId = $storeId->getId();
        }

        $resource = Mage::getModel('core/resource');
        $dbRead = $resource->getConnection('core_read');
        $table = $resource->getTableName('core/config_data');

        // Select the config data directly from the database
        if ($storeId === 0) {
            $select = $dbRead->select()
                ->from($table, 'value')
                ->where('path = ?', $path)
                ->where('scope = ?', 'default')
                ->where('scope_id = ?', 0);
        } else {
            $select = $dbRead->select()
                ->from($table, 'value')
                ->where('path = ?', $path)
                ->where('scope = ?', 'stores')
                ->where('scope_id = ?', $storeId);
        }

        $result = $dbRead->fetchOne($select);
        if ($result) {
            return $result;
        }

        return null;
    }
}