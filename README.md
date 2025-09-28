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