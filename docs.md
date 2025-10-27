# Documentation Complète - Projet Laravel Bank Manager

## Vue d'ensemble

Le projet **Laravel Bank Manager** est une API REST complète pour la gestion d'un système bancaire. Il permet de gérer les clients, comptes bancaires, transactions et administrateurs avec des fonctionnalités avancées comme l'authentification OAuth2, la limitation de débit, et l'archivage cloud.

## Table des matières

1. [Installation et Configuration](#installation-et-configuration)
2. [Architecture du Projet](#architecture-du-projet)
3. [Base de Données](#base-de-données)
4. [Modèles et Relations](#modèles-et-relations)
5. [API Endpoints](#api-endpoints)
6. [Middleware](#middleware)
7. [Services](#services)
8. [Tests](#tests)
9. [Sécurité](#sécurité)
10. [Déploiement](#déploiement)

## Installation et Configuration

### Prérequis

- PHP 8.1 ou supérieur
- Composer
- MySQL 8.0 ou MariaDB
- Node.js et npm (pour les assets frontend)
- Docker (optionnel pour le développement)

### Installation

1. **Cloner le repository**
   ```bash
   git clone <repository-url>
   cd projet_laravel_bankmanager
   ```

2. **Installer les dépendances PHP**
   ```bash
   composer install
   ```

3. **Installer les dépendances JavaScript**
   ```bash
   npm install
   ```

4. **Configuration de l'environnement**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configuration de la base de données**
   - Modifier le fichier `.env` avec vos paramètres de base de données
   - Exécuter les migrations :
   ```bash
   php artisan migrate
   ```

6. **Peupler la base de données (optionnel)**
   ```bash
   php artisan db:seed
   ```

7. **Générer la documentation API**
   ```bash
   php artisan l5-swagger:generate
   ```

8. **Démarrer le serveur**
   ```bash
   php artisan serve
   ```

### Configuration Docker (optionnel)

```bash
# Construire et démarrer les conteneurs
docker-compose up -d

# Exécuter les migrations dans le conteneur
docker-compose exec app php artisan migrate
```

## Architecture du Projet

### Structure des Dossiers

```
app/
├── Console/           # Commandes Artisan
├── Exceptions/        # Gestion des exceptions personnalisées
├── Http/
│   ├── Controllers/Api/  # Contrôleurs API
│   ├── Middleware/       # Middleware personnalisés
│   ├── Requests/         # Classes de validation
│   └── Resources/        # Ressources API
├── Models/            # Modèles Eloquent
├── Providers/         # Service Providers
├── Services/          # Services métier
└── Traits/            # Traits réutilisables

database/
├── factories/         # Factories pour les tests
├── migrations/        # Migrations de base de données
└── seeders/           # Seeders pour données de test

tests/                 # Tests unitaires et fonctionnels
```

### Technologies Utilisées

- **Framework**: Laravel 10.x
- **Authentification**: Laravel Passport (OAuth2)
- **Base de données**: MySQL/MariaDB
- **Cache**: Redis (optionnel)
- **Documentation API**: L5-Swagger/OpenAPI
- **Tests**: PHPUnit
- **Frontend**: Vite + Vue.js (optionnel)

### Patterns Architecturaux

- **Repository Pattern**: Non implémenté (utilisation directe des modèles Eloquent)
- **Service Layer**: Utilisé pour la logique métier complexe (CloudStorageService)
- **Trait Pattern**: Utilisé pour les fonctionnalités réutilisables (ApiResponseTrait)
- **Middleware Pattern**: Utilisé pour la gestion transversale (logging, rate limiting, etc.)

## Base de Données

### Schéma

#### Table `clients`
- `id` (bigint, primary key)
- `nom` (varchar)
- `prenom` (varchar)
- `email` (varchar, unique)
- `telephone` (varchar)
- `adresse` (text)
- `password` (varchar, hashed)
- `nci` (varchar, unique, numéro de carte d'identité)
- `timestamps`

#### Table `comptes`
- `id` (bigint, primary key)
- `numero_compte` (varchar, unique)
- `type` (enum: 'courant', 'epargne')
- `solde` (decimal 15,2)
- `statut` (enum: 'actif', 'bloque', 'ferme')
- `client_id` (bigint, foreign key)
- `devise` (varchar, default 'FCFA')
- `motifBlocage` (text, nullable)
- `metadata` (json, nullable)
- `dateFermeture` (timestamp, nullable)
- `timestamps`
- `deleted_at` (timestamp, nullable, soft deletes)

#### Table `transactions`
- `id` (bigint, primary key)
- `type` (enum: 'depot', 'retrait', 'transfert')
- `montant` (decimal 15,2)
- `date` (timestamp)
- `statut` (enum: 'en_cours', 'valide', 'annule')
- `compte_id` (bigint, foreign key)
- `timestamps`

#### Table `admins`
- `id` (bigint, primary key)
- `nom` (varchar)
- `email` (varchar, unique)
- `mot_de_passe` (varchar)
- `timestamps`

### Migrations

Les migrations sont organisées chronologiquement :

1. `2014_10_12_000000_create_users_table.php` - Utilisateurs Laravel standard
2. `2016_06_01_000001_create_oauth_auth_codes_table.php` - Tables OAuth2 Passport
3. `2025_10_23_155840_create_clients_table.php` - Table clients
4. `2025_10_23_155853_create_comptes_table.php` - Table comptes
5. `2025_10_23_155901_create_transactions_table.php` - Table transactions
6. `2025_10_23_155910_create_admins_table.php` - Table administrateurs
7. `2025_10_23_184956_add_missing_fields_to_comptes_table.php` - Champs additionnels comptes
8. `2025_10_26_232745_add_soft_deletes_to_comptes_table.php` - Soft deletes comptes
9. `2025_10_27_012339_add_date_fermeture_to_comptes_table.php` - Date de fermeture comptes

### Relations

- **Client → Comptes**: One-to-Many (un client peut avoir plusieurs comptes)
- **Compte → Client**: Many-to-One (un compte appartient à un client)
- **Compte → Transactions**: One-to-Many (un compte peut avoir plusieurs transactions)
- **Transaction → Compte**: Many-to-One (une transaction appartient à un compte)

## Modèles et Relations

### Modèle Client

```php
class Client extends Model
{
    use HasApiTokens, HasFactory;

    protected $fillable = ['nom', 'prenom', 'email', 'telephone', 'adresse', 'password', 'nci'];
    protected $hidden = ['password'];

    public function comptes()
    {
        return $this->hasMany(Compte::class);
    }
}
```

### Modèle Compte

```php
class Compte extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope(new ActiveScope);
    }

    protected $fillable = ['numero_compte', 'type', 'solde', 'statut', 'client_id', 'devise', 'motifBlocage', 'metadata', 'dateFermeture'];

    protected $casts = [
        'solde' => 'decimal:2',
        'metadata' => 'array',
        'dateFermeture' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // Scopes personnalisés
    public function scopeNumero($query, $numero)
    public function scopeClient($query, $telephone)

    // Accessors
    public function getTitulaireAttribute()
    public function getDateCreationAttribute()
    public function getDerniereModificationAttribute()
}
```

### Modèle Transaction

```php
class Transaction extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'montant', 'date', 'statut', 'compte_id'];

    protected $casts = [
        'montant' => 'decimal:2',
        'date' => 'datetime',
    ];

    public function compte()
    {
        return $this->belongsTo(Compte::class);
    }
}
```

### Modèle Admin

```php
class Admin extends Model
{
    use HasApiTokens, HasFactory;

    protected $fillable = ['nom', 'email', 'mot_de_passe'];
    protected $hidden = ['mot_de_passe'];
}
```

## API Endpoints

### Routes API

Toutes les routes sont préfixées par `/api/v1` et utilisent les middlewares suivants :
- `api.rate.limit` - Limitation du débit par IP
- `api.user.rate.limit` - Limitation du débit par utilisateur
- `api.response.format` - Formatage des réponses
- `rating` - Logging des dépassements de limite

### Endpoints Comptes

#### GET `/api/v1/comptes`
- **Description**: Lister tous les comptes avec filtrage et pagination
- **Authentification**: Requise (Bearer Token)
- **Paramètres de requête**:
  - `page` (integer, default: 1)
  - `limit` (integer, max: 100, default: 10)
  - `type` (string: 'courant'|'epargne')
  - `statut` (string: 'actif'|'bloque'|'ferme')
  - `search` (string) - Recherche par titulaire ou numéro
  - `sort` (string: 'dateCreation'|'solde'|'titulaire')
  - `order` (string: 'asc'|'desc', default: 'desc')

#### POST `/api/v1/comptes`
- **Description**: Créer un nouveau compte
- **Authentification**: Requise
- **Corps de la requête**:
  ```json
  {
    "type": "courant|epargne",
    "soldeInitial": 10000.00,
    "devise": "FCFA",
    "client": {
      "id": 1, // ou données pour nouveau client
      "titulaire": "Nom Prénom",
      "email": "email@example.com",
      "telephone": "+221771234568",
      "adresse": "Adresse complète"
    }
  }
  ```

#### GET `/api/v1/comptes/{compteId}`
- **Description**: Récupérer un compte spécifique
- **Authentification**: Requise
- **Recherche hybride**: Local puis cloud si nécessaire

#### PATCH `/api/v1/comptes/{compteId}`
- **Description**: Mettre à jour les informations client du compte
- **Authentification**: Requise
- **Corps de la requête**:
  ```json
  {
    "titulaire": "Nouveau Nom",
    "informationsClient": {
      "telephone": "+221771234569",
      "email": "nouveau@email.com",
      "password": "nouveaumotdepasse",
      "nci": "ABC12346"
    }
  }
  ```

#### DELETE `/api/v1/comptes/{compteId}`
- **Description**: Supprimer un compte (soft delete)
- **Authentification**: Requise (Admin uniquement)
- **Action**: Marque le compte comme 'ferme' et définit `dateFermeture`

### Endpoints Transactions

#### GET `/api/v1/transactions`
- **Description**: Lister toutes les transactions

#### POST `/api/v1/transactions`
- **Description**: Créer une nouvelle transaction
- **Mise à jour automatique du solde**: Débit/crédit selon le type

#### GET `/api/v1/transactions/{id}`
- **Description**: Récupérer une transaction spécifique

#### PUT/PATCH `/api/v1/transactions/{id}`
- **Description**: Mettre à jour une transaction
- **Correction automatique du solde**: Annule l'ancien mouvement et applique le nouveau

#### DELETE `/api/v1/transactions/{id}`
- **Description**: Supprimer une transaction
- **Correction automatique du solde**: Annule le mouvement

### Endpoints Clients

#### GET `/api/v1/clients`
- **Description**: Lister tous les clients

#### POST `/api/v1/clients`
- **Description**: Créer un nouveau client

#### GET `/api/v1/clients/{id}`
- **Description**: Récupérer un client spécifique

#### PUT/PATCH `/api/v1/clients/{id}`
- **Description**: Mettre à jour un client

#### DELETE `/api/v1/clients/{id}`
- **Description**: Supprimer un client

### Endpoints Administrateurs

#### GET `/api/v1/admins`
- **Description**: Lister tous les administrateurs

#### POST `/api/v1/admins`
- **Description**: Créer un nouvel administrateur

#### GET `/api/v1/admins/{id}`
- **Description**: Récupérer un administrateur spécifique

#### PUT/PATCH `/api/v1/admins/{id}`
- **Description**: Mettre à jour un administrateur

#### DELETE `/api/v1/admins/{id}`
- **Description**: Supprimer un administrateur

## Middleware

### ApiRateLimit
- **Limite**: 100 requêtes par minute par IP
- **Réponse 429**: Avec temps d'attente restant

### ApiUserRateLimit
- **Limite**: 10 requêtes par jour par utilisateur authentifié
- **Réponse 429**: Avec temps d'attente restant

### ApiResponseFormat
- **Ajout automatique**: Headers X-API-Version, X-Request-ID
- **Formatage**: Réponses JSON standardisées
- **Gestion d'erreurs**: Codes d'erreur normalisés

### LoggingMiddleware
- **Log**: Toutes les requêtes API avec métadonnées
- **Informations**: Méthode, URL, durée, utilisateur, opération, ressource

### RatingMiddleware
- **Monitoring**: Dépassements de limite de débit
- **Log**: Informations détaillées pour analyse

## Services

### CloudStorageService

Service pour l'archivage et récupération des comptes épargne depuis le cloud.

**Méthodes principales**:
- `getArchivedSavingsAccounts(array $filters)`: Récupère les comptes épargne archivés
- `getArchivedAccount(string $compteId)`: Récupère un compte archivé spécifique
- `archiveAccount(Compte $compte)`: Archive un compte dans le cloud

**Configuration**:
```env
CLOUD_STORAGE_URL=https://api.cloud-storage.example.com/v1
CLOUD_STORAGE_API_KEY=your-api-key
```

## Tests

### Structure des Tests

- **Tests unitaires**: `tests/Unit/`
- **Tests fonctionnels**: `tests/Feature/`

### Exemple de Test (CompteControllerTest)

```php
public function test_admin_can_delete_compte(): void
{
    // Création des données de test
    $admin = Admin::factory()->create();
    $user = User::factory()->create(['email' => $admin->email]);
    $client = Client::factory()->create();
    $compte = Compte::factory()->create([
        'client_id' => $client->id,
        'statut' => 'actif'
    ]);

    // Authentification
    $this->actingAs($user, 'sanctum');

    // Exécution de la requête
    $response = $this->deleteJson("/api/v1/comptes/{$compte->id}");

    // Assertions
    $response->assertStatus(200)
             ->assertJson([
                 'success' => true,
                 'message' => 'Compte supprimé avec succès'
             ]);

    // Vérification soft delete
    $this->assertSoftDeleted($compte);
}
```

### Exécution des Tests

```bash
# Tous les tests
php artisan test

# Tests spécifiques
php artisan test tests/Feature/CompteControllerTest.php

# Avec couverture
php artisan test --coverage
```

## Sécurité

### Authentification

- **OAuth2**: Via Laravel Passport
- **Tokens**: Access tokens et refresh tokens
- **Scopes**: Gestion des permissions (optionnel)

### Autorisation

- **Middleware**: Vérification des rôles (admin/client)
- **Contrôle d'accès**: Comptes accessibles uniquement par leur propriétaire (clients) ou tous (admins)

### Validation

- **Form Requests**: Validation des données d'entrée
- **Règles personnalisées**: Téléphone sénégalais, montants positifs, etc.

### Protection contre les attaques

- **Rate Limiting**: Limitation du débit par IP et par utilisateur
- **CORS**: Configuration des origines autorisées
- **CSRF**: Protection activée pour les routes web
- **SQL Injection**: Prévention via Eloquent ORM
- **XSS**: Échappement automatique des données

## Déploiement

### Préparation pour la Production

1. **Variables d'environnement**
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.com
   ```

2. **Optimisation**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   composer install --optimize-autoloader --no-dev
   ```

3. **Base de données**
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=ProductionSeeder
   ```

4. **Permissions**
   ```bash
   chown -R www-data:www-data storage/
   chown -R www-data:www-data bootstrap/cache/
   ```

### Configuration Serveur Web (Nginx)

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/project/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Monitoring

- **Logs**: Surveillance des logs Laravel
- **Métriques**: Temps de réponse, taux d'erreur
- **Alertes**: Dépassements de limites, erreurs 5xx

## Conclusion

Cette documentation couvre l'ensemble du système bancaire Laravel. Le projet suit les bonnes pratiques Laravel avec une architecture modulaire, des tests complets, et une sécurité renforcée. Pour toute question supplémentaire ou contribution, veuillez consulter les issues du repository ou contacter l'équipe de développement.