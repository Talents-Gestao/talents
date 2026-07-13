export function desligamentoRoutePrefix() {
    const current = route().current() ?? '';

    if (
        current.startsWith('admin.desligamento') ||
        current.startsWith('admin.survey-templates')
    ) {
        return 'admin.desligamento';
    }

    return 'client.desligamento';
}

export function desligamentoRoute(name, ...params) {
    return route(`${desligamentoRoutePrefix()}.${name}`, ...params);
}

export function isDesligamentoAdminContext() {
    return desligamentoRoutePrefix() === 'admin.desligamento';
}
