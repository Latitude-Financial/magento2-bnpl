<?php
/**
 * @package   LatitudeNew_Payment
 * @author    Lpay Team <integrationsupport@latitudefinancial.com>
 */

namespace LatitudeNew\Payment\Block\Form;

/**
 * Block for Cash On Delivery payment method form
 */
class Latitudepay extends \LatitudeNew\Payment\Block\Form\AbstractInstruction
{
    /**
     * Cash on delivery template
     *
     * @var string
     */
    // @codingStandardsIgnoreStart
    protected $_template = 'form/latitude.phtml';
    // @codingStandardsIgnoreEnd
}
