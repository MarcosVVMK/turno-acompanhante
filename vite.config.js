import {defineConfig, loadEnv} from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.ts',
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: 'https://turno-acompanhante-16af75ac5b84.herokuapp.com/',
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        manifest: true,
        rollupOptions: {
            input: 'resources/js/app.ts',
        },
    }
});
