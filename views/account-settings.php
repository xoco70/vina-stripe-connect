<?php
/**
 * Stripe Connect Account Settings
 * Displayed in user account settings page
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
?>

<div class="st-stripe-connect-settings" style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 30px;">
    <h3 style="margin-top: 0; color: #333; border-bottom: 2px solid #635bff; padding-bottom: 15px;">
        <span style="display: inline-block; vertical-align: middle;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" style="vertical-align: middle; margin-right: 10px;">
                <path d="M13.5 12C13.5 11.1716 12.8284 10.5 12 10.5C11.1716 10.5 10.5 11.1716 10.5 12C10.5 12.8284 11.1716 13.5 12 13.5C12.8284 13.5 13.5 12.8284 13.5 12Z" fill="#635bff"/>
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2ZM4 12C4 7.58172 7.58172 4 12 4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20C7.58172 20 4 16.4183 4 12Z" fill="#635bff"/>
            </svg>
        </span>
        <?php _e('Compte Stripe Connect', 'vina-stripe-connect'); ?>
    </h3>

    <?php
    // Show success/error messages
    if (isset($_GET['stripe_connect'])) {
        $status = sanitize_text_field($_GET['stripe_connect']);

        if ($status === 'success') {
            echo '<div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
            echo '<strong>' . __('Succès !', 'vina-stripe-connect') . '</strong> ';
            echo __('Votre compte Stripe a été connecté avec succès. Vous pouvez maintenant recevoir des paiements.', 'vina-stripe-connect');
            echo '</div>';
        } elseif ($status === 'error') {
            echo '<div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
            echo '<strong>' . __('Erreur', 'vina-stripe-connect') . '</strong> ';
            echo __('Une erreur est survenue lors de la connexion de votre compte Stripe. Veuillez réessayer.', 'vina-stripe-connect');
            echo '</div>';
        } elseif ($status === 'refresh') {
            echo '<div class="alert alert-warning" style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
            echo __('Veuillez compléter la configuration de votre compte Stripe.', 'vina-stripe-connect');
            echo '</div>';
        }
    }
    ?>

    <?php if ($account_data['connected'] && $account_data['charges_enabled']): ?>
        <!-- Account connected and active -->
        <div class="stripe-connect-status-active" style="border-left: 4px solid #00d924; padding-left: 20px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" style="margin-right: 15px;">
                    <circle cx="24" cy="24" r="24" fill="#00d924" opacity="0.1"/>
                    <path d="M20 24L22 26L28 20" stroke="#00d924" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <div>
                    <h4 style="margin: 0; color: #00d924; font-size: 18px;">
                        <?php _e('Compte connecté et actif', 'vina-stripe-connect'); ?>
                    </h4>
                    <p style="margin: 5px 0 0 0; color: #666;">
                        <?php _e('Vous pouvez recevoir des paiements pour vos activités', 'vina-stripe-connect'); ?>
                    </p>
                </div>
            </div>

            <div class="stripe-connect-account-info" style="background: #f7f9fc; padding: 15px; border-radius: 5px;">
                <p style="margin: 0 0 10px 0;">
                    <strong><?php _e('ID du compte :', 'vina-stripe-connect'); ?></strong>
                    <code style="background: #e3e8ef; padding: 3px 8px; border-radius: 3px; font-family: monospace;">
                        <?php echo esc_html($account_data['account_id']); ?>
                    </code>
                </p>
                <?php if (!empty($account_data['email'])): ?>
                <p style="margin: 0;">
                    <strong><?php _e('Email :', 'vina-stripe-connect'); ?></strong>
                    <?php echo esc_html($account_data['email']); ?>
                </p>
                <?php endif; ?>
            </div>

            <div class="stripe-connect-info-box" style="background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <p style="margin: 0; color: #0066cc; font-size: 14px;">
                    <strong><?php _e('Comment fonctionnent les paiements ?', 'vina-stripe-connect'); ?></strong>
                </p>
                <ul style="margin: 10px 0 0 0; padding-left: 20px; color: #666; font-size: 14px;">
                    <li><?php _e('Lorsqu\'un client réserve votre activité, le paiement est traité par la plateforme', 'vina-stripe-connect'); ?></li>
                    <li><?php _e('Vous recevez automatiquement <strong>80%</strong> du montant total directement sur votre compte Stripe', 'vina-stripe-connect'); ?></li>
                    <li><?php _e('La plateforme conserve <strong>20%</strong> comme frais de service (incluant les frais Stripe)', 'vina-stripe-connect'); ?></li>
                    <li><?php _e('Les fonds sont disponibles selon les délais de paiement Stripe (généralement 2-7 jours)', 'vina-stripe-connect'); ?></li>
                </ul>
            </div>

            <div class="stripe-connect-actions" style="display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap;">
                <button
                    type="button"
                    class="btn btn-primary stripe-connect-dashboard-btn"
                    data-user-id="<?php echo esc_attr($user_id); ?>"
                    style="background: #635bff; color: white; border: none; padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600;">
                    <?php _e('Accéder à mon tableau de bord Stripe', 'vina-stripe-connect'); ?>
                </button>

                <button
                    type="button"
                    class="btn btn-secondary stripe-connect-refresh-btn"
                    data-user-id="<?php echo esc_attr($user_id); ?>"
                    style="background: #f6f9fc; color: #425466; border: 1px solid #c8d7e3; padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600;">
                    <?php _e('Mettre à jour les informations', 'vina-stripe-connect'); ?>
                </button>

                <button
                    type="button"
                    class="btn btn-danger stripe-connect-disconnect-btn"
                    data-user-id="<?php echo esc_attr($user_id); ?>"
                    style="background: #dc3545; color: white; border: none; padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600;">
                    <?php _e('Déconnecter Stripe', 'vina-stripe-connect'); ?>
                </button>
            </div>
        </div>

    <?php elseif ($account_data['connected'] && !$account_data['charges_enabled']): ?>
        <!-- Account exists but onboarding incomplete -->
        <div class="stripe-connect-status-pending" style="border-left: 4px solid #ff9800; padding-left: 20px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" style="margin-right: 15px;">
                    <circle cx="24" cy="24" r="24" fill="#ff9800" opacity="0.1"/>
                    <path d="M24 16V26M24 30V32" stroke="#ff9800" stroke-width="3" stroke-linecap="round"/>
                </svg>
                <div>
                    <h4 style="margin: 0; color: #ff9800; font-size: 18px;">
                        <?php _e('Configuration incomplète', 'vina-stripe-connect'); ?>
                    </h4>
                    <p style="margin: 5px 0 0 0; color: #666;">
                        <?php _e('Veuillez compléter la configuration de votre compte pour recevoir des paiements', 'vina-stripe-connect'); ?>
                    </p>
                </div>
            </div>

            <button
                type="button"
                class="btn btn-warning stripe-connect-btn"
                data-user-id="<?php echo esc_attr($user_id); ?>"
                style="background: #ff9800; color: white; border: none; padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; width: 100%;">
                <?php _e('Compléter la configuration', 'vina-stripe-connect'); ?>
            </button>
        </div>

    <?php else: ?>
        <!-- No account connected -->
        <div class="stripe-connect-status-not-connected" style="text-align: center; padding: 30px 0;">
            <svg width="80" height="80" viewBox="0 0 80 80" fill="none" style="margin-bottom: 20px;">
                <rect width="80" height="80" rx="40" fill="#f0f8ff"/>
                <path d="M40 25V40M40 45V47" stroke="#635bff" stroke-width="4" stroke-linecap="round"/>
                <circle cx="40" cy="40" r="20" stroke="#635bff" stroke-width="4"/>
            </svg>

            <h4 style="margin: 0 0 10px 0; color: #333; font-size: 20px;">
                <?php _e('Connectez votre compte Stripe', 'vina-stripe-connect'); ?>
            </h4>

            <p style="color: #666; font-size: 16px; margin: 0 0 20px 0; max-width: 600px; margin-left: auto; margin-right: auto;">
                <?php _e('Pour recevoir des paiements pour vos activités, vous devez connecter un compte Stripe. Le processus est simple et sécurisé.', 'vina-stripe-connect'); ?>
            </p>

            <div class="stripe-connect-benefits" style="background: #f7f9fc; padding: 20px; border-radius: 8px; margin: 30px 0; text-align: left;">
                <h5 style="margin: 0 0 15px 0; color: #333;">
                    <?php _e('Avantages :', 'vina-stripe-connect'); ?>
                </h5>
                <ul style="margin: 0; padding-left: 25px; color: #666;">
                    <li style="margin-bottom: 10px;">
                        <?php _e('Recevez <strong>80%</strong> de chaque réservation directement sur votre compte', 'vina-stripe-connect'); ?>
                    </li>
                    <li style="margin-bottom: 10px;">
                        <?php _e('Paiements sécurisés et conformes aux normes internationales', 'vina-stripe-connect'); ?>
                    </li>
                    <li style="margin-bottom: 10px;">
                        <?php _e('Tableau de bord Stripe pour suivre vos revenus', 'vina-stripe-connect'); ?>
                    </li>
                    <li>
                        <?php _e('Support de toutes les principales cartes bancaires', 'vina-stripe-connect'); ?>
                    </li>
                </ul>
            </div>

            <button
                type="button"
                class="btn btn-primary stripe-connect-btn"
                data-user-id="<?php echo esc_attr($user_id); ?>"
                style="background: #635bff; color: white; border: none; padding: 15px 40px; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: 600; transition: background 0.2s;">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="white" style="vertical-align: middle; margin-right: 8px;">
                    <path d="M10 0C4.477 0 0 4.477 0 10s4.477 10 10 10 10-4.477 10-10S15.523 0 10 0zm1 15H9v-2h2v2zm0-3H9V5h2v7z"/>
                </svg>
                <?php _e('Connecter mon compte Stripe', 'vina-stripe-connect'); ?>
            </button>

            <p style="color: #999; font-size: 13px; margin-top: 15px;">
                <?php _e('Vous serez redirigé vers une page sécurisée Stripe', 'vina-stripe-connect'); ?>
            </p>
        </div>
    <?php endif; ?>
</div>
