import {
    AdjustmentsHorizontalIcon,
    ChatBubbleBottomCenterTextIcon,
    ChatBubbleLeftRightIcon,
    CheckCircleIcon,
    ClipboardDocumentListIcon,
    FlagIcon,
    LightBulbIcon,
    PresentationChartBarIcon,
    PuzzlePieceIcon,
    TrophyIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';

/**
 * Ícone de linha (Heroicons, o mesmo da barra lateral) de cada seção do modelo, pela chave estável da seção.
 * O ícone não fica no banco: seções sem chave conhecida (modelos da empresa) ficam sem ícone.
 */
const FEEDBACK_SECTION_ICONS = {
    objetivo: FlagIcon,
    inicio: ChatBubbleLeftRightIcon,
    termometro: AdjustmentsHorizontalIcon,
    profiler: PuzzlePieceIcon,
    conquistas: TrophyIcon,
    dev_lider_colab: UserGroupIcon,
    dev_colab_lider: ChatBubbleBottomCenterTextIcon,
    dev_lider_self: LightBulbIcon,
    acoes: ClipboardDocumentListIcon,
    percepcoes: PresentationChartBarIcon,
    encerramento: CheckCircleIcon,
};

export function feedbackSectionIcon(sectionKey) {
    return FEEDBACK_SECTION_ICONS[sectionKey] ?? null;
}
