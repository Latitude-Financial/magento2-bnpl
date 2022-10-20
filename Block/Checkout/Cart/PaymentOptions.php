<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/**
 * Payment Options rendering block
 * Class PaymentOptions
 * @package Latitude\Payment\Block\Catalog\Product\View\PaymentOptions
 */
namespace LatitudeNew\Payment\Block\Checkout\Cart;

use \Magento\Catalog\Block\Product\Context;
/**
 * PaymentOptions block
 *
 */
class PaymentOptions extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Checkout\Model\Cart
     */
    protected $cart;

    /**
     * @var \Latitude\Payment\Helper\Config
     */
    protected $configHelper;

    /**
     * PaymentOptions constructor.
     * @param Context $context
     * @param \Magento\Checkout\Model\Cart $cart
     * @param \LatitudeNew\Payment\Helper\Data $helper
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Checkout\Model\Cart $cart,
        \LatitudeNew\Payment\Helper\Data $helper,
        array $data = []
        ) {
        $this->cart  = $cart;
        $this->helper  = $helper;
        parent::__construct(
            $context,
            $data
        );
    }

    /**
     * Gets Installment amount for current product
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAmount()
    {
        $totalAmount = $this->cart->getQuote()->getGrandTotal();
        return $totalAmount;
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     * @param string $methodCode
     * @return bool
     */
    public function showOnCart($methodCode=null)
    {
        if ($methodCode)
        {
            return $this->helper->getConfigData('show_on_cart', null, $methodCode);
        }

        if (!$this->helper->isLatitudepayEnabled() && !$this->helper->isGenoapayEnabled())
            return 0;
            
        return $this->helper->getConfigData('show_on_cart'); //based on whatever's active (depending on store's currency setting)
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

    public function getLCOptions($page)
    {
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
                "id" => 'cart',
                "name" =>  'cart',
                "category" => '',
                "price" => $this->getAmount(),
                "sku" => '',
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
     * Retrieve Block Html
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return string
     */
    public function _toHtml()
    {
        if($this->helper->isLatitudepayEnabled() || $this->helper->isGenoapayEnabled() || $this->helper->isLCEnabled()){
            return parent::_toHtml();
        }
        return '';
    }
}