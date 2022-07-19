<?php
/**
 * @package   LatitudeNew_Payment
 * @author    Lpay Team <integrationsupport@latitudefinancial.com>
 */
namespace LatitudeNew\Payment\Controller\Handoverurl;

/**
 * Latitude Handoverurl Controller
 */
class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \LatitudeNew\Payment\Model\Api
     */
    protected $latitudeApi;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * Construct
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \LatitudeNew\Payment\Model\Api $latitudeApi
     * @param \Magento\Checkout\Model\Session $session
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \LatitudeNew\Payment\Model\Api $latitudeApi,
        \Magento\Checkout\Model\Session $session
    ) {
        parent::__construct($context);

        $this->latitudeApi = $latitudeApi;
        $this->session = $session;
    }

    /**
     * Method Execute
     */
    public function execute() {
       $token = $this->latitudeApi->requestAuthToken();

       try{
            if ($this->session->getLastRealOrder() && $token !== null) {
                $url = $this->latitudeApi->createOnlinePurchase($token, $this->session->getLastRealOrder());

                if ($url !== null && substr($url, 0, 4) == 'http') {
                    // $this->session->clearStorage();     //set session quote to null
                    return $this->resultRedirectFactory->create()->setUrl($url);
                } else {
                    //$this->messageManager->addError(__("Url invalid: ".$url));
                    return $this->resultRedirectFactory->create()->setPath('checkout/onepage/failure');
                }
            }
            //$this->messageManager->addError(__("Invalid Gateway Credentials"));
            return $this->resultRedirectFactory->create()->setPath('checkout/onepage/failure');
        }
        catch (\Exception $e){
            //$this->messageManager->addError(__(sprintf("Error creating purchase : %s", $e->getMessage())));
            return $this->resultRedirectFactory->create()->setPath('checkout/onepage/failure');
        }
    }
}
