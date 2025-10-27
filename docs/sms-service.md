# Service SMS (SMS Service)

## Vue d'ensemble
Le SmsService est un service spécialisé qui gère l'envoi de messages SMS pour l'authentification à deux facteurs, les notifications et les alertes de sécurité. Il utilise Twilio comme fournisseur SMS et assure la fiabilité, la sécurité et le suivi des envois.

## Architecture et conception

### Principes de conception
- **Interface simple** : API unifiée pour tous types de SMS
- **Fiabilité** : gestion des erreurs et retry automatique
- **Sécurité** : protection contre les abus et le spam
- **Observabilité** : logging et monitoring complets

### Technologies utilisées
- **Twilio SDK** : Client PHP officiel pour Twilio
- **Laravel Logging** : système de logs intégré
- **Cache/Redis** : stockage temporaire des codes
- **Validation** : contrôle des formats de numéros

## Configuration

### Variables d'environnement
```env
# Configuration Twilio
TWILIO_SID=votre_account_sid_twilio
TWILIO_TOKEN=votre_auth_token_twilio
TWILIO_FROM=+1234567890

# Configuration du service
SMS_TIMEOUT=30
SMS_MAX_RETRIES=3
SMS_CODE_EXPIRY=300
SMS_RATE_LIMIT=5
```

### Initialisation du service
```php
use Twilio\Rest\Client;

class SmsService
{
    protected $twilio;
    protected int $timeout;
    protected int $maxRetries;
    protected int $codeExpiry;
    protected string $fromNumber;

    public function __construct()
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');

        // Validation de la configuration
        if (!$sid || !$token || $sid === 'votre_account_sid_twilio') {
            throw new \Exception('Twilio credentials not properly configured');
        }

        $this->twilio = new Client($sid, $token);
        $this->timeout = config('services.sms.timeout', 30);
        $this->maxRetries = config('services.sms.max_retries', 3);
        $this->codeExpiry = config('services.sms.code_expiry', 300);
        $this->fromNumber = config('services.twilio.from');
    }
}
```

## Fonctionnalités principales

### 1. Envoi de SMS générique

#### sendSms(string $to, string $message): bool
Envoie un message SMS à un numéro spécifique.

**Paramètres :**
- `$to`: Numéro de téléphone au format international (ex: +221771234567)
- `$message`: Contenu du message

**Retour :** `true` si l'envoi réussit, `false` sinon

**Exemple d'utilisation :**
```php
$smsService = new SmsService();

$result = $smsService->sendSms('+221771234567', 'Votre solde est de 150 000 FCFA');

if ($result) {
    Log::info('SMS envoyé avec succès');
} else {
    Log::error('Échec de l\'envoi du SMS');
}
```

### 2. Envoi de codes d'authentification

#### sendAuthenticationCode(string $phoneNumber, string $code): bool
Envoie un code d'authentification par SMS.

**Paramètres :**
- `$phoneNumber`: Numéro de téléphone du destinataire
- `$code`: Code à envoyer (généralement 6 chiffres)

**Retour :** `true` si l'envoi réussit, `false` sinon

**Format du message :**
```
Votre code d'authentification BankManager est: 123456
```

**Utilisation principale :** Authentification à deux facteurs lors de la connexion

## Gestion des erreurs et résilience

