# Fonctionnalités API (API Features)

## Vue d'ensemble
Le système expose une API REST complète avec des fonctionnalités avancées comme le rate limiting, la gestion des réponses uniformes, la documentation automatique et la sécurité renforcée. L'API suit les standards RESTful et inclut des middlewares spécialisés pour la gestion des requêtes.

## Architecture API

### Principes de conception
- **RESTful** : respect des conventions REST
- **Versioning** : API versionnée (v1)
- **Documentation** : Swagger/OpenAPI automatique
- **Sécurité** : authentification et autorisation
- **Performance** : cache et optimisation

### Technologies utilisées
- **Laravel** : framework principal
- **Laravel Passport** : authentification OAuth2
- **Laravel Sanctum** : SPA authentication
- **Swagger/OpenAPI** : documentation automatique
- **Rate limiting** : protection contre les abus

## Structure des réponses API

### Format uniforme des réponses
Toutes les réponses API suivent un format standardisé :

**Réponse de succès :**
```json
{
    "success": true,
    "message": "Opération réussie",
    "data": {
        // Données spécifiques
    },
    "timestamp": "2025-10-27T15:20:00Z",
    "path": "/api/v1/comptes",
    "traceId": "abc123def456"
}
```

**Réponse d'erreur :**
```json
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Les données fournies sont invalides",
        "details": {
            "email": "L'adresse email est requise",
            "password": "Le mot de passe doit contenir au moins 8 caractères"
        },
        "timestamp": "2025-10-27T15:20:00Z",
        "path": "/api/v1/auth/login",
        "traceId": "abc123def456"
    }
}
```

### Trait ApiResponseTrait
```php
trait ApiResponseTrait
{
    protected function successResponse($data = null, string $message = 'Opération réussie', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'path' => request()->path(),
            'traceId' => uniqid()
        ], $status);
    }

    protected function errorResponse(string $code, string $message, array $details = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'timestamp' => now()->toISOString(),
                'path' => request()->path(),
                'traceId' => uniqid()
            ]
        ], $status);
    }

    protected function validationErrorResponse(Validator $validator): JsonResponse
    {
        $details = [];
        foreach ($validator->errors()->messages() as $field => $messages) {
            $details[$field] = $messages[0];
        }

        return $this->errorResponse('VALIDATION_ERROR', 'Les données fournies sont invalides', $details, 400);
    }
}
```

## Rate Limiting

### Middleware ApiRateLimit
Protection contre les abus et les attaques par déni de service.

```php
class ApiRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->ip();
        $maxAttempts = 100; // 100 requêtes par minute

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Trop de requêtes. Veuillez réessayer plus tard.',
                    'details' => [
                        'retry_after' => $retryAfter
                    ],
                    'timestamp' => now()->toISOString(),
                    'path' => $request->path(),
                    'traceId' => uniqid()
                ]
            ], 429);
        }

        RateLimiter::hit($key);

        return $next($request);
    }
}
```

### Configuration du rate limiting
- **Par IP** : 100 requêtes/minute
- **Par utilisateur authentifié** : limites supplémentaires
- **Par endpoint** : seuils spécifiques selon la criticité

### Gestion des dépassements
- **Headers de réponse** : indication des limites restantes
- **Retry-After** : temps d'attente suggéré
- **Logging** : suivi des abus potentiels

## Gestion des réponses API

### Middleware ApiResponseFormat
Uniformisation automatique des réponses.

```php
class ApiResponseFormat
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Ne modifier que les réponses JSON de l'API
        if ($request->is('api/*') && $response instanceof JsonResponse) {
            $data = $response->getData(true);

            // Ajouter les métadonnées si pas déjà présentes
            if (!isset($data['timestamp'])) {
                $data['timestamp'] = now()->toISOString();
                $data['path'] = $request->path();
                $data['traceId'] = uniqid();

                $response->setData($data);
            }
        }

        return $response;
    }
}
```

## Logging et audit

### Middleware LoggingMiddleware
Suivi complet des requêtes API.

```php
class LoggingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = microtime(true) - $startTime;

        Log::info('API Request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
            'status_code' => $response->getStatusCode(),
            'duration' => round($duration * 1000, 2) . 'ms',
            'trace_id' => $request->header('X-Trace-Id', uniqid())
        ]);

        return $response;
    }
}
```

### Types de logs
- **Requêtes normales** : niveau INFO
- **Erreurs** : niveau ERROR avec stack trace
- **Sécurité** : niveau WARNING pour tentatives suspectes
- **Performance** : métriques de performance

## Gestion des erreurs

### Codes d'erreur standardisés
| Code | Description | HTTP Status |
|---|---|---|
| VALIDATION_ERROR | Données invalides | 400 |
| UNAUTHORIZED | Non authentifié | 401 |
| FORBIDDEN | Accès refusé | 403 |
| NOT_FOUND | Ressource inexistante | 404 |
| RATE_LIMIT_EXCEEDED | Limite de taux dépassée | 429 |
| INTERNAL_ERROR | Erreur serveur | 500 |

