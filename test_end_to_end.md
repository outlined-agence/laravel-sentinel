# Guide de test end-to-end - Laravel Sentinel

Ce guide vous accompagne pas à pas pour tester le package Sentinel dans un projet Laravel.

---

## Table des matières

1. [Préparation du projet de test](#1-préparation-du-projet-de-test)
2. [Installation du package](#2-installation-du-package)
3. [Configuration](#3-configuration)
4. [Test des notifications Slack](#4-test-des-notifications-slack)
5. [Test des notifications Discord](#5-test-des-notifications-discord)
6. [Test du MonitoringService](#6-test-du-monitoringservice)
7. [Test de l'Exception Handler](#7-test-de-lexception-handler)
8. [Test du stockage en base de données](#8-test-du-stockage-en-base-de-données)
9. [Test du dashboard Filament](#9-test-du-dashboard-filament)
10. [Test du monitoring de ressources](#10-test-du-monitoring-de-ressources)
11. [Test de la déduplication](#11-test-de-la-déduplication)
12. [Test du rate limiting](#12-test-du-rate-limiting)
13. [Checklist finale](#13-checklist-finale)

---

## 1. Préparation du projet de test

### Option A : Nouveau projet Laravel

```bash
# Créer un nouveau projet Laravel
composer create-project laravel/laravel sentinel-test-app
cd sentinel-test-app

# Configurer la base de données SQLite (simple pour les tests)
touch database/database.sqlite
```

Modifier `.env` :
```env
DB_CONNECTION=sqlite
DB_DATABASE=/chemin/absolu/vers/sentinel-test-app/database/database.sqlite
```

### Option B : Projet existant

```bash
cd /chemin/vers/votre/projet
```

---

## 2. Installation du package

### Ajouter le repository local

Modifier `composer.json` du projet de test :

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "/Users/cabalex/projects/laravel-sentinel",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "outlined/laravel-sentinel": "@dev"
    }
}
```

### Installer le package

```bash
composer update outlined/laravel-sentinel
```

### Publier les fichiers

```bash
# Publier la configuration
php artisan vendor:publish --tag=sentinel-config

# Publier les migrations
php artisan vendor:publish --tag=sentinel-migrations

# Exécuter les migrations
php artisan migrate
```

### Vérifier l'installation

```bash
php artisan list | grep sentinel
```

Vous devriez voir :
```
sentinel:check-resources  Check all registered monitoring resources
sentinel:prune            Remove old monitoring events from the database
sentinel:test             Send a test notification to verify Sentinel is configured
```

---

## 3. Configuration

### Configuration minimale (.env)

```env
# Activer Sentinel
SENTINEL_ENABLED=true

# Activer le stockage en base de données
SENTINEL_DATABASE_ENABLED=true

# Désactiver Slack/Discord pour l'instant
SENTINEL_SLACK_ENABLED=false
SENTINEL_DISCORD_ENABLED=false

# Désactiver Sentry
SENTINEL_SENTRY_ENABLED=false
```

### Vérifier la configuration

```bash
php artisan tinker
```

```php
config('sentinel.enabled'); // true
config('sentinel.database.enabled'); // true
```

---

## 4. Test des notifications Slack

### 4.1 Créer un webhook Slack

1. Aller sur https://api.slack.com/apps
2. Créer une nouvelle app (ou utiliser une existante)
3. Activer "Incoming Webhooks"
4. Créer un webhook pour un channel de test
5. Copier l'URL du webhook

### 4.2 Configurer Slack

Dans `.env` :
```env
SENTINEL_SLACK_ENABLED=true
SENTINEL_SLACK_WEBHOOK=https://hooks.slack.com/services/XXX/YYY/ZZZ
SENTINEL_SLACK_CHANNEL=#test-monitoring
SENTINEL_SLACK_MENTION_ID=U12345678  # Optionnel: votre User ID Slack
```

### 4.3 Tester

```bash
# Test simple
php artisan sentinel:test

# Test avec niveau error
php artisan sentinel:test --level=error --message="Test d'erreur depuis Sentinel"

# Test niveau critical (avec mention si configuré)
php artisan sentinel:test --level=critical --message="ALERTE CRITIQUE - Test"
```

### 4.4 Vérifier

- [ ] Message reçu dans Slack
- [ ] Couleur correcte (orange pour warning, rouge pour error, violet pour critical)
- [ ] Emoji visible
- [ ] Informations d'environnement présentes
- [ ] Mention @ si niveau critical et MENTION_ID configuré

---

## 5. Test des notifications Discord

### 5.1 Créer un webhook Discord

1. Ouvrir Discord, aller dans un serveur
2. Paramètres du channel > Intégrations > Webhooks
3. Créer un nouveau webhook
4. Copier l'URL

### 5.2 Configurer Discord

Dans `.env` :
```env
SENTINEL_DISCORD_ENABLED=true
SENTINEL_DISCORD_WEBHOOK=https://discord.com/api/webhooks/XXX/YYY
```

### 5.3 Tester

```bash
php artisan sentinel:test --level=warning
```

### 5.4 Vérifier

- [ ] Message reçu dans Discord
- [ ] Embed avec couleur correcte
- [ ] Informations structurées visibles

---

## 6. Test du MonitoringService

### 6.1 Créer une route de test

Ajouter dans `routes/web.php` :

```php
use Outlined\Sentinel\Facades\Sentinel;
use Illuminate\Support\Facades\Route;

// Test logError
Route::get('/test/error', function () {
    try {
        throw new \RuntimeException('Test exception from route');
    } catch (\Exception $e) {
        Sentinel::logError($e, auth()->user(), [
            'custom_data' => 'test value',
            'request_id' => uniqid(),
        ]);
    }
    return 'Error logged! Check Slack/Discord and database.';
});

// Test logBusinessEvent
Route::get('/test/business', function () {
    // Événement réussi
    Sentinel::logBusinessEvent(
        type: 'payment',
        success: true,
        message: 'Payment processed successfully',
        additionalContext: [
            'order_id' => 12345,
            'amount' => 99.99,
            'currency' => 'EUR',
        ]
    );

    // Événement échoué
    Sentinel::logBusinessEvent(
        type: 'payment',
        success: false,
        message: 'Payment failed - Card declined',
        additionalContext: [
            'order_id' => 12346,
            'error_code' => 'card_declined',
        ]
    );

    return 'Business events logged!';
});

// Test logProviderError
Route::get('/test/provider', function () {
    Sentinel::logProviderError(
        provider: 'Stripe',
        message: 'API rate limit exceeded',
        data: [
            'endpoint' => '/v1/charges',
            'retry_after' => 60,
        ]
    );
    return 'Provider error logged!';
});

// Test logThresholdAlert
Route::get('/test/threshold', function () {
    // Warning
    Sentinel::logThresholdAlert(
        type: 'api_quota',
        message: 'API quota running low',
        currentValue: 150,
        threshold: 200,
        critical: false
    );

    // Critical
    Sentinel::logThresholdAlert(
        type: 'disk_space',
        message: 'Disk space critically low!',
        currentValue: 5,
        threshold: 10,
        critical: true
    );

    return 'Threshold alerts logged!';
});
```

### 6.2 Tester les routes

```bash
# Démarrer le serveur
php artisan serve

# Dans un autre terminal, tester les routes
curl http://localhost:8000/test/error
curl http://localhost:8000/test/business
curl http://localhost:8000/test/provider
curl http://localhost:8000/test/threshold
```

### 6.3 Vérifier

- [ ] Messages reçus dans Slack/Discord
- [ ] Contexte riche (URL, IP, etc.)
- [ ] Niveaux corrects (info pour succès, error pour échec, warning/critical pour seuils)

---

## 7. Test de l'Exception Handler

### 7.1 Configurer l'Exception Handler

#### Laravel 11 (bootstrap/app.php)

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Outlined\Sentinel\Exceptions\ReportsToSentinel;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->reportable(function (Throwable $e) {
            if (app()->bound(\Outlined\Sentinel\Services\MonitoringService::class)) {
                app(\Outlined\Sentinel\Services\MonitoringService::class)->logError($e);
            }
        });
    })->create();
```

#### Laravel 10 (app/Exceptions/Handler.php)

```php
<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Outlined\Sentinel\Exceptions\ReportsToSentinel;
use Throwable;

class Handler extends ExceptionHandler
{
    use ReportsToSentinel;

    public function report(Throwable $e): void
    {
        $this->reportToSentinelIfNeeded($e);
        parent::report($e);
    }
}
```

### 7.2 Créer des routes qui génèrent des exceptions

```php
// routes/web.php

// Exception 500 - Doit être loggée
Route::get('/test/exception-500', function () {
    throw new \RuntimeException('Erreur serveur non gérée');
});

// Exception 404 - Ne doit PAS être loggée
Route::get('/test/exception-404', function () {
    abort(404);
});

// ValidationException - Ne doit PAS être loggée
Route::get('/test/validation', function () {
    $validator = validator(['email' => 'invalid'], ['email' => 'email']);
    $validator->validate();
});

// Division par zéro - Doit être loggée
Route::get('/test/division', function () {
    return 1 / 0;
});
```

### 7.3 Tester

```bash
curl http://localhost:8000/test/exception-500  # Doit logger
curl http://localhost:8000/test/exception-404  # Ne doit PAS logger
curl http://localhost:8000/test/validation     # Ne doit PAS logger
curl http://localhost:8000/test/division       # Doit logger
```

### 7.4 Vérifier

- [ ] Les erreurs 500 sont loggées
- [ ] Les erreurs 404 sont ignorées
- [ ] Les ValidationException sont ignorées
- [ ] Le stack trace est inclus dans le message

---

## 8. Test du stockage en base de données

### 8.1 Vérifier la migration

```bash
php artisan migrate:status
```

Doit montrer la migration `sentinel_events` comme exécutée.

### 8.2 Générer des événements

```bash
curl http://localhost:8000/test/error
curl http://localhost:8000/test/business
curl http://localhost:8000/test/provider
```

### 8.3 Vérifier en base

```bash
php artisan tinker
```

```php
use Outlined\Sentinel\Models\SentinelEvent;

// Compter les événements
SentinelEvent::count();

// Voir les derniers événements
SentinelEvent::latest()->take(5)->get(['id', 'level', 'message', 'event_type', 'created_at']);

// Filtrer par niveau
SentinelEvent::errors()->count();

// Filtrer par type
SentinelEvent::type('business_payment')->get();

// Événements d'aujourd'hui
SentinelEvent::today()->count();
```

### 8.4 Tester la commande prune

```bash
# Voir ce qui serait supprimé
php artisan sentinel:prune --dry-run --days=0

# Supprimer les événements de plus de 7 jours
php artisan sentinel:prune --days=7
```

### 8.5 Vérifier

- [ ] Les événements sont créés en base
- [ ] Le contexte JSON est correctement stocké
- [ ] Les scopes fonctionnent (errors(), today(), etc.)
- [ ] La commande prune fonctionne

---

## 9. Test du dashboard Filament

### 9.1 Installer Filament (si pas déjà installé)

```bash
composer require filament/filament:"^3.0"
php artisan filament:install --panels
```

### 9.2 Créer un utilisateur admin

```bash
php artisan make:filament-user
```

### 9.3 Enregistrer le plugin Sentinel

Modifier `app/Providers/Filament/AdminPanelProvider.php` :

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Outlined\Sentinel\Filament\SentinelPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->plugins([
                SentinelPlugin::make(),
            ])
            // ... reste de la config
            ;
    }
}
```

### 9.4 Accéder au dashboard

1. Démarrer le serveur : `php artisan serve`
2. Aller sur http://localhost:8000/admin
3. Se connecter avec l'utilisateur créé
4. Dans le menu, section "Monitoring"

### 9.5 Vérifier

- [ ] Le menu "Monitoring" apparaît dans la sidebar
- [ ] La page "Dashboard" affiche les statistiques
- [ ] La page "Events" liste tous les événements
- [ ] Les filtres fonctionnent (par niveau, environnement, date)
- [ ] Le détail d'un événement affiche tout le contexte
- [ ] Les graphiques s'affichent correctement

---

## 10. Test du monitoring de ressources

### 10.1 Créer une ressource de test

Créer `app/Monitoring/Resources/TestResource.php` :

```php
<?php

namespace App\Monitoring\Resources;

use Outlined\Sentinel\Resources\AbstractResource;
use Outlined\Sentinel\Resources\ResourceStatus;

class TestResource extends AbstractResource
{
    protected float $warningThreshold = 50;
    protected float $criticalThreshold = 20;
    protected bool $higherIsBetter = true;

    public function getIdentifier(): string
    {
        return 'test_resource';
    }

    public function getName(): string
    {
        return 'Test Resource';
    }

    public function check(): ResourceStatus
    {
        // Simuler une valeur aléatoire
        $value = rand(0, 100);

        return $this->healthy($value, "Current value: {$value}");
    }
}
```

### 10.2 Créer une ressource "réelle" (ex: espace disque)

Créer `app/Monitoring/Resources/DiskSpaceResource.php` :

```php
<?php

namespace App\Monitoring\Resources;

use Outlined\Sentinel\Resources\AbstractResource;
use Outlined\Sentinel\Resources\ResourceStatus;

class DiskSpaceResource extends AbstractResource
{
    protected float $warningThreshold = 20; // 20% libre = warning
    protected float $criticalThreshold = 10; // 10% libre = critical
    protected bool $higherIsBetter = true; // Plus d'espace libre = mieux

    public function getIdentifier(): string
    {
        return 'disk_space';
    }

    public function getName(): string
    {
        return 'Disk Space';
    }

    public function check(): ResourceStatus
    {
        $path = base_path();
        $totalSpace = disk_total_space($path);
        $freeSpace = disk_free_space($path);

        $percentFree = round(($freeSpace / $totalSpace) * 100, 2);

        return $this->healthy(
            $percentFree,
            sprintf(
                "Free: %s / %s (%.2f%%)",
                $this->formatBytes($freeSpace),
                $this->formatBytes($totalSpace),
                $percentFree
            )
        );
    }

    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
```

### 10.3 Enregistrer les ressources

Dans `config/sentinel.php` :

```php
'resources' => [
    \App\Monitoring\Resources\TestResource::class,
    \App\Monitoring\Resources\DiskSpaceResource::class,
],
```

### 10.4 Tester

```bash
# Vérifier toutes les ressources
php artisan sentinel:check-resources

# Vérifier une ressource spécifique
php artisan sentinel:check-resources --resource=disk_space

# Sans envoyer d'alertes
php artisan sentinel:check-resources --no-alert

# En JSON
php artisan sentinel:check-resources --json
```

### 10.5 Vérifier

- [ ] La commande liste toutes les ressources
- [ ] Les statuts sont corrects (healthy/warning/critical)
- [ ] Les alertes sont envoyées si seuils dépassés
- [ ] Le JSON est valide

---

## 11. Test de la déduplication

### 11.1 Configurer la déduplication

Dans `.env` :
```env
SENTINEL_DEDUP_ENABLED=true
SENTINEL_DEDUP_TTL=60  # 60 secondes pour les tests
```

### 11.2 Créer une route de test

```php
// routes/web.php
Route::get('/test/dedup', function () {
    $message = 'Erreur de test pour déduplication - ' . date('Y-m-d H:i');

    // Envoyer 5 fois la même erreur
    for ($i = 1; $i <= 5; $i++) {
        Sentinel::logBusinessEvent(
            'dedup_test',
            false,
            $message,
            ['attempt' => $i]
        );
        echo "Attempt {$i} sent<br>";
    }

    return 'Done! Check Slack - you should only see 1 message.';
});
```

### 11.3 Tester

```bash
curl http://localhost:8000/test/dedup
# Attendre 60 secondes
curl http://localhost:8000/test/dedup
```

### 11.4 Vérifier

- [ ] Seul 1 message arrive sur Slack (pas 5)
- [ ] Après le TTL, un nouveau message peut être envoyé
- [ ] Les événements sont quand même stockés en base (tous les 5)

---

## 12. Test du rate limiting

### 12.1 Configurer le rate limiting

Dans `.env` :
```env
SENTINEL_RATE_LIMIT_ENABLED=true
SENTINEL_RATE_LIMIT_PER_MINUTE=5  # Limite basse pour les tests
SENTINEL_RATE_LIMIT_PER_HOUR=20
```

### 12.2 Créer une route de test

```php
// routes/web.php
Route::get('/test/ratelimit', function () {
    // Désactiver la déduplication temporairement pour ce test
    config(['sentinel.deduplication.enabled' => false]);

    $sent = 0;
    $blocked = 0;

    for ($i = 1; $i <= 10; $i++) {
        Sentinel::logBusinessEvent(
            'rate_test',
            false,
            "Rate limit test message #{$i}",
            ['index' => $i, 'timestamp' => now()->toIso8601String()]
        );
        $sent++;
    }

    return "Attempted to send 10 messages. Check Slack - max 5 should arrive (rate limit).";
});
```

### 12.3 Tester

```bash
curl http://localhost:8000/test/ratelimit
```

### 12.4 Vérifier

- [ ] Maximum 5 messages arrivent (limite par minute)
- [ ] Les messages suivants sont silencieusement ignorés
- [ ] Après 1 minute, on peut renvoyer

---

## 13. Checklist finale

### Installation
- [ ] Package installé via composer
- [ ] Configuration publiée
- [ ] Migrations exécutées

### Notifications
- [ ] Slack fonctionne
- [ ] Discord fonctionne (optionnel)
- [ ] Couleurs et emojis corrects
- [ ] Mentions @ sur alertes critiques

### MonitoringService
- [ ] `logError()` fonctionne
- [ ] `logBusinessEvent()` fonctionne
- [ ] `logProviderError()` fonctionne
- [ ] `logThresholdAlert()` fonctionne
- [ ] Contexte riche inclus (URL, IP, user, etc.)

### Exception Handler
- [ ] Exceptions 500 loggées automatiquement
- [ ] Exceptions 404 ignorées
- [ ] ValidationException ignorées
- [ ] Stack trace inclus

### Base de données
- [ ] Événements stockés
- [ ] Contexte JSON complet
- [ ] Commande prune fonctionne

### Filament Dashboard
- [ ] Plugin enregistré
- [ ] Dashboard avec statistiques
- [ ] Liste des événements
- [ ] Filtres fonctionnels
- [ ] Vue détaillée

### Monitoring de ressources
- [ ] Ressources personnalisées créées
- [ ] Commande check-resources fonctionne
- [ ] Alertes envoyées si seuils dépassés

### Déduplication & Rate Limiting
- [ ] Déduplication empêche le spam
- [ ] Rate limiting fonctionne

---

## Dépannage

### Pas de messages Slack/Discord

1. Vérifier le webhook URL dans `.env`
2. Vérifier que `SENTINEL_SLACK_ENABLED=true`
3. Vérifier les logs Laravel : `tail -f storage/logs/laravel.log`

### Erreurs de migration

```bash
php artisan migrate:fresh  # ATTENTION: supprime toutes les tables
```

### Le package n'est pas détecté

```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### Filament ne montre pas le plugin

1. Vérifier que le plugin est enregistré dans `AdminPanelProvider`
2. Vérifier que `SENTINEL_DATABASE_ENABLED=true`
3. Exécuter `php artisan filament:cache-components`

---

## Commandes utiles

```bash
# Vider le cache
php artisan config:clear
php artisan cache:clear

# Voir la config Sentinel
php artisan tinker
>>> config('sentinel')

# Logs en temps réel
tail -f storage/logs/laravel.log

# Tester rapidement
php artisan sentinel:test --level=error
```

Bonne chance avec vos tests ! 🚀
