# Configuración del Sistema de Reset de Contraseña

## Funcionalidades Implementadas

✅ **Backend (Laravel)**
- Rutas API para forgot-password y reset-password
- Métodos en AuthController para manejar el reset de contraseña
- Notificación personalizada para emails de reset
- Configuración del modelo User para usar la notificación personalizada
- Rutas web para manejar los enlaces de reset

✅ **Frontend (Vue.js)**
- Vista de "Olvidé mi contraseña" (ForgotPassword.vue)
- Vista de "Restablecer contraseña" (ResetPassword.vue)
- Enlace "¿Olvidaste tu contraseña?" en el formulario de login
- Rutas configuradas en Vue Router
- Validaciones de formularios
- Mensajes de éxito y error con toastifications

## Configuración Requerida

### 1. Configuración de Email

Edita tu archivo `.env` y configura el email según tu proveedor:

#### Para AWS SES (Recomendado para producción):
```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=tu-access-key-id
AWS_SECRET_ACCESS_KEY=tu-secret-access-key
AWS_DEFAULT_REGION=us-east-1
MAIL_FROM_ADDRESS=noreply@tu-dominio.com
MAIL_FROM_NAME="Sistema de Gestión Minera"
```

#### Para desarrollo local (emails se guardan en logs):
```env
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@minera.com
MAIL_FROM_NAME="Sistema de Gestión Minera"
```

#### Para Gmail:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-email@gmail.com
MAIL_PASSWORD=tu-contraseña-de-aplicacion
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu-email@gmail.com
MAIL_FROM_NAME="Sistema de Gestión Minera"
```

#### Para Mailgun:
```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=tu-dominio.mailgun.org
MAILGUN_SECRET=tu-secret-key
MAIL_FROM_ADDRESS=noreply@tu-dominio.com
MAIL_FROM_NAME="Sistema de Gestión Minera"
```

### 2. Configuración de la Base de Datos

Asegúrate de que la tabla `password_resets` esté creada:

```bash
php artisan migrate
```

### 3. Configuración de la URL de la Aplicación

En tu archivo `.env`, asegúrate de que la URL de la aplicación esté configurada:

```env
APP_URL=http://localhost:8000
```

## Cómo Funciona

### 1. Usuario solicita reset de contraseña
- El usuario hace clic en "¿Olvidaste tu contraseña?" en el login
- Se redirige a `/forgot-password`
- Ingresa su email y hace clic en "Enviar enlace de restablecimiento"
- Se envía una petición POST a `/api/auth/forgot-password`

### 2. Sistema envía email
- Laravel genera un token único
- Se envía un email con el enlace de reset
- El enlace apunta a `/reset-password/{token}?email={email}`

### 3. Usuario restablece contraseña
- El usuario hace clic en el enlace del email
- Se redirige a la vista de reset de contraseña
- Ingresa su nueva contraseña y confirmación
- Se envía una petición POST a `/api/auth/reset-password`
- Se actualiza la contraseña en la base de datos
- Se redirige al login con mensaje de éxito

## Archivos Modificados/Creados

### Backend
- `routes/api.php` - Agregadas rutas de reset de contraseña
- `routes/web.php` - Agregadas rutas web para reset
- `app/Http/Controllers/AuthController.php` - Agregados métodos forgotPassword y resetPassword
- `app/Notifications/ResetPasswordNotification.php` - Notificación personalizada
- `app/Models/User.php` - Agregado método sendPasswordResetNotification

### Frontend
- `resources/js/src/views/pages/authentication/Login.vue` - Agregado enlace "¿Olvidaste tu contraseña?"
- `resources/js/src/views/pages/authentication/ForgotPassword.vue` - Nueva vista
- `resources/js/src/views/pages/authentication/ResetPassword.vue` - Nueva vista
- `resources/js/src/router/routes/pages.js` - Agregadas rutas

## Pruebas

### 1. Probar AWS SES
```bash
# Probar configuración de SES
php artisan email:test-ses tu-email@ejemplo.com

# Ver logs de email
tail -f storage/logs/laravel.log
```

### 2. Probar en desarrollo local
```bash
# Configurar email como log
MAIL_MAILER=log

# Los emails aparecerán en storage/logs/laravel.log
```

### 3. Probar con Gmail
```bash
# Configurar Gmail en .env
# Usar contraseña de aplicación, no la contraseña normal
```

### 4. Verificar logs
```bash
tail -f storage/logs/laravel.log
```

## Seguridad

- Los tokens de reset expiran en 60 minutos (configurable en `config/auth.php`)
- Los tokens son únicos y se invalidan después de su uso
- Se requiere confirmación de contraseña
- Validación de email existente en la base de datos
- Protección CSRF en todas las rutas

## Personalización

### Cambiar el tiempo de expiración
Edita `config/auth.php`:
```php
'passwords' => [
    'users' => [
        'provider' => 'users',
        'table' => 'password_resets',
        'expire' => 60, // Cambiar a los minutos deseados
        'throttle' => 60,
    ],
],
```

### Personalizar el email
Edita `app/Notifications/ResetPasswordNotification.php` en el método `buildMailMessage()`.

### Personalizar las vistas
Modifica los archivos Vue en `resources/js/src/views/pages/authentication/`.

## Troubleshooting

### Error: "No se pudo enviar el enlace de restablecimiento"
- Verificar configuración de email en `.env`
- Revisar logs en `storage/logs/laravel.log`
- Verificar que el email existe en la base de datos

### Error: "Token de restablecimiento inválido"
- El token puede haber expirado (60 minutos)
- El token ya fue usado
- Verificar que la URL del email sea correcta

### Error: "No se pudo restablecer la contraseña"
- Verificar que el token sea válido
- Verificar que las contraseñas coincidan
- Verificar que la contraseña tenga al menos 8 caracteres 
