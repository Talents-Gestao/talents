import { route as ziggyRoute } from '../../../vendor/tightenco/ziggy';

/**
 * Ziggy com URLs relativas por padrão — evita links quebrados quando APP_URL
 * não coincide com a porta/origem do browser (ex.: localhost:8080 em Docker).
 */
export function route(name, params, absolute = false, config) {
    return ziggyRoute(name, params, absolute, config);
}

export const ZiggyRelativeVue = {
    install(app) {
        if (parseInt(app.version, 10) > 2) {
            app.config.globalProperties.route = route;
            app.provide('route', route);
            return;
        }

        app.mixin({
            methods: {
                route,
            },
        });
    },
};
