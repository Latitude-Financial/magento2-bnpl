<?php
/**
 * @package   LatitudeNew_Payment
 * @author    Lpay Team <integrationsupport@latitudefinancial.com>
 */
namespace LatitudeNew\Payment\Cron;

/**
 * Latitude cancel Pending order, a cleaner for cases where customer leaves the payment portal before completing purchase
 */ 
class Cancelpending
{
    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $logger;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var \Magento\Sales\Api\Data\OrderInterface
     */    
    protected $cancelOrder;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;    

    /**
     * @var \LatitudeNew\Payment\Model\Api
     */
    protected $latitudeApi;

    /**
     * Construct
     *
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param \Magento\Sales\Api\Data\OrderInterface $cancelOrder
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \LatitudeNew\Payment\Model\Api $latitudeApi
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Sales\Api\Data\OrderInterface $cancelOrder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \LatitudeNew\Payment\Model\Api $latitudeApi
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->logger = $logger;
        $this->cancelOrder = $cancelOrder;
        $this->scopeConfig = $scopeConfig;
        $this->latitudeApi = $latitudeApi;
    }
    
    /**
     * Method executed when cron runs in server
     */
    public function execute()
    {
        $frequency = 86400; //24 hours
    
        $orders = $this->orderCollectionFactory->create()
        ->addFieldToSelect('*')
        ->addFieldToFilter('state', 'new')
        ->addFieldToFilter('status', 'pending_approval');
        foreach ($orders as $order) {
            $orderAge = (-1*(strtotime($order->getUpdatedAt()) - time()));
            
            if ($orderAge > $frequency) {
                $purchasePaid = $this->latitudeApi->checkStatus($order); //TODO: change to lpay check status API
                if (!$purchasePaid) {
                    $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED, true);
                    $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                    $order->addStatusToHistory($order->getStatus(), 'The payment was not approved.');
                    $order->save();
                } else {
                    $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true);
                    $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                    $order->addStatusToHistory($order->getStatus(), 'The payment was approved.');
                    $order->save();
                }
            }
        }
        return $this;
    }
}
