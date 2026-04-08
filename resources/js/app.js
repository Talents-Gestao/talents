import '../css/app.css';
import './bootstrap';

import { registerSW } from 'virtual:pwa-register';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Talents';

if (import.meta.env.DEV) {
    let clientNavStart = 0;
    router.on('start', () => {
        clientNavStart = performance.now();
        globalThis.__TALENTS_LAST_INERTIA_SERVER_MS = undefined;
    });
    router.on('finish', () => {
        const totalMs = Math.round(performance.now() - clientNavStart);
        const serverMs = globalThis.__TALENTS_LAST_INERTIA_SERVER_MS;
        console.info(`[Inertia] até concluir a visita no navegador: ${totalMs} ms`);
        if (typeof serverMs === 'number' && !Number.isNaN(serverMs)) {
            console.info(`[Inertia] processamento no servidor (PHP, header X-Inertia-Server-Ms): ${serverMs} ms`);
            console.info(
                '[Inertia] se o total no navegador for bem maior que o PHP, investigue rede, tamanho do JSON e renderização (Vite/Vue).',
            );
        } else {
            console.info('[Inertia] sem header de tempo do servidor — defina APP_DEBUG=true no .env para medição completa.');
        }
    });
}

if (import.meta.env.PROD) {
    registerSW({ immediate: true });
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(VueApexCharts)
            .mount(el);
    },
    progress: {
        color: '#632a7e',
    },
});