### Gestion globale des exceptions
```php
// Dans app/Exceptions/Handler.php
public function render($request, Throwable $exception)
{
    if ($request->is('api/*')) {
        return $this->handleApiException($request, $exception);
    }

    return parent::render($request, $exception);
}

private function handleApiException(Request $request, Throwable $exception): JsonResponse
{
    $statusCode = 500;
    $errorCode = 'INTERNAL_ERROR';
    $message = 'Une erreur interne s\'est produite';

    if ($exception instanceof ValidationException) {
        $statusCode = 400;
        $errorCode = 'VALIDATION_ERROR';
        $message = 'Les données fournies sont invalides';
    } elseif ($exception instanceof AuthenticationException) {
        $statusCode = 401;
        $errorCode = 'UNAUTHORIZED';
        $message = 'Authentification requise';
    }

    return response()->json([
        'success' => false,
        'error' => [
            'code' => $errorCode,
            'message' => $message,
            'details' => config('app.debug') ? $exception->getMessage() : [],
            'timestamp' => now()->toISOString(),
            'path' => $request->path(),
            'traceId' => uniqid()
        ]
    ], $statusCode);
}
```

## Pagination

### Format standardisé
```json
{
    "success": true,
    "data": [...],
    "pagination": {
        "currentPage": 1,
        "totalPages": 5,
        "totalItems": 50,
        "itemsPerPage": 10,
        "hasNext": true,
        "hasPrevious": false
    },
    "links": {
        "self": "/api/v1/comptes?page=1",
        "next": "/api/v1/comptes?page=2",
        "first": "/api/v1/comptes?page=1",
        "last": "/api/v1/comptes?page=5"
    }
}
```

### Implémentation dans les contrôleurs
```php
public function index(Request $request)
{
    $perPage = min($request->get('limit', 10), 100);
    $comptes = Compte::paginate($perPage);

    return $this->successResponse(new CompteCollection($comptes));
}
```

## Documentation API

### Swagger/OpenAPI
Configuration automatique avec annotations PHP.

```php
/**
 * @OA\Info(
 *     title="Bank Manager API",
 *     version="1.0.0",
 *     description="API for managing bank accounts"
 * )
 *
 * @OA\Server(
 *     url="http://api.banque.example.com/api/v1",
 *     description="Production API server"
 * )
 */
class CompteController extends Controller
{
    /**
     * @OA\Get(
     *     path="/v1/comptes",
     *     summary="Lister tous les comptes",
     *     @OA\Response(response=200, description="Liste des comptes")
     * )
     */
    public function index() {
        // ...
    }
}
```

### Génération de la documentation
```bash
php artisan l5-swagger:generate
```

## Sécurité API

### Headers de sécurité
```php
// Middleware pour ajouter les headers de sécurité
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        $response->headers->set('Content-Security-Policy', "default-src 'self'");

        return $response;
    }
}
```

### Validation des entrées
- **Sanitisation** : nettoyage automatique des entrées
- **Type checking** : validation des types de données
- **Limites de taille** : prévention des attaques par payload volumineux

## Performance et optimisation

### Cache des réponses
```php
// Cache des endpoints peu changeants
public function index(Request $request)
{
    return Cache::remember("comptes_index_{$request->getQueryString()}", 300, function () use ($request) {
        $comptes = Compte::paginate(10);
        return new CompteCollection($comptes);
    });
}
```

### Compression des réponses
- **Gzip** : compression automatique pour les réponses > 1KB
- **Brotli** : compression avancée si supportée

### Optimisation des requêtes
- **Eager loading** : chargement des relations
- **Select spécifique** : seulement les colonnes nécessaires
- **Indexing** : optimisation des requêtes de base de données

## Tests API

### Tests fonctionnels
```php
class CompteApiTest extends TestCase
{
    public function test_list_comptes_requires_authentication()
    {
        $response = $this->getJson('/api/v1/comptes');

        $response->assertStatus(401);
    }

    public function test_create_compte_success()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/comptes', [
                'type' => 'courant',
                'soldeInitial' => 50000
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'numeroCompte', 'solde'],
                'timestamp'
            ]);
    }

    public function test_rate_limiting()
    {
        // Simuler 101 requêtes
        for ($i = 0; $i < 101; $i++) {
            $this->getJson('/api/v1/comptes');
        }

        $response = $this->getJson('/api/v1/comptes');
        $response->assertStatus(429);
    }
}
```

## Monitoring et métriques

### Métriques collectées
- **Temps de réponse** : moyenne, percentiles
- **Taux d'erreur** : par endpoint et code d'erreur
- **Utilisation du rate limiting** : nombre de rejets
- **Trafic** : requêtes par minute/heure

### Alertes configurées
- **Erreurs élevées** : > 5% d'erreurs sur 5 minutes
- **Latence élevée** : > 2 secondes en moyenne
- **Rate limiting** : > 50 rejets par minute
- **Trafic anormal** : variations importantes

## Bonnes pratiques

1. **Versioning** : évolution contrôlée de l'API
2. **Documentation** : toujours à jour et complète
3. **Sécurité** : authentification et autorisation strictes
4. **Performance** : monitoring et optimisation continus
5. **Tests** : couverture complète des endpoints
6. **Monitoring** : alertes et métriques en temps réel
7. **Logging** : traçabilité complète des opérations

## Évolutions futures

- **GraphQL** : requêtes plus flexibles
- **Webhooks** : notifications temps réel
- **API Gateway** : gestion centralisée du trafic
- **Rate limiting intelligent** : basé sur l'utilisateur
- **API Analytics** : insights détaillés sur l'utilisation
- **Versioning automatique** : gestion des breaking changes
- **HATEOAS** : navigation hypermedia
- **OpenAPI 3.1** : dernières spécifications