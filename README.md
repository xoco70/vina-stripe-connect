# Traveler Stripe Connect

Plugin WordPress pour intégrer Stripe Connect Express avec le thème Traveler.

## Description

Ce plugin permet aux partenaires (vignerons) de recevoir des paiements directement sur leur compte Stripe Connect tout en conservant une commission de 20% pour la plateforme.

## Fonctionnalités

### 🔗 Connexion Stripe Connect
- Onboarding simplifié via Account Links
- Interface dans l'espace utilisateur `/mon-compte/?sc=setting`
- Accès direct au tableau de bord Stripe

### 💰 Paiements avec Application Fee
- **Type**: Separate Charge and Transfer
- **Commission plateforme**: 20% (fixe)
- **Reversement partenaire**: 80%
- La plateforme paie les frais Stripe

### 🔐 Sécurité
- Support 3D Secure (Strong Customer Authentication)
- Paiements conformes PCI DSS via Stripe
- Mode sandbox pour les tests

### 🎯 Intégration Traveler
- Compatible avec le système de booking Traveler
- Gestion automatique des disponibilités
- Emails de confirmation
- Support des activités (st_activity)

## Installation

1. Télécharger le plugin dans `/wp-content/plugins/vina-stripe-connect/`
2. Activer le plugin dans WordPress
3. Configurer les clés API Stripe dans **Traveler > Theme Options > Payment Options**

## Configuration

### 1. Dans Stripe Dashboard

1. Créer un compte Stripe Platform : https://dashboard.stripe.com/
2. Activer Stripe Connect dans les paramètres
3. Récupérer les clés API (test et live)

### 2. Dans WordPress

Aller dans **Traveler > Theme Options > Payment Options** :

- **Enable Stripe Connect**: ON
- **Test Secret Key**: `sk_test_...`
- **Test Publishable Key**: `pk_test_...`
- **Enable Sandbox Mode**: ON (pour les tests)

### 3. Pour les partenaires

1. Se connecter à leur compte
2. Aller sur `/mon-compte/?sc=setting`
3. Cliquer sur "Connecter mon compte Stripe"
4. Compléter l'onboarding Stripe
5. Retour automatique sur le site

## Utilisation

### Pour les clients

1. Choisir une activité
2. Remplir les informations de réservation
3. Sélectionner "Stripe Connect" comme moyen de paiement
4. Entrer les informations de carte
5. Valider le paiement

### Pour les partenaires

Une fois le compte connecté :
- Recevoir 80% de chaque paiement automatiquement
- Accéder au tableau de bord Stripe pour voir les revenus
- Gérer les virements bancaires dans Stripe

## Structure des paiements

```
Client paie 100€
├── 80€ → Compte Stripe du partenaire (direct)
└── 20€ → Compte platform (application fee)
    └── Includes frais Stripe (~2.9% + 0.25€)
```

## Hooks & Filters

### Actions

```php
// Avant mise à jour du statut de commande
do_action('stripe_connect_before_update_status', $intent_status, $order_id);
```

### Filters

```php
// Modifier le statut de completion
apply_filters('stripe_connect_complete_purchase', false);
```

## Dépendances

- **WordPress**: 5.0+
- **Traveler Theme**: Version compatible
- **PHP**: 7.4+
- **Stripe PHP SDK**: Inclus (ou utilise vina-stripe si disponible)

## Structure des fichiers

```
vina-stripe-connect/
├── vina-stripe-connect.php         # Fichier principal
├── inc/
│   ├── stripe-connect-accounts.php  # Gestion comptes Connect
│   └── stripe-connect-gateway.php   # Gateway de paiement
├── views/
│   ├── stripe-connect-form.php      # Formulaire paiement
│   └── account-settings.php         # Interface utilisateur
├── assets/
│   ├── js/
│   │   └── stripe-connect.js        # JavaScript frontend
│   ├── css/
│   │   └── stripe-connect.css       # Styles
│   └── img/
│       └── stripe-connect-logo.svg  # Logo
└── README.md
```

## User Meta utilisées

- `stripe_connect_account_id` - ID du compte Stripe Connect
- `stripe_connect_status` - Statut (pending/active)
- `stripe_connect_capabilities` - Capabilities JSON
- `stripe_connect_onboarding_complete` - Boolean

## Dépannage

### Le paiement ne fonctionne pas

1. Vérifier que les clés API sont correctes
2. Vérifier que le partenaire a un compte connecté
3. Vérifier les logs dans **Stripe Dashboard > Logs**

### Le compte ne se connecte pas

1. Vérifier que l'utilisateur a le rôle "author"
2. Vérifier que les redirect URLs sont correctes
3. Essayer de rafraîchir le lien d'onboarding

### Erreur 3DS

1. Vérifier que JavaScript est activé
2. Tester avec une carte 3DS de test Stripe
3. Vérifier les logs navigateur (console)

## Cartes de test Stripe

### Succès immédiat
- **4242 4242 4242 4242** - Succès
- Date: Future
- CVC: N'importe quel 3 chiffres

### Avec 3DS
- **4000 0027 6000 3184** - Requiert authentification 3DS

### Échec
- **4000 0000 0000 0002** - Carte déclinée

## Support

- Documentation Stripe Connect: https://stripe.com/docs/connect
- Support Traveler: https://travelerwp.com/

## Changelog

### Version 1.0.0 (2026-02-06)
- Release initiale
- Support Stripe Connect Express
- Application fee 20%
- Separate Charge and Transfer
- Support 3D Secure
- Interface utilisateur complète

## Licence

GPLv2 or later

## Auteur

Vinyaqui - https://vinyaqui.com/
