<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmptyState from './EmptyState.vue';
import Nr1Button from './Nr1Button.vue';
import PublicationChecklist from './PublicationChecklist.vue';
import ReviewStepper from './ReviewStepper.vue';
import ReviewTimeline from './ReviewTimeline.vue';
import { useNr1Toast } from './useNr1Toast';

/**
 * Central de documentos da campanha V11: os dois documentos oficiais, o bloco «Revisão do relatório técnico e plano de
 * ação» com o fluxo em passos, ações por perfil e estado, pendências, ciência e histórico. Só apresentação: cada ação
 * posta para a rota que já existe (Publicar continua pedindo o MFA recente). A empresa só vê a rodada publicada.
 */
const props = defineProps({
    documents: { type: Object, required: true },
});

const { push } = useNr1Toast();
const isTalents = computed(() => props.documents.mode === 'talents');
const round = computed(() => props.documents.round);
const shown = computed(() => (isTalents.value ? (round.value?.versions?.length ? round.value : (props.documents.publishedRound ?? round.value)) : round.value));

const DOCS = [
    { type: 'executive_brief', icon: '▣', title: 'Relatório executivo', text: 'Visão resumida para a liderança: indicadores, prioridades e plano proposto.' },
    { type: 'technical_report', icon: '▤', title: 'Relatório técnico + plano de ação', text: 'Documento único para a PASSEG: metodologia, resultados, pontos fortes, prioridades, medidas, responsáveis, prazos, evidências e acompanhamento.' },
];
const versionOf = (type) => shown.value?.versions?.find((version) => version.type === type) ?? null;

const HELP = {
    draft: 'Documento em revisão pela Talents.',
    in_review: 'O relatório técnico está disponível para revisão documental.',
    changes_requested: 'A Talents pode revisar e reenviar o documento.',
    approved: 'A Talents pode publicar os documentos.',
    published: 'Relatórios liberados para consulta e impressão.',
};
const help = computed(() => HELP[round.value?.status] ?? '');

const back = () => ({ _nr1_return: window.location.pathname + window.location.search });
const post = (url, message, data = {}, confirm = null) => {
    if (confirm && !window.confirm(confirm)) return;
    router.post(url, { ...data, ...back() }, { preserveScroll: true, onSuccess: () => message && push(message) });
};

const sendForm = useForm({ reviewer_id: '', share_days: 30, ...({}) });
const send = () => {
    sendForm.transform((data) => ({ ...data, ...back() })).post(props.documents.urls.send, { preserveScroll: true, onSuccess: () => push('Enviado à revisão da PASSEG.') });
};
const toggleRequirement = (event) => post(props.documents.urls.reviewRequirement, 'Exigência de revisão atualizada.', { requires_passeg_review: event.target.checked });

// Empresa: ciência e integração ao PGR
const pgrForm = useForm({ evidence: null, note: '' });
const confirmPgr = () => pgrForm.post(props.documents.urls.pgrIntegration, { preserveScroll: true, forceFormData: true, onSuccess: () => push('Integração ao PGR confirmada.') });
</script>

