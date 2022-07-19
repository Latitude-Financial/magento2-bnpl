<?php
/**
 * @package   LatitudeNew_Payment
 * @author    Lpay Team <integrationsupport@latitudefinancial.com>
 */
namespace LatitudeNew\Payment\Controller\Order;

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
    private $validator;

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
        \LatitudeNew\Payment\Model\ValidateOrder $validateOrder,
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
        $this->validator = $validateOrder;
        $this->quoteFactory = $quoteFactory;
    }

    /**
     * Save Transaction
     *
     * @param \Magento\Sales\Model\Order $order
     * @param Array $param - request parameters
     * @return void
     */
    protected function saveTransaction($order, $param) {
        $this->helper->log('****** SAVING TRANSACTION ******');
        //change state from new to processing
        $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
       
        //Set transaction id
        $payment = $order->getPayment();
        $payment->setTransactionId($param['token']);

        //add transaction
        $transaction = $payment->addTransaction(
            \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE,
            null,        //salesDocument, default null
            false        //failSafe, default false
        );
        $transaction->setAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::TRANSACTION_ID , $param['token']);
        $transaction->setIsClosed(0);
        $transaction->save();
        
        $latitudeRef = $order->getCustomerNote();
        //add comment along and update status to processing
        $order->addStatusToHistory(
            \Magento\Sales\Model\Order::STATE_PROCESSING,                                                   //status   
            $param['message']." Transaction Id: ".$param['token']." Payment Reference: ".$latitudeRef,     //comment, default ''
            true                                                                                            //isCustomerNotified, default false
        );
        $order->save();

        $this->sendEmail->send($order, true); //forceSyncMode default false, true = Email will be sent immediately
    }

    protected function onFail($param, $order){
        $this->helper->log('****** FAILED URL ******');
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
            $payment->setTransactionId($param['token']);

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
                $param['message'].' Transaction ID: '.$param['token'],  //comment, default ''
                false                                                   //isCustomerNotified, default false
            );            
            $order->save();
          
            //setup redirect to cart
            $this->messageManager->addErrorMessage($param['message']);
            $this->_redirect('checkout/cart');
            //$this->_redirect('checkout?cancel', ['_fragment' => 'payment']);
        }
    }
    
    public function execute() {
        $this->helper->log('****** CALLBACK METHOD TRIGGERED ******');
        //get client secret from config
        $clientSecret = $this->helper->getConfigData('client_secret');
        
        //make sure order id is not empty
        if ($this->getRequest()->getParam('reference') === null)
        {
            $this->messageManager->addWarningMessage('One or more parameter is missing');
            $this->_redirect('checkout/onepage/failure');
            return;
        }

        //create associated array from request parameters to be used for validation
        $param = [
            'token' => $this->getRequest()->getParam('token'),
            'reference' => $this->getRequest()->getParam('reference'),
            'message' => $this->getRequest()->getParam('message'),
            'result' => $this->getRequest()->getParam('result'),
            'signature' => $this->getRequest()->getParam('signature')
        ];
        $this->helper->log('PARAMETERS - ' . json_encode($param));

        //validate query param, if mismatch, send back to cart with warning message
        if(!$this->validator->verifyResponse($param, $clientSecret))
        {
            $this->messageManager->addWarningMessage("Invalid Signature");
            $this->_redirect('checkout/onepage/failure');
            return;
        }

        //get order object
        $order =  $this->helper->getOrderByIncrementId($param['reference']);

        if ($order) {
            if ($param['result'] == 'COMPLETED') {
                $this->helper->log('****** SUCCESS URL ******');
                //only process success scenario when status is still pending approval (default of "Place Order" click)
                if ($order->getStatus() === 'pending_latitude_approval') {
                    $this->saveTransaction($order, $param);
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
                else {
                    $this->helper->log('****** Invalid order update request ******');
                    $this->messageManager->addWarningMessage('Invalid order update request');
                    $this->_redirect('checkout/onepage/failure');
                }
            } 
            else if ($param['result'] == 'FAILED')  {
                $this->onFail($param, $order);
            }
        } 
        else {
            $this->helper->log('****** Order not found ******');
            $this->messageManager->addWarningMessage('Order not found');
            $this->_redirect('checkout/onepage/failure');
        }
    }
}
