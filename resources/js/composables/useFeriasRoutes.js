export function feriasRoutePrefix() {
    const current = route().current() ?? '';

    return current.startsWith('admin.ferias') ? 'admin.ferias' : 'client.ferias';
}

export function feriasRoute(name, ...params) {
    return route(`${feriasRoutePrefix()}.${name}`, ...params);
}

export function isFeriasAdminContext() {
    return feriasRoutePrefix() === 'admin.ferias';
}
