<?php
/**
 * Stripe Connect Payment Form
 * Displayed at checkout
 */

if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_script('stripe-connect-js');
wp_enqueue_style('stripe-connect-css');

$sandbox_mode = st()->get_option('stripe_connect_enable_sandbox', 'on');
$publishable_key = ST_Stripe_Connect::get_instance()->get_publishable_key();
?>

<div class="pm-info">
    <div class="row">
        <div class="col-sm-12">
            <div class="stripe-connect-info-message" style="background: #f0f8ff; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <p style="margin: 0; color: #0066cc;">
                    <strong><?php _e('Paiement sécurisé via Stripe Connect', 'vina-stripe-connect'); ?></strong>
                </p>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">
                    <?php _e('Votre paiement sera traité de manière sécurisée. Le vigneron recevra directement 80% du montant.', 'vina-stripe-connect'); ?>
                </p>
            </div>

            <div class="col-card-info stripe-connect-card-info">
                <div id="stripe_connect_card_form" class="stripe-connect-card-element"></div>
                <div id="stripe_connect_card_errors" class="stripe-connect-error-message" role="alert"></div>
            </div>

            <input type="hidden" id="stripe_connect_payment_method_id" name="stripe_connect_payment_method_id" value="">
            <input type="hidden" id="wait_validate_stripe_connect" name="wait_validate_stripe_connect" value="wait">
        </div>
    </div>
</div>

<?php
$sanbox = ($sandbox_mode == 'on') ? 'sandbox' : 'live';
?>

<script type="text/javascript">
jQuery(document).ready(function($) {
    'use strict';

    var stripePublishKey = "<?php echo esc_js($publishable_key); ?>";
    var sanbox = "<?php echo esc_js($sanbox); ?>";

    if (!stripePublishKey) {
        console.error('Stripe publishable key not configured');
        return;
    }

    var stripe = Stripe(stripePublishKey);
    var elements = stripe.elements();

    // Custom styling for card element
    var style = {
        base: {
            color: '#32325d',
            fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
            fontSmoothing: 'antialiased',
            fontSize: '16px',
            lineHeight: '24px',
            '::placeholder': {
                color: '#aab7c4'
            }
        },
        invalid: {
            color: '#fa755a',
            iconColor: '#fa755a'
        }
    };

    // Create card element
    var cardElement = elements.create('card', {style: style});
    var cardErrors = $('#stripe_connect_card_errors');

    // Mount card element
    cardElement.mount('#stripe_connect_card_form');

    // Handle real-time validation errors
    cardElement.addEventListener('change', function(event) {
        if (event.error) {
            cardErrors.text(event.error.message).show();
        } else {
            cardErrors.hide();
        }
    });

    var paymentMethodId = $('#stripe_connect_payment_method_id');
    var waitValidate = $('#wait_validate_stripe_connect');

    // Create payment method
    var createPaymentMethod = function(type) {
        var cardholderName = '<?php echo __('Client', 'vina-stripe-connect'); ?>';

        if ($('#field-st_first_name').length) {
            cardholderName = $('#field-st_first_name').val();
        }

        if ($('#field-st_last_name').length) {
            var lastName = $('#field-st_last_name').val();
            cardholderName += ' ' + lastName;
        }

        stripe.createPaymentMethod({
            type: 'card',
            card: cardElement,
            billing_details: {
                name: cardholderName
            }
        }).then(function(result) {
            if (result.error) {
                cardErrors.text(result.error.message).show();
                $('.st-btn-process-action').removeClass('loading');
            } else {
                paymentMethodId.val(result.paymentMethod.id);

                switch (type) {
                    case 'modal':
                        $('.booking_modal_form').STSendModalBookingAjax();
                        break;
                    case 'form':
                        $('#cc-form').STSendAjax();
                        break;
                }
            }
        });
    };

    // Modal checkout
    $('.booking_modal_form', 'body').on('st_wait_checkout_modal', function(e) {
        var payment = $('input[name="st_payment_gateway"]:checked', this).val();
        if (payment === 'stripe_connect') {
            createPaymentMethod('modal');
            return false;
        }
        return true;
    });

    // Regular form checkout
    $('#cc-form', 'body').on('st_wait_checkout', function(e) {
        var payment = $('input[name="st_payment_gateway"]:checked', this).val();
        if (payment === 'stripe_connect') {
            createPaymentMethod('form');
            return false;
        }
        return true;
    });
});
</script>
