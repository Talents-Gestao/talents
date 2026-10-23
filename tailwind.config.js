import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** Cor via variável CSS em canais RGB, mantendo o suporte a `/opacidade`. */
const token = (name) => `rgb(var(--${name}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                shell: '1.75rem',
            },
            boxShadow: {
                shell: '0 25px 50px -12px rgb(74 32 112 / 0.12), 0 0 0 1px rgb(226 232 240 / 0.6)',
                'shell-inner': '0 20px 40px -15px rgb(99 42 126 / 0.15)',
            },
            colors: {
                /** Escala de marca: canais RGB em resources/css/tokens.css (aceita opacidade) */
                talents: {
                    50: token('talents-50'),
                    100: token('talents-100'),
                    200: token('talents-200'),
                    300: token('talents-300'),
                    400: token('talents-400'),
                    500: token('talents-500'),
                    600: token('talents-600'),
                    700: token('talents-700'),
                    800: token('talents-800'),
                    900: token('talents-900'),
                    accent: token('talents-accent'),
                },
                /** Destaques tipo LMS (gráficos, CTAs) */
                highlight: {
                    DEFAULT: token('highlight'),
                    muted: token('highlight-muted'),
                },
                /** Primária semântica: default = talents-600; o tema da empresa a sobrescreve */
                primary: {
                    DEFAULT: token('primary'),
                    hover: token('primary-hover'),
                    soft: token('primary-soft'),
                    text: token('primary-text'),
                    on: token('on-primary'),
                    border: token('primary-border'),
                    ring: token('primary-ring'),
                },
                surface: {
                    DEFAULT: token('surface'),
                    muted: token('surface-muted'),
                },
                ink: {
                    DEFAULT: token('ink'),
                    muted: token('ink-muted'),
                    subtle: token('ink-subtle'),
                },
                line: token('line'),
                success: { DEFAULT: token('success'), soft: token('success-soft') },
                warning: { DEFAULT: token('warning'), soft: token('warning-soft') },
                danger: { DEFAULT: token('danger'), soft: token('danger-soft') },
                info: { DEFAULT: token('info'), soft: token('info-soft') },
                /** Faixas NR-1: sempre com ícone e texto, nunca só cor */
                risk: {
                    low: { DEFAULT: token('risk-low'), soft: token('risk-low-soft') },
                    moderate: { DEFAULT: token('risk-moderate'), soft: token('risk-moderate-soft') },
                    high: { DEFAULT: token('risk-high'), soft: token('risk-high-soft') },
                },
            },
        },
    },

    plugins: [forms],
};
