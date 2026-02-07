# 🚀 Guide d'installation rapide - Stripe Connect

## Étape 1 : Activer le plugin

1. Dans WordPress admin, aller dans **Extensions > Extensions installées**
2. Trouver **"Traveler Stripe Connect"**
3. Cliquer sur **"Activer"**

## Étape 2 : Configurer les clés API Stripe

1. Aller dans **Traveler > Theme Options**
2. Cliquer sur l'onglet **"Payment Options"**
3. Trouver la section **"Stripe Connect"**
4. Configurer les paramètres :

```
✓ Enable Stripe Connect: ON

Test Mode (pour les tests) :
✓ Enable Sandbox Mode: ON
✓ Test Publishable Key: pk_test_...
✓ Test Secret Key: sk_test_...
```

5. Cliquer sur **"Save Changes"**

## Étape 3 : Tester avec un compte partenaire

### 3.1 Créer un utilisateur de test (Vigneron)

1. Aller dans **Users > Add New**
2. Créer un utilisateur avec le rôle **"Author"**
   - Username: `vigneron-test`
   - Email: `vigneron@test.com`
   - Role: **Author**

### 3.2 Connecter le compte Stripe

1. Se connecter en tant que le vigneron de test
2. Aller sur `/mon-compte/?sc=setting`
3. Trouver la section **"Compte Stripe Connect"**
4. Cliquer sur **"Connecter mon compte Stripe"**
5. Vous serez redirigé vers Stripe
6. Compléter l'onboarding avec des données de test :
   - **Pays** : France
   - **Type de business** : Individual
   - **Informations personnelles** : Données fictives
   - **IBAN** : `FR1420041010050500013M02606` (IBAN de test)
7. Cliquer sur **"Submit"**
8. Vous serez redirigé vers le site avec confirmation

✅ **Compte connecté avec succès !**

## Étape 4 : Créer une activité de test

1. En tant que vigneron, aller dans **Activities > Add New**
2. Créer une activité :
   - **Title** : "Visite et dégustation"
   - **Price** : 50€
   - **Location** : Bordeaux
   - **Date** : Date future
3. **Important** : Dans **Payment Options**, cocher **"Stripe Connect"**
4. Publier l'activité

## Étape 5 : Tester un paiement

### 5.1 En tant que client

1. Se déconnecter
2. Créer un nouveau compte ou se connecter comme client
3. Aller sur l'activité créée
4. Cliquer sur **"Book Now"**
5. Remplir les informations de réservation
6. Choisir **"Stripe Connect"** comme moyen de paiement

### 5.2 Carte de test

Utiliser cette carte de test :
```
Numéro : 4242 4242 4242 4242
Date : 12/34 (n'importe quelle date future)
CVC : 123
Code postal : 12345
```

### 5.3 Valider le paiement

1. Cliquer sur **"Confirm Booking"**
2. ✅ Le paiement est traité
3. ✅ 80€ vont au vigneron
4. ✅ 20€ restent sur la plateforme

## Étape 6 : Vérifier dans Stripe Dashboard

### Pour le compte plateforme

1. Se connecter sur https://dashboard.stripe.com/test
2. Aller dans **Payments**
3. Vous verrez le paiement avec :
   - Montant total : 50€
   - Application fee : 10€ (20%)
   - Transfer : 40€ (80%)

### Pour le compte partenaire

1. En tant que vigneron sur le site
2. Aller dans `/mon-compte/?sc=setting`
3. Cliquer sur **"Accéder à mon tableau de bord Stripe"**
4. Vous verrez le paiement de 40€ reçu

## 🎯 Résumé du flux

```
Client → Réserve activité (50€)
    ↓
Platform Stripe → Reçoit 50€
    ↓
    ├─→ 40€ (80%) → Transfert automatique vers compte vigneron
    └─→ 10€ (20%) → Reste sur plateforme (application fee)
```

## ⚠️ Notes importantes

### Mode Test vs Live

- **Test Mode** : Utilisez les clés `pk_test_...` et `sk_test_...`
- **Live Mode** : Utilisez les vraies clés `pk_live_...` et `sk_live_...`

### Passage en production

Quand vous êtes prêt pour la production :

1. Dans Stripe Dashboard :
   - Activer votre compte pour accepter les paiements réels
   - Compléter les informations business
   - Ajouter les informations bancaires

2. Dans WordPress :
   - Mettre **"Enable Sandbox Mode"** sur **OFF**
   - Remplacer par les clés **Live** :
     - `pk_live_...`
     - `sk_live_...`

3. Demander aux partenaires de :
   - Se reconnecter avec un compte Stripe réel
   - Compléter leur onboarding avec vraies informations
   - Fournir un vrai IBAN

## 🐛 Dépannage rapide

### Le bouton "Connecter Stripe" ne fonctionne pas
→ Vérifier que les clés API sont correctement configurées

### Le paiement échoue
→ Vérifier que le partenaire a bien complété son onboarding Stripe

### Erreur "Partner has not connected"
→ Le vigneron doit connecter son compte Stripe avant de recevoir des paiements

### Les 20% ne sont pas prélevés
→ Vérifier le code dans `stripe-connect-gateway.php` ligne avec `APPLICATION_FEE_PERCENT`

## 📞 Support

- Documentation complète : [README.md](README.md)
- Stripe Connect Docs : https://stripe.com/docs/connect
- Stripe Test Cards : https://stripe.com/docs/testing

## ✅ Checklist de mise en production

- [ ] Compte Stripe Platform activé
- [ ] Clés Live configurées
- [ ] Sandbox Mode désactivé
- [ ] Tous les partenaires ont connecté leur compte
- [ ] Test d'un paiement réel effectué
- [ ] Emails de confirmation fonctionnels
- [ ] Dashboard Stripe vérifié
