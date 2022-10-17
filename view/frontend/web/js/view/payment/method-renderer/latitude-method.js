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
        'Magento_Ui/js/model/messageList'
    ],
    function ($,Component, quote, totals, messageList) {
        'use strict';
        return Component.extend({
            totals: quote.getTotals(),
            defaults: {
                template: 'LatitudeNew_Payment/payment/latitude'
            },
            initialize: function () {
                this._super();
                var _self = this;
                _self.PaymentFailed();
                return this;
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
                                    window.location.replace(window.BASE_URL + 'latitudepay/lchandover/index');
                                    return false;
                                }
                            }
                        );

                    return true;
                }

                return false;
            },
            
            getInstallmentText: function() {
                window.LatitudeCheckout = window.checkoutConfig.latitudepayments.lc_options;

                window.LatitudeCheckout.container = {
                    main: "main-container",
                    footer: "footer-container",
                };

                var totals = quote.getTotals()();

                window.LatitudeCheckout.checkout = {
                    shippingAmount: totals.base_shipping_amount,
                    discount: totals.base_discount_amount,
                    taxAmount: totals.base_tax_amount,
                    subTotal: totals.base_subtotal,
                    total: totals.base_grand_total,
                };

                $.ajax({
                    url: window.checkoutConfig.latitudepayments.lc_script,
                    dataType: "script",
                    cache: true,
                }).fail(function (xhr, status) {
                    console.error("Could not Load latitude content. Failed with " + status);
                });
            },

            getLogoUrl: function() {
                return window.checkoutConfig.latitudepayments.latitude;
            },
            
            PaymentFailed: function () {
                var cancelUrl = document.URL.split('?')[1];
                if(cancelUrl){
                    var CancelRedirect = cancelUrl.split("/")[0];
                }
                if(CancelRedirect){
                    var msg = $.mage.__('There was an error with your payment, please try again or select other payment method');
                    if(cookieStorage.getItem('mage-messages')){
                        var messages = JSON.parse(cookieStorage.getItem('mage-messages'));
                        if(messages && messages.length){
                            messages.forEach(message => {
                                if(message.type == 'error'){
                                    messageList.addErrorMessage({ message: message.text });
                                }
                            });
                            cookieStorage.setItem('mage-messages','[]');
                        } else {
                            //messageList.addErrorMessage({ message: msg });
                        }
                    } else {
                        //messageList.addErrorMessage({ message: msg });
                    }
                    
                }
            }
        });
    }
);
