#!/bin/bash
#
# MakIA Restaurante - Script de Instalación para contacpro.app
#
# Servidor: hl1577.dinaserver.com (82.98.164.33)
# Usuario: contacpro
#
# Uso: ./install.sh
#

set -e

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuración
APP_NAME="makia-restaurante"
APP_DIR="/home/contacpro/apps/${APP_NAME}"
DOMAIN="contacpro.app"
API_DOMAIN="api.contacpro.app"
DASHBOARD_DOMAIN="dashboard.contacpro.app"
NODE_VERSION="20"
POSTGRES_VERSION="15"

echo -e "${BLUE}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║            MakIA Restaurante - Instalación                  ║"
echo "║                  contacpro.app                              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Verificar que somos root o tenemos sudo
if [ "$EUID" -ne 0 ]; then
    if ! command -v sudo &> /dev/null; then
        echo -e "${RED}Error: Este script requiere privilegios de administrador${NC}"
        exit 1
    fi
    SUDO="sudo"
else
    SUDO=""
fi

# Función para log
log() {
    echo -e "${GREEN}[✓]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[!]${NC} $1"
}

error() {
    echo -e "${RED}[✗]${NC} $1"
    exit 1
}

# ============================================
# 1. ACTUALIZAR SISTEMA
# ============================================
echo -e "\n${BLUE}1. Actualizando sistema...${NC}"

$SUDO apt update
$SUDO apt upgrade -y
log "Sistema actualizado"

# ============================================
# 2. INSTALAR DEPENDENCIAS BASE
# ============================================
echo -e "\n${BLUE}2. Instalando dependencias base...${NC}"

$SUDO apt install -y \
    curl \
    wget \
    git \
    build-essential \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    gnupg \
    lsb-release \
    unzip \
    htop \
    vim \
    nano

log "Dependencias base instaladas"

# ============================================
# 3. INSTALAR NODE.JS
# ============================================
echo -e "\n${BLUE}3. Instalando Node.js ${NODE_VERSION}...${NC}"

if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | $SUDO bash -
    $SUDO apt install -y nodejs
    log "Node.js $(node -v) instalado"
else
    warn "Node.js ya está instalado: $(node -v)"
fi

# Instalar pnpm (más rápido que npm)
if ! command -v pnpm &> /dev/null; then
    $SUDO npm install -g pnpm
    log "pnpm instalado"
fi

# ============================================
# 4. INSTALAR POSTGRESQL
# ============================================
echo -e "\n${BLUE}4. Instalando PostgreSQL ${POSTGRES_VERSION}...${NC}"

if ! command -v psql &> /dev/null; then
    $SUDO sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
    wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | $SUDO apt-key add -
    $SUDO apt update
    $SUDO apt install -y postgresql-${POSTGRES_VERSION} postgresql-contrib-${POSTGRES_VERSION}

    # Iniciar PostgreSQL
    $SUDO systemctl start postgresql
    $SUDO systemctl enable postgresql

    log "PostgreSQL ${POSTGRES_VERSION} instalado"
else
    warn "PostgreSQL ya está instalado"
fi

# ============================================
# 5. INSTALAR REDIS
# ============================================
echo -e "\n${BLUE}5. Instalando Redis...${NC}"

if ! command -v redis-server &> /dev/null; then
    $SUDO apt install -y redis-server
    $SUDO systemctl start redis-server
    $SUDO systemctl enable redis-server
    log "Redis instalado"
else
    warn "Redis ya está instalado"
fi

# ============================================
# 6. INSTALAR NGINX
# ============================================
echo -e "\n${BLUE}6. Instalando Nginx...${NC}"

if ! command -v nginx &> /dev/null; then
    $SUDO apt install -y nginx
    $SUDO systemctl start nginx
    $SUDO systemctl enable nginx
    log "Nginx instalado"
else
    warn "Nginx ya está instalado"
fi

# ============================================
# 7. INSTALAR CERTBOT (SSL)
# ============================================
echo -e "\n${BLUE}7. Instalando Certbot para SSL...${NC}"

if ! command -v certbot &> /dev/null; then
    $SUDO apt install -y certbot python3-certbot-nginx
    log "Certbot instalado"
else
    warn "Certbot ya está instalado"
fi

# ============================================
# 8. INSTALAR PM2 (Process Manager)
# ============================================
echo -e "\n${BLUE}8. Instalando PM2...${NC}"

if ! command -v pm2 &> /dev/null; then
    $SUDO npm install -g pm2
    log "PM2 instalado"
else
    warn "PM2 ya está instalado"
fi

