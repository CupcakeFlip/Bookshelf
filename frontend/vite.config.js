/**
 * Configures Vue compilation, source aliases, and the frontend development server.
 * Forwards API requests to local PHP and locates frontend tests under tests/,
 * so development, previews, and Vitest use the same project setup.
 */
import vue from '@vitejs/plugin-vue';
import {fileURLToPath, URL} from 'node:url';
import {defineConfig} from 'vite';

// The alias keeps imports independent of the component's relative directory.
export default defineConfig({
    plugins: [
        vue(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url))
        }
    },
    test: {
        root: fileURLToPath(new URL('..', import.meta.url)),
        include: ['tests/frontend/**/*.test.js'],
        // Resolve Vue from the frontend installation for tests outside frontend/.
        alias: {
            'vue': fileURLToPath(new URL('./node_modules/vue', import.meta.url)),
            'vue-router': fileURLToPath(new URL('./node_modules/vue-router', import.meta.url)),
        },
    },
    server: {
        // During local development, forward browser API calls to Apache/PHP.
        proxy: {
            '/api': {
                target: 'http://localhost:8000',
                changeOrigin: true,
            }
        }
    },
    base: '/',
    preview: {
        proxy: {
            '/api': {
                target: 'http://localhost:8000',
                changeOrigin: true,
            },
        },
    },
})
