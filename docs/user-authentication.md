# Authentification Utilisateur (User Authentication)

## Vue d'ensemble
Le système d'authentification gère la sécurité d'accès à l'application bancaire en combinant authentification classique (email/mot de passe) avec vérification à deux facteurs par SMS. Il prend en charge différents types d'utilisateurs (clients et administrateurs) avec des niveaux de permissions appropriés.

## Architecture d'authentification

### Technologies utilisées
- **Laravel Passport** : pour l'authentification API OAuth2
- **JWT Tokens** : pour les sessions stateless
- **SMS Service** : pour la vérification à deux facteurs
- **bcrypt** : pour le hashage des mots de passe

### Types d'utilisateurs
1. **Clients** : utilisateurs finaux avec comptes bancaires
2. **Administrateurs** : personnel bancaire avec droits étendus
3. **Utilisateurs système** : pour les APIs internes

## Structure de la base de données

### Table `users` (utilisée par Passport)
```sql
CREATE TABLE users (
    id CHAR(36) PRIMARY KEY, -- UUID
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### Tables OAuth2 (Passport)
- `oauth_access_tokens` : tokens d'accès actifs
- `oauth_refresh_tokens` : tokens de rafraîchissement
- `oauth_clients` : applications clientes
- `oauth_personal_access_clients` : clients personnels

## Flux d'authentification

### 1. Inscription (pour nouveaux clients)
```
POST /api/auth/register
Content-Type: application/json

{
    "nom": "Dupont",
    "prenom": "Jean",
    "email": "jean.dupont@email.com",
    "telephone": "+221771234567",
    "adresse": "123 Rue de la Paix, Dakar",
    "nci": "1234567890123"
}
```

**Processus :**
1. Validation des données d'inscription
2. Vérification d'unicité (email, téléphone, NCI)
3. Création du client avec mot de passe temporaire
4. Envoi d'email d'authentification
5. Envoi de SMS avec code de vérification
6. Création automatique d'un compte bancaire

### 2. Connexion (login)
```
POST /api/auth/login
Content-Type: application/json

{
    "email": "jean.dupont@email.com",
    "password": "motdepasse123"
}
```

**Processus :**
1. Validation des identifiants
2. Vérification du mot de passe
3. Détermination du type d'utilisateur (client/admin)
4. Génération d'un code d'authentification
5. Envoi du code par SMS
6. Retour d'un token temporaire (pending verification)

### 3. Vérification du code SMS
```
POST /api/auth/verify-code
Content-Type: application/json

{
    "email": "jean.dupont@email.com",
    "code": "123456"
}
```

**Processus :**
1. Validation du code fourni
2. Vérification de l'expiration (5 minutes)
3. Génération du token d'accès définitif (JWT/OAuth)
4. Définition des permissions selon le rôle
5. Retour du token et informations utilisateur

### 4. Rafraîchissement du token
```
POST /api/auth/refresh
Authorization: Bearer {refresh_token}
```

## Gestion des tokens

### Types de tokens
- **Access Token** : token d'accès aux APIs (court terme - 1h)
- **Refresh Token** : token de renouvellement (long terme - 30 jours)
- **Personal Access Token** : tokens permanents pour APIs

### Sécurité des tokens
- **Chiffrement** : tokens signés avec clé secrète
- **Expiration** : gestion automatique des expirations
- **Révocation** : possibilité d'invalider des tokens
- **Scopes** : permissions granulaires

## Vérification à deux facteurs (2FA)

### Implémentation SMS
```php
class SmsVerificationService
{
    public function sendVerificationCode(string $phoneNumber): string
    {
        $code = $this->generateCode();
        $this->cacheCode($phoneNumber, $code, 300); // 5 minutes

        $smsService = new SmsService();
        $smsService->sendSms($phoneNumber, "Code de vérification: $code");

        return $code;
    }

    public function verifyCode(string $phoneNumber, string $code): bool
    {
        $cachedCode = Cache::get("sms_code_$phoneNumber");

        if ($cachedCode && $cachedCode === $code) {
            Cache::forget("sms_code_$phoneNumber");
            return true;
        }

        return false;
    }