<template>
    <div>
        <div class="n1-intro">
            <div>
                <div class="n1-eyebrow">Central de documentos</div>
                <h1>Documentos oficiais.</h1>
                <p>Arquivos finais organizados em um só lugar, com revisão documental da PASSEG quando necessária.</p>
            </div>
        </div>

        <div class="n1-g12">
            <section class="n1-card c12" aria-label="Documentos">
                <div v-for="doc in DOCS" :key="doc.type" class="n1-docrow">
                    <span class="n1-docico" aria-hidden="true">{{ doc.icon }}</span>
                    <div class="n1-docinfo">
                        <strong>{{ doc.title }}</strong>
                        <p>{{ doc.text }}</p>
                        <small v-if="versionOf(doc.type)" class="n1-tiny">Versão {{ versionOf(doc.type).version_number }} · {{ versionOf(doc.type).public_identifier }} · gerado em {{ versionOf(doc.type).generated_at }}</small>
                    </div>
                    <Nr1Button v-if="versionOf(doc.type)?.download_url && versionOf(doc.type)?.pdf_ready" size="sm" :href="versionOf(doc.type).download_url" external>Imprimir ↓</Nr1Button>
                    <Nr1Button v-else size="sm" disabled>{{ isTalents ? 'Ainda não gerado' : 'Indisponível' }}</Nr1Button>
                </div>
            </section>

            <!-- Empresa -->
            <template v-if="!isTalents">
                <section class="n1-card c12" aria-labelledby="doc-company-title">
                    <div class="n1-sectiontop">
                        <h2 id="doc-company-title">Revisão do relatório técnico e plano de ação</h2>
                        <span v-if="round" class="n1-status ok"><span class="n1-dot" aria-hidden="true"></span>Publicado</span>
                    </div>

                    <p v-if="!round" class="n1-notice warn" role="status">Documento em revisão pela Talents.</p>

                    <template v-else>
                        <ReviewStepper status="published" :requires-passeg-review="false" />
                        <dl class="n1-docmeta">
                            <div><dt class="n1-cap">Versão publicada</dt><dd>Revisão {{ round.number }}</dd></div>
                            <div><dt class="n1-cap">Publicado em</dt><dd>{{ round.published_at }}</dd></div>
                            <div><dt class="n1-cap">Protocolo</dt><dd>{{ round.protocol }}</dd></div>
                            <div><dt class="n1-cap">Ciência da empresa</dt><dd>{{ round.acknowledgement ? `${round.acknowledgement.by} em ${round.acknowledgement.at}` : 'Pendente' }}</dd></div>
                        </dl>
                        <p class="n1-tiny">{{ documents.approvalMeaning }}</p>

                        <div v-if="documents.canRecord" class="n1-docactions no-print">
                            <Nr1Button v-if="!round.acknowledgement" variant="primary" size="sm" @click="post(documents.urls.acknowledge, 'Ciência registrada.')">Registrar ciência dos dois documentos</Nr1Button>
                            <form v-if="!round.pgr_integration" class="n1-smallbox" @submit.prevent="confirmPgr">
                                <div class="n1-mini-head">Confirmar a integração ao PGR</div>
                                <p class="n1-tiny">Só com a evidência (documento de SST). Campo separado da ciência.</p>
                                <div class="n1-fields">
                                    <div>
                                        <label class="n1-label" for="pgr-evidence">Evidência (até 20 MB)</label>
                                        <input id="pgr-evidence" type="file" class="n1-field" required @input="pgrForm.evidence = $event.target.files[0]" />
                                        <p v-if="pgrForm.errors.evidence" class="n1-tiny" style="color: #a6334b">{{ pgrForm.errors.evidence }}</p>
                                    </div>
                                    <div>
                                        <label class="n1-label" for="pgr-note">Observação (opcional)</label>
                                        <input id="pgr-note" v-model="pgrForm.note" type="text" class="n1-field" />
                                    </div>
                                </div>
                                <div style="margin-top: 10px"><Nr1Button type="submit" size="sm" :loading="pgrForm.processing">Confirmar integração ao PGR</Nr1Button></div>
                            </form>
                            <p v-else class="n1-tiny">Integração ao PGR confirmada por {{ round.pgr_integration.by }} em {{ round.pgr_integration.at }}.</p>
                        </div>

                        <div class="n1-divider"></div>
                        <h3>Histórico</h3>
                        <ReviewTimeline :history="round.history" />
                    </template>
                </section>
            </template>

            <!-- Talents -->
            <template v-else>
                <section class="n1-card c12" aria-labelledby="doc-review-title">
                    <div class="n1-sectiontop">
                        <h2 id="doc-review-title">Revisão do relatório técnico e plano de ação</h2>
                        <span v-if="round" class="n1-pill" :class="round.status === 'published' ? 'ok' : round.status === 'in_review' || round.status === 'changes_requested' ? 'warn' : round.status === 'approved' ? 'blue' : 'gray'">{{ round.status_label }}</span>
                    </div>

                    <template v-if="round">
                        <ReviewStepper :status="round.status" :requires-passeg-review="documents.requiresPassegReview" />
                        <div class="n1-doc-approval">
                            <div class="n1-row"><b>{{ round.status === 'published' ? 'Documento publicado' : round.status_label }}</b><span class="n1-tag">Versão {{ round.number }}</span></div>
                            <p class="n1-tiny">{{ help }}</p>

                            <PublicationChecklist v-if="round.status !== 'published'" :pending="round.pending" :registration-url="documents.urls.registration" />

                            <div class="n1-docactions no-print">
                                <Nr1Button size="sm" :disabled="!documents.canStart" @click="post(documents.urls.start, 'Nova revisão iniciada.')">Iniciar nova revisão</Nr1Button>
                                <Nr1Button size="sm" :disabled="!round.actions.regenerate" @click="post(documents.urls.regenerate, 'Documentos gerados: nova versão congelada, com hash.')">{{ round.versions.length ? 'Gerar novas versões' : 'Gerar documentos' }}</Nr1Button>
                                <Nr1Button variant="primary" size="sm" :disabled="!round.actions.publish" @click="post(documents.urls.publish, 'Documentos publicados para a empresa.', {}, 'Publicar os dois documentos para a empresa? A publicação pede a verificação em duas etapas recente.')">Publicar</Nr1Button>
                            </div>

                            <form v-if="round.actions.send" class="n1-smallbox n1-sendbox" @submit.prevent="send">
                                <div class="n1-mini-head">Enviar à PASSEG</div>
                                <div class="n1-fields">
                                    <div>
                                        <label class="n1-label" for="send-reviewer">Revisor</label>
                                        <select id="send-reviewer" v-model="sendForm.reviewer_id" class="n1-field" required>
                                            <option value="">Selecione</option>
                                            <option v-for="reviewer in documents.reviewers" :key="reviewer.id" :value="reviewer.id">{{ reviewer.name }}<template v-if="reviewer.organization"> · {{ reviewer.organization }}</template></option>
                                        </select>
                                        <p v-if="sendForm.errors.reviewer_id" class="n1-tiny" style="color: #a6334b">{{ sendForm.errors.reviewer_id }}</p>
                                    </div>
                                    <div>
                                        <label class="n1-label" for="send-days">Prazo de acesso (dias)</label>
                                        <input id="send-days" v-model="sendForm.share_days" type="number" min="1" max="90" class="n1-field" />
                                    </div>
                                </div>
                                <div style="margin-top: 10px"><Nr1Button type="submit" variant="primary" size="sm" :loading="sendForm.processing">Enviar à PASSEG</Nr1Button></div>
                            </form>

                            <p class="n1-passeg-note n1-tiny">
                                <b>Aprovar relatório</b> e <b>Solicitar alteração</b> (com o parecer escrito) são feitos pelo revisor externo em <code>/revisao</code>, não aqui. {{ documents.approvalMeaning }}
                            </p>

                            <label class="n1-switch no-print" :class="{ off: !documents.canChangeReviewRequirement }">
                                <input type="checkbox" :checked="documents.requiresPassegReview" :disabled="!documents.canChangeReviewRequirement" @change="toggleRequirement" />
                                Exigir revisão da PASSEG nesta campanha
                                <small v-if="!documents.canChangeReviewRequirement">(só entre rodadas)</small>
                            </label>

                            <dl v-if="round.published_at" class="n1-docmeta">
                                <div><dt class="n1-cap">Publicado em</dt><dd>{{ round.published_at }} por {{ round.published_by }}</dd></div>
                                <div><dt class="n1-cap">Protocolo de encaminhamento</dt><dd>{{ round.protocol }}</dd></div>
                                <div><dt class="n1-cap">Ciência da empresa</dt><dd>{{ round.acknowledgement ? `${round.acknowledgement.by} em ${round.acknowledgement.at}` : 'Pendente' }}</dd></div>
                                <div><dt class="n1-cap">Integração ao PGR</dt><dd>{{ round.pgr_integration ? `Confirmada por ${round.pgr_integration.by} em ${round.pgr_integration.at}` : 'Pendente' }}</dd></div>
                            </dl>
                            <p v-if="round.published_without_review" class="n1-tiny" style="color: #8a560a">Publicado diretamente pela Talents, sem a revisão da PASSEG (não exigida nesta campanha).</p>
                        </div>
                    </template>
                    <div v-else class="n1-docactions no-print">
                        <p class="n1-tiny">Ainda não há revisão nesta campanha.</p>
                        <Nr1Button variant="primary" size="sm" :disabled="!documents.canStart" @click="post(documents.urls.start, 'Revisão iniciada. Gere os documentos para começar.')">Iniciar revisão</Nr1Button>
                    </div>

                    <div v-if="documents.publishedRound" class="n1-smallbox" style="margin-top: 12px">
                        <div class="n1-mini-head">Última rodada publicada: revisão {{ documents.publishedRound.number }}</div>
                        <p class="n1-tiny">Publicada em {{ documents.publishedRound.published_at }}. A empresa continua vendo esta versão enquanto a nova revisão não for publicada.</p>
                    </div>

                    <div class="n1-divider"></div>
                    <h3>Histórico</h3>
                    <ReviewTimeline :history="round?.history ?? documents.publishedRound?.history ?? []" />
                    <p class="n1-tiny no-print" style="margin-top: 10px">Tela completa com todos os campos: <a :href="documents.urls.full">Central de documentos atual</a>.</p>
                </section>
            </template>
        </div>
    </div>
</template>
