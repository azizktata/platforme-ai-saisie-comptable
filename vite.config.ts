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
        host: '127.0.0.1', // Bind specifically to 127.0.0.1 instead of 0.0.0.0
        port: 5173,
        strictPort: true,
        cors: true,
        origin: 'http://127.0.0.1:5173', // Force correct asset URLs
    },
});