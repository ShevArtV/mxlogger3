import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
    plugins: [vue()],
    build: {
        outDir: 'js/mgr/vue-dist',
        emptyOutDir: true,
        rollupOptions: {
            input: {
                logs: resolve(__dirname, 'js/mgr/main-logs.js'),
            },
            output: {
                entryFileNames: '[name].min.js',
                chunkFileNames: '[name].min.js',
                assetFileNames: '[name].[ext]',
                // Один файл (без вендор-сплита): имя статичное, cache-bust по ?v=mtime
                // в контроллере надёжно сбрасывает кэш при каждой пересборке.
                inlineDynamicImports: true,
            },
        },
    },
});
