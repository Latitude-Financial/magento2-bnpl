<?php
/**
 * @package   LatitudeNew_Payment
 * @author    Lpay Team <integrationsupport@latitudefinancial.com>
 */
namespace LatitudeNew\Payment\Controller\LCorder;

/**
 * Latitude Callback Controller
 */
class Callback extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \LatitudeNew\Payment\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $sendEmail;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $orderFactory;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $transaction;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
    /**
     * @var \LatitudeNew\Payment\Model\ValidateOrder
     */
    private $latitudeApi;

    protected $quoteFactory;

    /**
     * Construct
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \LatitudeNew\Payment\Helper\Data $helper
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $sendEmail
     * @param \Magento\Sales\Model\Order $orderFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param \LatitudeNew\Payment\Model\Api $latitudeApi
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \LatitudeNew\Payment\Helper\Data $helper,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $sendEmail,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \LatitudeNew\Payment\Model\Api $latitudeApi,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Model\QuoteFactory $quoteFactory
    ) {
        parent::__construct($context);
        $this->helper = $helper;
        $this->sendEmail = $sendEmail;
        $this->scopeConfig = $scopeConfig;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->checkoutSession = $checkoutSession;
        $this->latitudeApi = $latitudeApi;
        $this->quoteFactory = $quoteFactory;
    }

    /**
     * Save Transaction
     *
     * @param \Magento\Sales\Model\Order $order
     * @param Array $param - request parameters
     * @return void
     */
    protected function saveTransaction($order, $verifyResponse) {
        $this->helper->log('****** SAVING LC INSTANT/SALE TRANSACTION ******', 'latitude');
        //change state from new to processing
        $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
       
        //Set transaction id
        $payment = $order->getPayment();
        $payment->setTransactionId($verifyResponse->transactionReference);
        $payment->setAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::TXN_ID , $verifyResponse->transactionReference);
        $payment->setAdditionalInformation('gateway_reference', $verifyResponse->gatewayReference);

        //add transaction
        $transaction = $payment->addTransaction(
            \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE,
            null,        //salesDocument, default null
            false        //failSafe, default false
        );
        $transaction->setIsClosed(0);
        $transaction->save();
        
        //$latitudeRef = $order->getCustomerNote();
        //add comment along and update status to processing
        $order->addStatusToHistory(
            \Magento\Sales\Model\Order::STATE_PROCESSING,                                                   //status   
            "Latitude Checkout INSTANT payment of $".$verifyResponse->amount." ".$verifyResponse->result." Transaction Ref: ".$verifyResponse->transactionReference." Transaction Type: ".$verifyResponse->transactionType." Gateway Ref: ".$verifyResponse->gatewayReference ,     //comment, default ''
            true                                                                                            //isCustomerNotified, default false
        );
        $order->save();

        $this->sendEmail->send($order, true); //forceSyncMode default false, true = Email will be sent immediately
    }

    /**
     * Save Transaction
     *
     * @param \Magento\Sales\Model\Order $order
     * @param Array $param - request parameters
     * @return void
     */
    protected function saveDeferredTransaction($order, $verifyResponse) {
        $this->helper->log('****** SAVING LC DEFERRED/AUTHORIZATION TRANSACTION ******', 'latitude');
        //change state from new to processing
        $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
       
        //Set transaction id
        $payment = $order->getPayment();
        $payment->setTransactionId($verifyResponse->transactionReference);
        $payment->setAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::TXN_ID , $verifyResponse->transactionReference);
        $payment->setAdditionalInformation('gateway_reference', $verifyResponse->gatewayReference);

        //add transaction
        $transaction = $payment->addTransaction(
            \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH,
            null,        //salesDocument, default null
            false        //failSafe, default false
        );
        $transaction->setIsClosed(0);
        $transaction->save();
        
        // $latitudeRef = $order->getCustomerNote();
        //add comment along and update status to processing
        $order->addStatusToHistory(
            \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT,                                                   //status   
            "Latitude Checkout DEFERRED payment of $".$verifyResponse->amount." ".$verifyResponse->result." Transaction Ref: ".$verifyResponse->transactionReference." Transaction Type: ".$verifyResponse->transactionType." Gateway Ref: ".$verifyResponse->gatewayReference ,     //comment, default ''
            true                                                                                            //isCustomerNotified, default false
        );
        $order->save();

        $this->sendEmail->send($order, true); //forceSyncMode default false, true = Email will be sent immediately
    }

    protected function onFail($verifyResponse, $order){
        $this->helper->log('****** LC FAILED URL ******', 'latitude');
        $quote = $this->quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());
        if ($quote->getId()) {
            //reactivate quote but remove reservedID, it will be assigned again on 'Place Order' click
            $quote->setIsActive(1)->setReservedOrderId(null)->save();

            //retrieve all sessions
            $this->checkoutSession->replaceQuote($quote);
            $this->checkoutSession->setQuoteId($order->getQuoteId());
            $this->checkoutSession->setLastOrderId($order->getId());
            //supposed to be used by getLastRealOrder() to retrieve this order in handoverurl/index.php 
            //https://www.magentoextensions.org/documentation/class_magento_1_1_checkout_1_1_model_1_1_session.html#a858077761ba432c5496e062867a53542
            //but PlaceOrder in the method-renderer would override this and create new order anyway
            $this->checkoutSession->setLastRealOrderId($order->getIncrementId()); 

            //Set transaction id
            $payment = $order->getPayment();
            $payment->setTransactionId($verifyResponse->transactionReference);

            //add transaction
            $transaction = $payment->addTransaction(
                \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, //https://community.magento.com/t5/Magento-DevBlog/The-Magento-Sale-Payment-Operation/ba-p/67251#:~:text=Capture%20transaction%20%E2%80%93%20a%20transaction%20that,on%20the%20customer's%20credit%20card.
                null,
                false                
            );
            $transaction->setAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::ADDITIONAL_INFORMATION,'Quote ID: '.$order->getQuoteId());
            $transaction->setIsClosed(0);
            $transaction->save();

            //add comment and update status to cancelled
            $order->addStatusToHistory(
                \Magento\Sales\Model\Order::STATE_CANCELED,             //status   
                $verifyResponse->message." Amount: ".$verifyResponse->amount." Result: ".$verifyResponse->result.' Transaction Ref: '.$verifyResponse->transactionReference." Transaction Type: ".$verifyResponse->transactionType." Gateway Ref: ".$verifyResponse->gatewayReference ,     //comment, default ''
                false                                                   //isCustomerNotified, default false
            );            
            $order->save();
          
            //setup redirect to cart
            $this->messageManager->addErrorMessage($verifyResponse->message);
            $this->_redirect('checkout/cart');
        }
    }

    function verifySignature($str, $apiSecret, $signature)
    {
        $base64 = base64_encode($str);
        return $signature === hash_hmac('sha256', $base64, $apiSecret);
    }

    protected function onCancel($order_id){
        $this->helper->log('****** LC CANCEL URL ******', 'latitude');

        $order =  $this->helper->getOrderByIncrementId($order_id);
        $quote = $this->quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());

        if ($quote->getId()) {
            //reactivate quote but remove reservedID, it will be assigned again on 'Place Order' click
            $quote->setIsActive(1)->setReservedOrderId(null)->save();

            //retrieve all sessions
            $this->checkoutSession->replaceQuote($quote);
            $this->checkoutSession->setQuoteId($order->getQuoteId());
            $this->checkoutSession->setLastOrderId($order->getId());
            //supposed to be used by getLastRealOrder() to retrieve this order in handoverurl/index.php 
            //https://www.magentoextensions.org/documentation/class_magento_1_1_checkout_1_1_model_1_1_session.html#a858077761ba432c5496e062867a53542
            //but PlaceOrder in the method-renderer would override this and create new order anyway
            $this->checkoutSession->setLastRealOrderId($order->getIncrementId()); 
          
            //setup redirect to cart
            $this->helper->log('Returned to cart from gateway', 'latitude');
            $this->messageManager->addErrorMessage('Returned to cart from gateway');
            $this->_redirect('checkout/cart');
        }
    }
    
    public function execute() {
        $this->helper->log('****** LC CALLBACK METHOD TRIGGERED ******', 'latitude');

        //The latitude_sf gateway sends back these data for verification process
        $gatewayReference = $this->getRequest()->getParam('gatewayReference');
        $order_id = $this->getRequest()->getParam('merchantReference');
        $transactionReference = $this->getRequest()->getParam('transactionReference'); 
        $signature = $this->getRequest()->getParam('signature');

        //make sure one of the required set of params is present
        if ((!$gatewayReference || !$order_id || !$transactionReference) && (!$signature || !$order_id)){
            $this->_redirect('checkout/onepage/failure');
            $this->messageManager->addWarningMessage('One or more parameter is missing');
            return;
        }

        //if signature and order_id present, verify signature
        //if pass, trigger cancel sequence (retrieve session and send to cart) 
        //if not, send to fail page
        if ($signature && $order_id) { 
            $merchantSk = $this->helper->getConfigData('merchant_secret', null, 'latitude');

            if ($this->verifySignature("merchantReference=$order_id", $merchantSk, $signature)){
                $this->onCancel($order_id);
                return;
            }

            //in case invalid signature
            $this->_redirect('checkout/onepage/failure');
            $this->messageManager->addWarningMessage('Invalid request');
            return;
        }

        $verifyResponse = $this->latitudeApi->verifyLCPurchase($order_id, $transactionReference, $gatewayReference);
        
        //validate query param, if mismatch, send back to cart with warning message
        if($verifyResponse->status !== 200)
        {
            $this->helper->log("Error verifying purchase with status: $verifyResponse->status", 'latitude');
            $this->_redirect('checkout/onepage/failure');
            $this->messageManager->addWarningMessage("Invalid purchase information");
            return;
        }

        //CAVEAT: added second argument too if in case there is a message that has no result, this may never be executed
        if ($verifyResponse->message !== "" && $verifyResponse->result !== 'failed'){
            $this->session->data['error'] = $verifyResponse->message;
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }

        //get order object
        $order =  $this->helper->getOrderByIncrementId($order_id);

        if ($order) {
            switch ($verifyResponse->result) {
                case 'completed':
                    $this->helper->log('****** LC SUCCESS URL ******', 'latitude');

                    //only process success scenario when status is still pending approval (default of "Place Order" click)
                    if ($verifyResponse->transactionType === 'sale' && $order->getStatus() === 'pending_latitude_approval') {
                        $this->helper->log('****** LC SUCCESS INSTANT/SALE ******', 'latitude');
                        $this->saveTransaction($order, $verifyResponse);
                        if ($order->canInvoice()) {
                            //create invoice
                            $invoice = $this->invoiceService->prepareInvoice($order);
                            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                            $invoice->register();
                            $invoice->save();

                            //save transaction to db with associated invoice and order object
                            $transactionSave = $this->transaction->addObject(
                                $invoice
                            )->addObject(
                                $invoice->getOrder()
                            );
                            $transactionSave->save();

                            //add new line of comment regarding invoicing, notify customer
                            $order->addStatusHistoryComment(
                                __('Notified customer about invoice #%1.', $invoice->getId())
                            )
                            ->setIsCustomerNotified(true)
                            ->save();
                        }
                        if ($order) {
                            //update session's quote and order info
                            $this->checkoutSession
                            ->setLastQuoteId($order->getQuoteId())
                            ->setLastSuccessQuoteId($order->getQuoteId());
    
                            $this->checkoutSession->setLastOrderId($order->getId())
                            ->setLastRealOrderId($order->getIncrementId())
                            ->setLastOrderStatus($order->getStatus());
    
                            $this->_redirect('checkout/onepage/success');
                        }
                    }
                    else if ($verifyResponse->transactionType === 'authorization' && $order->getStatus() === 'pending_latitude_approval'){
                        $this->helper->log('****** LC SUCCESS INSTANT/SALE ******', 'latitude');
                        $this->saveDeferredTransaction($order, $verifyResponse);
                        if ($order) {
                            //update session's quote and order info
                            $this->checkoutSession
                            ->setLastQuoteId($order->getQuoteId())
                            ->setLastSuccessQuoteId($order->getQuoteId());
    
                            $this->checkoutSession->setLastOrderId($order->getId())
                            ->setLastRealOrderId($order->getIncrementId())
                            ->setLastOrderStatus($order->getStatus());
    
                            $this->_redirect('checkout/onepage/success');
                        }
                    }
                    else {
                        $this->helper->log('****** Invalid order update request ******', 'latitude');
                        $this->messageManager->addWarningMessage('Invalid order update request');
                        $this->_redirect('checkout/onepage/failure');
                    }
                    break;
                case 'failed':
                    $this->onFail($verifyResponse, $order);
            } 
        }
        else {
            $this->helper->log('****** Order not found ******', 'latitude');
            $this->messageManager->addWarningMessage('Order not found');
            $this->_redirect('checkout/onepage/failure');
        }
    }
}
