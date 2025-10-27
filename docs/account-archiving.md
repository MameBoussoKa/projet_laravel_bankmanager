# Archivage et Désarchivage des Comptes (Account Archiving/Unarchiving)

## Vue d'ensemble
Le système d'archivage automatique gère les comptes bloqués dont la période de blocage a expiré. Il déplace les données sensibles vers le cloud storage tout en maintenant la traçabilité et permettant la restauration future si nécessaire. Ce système assure la conformité réglementaire et optimise les performances de la base de données locale.

## Architecture du système d'archivage

### Composants principaux
- **Commandes Artisan** : tâches automatisées programmées
- **CloudStorageService** : interface avec le stockage cloud
- **Soft Deletes** : suppression logique des comptes locaux
- **Audit Trail** : traçabilité complète des opérations

### Flux d'archivage
```
Comptes bloqués expirés
        ↓
Identification automatique
        ↓
Archivage cloud (compte + transactions)
        ↓
Soft delete local
        ↓
Confirmation et logging
```

## Structure des données archivées

### Format de stockage cloud
```json
{
    "id": "uuid-compte",
    "numero_compte": "C00123456",
    "titulaire": "Amadou Diallo",
    "type": "epargne",
    "solde": 150000.00,
    "devise": "FCFA",
    "date_creation": "2025-01-15T10:30:00Z",
    "date_archivage": "2025-10-27T12:00:00Z",
    "statut": "bloque",
    "motifBlocage": "Activité suspecte détectée",
    "dateBlocage": "2025-09-27T12:00:00Z",
    "dateDeblocagePrevue": "2025-10-27T12:00:00Z",
    "client_id": "uuid-client",
    "metadata": {
        "version": 1,
        "derniereModification": "2025-09-27T12:00:00Z"
    },
    "transactions": [
        {
            "type": "depot",
            "montant": 50000.00,
            "date": "2025-09-15T10:00:00Z",
            "statut": "valide"
        }
    ]
}
```

## Commandes d'archivage

### ArchiveExpiredBlockedAccounts
```bash
php artisan app:archive-expired-blocked-accounts
```

**Fonctionnement :**
1. Recherche tous les comptes avec `statut = 'bloque'` et `dateDeblocagePrevue < now()`
2. Pour chaque compte trouvé :
   - Archive les données du compte vers le cloud
   - Archive toutes les transactions associées
   - Effectue un soft delete du compte local
3. Génère un rapport d'exécution

**Code principal :**
```php
public function handle()
{
    $expiredBlockedAccounts = Compte::withoutGlobalScopes()
        ->where('statut', 'bloque')
        ->where('dateDeblocagePrevue', '<=', now())
        ->with(['client', 'transactions'])
        ->get();

    foreach ($expiredBlockedAccounts as $compte) {
        // Archivage cloud
        $accountArchived = $cloudService->archiveAccount($compte);
        $transactionsArchived = $cloudService->archiveAccountTransactions($compte);

        if ($accountArchived && $transactionsArchived) {
            $compte->delete(); // Soft delete
        }
    }
}
```

### UnarchiveExpiredBlockedAccounts
```bash
php artisan app:unarchive-expired-blocked-accounts
```

**Fonctionnement :**
1. Récupère la liste des comptes bloqués archivés dans le cloud
2. Vérifie si la période de blocage est réellement expirée
3. Pour chaque compte éligible :
   - Restaure le compte local (si soft deleted)
   - Met à jour le statut en "actif"
   - Restaure les transactions associées
   - Supprime l'archive cloud
4. Génère un rapport d'exécution

## Service de stockage cloud

### CloudStorageService

#### archiveAccount(Compte $compte)
- Prépare les données du compte pour l'archivage
- Envoie vers l'API cloud avec authentification
- Gère les erreurs et les retries

#### archiveAccountTransactions(Compte $compte)
- Récupère toutes les transactions du compte
- Structure les données pour l'archivage
- Archive par batch pour optimiser les performances

#### getArchivedBlockedAccounts()
- Récupère la liste des comptes bloqués archivés
- Utilise la pagination pour gérer les gros volumes

#### restoreAccountTransactions(Compte $compte, string $archivedAccountId)
- Restaure les transactions depuis le cloud
- Recrée les enregistrements locaux
- Préserve les relations et métadonnées

## Programmation automatique

### Kernel.php (app/Console/Kernel.php)
```php
protected function schedule(Schedule $schedule)
{
    // Archivage quotidien à 2h du matin
    $schedule->command('app:archive-expired-blocked-accounts')
             ->dailyAt('02:00')
             ->withoutOverlapping()
             ->runInBackground();

    // Désarchivage quotidien à 3h du matin
    $schedule->command('app:unarchive-expired-blocked-accounts')
             ->dailyAt('03:00')
             ->withoutOverlapping()
             ->runInBackground();
}
```

