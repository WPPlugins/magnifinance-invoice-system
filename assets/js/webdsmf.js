if (!WEBDSMF) {
    var WEBDSMF = {};
} else {
    if (WEBDSMF && typeof WEBDSMF !== "object") {
        throw new Error("WEBDSMF is not an Object type");
    }
}
WEBDSMF.isLoaded = false;
WEBDSMF.STARTS = function ($) {
    return{NAME: "Application initialize module", VERSION: 1.3, init: function () {
            this.validateForm();
            this.validateinput();
        },
        validateForm: function () {
            if ($('body').hasClass('woocommerce_page_woocommerce_magnifinance')) {
                if ($('input#wc_mf_loginemail').length > 0)
                    return;
                
                $('form#mainform').submit(function (event) {

                    var form = $(this);

                    if (!$('input#wc_mf_license_key').val() && !$('input#wc_mf_license_email').val()) {
                        event.preventDefault();
                        $('input#wc_mf_license_email').addClass('invalid');
                        $('input#wc_mf_license_key').addClass('invalid');
                    }
                });
            }
        },
        validateinput: function () {
            $('input#wc_mf_license_key,input#wc_mf_license_email').keyup(function () {
                var input = $(this).val();
                if (input == "" || input == null) {
                    $(this).addClass('invalid')
                } else {
                    $(this).removeClass('invalid')
                }
            });
            $('input#wc_mf_license_key,input#wc_mf_license_email').focusout(function () {
                var input = $(this).val();
                if (input == "" || input == null) {
                    $(this).addClass('invalid')
                } else {
                    $(this).removeClass('invalid');
                }
            });
        }
    };
}(jQuery);
jQuery(document).ready(function () {
    WEBDSMF.STARTS.init();
});
