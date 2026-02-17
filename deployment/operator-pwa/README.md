# MakIA Operador PWA - Deployment

## Quick Start

1. **Subir a contacpro.app:**
   ```bash
   cd ~/www
   # Borrar versión antigua
   rm -rf operarios
   # Descomprimir nueva versión
   unzip operator-pwa.zip -d operarios
   cd operarios
   ```

2. **Iniciar con PM2:**
   ```bash
   pm2 delete makia-operarios 2>/dev/null
   pm2 start ecosystem.config.js
   pm2 save
   ```

3. **Verificar:**
   ```bash
   pm2 logs makia-operarios
   curl http://localhost:3002
   ```

## Configuración Nginx

Añadir al archivo de configuración de nginx:

```nginx
location /operarios/ {
    proxy_pass http://localhost:3002/;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection 'upgrade';
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_cache_bypass $http_upgrade;
}
```

## Variables de Entorno

- `PORT`: Puerto del servidor (default: 3002)
- `NEXT_PUBLIC_API_URL`: URL de la API (default: https://api.contacpro.app/v1)

## Estructura

```
operator-pwa/
├── apps/
│   └── operator-pwa/
│       ├── .next/          # Build de Next.js
│       ├── public/         # Assets estáticos
│       └── server.js       # Servidor de producción
├── node_modules/           # Dependencias
├── ecosystem.config.js     # Configuración PM2
└── package.json
```
