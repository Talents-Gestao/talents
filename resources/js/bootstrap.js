import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

if (import.meta.env.DEV) {
    // Mesma instância de `axios` que o @inertiajs/core importa no bundle (Vite deduplica).
    axios.interceptors.response.use((response) => {
        const serverMs = response.headers?.['x-inertia-server-ms'];
        if (serverMs !== undefined && serverMs !== '') {
            globalThis.__TALENTS_LAST_INERTIA_SERVER_MS = Number(serverMs);
        }
        return response;
    });
}
