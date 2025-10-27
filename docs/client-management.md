# Gestion des Clients (Client Management)

## Vue d'ensemble
Le système de gestion des clients permet d'enregistrer, gérer et authentifier les clients de la banque. Chaque client peut avoir plusieurs comptes bancaires et effectuer des transactions.

## Structure de la base de données

### Table `clients`
```sql
CREATE TABLE clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    prenom VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    telephone VARCHAR(255) NOT NULL,
    adresse TEXT NOT NULL,
    password VARCHAR(255) NOT NULL,
    nci VARCHAR(255) UNIQUE NOT NULL, -- Numéro de Carte d'Identité
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### Champs importants
- **nom**: Nom du client
- **prenom**: Prénom du client
- **email**: Adresse email unique
- **telephone**: Numéro de téléphone
- **adresse**: Adresse complète
- **password**: Mot de passe hashé
- **nci**: Numéro de Carte d'Identité (unique)

## Modèle Client

### Relations
```php
class Client extends Model
{
    protected $fillable = [
        'nom', 'prenom', 'email', 'telephone',
        'adresse', 'password', 'nci'
    ];

    protected $hidden = ['password'];

    // Un client peut avoir plusieurs comptes
    public function comptes()
    {
        return $this->hasMany(Compte::class);
    }
}
```

## Fonctionnalités principales

### 1. Création d'un client
Lors de la création d'un client :
1. Validation des données d'entrée
2. Vérification de l'unicité de l'email et du NCI
3. Hashage du mot de passe
4. Génération automatique d'un numéro de compte
5. Génération d'un mot de passe temporaire
6. Envoi d'un email d'authentification
7. Envoi d'un code SMS pour vérification

### 2. Authentification du client
Processus d'authentification :
1. Vérification des identifiants (email + mot de passe)
2. Génération d'un code d'authentification
3. Envoi du code par SMS
4. Vérification du code saisi par l'utilisateur
5. Création d'un token d'accès (OAuth/Passport)

### 3. Gestion du profil client
- Mise à jour des informations personnelles
- Changement de mot de passe
- Consultation des comptes associés
- Historique des transactions

## API Endpoints

### Création d'un client
```
POST /api/clients
Content-Type: application/json

{
    "nom": "DUPONT",
    "prenom": "Jean",
    "email": "jean.dupont@email.com",
    "telephone": "+221771234567",
    "adresse": "123 Rue de la Paix, Dakar",
    "nci": "1234567890123"
}
```

### Authentification
```
POST /api/auth/login
Content-Type: application/json

{
    "email": "jean.dupont@email.com",
    "password": "motdepasse123"
}
```

### Vérification du code SMS
```
POST /api/auth/verify-code
Content-Type: application/json

{
    "email": "jean.dupont@email.com",
    "code": "123456"
}
```

### Mise à jour du profil
```
PUT /api/clients/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "telephone": "+221771234568",
    "adresse": "456 Avenue des banques, Dakar"
}
```

## Services utilisés

### SmsService
- Envoi de codes d'authentification par SMS
- Utilise Twilio comme fournisseur SMS
- Configuration requise dans `.env` :
  - `TWILIO_SID`
  - `TWILIO_TOKEN`
  - `TWILIO_FROM`

### MailService
- Envoi d'emails d'authentification
- Utilise Laravel Mail avec template Blade

## Sécurité

### Validation des données
- Validation côté serveur avec Laravel Request classes
- Sanitisation des entrées
- Vérification des formats (email, téléphone, NCI)

### Authentification
- Hashage des mots de passe avec bcrypt
- Authentification à deux facteurs (mot de passe + SMS)
- Tokens JWT ou OAuth pour les sessions

### Autorisation
- Middleware pour vérifier les permissions
- Accès limité aux propres données du client
- Logs d'audit pour les modifications sensibles

## Gestion des erreurs

### Codes d'erreur courants
- `VALIDATION_ERROR`: Données invalides
- `CLIENT_EXISTS`: Client déjà existant
- `AUTH_FAILED`: Échec d'authentification
- `SMS_FAILED`: Échec d'envoi SMS
- `EMAIL_FAILED`: Échec d'envoi email

## Tests

### Tests unitaires
- Validation des modèles
- Test des relations
- Test des méthodes métier

### Tests fonctionnels
- Création de client
- Authentification complète
- Mise à jour de profil
- Gestion des erreurs

## Migration et Seeders

### Migration
```bash
php artisan migrate
```

### Seeders
```bash
php artisan db:seed --class=ClientSeeder
```

## Déploiement

### Variables d'environnement
```env
# Base de données
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bankmanager
DB_USERNAME=user
DB_PASSWORD=password

# Services externes
TWILIO_SID=votre_account_sid_twilio
TWILIO_TOKEN=votre_auth_token_twilio
TWILIO_FROM=+1234567890

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=votre_email@gmail.com
MAIL_PASSWORD=votre_mot_de_passe_app
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=votre_email@gmail.com
MAIL_FROM_NAME="BankManager"
```

## Bonnes pratiques

1. **Validation stricte** : Toujours valider les données côté serveur
2. **Sécurité** : Ne jamais stocker les mots de passe en clair
3. **Logs** : Logger toutes les actions importantes
4. **Tests** : Maintenir une couverture de test élevée
5. **Documentation** : Documenter tous les endpoints API
6. **Monitoring** : Surveiller les échecs d'authentification

## Évolutions futures

- Intégration avec des services de vérification d'identité
- Authentification biométrique
- Gestion des documents (CNI, justificatifs)
- Notifications push
- Support multi-devises pour les clients internationaux