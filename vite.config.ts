import { defineConfig, loadEnv } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, '.', 'VITE_');
  const browserOrigin = env.VITE_DEV_SERVER_ORIGIN || 'http://127.0.0.1:5173';

  return {
    plugins: [
      laravel({
        input: ['resources/js/app.tsx'],
        refresh: true,
      }),
      react(),
      tailwindcss(),
    ],
    server: {
      host: '0.0.0.0',
      // Bind to all interfaces, but publish a routable URL in Laravel's hot file.
      origin: browserOrigin,
      strictPort: true,
      allowedHosts: true,
    },
  };
});
