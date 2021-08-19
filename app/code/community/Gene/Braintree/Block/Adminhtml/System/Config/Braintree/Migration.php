<?php

/**
 * Class Gene_Braintree_Block_Adminhtml_System_Config_Braintree_Migration
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Adminhtml_System_Config_Braintree_Migration
    extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface
{
    /**
     * Render element html
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        // This option is only available if the migration can be ran
        if (!Mage::helper('gene_braintree')->canRunMigration()) {
            return '';
        }

        $useContainerId = $element->getData('use_container_id');
        return sprintf('<tr id="row_%s">
                <td class="label">
                    <strong id="%s">%s</strong>
                </td>
                <td class="value">
                    %s
                    <p class="note">
                        <span>'. $this->__('The migration tool allows you to import various settings, import customers and remove the legacy files from the Braintree_Payments extension.') . '</span>
                    </p>
                </td>
            </tr>',
            $element->getHtmlId(), $element->getHtmlId(), $element->getLabel(), $this->getMigrationHtml()
        );
    }

    /**
     * Return HTML to run the migration
     *
     * @return string
     */
    protected function getMigrationHtml()
    {
        return '<button type="button" class="scalable" onclick="return showMigration();">Run Migration Tool</button>';
    }
}
