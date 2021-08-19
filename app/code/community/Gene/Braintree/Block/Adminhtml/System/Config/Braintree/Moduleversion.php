<?php

/**
 * Class Gene_Braintree_Block_Adminhtml_System_Config_Braintree_Moduleversion
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Adminhtml_System_Config_Braintree_Moduleversion
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
                    <span id="module-version">%s</span>
                    <div id="recent-version">
                        <div style="padding: 8px 0;line-height: 16px;">
                            <img src="' . $this->getSkinUrl('images/rule-ajax-loader.gif') . '" align="left" style="margin-right: 3px;" /> ' . $this->__('Checking latest version...') . '
                        </div>
                    </div>
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
        $response = Mage::getConfig()->getModuleConfig('Gene_Braintree')->version;
        $response.= '
<script type="text/javascript">

// Define the module version in the window
var moduleVersion = \'' . Mage::getConfig()->getModuleConfig('Gene_Braintree')->version . '\';

// Once the dom has loaded make the checkout
document.observe("dom:loaded", function() {
    try {
        new Ajax.Request("https://braintree.gene.co.uk/", {
            method: "post", parameters: {version: moduleVersion},
            onCreate: function(response) {
                var t = response.transport;
                t.setRequestHeader = t.setRequestHeader.wrap(function(original, k, v) {
                    if (/^(accept|accept-language|content-language)$/i.test(k))
                        return original(k, v);
                    if (/^content-type$/i.test(k) &&
                        /^(application\/x-www-form-urlencoded|multipart\/form-data|text\/plain)(;.+)?$/i.test(v))
                        return original(k, v);
                    return;
                });
            },
            onSuccess: function(transport) {
                try {
                    var json = eval(\'(\' + transport.responseText + \')\');
                } catch(e){}

                // Is there a message to be displayed to the user?
                if(json.message) {
                    $(\'recent-version\').innerHTML = json.message;
                }

                if(json.latest == true) {
                    $(\'module-version\').setStyle({color: \'green\'});
                } else {
                    $(\'module-version\').setStyle({color: \'darkred\'});
                }
            },
            onFailure: function() {
                $(\'recent-version\').innerHTML = \'<span style="color:darkred;">Unable to check for updates</span>\';
            }
        });
    } catch(e) {
        $(\'recent-version\').innerHTML = \'<span style="color:darkred;">Unable to check for updates</span>\';
    }
});
</script>
        ';

        return $response;
    }
}
