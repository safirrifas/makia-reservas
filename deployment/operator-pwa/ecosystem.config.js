module.exports = {
  apps: [
    {
      name: 'makia-operarios',
      script: 'apps/operator-pwa/server.js',
      cwd: __dirname,
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '256M',
      env: {
        NODE_ENV: 'production',
        PORT: 3002,
        HOSTNAME: '0.0.0.0',
        NEXT_PUBLIC_API_URL: 'https://api.contacpro.app/v1',
      },
    },
  ],
};
