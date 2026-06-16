import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
    plugins: [vue()],
    build: {
        outDir: 'js/mgr/vue-dist',
        emptyOutDir: true,
        // Инлайнить шрифты PrimeIcons как base64 прямо в CSS — бандл самодостаточен,
        // отдельные woff/ttf не нужны (без них иконки не рендерятся).
        assetsInlineLimit: 4 * 1024 * 1024,
        rollupOptions: {
            input: {
                logs: resolve(__dirname, 'js/mgr/main-logs.js'),
            },
            output: {
                entryFileNames: '[name].min.js',
                chunkFileNames: '[name].min.js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name && assetInfo.name.endsWith('.css')) {
                        return 'logs.min.css';
                    }
                    return '[name].[ext]';
                },
                // Один файл (без вендор-сплита): имя статичное, cache-bust по ?v=mtime.
                inlineDynamicImports: true,
            },
        },
    },
});