# ============================================
# 9. CREAR ESTRUCTURA DE DIRECTORIOS
# ============================================
echo -e "\n${BLUE}9. Creando estructura de directorios...${NC}"

mkdir -p ${APP_DIR}/{api,dashboard,widget,logs,backups}
mkdir -p ${APP_DIR}/api/{dist,node_modules}

log "Directorios creados en ${APP_DIR}"

# ============================================
# 10. CONFIGURAR BASE DE DATOS
# ============================================
echo -e "\n${BLUE}10. Configurando base de datos PostgreSQL...${NC}"

# Generar contraseña segura
DB_PASSWORD=$(openssl rand -base64 32 | tr -d /=+ | head -c 24)

# Crear usuario y base de datos
$SUDO -u postgres psql << EOF
-- Crear usuario si no existe
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'makia') THEN
        CREATE USER makia WITH PASSWORD '${DB_PASSWORD}';
    END IF;
END
\$\$;

-- Crear base de datos si no existe
SELECT 'CREATE DATABASE makia_production OWNER makia'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'makia_production')\gexec

-- Permisos
GRANT ALL PRIVILEGES ON DATABASE makia_production TO makia;
EOF

log "Base de datos configurada"
echo -e "${YELLOW}Contraseña de BD: ${DB_PASSWORD}${NC}"
echo "${DB_PASSWORD}" > ${APP_DIR}/.db_password
chmod 600 ${APP_DIR}/.db_password

# ============================================
# 11. CREAR ARCHIVO DE VARIABLES DE ENTORNO
# ============================================
echo -e "\n${BLUE}11. Creando archivo de variables de entorno...${NC}"

JWT_SECRET=$(openssl rand -base64 64 | tr -d /=+ | head -c 64)
WEBHOOK_SECRET=$(openssl rand -base64 32 | tr -d /=+ | head -c 32)

cat > ${APP_DIR}/.env << EOF
# ============================================
# MakIA Restaurante - Variables de Producción
# contacpro.app
# ============================================

# Entorno
NODE_ENV=production
PORT=3000

# Base de datos
DATABASE_URL=postgresql://makia:${DB_PASSWORD}@localhost:5432/makia_production

# Redis
REDIS_URL=redis://localhost:6379

# JWT
JWT_SECRET=${JWT_SECRET}
JWT_EXPIRES_IN=7d

# Dominios
APP_URL=https://${DOMAIN}
API_URL=https://${API_DOMAIN}
DASHBOARD_URL=https://${DASHBOARD_DOMAIN}

# Webhooks
WEBHOOK_SECRET=${WEBHOOK_SECRET}

# Email (configurar con tu proveedor)
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=
SMTP_PASSWORD=
SMTP_FROM=noreply@contacpro.app

# SMS (Twilio - opcional)
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_PHONE_NUMBER=

# WhatsApp Business (opcional)
WHATSAPP_API_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_VERIFY_TOKEN=

# Telegram (opcional)
TELEGRAM_BOT_TOKEN=

# Stripe (pagos)
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_PRICE_STARTER=
STRIPE_PRICE_PRO=
STRIPE_PRICE_BUSINESS=

# Logging
LOG_LEVEL=info
EOF

chmod 600 ${APP_DIR}/.env
log "Variables de entorno creadas en ${APP_DIR}/.env"

# ============================================
# 12. CONFIGURAR NGINX
# ============================================
echo -e "\n${BLUE}12. Configurando Nginx...${NC}"

