<?php
/**
 * Plugin Name: Traveler Stripe Connect
 * Plugin URI: https://vinyaqui.com/
 * Description: Stripe Connect Express integration for Traveler Theme - Platform with 20% application fee
 * Version: 1.0.0
 * Author: Vinyaqui
 * Author URI: https://vinyaqui.com/
 * License: GPLv2 or later
 * Text Domain: vina-stripe-connect
 */

if (!function_exists('add_action')) {
    echo __('Hi there! I\'m just a plugin, not much I can do when called directly.', 'vina-stripe-connect');
    exit;
}

// Plugin constants
define('ST_STRIPE_CONNECT_VERSION', '1.0.0');
define('ST_STRIPE_CONNECT_MINIMUM_WP_VERSION', '5.0');
define('ST_STRIPE_CONNECT_PLUGIN_PATH', trailingslashit(plugin_dir_path(__FILE__)));
define('ST_STRIPE_CONNECT_PLUGIN_URL', trailingslashit(plugin_dir_url(__FILE__)));

class ST_Stripe_Connect {

    private static $instance = null;

    public function __construct() {
        $theme = wp_get_theme();

        // Check if Traveler theme is active
        if ('Traveler' == $theme->name || 'Traveler' == $theme->parent_theme) {
            add_action('init', [$this, 'plugin_loader'], 20);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

            // AJAX handlers
            add_action('wp_ajax_stripe_connect_create_account_link', [$this, 'ajax_create_account_link']);
            add_action('wp_ajax_stripe_connect_create_login_link', [$this, 'ajax_create_login_link']);
            add_action('wp_ajax_stripe_connect_confirm_payment', [$this, 'ajax_confirm_payment']);
            add_action('wp_ajax_nopriv_stripe_connect_confirm_payment', [$this, 'ajax_confirm_payment']);

            // Rewrite rules for callback
            add_action('init', [$this, 'add_rewrite_rules']);
            add_action('template_redirect', [$this, 'handle_callback']);

            // User account page integration
            add_action('wp', [$this, 'init_user_account_integration']);

            // Activation hook
            register_activation_hook(__FILE__, [$this, 'activate']);
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Add user meta capability for storing Stripe Connect account ID
        // Flush rewrite rules
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }

    /**
     * Add rewrite rules for callback
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^stripe-connect-callback/?$',
            'index.php?stripe_connect_callback=1',
            'top'
        );
        add_rewrite_tag('%stripe_connect_callback%', '([^&]+)');
    }

    /**
     * Handle Stripe Connect callback
     */
    public function handle_callback() {
        if (get_query_var('stripe_connect_callback')) {
            $this->process_stripe_callback();
            exit;
        }
    }

    /**
     * Process Stripe Connect callback
     */
    private function process_stripe_callback() {
        if (!is_user_logged_in()) {
            wp_redirect(home_url('/mon-compte/'));
            exit;
        }

        $user_id = get_current_user_id();

        // Get the account ID from URL
        $account_id = isset($_GET['account']) ? sanitize_text_field($_GET['account']) : '';

        if ($account_id) {
            // Verify the account exists and belongs to this onboarding
            require_once ST_STRIPE_CONNECT_PLUGIN_PATH . 'inc/stripe-connect-accounts.php';
            $accounts_manager = ST_Stripe_Connect_Accounts::get_instance();

            if ($accounts_manager->verify_and_save_account($user_id, $account_id)) {
                wp_redirect(add_query_arg([
                    'sc' => 'setting',
                    'stripe_connect' => 'success'
                ], home_url('/mon-compte/')));
            } else {
                wp_redirect(add_query_arg([
                    'sc' => 'setting',
                    'stripe_connect' => 'error'
                ], home_url('/mon-compte/')));
            }
        } else {
            wp_redirect(home_url('/mon-compte/?sc=setting'));
        }
        exit;
    }

    /**
     * Get Stripe secret key based on mode
     */
    public function get_secret_key() {
        $sandbox_mode = st()->get_option('stripe_connect_enable_sandbox', 'on');

        if ($sandbox_mode === 'on') {
            return st()->get_option('stripe_connect_test_secret_key', '');
        } else {
            return st()->get_option('stripe_connect_secret_key', '');
        }
    }

    /**
     * Get Stripe publishable key based on mode
     */
    public function get_publishable_key() {
        $sandbox_mode = st()->get_option('stripe_connect_enable_sandbox', 'on');

        if ($sandbox_mode === 'on') {
            return st()->get_option('stripe_connect_test_publish_key', '');
        } else {
            return st()->get_option('stripe_connect_publish_key', '');
        }
    }

    /**
     * AJAX: Create Stripe Connect account link
     */
    public function ajax_create_account_link() {
        // Debug log
        error_log('Stripe Connect: AJAX create_account_link called');

        check_ajax_referer('stripe_connect_nonce', 'nonce');

        if (!is_user_logged_in()) {
            error_log('Stripe Connect: User not logged in');
            wp_send_json_error(['message' => __('You must be logged in.', 'vina-stripe-connect')]);
        }

        error_log('Stripe Connect: User ID = ' . get_current_user_id());

        // Ensure Stripe library is loaded
        $this->plugin_loader();

        require_once ST_STRIPE_CONNECT_PLUGIN_PATH . 'inc/stripe-connect-accounts.php';
        $accounts_manager = ST_Stripe_Connect_Accounts::get_instance();

        $result = $accounts_manager->create_account_link(get_current_user_id());

        error_log('Stripe Connect: Result = ' . print_r($result, true));

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Create login link for dashboard access
     */
    public function ajax_create_login_link() {
        check_ajax_referer('stripe_connect_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in.', 'vina-stripe-connect')]);
        }