### Stratégie de retry
```php
private function sendWithRetry(string $to, string $message): bool
{
    $attempts = 0;

    while ($attempts < $this->maxRetries) {
        try {
            $this->twilio->messages->create($to, [
                'from' => $this->fromNumber,
                'body' => $message
            ]);

            Log::info("SMS envoyé avec succès à {$to}");
            return true;

        } catch (\Twilio\Exceptions\RestException $e) {
            $attempts++;

            // Gestion des erreurs spécifiques Twilio
            if ($e->getCode() === 21211) { // Numéro invalide
                Log::error("Numéro de téléphone invalide: {$to}");
                break; // Pas de retry pour numéro invalide
            }

            if ($attempts < $this->maxRetries) {
                sleep(pow(2, $attempts)); // Exponential backoff
                Log::warning("Tentative d'envoi SMS échouée, retry dans " . pow(2, $attempts) . " secondes", [
                    'attempt' => $attempts,
                    'to' => $to,
                    'error' => $e->getMessage()
                ]);
            }
        } catch (\Exception $e) {
            $attempts++;
            Log::error("Erreur inattendue lors de l'envoi SMS", [
                'attempt' => $attempts,
                'to' => $to,
                'error' => $e->getMessage()
            ]);
        }
    }

    Log::error("Échec définitif de l'envoi SMS après {$this->maxRetries} tentatives", [
        'to' => $to,
        'message' => substr($message, 0, 100) // Tronquer pour sécurité
    ]);

    return false;
}
```

### Types d'erreurs gérées
- **Erreurs réseau** : timeouts, connexions perdues
- **Numéros invalides** : format incorrect, numéro inexistant
- **Quota dépassé** : limite de SMS atteinte
- **Erreurs Twilio** : problèmes côté fournisseur
- **Rate limiting** : trop de requêtes

## Sécurité et conformité

### Protection contre les abus
- **Rate limiting** : limitation du nombre de SMS par numéro/heure
- **Validation des numéros** : format international obligatoire
- **Filtrage du contenu** : prévention des SMS malveillants
- **Logs sécurisés** : masquage des numéros sensibles dans les logs

### Conformité réglementaire
- **RGPD** : consentement pour l'envoi de SMS
- **TCPA** : respect des lois anti-spam US (si applicable)
- **LOI Informatique et Libertés** : protection des données personnelles

### Chiffrement et confidentialité
- **Logs masqués** : numéros partiellement masqués dans les logs
- **Contenu sensible** : codes temporaires, pas de données bancaires
- **Expiration** : codes valides seulement 5 minutes

## Validation et formatage

### Validation des numéros
```php
private function validatePhoneNumber(string $phoneNumber): bool
{
    // Format international requis: +221771234567
    $pattern = '/^\+221[0-9]{9}$/';

    if (!preg_match($pattern, $phoneNumber)) {
        Log::warning("Format de numéro invalide: {$phoneNumber}");
        return false;
    }

    return true;
}
```

