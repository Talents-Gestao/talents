<script setup>
import Nr1Button from './Nr1Button.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import EmptyState from './EmptyState.vue';
import { useNr1Toast } from './useNr1Toast';

const props = defineProps({
    campaign: { type: Object, required: true },
    companyName: { type: String, default: '' },
});

const MODALITY = {
    online: { icon: '▣', sub: 'Conteúdo gravado • Time e Líderes', desc: 'Aprendizado flexível, com conteúdos específicos para o time e para as lideranças.' },
    presencial: { icon: '♧', sub: 'Encontro ao vivo • Time e Líderes', desc: 'Treinamentos com especialistas da Talents, troca de experiências e espaço para dúvidas.' },
};

const { push } = useNr1Toast();
const isTalents = computed(() => props.campaign.contract !== null);
const content = ref(null);

const contractedCount = (card) => card.rows.filter((row) => row.state === 'contracted').length;
const statusText = (card) => {
    const n = contractedCount(card);

    return n === 2 ? 'Contratado · Time e Líderes' : n === 1 ? 'Contratado parcialmente' : 'Opcional · Sob proposta';
};

const showOrientation = () => {
    content.value = { title: props.campaign.orientation.title, paragraphs: props.campaign.orientation.paragraphs };
};
const showTraining = (card, row) => {
    const t = row.training ?? {};
    const lines = [t.scope];
    if (t.starts_on || t.ends_on) lines.push(`Período: ${t.starts_on ?? '—'} a ${t.ends_on ?? '—'}`);
    if (t.location) lines.push(`Local: ${t.location}`);
    if (t.event_at) lines.push(`Data e hora: ${t.event_at}`);
    if (t.facilitator) lines.push(`Facilitador: ${t.facilitator}`);
    if (t.link_url) lines.push(`Link: ${t.link_url}`);
    content.value = { title: `${card.label} · ${row.audience_label}`, paragraphs: lines.filter(Boolean) };
};

const request = (row) => router.post(row.request_url, {}, { preserveScroll: true, onSuccess: () => push('Proposta solicitada. A Talents entrará em contato.') });

// Talents: marcar contratado (escopo obrigatório) e remover.
const contractTarget = ref(null);
const contractForm = useForm({ modality: '', audience: '', scope: '', link_url: '', location: '', event_at: '', released: true });
const openContract = (card, row) => {
    contractTarget.value = { card, row };
    contractForm.reset();
    contractForm.modality = card.modality;
    contractForm.audience = row.audience;
};
const submitContract = () => {
    contractForm.post(props.campaign.contract.contract, {
        preserveScroll: true,
        onSuccess: () => {
            contractTarget.value = null;
        },
    });
};
const uncontract = (card, row) => {
    if (window.confirm(`Remover a contratação de ${card.label} · ${row.audience_label}?`)) {
        router.post(props.campaign.contract.uncontract, { modality: card.modality, audience: row.audience }, { preserveScroll: true });
    }
};
const toggleSlot = (card, row, checked) => (checked ? openContract(card, row) : uncontract(card, row));

const eligibleForm = useForm({
    eligible_count: props.campaign.eligibleEditor?.count ?? '',
    eligible_source: props.campaign.eligibleEditor?.source ?? 'Informado pelo RH',
});
const saveEligible = () => eligibleForm.post(props.campaign.eligibleEditor.url, { preserveScroll: true });

const copied = ref('');
const copy = async (url) => {
    try {
        await navigator.clipboard.writeText(url);
        copied.value = url;
        push('Link copiado para a área de transferência.');
    } catch {
        copied.value = '';
        push('Não foi possível copiar. Selecione o link e copie manualmente.', 'warn');
    }
};
</script>

