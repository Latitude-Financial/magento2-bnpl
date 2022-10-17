<?php
/**
 * @package   LatitudeNew_Payment
 * @author    Lpay Team <integrationsupport@latitudefinancial.com>
 */
namespace LatitudeNew\Payment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Escaper;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Latitudepay InstructionsConfigProvider model
 */
class InstructionsConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCodes = [
        Latitudepay::PAYMENT_METHOD_LATITUDEPAY_CODE,
        Genoapay::PAYMENT_METHOD_GENOAPAY_CODE
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

     /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var Repository
     */
    protected $assetRepo;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var Escaper
     */
    protected $escaper;

     /**
     * @var \Magento\Checkout\Model\Cart
     */
    protected $cart;
    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /** @var LayoutInterface  */

    protected $layout;

    protected $currency;

    /**
     * Construct
     *    
     * @param PaymentHelper $paymentHelper
     * @param Escaper $escaper
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param Repository $assetRepo,
     * @param RequestInterface $request,
     * @param UrlInterface $urlBuilder,
     * @param \LatitudeNew\Payment\Helper\Data $helper,
     * @param \Magento\Checkout\Model\Cart $cart,
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
     * @param LayoutInterface $layout
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        Escaper $escaper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        Repository $assetRepo,
        RequestInterface $request,
        UrlInterface $urlBuilder,
        \LatitudeNew\Payment\Helper\Data $helper,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        LayoutInterface $layout,
        \Magento\Directory\Model\Currency $currency
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->helper  = $helper;
        $this->cart  = $cart;
        $this->priceCurrency = $priceCurrency;
        $this->paymentHelper = $paymentHelper;
        $this->layout = $layout;
        $this->currency = $currency;
        $this->escaper = $escaper;

        foreach ($this->methodCodes as $code) {
            try {
                $this->methods[$code] = $this->paymentHelper->getMethodInstance($code);
            } catch (LocalizedException $e) {
                $this->helper->log($e->getMessage());
            }       
         }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $lpayinstallmentBlockId = "latitude_installment_block";
        $gpayinstallmentBlockId = "genoapay_installment_block";
        /** @noinspection PhpUndefinedMethodInspection */
        /** @noinspection PhpUndefinedMethodInspection */
        $config = [
            'latitudepayments' => [
                'latitudepay' => $this->getViewFileUrl('LatitudeNew_Payment::images/latitudepay-logo.svg'),
                'genoapay' => $this->getViewFileUrl('LatitudeNew_Payment::images/genoapay-logo.svg'),
                'latitude' => 'https://resources.latitudefinancial.com/img/interest-free/logos/' . ($this->helper->getStoreCurrency() === 'AUD' ? 'lfs-lock-up.svg' : 'GEM-IF-LOGO.svg'),
                'installmentno' => $this->getInstallmentNo(),
                'currency_symbol' => $this->currency->getCurrencySymbol(),
                'utilJs' => $this->helper->getUtilJs(),
                'lpay_installment_block' => '<img class="lpay_snippet" src="'.$this->getSnippetImage().'" alt="LatitudePay" >',
                'gpay_installment_block'    => '<img class="lpay_snippet" src="'.$this->getSnippetImage().'" alt="GenoaPay" >',
                'lc_script' => $this->helper->getScriptURL(),
                'lc_options' => [
                    "merchantId" => $this->helper->getConfigData('merchant_id', null, 'latitude'),
                    "page" => "checkout",
                    "currency" => $this->helper->getStoreCurrency()
                ]
            ],
        ];

        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable($this->cart->getQuote())) {
                $config['payment']['latitude']['redirectUrl'][$code] = $this->getMethodRedirectUrl($code);
                $config['payment']['instructions'][$code] = $this->getInstructions($code);
            }
        }
        return $config;
    }

     /**
     * Return redirect URL for method
     *
     * @param string $code
     * @return mixed
     */
    protected function getMethodRedirectUrl($code)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->methods[$code]->getCheckoutRedirectUrl();
    }

    /**
     * Retrieve url of a view file
     *
     * @param string $fileId
     * @param array $params
     * @return string
     */
    public function getViewFileUrl($fileId, array $params = [])
    {
        try {
            $params = array_merge(['_secure' => $this->request->isSecure()], $params);
            return $this->assetRepo->getUrlWithParams($fileId, $params);
        } catch (LocalizedException $e) {
            $this->helper->log($e->getMessage());
            return $this->urlBuilder->getUrl('', ['_direct' => 'core/index/notFound']);
        }
    }

    /**
     * Retrieve Payment Installment Text
     *
     * @return string|false
     * @throws LocalizedException
     */
    public function getInstallmentNo()
    {
        $installment = $this->helper->getConfigData('installment_no');
        return ($installment ? $installment :'installmentno');
    }

    /**
     * Retrieve Snippet Image
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return \Magento\Framework\Phrase
     */
    public function getSnippetImage()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $param = [
            'amount' => '__AMOUNT__',
            'services' => $this->helper->getLatitudepayPaymentServices(),
            'terms' => $this->helper->getLatitudepayPaymentTerms(),
            'style' => 'checkout'
        ];
        return $this->helper->getSnippetImageUrl() . '?' . http_build_query($param);
    }
    
    /**
     * Get instructions text from config
     *
     * @param string $code
     * @return string
     */
    protected function getInstructions($code)
    {
        return nl2br($this->escaper->escapeHtml($this->methods[$code]->getInstructions()));
    }
}
