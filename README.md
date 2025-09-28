# GitDeploy

[![Latest Version on Packagist](https://img.shields.io/packagist/v/benitoquib/git-deploy.svg?style=flat-square)](https://packagist.org/packages/benitoquib/git-deploy)
[![License](https://img.shields.io/packagist/l/benitoquib/git-deploy.svg?style=flat-square)](https://packagist.org/packages/benitoquib/git-deploy)

Un paquete PHP completo para manejar despliegues automatizados con webhooks de GitLab, perfecto para hostings compartidos y proyectos PHP/Laravel.

## 🚀 Características

- ✅ **Webhooks de GitLab** - Soporte completo para webhooks de GitLab
- 🔐 **Autenticación JWT** - Seguridad robusta con tokens JWT
- 📱 **Notificaciones de Telegram** - Recibe notificaciones en tiempo real
- 🎯 **Deployment Automático** - Ejecuta Composer automáticamente cuando detecta cambios
- 📝 **Logging Completo** - Registro detallado de todas las operaciones
- 🔄 **Rollback** - Capacidad de rollback a commits anteriores
- ⚙️ **Altamente Configurable** - Personaliza cada aspecto del deployment
- 🏢 **Hosting Compartido** - Diseñado especialmente para hostings compartidos

## 📦 Instalación

Como el paquete no se ha subido a Packagist, puedes instalarlo agregando el repositorio VCS directamente en tu `composer.json`:

```json
"repositories": [
  {
    "type": "vcs",
    "url": "https://github.com/benitoquib/git-deploy"
  }
]
```

Luego instala el paquete con Composer:

```bash
composer require benitoquib/git-deploy:dev-main 
```

> **Nota:** Cuando el paquete esté disponible en Packagist, podrás instalarlo directamente con el comando siguiente sin necesidad de agregar el repositorio.

```bash
composer require benitoquib/git-deploy
```

## ⚡ Inicio Rápido

### 1. Configuración Básica

Crea un archivo `.env` en tu proyecto:

```env
# Requerido
GITDEPLOY_JWT_SECRET=tu-clave-secreta-super-segura

# Configuración Git
GITDEPLOY_GIT_BINARY=/usr/bin/git
GITDEPLOY_PROJECT_ROOT=/ruta/a/tu/proyecto

# Telegram (opcional)
GITDEPLOY_TELEGRAM_BOT_TOKEN=tu-token-bot-telegram
GITDEPLOY_TELEGRAM_CHAT_ID=tu-chat-id-telegram

# Deployment (opcional)
GITDEPLOY_DEPLOYMENT_ENABLED=true
GITDEPLOY_AUTO_COMPOSER=true
GITDEPLOY_BACKUP_COMMITS=true
```

### 2. Crear el Endpoint del Webhook

Crea un archivo `webhook.php` en tu directorio web:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use BenitoQuib\GitDeploy\WebhookHandler;
use BenitoQuib\GitDeploy\GitDeployConfig;

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

try {
    $config = GitDeployConfig::fromEnv();
    $handler = new WebhookHandler($config);
    $handler->handle();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

### 3. Configurar GitLab Webhook

En tu proyecto de GitLab:
1. Ve a **Settings > Webhooks**
2. URL: `https://tu-dominio.com/webhook.php`
3. Secret Token: El mismo valor de `GITDEPLOY_JWT_SECRET`
4. Trigger: Marca **Push events**

¡Listo! Ahora cada push a tu repositorio ejecutará automáticamente el deployment.

## 📖 Uso Programático

### Operaciones Git

```php
use BenitoQuib\GitDeploy\Git\GitManager;
use BenitoQuib\GitDeploy\GitDeployConfig;

$config = GitDeployConfig::fromEnv();
$gitManager = new GitManager($config);

// Obtener estado actual
$status = $gitManager->getStatus();
echo "Rama actual: " . $status['branch'];

// Ejecutar pull
$gitManager->pull();

// Obtener log de commits
$log = $gitManager->getCommitLog(10);
```

### Deployment Manual

```php
use BenitoQuib\GitDeploy\Deployment\DeploymentManager;

$deploymentManager = new DeploymentManager($config, $gitManager);

// Ejecutar deployment
$result = $deploymentManager->deploy($forceComposer = true);

if ($result['success']) {
    echo "Deployment exitoso!";
}
```

### Notificaciones de Telegram

```php
use BenitoQuib\GitDeploy\Notifications\TelegramNotifier;

$notifier = new TelegramNotifier($telegramConfig);

// Mensaje simple
$notifier->sendMessage("🚀 Deployment completado!");

// Notificación de error
$notifier->sendErrorNotification($exception, 'Deployment Error');
```

## 🚀 Integración con Laravel

GitDeploy se integra perfectamente con Laravel. Aquí te mostramos cómo configurarlo paso a paso.

### 1. Instalación en Laravel

Como el paquete no se ha subido a Packagist, puedes instalarlo agregando el repositorio VCS directamente en tu `composer.json`:

```json
"repositories": [
  {
    "type": "vcs",
    "url": "https://github.com/benitoquib/git-deploy"
  }
]
```

Luego instala el paquete con Composer:

```bash
composer require benitoquib/git-deploy:dev-main 
```

> **Nota:** Cuando el paquete esté disponible en Packagist, podrás instalarlo directamente con el comando siguiente sin necesidad de agregar el repositorio.

```bash
# En tu proyecto Laravel
composer require benitoquib/git-deploy
```

### 2. Configuración de Variables de Entorno

Agrega estas variables a tu archivo `.env` de Laravel:

```env
# GitDeploy Configuration
GITDEPLOY_JWT_SECRET="${APP_KEY}" # Usa la clave de Laravel o genera una nueva
GITDEPLOY_GIT_BINARY=/usr/bin/git
GITDEPLOY_PROJECT_ROOT="${PWD}" # O usa base_path() programáticamente
GITDEPLOY_TIMEZONE="${APP_TIMEZONE}"

# Telegram (opcional)
GITDEPLOY_TELEGRAM_BOT_TOKEN=tu-token-bot-telegram
GITDEPLOY_TELEGRAM_CHAT_ID=tu-chat-id-telegram

# Deployment específico para Laravel
GITDEPLOY_DEPLOYMENT_ENABLED=true
GITDEPLOY_AUTO_COMPOSER=true
GITDEPLOY_CLEAR_CACHE=true
GITDEPLOY_FIX_PERMISSIONS=false
```

### 3. Crear el Controller

Genera un controller para manejar los webhooks:

```bash
php artisan make:controller DeploymentController
```

```php
<?php
// app/Http/Controllers/DeploymentController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use BenitoQuib\GitDeploy\WebhookHandler;
use BenitoQuib\GitDeploy\GitDeployConfig;
use Illuminate\Support\Facades\Log;

class DeploymentController extends Controller
{
    public function webhook(Request $request): JsonResponse
    {
        try {
            $config = GitDeployConfig::getInstance([
                'jwt_secret' => config('app.key'),
                'project_root' => base_path(),
                'git_binary' => env('GITDEPLOY_GIT_BINARY', '/usr/bin/git'),
                'timezone' => config('app.timezone'),
                'telegram' => [
                    'bot_token' => env('GITDEPLOY_TELEGRAM_BOT_TOKEN'),
                    'chat_id' => env('GITDEPLOY_TELEGRAM_CHAT_ID'),
                    'enabled' => !empty(env('GITDEPLOY_TELEGRAM_BOT_TOKEN')),
                ],
                'deployment' => [
                    'enabled' => env('GITDEPLOY_DEPLOYMENT_ENABLED', true),
                    'auto_composer' => env('GITDEPLOY_AUTO_COMPOSER', true),
                    'clear_cache' => env('GITDEPLOY_CLEAR_CACHE', true),
                    'custom_script' => base_path('deployment-script.sh'), // Opcional
                ]
            ]);
            
            $handler = new WebhookHandler($config);
            
            // Capturar la salida
            ob_start();
            $handler->handle();
            $output = ob_get_clean();
            
            $result = json_decode($output, true) ?? ['success' => true];
            
            // Log del deployment
            Log::info('GitDeploy webhook executed', [
                'result' => $result,
                'user_agent' => $request->header('User-Agent'),
                'ip' => $request->ip()
            ]);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('GitDeploy webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'error' => 'Deployment failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
```

### 4. Configurar las Rutas

Agrega la ruta en `routes/web.php` o `routes/api.php`:

```php
<?php
// routes/web.php o routes/api.php

use App\Http\Controllers\DeploymentController;

// Webhook público (sin middleware de autenticación)
Route::post('/webhook/deploy', [DeploymentController::class, 'webhook'])
  ->name('deployment.webhook');

// O si prefieres con middleware personalizado:
Route::post('/webhook/deploy', [DeploymentController::class, 'webhook'])
  ->middleware(['throttle:60,1']) // Limitar a 60 requests por minuto
  ->name('deployment.webhook');
```

> **Nota:** Si tienes problemas de CSRF con el webhook, agrega la ruta a las excepciones de CSRF.  
> En Laravel 12, esto se configura en `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
  $middleware->validateCsrfTokens(except: [
    '/webhook/*',
  ]);
})
```

### 5. Comando Artisan para Deployment Manual

Crea un comando personalizado para deployments manuales:

```bash
php artisan make:command DeployCommand
```

```php
<?php
// app/Console/Commands/DeployCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use BenitoQuib\GitDeploy\Git\GitManager;
use BenitoQuib\GitDeploy\Deployment\DeploymentManager;
use BenitoQuib\GitDeploy\GitDeployConfig;
use BenitoQuib\GitDeploy\Notifications\TelegramNotifier;

class DeployCommand extends Command
{
    protected $signature = 'deploy:run 
                            {--force-composer : Force composer install}
                            {--no-cache : Skip cache clearing}
                            {--notify : Send Telegram notification}';
    
    protected $description = 'Execute manual deployment with GitDeploy';
    
    public function handle()
    {
        $this->info('🚀 Starting Laravel deployment with GitDeploy...');
        
        try {
            // Configuración
            $config = GitDeployConfig::getInstance([
                'jwt_secret' => config('app.key'),
                'project_root' => base_path(),
                'git_binary' => env('GITDEPLOY_GIT_BINARY', '/usr/bin/git'),
                'telegram' => [
                    'bot_token' => env('GITDEPLOY_TELEGRAM_BOT_TOKEN'),
                    'chat_id' => env('GITDEPLOY_TELEGRAM_CHAT_ID'),
                ],
                'deployment' => [
                    'clear_cache' => !$this->option('no-cache'),
                    'enabled' => true,
                ]
            ]);
            
            $gitManager = new GitManager($config);
            $deploymentManager = new DeploymentManager($config, $gitManager);
            
            // Mostrar estado actual
            $this->info('📊 Estado actual:');
            $status = $gitManager->getStatus();
            $this->line("   Rama: {$status['branch']}");
            $this->line("   Commit: " . substr($status['commit'], 0, 8));
            $this->line("   Estado: " . ($status['clean'] ? 'Limpio' : 'Con cambios'));
            
            // Ejecutar pull
            $this->info('📥 Ejecutando git pull...');
            $gitManager->pull();
            $this->info('   ✅ Pull completado');
            
            // Ejecutar deployment
            $this->info('📦 Ejecutando deployment...');
            $result = $deploymentManager->deploy($this->option('force-composer'));
            
            if ($result['success']) {
                $this->info('✅ Deployment exitoso!');
                
                if ($result['composer_changes']) {
                    $this->line('   📦 Composer packages actualizados');
                    $composerTime = $result['composer_install']['execution_time'] ?? 'N/A';
                    $this->line("   ⏱️  Tiempo de Composer: {$composerTime}s");
                }
                
                $this->line("   ⏱️ Tiempo total: {$result['execution_time']}s");
                
                // Limpiar cache de Laravel
                if (!$this->option('no-cache')) {
                    $this->info('🧹 Limpiando cache de Laravel...');
                    $this->call('cache:clear');
                    $this->call('config:clear');
                    $this->call('view:clear');
                    $this->call('route:clear');
                    $this->info('   ✅ Cache limpiado');
                }
                
                // Notificación Telegram
                if ($this->option('notify') && $config->isTelegramEnabled()) {
                    $notifier = new TelegramNotifier($config->getTelegramConfig());
                    $notifier->sendMessage(
                        "*🚀 Laravel Deployment Manual*\n\n" .
                        "*Estado:* ✅ Exitoso\n" .
                        "*Rama:* `{$status['branch']}`\n" .
                        "*Tiempo:* `{$result['execution_time']}s`\n" .
                        "*Ejecutado por:* Comando Artisan"
                    );
                    $this->info('📱 Notificación enviada a Telegram');
                }
                
            } else {
                $this->error('❌ Deployment falló: ' . $result['error']);
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error('💥 Error: ' . $e->getMessage());
            \Log::error('Manual deployment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
        
        $this->info('🎉 Deployment completado exitosamente!');
        return 0;
    }
}
```

### 6. Script de Deployment Personalizado (Opcional)

Crea un script `deployment-script.sh` en la raíz de tu proyecto Laravel:

```bash
#!/bin/bash
# deployment-script.sh - Script personalizado para Laravel

echo "🚀 Ejecutando deployment personalizado de Laravel..."

# Optimizar autoload de Composer
composer dump-autoload --optimize --no-dev

# Migrar base de datos (cuidado en producción)
# php artisan migrate --force

# Optimizar configuración
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Vincular storage si es necesario
php artisan storage:link

# Optimizar para producción
php artisan optimize

echo "✅ Deployment personalizado completado"
```

### 7. Configurar GitLab Webhook

En tu proyecto de GitLab:
1. **URL del Webhook:** `https://tu-laravel-app.com/webhook/deploy`
2. **Secret Token:** El valor de `GITDEPLOY_JWT_SECRET`
3. **Triggers:** Push events, Merge request events

### 8. Uso en Código

```php
<?php
// En cualquier parte de tu aplicación Laravel

use BenitoQuib\GitDeploy\GitDeployConfig;
use BenitoQuib\GitDeploy\Git\GitManager;

// En un Job, Service, o Controller
class DeploymentService
{
    public function getDeploymentStatus()
    {
        $config = GitDeployConfig::fromEnv();
        $gitManager = new GitManager($config);
        
        return [
            'current_branch' => $gitManager->getCurrentBranch(),
            'last_commit' => $gitManager->getLastCommitInfo(),
            'status' => $gitManager->getStatus(),
            'is_clean' => $gitManager->isWorkingDirectoryClean(),
        ];
    }
}

// En un Controller para dashboard
public function deploymentStatus()
{
    $service = new DeploymentService();
    return response()->json($service->getDeploymentStatus());
}
```

### 9. Comandos Útiles

```bash
# Deployment manual
php artisan deploy:run

# Deployment con composer forzado
php artisan deploy:run --force-composer

# Deployment sin limpiar cache
php artisan deploy:run --no-cache

# Deployment con notificación
php artisan deploy:run --notify

# Ver logs de deployment
tail -f storage/logs/laravel.log | grep GitDeploy
```

### 10. Middleware de Seguridad (Opcional)

```php
<?php
// app/Http/Middleware/GitDeploySecurityMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class GitDeploySecurityMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Verificar IP (opcional)
        $allowedIps = config('gitdeploy.allowed_ips', []);
        if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps)) {
            abort(403, 'IP not allowed');
        }
        
        // Verificar User-Agent de GitLab (opcional)
        $userAgent = $request->header('User-Agent');
        if (!str_contains($userAgent, 'GitLab')) {
            \Log::warning('Non-GitLab user agent in deployment webhook', [
                'user_agent' => $userAgent,
                'ip' => $request->ip()
            ]);
        }
        
        return $next($request);
    }
}
```

¡Listo! Con esta configuración tendrás GitDeploy perfectamente integrado en tu aplicación Laravel. 🚀

## 🎯 Acciones Disponibles

### Via API REST

Envía requests POST con JWT token:

```bash
# Pull + Deployment
curl -X POST https://tu-dominio.com/webhook.php \
  -H "Authorization: Bearer tu-jwt-token" \
  -H "Content-Type: application/json" \
  -d '{"action": "pull"}'

# Reset a commit específico
curl -X POST https://tu-dominio.com/webhook.php \
  -H "Authorization: Bearer tu-jwt-token" \
  -H "Content-Type: application/json" \
  -d '{"action": "reset", "commit_id": "abc123"}'

# Ver log de commits
curl -X POST https://tu-dominio.com/webhook.php \
  -H "Authorization: Bearer tu-jwt-token" \
  -H "Content-Type: application/json" \
  -d '{"action": "log"}'

# Deployment manual
curl -X POST https://tu-dominio.com/webhook.php \
  -H "Authorization: Bearer tu-jwt-token" \
  -H "Content-Type: application/json" \
  -d '{"action": "deploy", "force_composer": true}'
```

## � Mejores Prácticas para Variables de Entorno

### ¿Por qué usar prefijo GITDEPLOY_?

1. **Evita conflictos**: Previene sobrescribir variables de otras librerías
2. **Claridad**: Es obvio qué variables pertenecen a GitDeploy
3. **Organización**: Facilita la gestión de configuraciones complejas
4. **Estándares**: Sigue las mejores prácticas de la industria

### Migración de Variables Antiguas

Si ya usas variables sin prefijo, GitDeploy las seguirá respetando pero mostrará avisos de deprecación:

```bash
# ⚠️ Formato anterior (funciona pero deprecated)
JWT_SECRET=mi-secreto
TELEGRAM_BOT_TOKEN=mi-token

# ✅ Nuevo formato (recomendado)
GITDEPLOY_JWT_SECRET=mi-secreto
GITDEPLOY_TELEGRAM_BOT_TOKEN=mi-token
```

### Prioridad de Variables

GitDeploy busca las variables en este orden:
1. `GITDEPLOY_*` (prioridad alta)
2. Variables sin prefijo (compatibilidad)
3. Valores por defecto

### Generación de JWT_SECRET Seguro

```bash
# Opción 1: OpenSSL
openssl rand -base64 32

# Opción 2: PHP
php -r "echo base64_encode(random_bytes(32));"

# Opción 3: Online (solo para desarrollo)
# https://generate-secret.vercel.app/32
```

## �🔧 Configuración para Hostings Compartidos

### cPanel/Shared Hosting

```env
# Típicamente en cPanel:
GIT_BINARY=/usr/local/cpanel/3rdparty/lib/path-bin/git
PROJECT_ROOT=/home/usuario/public_html
FIX_PERMISSIONS=true
```

### Obtener Token de Telegram

1. Busca **@BotFather** en Telegram
2. Envía `/newbot` y sigue las instrucciones
3. Guarda el token que te proporciona
4. Para obtener tu Chat ID, busca **@userinfobot** y envía `/start`

## 📊 Respuestas de la API

### Respuesta Exitosa (Pull)
```json
{
    "action": "pull",
    "stash": ["Saved working directory and index state WIP on main"],
    "pull": "Pull successful",
    "deployment": {
        "success": true,
        "composer_changes": true,
        "composer_install": {
            "success": true,
            "execution_time": 12.5
        }
    },
    "next_token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

## 📝 Variables de Entorno

**Nuevas variables (recomendadas):**

| Variable | Requerido | Descripción |
|----------|-----------|-------------|
| `GITDEPLOY_JWT_SECRET` | ✅ | Clave secreta para JWT |
| `GITDEPLOY_GIT_BINARY` | ❌ | Ruta al binario de Git |
| `GITDEPLOY_PROJECT_ROOT` | ❌ | Ruta raíz del proyecto |
| `GITDEPLOY_TELEGRAM_BOT_TOKEN` | ❌ | Token del bot de Telegram |
| `GITDEPLOY_TELEGRAM_CHAT_ID` | ❌ | ID del chat de Telegram |
| `GITDEPLOY_DEPLOYMENT_ENABLED` | ❌ | Habilitar deployment automático |
| `GITDEPLOY_AUTO_COMPOSER` | ❌ | Ejecutar composer automáticamente |
| `GITDEPLOY_BACKUP_COMMITS` | ❌ | Hacer backup de commits |
| `GITDEPLOY_CLEAR_CACHE` | ❌ | Limpiar cache después del deployment |
| `GITDEPLOY_FIX_PERMISSIONS` | ❌ | Corregir permisos de archivos |
| `GITDEPLOY_VALIDATE_GITLAB_IPS` | ❌ | Validar IPs de GitLab |

**Variables antiguas (compatibilidad hacia atrás - deprecated):**

| Variable | Estado | Migrar a |
|----------|--------|----------|
| `JWT_SECRET` | ⚠️ Deprecated | `GITDEPLOY_JWT_SECRET` |
| `GIT_BINARY` | ⚠️ Deprecated | `GITDEPLOY_GIT_BINARY` |
| `PROJECT_ROOT` | ⚠️ Deprecated | `GITDEPLOY_PROJECT_ROOT` |
| `TELEGRAM_BOT_TOKEN` | ⚠️ Deprecated | `GITDEPLOY_TELEGRAM_BOT_TOKEN` |
| `TELEGRAM_CHAT_ID` | ⚠️ Deprecated | `GITDEPLOY_TELEGRAM_CHAT_ID` |

> **⚠️ Importante:** Las variables sin prefijo se eliminarán en la v2.0.0. Usa las versiones con prefijo `GITDEPLOY_` para evitar conflictos con otras librerías.

## 🔧 Troubleshooting Laravel

### Problema: "Class not found" después del deployment

**Causa:** El autoload de Composer no se actualizó después de agregar nuevas clases.

**Solución:**
```bash
# En tu script de deployment
composer dump-autoload --optimize

# O en tu comando artisan
php artisan deploy:run --force-composer
```

### Problema: Cache no se limpia automáticamente

**Causa:** Laravel mantiene cache de configuración, rutas y vistas.

**Solución:**
```php
// En tu DeploymentController
$result = $deploymentManager->deploy();

if ($result['success']) {
    // Limpiar cache de Laravel manualmente
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('view:clear');
    Artisan::call('route:clear');
}
```

### Problema: Permisos de archivos en hosting compartido

**Causa:** Los archivos nuevos pueden tener permisos incorrectos.

**Solución:**
```env
# En tu .env
GITDEPLOY_FIX_PERMISSIONS=true
```

### Problema: Migraciones no se ejecutan automáticamente

**Causa:** Las migraciones son peligrosas en producción y no se ejecutan por defecto.

**Solución:**
```bash
# Crear script personalizado deployment-script.sh
#!/bin/bash
echo "Ejecutando migraciones..."
php artisan migrate --force
echo "Migraciones completadas"
```

### Problema: Variables de entorno no se cargan

**Causa:** Laravel tiene su propio sistema de variables de entorno.

**Solución:**
```php
// Usar configuración directa en lugar de fromEnv()
$config = GitDeployConfig::getInstance([
    'jwt_secret' => config('app.key'),
    'project_root' => base_path(),
    'git_binary' => env('GITDEPLOY_GIT_BINARY', '/usr/bin/git'),
    // ... más configuración
]);
```

### Problema: Webhook no funciona en Laravel con CSRF

**Causa:** Laravel protege todas las rutas POST con token CSRF.

**Solución:**
```php
// En app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'webhook/deploy', // Agregar tu ruta de webhook
];
```

### Problema: Logs no aparecen en Laravel

**Causa:** GitDeploy usa error_log() por defecto, no el sistema de logs de Laravel.

**Solución:**
```php
// En tu controller, loggear manualmente
use Illuminate\Support\Facades\Log;

try {
    $handler->handle();
    Log::info('GitDeploy webhook executed successfully');
} catch (\Exception $e) {
    Log::error('GitDeploy webhook failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
```

### Problema: Performance lenta en deployments

**Causa:** Composer install sin optimizaciones.

**Solución:**
```bash
# En tu script de deployment
composer install --no-dev --optimize-autoloader --classmap-authoritative
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Problema: Base de datos desconectada después del deployment

**Causa:** Laravel puede mantener conexiones activas que se invalidan.

**Solución:**
```php
// En tu comando de deployment
use Illuminate\Support\Facades\DB;

// Después del deployment
DB::purge(); // Limpiar conexiones
```

### Verificar configuración

Para verificar que GitDeploy está configurado correctamente en Laravel:

```bash
# Crear un comando de diagnóstico
php artisan make:command GitDeployDiagnostic
```

```php
// En el comando
public function handle()
{
    $this->info('🔍 Diagnóstico de GitDeploy en Laravel');
    
    // Verificar configuración
    $config = GitDeployConfig::fromEnv();
    $this->line('JWT Secret: ' . (strlen($config->getJwtSecret()) > 0 ? '✅' : '❌'));
    $this->line('Git Binary: ' . (file_exists($config->getGitBinary()) ? '✅' : '❌'));
    $this->line('Project Root: ' . (is_dir($config->getProjectRoot()) ? '✅' : '❌'));
    $this->line('Telegram: ' . ($config->isTelegramEnabled() ? '✅' : '❌'));
}
```

## 🏆 Mejores Prácticas para Deployment

### Configuración de Producción Robusta

```bash
# Script de deployment completo (production-deploy.sh)
#!/bin/bash
set -e  # Terminar si cualquier comando falla

echo "🚀 Iniciando deployment..."

# Backup de archivos críticos
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)

# Modo de mantenimiento (si usas Laravel)
php artisan down --message="Actualizando sistema..." --retry=60

# Git operations
git pull origin main

# Composer dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# Cache optimization
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Database migrations (opcional y peligroso)
# php artisan migrate --force

# Fix permissions
find storage -type d -exec chmod 755 {} \;
find storage -type f -exec chmod 644 {} \;

# Restart services if needed
# sudo systemctl reload php8.2-fpm

# Salir del modo de mantenimiento
php artisan up

echo "✅ Deployment completado exitosamente"
```

### Configuración de Seguridad

```env
# Variables de seguridad recomendadas
GITDEPLOY_JWT_SECRET="your-super-secret-jwt-key-here"
GITDEPLOY_ALLOWED_IPS="54.230.24.0/24,54.239.132.0/22"  # IPs de GitLab
GITDEPLOY_MAX_DEPLOYMENT_TIME=300  # 5 minutos máximo
GITDEPLOY_REQUIRE_TOKEN=true
GITDEPLOY_LOG_LEVEL=WARNING  # Solo errores y warnings en producción
```

### Testing en Staging

```bash
# Script para testing automático antes de deployment
#!/bin/bash
echo "🧪 Ejecutando tests antes del deployment..."

# Tests unitarios
vendor/bin/phpunit

# Tests de integración
php artisan test --testsuite=Feature

# Análisis estático
vendor/bin/phpstan analyse

# Check de seguridad
composer audit

echo "✅ Todos los tests pasaron. Procediendo con deployment..."
```

### Rollback Automático

```php
// Sistema de rollback automático
class AutoRollbackDeployment
{
    public function deployWithRollback()
    {
        $backupBranch = 'backup-' . date('Y-m-d-H-i-s');
        
        try {
            // Crear backup del estado actual
            $this->gitManager->createBranch($backupBranch);
            
            // Intentar deployment
            $result = $this->deploymentManager->deploy();
            
            // Verificar que el sitio funcione
            if (!$this->healthCheck()) {
                throw new \Exception('Health check failed after deployment');
            }
            
            return $result;
            
        } catch (\Exception $e) {
            // Rollback automático
            $this->gitManager->checkout($backupBranch);
            
            // Notificar del rollback
            $this->notifier->sendErrorNotification(
                "Deployment falló. Rollback automático ejecutado. Error: " . $e->getMessage()
            );
            
            throw $e;
        }
    }
    
    private function healthCheck(): bool
    {
        // Verificar que la aplicación responda correctamente
        $response = @file_get_contents($this->config->getBaseUrl() . '/health');
        return $response !== false && strpos($response, 'OK') !== false;
    }
}
```

### Monitoreo y Logging

```php
// Middleware de monitoreo personalizado
class DeploymentMonitoringMiddleware
{
    public function handle($request, Closure $next)
    {
        $startTime = microtime(true);
        $memoryStart = memory_get_usage();
        
        try {
            $response = $next($request);
            
            // Log de métricas exitosas
            Log::info('Deployment metrics', [
                'execution_time' => microtime(true) - $startTime,
                'memory_usage' => memory_get_usage() - $memoryStart,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            // Notificar fallos inmediatos
            Log::error('Deployment failed', [
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime,
                'ip' => $request->ip()
            ]);
            
            throw $e;
        }
    }
}
```

### Configuración de Backup y Recuperación

```bash
# Script de backup antes del deployment
#!/bin/bash
BACKUP_DIR="/backups/$(date +%Y%m%d_%H%M%S)"
mkdir -p $BACKUP_DIR

# Backup de archivos
rsync -av --exclude='.git/' --exclude='node_modules/' . $BACKUP_DIR/

# Backup de base de datos (si aplica)
mysqldump -u usuario -p base_datos > $BACKUP_DIR/database.sql

echo "Backup creado en: $BACKUP_DIR"
```

### Health Check Endpoint

```php
// Crear endpoint de health check
Route::get('/health', function () {
    try {
        // Verificar conexión a base de datos
        DB::connection()->getPdo();
        
        // Verificar archivos críticos
        $criticalFiles = ['.env', 'composer.json', 'composer.lock'];
        foreach ($criticalFiles as $file) {
            if (!file_exists(base_path($file))) {
                throw new Exception("Missing critical file: $file");
            }
        }
        
        return response()->json([
            'status' => 'OK',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0')
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'status' => 'ERROR',
            'message' => $e->getMessage(),
            'timestamp' => now()->toISOString()
        ], 500);
    }
});
```

## 🤝 Contribuir

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

Este proyecto está licenciado bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para más detalles.

## 👨‍💻 Autor

**Benito Quib Che**
- GitHub: [@benitoquib](https://github.com/benitoquib)
- Email: benitoquib98@gmail.com

## ⭐ Soporte

Si este paquete te ha sido útil, considera darle una estrella ⭐ en GitHub!

Para reportar bugs o solicitar features, abre un [issue](https://github.com/benitoquib/git-deploy/issues).

---

**¿Necesitas ayuda con la configuración?** Abre un issue y te ayudaremos 🚀