### Gestion des chevauchements
- `withoutOverlapping()` : empêche l'exécution simultanée
- `runInBackground()` : exécution en arrière-plan
- Logs détaillés pour le monitoring

## Règles métier d'archivage

### Conditions d'archivage
- **Statut** : uniquement les comptes `bloque`
- **Expiration** : `dateDeblocagePrevue` dépassée
- **Type** : principalement les comptes épargne
- **Intégrité** : données complètes et cohérentes

### Exceptions
- Comptes avec transactions en cours
- Comptes sous investigation judiciaire
- Comptes avec blocages administratifs spéciaux

### Périodes de rétention
- **Archivage cloud** : conservation permanente
- **Soft delete local** : 90 jours avant suppression définitive
- **Logs d'audit** : conservation 7 ans (conformité réglementaire)

## Sécurité et conformité

### Chiffrement des données
- **Données sensibles** : chiffrement AES-256 avant archivage
- **Clés de chiffrement** : rotation automatique
- **Accès cloud** : authentification forte (API keys + certificats)

### Audit et traçabilité
- **Logs détaillés** : chaque opération d'archivage/désarchivage
- **Traçabilité** : qui, quand, pourquoi
- **Conformité** : respect des normes bancaires (RGPD, etc.)

### Gestion des accès
- **Administrateurs seulement** : opérations manuelles réservées
- **APIs sécurisées** : endpoints protégés par authentification
- **Monitoring** : alertes sur les échecs d'archivage

## Gestion des erreurs et recovery

### Stratégies de retry
```php
public function archiveWithRetry(Compte $compte, int $maxRetries = 3)
{
    $attempts = 0;

    while ($attempts < $maxRetries) {
        try {
            return $this->cloudService->archiveAccount($compte);
        } catch (Exception $e) {
            $attempts++;
            Log::warning("Tentative d'archivage échouée", [
                'compte_id' => $compte->id,
                'attempt' => $attempts,
                'error' => $e->getMessage()
            ]);

            if ($attempts < $maxRetries) {
                sleep(pow(2, $attempts)); // Exponential backoff
            }
        }
    }

    return false;
}
```

### Récupération d'erreur
- **Rollback automatique** : en cas d'échec partiel
- **Alertes** : notification des administrateurs
- **Recovery manuelle** : procédures documentées

## Performance et optimisation

### Optimisations implementées
- **Batch processing** : traitement par lots pour les gros volumes
- **Lazy loading** : chargement des relations à la demande
- **Indexing** : index sur les champs de recherche fréquents
- **Caching** : mise en cache des configurations

### Métriques de performance
- **Temps d'exécution** : monitoring des durées
- **Taux de succès** : suivi des échecs/réussites
- **Utilisation ressources** : CPU, mémoire, réseau

## Monitoring et alerting

### Métriques surveillées
- Nombre de comptes archivés/désarchivés par jour
- Taux d'échec des opérations
- Temps de réponse des APIs cloud
- Utilisation du stockage cloud

### Alertes configurées
- Échec d'archivage répété
- Quota de stockage cloud atteint
- Anomalies dans les données
- Dépassement des seuils de performance

## Tests et validation

### Tests unitaires
- Test des services d'archivage
- Validation des règles métier
- Test des scénarios d'erreur

### Tests d'intégration
- Workflow complet d'archivage
- Intégration avec le cloud storage
- Test des commandes Artisan

### Tests de performance
- Archivage de gros volumes
- Gestion de la concurrence
- Test des limites système

## Bonnes pratiques

1. **Archivage régulier** : éviter l'accumulation de données locales
2. **Sauvegarde préalable** : toujours vérifier l'intégrité avant archivage
3. **Monitoring continu** : surveillance des opérations automatisées
4. **Recovery plan** : procédures de récupération documentées
5. **Audit complet** : traçabilité de toutes les opérations
6. **Sécurité renforcée** : chiffrement et contrôles d'accès stricts

## Évolutions futures

- **Archivage intelligent** : basé sur l'analyse des patterns d'utilisation
- **Compression automatique** : réduction de l'espace de stockage
- **Archivage multi-cloud** : répartition sur plusieurs fournisseurs
- **Blockchain audit** : traçabilité immuable des opérations
- **Machine Learning** : prédiction des besoins d'archivage
- **Archivage temps réel** : pour les comptes très actifs
- **API de recherche avancée** : dans les archives
- **Restauration sélective** : choix des données à restaurer