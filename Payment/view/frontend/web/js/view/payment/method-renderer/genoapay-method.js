/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/totals',
        //'Magento_Ui/js/model/messageList'
    ],
    function ($,Component, quote, totals) {
        'use strict';
        return Component.extend({
            totals: quote.getTotals(),
            defaults: {
                template: 'LatitudeNew_Payment/payment/genoapay'
            },
            initialize: function () {
                this._super();
                // var _self = this;
                // _self.PaymentFailed();
                return this;
            },
            initPopup: function() {
                $.getScript('https://latitudepay-image-api-dev.dev.merchant-integration-bnpl-np.lfscnp.com/v2/util.js') //window.checkoutConfig.latitudepayments.utilJs
                .done(function( script, textStatus ) {
                    console.log( textStatus );
                  })
                  .fail(function( jqxhr, settings, exception ) {
                    console.log(exception);
                });
            },
            placeOrder: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                if (this.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                                        
                    //https://alanstorm.com/magento-2-checkout-application-order-placing/
                    //returns the jQuery ajax request (actually a jQuery “deferred” object that does an ajax request, so essentially the same thing) 
                    //that places the order, with the fail and done handlers handling success and failure cases. 
                    this.getPlaceOrderDeferredObject()
                        .fail(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(
                            function () {
                                if (self.redirectAfterPlaceOrder) {
                                    if (!location.origin) {
                                        location.origin = location.protocol + "//" + location.host;
                                    }
                                    window.location.replace(window.authenticationPopup.baseUrl + 'latitudepay/handoverurl/index');
                                    return false;
                                }
                            }
                        );

                    return true;
                }

                return false;
            },
            /** Returns payment method instructions */
            getInstallmentText: function() {
                var grandTotal  = 0,
                installmentText = '',
                curInstallment  = window.checkoutConfig.latitudepayments.installmentno,
                currency        = window.checkoutConfig.latitudepayments.currency_symbol,
                grandTotal      = totals.getSegment('grand_total').value,
                html            = window.checkoutConfig.latitudepayments.gpay_installment_block;
                if(grandTotal){
                    installmentText = html.replace('__AMOUNT__',grandTotal);
                }
                return installmentText;
            },
            // PaymentFailed: function () {
            //     var cancelUrl = document.URL.split('?')[1];
            //     if(cancelUrl){
            //         var CancelRedirect = cancelUrl.split("/")[0];
            //     }
            //     if(CancelRedirect){
            //         var msg = $.mage.__('There was an error with your payment, please try again or select other payment method');
            //         if(cookieStorage.getItem('mage-messages')){
            //             var messages = JSON.parse(cookieStorage.getItem('mage-messages'));
            //             if(messages && messages.length){
            //                 messages.forEach(message => {
            //                     if(message.type == 'error'){
            //                         messageList.addErrorMessage({ message: message.text });
            //                     }
            //                 });
            //                 cookieStorage.setItem('mage-messages','[]');
            //             } else {
            //                 messageList.addErrorMessage({ message: msg });
            //             }
            //         } else {
            //             messageList.addErrorMessage({ message: msg });
            //         }
                    
            //     }
            // }
        });
    }
);