### Formats supportés
- **Sénégal** : +221XXXXXXXXX (9 chiffres après l'indicatif)
- **Extension future** : support d'autres pays selon les besoins

## Intégration avec le système

### Authentification utilisateur
```php
// Dans le contrôleur d'authentification
public function login(Request $request)
{
    // Validation des identifiants
    $user = $this->validateCredentials($request->email, $request->password);

    if ($user) {
        // Génération du code
        $code = $this->generateVerificationCode();

        // Envoi par SMS
        $smsService = new SmsService();
        $result = $smsService->sendAuthenticationCode($user->telephone, $code);

        if ($result) {
            // Stockage temporaire du code
            Cache::put("auth_code_{$user->id}", $code, 300); // 5 minutes

            return response()->json([
                'message' => 'Code envoyé par SMS',
                'user_id' => $user->id
            ]);
        }
    }

    return response()->json(['error' => 'Échec de l\'authentification'], 401);
}
```

### Création de client
```php
// Envoi du code NCI lors de la création
private function sendSMSCode(Client $client, string $code): void
{
    try {
        $smsService = new SmsService();
        $result = $smsService->sendAuthenticationCode($client->telephone, $code);

        if (!$result) {
            Log::warning("Échec de l'envoi du SMS au client {$client->id}");
        }
    } catch (\Exception $e) {
        Log::error("Erreur lors de l'envoi du SMS: " . $e->getMessage());
        // Fallback: log pour développement
        Log::info("SMS de secours - Code pour {$client->telephone}: {$code}");
    }
}
```

## Monitoring et observabilité

### Métriques collectées
- **Taux de succès** : pourcentage de SMS délivrés
- **Temps de réponse** : latence moyenne des envois
- **Erreurs par type** : classification des échecs
- **Volume horaire** : nombre de SMS envoyés par heure

### Logs structurés
```php
private function logSmsOperation(string $operation, string $to, bool $success, ?string $error = null)
{
    $logData = [
        'operation' => $operation,
        'timestamp' => now()->toISOString(),
        'to' => $this->maskPhoneNumber($to), // Masquage pour sécurité
        'success' => $success,
        'service' => 'SmsService',
    ];

    if ($error) {
        $logData['error'] = $error;
        Log::error('SMS operation failed', $logData);
    } else {
        Log::info('SMS operation successful', $logData);
    }
}

private function maskPhoneNumber(string $phoneNumber): string
{
    // Masque le milieu du numéro: +22177****567
    return substr($phoneNumber, 0, 6) . '****' . substr($phoneNumber, -3);
}
```

### Alertes configurées
- **Taux d'échec élevé** : alerte si > 10% d'échecs
- **Quota dépassé** : notification de limite atteinte
- **Latence anormale** : envoi prend plus de 30 secondes
- **Numéros invalides** : trop de tentatives avec mauvais numéros

## Tests et validation

### Tests unitaires
```php
class SmsServiceTest extends TestCase
{
    public function test_send_sms_success()
    {
        // Mock du client Twilio
        $twilioMock = $this->mock(\Twilio\Rest\Client::class);
        $twilioMock->shouldReceive('messages->create')->once();

        $service = new SmsService();
        $result = $service->sendSms('+221771234567', 'Test message');

        $this->assertTrue($result);
    }

    public function test_send_sms_invalid_number()
    {
        $service = new SmsService();

        $result = $service->sendSms('771234567', 'Test message'); // Numéro invalide

        $this->assertFalse($result);
    }

    public function test_send_authentication_code()
    {
        $twilioMock = $this->mock(\Twilio\Rest\Client::class);
        $twilioMock->shouldReceive('messages->create')->once();

        $service = new SmsService();
        $result = $service->sendAuthenticationCode('+221771234567', '123456');

        $this->assertTrue($result);
    }
}
```

### Tests d'intégration
- Test des workflows complets d'authentification
- Validation de l'intégration avec Twilio
- Test des scénarios de panne réseau

### Tests de charge
- Envoi massif de SMS
- Test de la résilience sous charge
- Validation du rate limiting

## Gestion des coûts

### Optimisation des coûts
- **Batch sending** : regroupement des SMS (si supporté)
- **Tarifs préférentiels** : négociation avec Twilio
- **Monitoring des coûts** : suivi des dépenses
- **Alertes budgétaires** : notification de dépassement

### Métriques de coût
- **Coût par SMS** : tracking des prix
- **Volume mensuel** : suivi de l'utilisation
- **Coût par fonctionnalité** : authentification vs notifications

## Bonnes pratiques

1. **Configuration sécurisée** : credentials dans .env, pas en dur
2. **Validation stricte** : contrôle de tous les paramètres d'entrée
3. **Logs sécurisés** : masquage des informations sensibles
4. **Gestion d'erreurs** : retry et fallback appropriés
5. **Monitoring continu** : alertes et métriques en temps réel
6. **Tests automatisés** : couverture complète des fonctionnalités
7. **Documentation** : procédures d'urgence documentées

## Évolutions futures

- **Multi-fournisseurs** : support de plusieurs opérateurs SMS
- **SMS enrichis** : MMS, SMS avec liens
- **Routage intelligent** : choix du fournisseur selon le pays
- **Analytics avancés** : taux de délivrabilité, temps de lecture
- **Machine Learning** : optimisation des envois, détection d'anomalies
- **API temps réel** : webhooks pour statuts de livraison
- **Personnalisation** : templates dynamiques
- **Internationalisation** : support multi-langues