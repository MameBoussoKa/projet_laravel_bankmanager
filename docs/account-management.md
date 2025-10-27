# Gestion des Comptes (Account Management)

## Vue d'ensemble
Le système de gestion des comptes permet de créer, gérer et contrôler les comptes bancaires des clients. Il prend en charge différents types de comptes (courant et épargne), avec des fonctionnalités avancées comme le blocage/déblocage, l'archivage automatique et la recherche hybride (local + cloud).

## Structure de la base de données

### Table `comptes`
```sql
CREATE TABLE comptes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_compte VARCHAR(255) UNIQUE NOT NULL,
    type ENUM('courant', 'epargne') NOT NULL,
    solde DECIMAL(15, 2) NOT NULL DEFAULT 0,
    statut ENUM('actif', 'bloque', 'ferme') NOT NULL DEFAULT 'actif',
    client_id BIGINT UNSIGNED NOT NULL,
    devise VARCHAR(255) DEFAULT 'FCFA',
    motifBlocage TEXT NULL,
    metadata JSON NULL,
    dateFermeture TIMESTAMP NULL,
    dateBlocage TIMESTAMP NULL,
    dateDeblocagePrevue TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL, -- Soft deletes

    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);
```

### Champs importants
- **numero_compte**: Numéro unique du compte (format: CXXXXXXXX)
- **type**: Type de compte (courant/epargne)
- **solde**: Solde actuel du compte
- **statut**: État du compte (actif/bloque/ferme)
- **client_id**: Référence vers le client propriétaire
- **devise**: Devise du compte (défaut: FCFA)
- **motifBlocage**: Raison du blocage (si applicable)
- **dateBlocage/dateDeblocagePrevue**: Gestion temporelle du blocage

## Modèle Compte

### Relations
```php
class Compte extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'numero_compte', 'type', 'solde', 'statut',
        'client_id', 'devise', 'motifBlocage', 'metadata',
        'dateFermeture', 'dateBlocage', 'dateDeblocagePrevue'
    ];

    protected $casts = [
        'solde' => 'decimal:2',
        'metadata' => 'array',
        'dateFermeture' => 'datetime',
        'dateBlocage' => 'datetime',
        'dateDeblocagePrevue' => 'datetime',
    ];

    // Relations
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // Scopes
    public function scopeNumero($query, $numero)
    {
        return $query->where('numero_compte', $numero);
    }

    public function scopeClient($query, $telephone)
    {
        return $query->whereHas('client', function($q) use ($telephone) {
            $q->where('telephone', $telephone);
        });
    }

    // Global scope pour filtrer les comptes actifs
    protected static function booted(): void
    {
        static::addGlobalScope(new ActiveScope());
    }

    // Accessors
    public function getTitulaireAttribute()
    {
        return $this->client ? $this->client->nom . ' ' . $this->client->prenom : null;
    }

    public function getSoldeAttribute($value)
    {
        return number_format($value, 2, '.', '');
    }

    public function getDateCreationAttribute()
    {
        return $this->created_at?->format('Y-m-d H:i:s');
    }

    public function getDerniereModificationAttribute()
    {
        return $this->updated_at?->format('Y-m-d H:i:s');
    }
}
```

## Fonctionnalités principales

### 1. Création d'un compte
Processus de création :
1. Validation des données d'entrée
2. Vérification du solde minimum (10,000 FCFA)
3. Génération automatique du numéro de compte unique
4. Création du client si nécessaire (avec génération de mot de passe et code)
5. Envoi d'email d'authentification au client
6. Envoi de SMS avec code d'authentification

### 2. Consultation des comptes
- **Filtrage avancé** : par type, statut, recherche textuelle
- **Tri personnalisé** : par date, solde, titulaire
- **Pagination** : contrôle du nombre d'éléments par page
- **Recherche hybride** : comptes locaux + archivés (pour comptes épargne)
- **Autorisation** : clients voient seulement leurs comptes, admins voient tous

### 3. Mise à jour des informations client
- Modification des informations personnelles du client
- Mise à jour des coordonnées (téléphone, email)
- Changement de mot de passe
- Versioning des modifications via metadata

### 4. Blocage et déblocage des comptes
- **Blocage** : uniquement pour comptes épargne actifs
- **Durée configurable** : jours ou mois
- **Motif obligatoire** : justification du blocage
- **Déblocage manuel** : par administrateur avec motif
- **Archivage automatique** : comptes bloqués expirés sont archivés

### 5. Suppression (soft delete)
- Marquage comme "fermé" avec date de fermeture
- Soft delete pour conservation des données
- Uniquement par administrateurs

## API Endpoints

### Lister les comptes
```
GET /api/comptes?page=1&limit=10&type=courant&statut=actif&search=Dupont&sort=solde&order=desc
```

**Paramètres de requête :**
- `page`: numéro de page (défaut: 1)
- `limit`: éléments par page (max: 100)
- `type`: filtrer par type (courant/epargne)
- `statut`: filtrer par statut (actif/bloque/ferme)
- `search`: recherche par titulaire ou numéro
- `sort`: champ de tri (dateCreation/solde/titulaire)
- `order`: ordre (asc/desc)
- `include_archived`: inclure comptes archivés (pour épargne)

