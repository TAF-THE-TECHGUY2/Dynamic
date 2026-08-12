import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            // Two entries, one per rendering strategy: site.ts is Alpine for the
            // public Blade pages, app.tsx is Inertia + React for the admin
            // console. Both pull the same resources/css/app.css, so the brand
            // tokens are defined once.
            input: ['resources/css/app.css', 'resources/js/site.ts', 'resources/js/app.tsx'],
            refresh: true,
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
