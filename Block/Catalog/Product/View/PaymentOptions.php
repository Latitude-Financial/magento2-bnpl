<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/**
 * Payment Options rendering block
 * Class PaymentOptions
 * @package LatitudeNew\Payment\Block\Catalog\Product\View\PaymentOptions
 */
namespace LatitudeNew\Payment\Block\Catalog\Product\View;

use \Magento\Catalog\Block\Product\Context;
/**
 * PaymentOptions block
 *
 */
class PaymentOptions extends \Magento\Framework\View\Element\Template
{
    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $coreRegistry;

    /**
     *Product model
     *
     * @var \Magento\Catalog\Model\Product
     */

    protected $product;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    protected $priceCurrency;

    protected $helper;

    const INSTALLMENT_NO = 10;

    /**
     * PaymentOptions constructor.
     * @param Context $context
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     * @param \LatitudeNew\Payment\Helper\Data $helper
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \LatitudeNew\Payment\Helper\Data $helper,
        array $data = []
        ) {
        $this->coreRegistry  = $context->getRegistry();
        $this->priceCurrency = $priceCurrency;
        $this->helper  = $helper;
        parent::__construct(
            $context,
            $data
        );
    }
    /**
     * @return mixed
     */
    public function getCurrentProduct()
    {

        if($this->product == null) {
            $this->product = $this->coreRegistry->registry('current_product');
        }
        return $this->product;

    }

    /**
     * Gets Installment amount for current product
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAmount()
    {
        $amountPerInstallment ='';
        $totalAmount = $this->getCurrentProduct()->getFinalPrice();
        $InstallmentNo = $this->helper->getConfigData('installment_no');
        if($InstallmentNo){
            $curInstallment = $InstallmentNo;
        }
        if($curInstallment){
            $amountPerInstallment = $totalAmount;
        }
        return $amountPerInstallment;
    }

     /**
     * @throws \Magento\Framework\Exception\LocalizedException
     * @param string $methodCode
     * @return bool
     */
    public function showOnPDP($methodCode = null)
    {
        if ($methodCode)
        {
            return $this->helper->getConfigData('show_on_pdp', null, $methodCode);
        }

        if (!$this->helper->isLatitudepayEnabled() && !$this->helper->isGenoapayEnabled())
            return 0;

        return $this->helper->getConfigData('show_on_pdp'); //based on whatever's active (depending on store's currency setting)
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
            'amount' => $this->getAmount(),
            'services' => $this->helper->getLatitudepayPaymentServices(),
            'terms' => $this->helper->getLatitudepayPaymentTerms(),
            'style' => 'default'
        ];
        return $this->helper->getSnippetImageUrl() . '?' . http_build_query($param);
    }

    /**
     * Retrieve util js
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return \Magento\Framework\Phrase
     */
    public function getUtilJs()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->helper->getUtilJs();
    }

    protected function getPriceForLC()
    {
        $product = $this->getCurrentProduct();

        if ($product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            return round((float)$product->getFinalPrice(), 2);
        }

        return round((float)$product->getPrice(), 2);
    }

    public function getLCOptions($page)
    {
        $product = $this->getCurrentProduct();

        return json_encode([
            "merchantId" => $this->helper->getConfigData('merchant_id', null, 'latitude'),
            "currency" => $this->helper->getStoreCurrency(),
            "container" =>"latitude-banner-container",
            "containerClass" => "",
            "page" => $page,
            "layout" => $this->helper->getConfigData('layout', null, 'latitude'),
            "paymentOption" => $this->helper->getConfigData('plan_type', null, 'latitude'),
            "promotionMonths" => $this->helper->getConfigData('plan_period', null, 'latitude'),
            "minAmount" => $this->helper->getConfigData('minimum_amount', null, 'latitude'), 
            "product" => [
                "id" => $product->getId() ? $product->getId() : '',
                "name" =>  $product->getName() ? $product->getName() : '',
                "category" =>  $product->getCategory() ? $product->getCategory() : '',
                "price" => $this->getPriceForLC(),
                "sku" =>  $product->getSku() ? $product->getSku() : '',
            ]
        ]);
    }

    public function getLCMerchantID()
    {
       return $this->helper->getConfigData('merchant_id', null, 'latitude');
    }

    public function getLCHost()
    {
        $isTest = (boolean)($this->helper->getConfigData('test_mode', null, 'latitude') === '1');
        return $isTest ? 
            'https://develop.checkout.dev.merchant-services-np.lfscnp.com' 
            : 
            'https://checkout.latitudefinancial.com';
    }

    /**
     * override from \Magento\Framework\View\Element\Template
     */
    public function _toHtml()
    {
        $_product = $this->getCurrentProduct();
        if(!$_product){
            return '';
        }
        if( $_product->isAvailable() && $_product->isSaleable() && ($this->helper->isLatitudepayEnabled() || $this->helper->isGenoapayEnabled() || $this->helper->isLCEnabled())){
            return parent::_toHtml();
        }
        return '';
    }
}