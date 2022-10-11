<?php
/**
 * @package   LatitudeNew_Payment
 * @author    Lpay Team <integrationsupport@latitudefinancial.com>
 */
namespace LatitudeNew\Payment\Model\Order;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Info;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\ManagerInterface;
use Magento\Sales\Api\CreditmemoManagementInterface as CreditmemoManager;

/**
 * Class Payment (Sends order email to the customer.)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Payment extends \Magento\Sales\Model\Order\Payment
{
    const REVIEW_ACTION_ACCEPT = 'accept';
 
     const REVIEW_ACTION_DENY = 'deny';
 
     const REVIEW_ACTION_UPDATE = 'update';
 
     const PARENT_TXN_ID = 'parent_transaction_id';
 
     protected $_order;
 
     protected $_canVoidLookup = null;
 
     protected $_eventPrefix = 'sales_order_payment';
 
     protected $_eventObject = 'payment';
 
     protected $transactionAdditionalInfo = [];
 
     protected $creditmemoFactory;
 
     protected $priceCurrency;
 
     protected $transactionRepository;
 
     protected $transactionManager;
 
     protected $transactionBuilder;
 
     protected $orderPaymentProcessor;
 
     protected $orderRepository;
 
     private $orderStateResolver;
 
     private $creditmemoManager = null;

     /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory
     * @param PriceCurrencyInterface $priceCurrency
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param ManagerInterface $transactionManager
     * @param \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
     * @param \Magento\Sales\Model\Order\Payment\Processor $paymentProcessor
     * @param OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     * @param CreditmemoManager $creditmemoManager
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory,
        PriceCurrencyInterface $priceCurrency,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        ManagerInterface $transactionManager,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Sales\Model\Order\Payment\Processor $paymentProcessor,
        OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        CreditmemoManager $creditmemoManager = null
    ) {
        $this->priceCurrency = $priceCurrency;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->transactionRepository = $transactionRepository;
        $this->transactionManager = $transactionManager;
        $this->transactionBuilder = $transactionBuilder;
        $this->orderPaymentProcessor = $paymentProcessor;
        $this->orderRepository = $orderRepository;
        $this->creditmemoManager = $creditmemoManager ?: ObjectManager::getInstance()->get(CreditmemoManager::class);
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $encryptor,
            $creditmemoFactory,
            $priceCurrency,
            $transactionRepository,
            $transactionManager,
            $transactionBuilder,
            $paymentProcessor,
            $orderRepository,
            $resource,
            $resourceCollection,
            $data,
            $creditmemoManager
        );
    }

     /**
     * Void (override from \Magento\Sales\Model\Order\Payment)
     */
    protected function _void($isOnline, $amount = null, $gatewayCallback = 'void')
     {
         $order = $this->getOrder();
         $authTransaction = $this->getAuthorizationTransaction();
         $this->setTransactionId(
             $this->transactionManager->generateTransactionId($this, Transaction::TYPE_VOID, $authTransaction)
         );
         $this->setShouldCloseParentTransaction(true);
 
         // attempt to void
         if ($isOnline) {
             $method = $this->getMethodInstance();
             $method->setStore($order->getStoreId());
             $method->{$gatewayCallback}($this);
         }
         if ($this->checkIfTransactionExists()) {
             return $this;
         }
 
         // if the authorization was untouched, we may assume voided amount = order grand total
         // but only if the payment auth amount equals to order grand total
         if ($authTransaction &&
             $order->getBaseGrandTotal() == $this->getBaseAmountAuthorized() &&
             0 == $this->getBaseAmountCanceled()
         ) {
             if ($authTransaction->canVoidAuthorizationCompletely()) {
                 $amount = (double)$order->getBaseGrandTotal();
             }
         }
 
         if ($amount) {
             $amount = $this->formatAmount($amount);
         }
 
         // update transactions, order state and add comments
         $transaction = $this->addTransaction(Transaction::TYPE_VOID, null, true);
         $message = $this->hasMessage() ? $this->getMessage() : __('Voided authorization.');
         $message = $this->prependMessage($message);
         if ($amount) {
             $message .= ' ' . __('Amount: %1.', $this->formatPrice($amount));
         }
         $message = $this->_appendTransactionToMessage($transaction, $message);
         //$this->setOrderStateProcessing($message);
         $order->setDataChanges(true);
 
         return $this;
     }
}