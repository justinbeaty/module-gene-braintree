<?php

/**
 * Class Gene_Braintree_Block_Adminhtml_System_Config_Braintree_Version
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Adminhtml_System_Config_Braintree_Version
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
        $useContainerId = $element->getData('use_container_id');
        return sprintf('<tr id="row_%s">
                <td class="label">
                    <strong id="%s">%s</strong>
                </td>
                <td class="value">
                    %s
                </td>
            </tr>',
            $element->getHtmlId(), $element->getHtmlId(), $element->getLabel(), $this->getVersionHtml()
        );
    }

    /**
     * Inform the user there version will not work
     * @return string
     */
    protected function getVersionHtml()
    {
        if(@class_exists('Braintree_Version')) {
            $version = Braintree_Version::get();
            if ($version < 2.32) {
                return '
                <span style="color: red;">' . $version . '</span><br />
                <small>
                    <strong>Warning:</strong> Our payment methods will not work with a version of the Braintree lib files older than 2.32.0. You\'ll have to upgrade, please download the newer version <a href="https://developers.braintreepayments.com/javascript+php/sdk/server/setup">here</a>. Once you\'ve downloaded it please replace the file <strong>/lib/Braintree.php</strong> and the folder <strong>/lib/Braintree/</strong> with the newer versions within the archive.
                </small>';
            } else {
                return '<span style="color: green;">' . $version . '</span>';
            }
        } else {
            return '<span style="color: red;font-weight: bold;">Not Installed</span>';
        }
    }
}
