define(['jquery'],function($) {
    'use strict';
    return function(config, element) {
        function initPaymentOptionPopup () {
            $.getScript( config.utilJs, function( data, textStatus, jqxhr ) {});
        }
        $(document).ready(function () {
            initPaymentOptionPopup();
        });
        return {
            initPaymentOptionPopup: initPaymentOptionPopup
        }
    }
});
