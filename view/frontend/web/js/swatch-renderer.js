/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define(['jquery', 'underscore','priceUtils','mage/utils/wrapper'], function ($, _, utils,wrapper)
{
    'use strict';
    return function (swatchRenderer)
    {
        var UpdatePriceWrapper = wrapper.wrap(swatchRenderer.prototype._UpdatePrice, function(originalSwatchRenderer){

            var $widget = this,
                $product = $widget.element.parents($widget.options.selectorProduct),
                $productPrice = $product.find(this.options.selectorProductPrice),
                options = _.object(_.keys($widget.optionsMap), {}),
                result,
                isconfiguredPrice;
            $widget.element.find('.' + $widget.options.classes.attributeClass + '[option-selected]').each(function () {
                var attributeId = $(this).attr('attribute-id');

                options[attributeId] = $(this).attr('option-selected');
            });
            result = $widget.options.jsonConfig.optionPrices[_.findKey($widget.options.jsonConfig.index, options)];
            isconfiguredPrice  = typeof result != 'undefined';
           if(isconfiguredPrice){
            var configuredPrice =  result.finalPrice.amount,
                curInstallment  = 10,
                priceFormat  = {};
            if(configuredPrice){
                var amountPerInstallment = configuredPrice / curInstallment,
                    installmentText = (amountPerInstallment * 100) / 100,
                    price = utils.formatPrice(installmentText, priceFormat);
                    console.log(amountPerInstallment);
                $(".latitude-icon .price").html(price);
            }
           }
            return originalSwatchRenderer();
        });
        swatchRenderer.prototype._UpdatePrice = UpdatePriceWrapper;
        return swatchRenderer;
    }
});