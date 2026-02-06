jQuery(function($) {
    'use strict';

    var old_order_id = false;

    /**
     * Handle 3DS confirmation
     */
    function handle3DSConfirmation(paymentIntentClientSecret, orderData, callback) {
        var publishKey = orderData.publishKey;

        if (orderData.sanbox === 'sandbox') {
            publishKey = orderData.testPublishKey;
        }

        var stripe = Stripe(publishKey);

        stripe.handleCardAction(paymentIntentClientSecret).then(function(result) {
            if (result.error) {
                alert(result.error.message);
                callback(false);
            } else {
                // Send confirmation to server
                $.ajax({
                    url: stripe_connect_params.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'stripe_connect_confirm_payment',
                        payment_intent_id: result.paymentIntent.id,
                        st_order_id: orderData.order_id || old_order_id,
                        _s: stripe_connect_params._s
                    },
                    success: function(response) {
                        if (response.success && response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            callback(false);
                        }
                    },
                    error: function() {
                        alert('Server error during confirmation');
                        callback(false);
                    }
                });
            }
        });
    }

    /**
     * Handle checkout response
     */
    function handleCheckoutResponse(data, button, form) {
        // Store order ID
        if (data.order_id) {
            old_order_id = data.order_id;
        }

        // Check for 3DS requirement
        if (data.payment_intent_client_secret && data.requires_action) {
            handle3DSConfirmation(data.payment_intent_client_secret, data, function(success) {
                if (!success) {
                    button.removeClass('loading');
                }
            });
            return;
        }

        // Success - redirect
        if (data.success && data.redirect_form) {
            window.location.href = data.redirect_form;
            return;
        }

        if (data.redirect) {
            window.location.href = data.redirect;
            return;
        }

        // Error handling
        if (data.message) {
            form.find('.form_alert').addClass('alert-danger').removeClass('hidden').html(data.message);
        }

        button.removeClass('loading');
    }

    /**
     * Modal booking integration
     */
    $('.booking_modal_form', 'body').on('st_wait_checkout_modal', function(e) {
        var form = $(this);
        var payment = $('input[name="st_payment_gateway"]:checked', form).val();

        if (payment === 'stripe_connect') {
            var waitValidate = $('input[name="wait_validate_stripe_connect"]', form).val();

            if (waitValidate === 'wait') {
                // Payment method creation is handled in the form view
                return false;
            }
        }

        return true;
    });

    /**
     * Standard form checkout integration
     */
    $('#cc-form', 'body').on('st_wait_checkout', function(e) {
        var form = $(this);
        var payment = $('input[name="st_payment_gateway"]:checked', form).val();

        if (payment === 'stripe_connect') {
            var waitValidate = $('input[name="wait_validate_stripe_connect"]', form).val();

            if (waitValidate === 'wait') {
                // Payment method creation is handled in the form view
                return false;
            }
        }

        return true;
    });

    /**
     * Validate before checkout
     */
    $('#cc-form', 'body').on('st_before_checkout', function(e) {
        var form = $(this);
        var payment = $('input[name="st_payment_gateway"]:checked', form).val();

        if (payment === 'stripe_connect') {
            $('input[name="wait_validate_stripe_connect"]', form).val('wait');
        }
    });

    $('.booking_modal_form', 'body').on('st_before_checkout_modal', function(e) {
        var form = $(this);
        var payment = $('input[name="st_payment_gateway"]:checked', form).val();

        if (payment === 'stripe_connect') {
            $('input[name="wait_validate_stripe_connect"]', form).val('wait');
        }
    });

    /**
     * AJAX form submission customization for Stripe Connect
     */
    $(document).on('st_checkout_success', function(event, data) {
        if (data.payment_gateway === 'stripe_connect') {
            var button = $('.st-btn-process-action');

            if (data.requires_action && data.payment_intent_client_secret) {
                handle3DSConfirmation(data.payment_intent_client_secret, data, function(success) {
                    if (!success) {
                        button.removeClass('loading');
                    }
                });
            } else {
                handleCheckoutResponse(data, button, $('#cc-form'));
            }
        }
    });

    /**
     * Helper: Get new captcha (if used)
     */
    function get_new_captcha(form) {
        var captcha_box = form.find('.captcha_box');

        if (captcha_box.length) {
            var url = captcha_box.find('.captcha_img').attr('src');
            captcha_box.find('.captcha_img').attr('src', url);
        }
    }

    /**
     * Stripe Connect button hover effect
     */
    $(document).on('mouseenter', '.stripe-connect-dashboard-btn', function() {
        $(this).css('background', '#5145e6');
    }).on('mouseleave', '.stripe-connect-dashboard-btn', function() {
        $(this).css('background', '#635bff');
    });

    $(document).on('mouseenter', '.stripe-connect-btn', function() {
        $(this).css('background', '#5145e6');
    }).on('mouseleave', '.stripe-connect-btn', function() {
        $(this).css('background', '#635bff');
    });
});
