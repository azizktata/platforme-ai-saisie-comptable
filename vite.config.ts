import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/app.tsx'],
      refresh: true,
    }),
    react(),
  ],
  server: {
    host: '0.0.0.0',
    // The preview is served from a proxied Arena host rather than localhost.
    allowedHosts: true,
  },
});
