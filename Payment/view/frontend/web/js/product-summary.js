/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define(['jquery','priceUtils'], function ($, utils)
{
    'use strict';

    return function (widget)
    {
        $.widget('mage.productSummary', widget,
            {
                _renderSummaryBox: function renderSummaryBox(event, data)
                {
                    var curInstallment  = 10,
                        priceFormat = {},
                        configuredPrice = parseFloat($('.price-as-configured .price').html().replace(/[^0-9.]/g,''));
                    if(configuredPrice){
                        var amountPerInstallment = configuredPrice / curInstallment,
                            installmentText = Math.floor(amountPerInstallment * 100) / 100,
                            price = utils.formatPrice(installmentText, priceFormat);
                        $(".latitude-icon .price").html(price);
                    }

                    this._super(event, data);

                }
            });

        return $.mage.productSummary;
    }
});