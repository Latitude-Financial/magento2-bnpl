var config = {
    config: {
        mixins: {
            'Magento_Bundle/js/product-summary': {
             'LatitudeNew_Payment/js/product-summary': true
            },
            'Magento_Swatches/js/swatch-renderer': {
                'LatitudeNew_Payment/js/swatch-renderer': true
            }
        }
    },
    map: {
        '*': {
            "paymentOptionPopup": "LatitudeNew_Payment/js/payment-popup"
        }
    }

};
