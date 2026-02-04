import { Hono } from 'hono';
import { cors } from 'hono/cors';
import { logger } from 'hono/logger';
import { secureHeaders } from 'hono/secure-headers';
import { timing } from 'hono/timing';
import { prettyJSON } from 'hono/pretty-json';

// Módulos
import { authRoutes } from './modules/auth/routes';
import { bookingsRoutes } from './modules/bookings/routes';
import { organizationsRoutes } from './modules/organizations/routes';
import { availabilityRoutes } from './modules/bookings/availability';

// Middleware
import { rateLimiter } from './shared/middleware/rate-limiter';
import { errorHandler } from './shared/middleware/error-handler';

// Types
import type { Context, Variables } from './shared/types';

const app = new Hono<{ Variables: Variables }>();

// ============================================
// MIDDLEWARE GLOBAL
// ============================================
app.use('*', logger());
app.use('*', timing());
app.use('*', prettyJSON());
app.use('*', secureHeaders());
app.use('*', cors({
  origin: ['http://localhost:3000', 'http://localhost:5173', 'https://*.makia.app'],
  credentials: true,
}));

// Rate limiting
app.use('/v1/*', rateLimiter({
  windowMs: 60 * 1000, // 1 minuto
  max: 100, // 100 requests por minuto
}));

// ============================================
// HEALTH CHECK
// ============================================
app.get('/', (c) => {
  return c.json({
    name: 'MakIA Reservas API',
    version: '1.0.0',
    status: 'healthy',
    timestamp: new Date().toISOString(),
  });
});

app.get('/health', (c) => {
  return c.json({ status: 'ok' });
});

// ============================================
// API ROUTES v1
// ============================================
const v1 = new Hono<{ Variables: Variables }>();

// Rutas públicas
v1.route('/auth', authRoutes);

// Rutas de reservas (algunas públicas, otras protegidas)
v1.route('/bookings', bookingsRoutes);
v1.route('/availability', availabilityRoutes);

// Rutas protegidas
v1.route('/organizations', organizationsRoutes);

// Montar v1
app.route('/v1', v1);

// ============================================
// ERROR HANDLER
// ============================================
app.onError(errorHandler);

// 404 handler
app.notFound((c) => {
  return c.json({
    success: false,
    error: {
      code: 'NOT_FOUND',
      message: `Route ${c.req.method} ${c.req.path} not found`,
    },
  }, 404);
});

export { app };
