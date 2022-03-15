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

    public function __construct(
        CollectionFactory $historyCollectionFactory,
        \Magento\Checkout\Model\Session $checkoutSession
    ){
        $this->checkoutSession = $checkoutSession;
        $this->historyCollectionFactory = $historyCollectionFactory;
    }

    /**
     * @param paramArr Associative array of request parameters
     */
    function verifyResponse($paramArr, $apiSecret){
        //concat array excluding signature and white spaces
        $qs = 'token' . $paramArr['token'] . 'reference' . $paramArr['reference'] . 'message' . $paramArr['message'] . 'result' . $paramArr['result'];
        $qs = preg_replace("/\s+/", "", $qs);
        $qs = base64_encode($qs);
    
        $fromResponse = $paramArr['signature'];
        $generated = hash_hmac('sha256', $qs, $apiSecret);
    
        return $fromResponse == $generated;
    }
}