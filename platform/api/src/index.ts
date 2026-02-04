import { serve } from '@hono/node-server';
import { app } from './app';

const port = Number(process.env.PORT) || 3000;

console.log(`
  __  __       _    _____
 |  \\/  |     | |  |_   _|   /\\
 | \\  / | __ _| | __ | |    /  \\
 | |\\/| |/ _\` | |/ / | |   / /\\ \\
 | |  | | (_| |   < _| |_ / ____ \\
 |_|  |_|\\__,_|_|\\_\\_____/_/    \\_\\

  MakIA Reservas API v1.0.0
  Running on http://localhost:${port}
`);

serve({
  fetch: app.fetch,
  port,
});