<template>
    <div>
        <div class="n1-intro">
            <div>
                <div class="n1-eyebrow">Campanha NR-1</div>
                <h1>Prepare seu time para participar.</h1>
                <p>Orientações e treinamentos disponíveis conforme as modalidades contratadas pela empresa.</p>
            </div>
            <span class="n1-pill" :class="campaign.collection.open ? 'ok' : 'gray'">{{ campaign.collection.open ? 'Campanha ativa' : campaign.collection.label }}</span>
        </div>

        <div class="n1-g12">
            <div class="c4">
                <div class="n1-course included">
                    <div class="n1-camptitle"><span class="n1-pill ok">Disponível para todos</span></div>
                    <div class="n1-course-head">
                        <span class="n1-course-icon" aria-hidden="true">▶</span>
                        <div>
                            <h2 class="n1-course-title">Orientação NR-1</h2>
                            <span class="n1-tiny">Vídeo de preparação</span>
                        </div>
                    </div>
                    <p class="n1-course-desc">Uma explicação objetiva sobre a pesquisa, o sigilo das respostas e como preencher o questionário.</p>
                    <div class="n1-course-rows">
                        <Nr1Button variant="primary" block type="button" @click="showOrientation">Acessar orientação</Nr1Button>
                    </div>
                </div>
            </div>

            <div v-for="card in campaign.trainings" :key="card.modality" class="c4">
                <div class="n1-course" :class="contractedCount(card) > 0 ? 'subscribed' : 'optional'">
                    <div class="n1-camptitle">
                        <span class="n1-pill" :class="contractedCount(card) > 0 ? 'ok' : 'gray'">{{ statusText(card) }}</span>
                    </div>
                    <div class="n1-course-head">
                        <span class="n1-course-icon" aria-hidden="true">{{ MODALITY[card.modality].icon }}</span>
                        <div>
                            <h2 class="n1-course-title">{{ card.label }}</h2>
                            <span class="n1-tiny">{{ MODALITY[card.modality].sub }}</span>
                        </div>
                    </div>
                    <p class="n1-course-desc">{{ MODALITY[card.modality].desc }}</p>
                    <div class="n1-course-rows">
                        <div v-for="row in card.rows" :key="row.audience" class="n1-variant" :class="row.state === 'contracted' ? 'active' : 'optional'">
                            <div>
                                <b>{{ row.audience_label }}</b>
                                <small>
                                    {{ row.state === 'contracted' ? (card.modality === 'online' ? 'Acesso incluído na contratação' : 'Encontro contratado · Agendamento a confirmar') : 'Ainda não contratado' }}
                                </small>
                            </div>
                            <Nr1Button variant="primary" size="sm" v-if="row.state === 'contracted'" type="button" @click="showTraining(card, row)">
                                {{ card.modality === 'online' ? 'Ver conteúdo' : 'Detalhes' }}
                            </Nr1Button>
                            <Nr1Button variant="secondary" size="sm" v-else-if="isTalents" type="button" @click="openContract(card, row)">Marcar contratado</Nr1Button>
                            <Nr1Button variant="secondary" size="sm" v-else-if="row.state === 'requested'" type="button" disabled>Proposta solicitada</Nr1Button>
                            <Nr1Button variant="secondary" size="sm" v-else-if="row.request_url" type="button" @click="request(row)">Solicitar proposta</Nr1Button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="isTalents" id="campaignSettings" class="n1-card c12">
                <div class="n1-sectiontop">
                    <h2>Serviços contratados<template v-if="companyName"> · {{ companyName }}</template></h2>
                    <span class="n1-pill blue">Gestão Talents</span>
                </div>
                <p class="n1-tiny" style="margin: 0 0 12px">Defina os públicos atendidos por cada modalidade. A orientação é incluída em todas as campanhas.</p>
                <div class="n1-g12">
                    <div v-for="card in campaign.trainings" :key="card.modality" class="n1-setting c6">
                        <div>
                            <b>{{ card.label }}</b>
                            <p>Selecione os públicos incluídos na contratação.</p>
                        </div>
                        <div style="display: flex; gap: 14px; flex-wrap: wrap">
                            <label v-for="row in card.rows" :key="row.audience" style="display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 730; color: #654672; cursor: pointer">
                                <input
                                    type="checkbox"
                                    style="width: 18px; height: 18px; accent-color: #8642a1; color: #8642a1"
                                    :checked="row.state === 'contracted'"
                                    @change="toggleSlot(card, row, $event.target.checked)"
                                />
                                {{ row.audience_label }}
                            </label>
                        </div>
                    </div>
                </div>

                <form v-if="contractTarget" class="n1-smallbox" style="margin-top: 14px" @submit.prevent="submitContract">
                    <div class="n1-mini-head">{{ contractTarget.card.label }} · {{ contractTarget.row.audience_label }}</div>
                    <div class="n1-fields" style="margin-top: 10px">
                        <div>
                            <label class="n1-label" for="contract-scope">Escopo ou preço contratado</label>
                            <input id="contract-scope" v-model="contractForm.scope" type="text" class="n1-field" required />
                            <p v-if="contractForm.errors.scope" class="n1-tiny" style="color: #ad3d50">{{ contractForm.errors.scope }}</p>
                        </div>
                        <div v-if="contractTarget.card.modality === 'online'">
                            <label class="n1-label" for="contract-link">Link do treinamento</label>
                            <input id="contract-link" v-model="contractForm.link_url" type="url" class="n1-field" placeholder="https://" />
                            <p v-if="contractForm.errors.link_url" class="n1-tiny" style="color: #ad3d50">{{ contractForm.errors.link_url }}</p>
                        </div>
                        <template v-else>
                            <div>
                                <label class="n1-label" for="contract-location">Local</label>
                                <input id="contract-location" v-model="contractForm.location" type="text" class="n1-field" />
                            </div>
                            <div>
                                <label class="n1-label" for="contract-event">Data e hora</label>
                                <input id="contract-event" v-model="contractForm.event_at" type="datetime-local" class="n1-field" />
                            </div>
                        </template>
                    </div>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 12px; margin-top: 10px">
                        <input v-model="contractForm.released" type="checkbox" /> Liberar ao público
                    </label>
                    <div class="n1-tools" style="margin-top: 12px">
                        <Nr1Button variant="primary" size="sm" type="submit" :disabled="contractForm.processing">Salvar contratação</Nr1Button>
                        <Nr1Button variant="secondary" size="sm" type="button" @click="contractTarget = null">Cancelar</Nr1Button>
                    </div>
                </form>
            </div>

            <div class="n1-card c7">
                <h2>Questionário psicossocial</h2>
                <div class="n1-g12">
                    <div class="c6 n1-smallbox"><div class="n1-cap">Questões</div><div class="n1-big">{{ campaign.questionnaire.questions }}</div></div>
                    <div class="c6 n1-smallbox"><div class="n1-cap">Dimensões</div><div class="n1-big">{{ campaign.questionnaire.dimensions }}</div></div>
                </div>
                <div class="n1-divider"></div>
                <div v-if="campaign.links.length || campaign.publicUrl">
                    <div v-for="link in campaign.links.filter((l) => l.url)" :key="link.label" style="margin-bottom: 12px">
                        <label class="n1-label" :for="'link-' + link.label">Link da campanha · {{ link.label }}</label>
                        <div class="n1-fields">
                            <input :id="'link-' + link.label" type="text" readonly :value="link.url" class="n1-field" :aria-label="'Link da campanha ' + link.label" />
                            <Nr1Button variant="primary" block type="button" @click="copy(link.url)">{{ copied === link.url ? 'Link copiado' : 'Copiar link da pesquisa' }}</Nr1Button>
                        </div>
                    </div>
                    <div v-if="!campaign.links.some((l) => l.url) && campaign.publicUrl">
                        <label class="n1-label" for="link-campanha">Link da campanha</label>
                        <div class="n1-fields">
                            <input id="link-campanha" type="text" readonly :value="campaign.publicUrl" class="n1-field" aria-label="Link da campanha" />
                            <Nr1Button variant="primary" block type="button" @click="copy(campaign.publicUrl)">{{ copied === campaign.publicUrl ? 'Link copiado' : 'Copiar link da pesquisa' }}</Nr1Button>
                        </div>
                    </div>
                </div>
                <EmptyState v-else title="Links indisponíveis no momento" text="Os links da pesquisa ficam disponíveis enquanto a campanha estiver aberta." />
            </div>

            <div class="n1-card c5">
                <h2>Participação na pesquisa</h2>
                <template v-if="campaign.participation">
                    <div class="n1-big">{{ campaign.participation.percent_label ?? '—' }}</div>
                    <div class="n1-progress"><span :style="{ width: campaign.participation.percent + '%' }"></span></div>
                    <p class="n1-tiny" style="margin-top: 8px">
                        {{ campaign.participation.completed }} respostas
                        <template v-if="campaign.participation.eligible">de <b>{{ campaign.participation.eligible }}</b> colaboradores elegíveis</template>
                    </p>
                </template>
                <p v-else class="n1-tiny">Amostra insuficiente: o número e a participação não são exibidos com poucas respostas.</p>
                <form v-if="campaign.eligibleEditor" style="display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; margin: 12px 0" @submit.prevent="saveEligible">
                    <div>
                        <label class="n1-label" for="eligible-count" style="margin: 0 0 4px">Elegíveis cadastrados</label>
                        <input id="eligible-count" v-model="eligibleForm.eligible_count" type="number" min="1" class="n1-field" style="width: 110px" />
                    </div>
                    <Nr1Button variant="secondary" size="sm" type="submit" :disabled="eligibleForm.processing">Atualizar</Nr1Button>
                    <p v-if="eligibleForm.errors.eligible_count" class="n1-tiny" style="color: #ad3d50; width: 100%">{{ eligibleForm.errors.eligible_count }}</p>
                </form>
                <span class="n1-pill" :class="campaign.collection.open ? 'ok' : 'gray'">{{ campaign.collection.open ? 'Coleta em andamento' : 'Coleta finalizada' }}</span>
            </div>

            <div v-if="content" class="n1-card c12" role="region" aria-live="polite" aria-label="Material">
                <div class="n1-row">
                    <h2>{{ content.title }}</h2>
                    <Nr1Button variant="secondary" size="sm" type="button" @click="content = null">Fechar</Nr1Button>
                </div>
                <p v-for="line in content.paragraphs" :key="line" class="n1-bodycopy">{{ line }}</p>
            </div>
        </div>
    </div>
</template>
