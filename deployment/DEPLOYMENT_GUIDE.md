# MakIA Restaurante - Guía de Despliegue

## Servidor: contacpro.app

**Credenciales:**
- Host: `hl1577.dinaserver.com` (82.98.164.33)
- Usuario: `contacpro`
- Dominio: `contacpro.app`

---

## Opción 1: Instalación Automática (Recomendado)

### Paso 1: Conectar al servidor

```bash
ssh contacpro@82.98.164.33
```

### Paso 2: Descargar y ejecutar script de instalación

```bash
# Crear directorio temporal
mkdir -p ~/setup && cd ~/setup

# Descargar script (o copiar manualmente)
# Si tienes git:
git clone https://github.com/tu-usuario/makia-reservas.git
cd makia-reservas/deployment/scripts

# Ejecutar instalación
chmod +x install.sh
sudo ./install.sh
```

### Paso 3: Subir código de la aplicación

Desde tu máquina local:

```bash
# Comprimir la API
cd /home/user/makia-reservas/platform/api
tar -czvf api.tar.gz --exclude=node_modules --exclude=dist .

# Subir al servidor
scp api.tar.gz contacpro@82.98.164.33:/home/contacpro/apps/makia-restaurante/api/

# En el servidor
ssh contacpro@82.98.164.33
cd /home/contacpro/apps/makia-restaurante/api
tar -xzvf api.tar.gz
rm api.tar.gz
```

### Paso 4: Configurar variables de entorno

```bash
cd /home/contacpro/apps/makia-restaurante
nano .env

# Editar las siguientes variables:
# - SMTP_* (para emails)
# - STRIPE_* (para pagos)
# - TWILIO_* (para SMS - opcional)
# - WHATSAPP_* (para WhatsApp - opcional)
# - TELEGRAM_* (para Telegram - opcional)
```

### Paso 5: Desplegar

```bash
cd /home/contacpro/apps/makia-restaurante
./deploy.sh
```

### Paso 6: Configurar SSL

```bash
sudo certbot --nginx \
  -d contacpro.app \
  -d www.contacpro.app \
  -d api.contacpro.app \
  -d dashboard.contacpro.app
```

### Paso 7: Verificar

```bash
# Estado de PM2
pm2 status

# Logs
pm2 logs makia-api

# Test de API
curl https://api.contacpro.app/health
```

---

## Opción 2: Docker Compose

### Paso 1: Instalar Docker

```bash
# Docker
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Logout/login para aplicar grupos
exit
ssh contacpro@82.98.164.33
```

### Paso 2: Preparar directorio

```bash
mkdir -p ~/apps/makia-restaurante
cd ~/apps/makia-restaurante

# Clonar repositorio o subir archivos
git clone https://github.com/tu-usuario/makia-reservas.git .
```

### Paso 3: Configurar

```bash
# Copiar y editar variables
cp deployment/.env.example deployment/docker/.env
nano deployment/docker/.env

# Crear directorios para certificados
mkdir -p deployment/docker/certbot/{www,conf}
```

### Paso 4: Iniciar servicios

```bash
cd deployment/docker

# Iniciar (primera vez sin SSL)
docker-compose up -d postgres redis

# Esperar a que inicie PostgreSQL
sleep 10

# Iniciar API
docker-compose up -d api

# Verificar
docker-compose ps
docker-compose logs -f api
```

### Paso 5: Configurar SSL

```bash
# Obtener certificados (modo staging primero)
docker-compose run --rm certbot certonly \
  --webroot \
  --webroot-path=/var/www/certbot \
  --email admin@contacpro.app \
  --agree-tos \
  --no-eff-email \
  --staging \
  -d contacpro.app \
  -d www.contacpro.app \
  -d api.contacpro.app \
  -d dashboard.contacpro.app

# Si funciona, obtener certificados reales (sin --staging)
docker-compose run --rm certbot certonly \
  --webroot \
  --webroot-path=/var/www/certbot \
  --email admin@contacpro.app \
  --agree-tos \
  --no-eff-email \
  -d contacpro.app \
  -d www.contacpro.app \
  -d api.contacpro.app \
  -d dashboard.contacpro.app

# Iniciar nginx
docker-compose up -d nginx
```