# API
cat > /tmp/makia-api.conf << 'EOF'
server {
    listen 80;
    server_name api.contacpro.app;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
        proxy_read_timeout 86400;
    }

    # Webhooks - sin límite de rate
    location /social {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
EOF

# Dashboard
cat > /tmp/makia-dashboard.conf << 'EOF'
server {
    listen 80;
    server_name dashboard.contacpro.app;

    root /home/contacpro/apps/makia-restaurante/dashboard/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /assets {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
EOF

# Main site
cat > /tmp/makia-main.conf << 'EOF'
server {
    listen 80;
    server_name contacpro.app www.contacpro.app;

    root /home/contacpro/apps/makia-restaurante/www;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # Widget CDN
    location /widget.js {
        alias /home/contacpro/apps/makia-restaurante/widget/dist/widget.js;
        add_header Access-Control-Allow-Origin *;
        add_header Cache-Control "public, max-age=3600";
    }
}
EOF

$SUDO mv /tmp/makia-api.conf /etc/nginx/sites-available/makia-api
$SUDO mv /tmp/makia-dashboard.conf /etc/nginx/sites-available/makia-dashboard
$SUDO mv /tmp/makia-main.conf /etc/nginx/sites-available/makia-main

$SUDO ln -sf /etc/nginx/sites-available/makia-api /etc/nginx/sites-enabled/
$SUDO ln -sf /etc/nginx/sites-available/makia-dashboard /etc/nginx/sites-enabled/
$SUDO ln -sf /etc/nginx/sites-available/makia-main /etc/nginx/sites-enabled/

$SUDO nginx -t && $SUDO systemctl reload nginx
log "Nginx configurado"

# ============================================
# 13. CONFIGURAR FIREWALL
# ============================================
echo -e "\n${BLUE}13. Configurando firewall...${NC}"

if command -v ufw &> /dev/null; then
    $SUDO ufw allow 22/tcp    # SSH
    $SUDO ufw allow 80/tcp    # HTTP
    $SUDO ufw allow 443/tcp   # HTTPS
    $SUDO ufw --force enable
    log "Firewall configurado"
else
    warn "UFW no instalado, saltando configuración de firewall"
fi

# ============================================
# 14. CREAR SCRIPT DE DESPLIEGUE
# ============================================
echo -e "\n${BLUE}14. Creando script de despliegue...${NC}"

cat > ${APP_DIR}/deploy.sh << 'DEPLOY_EOF'
#!/bin/bash
#
# Script de despliegue para MakIA Restaurante
#

set -e

APP_DIR="/home/contacpro/apps/makia-restaurante"
REPO_URL="https://github.com/tu-usuario/makia-reservas.git"

echo "🚀 Desplegando MakIA Restaurante..."

# Ir al directorio
cd ${APP_DIR}

# Actualizar código (si es git)
if [ -d ".git" ]; then
    echo "📥 Actualizando código..."
    git pull origin main
fi

# Instalar dependencias de la API
echo "📦 Instalando dependencias de la API..."
cd ${APP_DIR}/api
pnpm install --frozen-lockfile

# Ejecutar migraciones
echo "🗃️ Ejecutando migraciones..."
pnpm prisma migrate deploy

# Compilar
echo "🔨 Compilando..."
pnpm build

# Reiniciar PM2
echo "🔄 Reiniciando servicios..."
pm2 reload makia-api || pm2 start dist/index.js --name makia-api

echo "✅ Despliegue completado!"
DEPLOY_EOF

chmod +x ${APP_DIR}/deploy.sh
log "Script de despliegue creado"

# ============================================
# 15. CONFIGURAR PM2
# ============================================
echo -e "\n${BLUE}15. Configurando PM2...${NC}"

cat > ${APP_DIR}/ecosystem.config.js << 'EOF'
module.exports = {
  apps: [
    {
      name: 'makia-api',
      script: './api/dist/index.js',
      cwd: '/home/contacpro/apps/makia-restaurante',
      instances: 'max',
      exec_mode: 'cluster',
      env: {
        NODE_ENV: 'production',
        PORT: 3000
      },
      env_file: '.env',
      error_file: './logs/error.log',
      out_file: './logs/out.log',
      merge_logs: true,
      time: true,
      max_memory_restart: '500M',
      exp_backoff_restart_delay: 100
    }
  ]
};
EOF

# Configurar PM2 para iniciar con el sistema
pm2 startup systemd -u contacpro --hp /home/contacpro || true

log "PM2 configurado"

# ============================================
# RESUMEN
# ============================================
echo -e "\n${GREEN}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║               ✅ INSTALACIÓN COMPLETADA                     ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

echo -e "
${BLUE}📁 Directorio de la aplicación:${NC}
   ${APP_DIR}

${BLUE}🔐 Credenciales de base de datos:${NC}
   Host: localhost
   Puerto: 5432
   Base de datos: makia_production
   Usuario: makia
   Contraseña: (guardada en ${APP_DIR}/.db_password)

${BLUE}⚙️ Variables de entorno:${NC}
   ${APP_DIR}/.env

${BLUE}🌐 Dominios configurados:${NC}
   - https://contacpro.app (sitio principal)
   - https://api.contacpro.app (API)
   - https://dashboard.contacpro.app (Dashboard)

${BLUE}📋 Próximos pasos:${NC}
   1. Copiar el código de la API a ${APP_DIR}/api/
   2. Configurar variables de entorno en ${APP_DIR}/.env
   3. Ejecutar: cd ${APP_DIR} && ./deploy.sh
   4. Configurar SSL: sudo certbot --nginx -d contacpro.app -d api.contacpro.app -d dashboard.contacpro.app
   5. Verificar: pm2 status

${YELLOW}⚠️ IMPORTANTE: Recuerda configurar:${NC}
   - Credenciales SMTP para emails
   - Claves de Stripe para pagos
   - Tokens de WhatsApp/Telegram (opcional)
"
