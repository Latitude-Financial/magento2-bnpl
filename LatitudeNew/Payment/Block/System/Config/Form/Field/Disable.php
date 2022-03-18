<?php
/**
 * @package   LatitudeNew_Payment
 * @author    Lpay Team <integrationsupport@latitudefinancial.com>
 */
namespace LatitudeNew\Payment\Block\System\Config\Form\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Disable extends \Magento\Config\Block\System\Config\Form\Field
{
	// @codingStandardsIgnoreStart
    protected function _getElementHtml(AbstractElement $element)
    {
    // @codingStandardsIgnoreEnd
        $element->setDisabled('disabled');
        return $element->getElementHtml();
    }
}
