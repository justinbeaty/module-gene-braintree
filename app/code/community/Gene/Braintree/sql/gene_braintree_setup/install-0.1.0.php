<?php

/* @var $installer Gene_Braintree_Model_Entity_Setup */
$installer = $this;
$installer->startSetup();

$entityTypeId     = $installer->getEntityTypeId('customer');
$attributeSetId   = $installer->getDefaultAttributeSetId($entityTypeId);
$attributeGroupId = $installer->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

// Add in a new attribute for the braintree's customer ID
// This is generated and stored Magento side and is used for stored details
$installer->addAttribute('customer', 'braintree_customer_id', array(
    'input'         => 'text',
    'type'          => 'varchar',
    'label'         => 'Generated Braintree Customer Account ID',
    'visible'       => 0,
    'required'      => 0,
    'user_defined' => 1,
));

// Add the attribute into the group
$installer->addAttributeToGroup(
    $entityTypeId,
    $attributeSetId,
    $attributeGroupId,
    'braintree_customer_id',
    '999'
);

$installer->endSetup();