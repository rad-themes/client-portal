import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [tailwindcss()],
    build: {
        outDir: 'resources/dist',
        emptyOutDir: true,
        rollupOptions: {
            input: 'resources/css/portal.css',
            output: { assetFileNames: '[name][extname]' },
        },
    },
});
