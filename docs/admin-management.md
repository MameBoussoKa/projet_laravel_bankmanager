# Gestion des Administrateurs (Admin Management)

## Vue d'ensemble
Le système de gestion des administrateurs permet de créer et gérer les comptes administrateur qui ont accès aux fonctionnalités avancées du système bancaire. Les administrateurs peuvent gérer les clients, comptes, transactions, et effectuer des opérations sensibles comme le blocage/déblocage de comptes.

## Structure de la base de données

### Table `admins`
```sql
CREATE TABLE admins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### Champs importants
- **nom**: Nom complet de l'administrateur
- **email**: Adresse email unique (utilisée pour l'authentification)
- **mot_de_passe**: Mot de passe hashé

## Modèle Admin

### Configuration et relations
```php
class Admin extends Model
{
    use HasApiTokens, HasFactory;

    protected $fillable = ['nom', 'email', 'mot_de_passe'];

    protected $hidden = ['mot_de_passe'];

    // Accessor pour masquer le mot de passe
    protected $hidden = ['mot_de_passe'];

    // Casts pour les types de données
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
```

## Rôles et permissions

### Permissions des administrateurs
- **Gestion des clients** : CRUD complet sur les clients
- **Gestion des comptes** : création, consultation, blocage/déblocage, suppression
- **Gestion des transactions** : validation manuelle, annulation, supervision
- **Rapports et statistiques** : accès aux données complètes
- **Configuration système** : paramètres globaux, seuils, etc.
- **Audit et logs** : consultation des logs d'audit

### Différences avec les clients
| Fonctionnalité | Client | Administrateur |
|---|---|---|
| Voir ses comptes | ✅ | ✅ (tous) |
| Gérer ses comptes | ✅ (limité) | ✅ (complet) |
| Voir transactions | ✅ (propres) | ✅ (toutes) |
| Bloquer comptes | ❌ | ✅ |
| Créer comptes | ❌ | ✅ |
| Supprimer comptes | ❌ | ✅ |
| Accès statistiques | ❌ | ✅ |

## API Endpoints

### Lister les administrateurs
```
GET /api/admins
Authorization: Bearer {admin_token}
```

**Réponse :**
```json
[
    {
        "id": 1,
        "nom": "Jean Dupont",
        "email": "jean.dupont@banque.com",
        "created_at": "2025-01-15T10:30:00Z",
        "updated_at": "2025-01-15T10:30:00Z"
    }
]
```

### Créer un administrateur
```
POST /api/admins
Authorization: Bearer {admin_token}
Content-Type: application/json

{
    "nom": "Marie Martin",
    "email": "marie.martin@banque.com",
    "mot_de_passe": "MotDePasseSecure123!"
}
```

### Consulter un administrateur
```
GET /api/admins/{adminId}
Authorization: Bearer {admin_token}
```

### Mettre à jour un administrateur
```
PUT /api/admins/{adminId}
Authorization: Bearer {admin_token}
Content-Type: application/json

{
    "nom": "Marie Dubois",
    "email": "marie.dubois@banque.com"
}
```

### Supprimer un administrateur
```
DELETE /api/admins/{adminId}
Authorization: Bearer {admin_token}
```

## Authentification des administrateurs

### Processus de connexion
1. **Validation des identifiants** : email + mot de passe
2. **Vérification du rôle** : confirmation que c'est un admin
3. **Génération du token** : JWT ou OAuth token
4. **Définition des permissions** : scopes d'accès

### Middleware d'autorisation
```php
// Vérifier si l'utilisateur est un administrateur
public function isAdmin(User $user): bool
{
    return Admin::where('email', $user->email)->exists();
}

// Middleware pour les routes admin
public function handle(Request $request, Closure $next): Response
{
    $user = Auth::user();

    if (!$this->isAdmin($user)) {
        return response()->json([
            'error' => 'Accès refusé - Droits administrateur requis'
        ], 403);
    }

    return $next($request);
}
```

## Sécurité

### Politiques de mot de passe
- **Longueur minimum** : 8 caractères
- **Complexité** : au moins une majuscule, minuscule, chiffre, caractère spécial
- **Expiration** : changement obligatoire tous les 90 jours
- **Historique** : interdiction de réutiliser les 5 derniers mots de passe

### Authentification multi-facteurs (2FA)
- **Optionnel mais recommandé** : activation possible
- **Méthodes supportées** : application TOTP, SMS
- **Obligatoire pour** : opérations sensibles (suppression, blocage permanent)

### Audit et logs
- **Logs d'accès** : chaque connexion/déconnexion
- **Logs d'actions** : toutes les opérations administratives
- **Alertes** : tentatives de connexion échouées, actions suspectes

## Gestion des sessions

### Durée de session
- **Expiration** : 8 heures d'inactivité
- **Renouvellement automatique** : via refresh tokens
- **Déconnexion forcée** : changement de mot de passe

### Gestion des tokens
- **Type** : Bearer tokens (Laravel Passport)
- **Scopes** : admin.read, admin.write, admin.delete
- **Révocation** : possibilité de révoquer tous les tokens d'un admin

## Fonctionnalités avancées

### Tableaux de bord
- **Métriques en temps réel** : nombre de comptes actifs, transactions du jour
- **Alertes système** : comptes bloqués, soldes négatifs, erreurs système
- **Rapports automatisés** : génération quotidienne/hebdomadaire/mensuelle

### Outils d'administration
- **Recherche avancée** : clients, comptes, transactions
- **Exports de données** : CSV, PDF pour conformité
- **Configuration système** : seuils d'alerte, limites de transaction
- **Maintenance** : archivage automatique, nettoyage des logs

## Création et gestion

### Création d'administrateurs
- **Via API** : endpoint dédié (réservé aux admins existants)
- **Via seeders** : pour l'initialisation du système
- **Via factories** : pour les tests

### Gestion des rôles
- **Super admin** : accès complet à toutes les fonctionnalités
- **Admin standard** : accès limité (pas de suppression d'admins)
- **Auditeur** : accès en lecture seule aux données sensibles

## Tests et validation

### Tests unitaires
- Validation des modèles et relations
- Test des permissions et autorisations
- Vérification des règles métier

### Tests fonctionnels
- Authentification admin réussie/échouée
- Accès aux endpoints protégés
- Opérations CRUD sur les entités

### Tests de sécurité
- Tentatives d'accès non autorisé
- Injection SQL et XSS
- Rate limiting et brute force

## Bonnes pratiques

1. **Principe du moindre privilège** : donner uniquement les permissions nécessaires
2. **Séparation des rôles** : ne pas mélanger admin et client
3. **Logs complets** : traçabilité de toutes les actions
4. **Changement régulier** : des mots de passe et tokens
5. **Surveillance** : monitoring des accès et actions
6. **Sauvegarde** : stratégie de récupération en cas de compromission

## Intégration avec le système

### Authentification unifiée
- **Base utilisateurs commune** : table `users` pour l'authentification
- **Rôles dynamiques** : vérification en temps réel des permissions
- **Single sign-on** : possibilité d'extension future

### API cohérente
- **Format uniforme** : mêmes standards que l'API client
- **Documentation Swagger** : génération automatique
- **Versioning** : gestion des évolutions d'API

## Évolutions futures

- **RBAC avancé** : Role-Based Access Control granulaire
- **Audit trails** : blockchain pour l'immuabilité
- **Intégration LDAP** : authentification d'entreprise
- **Dashboard personnalisable** : widgets configurables
- **Notifications avancées** : alertes en temps réel
- **API Management** : gestion fine des accès API
- **Compliance** : conformité RGPD, PCI DSS