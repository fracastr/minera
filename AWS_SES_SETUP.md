# Configuración de AWS SES para Reset de Contraseña

## ¿Qué es AWS SES?

Amazon Simple Email Service (SES) es un servicio de email de AWS que permite enviar emails de forma confiable y escalable. Es ideal para aplicaciones en producción.

## Ventajas de AWS SES

✅ **Costo efectivo** - Muy económico para volúmenes altos
✅ **Alta confiabilidad** - 99.9% de uptime
✅ **Escalabilidad** - Se adapta automáticamente a tu demanda
✅ **Integración nativa** - Funciona perfectamente con Laravel
✅ **Métricas detalladas** - Monitoreo completo de envíos
✅ **Conformidad** - Cumple con estándares de seguridad

## Configuración Paso a Paso

### 1. Crear cuenta en AWS

1. Ve a [aws.amazon.com](https://aws.amazon.com)
2. Crea una cuenta o inicia sesión
3. Accede a la consola de AWS

### 2. Configurar AWS SES

#### Paso 1: Ir a SES
1. En la consola de AWS, busca "SES"
2. Selecciona "Simple Email Service"
3. Asegúrate de estar en la región correcta (ej: us-east-1)

#### Paso 2: Verificar dominio (Recomendado)
1. Ve a "Verified identities"
2. Haz clic en "Create identity"
3. Selecciona "Domain"
4. Ingresa tu dominio (ej: minera.com)
5. Sigue las instrucciones para verificar el dominio

#### Paso 3: Verificar email (Alternativa)
Si no tienes un dominio:
1. Ve a "Verified identities"
2. Haz clic en "Create identity"
3. Selecciona "Email address"
4. Ingresa tu email
5. Revisa tu email y haz clic en el enlace de verificación

#### Paso 4: Solicitar salida del sandbox
Por defecto, SES está en modo sandbox (solo emails verificados):
1. Ve a "Account dashboard"
2. Haz clic en "Request production access"
3. Completa el formulario explicando el uso de emails
4. Espera la aprobación (puede tomar 24-48 horas)

### 3. Crear usuario IAM para SES

#### Paso 1: Ir a IAM
1. En la consola de AWS, busca "IAM"
2. Selecciona "Identity and Access Management"

#### Paso 2: Crear usuario
1. Ve a "Users"
2. Haz clic en "Create user"
3. Nombre: `ses-email-user`
4. Marca "Programmatic access"

#### Paso 3: Asignar permisos
1. Selecciona "Attach policies directly"
2. Busca y selecciona `AmazonSESFullAccess`
3. Completa la creación

#### Paso 4: Obtener credenciales
1. Anota el **Access Key ID**
2. Anota el **Secret Access Key**
3. **IMPORTANTE**: Guarda estas credenciales de forma segura

### 4. Configurar Laravel

#### Paso 1: Actualizar .env
```env
# Configuración de AWS SES
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=tu-access-key-id
AWS_SECRET_ACCESS_KEY=tu-secret-access-key
AWS_DEFAULT_REGION=us-east-1
MAIL_FROM_ADDRESS=noreply@tu-dominio.com
MAIL_FROM_NAME="Sistema de Gestión Minera"
```

#### Paso 2: Verificar configuración
```bash
# Probar envío de email
php artisan tinker
Mail::raw('Test email', function($message) {
    $message->to('tu-email@ejemplo.com')
            ->subject('Test AWS SES');
});
```

### 5. Configuración de Producción

#### Paso 1: Configurar dominio verificado
```env
MAIL_FROM_ADDRESS=noreply@tu-dominio.com
```

#### Paso 2: Configurar DKIM (Opcional pero recomendado)
1. En SES, ve a tu dominio verificado
2. Configura DKIM para mejorar la entregabilidad
3. Agrega los registros DNS según las instrucciones

#### Paso 3: Configurar métricas
1. En SES, ve a "Configuration sets"
2. Crea un configuration set
3. Configura métricas y eventos

## Configuración de Desarrollo

### Para desarrollo local:
```env
# Usar log para desarrollo
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@minera.com
MAIL_FROM_NAME="Sistema de Gestión Minera"
```

### Para pruebas con SES:
```env
# Usar SES en sandbox
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=tu-access-key-id
AWS_SECRET_ACCESS_KEY=tu-secret-access-key
AWS_DEFAULT_REGION=us-east-1
MAIL_FROM_ADDRESS=tu-email-verificado@ejemplo.com
MAIL_FROM_NAME="Sistema de Gestión Minera"
```

## Monitoreo y Métricas

### 1. Dashboard de SES
- Ve a la consola de SES
- Revisa métricas de envío
- Monitorea tasas de rebote y quejas

### 2. CloudWatch
- Configura alertas para tasas de rebote altas
- Monitorea cuotas de envío

### 3. Logs de Laravel
```bash
# Ver logs de email
tail -f storage/logs/laravel.log
```

## Troubleshooting

### Error: "Email address not verified"
- Verifica que el email remitente esté verificado en SES
- En sandbox, solo emails verificados pueden enviar

### Error: "Sending quota exceeded"
- Revisa las cuotas en el dashboard de SES
- Solicita aumento de cuota si es necesario

### Error: "Invalid credentials"
- Verifica AWS_ACCESS_KEY_ID y AWS_SECRET_ACCESS_KEY
- Asegúrate de que el usuario IAM tenga permisos de SES

### Error: "Region mismatch"
- Verifica que AWS_DEFAULT_REGION coincida con tu región de SES

## Costos de AWS SES

### Precios (us-east-1):
- **Envío**: $0.10 por 1,000 emails
- **Recibido**: $0.09 por 1,000 emails
- **Almacenamiento**: $0.09 por GB/mes

### Ejemplo de costos:
- 10,000 emails/mes = $1.00
- 100,000 emails/mes = $10.00

## Mejores Prácticas

### 1. Verificación de dominio
- Siempre verifica tu dominio en lugar de emails individuales
- Configura SPF, DKIM y DMARC

### 2. Monitoreo
- Revisa regularmente las métricas de SES
- Configura alertas para problemas

### 3. Listas de supresión
- Respeta las solicitudes de cancelación de suscripción
- Maneja rebotes y quejas apropiadamente

### 4. Testing
- Prueba en sandbox antes de producción
- Usa emails de prueba para desarrollo

## Comandos Útiles

```bash
# Probar configuración de email
php artisan tinker
Mail::raw('Test', function($m) { $m->to('test@example.com')->subject('Test'); });

# Ver logs de email
tail -f storage/logs/laravel.log

# Limpiar cache de configuración
php artisan config:clear
php artisan cache:clear
```

## Recursos Adicionales

- [Documentación oficial de AWS SES](https://docs.aws.amazon.com/ses/)
- [Laravel Mail Documentation](https://laravel.com/docs/mail)
- [AWS SES Pricing](https://aws.amazon.com/ses/pricing/)
- [SES Best Practices](https://docs.aws.amazon.com/ses/latest/DeveloperGuide/best-practices.html) 
