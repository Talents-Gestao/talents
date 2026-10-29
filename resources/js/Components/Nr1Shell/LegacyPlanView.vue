<script setup>
import { computed } from 'vue';
import EmptyState from './EmptyState.vue';
import LegacyNotice from './LegacyNotice.vue';
import Nr1Button from './Nr1Button.vue';

/**
 * Plano de ação de uma campanha do modelo anterior: os itens do plano do parecer (só leitura). A edição continua em
 * «Parecer e publicação»; a empresa vê o plano depois da publicação.
 */
const props = defineProps({
    plan: { type: Object, required: true },
    notice: { type: String, default: 'Pesquisa no modelo anterior' },
});

const STATUS = { pending: 'Pendente', in_progress: 'Em andamento', done: 'Concluída', completed: 'Concluída', open: 'Aberta' };
const withResponsible = computed(() => props.plan.items.filter((item) => item.responsible).length);
</script>

<template>
    <div>
        <div class="n1-intro">
            <div>
                <div class="n1-eyebrow">Plano de ação</div>
                <h1>Da análise à execução.</h1>
                <p>Medidas do plano registrado no parecer técnico desta campanha, com responsáveis e prazos.</p>
            </div>
            <LegacyNotice :text="notice" />
        </div>

        <EmptyState v-if="plan.locked" title="O plano aparece quando a Talents publicar o parecer" text="Você será avisado quando o parecer técnico e o plano de ação estiverem disponíveis." />

        <div v-else class="n1-g12">
            <div class="n1-card n1-stat teal c4"><div class="n1-cap">Ações propostas</div><div class="n1-big">{{ plan.items.length }}</div></div>
            <div class="n1-card n1-stat purple c4"><div class="n1-cap">Com responsável</div><div class="n1-big">{{ withResponsible }}</div></div>
            <div class="n1-card n1-stat gold c4"><div class="n1-cap">Sem responsável</div><div class="n1-big">{{ plan.items.length - withResponsible }}</div></div>

            <section class="n1-card c12" aria-labelledby="legacy-plan-title">
                <div class="n1-sectiontop">
                    <h2 id="legacy-plan-title">Medidas do parecer</h2>
                    <span class="n1-pill" :class="plan.published ? 'ok' : 'gray'">{{ plan.published ? `Publicado em ${plan.publishedAt}` : 'Rascunho' }}</span>
                </div>
                <EmptyState v-if="!plan.items.length" title="Plano de ação ainda não elaborado" text="As medidas aparecem aqui quando forem registradas no parecer técnico." />
                <ul v-else class="n1-planlist">
                    <li v-for="item in plan.items" :key="item.id">
                        <div>
                            <b>{{ item.title }}</b>
                            <p v-if="item.description">{{ item.description }}</p>
                            <p>Responsável: {{ item.responsible || 'a definir' }} · Prazo: {{ item.due || 'a definir' }}</p>
                        </div>
                        <span class="n1-pill blue">{{ STATUS[item.status] ?? item.status }}</span>
                    </li>
                </ul>
                <div class="n1-tools no-print" style="margin-top: 12px">
                    <Nr1Button v-if="plan.pdfUrl" size="sm" :href="plan.pdfUrl" external>Ver impressão do plano ↓</Nr1Button>
                    <Nr1Button v-if="plan.editUrl" size="sm" variant="primary" inertia :href="plan.editUrl">Editar no Parecer e publicação →</Nr1Button>
                </div>
            </section>
        </div>
    </div>
</template>
