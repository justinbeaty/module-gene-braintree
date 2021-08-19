<?php

/**
 * Class Gene_Braintree_Model_Source_ButtonStyle_Layout
 *
 * @author Craig Newbury <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Source_ButtonStyle_Layout
{

    const LAYOUT_VERTICAL = 'vertical';
    const LAYOUT_HORIZONTAL = 'horizontal';


    /**
     * Possible actions on order place
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => self::LAYOUT_VERTICAL,
                'label' => Mage::helper('gene_braintree')->__('Vertical (All buttons)')
            ),
            array(
                'value' => self::LAYOUT_HORIZONTAL,
                'label' => Mage::helper('gene_braintree')->__('Horizontal (Max 2 Buttons)')
            ),
        );
    }
}
