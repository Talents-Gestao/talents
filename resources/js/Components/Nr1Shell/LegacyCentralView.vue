<script setup>
import EmptyState from './EmptyState.vue';
import LegacyNotice from './LegacyNotice.vue';
import Nr1Button from './Nr1Button.vue';

/**
 * Central de documentos de uma campanha do modelo anterior, no desenho do HTML aprovado: à esquerda os documentos
 * oficiais com «Imprimir», à direita a situação da publicação e o histórico real. A publicação, a liberação dos
 * documentos e o parecer ficam em «Parecer e publicação»; a revisão da PASSEG não existe neste modelo.
 */
defineProps({
    central: { type: Object, required: true },
    notice: { type: String, default: 'Pesquisa no modelo anterior' },
});
</script>

<template>
    <div>
        <div class="n1-intro">
            <div>
                <div class="n1-eyebrow">Central de documentos</div>
                <h1>Documentos oficiais.</h1>
                <p>Arquivos finais organizados em um só lugar, com revisão documental da PASSEG quando necessária.</p>
            </div>
            <LegacyNotice :text="notice" />
        </div>

        <div class="n1-docgrid">
            <section class="n1-card" aria-label="Documentos oficiais">
                <div v-for="doc in central.documents" :key="doc.key" class="n1-docrow">
                    <span class="n1-docico" aria-hidden="true">{{ doc.key === 'executivo' ? '▣' : '▤' }}</span>
                    <div>
                        <strong>{{ doc.title }}</strong>
                        <p>{{ doc.text }}</p>
                        <p v-if="!doc.url" class="n1-tiny">{{ doc.pending }}</p>
                    </div>
                    <Nr1Button v-if="doc.url" size="sm" :href="doc.url" external>Imprimir ↓</Nr1Button>
                    <span v-else class="n1-pill gray">Indisponível</span>
                </div>
            </section>

            <section class="n1-card" aria-labelledby="legacy-review-title">
                <div class="n1-sectiontop">
                    <h2 id="legacy-review-title">Revisão do relatório técnico e plano de ação</h2>
                    <span class="n1-pill" :class="central.published ? 'ok' : 'gray'">{{ central.status }}</span>
                </div>
                <div class="n1-row" style="justify-content: flex-start; gap: 10px">
                    <b>{{ central.published ? 'Documento publicado' : 'Documento não publicado' }}</b>
                    <span v-if="central.version" class="n1-pill blue">Versão {{ central.version }}</span>
                </div>
                <p class="n1-tiny">{{ central.help }}</p>
                <p class="n1-tiny">A revisão documental da PASSEG não se aplica a esta pesquisa: ela existe só nas campanhas do questionário de 39 perguntas.</p>
                <div v-if="central.parecerUrl" class="n1-tools no-print" style="margin-top: 12px">
                    <Nr1Button variant="primary" size="sm" inertia :href="central.parecerUrl">{{ central.published ? 'Abrir Parecer e publicação' : 'Publicar em Parecer e publicação' }} →</Nr1Button>
                </div>

                <div class="n1-divider"></div>
                <h3>Histórico</h3>
                <EmptyState v-if="!central.history.length" title="Sem registros ainda" text="Publicações do parecer e versões liberadas dos documentos aparecem aqui." />
                <ul v-else class="n1-history">
                    <li v-for="(row, index) in central.history" :key="index">
                        <time>{{ row.at }}</time>
                        <span>{{ row.label }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