### Paso 6: Comandos útiles

```bash
# Ver logs
docker-compose logs -f api

# Reiniciar servicio
docker-compose restart api

# Actualizar
git pull
docker-compose build api
docker-compose up -d api

# Ejecutar migraciones
docker-compose exec api npx prisma migrate deploy

# Acceder a la base de datos
docker-compose exec postgres psql -U makia -d makia_production

# Backup de base de datos
docker-compose exec postgres pg_dump -U makia makia_production > backup.sql
```

---

## Estructura del Servidor

```
/home/contacpro/apps/makia-restaurante/
├── api/                    # API (Hono + TypeScript)
│   ├── dist/              # Código compilado
│   ├── node_modules/
│   ├── prisma/
│   └── package.json
├── dashboard/             # Dashboard React (build estático)
│   └── dist/
├── widget/                # Widget embebible
│   └── dist/
├── www/                   # Sitio principal
├── logs/                  # Logs de PM2
├── backups/              # Backups de BD
├── .env                  # Variables de entorno
├── ecosystem.config.js   # Configuración PM2
└── deploy.sh            # Script de despliegue
```

---

## URLs Finales

| Servicio | URL |
|----------|-----|
| Sitio web | https://contacpro.app |
| Dashboard | https://dashboard.contacpro.app |
| API | https://api.contacpro.app |
| Widget CDN | https://contacpro.app/widget.js |

---

## Mantenimiento

### Backups automáticos

Añadir a crontab (`crontab -e`):

```cron
# Backup diario a las 3:00 AM
0 3 * * * /home/contacpro/apps/makia-restaurante/backup.sh >> /home/contacpro/apps/makia-restaurante/logs/backup.log 2>&1
```

Crear `/home/contacpro/apps/makia-restaurante/backup.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/home/contacpro/apps/makia-restaurante/backups"
DATE=$(date +%Y%m%d_%H%M%S)

# Backup PostgreSQL
pg_dump -U makia makia_production | gzip > ${BACKUP_DIR}/db_${DATE}.sql.gz

# Mantener solo últimos 7 días
find ${BACKUP_DIR} -name "db_*.sql.gz" -mtime +7 -delete

echo "Backup completado: db_${DATE}.sql.gz"
```

### Actualizar la aplicación

```bash
cd /home/contacpro/apps/makia-restaurante
./deploy.sh
```

### Monitorear

```bash
# Estado de servicios
pm2 status

# Uso de recursos
pm2 monit

# Logs en tiempo real
pm2 logs makia-api --lines 100

# Reiniciar si hay problemas
pm2 restart makia-api
```

### Renovar SSL

Certbot renueva automáticamente. Verificar:

```bash
sudo certbot renew --dry-run
```

---

## Solución de Problemas

### La API no responde

```bash
# Verificar proceso
pm2 status

# Ver logs de error
pm2 logs makia-api --err --lines 50

# Reiniciar
pm2 restart makia-api
```

### Error de base de datos

```bash
# Verificar PostgreSQL
sudo systemctl status postgresql

# Reiniciar si es necesario
sudo systemctl restart postgresql

# Verificar conexión
psql -U makia -d makia_production -c "SELECT 1"
```

### Error de Nginx

```bash
# Test de configuración
sudo nginx -t

# Ver logs
sudo tail -f /var/log/nginx/error.log

# Reiniciar
sudo systemctl restart nginx
```

### Memoria insuficiente

```bash
# Ver uso de memoria
free -m

# Crear swap (si no existe)
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

---

## Contacto de Soporte

- Email: soporte@contacpro.app
- Documentación: https://docs.contacpro.app
