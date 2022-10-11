<?php
/**
 * @package   LatitudeNew_Payment
 * @author    Lpay Team <integrationsupport@latitudefinancial.com>
 */
namespace LatitudeNew\Payment\Model;

use Magento\Directory\Helper\Data as DirectoryHelper;

/**
 * Latitudepay payment method model
 */
class Latitudepay extends \Magento\Payment\Model\Method\AbstractMethod
{
    
    const PAYMENT_METHOD_LATITUDEPAY_CODE = 'latitudepay';

    /**
     * Payment method code
     *
     * @var string
     */

    protected $_code = self::PAYMENT_METHOD_LATITUDEPAY_CODE;

    protected $_supportedCurrencyCodes = array('AUD');

    /**
     * Latitudepay payment block paths
     *
     * @var string
     */
    protected $_formBlockType = \LatitudeNew\Payment\Block\Form\Latitudepay::class;
    
    /**
     * Info instructions block path
     *
     * @var string
     */
    protected $_infoBlockType = \Magento\Payment\Block\Info\Instructions::class;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = false;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canOrder                   = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canCapture                 = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canRefund                  = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseForMultishipping     = false;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isInitializeNeeded         = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canFetchTransactionInfo    = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canReviewPayment           = true;

    /**
     * Get instructions text from config
     *
     * @return string
     */    
    protected $latitudeApi;

    protected $storeManager;

    protected $_scopeConfig;

    /**
     * Construct
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \LatitudeNew\Payment\Model\Api $latitudeApi
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     * @param DirectoryHelper $directory
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \LatitudeNew\Payment\Model\Api $latitudeApi,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        DirectoryHelper $directory = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data,
            $directory
        );
        $this->latitudeApi = $latitudeApi;
        $this->storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * GetInstructions
     *
     * @return string
     */
    public function getInstructions()
    {
        return trim($this->getConfigData('instructions'));
    }

     /**
     * @inheritdoc
     */
    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function canUseForCurrency($currencyCode)
    {
        $paymentCurrency =$this->getConfigData('currency');
        $currency = (isset($paymentCurrency) ? $paymentCurrency : $currencyCode);
        $currentCurrencyCode = $this->storeManager->getStore()->getCurrentCurrencyCode();
        if ($currentCurrencyCode == $currency) {
            return true;
        }
        return false;
    }

    /**
     * Is active
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return (bool)(int)$this->_scopeConfig->getValue(
                'payment/latitudepay/active',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeId
        ) && (bool)(int)$this->getConfigData('active', $storeId);
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        //@TODO:
        //1. check if API crediential coorect.
        //2. check currency is correct again
        //3. check quote amount is correct.
        return $this->isActive() && $quote && ($quote->getGrandTotal() >= 20);
    }

    /**
     * Refund (override from \Magento\Payment\Model\Method\AbstractMethod)
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount 
     * @return $this
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        $transId = $payment->getParentTransactionId();

        if (!$transId)
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Error issuing refund - no Transaction Id')
            );
        
        $grand_total = $order->getGrandTotal();
        $total_refunded = $order->getTotalRefunded();
        $refunded_amt = $grand_total - $total_refunded;
        if ($refunded_amt == 0) {
            $refundStatus = 'Full Refund';
        } 
        else if ($refunded_amt < 0) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Error issuing refund - Grandtotal: '.$grand_total.', Total Refunded: '.$total_refunded.', Amount: '.$amount)
            );
        }
        else {
            $refundStatus = 'Partial Refund';
        }

        $this->latitudeApi->refund($order, $transId, $amount, $refundStatus);
        return $this;
    }
}