    private function generateCode(): string
    {
        return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
```

### Gestion des codes
- **Longueur** : 6 chiffres
- **Expiration** : 5 minutes
- **Tentatives** : maximum 3 essais par code
- **Blocage** : après échecs répétés (temporaire)

## Gestion des mots de passe

### Politiques de sécurité
- **Longueur minimum** : 8 caractères
- **Complexité** : majuscules, minuscules, chiffres, symboles
- **Expiration** : changement obligatoire tous les 90 jours
- **Historique** : pas de réutilisation des 5 derniers mots de passe

### Réinitialisation
```
POST /api/auth/forgot-password
Content-Type: application/json

{
    "email": "jean.dupont@email.com"
}
```

**Processus :**
1. Génération d'un token de réinitialisation
2. Envoi d'email avec lien sécurisé
3. Validation du token (expiration 1 heure)
4. Mise à jour du mot de passe

## Autorisation et permissions

### Middleware d'authentification
```php
// Vérifier l'authentification
public function handle(Request $request, Closure $next): Response
{
    if (!Auth::check()) {
        return response()->json(['error' => 'Non autorisé'], 401);
    }

    return $next($request);
}

// Vérifier le rôle administrateur
public function handleAdmin(Request $request, Closure $next): Response
{
    $user = Auth::user();

    if (!$this->isAdmin($user)) {
        return response()->json(['error' => 'Accès administrateur requis'], 403);
    }

    return $next($request);
}
```

### Permissions par rôle
| Endpoint | Client | Admin |
|---|---|---|
| `/api/comptes` (liste) | ✅ (ses comptes) | ✅ (tous) |
| `/api/comptes` (création) | ❌ | ✅ |
| `/api/comptes/*/bloquer` | ❌ | ✅ |
| `/api/transactions` | ✅ (ses transactions) | ✅ (toutes) |

## Sécurité avancée

### Protection contre les attaques
- **Rate limiting** : limitation du nombre de tentatives
- **Brute force protection** : blocage après échecs répétés
- **Session management** : invalidation des sessions suspectes
- **Audit logging** : traçabilité de toutes les authentifications

### Chiffrement et hashage
- **Mots de passe** : bcrypt avec cost factor élevé
- **Tokens** : signature HMAC-SHA256
- **Données sensibles** : chiffrement AES-256

## Gestion des sessions

### États de session
- **Active** : session valide et utilisable
- **Expired** : token expiré, nécessite refresh
- **Revoked** : session révoquée manuellement
- **Suspended** : compte suspendu temporairement

### Monitoring des sessions
- **Connexions actives** : suivi en temps réel
- **Historique** : logs des connexions/déconnexions
- **Alertes** : connexions depuis IPs inhabituelles

## API Endpoints d'authentification

### Authentification de base
```
POST /api/auth/login
POST /api/auth/logout
POST /api/auth/refresh
POST /api/auth/me
```

### Gestion des mots de passe
```
POST /api/auth/forgot-password
POST /api/auth/reset-password
PUT /api/auth/change-password
```

### Vérification 2FA
```
POST /api/auth/send-code
POST /api/auth/verify-code
POST /api/auth/disable-2fa
```

## Intégration avec les services externes

### SMS Service
- Envoi de codes de vérification
- Notifications de sécurité
- Alertes de connexion

### Email Service
- Confirmations d'inscription
- Réinitialisations de mot de passe
- Notifications importantes

### Cache/Redis
- Stockage temporaire des codes SMS
- Sessions utilisateur
- Rate limiting

## Tests et validation

### Tests unitaires
- Validation des tokens JWT
- Test des services d'authentification
- Vérification des permissions

### Tests fonctionnels
- Flux complet d'inscription/connexion
- Gestion des erreurs d'authentification
- Test des middlewares de sécurité

### Tests de sécurité
- Tentatives d'injection
- Attaques par déni de service
- Test des politiques de mots de passe

## Bonnes pratiques

1. **Ne jamais stocker les mots de passe en clair**
2. **Utiliser HTTPS pour toutes les communications**
3. **Implémenter un rate limiting strict**
4. **Logger toutes les tentatives d'authentification**
5. **Utiliser des tokens de courte durée**
6. **Mettre en place une surveillance continue**
7. **Prévoir des mécanismes de récupération**

## Évolutions futures

- **Authentification biométrique** : empreintes, reconnaissance faciale
- **WebAuthn** : clés de sécurité physiques
- **Authentification sociale** : Google, Facebook (pour admin)
- **Zero Trust Architecture** : vérification continue
- **Blockchain authentication** : identité décentralisée
- **Machine Learning** : détection d'anomalies comportementales
- **Multi-tenant** : isolation des organisations