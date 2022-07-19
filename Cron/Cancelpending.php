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
    protected $helper;

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
        // \Psr\Log\LoggerInterface $logger,
        \LatitudeNew\Payment\Helper\Data $helper,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Sales\Api\Data\OrderInterface $cancelOrder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \LatitudeNew\Payment\Model\Api $latitudeApi
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->helper = $helper;
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
        //$frequency = 600; //10 minutes, debug mode

        $orders = $this->orderCollectionFactory->create()
        ->addFieldToSelect('*')
        ->addFieldToFilter('state', 'new')
        ->addFieldToFilter('status', 'pending_latitude_approval');

        $this->helper->log('*** CHECKING EXPIRED ORDERS ***');
        foreach ($orders as $order) {
            $orderAge = (-1*(strtotime($order->getUpdatedAt()) - time()));
            
            if ($orderAge > $frequency) {
                $this->helper->log('Order number ' . $order->getId() .' is expired, Cancelling Order...');
                /**CAVEAT: 
                 * We don't end up checking for status because TransactionId is only saved on payment completion
                   and we need transaction ID to check for order status on Lpay API
                   Also, the success scenario in case where customer exit the portal before redirected back from payment completion
                   has been handled by Lpay's callback feature, so there will not be a pending request waiting to be "processed"
                   hence, anything pending at the point of 24 hours, should be considered as cancelled order
                */
                // $purchasePaid = $this->latitudeApi->checkStatus($order);
                // if (!$purchasePaid) {
                    $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED, true);
                    $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                    $order->addStatusToHistory($order->getStatus(), 'Cancelled by Cron - The payment was not approved.');
                    $order->save();
                // } else {
                //     $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true);
                //     $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                //     $order->addStatusToHistory($order->getStatus(), 'Approved by Cron - The payment was approved.');
                //     $order->save();
                // }
            }
        }
        return $this;
    }
}
