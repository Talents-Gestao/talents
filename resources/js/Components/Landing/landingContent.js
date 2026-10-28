/**
 * Textos já existentes no site (ParaEmpresas.vue, ParaPessoas.vue) reaproveitados nas Frentes da Home.
 * Mantidos em sincronia com essas páginas; nenhuma frase nova.
 */
export const FRENTES = [
    {
        id: 'empresas',
        label: 'Para Empresas',
        title: 'Metamorfose Empresarial™',
        text: 'A Metamorfose Empresarial™ foi criada para transformar empresas através das pessoas. Por meio de comportamento, liderança, cultura organizacional e desenvolvimento humano, ajudamos equipes e empresas a evoluírem de forma mais consciente, estratégica e sustentável.',
        route: 'landing.empresas',
        cta: 'Para Empresas',
        items: [
            { title: 'Diagnóstico Estratégico', description: 'Leitura profunda do contexto organizacional e das pessoas.' },
            { title: 'Voz do Time', description: 'Escuta estruturada para compreender a realidade interna.' },
            { title: 'Mapeamento Comportamental', description: 'Compreensão dos perfis e impactos na rotina.' },
            { title: 'Imersão de Perfis', description: 'Aprofundamento prático para alinhar equipes e lideranças.' },
        ],
        extra: {
            title: 'Apoio à NR-1 e riscos psicossociais',
            text: 'Além da transformação cultural, apoiamos empresas na gestão estratégica de riscos psicossociais e saúde organizacional — com método, clareza e direcionamento prático.',
            route: 'landing.nr1',
            label: 'Conhecer solução NR-1 →',
        },
    },
    {
        id: 'pessoas',
        label: 'Para Pessoas',
        title: 'Metamorfose Pessoal™',
        lead: 'Autoconhecimento, clareza e direcionamento para diferentes fases da vida pessoal e profissional.',
        text: 'A Metamorfose Pessoal™ foi criada para gerar mais clareza, consciência e direcionamento através do comportamento humano, apoiando pessoas em diferentes fases, decisões e transformações da vida pessoal e profissional.',
        route: 'landing.pessoas',
        cta: 'Para Pessoas',
        items: [
            { title: 'Mapeamento Comportamental', description: 'Compreensão dos padrões que orientam suas escolhas.' },
            { title: 'Análise de Perfil', description: 'Leitura estratégica de fortalezas, desafios e potencialidades.' },
            { title: 'Direcionamento Estratégico', description: 'Clareza prática para decisões pessoais e profissionais.' },
        ],
    },
];

export const PILARES_INTRO = {
    label: 'Metodologia Talents',
    title: 'Metodologia em 4 pilares — com a contratação no centro',
    lead: 'Contratar certo é o ponto de partida. Os outros pilares sustentam retenção, cultura e saúde do time.',
    text: 'A metamorfose empresarial começa com bons talentos no time — e continua com comportamento, direcionamento e proteção psicossocial.',
};
