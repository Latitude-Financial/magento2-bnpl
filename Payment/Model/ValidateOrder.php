<?php
/**
 * @package   LatitudeNew_Payment
 * @author    Lpay Team <integrationsupport@latitudefinancial.com>
 */

namespace LatitudeNew\Payment\Model;


use Magento\Sales\Model\ResourceModel\Order\Status\History\CollectionFactory;

class ValidateOrder
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var CollectionFactory
     */
    private $historyCollectionFactory;

    /**
     * @var \LatitudeNew\Payment\Helper\Data
     */
    protected $helper;

    public function __construct(
        CollectionFactory $historyCollectionFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \LatitudeNew\Payment\Helper\Data $helper
    ){
        $this->checkoutSession = $checkoutSession;
        $this->historyCollectionFactory = $historyCollectionFactory;
        $this->helper = $helper;
    }

    /**
     * @param paramArr Associative array of request parameters
     */
    function verifyResponse($paramArr, $apiSecret){
        $this->helper->log('****** VERIFYING API RESPONSE ******');
        //concat array excluding signature and white spaces
        $qs = 'token' . $paramArr['token'] . 'reference' . $paramArr['reference'] . 'message' . $paramArr['message'] . 'result' . $paramArr['result'];
        $this->helper->log('Query String: ' . $qs);
        
        $qs = preg_replace("/\s+/", "", $qs);
        $qs = base64_encode($qs);
    
        $fromResponse = $paramArr['signature'];
        $generated = hash_hmac('sha256', $qs, $apiSecret);

        $this->helper->log('Signature from response: ' . $fromResponse);
        $this->helper->log('Generated signature: ' . $generated);

        return $fromResponse == $generated;
    }
}