        // Ensure Stripe library is loaded
        $this->plugin_loader();

        require_once ST_STRIPE_CONNECT_PLUGIN_PATH . 'inc/stripe-connect-accounts.php';
        $accounts_manager = ST_Stripe_Connect_Accounts::get_instance();

        $result = $accounts_manager->create_login_link(get_current_user_id());

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Confirm payment (for 3DS)
     */
    public function ajax_confirm_payment() {
        check_ajax_referer('_wpnonce_security', '_s');

        $payment_intent_id = isset($_POST['payment_intent_id']) ? sanitize_text_field($_POST['payment_intent_id']) : '';
        $order_id = isset($_POST['st_order_id']) ? intval($_POST['st_order_id']) : 0;

        if (!$payment_intent_id || !$order_id) {
            wp_send_json_error(['message' => __('Invalid request', 'vina-stripe-connect')]);
        }

        // Ensure Stripe library is loaded
        $this->plugin_loader();

        require_once ST_STRIPE_CONNECT_PLUGIN_PATH . 'inc/stripe-connect-gateway.php';
        $gateway = ST_Stripe_Connect_Payment_Gateway::instance();

        $result = $gateway->confirm_payment_intent($payment_intent_id, $order_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Initialize user account page integration
     */
    public function init_user_account_integration() {
        if (is_user_logged_in() && is_page() && isset($_GET['sc']) && $_GET['sc'] === 'setting') {
            add_action('wp_footer', [$this, 'render_stripe_connect_section'], 5);
        }
    }

    /**
     * Render Stripe Connect section in user settings
     * Injects via JavaScript to add after password change section
     */
    public function render_stripe_connect_section() {
        $user = wp_get_current_user();

        // Only show for partners, authors and administrators
        if (!in_array('partner', $user->roles) && !in_array('author', $user->roles) && !in_array('administrator', $user->roles)) {
            return;
        }

        // Check if Stripe Connect is enabled
        if (st()->get_option('pm_gway_stripe_connect_enable') !== 'on') {
            return;
        }

        // Force enqueue scripts on this page
        wp_enqueue_style('stripe-connect-css', ST_STRIPE_CONNECT_PLUGIN_URL . 'assets/css/stripe-connect.css', [], ST_STRIPE_CONNECT_VERSION);

        require_once ST_STRIPE_CONNECT_PLUGIN_PATH . 'inc/stripe-connect-accounts.php';
        $accounts_manager = ST_Stripe_Connect_Accounts::get_instance();

        $account_data = $accounts_manager->get_user_account(get_current_user_id());

        // Define stripeConnectParams before including template
        ?>
        <script type="text/javascript">
        var stripeConnectParams = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('stripe_connect_nonce'); ?>',
            security: '<?php echo wp_create_nonce('_wpnonce_security'); ?>',
            home_url: '<?php echo home_url('/'); ?>'
        };
        </script>
        <?php

        // Start output buffer
        ob_start();
        include ST_STRIPE_CONNECT_PLUGIN_PATH . 'views/account-settings.php';
        $html = ob_get_clean();

        // Inject via JavaScript after the last .infor-st-setting
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var stripeConnectHtml = <?php echo wp_json_encode($html, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

            // Find the last .infor-st-setting div and append after it
            $('.infor-st-setting').last().after(stripeConnectHtml);

            // Event handlers for the injected content
            // Connect button - using event delegation for dynamically added elements
            $(document).on('click', '.stripe-connect-btn', function(e) {
                e.preventDefault();

                console.log('Stripe Connect: Button clicked');
                console.log('stripeConnectParams:', stripeConnectParams);

                var button = $(this);
                var originalText = button.html();
                var userId = button.data('user-id');

                console.log('User ID:', userId);

                button.prop('disabled', true).html('<?php _e('Connexion en cours...', 'vina-stripe-connect'); ?>');

                var requestData = {
                    action: 'stripe_connect_create_account_link',
                    nonce: stripeConnectParams.nonce,
                    user_id: userId
                };

                console.log('Request data:', requestData);

                $.ajax({
                    url: stripeConnectParams.ajax_url,
                    type: 'POST',
                    data: requestData,
                    success: function(response) {
                        console.log('AJAX Success:', response);

                        if (response.success && response.data && response.data.url) {
                            console.log('Redirecting to:', response.data.url);
                            window.location.href = response.data.url;
                        } else {
                            console.error('Error in response:', response);
                            var errorMsg = (response.data && response.data.message) || '<?php _e('Erreur lors de la création du lien', 'vina-stripe-connect'); ?>';
                            alert(errorMsg);
                            button.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                        console.error('Response:', xhr.responseText);
                        alert('<?php _e('Erreur de connexion au serveur', 'vina-stripe-connect'); ?>\n\nDétails: ' + error);
                        button.prop('disabled', false).html(originalText);
                    }
                });
            });

            // Refresh button - using event delegation
            $(document).on('click', '.stripe-connect-refresh-btn', function(e) {
                e.preventDefault();
                $('.stripe-connect-btn').trigger('click');
            });

            // Dashboard button - using event delegation
            $(document).on('click', '.stripe-connect-dashboard-btn', function(e) {
                e.preventDefault();

                var button = $(this);
                button.prop('disabled', true).html('<?php _e('Chargement...', 'vina-stripe-connect'); ?>');

                $.ajax({
                    url: stripeConnectParams.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'stripe_connect_create_login_link',
                        nonce: stripeConnectParams.nonce,
                        user_id: button.data('user-id')
                    },
                    success: function(response) {
                        if (response.success && response.data.url) {
                            window.open(response.data.url, '_blank');
                            button.prop('disabled', false).html('<?php _e('Accéder à mon tableau de bord Stripe', 'vina-stripe-connect'); ?>');
                        } else {
                            alert(response.data.message || '<?php _e('Erreur', 'vina-stripe-connect'); ?>');
                            button.prop('disabled', false).html('<?php _e('Accéder à mon tableau de bord Stripe', 'vina-stripe-connect'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('Erreur de connexion', 'vina-stripe-connect'); ?>');
                        button.prop('disabled', false).html('<?php _e('Accéder à mon tableau de bord Stripe', 'vina-stripe-connect'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        if (st()->get_option('pm_gway_stripe_connect_enable') !== 'on') {
            return;
        }

        // Stripe.js library
        wp_register_script(
            'stripe-connect-js-lib',
            'https://js.stripe.com/v3/',
            [],
            null,
            true
        );

        // Plugin scripts
        wp_register_script(
            'stripe-connect-js',
            ST_STRIPE_CONNECT_PLUGIN_URL . 'assets/js/stripe-connect.js',
            ['jquery', 'stripe-connect-js-lib'],
            ST_STRIPE_CONNECT_VERSION,
            true
        );

        // Plugin styles
        wp_register_style(
            'stripe-connect-css',
            ST_STRIPE_CONNECT_PLUGIN_URL . 'assets/css/stripe-connect.css',
            [],
            ST_STRIPE_CONNECT_VERSION
        );

        // Localize script
        wp_localize_script('stripe-connect-js', 'stripeConnectParams', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'publishable_key' => $this->get_publishable_key(),
            'nonce' => wp_create_nonce('stripe_connect_nonce'),
            'security' => wp_create_nonce('_wpnonce_security'),
            'home_url' => home_url('/'),
        ]);

        wp_enqueue_script('stripe-connect-js-lib');
        wp_enqueue_script('stripe-connect-js');
        wp_enqueue_style('stripe-connect-css');
    }

    /**
     * Load plugin classes
     */
    public function plugin_loader() {
        // Force load vina-stripe's Stripe library (newer version)
        // We need to load the ENTIRE library from vina-stripe, not mix with traveler-code's old version
        if (file_exists(WP_PLUGIN_DIR . '/vina-stripe/vendor/stripe/stripe-php/init.php')) {
            // Use the init.php file which properly loads all Stripe classes
            require_once WP_PLUGIN_DIR . '/vina-stripe/vendor/stripe/stripe-php/init.php';
            error_log('Stripe Connect: Loaded Stripe via init.php from vina-stripe');
        } elseif (file_exists(WP_PLUGIN_DIR . '/vina-stripe/vendor/autoload.php')) {
            require_once WP_PLUGIN_DIR . '/vina-stripe/vendor/autoload.php';
            error_log('Stripe Connect: Loaded Stripe via autoload.php from vina-stripe');
        } elseif (file_exists(ST_STRIPE_CONNECT_PLUGIN_PATH . 'vendor/autoload.php')) {
            require_once ST_STRIPE_CONNECT_PLUGIN_PATH . 'vendor/autoload.php';
            error_log('Stripe Connect: Loaded Stripe via autoload.php from vina-stripe-connect');
        } else {
            error_log('Stripe Connect: ERROR - No Stripe library found!');
        }

        // Load plugin classes
        require_once ST_STRIPE_CONNECT_PLUGIN_PATH . 'inc/stripe-connect-accounts.php';
        require_once ST_STRIPE_CONNECT_PLUGIN_PATH . 'inc/stripe-connect-gateway.php';
    }

    /**
     * Load template
     */
    public function load_template($name, $data = null) {
        if (is_array($data)) {
            extract($data);
        }

        $template = ST_STRIPE_CONNECT_PLUGIN_PATH . 'views/' . $name . '.php';

        if (is_file($template)) {
            $custom_template = locate_template('vina-stripe-connect/views/' . $name . '.php');
            if (is_file($custom_template)) {
                $template = $custom_template;
            }

            ob_start();
            require $template;
            return ob_get_clean();
        }

        return '';
    }

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

// Initialize plugin
ST_Stripe_Connect::get_instance();
