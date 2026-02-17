#!/bin/bash
#
# MakIA Restaurante - Quick Deploy Script
#
# Ejecuta directamente en el servidor:
# curl -sSL https://raw.githubusercontent.com/tu-usuario/makia-reservas/main/deployment/scripts/quick-deploy.sh | bash
#

set -e

echo "🚀 MakIA Restaurante - Quick Deploy"
echo "===================================="

APP_DIR="/home/contacpro/apps/makia-restaurante"

# Verificar que estamos en el servidor correcto
if [ "$(whoami)" != "contacpro" ]; then
    echo "⚠️  Este script debe ejecutarse como usuario 'contacpro'"
    exit 1
fi

# Verificar estructura
if [ ! -d "$APP_DIR" ]; then
    echo "❌ Directorio $APP_DIR no existe. Ejecuta primero install.sh"
    exit 1
fi

cd $APP_DIR

# Verificar .env
if [ ! -f ".env" ]; then
    echo "❌ Archivo .env no encontrado. Crea uno basado en .env.example"
    exit 1
fi

# Cargar variables
source .env

echo "📦 Instalando dependencias..."
cd api
pnpm install --frozen-lockfile

echo "🗃️ Ejecutando migraciones..."
pnpm prisma migrate deploy

echo "🔨 Compilando..."
pnpm build

echo "🔄 Reiniciando servicio..."
cd $APP_DIR

if pm2 describe makia-api > /dev/null 2>&1; then
    pm2 reload ecosystem.config.js
else
    pm2 start ecosystem.config.js
fi

pm2 save

echo ""
echo "✅ Despliegue completado!"
echo ""
echo "📋 Verificar:"
echo "   pm2 status"
echo "   curl http://localhost:3000/health"
echo ""
