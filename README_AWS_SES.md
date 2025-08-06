# 🚀 AWS SES - Configuración Rápida

## ✅ Configuración Completada

Tu sistema de reset de contraseña ya está configurado para usar AWS SES. Aquí tienes todo lo que necesitas:

### 📦 Paquetes Instalados
- `aws/aws-sdk-php` - SDK oficial de AWS para PHP
- Configuración de SES en `config/services.php`
- Comando de prueba: `php artisan email:test-ses`

### 📁 Archivos Creados/Modificados
- `AWS_SES_SETUP.md` - Guía completa de configuración
- `setup-aws-ses.sh` - Script de configuración automática
- `app/Console/Commands/TestSESEmail.php` - Comando de prueba
- `env.example` - Ejemplo de configuración
- `PASSWORD_RESET_SETUP.md` - Documentación actualizada

## 🚀 Configuración Rápida

### Opción 1: Script Automático
```bash
./setup-aws-ses.sh
```

### Opción 2: Configuración Manual

1. **Configurar .env:**
```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=tu-access-key-id
AWS_SECRET_ACCESS_KEY=tu-secret-access-key
AWS_DEFAULT_REGION=us-east-1
MAIL_FROM_ADDRESS=noreply@tu-dominio.com
MAIL_FROM_NAME="Sistema de Gestión Minera"
```

2. **Probar configuración:**
```bash
php artisan email:test-ses tu-email@ejemplo.com
```

## 📋 Pasos en AWS

### 1. Crear cuenta AWS
- Ve a [aws.amazon.com](https://aws.amazon.com)
- Crea cuenta o inicia sesión

### 2. Configurar SES
- Busca "SES" en la consola
- Verifica tu dominio o email
- Solicita salida del sandbox

### 3. Crear usuario IAM
- Ve a IAM en la consola
- Crea usuario con `AmazonSESFullAccess`
- Anota Access Key ID y Secret Access Key

## 🧪 Pruebas

```bash
# Probar envío de email
php artisan email:test-ses tu-email@ejemplo.com

# Ver logs
tail -f storage/logs/laravel.log

# Limpiar cache
php artisan config:clear
```

## 💰 Costos

- **Envío**: $0.10 por 1,000 emails
- **Ejemplo**: 10,000 emails/mes = $1.00

## 🔧 Troubleshooting

### Error: "Email address not verified"
- Verifica que el email remitente esté verificado en SES
- En sandbox, solo emails verificados pueden enviar

### Error: "Invalid credentials"
- Verifica AWS_ACCESS_KEY_ID y AWS_SECRET_ACCESS_KEY
- Asegúrate de que el usuario IAM tenga permisos de SES

### Error: "Region mismatch"
- Verifica que AWS_DEFAULT_REGION coincida con tu región de SES

## 📚 Documentación

- **Guía completa**: `AWS_SES_SETUP.md`
- **Configuración general**: `PASSWORD_RESET_SETUP.md`
- **Script automático**: `setup-aws-ses.sh`

## 🎯 Ventajas de AWS SES

✅ **Costo efectivo** - Muy económico para volúmenes altos  
✅ **Alta confiabilidad** - 99.9% de uptime  
✅ **Escalabilidad** - Se adapta automáticamente  
✅ **Integración nativa** - Funciona perfectamente con Laravel  
✅ **Métricas detalladas** - Monitoreo completo  
✅ **Conformidad** - Cumple estándares de seguridad  

## 🔒 Seguridad

- Los tokens de reset expiran en 60 minutos
- Validación de email existente en la base de datos
- Confirmación de contraseña requerida
- Protección CSRF en todas las rutas
- Credenciales AWS seguras

---

**¡Tu sistema de reset de contraseña con AWS SES está listo para usar! 🎉** 
