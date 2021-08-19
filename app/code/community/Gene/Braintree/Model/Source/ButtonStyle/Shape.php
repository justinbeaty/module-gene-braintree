<?php

/**
 * Class Gene_Braintree_Model_Source_ButtonStyle_Shape
 *
 * @author Craig Newbury <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Source_ButtonStyle_Shape
{

    const SHAPE_PILL = 'pill';
    const SHAPE_RECTANGLE = 'rect';

    /**
     * Possible actions on order place
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => self::SHAPE_PILL,
                'label' => Mage::helper('gene_braintree')->__('Pill')
            ),
            array(
                'value' => self::SHAPE_RECTANGLE,
                'label' => Mage::helper('gene_braintree')->__('Rectangle (Recommended)')
            ),
        );
    }
}
