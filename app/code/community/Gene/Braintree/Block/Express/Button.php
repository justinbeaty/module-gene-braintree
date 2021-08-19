<?php

/**
 * Class Gene_Braintree_Block_Express_Button
 *
 * @author Dave Macaulay <braintreesupport@gene.co.uk>
 */
class Gene_Braintree_Block_Express_Button extends Gene_Braintree_Block_Express_Abstract
{
    const TYPE_CART = 'cart';
    const TYPE_CATALOG = 'catalog';

    /**
     * Registry entry to mark this block as instantiated
     *
     * @param string $html
     *
     * @return string
     */
    public function _afterToHtml($html)
    {
        if ($this->getExpressType() == self::TYPE_CART && $this->isEnabledCart()) {
            return $html;
        } elseif ($this->getExpressType() == self::TYPE_CATALOG && $this->isEnabledPdp()) {
            return $html;
        }

        return '';
    }

    /**
     * @return string
     */
    public function getButtonLocation()
    {
        if ($this->getRequest()->getControllerName() === 'product') {
            return 'pdp';
        }
        if ($this->getIsCart()) {
            return 'cart';
        }
        return 'checkout';
    }
}
