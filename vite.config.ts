import { defineConfig, loadEnv } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, '.', 'VITE_');

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
            host: '127.0.0.1',
            port: 5173,
            strictPort: true,
            cors: true,
            origin: env.VITE_DEV_SERVER_ORIGIN || 'http://127.0.0.1:5173',
        },
    };
});
