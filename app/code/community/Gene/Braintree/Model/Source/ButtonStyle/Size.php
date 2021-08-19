<?php

/**
 * Class Gene_Braintree_Model_Source_ButtonStyle_Size
 *
 * @author Craig Newbury <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Model_Source_ButtonStyle_Size
{

    const SIZE_MEDIUM       = 'medium';
    const SIZE_LARGE        = 'large';
    const SIZE_RESPONSIVE   = 'responsive';

    /**
     * Possible actions on order place
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => self::SIZE_MEDIUM,
                'label' => Mage::helper('gene_braintree')->__('Medium')
            ),
            array(
                'value' => self::SIZE_LARGE,
                'label' => Mage::helper('gene_braintree')->__('Large')
            ),
            array(
                'value' => self::SIZE_RESPONSIVE,
                'label' => Mage::helper('gene_braintree')->__('Responsive (Recommended)')
            ),
        );
    }
}
