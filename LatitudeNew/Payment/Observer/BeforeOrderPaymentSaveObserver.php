<?php
/**
 * @package   LatitudeNew_Payment
 * @author    Lpay Team <integrationsupport@latitudefinancial.com>
 */
namespace LatitudeNew\Payment\Observer;

use Magento\Framework\Event\ObserverInterface;
use \LatitudeNew\Payment\Model\Latitudepay;
use \LatitudeNew\Payment\Model\Genoapay;

/**
 * BeforeOrderPaymentSaveObserver Observer
 */
class BeforeOrderPaymentSaveObserver implements ObserverInterface
{
    /**
     * Sets current instructions for bank transfer account
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $observer->getEvent()->getPayment();
        $instructionMethods = [
            Latitudepay::PAYMENT_METHOD_LATITUDEPAY_CODE,
            Genoapay::PAYMENT_METHOD_GENOAPAY_CODE
        ];
        if (in_array($payment->getMethod(), $instructionMethods)) {
            $payment->setAdditionalInformation(
                'instructions',
                $payment->getMethodInstance()->getInstructions()
            );
        }
    }
}
