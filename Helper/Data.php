<?php
/**
 * @package   LatitudeNew_Payment
 * @author    Lpay Team <integrationsupport@latitudefinancial.com>
 */
namespace LatitudeNew\Payment\Helper;

/**
 * Latitudepay Config and Curl helper
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactoryCreate;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Magento\Framework\HTTP\Adapter\CurlFactory
     */
    private $curlFactory;

    /**
     * @var \LatitudeNew\Payment\Logger\Logger
     */
    protected $logger;

    /**
     * Payment method code
     *
     * @var string
     */
    protected $code;

    protected $currentCurrencyCode;

    protected $supportedCurrencyCodes;

    protected $latitudepayCurrency;

    protected $genoapayCurrency ;

    protected $curl;

    protected $latitudeReference;
    /**
     * Construct
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Sales\Model\OrderFactory $orderFactoryCreate
     * @param \Magento\Framework\HTTP\Adapter\CurlFactory $curlFactory
     * @param \LatitudeNew\Payment\Logger\Logger  $logger
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Sales\Model\OrderFactory $orderFactoryCreate,
        \Magento\Framework\HTTP\Client\CurlFactory $curlFactory,
        //\Psr\Log\LoggerInterface $logger,
        \LatitudeNew\Payment\Logger\Logger $logger,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);

        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->getOrderFactory = $orderFactoryCreate;
        $this->messageManager = $messageManager;
        $this->curlFactory = $curlFactory;
        $this->logger = $logger;

        /** @noinspection PhpUnhandledExceptionInspection */
        //find out which currency store is using, and use that info to find out the app code latitudepay/genoapay
        $this->currentCurrencyCode          =  $this->storeManager->getStore()->getCurrentCurrencyCode();
        $this->latitudepayCurrency          =  $this->scopeConfig->getValue('payment/latitudepay/currency', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->genoapayCurrency             =  $this->scopeConfig->getValue('payment/genoapay/currency', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->supportedCurrencyCodes       = array($this->latitudepayCurrency=>'latitudepay',$this->genoapayCurrency=>'genoapay');
        if (isset($this->supportedCurrencyCodes[$this->currentCurrencyCode])) {
            $this->code = $this->supportedCurrencyCodes[$this->currentCurrencyCode];
        }
    }

  /**
     * Retrieve information from Latitudepay/Genoapay configuration
     *@throws \Magento\Framework\Exception\LocalizedException
     * @param string $field
     * @param int $storeId
     * @param string $methodCode
     * @return  false|string
     */
    public function getConfigData($field, $storeId = null, $methodCode = null)
    {
        if($storeId == null){
            $storeId  = $this->storeManager->getStore()->getId();
        }

        //if specific payment (genoapay/latitudepay/latitude) is passed, override this->code set on constructor
        if ($methodCode) {
            $this->code = $methodCode;
        }

        if (empty($this->code)) {
            return false;
        }

        $path = 'payment/' . $this->code . '/' . $field;

        if($storeId){
            return $this->scopeConfig->getValue( $path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE,$storeId );
        }else{
            return $this->scopeConfig->getValue( $path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE );
        }
    }

    /**
     * Checks whether the Latitudepay payment method is enabled.
     *
     * @param null $store
     * @return mixed
     */
    public function isLatitudepayEnabled($store = null)
    {
        return $this->scopeConfig->getValue('payment/latitudepay/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store) && ($this->currentCurrencyCode == $this->latitudepayCurrency) ;
    }
    /**
     * get Genoapay Enabled
     *
     * @param null $store
     * @return mixed
     */
    public function isGenoapayEnabled($store = null)
    {
        return $this->scopeConfig->getValue('payment/genoapay/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store) && ($this->currentCurrencyCode == $this->genoapayCurrency);
    }

    /**
     * get Genoapay Enabled
     *
     * @param null $store
     * @return mixed
     */
    public function isLCEnabled($store = null)
    {
        return $this->scopeConfig->getValue('payment/latitude/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
    }

    public function getStoreCurrency(){
        return $this->currentCurrencyCode;
    }
    
    /**
     * @return bullean
     */
    public function isLogRequired($methodCode = null)
    {
        if ($methodCode === 'latitude')
            return $this->getConfigData('debug_mode', null, $methodCode);

        return $this->getConfigData('logging');
    }

    /**
     * @param $message
     * @return Mage_Log
     */
    public function log($message, $methodCode = null)
    {
        if ($methodCode === 'latitude' && $this->isLogRequired($methodCode)){
            $this->logger->info($message);
        }
        else if ($this->isLogRequired()) {
            $this->logger->info($message);
        }
        
        return false;
    }

    /**
     * @param (int) $id
     * @param (string) $hash
     * @return $object
     */
    public function getOrderByIncrementId($incrementId)
    {
        $orderobj = $this->getOrderFactory->create()->loadByIncrementId($incrementId);
        return $orderobj;
    }

    /**
     * Get Latitudepay Payment Services
     *
     * @param null $store
     * @return mixed
     */
    public function getLatitudepayPaymentServices($store = null)
    {
        if($this->isGenoapayEnabled()){
            return 'GPAY';
        }

        if($this->isLatitudepayEnabled()){
            if($this->scopeConfig->getValue('payment/latitudepay/payment_services', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store)){
                return $this->scopeConfig->getValue('payment/latitudepay/payment_services', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
            }
            return 'LPAY';
        }

        if ($this->isLCEnabled()){
            
        }
        return '';
    }

    /**
     * Get Latitudepay Payment Terms
     *
     * @param null $store
     * @return mixed
     */
    public function getLatitudepayPaymentTerms($store = null)
    {
        if($this->isLatitudepayEnabled()){
            return $this->scopeConfig->getValue('payment/latitudepay/payment_terms', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
        }
        return null;
    }

    /**
     * Get Image API URL.
     *
     * @param null $store
     * @return mixed
     */
    public function getImageApiUrl($store = null)
    {
        if (isset($this->supportedCurrencyCodes[$this->currentCurrencyCode])) {
            $this->code = $this->supportedCurrencyCodes[$this->currentCurrencyCode];
        }
        return $this->scopeConfig->getValue('payment/'.$this->code.'/image_api_url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * Retrieve util js
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return \Magento\Framework\Phrase
     */
    public function getUtilJs()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->getImageApiUrl().'/util.js';
    }

    /**
     * Retrieve snippet image url
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return \Magento\Framework\Phrase
     */
    public function getSnippetImageUrl()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->getImageApiUrl().'/snippet.svg';
    }

     /**
     * If display billing address on payment page is available, otherwise should be display on payment method
     *
     * @return bool
     */
    public function isDisplayBillingOnPaymentPageAvailable(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            'checkout/options/display_billing_address_on',  //Configuration value of whether to display billing address on payment method or payment page
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getScriptURL()
    {
        $isTest = (boolean)($this->getConfigData('test_mode', null, 'latitude') === '1');
        $host = $isTest ? 
            'https://develop.checkout.dev.merchant-services-np.lfscnp.com' 
            : 
            'https://checkout.latitudefinancial.com';
        return $host. "/assets/content.js?platform=magento2&merchantId=".$this->getConfigData('merchant_id', null, 'latitude');
    }

    public function makecurlCall($url, $options, $headers, $credentials, $body, $isPost = true, $methodCode = null)
    {
        $this->log('*** Making CURL Request ***', $methodCode);
        $this->log("Calling $url with POST: $isPost, headers: ".json_encode($headers).", credentials: ".implode(',',$credentials ? $credentials : array()).", and body: $body", $methodCode);
        
        $this->curl = $this->curlFactory->create();
        try{
            $this->curl->setHeaders($headers);
            $this->curl->setOptions($options);

            if ($credentials !== false)
                $this->curl->setCredentials($credentials[0], $credentials[1]);

            if ($isPost)
                $this->curl->post($url, $body !== null ? $body : array());
            else
                $this->curl->get($url);
                
            $response = $this->curl->getBody();
            $status = $this->curl->getStatus();

            $this->log('Status: ' . $status . ', Response: ' . $response, $methodCode);
            
            $decodedResponse = json_decode($response);
            if (!$decodedResponse) {
                $decodedResponse = new \stdClass();
                $decodedResponse->error = "No response returned from api";
            }
            $decodedResponse->status = $status;

            return $decodedResponse;

        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->log($message, $methodCode);
            $this->messageManager->addErrorMessage(__("Error making curl call to $url with error $message"));
            throw new \Exception($e);
        }
    }
}
