# Gestion des Transactions (Transaction Management)

## Vue d'ensemble
Le système de gestion des transactions permet d'enregistrer, suivre et gérer toutes les opérations financières effectuées sur les comptes clients. Il prend en charge différents types de transactions (dépôt, retrait, transfert) avec validation des soldes, historique complet et auditabilité.

## Structure de la base de données

### Table `transactions`
```sql
CREATE TABLE transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('depot', 'retrait', 'transfert') NOT NULL,
    montant DECIMAL(15, 2) NOT NULL,
    date TIMESTAMP NOT NULL,
    statut ENUM('en_cours', 'valide', 'annule') NOT NULL DEFAULT 'en_cours',
    compte_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (compte_id) REFERENCES comptes(id) ON DELETE CASCADE
);
```

### Champs importants
- **type**: Type de transaction (depot/retrait/transfert)
- **montant**: Montant de la transaction
- **date**: Date et heure de la transaction
- **statut**: État de la transaction (en_cours/valide/annule)
- **compte_id**: Référence vers le compte concerné

## Modèle Transaction

### Relations et configuration
```php
class Transaction extends Model
{
    protected $fillable = [
        'type', 'montant', 'date', 'statut', 'compte_id'
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date' => 'datetime',
    ];

    // Relation avec le compte
    public function compte()
    {
        return $this->belongsTo(Compte::class);
    }

    // Scope pour filtrer par statut
    public function scopeValide($query)
    {
        return $query->where('statut', 'valide');
    }

    public function scopeEnCours($query)
    {
        return $query->where('statut', 'en_cours');
    }

    // Scope pour filtrer par type
    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Scope pour filtrer par période
    public function scopePeriode($query, $debut, $fin)
    {
        return $query->whereBetween('date', [$debut, $fin]);
    }
}
```

## Types de transactions

### 1. Dépôt (depot)
- Ajout de fonds sur un compte
- Crédite le solde du compte
- Toujours positif pour le compte destinataire

### 2. Retrait (retrait)
- Soustraction de fonds d'un compte
- Débite le solde du compte
- Vérification du solde disponible

### 3. Transfert (transfert)
- Transfert de fonds entre deux comptes
- Débite le compte source, crédite le compte destinataire
- Validation des soldes et statuts des comptes

## Statuts des transactions

### États possibles
- **en_cours**: Transaction initiée, en attente de validation
- **valide**: Transaction confirmée et exécutée
- **annule**: Transaction annulée (erreur ou rejet)

### Transitions
- en_cours → valide (succès)
- en_cours → annule (échec/annulation)

## Règles métier

### Validation des montants
- **Montant minimum** : 100 FCFA
- **Montant maximum** : 10,000,000 FCFA (par transaction)
- **Décimales** : maximum 2 chiffres après la virgule

### Contrôles de solde
- **Retraits** : solde disponible ≥ montant + frais
- **Transferts** : solde source ≥ montant + frais
- **Vérification temps réel** : avant validation de la transaction

### Frais de transaction
- **Dépôts** : gratuits
- **Retraits** : 500 FCFA (minimum)
- **Transferts** : 1,000 FCFA (minimum)
- **TVA** : 18% sur les frais

## API Endpoints

### Lister les transactions
```
GET /api/transactions?page=1&limit=10&compte_id=123&type=depot&statut=valide&debut=2025-01-01&fin=2025-12-31
```

**Paramètres de requête :**
- `page`: numéro de page (défaut: 1)
- `limit`: éléments par page (max: 100)
- `compte_id`: filtrer par compte
- `type`: filtrer par type (depot/retrait/transfert)
- `statut`: filtrer par statut (en_cours/valide/annule)
- `debut`: date de début (YYYY-MM-DD)
- `fin`: date de fin (YYYY-MM-DD)

### Créer une transaction
```
POST /api/transactions
Content-Type: application/json

{
    "type": "depot",
    "montant": 50000,
    "compte_id": 123
}
```

### Consulter une transaction
```
GET /api/transactions/{transactionId}
```

### Mettre à jour une transaction
```
PUT /api/transactions/{transactionId}
Content-Type: application/json

{
    "statut": "valide"
}
```

### Supprimer une transaction
```
DELETE /api/transactions/{transactionId}
```

## Logique de traitement

### Processus de création
1. **Validation des données** : type, montant, compte_id
2. **Vérification du compte** : existence, statut actif
3. **Contrôle des fonds** : pour retraits et transferts
4. **Calcul des frais** : selon le type de transaction
5. **Création en statut "en_cours"**
6. **Mise à jour du solde** : si validation automatique
7. **Changement de statut** : en "valide"

