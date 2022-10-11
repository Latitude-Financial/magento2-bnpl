<?php
/**
 * @package   LatitudeNew_Payment
 * @author    Lpay Team <integrationsupport@latitudefinancial.com>
 */
namespace LatitudeNew\Payment\Model;

use Magento\Sales\Api\Data\OrderStatusHistoryInterface;

/**
 * Latitude API model
 */
class Api extends \Magento\Framework\Model\AbstractModel
{
    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $orderdata;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $orderFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Status\History\CollectionFactory
     */
    protected $_historyCollectionFactory;

    protected $scopeConfig;

    protected $storeManager;

    /**
     * @var \LatitudeNew\Payment\Helper\Data
     */  
    public $helper;

    /**
     * Construct
     *    
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Sales\Model\Order $orderFactory
     * @param EncryptorInterface $encryptor
     * @param \Magento\Sales\Model\ResourceModel\Order\Status\History\CollectionFactory $historyCollectionFactory
     */
    public function __construct(
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \LatitudeNew\Payment\Helper\Data $helper
    ) {
        $this->messageManager = $messageManager;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->contentType = $this->helper->getConfigData('content_type');
    }

    /**
     * Get auth token from latitudepay/genoapay API based on currency settings (retrieved in helper)
     */
    function requestAuthToken($storeId = null,$methodCode= null){
        $this->helper->log('****** REQUESTING AUTH TOKEN ******');
        $env = $this->helper->getConfigData('environment',$storeId,$methodCode);
        $gatewayUrl = $this->helper->getConfigData($env === 'production' ? 'api_url_production' : 'api_url_sandbox',$storeId,$methodCode);
        $clientId = $this->helper->getConfigData('client_key',$storeId,$methodCode);
        $clientSecret = $this->helper->getConfigData('client_secret',$storeId,$methodCode);

        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION =>CURL_HTTP_VERSION_1_1
        );

        $headers = [
            'Accept' => $this->contentType,
            'Content-Type' => $this->contentType,
            "Cache-Control" => "no-cache"
        ];

        $url = "$gatewayUrl/token";

        $credentials = [$clientId, $clientSecret];