### Créer un compte
```
POST /api/comptes
Content-Type: application/json

{
    "type": "courant",
    "soldeInitial": 50000,
    "devise": "FCFA",
    "client": {
        "id": 1
    }
}

// Ou créer un nouveau client
{
    "type": "epargne",
    "soldeInitial": 100000,
    "devise": "FCFA",
    "client": {
        "titulaire": "Amadou Diallo",
        "email": "amadou.diallo@example.com",
        "telephone": "+221771234568",
        "adresse": "Dakar, Sénégal"
    }
}
```

### Consulter un compte
```
GET /api/comptes/{compteId}
```

### Mettre à jour les informations client
```
PATCH /api/comptes/{compteId}
Content-Type: application/json

{
    "titulaire": "Amadou Diallo Junior",
    "informationsClient": {
        "telephone": "+221771234569",
        "email": "amadou.diallo@example.com",
        "password": "nouveauMotDePasse123"
    }
}
```

### Bloquer un compte
```
POST /api/comptes/{compteId}/bloquer
Content-Type: application/json

{
    "motif": "Activité suspecte détectée",
    "duree": 30,
    "unite": "jours"
}
```

### Débloquer un compte
```
POST /api/comptes/{compteId}/debloquer
Content-Type: application/json

{
    "motif": "Vérification complétée avec succès"
}
```

### Supprimer un compte
```
DELETE /api/comptes/{compteId}
```

## Génération automatique

### Numéro de compte
```php
private function generateAccountNumber(): string
{
    do {
        $numero = 'C' . str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
    } while (Compte::where('numero_compte', $numero)->exists());

    return $numero;
}
```

### Mot de passe client
```php
private function generatePassword(): string
{
    return Str::random(12); // 12 caractères aléatoires
}
```

### Code d'authentification
```php
private function generateCode(): string
{
    do {
        $code = strtoupper(Str::random(8)); // 8 caractères alphanumériques
    } while (Client::where('nci', $code)->exists());

    return $code;
}
```

## Services utilisés

### CloudStorageService
- Archivage automatique des comptes bloqués expirés
- Recherche dans les comptes archivés
- Stockage des données sensibles dans le cloud

### SmsService
- Envoi de codes d'authentification lors de la création
- Notifications SMS pour les opérations importantes

### MailService
- Envoi d'emails d'authentification avec mot de passe temporaire

## Sécurité et autorisation

### Rôles et permissions
- **Client** : accès uniquement à ses propres comptes
- **Admin** : accès à tous les comptes, opérations de blocage/déblocage/suppression

### Validation
- Contrôle d'accès basé sur l'email utilisateur
- Vérification des statuts de compte avant opérations
- Validation des montants et durées

### Audit
- Logs détaillés de toutes les opérations
- Traçabilité des modifications via metadata
- Historique des blocages/déblocages

## Gestion des statuts

### États possibles
- **actif** : compte opérationnel
- **bloque** : compte temporairement indisponible
- **ferme** : compte clôturé (soft delete)

### Transitions autorisées
- actif → bloque (admin seulement)
- bloque → actif (admin seulement)
- actif/bloque → ferme (admin seulement)

## Archivage automatique

### Commandes Artisan
```bash
# Archiver les comptes bloqués expirés
php artisan app:archive-expired-blocked-accounts

# Désarchiver les comptes dont le blocage a expiré
php artisan app:unarchive-expired-blocked-accounts
```

### Processus d'archivage
1. Identification des comptes bloqués avec `dateDeblocagePrevue` dépassée
2. Archivage des données dans le cloud
3. Archivage des transactions associées
4. Soft delete du compte local

### Désarchivage
1. Vérification de l'expiration du blocage
2. Restauration des données depuis le cloud
3. Recréation du compte local si nécessaire
4. Restauration des transactions

## Gestion des erreurs

### Codes d'erreur courants
- `VALIDATION_ERROR`: Données invalides
- `ACCOUNT_NOT_FOUND`: Compte inexistant
- `FORBIDDEN`: Accès refusé
- `ACCOUNT_NOT_ACTIVE`: Compte pas actif
- `INVALID_ACCOUNT_TYPE`: Type de compte invalide
- `ACCOUNT_ALREADY_CLOSED`: Compte déjà fermé

## Tests

### Tests unitaires
- Validation des modèles et relations
- Test des méthodes de génération
- Test des scopes et accessors

### Tests fonctionnels
- Création de comptes avec/sans client existant
- Opérations CRUD complètes
- Gestion des autorisations
- Blocage/déblocage de comptes

### Tests d'intégration
- Interaction avec services externes (SMS, Email, Cloud)
- Gestion des erreurs et edge cases

## Bonnes pratiques

1. **Génération sécurisée** : numéros de compte uniques et imprévisibles
2. **Validation stricte** : contrôle de tous les paramètres d'entrée
3. **Soft deletes** : conservation des données pour audit
4. **Versioning** : traçabilité des modifications via metadata
5. **Séparation des responsabilités** : logique métier dans le modèle, présentation dans les ressources
6. **Cache intelligent** : utilisation de scopes globaux pour optimisation

## Évolutions futures

- Support multi-devises avec conversion automatique
- Comptes joints (plusieurs titulaires)
- Intégration avec services de paiement externes
- Notifications en temps réel (WebSocket)
- Analyse prédictive des risques de blocage
- Interface d'administration avancée
- API pour applications mobiles natives