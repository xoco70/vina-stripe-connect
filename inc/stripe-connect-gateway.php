<?php
/**
 * Stripe Connect Payment Gateway
 *
 * Handles payments with Stripe Connect Express
 * Implements Separate Charge and Transfer with 20% application fee
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ST_Stripe_Connect_Payment_Gateway') && class_exists('STAbstactPaymentGateway')) {

    class ST_Stripe_Connect_Payment_Gateway extends STAbstactPaymentGateway {

        public static $_inst;
        private $default_status = true;
        private $_gateway_id = 'stripe_connect';

        /**
         * Application fee percentage (20%)
         */
        const APPLICATION_FEE_PERCENT = 0.20;

        public function __construct() {
            add_filter('st_payment_gateway_stripe_connect', [$this, 'get_name']);
            add_action('admin_notices', [$this, 'add_notices']);
            add_action('wp_enqueue_scripts', [$this, 'load_scripts']);
        }

        /**
         * Load scripts
         */
        public function load_scripts() {
            if (st()->get_option('pm_gway_stripe_connect_enable') !== 'on') {
                return;
            }

            wp_register_style('stripe-connect-css', ST_STRIPE_CONNECT_PLUGIN_URL . 'assets/css/stripe-connect.css');
            wp_register_script('stripe-connect-js-lib', 'https://js.stripe.com/v3/', [], null, true);
            wp_register_script('stripe-connect-js', ST_STRIPE_CONNECT_PLUGIN_URL . 'assets/js/stripe-connect.js', ['jquery'], ST_STRIPE_CONNECT_VERSION, true);

            wp_localize_script('jquery', 'stripe_connect_params', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'home_url' => home_url('/'),
                '_s' => wp_create_nonce('_wpnonce_security'),
            ]);

            if (!wp_script_is('stripe-api', 'enqueued')) {
                wp_enqueue_script('stripe-connect-js-lib');
            }

            wp_enqueue_style('stripe-connect-css');
            wp_enqueue_script('stripe-connect-js');
        }

        /**
         * Admin notices
         */
        public function add_notices() {
            // Add any admin notices if needed
        }

        /**
         * Get option fields for Theme Options
         */
        public function get_option_fields() {
            return [
                [
                    'id' => 'stripe_connect_publish_key',
                    'label' => __('Publishable Key', 'vina-stripe-connect'),
                    'type' => 'text',
                    'section' => 'option_pmgateway',
                    'desc' => __('Your Stripe Platform Publishable Key', 'vina-stripe-connect'),
                    'condition' => 'pm_gway_stripe_connect_enable:is(on)'
                ],
                [
                    'id' => 'stripe_connect_secret_key',
                    'label' => __('Secret Key', 'vina-stripe-connect'),
                    'type' => 'text',
                    'section' => 'option_pmgateway',
                    'desc' => __('Your Stripe Platform Secret Key', 'vina-stripe-connect'),
                    'condition' => 'pm_gway_stripe_connect_enable:is(on)'
                ],
                [
                    'id' => 'stripe_connect_enable_sandbox',
                    'label' => __('Enable Sandbox Mode', 'vina-stripe-connect'),
                    'type' => 'on-off',
                    'section' => 'option_pmgateway',
                    'std' => 'on',
                    'desc' => __('Enable test mode for Stripe Connect', 'vina-stripe-connect'),
                    'condition' => 'pm_gway_stripe_connect_enable:is(on)'
                ],
                [
                    'id' => 'stripe_connect_test_publish_key',
                    'label' => __('Test Publishable Key', 'vina-stripe-connect'),
                    'type' => 'text',
                    'section' => 'option_pmgateway',
                    'desc' => __('Your Stripe Platform Test Publishable Key', 'vina-stripe-connect'),
                    'condition' => 'pm_gway_stripe_connect_enable:is(on),stripe_connect_enable_sandbox:is(on)'
                ],
                [
                    'id' => 'stripe_connect_test_secret_key',
                    'label' => __('Test Secret Key', 'vina-stripe-connect'),
                    'type' => 'text',
                    'section' => 'option_pmgateway',
                    'desc' => __('Your Stripe Platform Test Secret Key', 'vina-stripe-connect'),
                    'condition' => 'pm_gway_stripe_connect_enable:is(on),stripe_connect_enable_sandbox:is(on)'
                ],
            ];
        }

        /**
         * Validate before checkout
         */
        public function _pre_checkout_validate() {
            return true;
        }

        /**
         * Get Stripe secret key
         */
        private function get_stripe_secret_key() {
            $sandbox_mode = st()->get_option('stripe_connect_enable_sandbox', 'on');

            if ($sandbox_mode === 'on') {
                return st()->get_option('stripe_connect_test_secret_key', '');
            }

            return st()->get_option('stripe_connect_secret_key', '');
        }

        /**
         * Get Stripe publishable key
         */
        private function get_stripe_publishable_key() {
            $sandbox_mode = st()->get_option('stripe_connect_enable_sandbox', 'on');

            if ($sandbox_mode === 'on') {
                return st()->get_option('stripe_connect_test_publish_key', '');
            }

            return st()->get_option('stripe_connect_publish_key', '');
        }

        /**
         * Main checkout function
         */
        public function do_checkout($order_id) {
            $result = $this->create_payment_intent($order_id);

            if ($result['status']) {
                $result['publishKey'] = $this->get_stripe_publishable_key();
                $result['sanbox'] = st()->get_option('stripe_connect_enable_sandbox', 'on') === 'on' ? 'sandbox' : 'live';

                return $result;
            }

            return [
                'status' => false,
                'message' => isset($result['message']) ? $result['message'] : __('Payment failed', 'vina-stripe-connect'),
                'redirect' => STCart::get_success_link($order_id),
            ];
        }

        /**
         * Create PaymentIntent with application fee and transfer
         */
        private function create_payment_intent($order_id) {
            $stripe_secret_key = $this->get_stripe_secret_key();

            if (empty($stripe_secret_key)) {
                return [
                    'status' => false,
                    'message' => __('Stripe API key not configured', 'vina-stripe-connect')
                ];
            }

            // Get order details
            $total = get_post_meta($order_id, 'total_price', true);
            $st_first_name = get_post_meta($order_id, 'st_first_name', true);
            $st_last_name = get_post_meta($order_id, 'st_last_name', true);
            $st_address = get_post_meta($order_id, 'st_address', true);
            $st_zip_code = get_post_meta($order_id, 'st_zip_code', true);
            $st_country = get_post_meta($order_id, 'st_country', true);
            $st_cart_info = get_post_meta($order_id, 'st_cart_info', true);
            $st_booking_id = get_post_meta($order_id, 'st_booking_id', true);
            $cart_info = maybe_unserialize($st_cart_info);

            $total = round((float)$total, 2);
            $payment_method_id = STInput::post('stripe_connect_payment_method_id');

            // Get connected account for the activity owner
            require_once ST_STRIPE_CONNECT_PLUGIN_PATH . 'inc/stripe-connect-accounts.php';
            $accounts_manager = ST_Stripe_Connect_Accounts::get_instance();
            $connected_account_id = $accounts_manager->get_account_for_post($st_booking_id);

            if (!$connected_account_id) {
                return [
                    'status' => false,
                    'message' => __('Partner has not connected their Stripe account. Payment cannot be processed.', 'vina-stripe-connect')
                ];
            }

            \Stripe\Stripe::setApiKey($stripe_secret_key);

            try {
                if (!$payment_method_id) {
                    return [
                        'status' => false,
                        'message' => __('Payment method is required', 'vina-stripe-connect')
                    ];
                }

                // Create customer
                $customer = \Stripe\Customer::create([
                    'name' => $st_first_name . ' ' . $st_last_name,
                    'address' => [
                        'line1' => $st_address,
                        'postal_code' => $st_zip_code,
                        'country' => $st_country,
                    ],
                ]);

                // Handle currency conversion
                $list_currency_zero = [
                    'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW',
                    'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'
                ];

                $currency = get_post_meta($order_id, 'currency', true);
                $rate = $currency['rate'];
                $money = TravelHelper::convert_money($total, $rate);
                $currency_code = TravelHelper::get_current_currency('name');

                if (in_array($currency_code, $list_currency_zero)) {
                    $money = (int) number_format($money, 0, '.', '');
                } else {
                    $money = (int) number_format($money * 100, 0, '.', '');
                }

                // Calculate application fee (20%)
                $application_fee = (int) ($money * self::APPLICATION_FEE_PERCENT);

                // Create PaymentIntent with Separate Charge and Transfer
                $intent = \Stripe\PaymentIntent::create([
                    'customer' => $customer->id,
                    'payment_method' => $payment_method_id,
                    'amount' => $money,
                    'currency' => $currency_code,
                    'application_fee_amount' => $application_fee,
                    'transfer_data' => [
                        'destination' => $connected_account_id,
                    ],
                    'description' => sprintf(
                        __('Full name: %s %s - Service: %s', 'vina-stripe-connect'),
                        $st_first_name,
                        $st_last_name,
                        esc_html($cart_info[$st_booking_id]['title'])
                    ),
                    'confirmation_method' => 'manual',
                    'confirm' => true,
                    'return_url' => add_query_arg([
                        'order_id' => $order_id,
                        'payment_intent' => '{PAYMENT_INTENT_ID}',
                    ], home_url('/checkout/')),
                    'metadata' => [
                        'order_id' => $order_id,
                        'booking_id' => $st_booking_id,
                        'partner_account' => $connected_account_id,
                    ],
                ]);

                return $this->generate_payment_response($intent, $order_id);

            } catch (\Stripe\Exception\CardException $e) {
                wp_delete_post($order_id, true);

                return [
                    'status' => false,
                    'message' => $e->getMessage(),
                ];

            } catch (\Stripe\Exception\ApiErrorException $e) {
                wp_delete_post($order_id, true);

                return [
                    'status' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        /**
         * Generate payment response based on PaymentIntent status
         */
        private function generate_payment_response($intent, $order_id) {
            if ($intent->status == 'succeeded') {
                // Payment succeeded immediately
                do_action('stripe_connect_before_update_status', $intent->status, $order_id);

                update_post_meta($order_id, 'status', 'complete');
                update_post_meta($order_id, 'transaction_id', $intent->id);

                global $wpdb;
                $table = $wpdb->prefix . 'st_order_item_meta';

                $wpdb->update(
                    $table,
                    ['status' => 'complete'],
                    ['order_item_id' => $order_id]
                );

                // Update availability
                $this->update_booking_availability($order_id);

                // Send confirmation email
                STCart::send_mail_after_booking($order_id, true);

                return [
                    'status' => true,
                    'redirect_form' => STCart::get_success_link($order_id),
                    'success' => true,
                ];

            } elseif ($intent->status == 'requires_action' || $intent->status == 'requires_source_action') {
                // Requires 3DS authentication
                update_post_meta($order_id, 'status', 'incomplete');
                update_post_meta($order_id, 'transaction_id', $intent->id);

                global $wpdb;
                $table = $wpdb->prefix . 'st_order_item_meta';

                $wpdb->update(
                    $table,
                    ['status' => 'incomplete'],
                    ['order_item_id' => $order_id]
                );

                return [
                    'status' => true,
                    'redirect_form' => STCart::get_success_link($order_id),
                    'requires_action' => true,
                    'payment_intent_client_secret' => $intent->client_secret
                ];

            } else {
                // Invalid status
                return [
                    'status' => false,
                    'error' => __('Invalid PaymentIntent status', 'vina-stripe-connect'),
                ];
            }
        }

        /**
         * Confirm payment intent (for 3DS)
         */
        public function confirm_payment_intent($payment_intent_id, $order_id) {
            $stripe_secret_key = $this->get_stripe_secret_key();

            \Stripe\Stripe::setApiKey($stripe_secret_key);

            try {
                $intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);

                if ($intent->status == 'succeeded') {
                    update_post_meta($order_id, 'status', 'complete');

                    global $wpdb;
                    $table = $wpdb->prefix . 'st_order_item_meta';

                    $wpdb->update(
                        $table,
                        ['status' => 'complete'],
                        ['order_item_id' => $order_id]
                    );

                    $this->update_booking_availability($order_id);
                    STCart::send_mail_after_booking($order_id, true);

                    return [
                        'success' => true,
                        'redirect' => STCart::get_success_link($order_id)
                    ];
                }

                return [
                    'success' => false,
                    'message' => __('Payment confirmation failed', 'vina-stripe-connect')
                ];

            } catch (\Stripe\Exception\ApiErrorException $e) {
                return [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        /**
         * Update booking availability
         */
        private function update_booking_availability($order_id) {
            $get_order = $this->st_get_order_by_order_item_id($order_id);

            if (empty($get_order)) {
                return;
            }

            global $wpdb;
            $post_type = $get_order['st_booking_post_type'];
            $check_in_timestamp = $get_order['check_in_timestamp'];
            $check_out_timestamp = $get_order['check_out_timestamp'];
            $post_id = $get_order['st_booking_id'];
            $booked = 1;

            switch ($post_type) {
                case 'st_tours':
                case 'st_activity':
                    $table_avai = $wpdb->prefix . ($post_type == 'st_activity' ? 'st_activity_availability' : 'st_tour_availability');
                    $adult_number = $get_order['adult_number'];
                    $child_number = $get_order['child_number'];
                    $infant_number = $get_order['infant_number'];
                    $number_booked = $adult_number + $child_number + $infant_number;

                    $sql = $wpdb->prepare(
                        "UPDATE {$table_avai} SET number_booked = IFNULL(number_booked, 0) + %d WHERE post_id = %d AND check_in = %s",
                        $number_booked,
                        $post_id,
                        $check_in_timestamp
                    );

                    $wpdb->query($sql);
                    break;

                case 'st_rental':
                    $table_avai = $wpdb->prefix . 'st_rental_availability';
                    $sql = $wpdb->prepare(
                        "UPDATE {$table_avai} SET number_booked = IFNULL(number_booked, 0) + %d WHERE post_id = %d AND check_in >= %d AND check_out <= %d",
                        $booked,
                        $post_id,
                        $check_in_timestamp,
                        $check_out_timestamp
                    );

                    $wpdb->query($sql);
                    break;

                case 'st_hotel':
                case 'hotel_room':
                    $table_avai = $wpdb->prefix . 'st_room_availability';
                    $sql = $wpdb->prepare(
                        "UPDATE {$table_avai} SET number_booked = IFNULL(number_booked, 0) + %d WHERE post_id = %d AND check_in >= %d AND check_out <= %d",
                        $booked,
                        $post_id,
                        $check_in_timestamp,
                        $check_out_timestamp
                    );

                    $wpdb->query($sql);
                    break;
            }
        }

        /**
         * Get order by order item ID
         */
        private function st_get_order_by_order_item_id($order_item_id) {
            global $wpdb;
            $query = 'SELECT * FROM ' . $wpdb->prefix . 'st_order_item_meta WHERE 1=1 AND order_item_id = ' . intval($order_item_id);
            return $wpdb->get_row($query, ARRAY_A);
        }

        /**
         * Check if purchase is complete
         */
        public function check_complete_purchase($order_id) {
            return apply_filters('stripe_connect_complete_purchase', false);
        }

        /**
         * Render payment form HTML
         */
        public function html() {
            echo ST_Stripe_Connect::get_instance()->load_template('stripe-connect-form');
        }

        /**
         * Get gateway name
         */
        public function get_name() {
            return __('Stripe Connect (Partners)', 'vina-stripe-connect');
        }

        /**
         * Get default status
         */
        public function get_default_status() {
            return $this->default_status;
        }

        /**
         * Check if gateway is available
         */
        public function is_available($item_id = false) {
            if (st()->get_option('pm_gway_stripe_connect_enable') !== 'on') {
                return false;
            }

            $secret_key = $this->get_stripe_secret_key();

            if (empty($secret_key)) {
                return false;
            }

            // Check if activity has a connected partner
            if ($item_id) {
                require_once ST_STRIPE_CONNECT_PLUGIN_PATH . 'inc/stripe-connect-accounts.php';
                $accounts_manager = ST_Stripe_Connect_Accounts::get_instance();

                if (!$accounts_manager->get_account_for_post($item_id)) {
                    return false;
                }

                $meta = get_post_meta($item_id, 'is_meta_payment_gateway_stripe_connect', true);
                if ($meta == 'off') {
                    return false;
                }
            }

            return true;
        }

        /**
         * Get gateway ID
         */
        public function getGatewayId() {
            return $this->_gateway_id;
        }

        /**
         * Is check complete required
         */
        public function is_check_complete_required() {
            return true;
        }

        /**
         * Get gateway logo
         */
        public function get_logo() {
            return ST_STRIPE_CONNECT_PLUGIN_URL . 'assets/img/stripe-connect-logo.png';
        }

        /**
         * Get singleton instance
         */
        public static function instance() {
            if (!self::$_inst) {
                self::$_inst = new self();
            }
            return self::$_inst;
        }

        /**
         * Add payment gateway to list
         */
        public static function add_payment($payment) {
            $payment['stripe_connect'] = self::instance();
            return $payment;
        }
    }

    // Register payment gateway
    add_filter('st_payment_gateways', ['ST_Stripe_Connect_Payment_Gateway', 'add_payment']);
}
