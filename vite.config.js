import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    server : {
        hmr:{
            host: process.env.DDEV_HOSTNAME,
            protocol : 'wss',
            clientPort: 5173
        },
    },
});
