<?php
/**
 * Stripe Connect Accounts Manager
 *
 * Handles Stripe Connect Express account creation, onboarding, and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class ST_Stripe_Connect_Accounts {

    private static $instance = null;

    /**
     * User meta keys for storing Stripe Connect data
     */
    private const META_ACCOUNT_ID = 'stripe_connect_account_id';
    private const META_ACCOUNT_STATUS = 'stripe_connect_status';
    private const META_CAPABILITIES = 'stripe_connect_capabilities';
    private const META_ONBOARDING_COMPLETE = 'stripe_connect_onboarding_complete';

    /**
     * Get Stripe API key
     */
    private function get_stripe_secret_key() {
        $sandbox_mode = st()->get_option('stripe_connect_enable_sandbox', 'on');

        if ($sandbox_mode === 'on') {
            return st()->get_option('stripe_connect_test_secret_key', '');
        }

        return st()->get_option('stripe_connect_secret_key', '');
    }

    /**
     * Initialize Stripe with API key
     */
    /**
     * Initialize Stripe with API keys
     */
    private function init_stripe() {
        $secret_key = $this->get_stripe_secret_key();

        if (empty($secret_key)) {
            throw new Exception(__('Stripe API key not configured', 'vina-stripe-connect'));
        }

        \Stripe\Stripe::setApiKey($secret_key);
        \Stripe\Stripe::setApiVersion('2023-10-16');
    }

    /**
     * Create or get existing Stripe Connect account for a user
     */
    public function get_or_create_account($user_id) {
        $account_id = get_user_meta($user_id, self::META_ACCOUNT_ID, true);

        // If account exists, return it
        if ($account_id) {
            try {
                $this->init_stripe();
                $account = \Stripe\Account::retrieve($account_id);
                return [
                    'success' => true,
                    'account_id' => $account_id,
                    'account' => $account
                ];
            } catch (\Stripe\Exception\ApiErrorException $e) {
                // Account doesn't exist anymore, create a new one
                delete_user_meta($user_id, self::META_ACCOUNT_ID);
                delete_user_meta($user_id, self::META_ACCOUNT_STATUS);
            }
        }

        // Create new account
        return $this->create_account($user_id);
    }

    /**
     * Create a new Stripe Connect Express account
     */
    public function create_account($user_id) {
        try {
            $this->init_stripe();

            $user = get_userdata($user_id);
            $user_email = $user->user_email;

            // Create Connect Express account
            $account = \Stripe\Account::create([
                'type' => 'express',
                'email' => $user_email,
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
                'business_type' => 'individual',
                'country' => 'FR', // France - adjust as needed
            ]);

            // Save account ID to user meta
            update_user_meta($user_id, self::META_ACCOUNT_ID, $account->id);
            update_user_meta($user_id, self::META_ACCOUNT_STATUS, 'pending');
            update_user_meta($user_id, self::META_ONBOARDING_COMPLETE, false);

            return [
                'success' => true,
                'account_id' => $account->id,
                'account' => $account
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Create an account link for onboarding
     */
    public function create_account_link($user_id) {
        try {
            // Get or create account
            $account_result = $this->get_or_create_account($user_id);

            if (!$account_result['success']) {
                return $account_result;
            }

            $account_id = $account_result['account_id'];

            $this->init_stripe();

            // Create account link
            $account_link = \Stripe\AccountLink::create([
                'account' => $account_id,
                'refresh_url' => add_query_arg([
                    'sc' => 'setting',
                    'stripe_connect' => 'refresh'
                ], home_url('/mon-compte/')),
                'return_url' => home_url('/stripe-connect-callback/?account=' . $account_id),
                'type' => 'account_onboarding',
            ]);

            return [
                'success' => true,
                'url' => $account_link->url,
                'account_id' => $account_id
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify and save account after callback
     */
    public function verify_and_save_account($user_id, $account_id) {
        try {
            $this->init_stripe();

            // Retrieve account to verify it exists
            $account = \Stripe\Account::retrieve($account_id);

            // Check if charges are enabled
            if ($account->charges_enabled) {
                update_user_meta($user_id, self::META_ACCOUNT_ID, $account_id);
                update_user_meta($user_id, self::META_ACCOUNT_STATUS, 'active');
                update_user_meta($user_id, self::META_ONBOARDING_COMPLETE, true);
                update_user_meta($user_id, self::META_CAPABILITIES, json_encode([
                    'charges_enabled' => $account->charges_enabled,
                    'payouts_enabled' => $account->payouts_enabled,
                    'details_submitted' => $account->details_submitted,
                ]));

                return true;
            } else {
                // Onboarding not complete
                update_user_meta($user_id, self::META_ACCOUNT_STATUS, 'pending');
                update_user_meta($user_id, self::META_ONBOARDING_COMPLETE, false);

                return false;
            }

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return false;
        }
    }

    /**
     * Get user's Stripe Connect account data
     */
    public function get_user_account($user_id) {
        $account_id = get_user_meta($user_id, self::META_ACCOUNT_ID, true);

        if (!$account_id) {
            return [
                'connected' => false,
                'account_id' => null,
                'status' => 'not_connected'
            ];
        }

        try {
            $this->init_stripe();
            $account = \Stripe\Account::retrieve($account_id);

            return [
                'connected' => true,
                'account_id' => $account_id,
                'status' => get_user_meta($user_id, self::META_ACCOUNT_STATUS, true),
                'onboarding_complete' => get_user_meta($user_id, self::META_ONBOARDING_COMPLETE, true),
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
                'details_submitted' => $account->details_submitted,
                'email' => $account->email,
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Account not found or error
            delete_user_meta($user_id, self::META_ACCOUNT_ID);
            delete_user_meta($user_id, self::META_ACCOUNT_STATUS);

            return [
                'connected' => false,
                'account_id' => null,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if user has a valid connected account
     */
    public function has_valid_account($user_id) {
        $account_data = $this->get_user_account($user_id);

        return $account_data['connected'] &&
               $account_data['charges_enabled'] &&
               $account_data['details_submitted'];
    }

    /**
     * Get Stripe account ID for a post author (activity owner)
     */
    public function get_account_for_post($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return null;
        }

        $author_id = $post->post_author;

        if (!$this->has_valid_account($author_id)) {
            return null;
        }

        return get_user_meta($author_id, self::META_ACCOUNT_ID, true);
    }

    /**
     * Create login link for existing account (dashboard access)
     */
    public function create_login_link($user_id) {
        $account_id = get_user_meta($user_id, self::META_ACCOUNT_ID, true);

        if (!$account_id) {
            return [
                'success' => false,
                'message' => __('No connected account found', 'vina-stripe-connect')
            ];
        }

        try {
            $this->init_stripe();

            $login_link = \Stripe\Account::createLoginLink($account_id);

            return [
                'success' => true,
                'url' => $login_link->url
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Disconnect Stripe Connect account
     */
    public function disconnect_account($user_id) {
        try {
            // Delete all Stripe Connect related meta
            delete_user_meta($user_id, self::META_ACCOUNT_ID);
            delete_user_meta($user_id, self::META_ACCOUNT_STATUS);
            delete_user_meta($user_id, self::META_CAPABILITIES);
            delete_user_meta($user_id, self::META_ONBOARDING_COMPLETE);

            return [
                'success' => true,
                'message' => __('Compte Stripe déconnecté avec succès', 'vina-stripe-connect')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
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
