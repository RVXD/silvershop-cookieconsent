import { defineConfig } from 'vite';

// Builds the module's client assets to fixed, unhashed paths that
// ContentControllerExtension references via $module->getResource():
//   client/dist/js/cookieconsent.js
//   client/dist/styles/cookieconsent.css
//
// The JS entry imports the SCSS so Vite compiles both in one pass; the IIFE
// bundle self-executes on load.
export default defineConfig({
    build: {
        outDir: 'client/dist',
        emptyOutDir: false,
        cssCodeSplit: false,
        lib: {
            entry: 'client/src/js/cookieconsent.js',
            name: 'SilverShopConsent',
            formats: ['iife'],
            fileName: () => 'js/cookieconsent.js',
        },
        rollupOptions: {
            output: {
                assetFileNames: (assetInfo) => {
                    const name = assetInfo.names?.[0] ?? assetInfo.name ?? '';
                    return name.endsWith('.css')
                        ? 'styles/cookieconsent.css'
                        : 'assets/[name][extname]';
                },
            },
        },
    },
});
