import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    safelist: [
        // ── Colores usados dinámicamente en navigation.blade.php ──────────────
        // Cada módulo del nav tiene su propio color; Tailwind purge no detecta
        // las clases dentro de ternarios PHP en producción, así que las
        // protegemos aquí explícitamente.
        {
            pattern: /^(bg|text|ring|border)-(indigo|violet|emerald|sky|orange)-(50|100|200|400|500|600|700|800|900)$/,
            variants: ['hover', 'dark', 'group-hover', 'dark:hover'],
        },
        {
            pattern: /^(bg|text|ring|border)-(indigo|violet|emerald|sky|orange)-900\/(20|30)$/,
            variants: ['hover', 'dark', 'dark:hover'],
        },
        // Dashboard hero cards
        'bg-emerald-500/20', 'bg-blue-500/20', 'bg-amber-500/20',
        'bg-indigo-500/20', 'bg-violet-500/20', 'bg-purple-500/20',
        'text-emerald-400', 'text-blue-400', 'text-amber-400',
        'text-indigo-400', 'text-violet-400',
        // rotate para el chevron de los dropdowns
        'rotate-180',
        // ring opacity
        'ring-1',
        // scale para transiciones
        'scale-95', 'scale-100',
        // translate para animaciones de dropdown
        '-translate-y-1', 'translate-y-0',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};