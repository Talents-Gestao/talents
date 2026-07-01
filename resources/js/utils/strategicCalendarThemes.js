export const monthThemes = {
    1: { label: 'Janeiro Branco', campaign: 'Paz e saúde mental', color: '#475569', background: '#f1f5f9' },
    2: { label: 'Fevereiro Laranja', campaign: 'Leucemia e linfoma', color: '#ea580c', background: '#ffedd5' },
    3: { label: 'Março Azul-claro', campaign: 'Hidratação e bem-estar', color: '#0284c7', background: '#e0f2fe' },
    4: { label: 'Abril Azul', campaign: 'Conscientização sobre o autismo', color: '#2563eb', background: '#dbeafe' },
    5: { label: 'Maio Amarelo', campaign: 'Hepatites e prevenção', color: '#ca8a04', background: '#fef9c3' },
    6: { label: 'Junho Vermelho', campaign: 'Doação de sangue', color: '#dc2626', background: '#fee2e2' },
    7: { label: 'Julho Amarelo', campaign: 'Hepatites virais', color: '#a16207', background: '#fef9c3' },
    8: { label: 'Agosto Dourado', campaign: 'Aleitamento materno', color: '#d97706', background: '#fef3c7' },
    9: { label: 'Setembro Amarelo', campaign: 'Prevenção ao suicídio', color: '#ca8a04', background: '#fef08a' },
    10: { label: 'Outubro Rosa', campaign: 'Câncer de mama', color: '#db2777', background: '#fce7f3' },
    11: { label: 'Novembro Azul', campaign: 'Câncer de próstata', color: '#1d4ed8', background: '#dbeafe' },
    12: { label: 'Dezembro Vermelho', campaign: 'Prevenção à AIDS', color: '#dc2626', background: '#fee2e2' },
};

export const kindThemes = {
    event: { label: 'Evento', color: '#0284c7', background: '#bae6fd' },
    rito: { label: 'Rito', color: '#dc2626', background: '#fecaca' },
    task: { label: 'Tarefa', color: '#059669', background: '#a7f3d0' },
};

export function monthTheme(month) {
    return monthThemes[Number(month)] ?? monthThemes[1];
}

export function kindTheme(kind) {
    return kindThemes[kind] ?? kindThemes.event;
}