### Validation automatique vs manuelle
- **Dépôts** : validation automatique
- **Retraits** : validation manuelle (sécurité)
- **Transferts** : validation manuelle (vérification destinataire)

## Calcul des soldes

### Mise à jour automatique
```php
public function updateAccountBalance(Transaction $transaction)
{
    $compte = $transaction->compte;

    switch ($transaction->type) {
        case 'depot':
            $compte->increment('solde', $transaction->montant);
            break;

        case 'retrait':
            $compte->decrement('solde', $transaction->montant + $this->calculateFees($transaction));
            break;

        case 'transfert':
            // Pour les transferts, gérer compte source et destinataire
            if ($transaction->isSourceAccount()) {
                $compte->decrement('solde', $transaction->montant + $this->calculateFees($transaction));
            } else {
                $compte->increment('solde', $transaction->montant);
            }
            break;
    }
}
```

### Calcul des frais
```php
private function calculateFees(Transaction $transaction): float
{
    $baseFees = [
        'depot' => 0,
        'retrait' => 500,
        'transfert' => 1000,
    ];

    $fee = $baseFees[$transaction->type] ?? 0;

    // Ajouter TVA 18%
    return $fee * 1.18;
}
```

## Sécurité et audit

### Contrôles de sécurité
- **Authentification requise** : toutes les opérations
- **Autorisation** : clients voient seulement leurs transactions
- **Validation des montants** : prévention des montants négatifs
- **Limites de fréquence** : rate limiting par utilisateur

### Traçabilité
- **Logs détaillés** : chaque opération loggée
- **Historique complet** : conservation de toutes les transactions
- **Audit trail** : suivi des modifications de statut
- **Timestamps** : created_at, updated_at, date de transaction

## Gestion des erreurs

### Codes d'erreur courants
- `INSUFFICIENT_FUNDS`: Fonds insuffisants
- `INVALID_AMOUNT`: Montant invalide
- `ACCOUNT_INACTIVE`: Compte inactif
- `TRANSACTION_FAILED`: Échec de transaction
- `ACCOUNT_NOT_FOUND`: Compte inexistant

### Gestion des rollbacks
- **Échec de transaction** : annulation automatique
- **Restauration des soldes** : en cas d'erreur
- **Compensation** : mécanisme de correction

## Rapports et statistiques

### Métriques disponibles
- **Volume total** : somme des transactions par période
- **Nombre de transactions** : par type et statut
- **Évolution des soldes** : historique des mouvements
- **Frais générés** : revenus par type de transaction

### Exports
- **CSV** : export des transactions filtrées
- **PDF** : relevés de compte
- **API** : données pour tableaux de bord externes

## Intégration avec autres modules

### Comptes
- **Mise à jour automatique** : des soldes après transaction
- **Vérification de statut** : comptes actifs seulement
- **Historique intégré** : transactions liées aux comptes

### Clients
- **Accès filtré** : transactions de leurs comptes uniquement
- **Notifications** : alertes sur transactions importantes
- **Historique client** : suivi des opérations

### Administrateurs
- **Supervision** : validation manuelle des transactions
- **Monitoring** : alertes sur anomalies
- **Rapports** : statistiques complètes

## Tests

### Tests unitaires
- Validation des calculs de frais
- Test des règles métier
- Vérification des contraintes

### Tests fonctionnels
- Création de transactions valides/invalides
- Mise à jour des soldes
- Gestion des erreurs

### Tests d'intégration
- Workflow complet de transaction
- Intégration avec comptes et clients
- Gestion des rollbacks

## Performance

### Optimisations
- **Indexation** : sur compte_id, date, type
- **Pagination** : limitation des résultats
- **Cache** : pour les soldes fréquemment consultés
- **Async** : traitement des transactions lourdes

### Monitoring
- **Temps de réponse** : SLA définis
- **Taux de succès** : suivi des échecs
- **Charge système** : monitoring des ressources

## Bonnes pratiques

1. **Atomicité** : transactions en une seule opération
2. **Isolation** : prévention des conflits de concurrence
3. **Cohérence** : soldes toujours à jour
4. **Durabilité** : persistance garantie des données
5. **Audit** : traçabilité complète des opérations
6. **Sécurité** : validation stricte des autorisations

## Évolutions futures

- **Transactions programmées** : virements récurrents
- **Validation biométrique** : authentification avancée
- **Transferts instantanés** : entre comptes de la même banque
- **Intégration bancaire** : connexion aux autres banques
- **Cryptomonnaies** : support des actifs numériques
- **IA prédictive** : détection de fraudes
- **API temps réel** : notifications WebSocket
- **Multi-devises** : conversion automatique