        try{
            $response = $this->helper->makecurlCall($url, $options, $headers, $credentials, null);

            if (property_exists($response, 'error')){
                $this->messageManager->addErrorMessage(__(sprintf("Error getting token : %s", $response->error)));
                $this->helper->log("Auth error: $response->error");
                return null;
            }
            else
                return $response->authToken;
        }
        catch (\Exception $e){
            $this->messageManager->addErrorMessage(__(sprintf("Error getting token : %s", $e->getMessage())));
            $this->helper->log("Auth error: $e");
        }
    }

    // function stripJSON($obj){
    //     $str = '';
        
    //     foreach ($obj as $key => $value) {
    //         if (is_array($value) || is_object($value)) {
    //             $str .= (is_array($obj) ? '' : $key) . $this->stripJSON($value);
    //         } 
    //         else{
    //             $str .= (is_array($obj) ? '' : $key) . preg_replace("/\s+/", '', !is_numeric($value) && !is_string($value) ? var_export($value,true) : $value);
    //         }
    //     }    
    //     return $str;
    // }

    function stripJSON($requestBody)
    {
        $pattern = '/{"|":{"|","|":"|"},"|}],"|":|\[{"|"}}],"|}}|"}]"|},|,"|"}}|"}/';
        $replacement = '';

        $removeJsonFormatting = preg_replace($pattern, $replacement, $requestBody);
        $removeAllSpace = str_replace(' ', '', $removeJsonFormatting);
        $JSONStringWithoutFormatting = $removeAllSpace;

        return $JSONStringWithoutFormatting;
    }
    
    // function getJSONsignature($jsn, $apiSecret){    
    //     $progress = $this->stripJSON(json_decode($jsn,true));
    //     $this->helper->log("Stripped: $progress");
    //     $progress = base64_encode($progress);
    //     $this->helper->log("base64: $progress");
    //     return hash_hmac('sha256', $progress, $apiSecret);
    // }

    function getJSONsignature($jsn, $apiSecret)
    {
        $progress = trim($this->stripJSON($jsn));
        $progress = base64_encode(str_replace(' ', '', $progress));
        return hash_hmac('sha256', str_replace(' ', '',trim($progress)), $apiSecret);
    }

    /**
     * Get purchase redirect url
     */
    function createOnlinePurchase($authToken, $lastOrder)
    {
        $this->helper->log('****** REQUESTING PURCHASE URL ******');
        try{
            $baseUrl =  $this->storeManager->getStore()->getBaseUrl();
            $env = $this->helper->getConfigData('environment');
            $gatewayUrl = $this->helper->getConfigData($env === 'production' ? 'api_url_production' : 'api_url_sandbox');
            $clientSecret = $this->helper->getConfigData('client_secret');

            $order = $lastOrder->getData();
            $items = $lastOrder->getAllVisibleItems();
            $billing = $lastOrder->getBillingAddress()->getData();
            $shipping = $lastOrder->getShippingAddress()->getData();

            //for PHP <7.4 compatibility, changed arrow function to function with use()
            $products = array_map(function ($item) use ($lastOrder) {
                //Don't use Quote object!! quote->getAllVisibleItems(), item->getQty() returns No products were found 
                return [
                    "name" => $item->getName(),
                    "price" => [
                    "amount" => sprintf('%.2F',$item->getPriceInclTax()),
                    "currency" => $lastOrder->getOrderCurrencyCode(),
                    ],
                    "sku" => $item->getSku(),
                    "quantity" => ((int)$item->getQtyOrdered()),
                    "taxIncluded" => true
                ];
            }, $items);
            
            if ($lastOrder->getCustomerIsGuest()) {
                $customer_firstname = $billing['firstname'];
                $customer_lastname = $billing['lastname'];
            } else {
                $customer_firstname =  $order['customer_firstname'];
                $customer_lastname  =  $order['customer_lastname'];
            }

            $body = [
                'customer' => [
                    "mobileNumber" => $billing['telephone'],
                    "firstName" => $customer_firstname,
                    "surname" => $customer_lastname,
                    "email" => $order['customer_email'],
                    "address" => [
                        "addressLine1" => $billing['street'],
                        "suburb" => $billing['region'] ? $billing['region'] : '',
                        "cityTown" => $billing['city'],
                        "state" => $billing['region'] ? $billing['region'] : '',
                        "postcode" => $billing['postcode'],
                        "countryCode" => $billing['country_id']
                    ],
                    "dateOfBirth" => $order['customer_dob'] ? $order['customer_dob'] : ''
                ],
                "shippingAddress" => [
                    "addressLine1" => $shipping['street'],
                    "suburb" => $shipping['region'] ? $shipping['region'] : '',
                    "cityTown" => $shipping['city'],
                    "state" => $shipping['region'] ? $shipping['region'] : '',
                    "postcode" => $shipping['postcode'],
                    "countryCode" => $shipping['country_id']
                ],
                "billingAddress" => [
                    "addressLine1" => $billing['street'],
                    "suburb" => $billing['region'] ? $billing['region'] : '',
                    "cityTown" => $billing['city'],
                    "state" => $billing['region'] ? $billing['region'] : '',
                    "postcode" => $billing['postcode'],
                    "countryCode" => $billing['country_id']
                ],
                "products" => $products,
                "shippingLines" => [
                    [
                        "carrier" => ($lastOrder->getShippingMethod() ? $lastOrder->getShippingMethod() : 'N/A'),
                        "price" => [
                            "amount" => sprintf('%.2F',$lastOrder->getShippingAmount()),
                            "currency" => $lastOrder->getOrderCurrencyCode()
                        ],
                        "taxIncluded" => true
                    ]
                ],
                "taxAmount" => [
                    "amount" => sprintf('%.2F',$lastOrder->getTaxAmount()),
                    "currency" => $lastOrder->getOrderCurrencyCode()
                ],
                "reference" => $order['increment_id'],
                "totalAmount" => [
                    "amount" => sprintf('%.2F',$order['grand_total']),
                    "currency" => $lastOrder->getOrderCurrencyCode()
                ],
                "returnUrls" =>[
                    "successUrl" => $baseUrl . $this->helper->getConfigData('success_url'),
                    "failUrl" => $baseUrl . $this->helper->getConfigData('fail_url'),
                    "callbackUrl" => $baseUrl . $this->helper->getConfigData('callback_url')
                ]
            ];

            $bodyStr = json_encode($body,JSON_UNESCAPED_SLASHES);
            $this->helper->log("Body String: $bodyStr");

            $signature = $this->getJSONsignature($bodyStr, $clientSecret);
            $this->helper->log("Signature: $signature");

            $url = "$gatewayUrl/sale/online?signature=$signature";

            $options = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
            );

            $headers = [
                'Accept' => $this->contentType,
                'Content-Type' => $this->contentType,
                'Cache-Control' => 'no-cache',
                'X-Idempotency-Key' => uniqid('', true),
                'Authorization' => 'Bearer '.$authToken,
            ];

            
            $response = $this->helper->makecurlCall($url, $options, $headers, false, $bodyStr);

            if (property_exists($response, 'error')){
                $this->helper->log("Error creating purchase : $response->error");
                $this->messageManager->addErrorMessage(__(sprintf("Error creating purchase : %s", $response->error)));
                return $response->error;
            }
            else{
                //workaround since setData() doesn't persist new key to the DB
                $lastOrder->setCustomerNote($response->reference)->save();
                $this->helper->log("Successful purchase creation with reference: $response->reference");
                return $response->paymentUrl;
            }
        }
        catch (\Exception $e){
            $this->messageManager->addErrorMessage(__(sprintf("Error creating purchase : %s", $e->getMessage())));
            $this->helper->log("Error creating purchase : $e");
            return $e->getMessage();
        }
    }

    function refund($order, $transactionId, $amount, $reason){
        $this->helper->log('****** INITIATING REFUND ******');
        //get specific payment method to refund
        $storeId = $order->getStore()->getId();
        $methodCode = $order->getPayment()->getMethod();

        //request auth from that specific payment method
        $authToken = $this->requestAuthToken($storeId, $methodCode);

        //get config data for that specific payment method
        $env = $this->helper->getConfigData('environment',$storeId,$methodCode);
        $gatewayUrl = $this->helper->getConfigData($env === 'production' ? 'api_url_production' : 'api_url_sandbox',$storeId,$methodCode);
        $clientSecret = $this->helper->getConfigData('client_secret',$storeId,$methodCode); 

        $body = [
            'amount' => [
                'amount' => $amount,
                'currency' => $order->getOrderCurrencyCode()
            ],
            'reason' => $reason,
            'reference' => $order->getIncrementId(),
        ];

       
        $bodyStr = json_encode($body,JSON_UNESCAPED_SLASHES);
        $this->helper->log("Body String: $bodyStr");

        $signature = $this->getJSONsignature($bodyStr, $clientSecret);
        $this->helper->log("Signature: $signature");

        $url = "$gatewayUrl/sale/$transactionId/refund?signature=$signature";

        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
        );

        $headers = [
            'Accept' => $this->contentType,
            'Content-Type' => $this->contentType,
            'Cache-Control' => 'no-cache',
            'X-Idempotency-Key' => uniqid('', true),
            'Authorization' => 'Bearer '.$authToken,
        ];
        
        $response = $this->helper->makecurlCall($url, $options, $headers, false, $bodyStr);

        if (property_exists($response, 'error')){
            $this->helper->log('Error issuing refund - '.$response->error.' Transaction Id: '.$transactionId);
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Error issuing refund - '.$response->error.' Transaction Id: '.$transactionId)
            );            
        }
    }

    /**
     * Unused at the moment, was supposed to be used by cancel order cron
     */
    function checkStatus($order){
        $this->helper->log('****** CHECKING ORDER STATUS ******');
        //get transaction id to check
        $transactionId = $order->getPayment()->getTransactionId();

        //get specific store and method code to check
        $storeId = $order->getStore()->getId();
        $methodCode = $order->getPayment()->getMethod();

        //request auth from that specific payment method
        $authToken = $this->requestAuthToken($storeId, $methodCode);

        $this->helper->log("Checking Order: ".$order->getId()." with Trasaction #$transactionId, storeId: $storeId, and methodCode: $methodCode");

        //get config data for that specific payment method
        $env = $this->helper->getConfigData('environment',$storeId,$methodCode);
        $gatewayUrl = $this->helper->getConfigData($env === 'production' ? 'api_url_production' : 'api_url_sandbox',$storeId,$methodCode);
        
        $url = "$gatewayUrl/sale/pos/$transactionId/status";

        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
        );

        $headers = [
            'Accept' => $this->contentType,
            'Content-Type' => $this->contentType,
            'Cache-Control' => 'no-cache',
            'Authorization' => 'Bearer '.$authToken,
        ];
        
        $response = $this->helper->makecurlCall($url, $options, $headers, false, null, false);

        if (property_exists($response, 'error')){
            return false;           
        }
        else if ($response->status === 'APPROVED')
            return true;
        else
            return false;
    }


    function generateSignatureFromString($str, $apiSecret)
    {
        $base64 = base64_encode($str);
        return hash_hmac('sha256', $base64, $apiSecret);
    }

    /**
     * Get purchase redirect url
     */
    function createLCPurchase($lastOrder)
    {
        $this->helper->log('****** REQUESTING LC PURCHASE URL ******', 'latitude');
        try{
            //TODO: all getConfigData should have (path,null,'latitude') on the params
            $baseUrl =  $this->storeManager->getStore()->getBaseUrl();
            $isTest = (boolean)($this->helper->getConfigData('test_mode', null, 'latitude') === '1');
            $gatewayUrl = $this->helper->getConfigData($isTest ? 'api_url_sandbox' : 'api_url_production', null, 'latitude');
            $merchantId = $this->helper->getConfigData('merchant_id', null, 'latitude');
            $merchantSk = $this->helper->getConfigData('merchant_secret', null, 'latitude');

            $order = $lastOrder->getData();
            $items = $lastOrder->getAllVisibleItems();
            $billing = $lastOrder->getBillingAddress()->getData();
            $shipping = $lastOrder->getShippingAddress()->getData();

            //for cancel URL
            $order_id = $order['increment_id'];
            $signature = $this->generateSignatureFromString("merchantReference=$order_id", $merchantSk);
            $cancel_query = "?merchantReference=$order_id&signature=$signature";

            //for PHP <7.4 compatibility, changed arrow function to function with use()
            $products = array_map(function ($item) use ($lastOrder) {
                //Don't use Quote object!! quote->getAllVisibleItems(), item->getQty() returns No products were found 
                return [
                    "name" => $item->getName(),
                    "sku" => $item->getSku(),
                    "quantity" => ((int)$item->getQtyOrdered()),
                    "unitPrice" => (float)sprintf('%.2F',$item->getPriceInclTax()),
                    "amount" => (float)sprintf('%.2F',$item->getQtyOrdered() * $item->getPriceInclTax()),
                    "requiresShipping" => (boolean)($item->getIsVirtual() === '0'),
                    "isGiftCard" => false
                ];
            }, $items);
            
            if ($lastOrder->getCustomerIsGuest()) {
                $customer_firstname = $billing['firstname'];
                $customer_lastname = $billing['lastname'];
            } else {
                $customer_firstname =  $order['customer_firstname'];
                $customer_lastname  =  $order['customer_lastname'];
            }

            $body =  [
                "merchantId" => $merchantId,
                "isTest" =>  $isTest,
                "merchantReference" => $order['increment_id'],
                "amount" => (float)sprintf('%.2F',$order['grand_total']),
                "currency" => $lastOrder->getOrderCurrencyCode(),
                "customer" => [
                   "firstName" => $customer_firstname,
                   "lastName" => $customer_lastname,
                   "phone" => (string)$billing['telephone'],
                   "email" => $order['customer_email'],
                ],
                "shippingAddress" => [
                    "name" => (string)$shipping['firstname'] ? $shipping['firstname'] . ' ' . $shipping['lastname'] : $billing['firstname'] . ' ' . $billing['lastname'],
                    "line1" => (string)$shipping['street'] ? $shipping['street'] : $billing['street'],
                    "city" => (string)$shipping['city'] ? $shipping['city'] : $billing['city'],
                    "postCode" =>  (string)$shipping['postcode'] ? $shipping['postcode'] : $billing['postcode'],
                    "state" => (string)$shipping['region'] ? $shipping['region'] : ($billing['region'] ? $billing['region'] : ''),
                    "countryCode" => (string)$shipping['country_id'] ? $shipping['country_id'] : $billing['country_id'],
                    "phone" => (string)$shipping['telephone'] ? $shipping['telephone'] : $billing['telephone'],
                ],
                "billingAddress" => [
                    "name" => (string)$billing['firstname'] . ' ' . $billing['lastname'],
                    "line1" => (string)$billing['street'],
                    "city" => (string)$billing['city'],
                    "postCode" =>  (string)$billing['postcode'],
                    "state" => (string)$billing['region'] ? $billing['region'] : '',
                    "countryCode" => (string)$billing['country_id'],
                    "phone" => (string)$billing['telephone'],
                ],
                "orderLines" => $products,
                "merchantUrls" => [
                    "cancel" => $baseUrl . $this->helper->getConfigData('cancel_url') . $cancel_query,
                    "complete" => $baseUrl . $this->helper->getConfigData('callback_url'),
                ],
                "totalShippingAmount" => (float)sprintf('%.2F',$lastOrder->getShippingAmount()),
                "totalDiscountAmount" => abs((float)sprintf('%.2F',$lastOrder->getDiscountAmount())),
                "platformType" => 'magento2',
                "pluginVersion" => $this->helper->getConfigData('version', null, 'latitudepay'),
            ];

            $bodyStr = json_encode($body,JSON_UNESCAPED_SLASHES);
            $this->helper->log("Body String: $bodyStr", 'latitude');

            $url = "$gatewayUrl/purchase";

            $options = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
            );

            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$merchantId:$merchantSk"),
            ];

            $response = $this->helper->makecurlCall($url, $options, $headers, false, $bodyStr, true, 'latitude');

            if ($response->status !== 200){
                $this->helper->log("Error creating purchase with status: $response->status", 'latitude');
                $this->messageManager->addErrorMessage(__(sprintf("Error creating purchase with status: %s", $response->status)));
                return $response->status;
            }
            else if ($response->error !== '' && $response->redirectUrl && $response->redirectUrl !== null){
                $this->helper->log("Purchase creation redirect with error: $response->error", 'latitude');
                return $response->redirectUrl;
            }
            else if ($response->error !== ""){
                $this->helper->log("Error creating purchase : $response->error", 'latitude');
                $this->messageManager->addErrorMessage(__(sprintf("Error creating purchase : %s", $response->error)));
                return $response->error;
            }
            else{
                //workaround since setData() doesn't persist new key to the DB
                //$lastOrder->setCustomerNote($response->gatewayReference)->save();
                $this->helper->log("Successful purchase creation with gateway reference: $response->gatewayReference", 'latitude');
                return $response->redirectUrl;
            }
        }
        catch (\Exception $e){
            $this->messageManager->addErrorMessage(__(sprintf("Error creating purchase : %s", $e->getMessage())));
            $this->helper->log("Error creating purchase : $e", 'latitude');
            return $e->getMessage();
        }
    }

    /**
     * Get purchase redirect url
     */
    function verifyLCPurchase($order_id, $transactionReference, $gatewayReference)
    {
        $this->helper->log('****** VERIFYING LC CALLBACK URL ******', 'latitude');

        $isTest = (boolean)($this->helper->getConfigData('test_mode', null, 'latitude') === '1');
        $gatewayUrl = $this->helper->getConfigData($isTest ? 'api_url_sandbox' : 'api_url_production', null, 'latitude');
        $merchantId = $this->helper->getConfigData('merchant_id', null, 'latitude');
        $merchantSk = $this->helper->getConfigData('merchant_secret', null, 'latitude');

        $body = [
            "merchantReference" => $order_id,
            "transactionReference" =>  $transactionReference,
            "gatewayReference" => $gatewayReference
        ];
        
        $bodyStr = json_encode($body,JSON_UNESCAPED_SLASHES);
        $this->helper->log("Body String: $bodyStr", 'latitude');

        $url = "$gatewayUrl/purchase/verify";

        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
        );

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode("$merchantId:$merchantSk"),
        ];

        $response = $this->helper->makecurlCall($url, $options, $headers, false, $bodyStr, true, 'latitude');

        return $response;
    }

    function refundLCOrder($order, $transactionId, $gatewayReference, $amount, $reason){
        $this->helper->log('****** INITIATING LC REFUND ******', 'latitude');
    
        //get specific payment method to refund
        $storeId = $order->getStore()->getId();
        $isTest = (boolean)($this->helper->getConfigData('test_mode', null, 'latitude') === '1');
        $gatewayUrl = $this->helper->getConfigData($isTest ? 'api_url_sandbox' : 'api_url_production', null, 'latitude');
        $merchantId = $this->helper->getConfigData('merchant_id', null, 'latitude');
        $merchantSk = $this->helper->getConfigData('merchant_secret', null, 'latitude');
      
        $body = [
            'merchantId' => $merchantId,
            'isTest' => $isTest,
            'merchantReference' => $order->getIncrementId(),
            'gatewayReference' => $gatewayReference,
            'amount' => (float)sprintf('%.2F', $amount),
            'currency' => $order->getOrderCurrencyCode(),
            'type' => 'refund',
            'description' => $reason,
            'platformType' => 'magento2'
        ];
    
        $bodyStr = json_encode($body,JSON_UNESCAPED_SLASHES);
        $this->helper->log("Body String: $bodyStr", 'latitude');
    
        $url = "$gatewayUrl/refund";
    
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
        );
    
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode("$merchantId:$merchantSk"),
        ];
        
        $response = $this->helper->makecurlCall($url, $options, $headers, false, $bodyStr, true, 'latitude');
    
        if ($response->status !== 200){
            $this->helper->log("Error issuing refund with status: $response->status, Transaction Ref: $transactionId, Gateway Ref: $gatewayReference", 'latitude');
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Error issuing refund - '.$response->status.' Transaction Ref: '.$transactionId.' Gateway Ref: '.$gatewayReference)
            );  
        }
        if ($response->error !== ""){
            $this->helper->log("Error issuing refund - $response->error, Transaction Ref: $transactionId, Gateway Ref: $gatewayReference", 'latitude');
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Error issuing refund - '.$response->error.' Transaction Ref: '.$transactionId.' Gateway Ref: '.$gatewayReference)
            );  
        }
    
        $order->addStatusHistoryComment(
            __("Refunded $$amount - $reason, Gateway Ref: $gatewayReference, Transaction Ref: $transactionId")
        )
        ->setIsCustomerNotified(false)
        ->save();
    }
    
    function captureLCOrder($order, $transactionId, $gatewayReference, $amount, $reason){
        $this->helper->log('****** INITIATING LC CAPTURE ******', 'latitude');
    
        //get specific payment method to refund
        $storeId = $order->getStore()->getId();
        $isTest = (boolean)($this->helper->getConfigData('test_mode', null, 'latitude') === '1');
        $gatewayUrl = $this->helper->getConfigData($isTest ? 'api_url_sandbox' : 'api_url_production', null, 'latitude');
        $merchantId = $this->helper->getConfigData('merchant_id', null, 'latitude');
        $merchantSk = $this->helper->getConfigData('merchant_secret', null, 'latitude');
      
        $body = [
            'merchantId' => $merchantId,
            'isTest' => $isTest,
            'merchantReference' => $order->getIncrementId(),
            'gatewayReference' => $gatewayReference,
            'amount' => (float)sprintf('%.2F', $amount),
            'currency' => $order->getOrderCurrencyCode(),
            'type' => 'capture',
            'description' => $reason,
            'platformType' => 'magento2'
        ];
    
        $bodyStr = json_encode($body,JSON_UNESCAPED_SLASHES);
        $this->helper->log("Body String: $bodyStr", 'latitude');
    
        $url = "$gatewayUrl/capture";
    
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
        );
    
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode("$merchantId:$merchantSk"),
        ];
        
        $response = $this->helper->makecurlCall($url, $options, $headers, false, $bodyStr, true, 'latitude');
    
        if ($response->status !== 200){
            $this->helper->log("Error capturing payment with status: $response->status, Transaction Ref: $transactionId, Gateway Ref: $gatewayReference", 'latitude');
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Error capturing payment - '.$response->status.' Transaction Id: '.$transactionId.' Gateway Ref: '.$gatewayReference)
            );  
        }
        if ($response->error !== ""){
            $this->helper->log("Error capturing payment - $response->error, Transaction Ref: $transactionId, Gateway Ref: $gatewayReference", 'latitude');
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Error capturing payment - '.$response->error.' Transaction Id: '.$transactionId.' Gateway Ref: '.$gatewayReference)
            );  
        }
    
        $order->addStatusHistoryComment(
            __("Captured $$amount - $reason Gateway Ref: $gatewayReference, Transaction Ref: $transactionId")
        )
        ->setIsCustomerNotified(true)
        ->save();
    }
    
    function voidLCOrder($order, $transactionId, $gatewayReference, $amount){
        $this->helper->log('****** INITIATING LC VOID ******', 'latitude');
    
        //get specific payment method to refund
        $storeId = $order->getStore()->getId();
        $isTest = (boolean)($this->helper->getConfigData('test_mode', null, 'latitude') === '1');
        $gatewayUrl = $this->helper->getConfigData($isTest ? 'api_url_sandbox' : 'api_url_production', null, 'latitude');
        $merchantId = $this->helper->getConfigData('merchant_id', null, 'latitude');
        $merchantSk = $this->helper->getConfigData('merchant_secret', null, 'latitude');
      
        $body = [
            'merchantId' => $merchantId,
            'isTest' => $isTest,
            'merchantReference' => $order->getIncrementId(),
            'gatewayReference' => $gatewayReference,
            'amount' => (float)sprintf('%.2F', $amount),
            'currency' => $order->getOrderCurrencyCode(),
            'type' => 'void',
            'description' => 'Customer change of mind',
            'platformType' => 'magento2'
        ];
    
        $bodyStr = json_encode($body,JSON_UNESCAPED_SLASHES);
        $this->helper->log("Body String: $bodyStr", 'latitude');
    
        $url = "$gatewayUrl/void";
    
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
        );
    
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode("$merchantId:$merchantSk"),
        ];
        
        $response = $this->helper->makecurlCall($url, $options, $headers, false, $bodyStr, true, 'latitude');
    
        if ($response->status !== 200){
            $this->helper->log("Error voiding payment with status: $response->status, Transaction Ref: $transactionId, Gateway Ref: $gatewayReference", 'latitude');
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Error voiding payment - '.$response->error.' Transaction Id: '.$transactionId.' Gateway Ref: '.$gatewayReference)
            );  
        }
        if ($response->error !== ""){
            $this->helper->log("Error voiding payment - $response->error, Transaction Ref: $transactionId, Gateway Ref: $gatewayReference", 'latitude');
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Error voiding payment - '.$response->error.' Transaction Id: '.$transactionId.' Gateway Ref: '.$gatewayReference)
            );  
        }
    
        $order->addStatusToHistory(
            \Magento\Sales\Model\Order::STATE_CLOSED,             //status   
            "Voided $$amount - Gateway Ref: $gatewayReference, Transaction Ref: $transactionId",     //comment, default ''
            false                                                   //isCustomerNotified, default false
        )
        ->setIsCustomerNotified(true)
        ->save();
    }
}

