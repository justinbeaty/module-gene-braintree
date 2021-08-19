<?php

/**
 * Class Gene_Braintree_Block_Adminhtml_System_Config_Braintree_Config
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Adminhtml_System_Config_Braintree_Config
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
        return sprintf('<tr id="row_%s">
                <td class="label">
                    <strong id="%s">%s</strong>
                </td>
                <td class="value">
                    %s
                </td>
            </tr>',
            $element->getHtmlId(), $element->getHtmlId(), $element->getLabel(), $this->getValidConfigHtml()
        );
    }

    /**
     * Inform the user there version will not work
     * @return string
     */
    protected function getValidConfigHtml()
    {
        $response = Mage::getModel('gene_braintree/wrapper_braintree')->validateCredentials(true);
        $response.= '
<script type="text/javascript">

// Set the config timeout
var configTimeout;

// Make a request to the server and validate the configuration options
function checkConfig() {

    // Clear any timeout already set
    clearTimeout(configTimeout);

    // Place a loading gif into the config area
    $(\'row_payment_gene_braintree_valid_config\').down(\'td.value\').innerHTML = \'<div style="line-height: 18px;margin: 6px 0;"><img src="' . $this->getSkinUrl('images/gene/loader.gif') . '" height="18" style="float: left;margin-right: 8px;" /> '. $this->__('Validating Credentials...') . '</center>\';

    // Defined the configTimeout
    configTimeout = setTimeout(function() {
        // Make the request
        new Ajax.Request(
            "' . Mage::helper('adminhtml')->getUrl('adminhtml/braintree/validateConfig') . '",
            {
                method: "post",
                onCreate: function (response) {
                    // Disable the annoying loading modal
                    Ajax.Responders.unregister(varienLoaderHandler.handler);
                },
                onSuccess: function(transport){
                    $(\'row_payment_gene_braintree_valid_config\').down(\'td.value\').innerHTML = transport.responseText;
                },
                parameters: Form.serializeElements($$(\'.validate-config\'))
            }
        );
    }, 800);
}

// Observe the relevant form elements
$$(\'input.validate-config\').each(function(element) {
    Event.observe(element, \'keyup\', function() {
        checkConfig();
    });
});
$$(\'select.validate-config\').each(function(element) {
    Event.observe(element, \'change\', function() {
        checkConfig();
    });
});
</script>';
        return $response;
    }
}
