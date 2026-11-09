<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmptyState from './EmptyState.vue';
import Nr1Button from './Nr1Button.vue';

/**
 * Central de documentos de uma campanha do modelo anterior. A Talents vê a situação da publicação.
 * A empresa só recebe os arquivos que a Talents já publicou.
 */
const props = defineProps({
    central: { type: Object, required: true },
    notice: { type: String, default: 'Pesquisa no modelo anterior' },
});

const forCompany = computed(() => props.central.parecerUrl == null);

const companyFiles = computed(() => {
    const files = [];

    for (const doc of props.central.documents ?? []) {
        if (doc.url) {
            files.push({ key: doc.key, title: doc.title, text: doc.text, url: doc.url });
        }
    }

    for (const doc of props.central.released?.documents ?? []) {
        if (!doc.download_url || files.some((file) => file.title === doc.label)) {
            continue;
        }
        files.push({
            key: doc.type,
            title: doc.label,
            text: doc.version_number ? `Versão ${doc.version_number}` : '',
            url: doc.download_url,
        });
    }

    if (props.central.released?.briefUrl && !files.some((file) => file.key === 'executivo')) {
        files.push({ key: 'brief', title: 'Resumo executivo', text: '', url: props.central.released.briefUrl });
    }

    return files;
});

const validation = useForm({ declaration_accepted: false });
const validateDocument = (url) => validation.post(url, { preserveScroll: true, onSuccess: () => validation.reset() });
</script>

<template>
    <div>
        <div class="n1-intro">
            <div v-if="forCompany">
                <div class="n1-eyebrow">Central de documentos</div>
                <h1>Documentos da sua empresa.</h1>
                <p>Arquivos que a Talents publicou para esta pesquisa.</p>
            </div>
            <div v-else>
                <div class="n1-eyebrow">Central de documentos</div>
                <h1>Documentos oficiais.</h1>
                <p>Arquivos finais organizados em um só lugar, com revisão documental da PASSEG quando necessária.</p>
            </div>
        </div>

        <div v-if="forCompany">
            <EmptyState
                v-if="!companyFiles.length"
                title="Nenhum documento publicado"
                text="Quando a Talents publicar um arquivo para a sua empresa, ele aparece aqui."
            />
            <section v-else class="n1-card" aria-label="Documentos publicados">
                <div v-for="doc in companyFiles" :key="doc.key" class="n1-docrow">
                    <span class="n1-docico" aria-hidden="true">▤</span>
                    <div>
                        <strong>{{ doc.title }}</strong>
                        <p v-if="doc.text">{{ doc.text }}</p>
                    </div>
                    <Nr1Button size="sm" :href="doc.url" external>Baixar</Nr1Button>
                </div>
            </section>
            <section v-if="central.released?.canValidate && central.released.documents.some((item) => item.validatable)" class="n1-card no-print" style="margin-top: 15px" aria-label="Validação">
                <div v-for="doc in central.released.documents.filter((item) => item.validatable)" :key="doc.type">
                    <h3>Validar {{ doc.label }} · versão {{ doc.version_number }}</h3>
                    <p class="n1-tiny">A validação se refere ao conteúdo congelado da versão atual. O PDF original não é refeito.</p>
                    <label class="n1-tiny" style="display: flex; gap: 8px; align-items: flex-start">
                        <input v-model="validation.declaration_accepted" type="checkbox" />
                        <span>{{ central.released.declaration }}</span>
                    </label>
                    <p v-if="validation.errors.declaration_accepted" class="n1-tiny" role="alert">{{ validation.errors.declaration_accepted }}</p>
                    <Nr1Button size="sm" variant="primary" :loading="validation.processing" @click="validateDocument(doc.validate_url)">Validar {{ doc.label }}</Nr1Button>
                </div>
            </section>
        </div>

        <div v-else class="n1-docgrid">
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
