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
- 🔐 **Autenticación JWT** para llamadas API manuales
- 📱 **Notificaciones Telegram** de deployments
- 🎯 **Soporte Composer** con instalación automática
- 📝 **Logging completo** de operaciones
- ⚡ **Fácil configuración** con variables de entorno
- 🛡️ **Seguridad incorporada** con validación de tokens
- 🔄 **Rollback** a commits específicos

## 📦 Instalación

```bash
composer require benitoquib/git-deploy
```

## 🚀 Configuración Rápida

### 1. Crear archivo webhook

Crea `webhook.php` en la raíz de tu proyecto:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use BenitoQuib\GitDeploy\GitDeployConfig;
use BenitoQuib\GitDeploy\WebhookHandler;

try {
    $config = GitDeployConfig::fromEnv(__DIR__ . '/.env');
    $handler = new WebhookHandler($config);
    $result = $handler->handle();
    $handler->respond($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

### 2. Configurar variables de entorno

Copia `.env.example` a `.env` y ajusta:

```bash
# Requerido
GITDEPLOY_JWT_SECRET=tu_secreto_super_seguro
GITDEPLOY_GITLAB_TOKEN=tu_token_webhook

# Telegram (opcional pero recomendado)
GITDEPLOY_TELEGRAM_ENABLED=true
GITDEPLOY_TELEGRAM_BOT_TOKEN=tu_bot_token
GITDEPLOY_TELEGRAM_CHAT_ID=tu_chat_id

# Deployment
GITDEPLOY_DEPLOYMENT_ENABLED=true
GITDEPLOY_COMPOSER_INSTALL=true
```

### 3. Configurar GitLab Webhook

En tu proyecto de GitLab:
1. Ve a **Settings > Webhooks**
2. URL: `https://tudominio.com/webhook.php`
3. Secret Token: Mismo valor que `GITDEPLOY_GITLAB_TOKEN`
4. Trigger events: `Push events`
5. Guarda y prueba

## 🎯 Uso

### Webhook Automático (GitLab)

El webhook se activa automáticamente en cada push:

```
POST /webhook.php
Headers:
  X-Gitlab-Event: Push Hook
  X-Gitlab-Token: tu_token
```

### API Manual

Para operaciones manuales necesitas un token JWT:

```bash
# Generar token (necesitas implementar endpoint)
curl -X POST https://tudominio.com/generate-token.php

# Hacer pull manual
curl -X POST https://tudominio.com/webhook.php \
  -H "Authorization: Bearer TU_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"action": "pull"}'
```

### Acciones Disponibles

```php
// Pull del repositorio
{"action": "pull"}

// Reset a commit específico
{"action": "reset", "commit_id": "abc123"}

// Ver log de commits
{"action": "log", "limit": 10}

// Deployment manual
{"action": "deploy", "force": true}

// Estado del repositorio
{"action": "status"}
```

## ⚙️ Configuración Avanzada

### Personalización Completa

```php
use BenitoQuib\GitDeploy\GitDeployConfig;
use BenitoQuib\GitDeploy\WebhookHandler;

$config = new GitDeployConfig([
    'git_binary' => '/usr/local/bin/git',
    'project_root' => __DIR__,
    'jwt_secret' => 'mi-secreto',
    
    'deployment' => [
        'enabled' => true,
        'composer_install' => true,
        'composer_optimize' => true,
        'run_migrations' => true, // Laravel
        'clear_cache' => true,
        'custom_commands' => [
            'php artisan config:cache',
            'php artisan route:cache',
        ]
    ],
    
    'telegram' => [
        'enabled' => true,
        'bot_token' => 'tu-bot-token',
        'chat_id' => 'tu-chat-id'
    ],
    
    'allowed_branches' => ['main', 'production'],
    'allowed_ips' => ['192.168.1.1', '10.0.0.0/8'],
]);

$handler = new WebhookHandler($config);
```

### Solo para Laravel

```php
// En tu RouteServiceProvider o web.php
Route::post('/deploy', function () {
    $config = GitDeployConfig::fromEnv(base_path('.env'));
    $config->set('deployment.run_migrations', true);
    $config->set('deployment.clear_cache', true);
    $config->set('deployment.custom_commands', [
        'php artisan config:cache',
        'php artisan route:cache',
        'php artisan view:cache',
    ]);
    
    $handler = new WebhookHandler($config);
    return response()->json($handler->handle());
});
```

## 🔒 Seguridad

### Recomendaciones

1. **Usar HTTPS** siempre en producción
2. **Configurar IPs permitidas** si es posible
3. **Rotar tokens** periódicamente
4. **Monitorear logs** de acceso
5. **Limitar ramas** de deployment

### Ejemplo con Validación de IP

```bash
# En .env
GITDEPLOY_ALLOWED_IPS=192.168.1.100,203.0.113.0/24
```

## 📱 Telegram Setup

### 1. Crear Bot

1. Habla con [@BotFather](https://t.me/BotFather)
2. Envía `/newbot` y sigue instrucciones
3. Guarda el token que te da

### 2. Obtener Chat ID

```bash
# Envía un mensaje a tu bot, luego:
curl https://api.telegram.org/bot<TOKEN>/getUpdates
# Busca "chat":{"id": NUMERO }
```

### 3. Configurar

```bash
GITDEPLOY_TELEGRAM_ENABLED=true
GITDEPLOY_TELEGRAM_BOT_TOKEN=1234567890:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
GITDEPLOY_TELEGRAM_CHAT_ID=-1001234567890
```

## 🏗️ Estructura del Paquete

```
src/
├── Auth/
│   ├── JwtAuthenticator.php       # Manejo de JWT
│   └── GitLabAuthenticator.php    # Validación webhooks GitLab
├── Git/
│   └── GitManager.php             # Operaciones Git
├── Deployment/
│   └── DeploymentManager.php      # Lógica de deployment
├── Notifications/
│   └── TelegramNotifier.php       # Notificaciones Telegram
├── Exceptions/
│   └── GitDeployException.php     # Excepciones personalizadas
├── GitDeployConfig.php            # Configuración
└── WebhookHandler.php             # Handler principal
```

## 🛠️ Para Diferentes Hostings

### cPanel/Shared Hosting

```bash
# Típicamente el git está en:
GITDEPLOY_GIT_BINARY=/usr/local/cpanel/3rdparty/lib/path-bin/git

# O también puede ser:
GITDEPLOY_GIT_BINARY=/usr/bin/git
```

### VPS/Dedicated

```bash
GITDEPLOY_GIT_BINARY=/usr/bin/git
# O donde esté instalado git
```

### Verificar ubicación de Git

```bash
which git
# O
whereis git
```

## 🔍 Troubleshooting

### Problema: Git binary not found

```bash
# Verificar ubicación
which git
whereis git

# Actualizar en .env
GITDEPLOY_GIT_BINARY=/ruta/correcta/al/git
```

### Problema: Permisos de escritura

```bash
# El usuario web debe tener permisos en el directorio
chown -R www-data:www-data /path/to/project
chmod -R 755 /path/to/project
```

### Problema: Telegram no funciona

1. Verificar token del bot
2. Verificar chat ID
3. Verificar que el bot esté en el chat/grupo
4. Revisar logs: `tail -f git-deploy.log`

### Problema: JWT expired

Los tokens JWT expiran en 1 hora. Generar nuevo token:

```bash
curl -X POST https://tudominio.com/generate-token.php
```

## 📊 Logging

El paquete registra todas las operaciones:

```bash
# Ver logs
tail -f git-deploy.log

# Logs más detallados
GITDEPLOY_LOG_LEVEL=debug
```

### Ejemplo de log

```
[2024-01-15 10:30:00] git-deploy.INFO: Processing action: pull
[2024-01-15 10:30:01] git-deploy.INFO: Stash created: stash@{0}
[2024-01-15 10:30:02] git-deploy.INFO: Pull successful: Already up to date.
[2024-01-15 10:30:03] git-deploy.INFO: Deployment started
[2024-01-15 10:30:05] git-deploy.INFO: Composer install completed
[2024-01-15 10:30:06] git-deploy.INFO: Telegram notification sent
```

## 🚨 Casos de Uso

### 1. Proyecto PHP Simple

```php
// webhook.php básico
$config = GitDeployConfig::fromEnv();
$config->set('deployment.enabled', false); // Solo git pull
$handler = new WebhookHandler($config);
```

### 2. Laravel con Deployment Completo

```php
$config = GitDeployConfig::fromEnv();
$config->set('deployment.enabled', true);
$config->set('deployment.run_migrations', true);
$config->set('deployment.clear_cache', true);
$config->set('deployment.custom_commands', [
    'php artisan config:cache',
    'php artisan route:cache',
    'php artisan queue:restart'
]);
```

### 3. WordPress con Composer

```php
$config = GitDeployConfig::fromEnv();
$config->set('deployment.enabled', true);
$config->set('deployment.composer_install', true);
$config->set('deployment.custom_commands', [
    'wp cache flush'
]);
```

## 🤝 Contribuir

1. Fork el proyecto
2. Crea una rama: `git checkout -b feature/nueva-funcionalidad`
3. Commit: `git commit -am 'Agregar nueva funcionalidad'`
4. Push: `git push origin feature/nueva-funcionalidad`
5. Pull Request

## 📄 Licencia

MIT License. Ver [LICENSE](LICENSE) para detalles.

## 🆘 Soporte

- 🐛 [Issues](https://github.com/tuusuario/git-deploy/issues)
- 💬 [Discussions](https://github.com/tuusuario/git-deploy/discussions)
- 📧 Email: tu@email.com

## 🏷️ Changelog

### v1.0.0
- ✅ Soporte inicial para webhooks GitLab
- ✅ Autenticación JWT
- ✅ Notificaciones Telegram
- ✅ Deployment automático
- ✅ Logging completo

---

**¡Hecho con ❤️ para la comunidad de desarrolladores!**