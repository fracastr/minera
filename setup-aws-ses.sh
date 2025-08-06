#!/bin/bash

# Script de configuración rápida para AWS SES
# Uso: ./setup-aws-ses.sh

echo "🚀 Configuración rápida de AWS SES para Sistema de Gestión Minera"
echo "================================================================"
echo ""

# Verificar si existe .env
if [ ! -f .env ]; then
    echo "❌ Error: No se encontró el archivo .env"
    echo "Copia .env.example a .env y configura las variables básicas"
    exit 1
fi

echo "📋 Pasos para configurar AWS SES:"
echo ""
echo "1. 🌐 Crear cuenta en AWS:"
echo "   - Ve a https://aws.amazon.com"
echo "   - Crea una cuenta o inicia sesión"
echo ""
echo "2. 📧 Configurar SES:"
echo "   - Ve a la consola de AWS"
echo "   - Busca 'SES' (Simple Email Service)"
echo "   - Verifica tu dominio o email"
echo "   - Solicita salida del sandbox si es necesario"
echo ""
echo "3. 👤 Crear usuario IAM:"
echo "   - Ve a IAM en la consola de AWS"
echo "   - Crea un usuario con permisos AmazonSESFullAccess"
echo "   - Anota el Access Key ID y Secret Access Key"
echo ""
echo "4. ⚙️  Configurar variables en .env:"
echo ""

# Solicitar información
read -p "AWS Access Key ID: " aws_key
read -p "AWS Secret Access Key: " aws_secret
read -p "AWS Region (ej: us-east-1): " aws_region
read -p "Email remitente (ej: noreply@tu-dominio.com): " from_email
read -p "Nombre remitente (ej: Sistema de Gestión Minera): " from_name

echo ""
echo "📝 Configurando .env..."

# Crear backup del .env
cp .env .env.backup

# Actualizar .env con la configuración de SES
sed -i '' 's/MAIL_MAILER=.*/MAIL_MAILER=ses/' .env
sed -i '' '/AWS_ACCESS_KEY_ID=/d' .env
sed -i '' '/AWS_SECRET_ACCESS_KEY=/d' .env
sed -i '' '/AWS_DEFAULT_REGION=/d' .env

# Agregar configuración de AWS
echo "" >> .env
echo "# AWS SES Configuration" >> .env
echo "AWS_ACCESS_KEY_ID=$aws_key" >> .env
echo "AWS_SECRET_ACCESS_KEY=$aws_secret" >> .env
echo "AWS_DEFAULT_REGION=$aws_region" >> .env
echo "MAIL_FROM_ADDRESS=$from_email" >> .env
echo "MAIL_FROM_NAME=\"$from_name\"" >> .env

echo "✅ Configuración completada!"
echo ""
echo "🧪 Para probar la configuración:"
echo "php artisan email:test-ses tu-email@ejemplo.com"
echo ""
echo "📚 Para más información, consulta:"
echo "AWS_SES_SETUP.md"
echo ""
echo "⚠️  IMPORTANTE:"
echo "- Verifica que el email remitente esté verificado en SES"
echo "- Si estás en sandbox, solo emails verificados pueden recibir emails"
echo "- Guarda las credenciales de AWS de forma segura"
