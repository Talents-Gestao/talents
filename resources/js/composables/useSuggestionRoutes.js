export function suggestionRoutePrefix() {
    const current = route().current() ?? '';

    return current.startsWith('admin.suggestions') ? 'admin.suggestions' : 'client.suggestions';
}

export function suggestionRoute(name, ...params) {
    return route(`${suggestionRoutePrefix()}.${name}`, ...params);
}

export function isSuggestionAdminContext() {
    return suggestionRoutePrefix() === 'admin.suggestions';
}
