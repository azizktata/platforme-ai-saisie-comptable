import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite'; // <-- Required for Tailwind v4

export default defineConfig({
    plugins: [
        laravel({
           input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
        
    ],
    server: {
        host: '127.0.0.1', // Bind specifically to 127.0.0.1 instead of 0.0.0.0
        port: 5173,
        strictPort: true,
        cors: true,
        origin: 'http://127.0.0.1:5173', // Force correct asset URLs
    },
});