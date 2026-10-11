<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import type { ConferenceProjection } from '../../core/contracts';
import { formatDate } from '../../core/formatters';
import { apiFetch } from '../../core/api';

const props = defineProps<{
  conferences: ConferenceProjection[];
  quota: { used: number; limit: number | null };
}>();

const showNewConferenceForm = ref(false);

const name = ref('');
const startDate = ref('');
const endDate = ref('');
const isCreating = ref(false);
const formErrors = ref<Record<string, string>>({});
const generalError = ref<string | null>(null);

async function handleCreateConference() {
  isCreating.value = true;
  formErrors.value = {};
  generalError.value = null;

  try {
    const created = await apiFetch<ConferenceProjection>('/conferencias', {
      method: 'POST',
      data: {
        name: name.value,
        startDate: startDate.value,
        endDate: endDate.value,
      },
    });

    window.location.href = `/conferencias/${created.id}`;
  } catch (err: unknown) {
    const errorObj = err as { message?: string; errors?: Record<string, string[] | string> };
    if (errorObj?.errors) {
      const mapped: Record<string, string> = {};
      for (const [key, val] of Object.entries(errorObj.errors)) {
        mapped[key] = Array.isArray(val) ? val[0] : String(val);
      }
      formErrors.value = mapped;
    }
    generalError.value = errorObj?.message || 'Falha ao criar conferência.';
  } finally {
    isCreating.value = false;
  }
}
</script>

<template>
  <AppLayout title="Conferências">
    <div class="conferences-index">
      <header class="page-header">
        <div class="header-titles">
          <h1 class="page-title">Conferências</h1>
          <p class="page-subtitle">
            Gestão de lotes de conciliação entre pedidos faturados e repasses do intermediador.
          </p>
        </div>

        <div class="header-actions">
          <div v-if="quota.limit !== null" class="quota-badge tabular-nums">
            {{ quota.used }} de {{ quota.limit }} conferências utilizadas
          </div>
          <button
            type="button"
            class="btn btn-primary"
            data-test="btn-new-conference"
            @click="showNewConferenceForm = !showNewConferenceForm"
          >
            {{ showNewConferenceForm ? 'Fechar formulário' : 'Nova conferência' }}
          </button>
        </div>
      </header>

      <!-- Formulário de Nova Conferência -->
      <section v-if="showNewConferenceForm" class="new-conference-box">
        <h2 class="box-title">Cadastrar nova conferência</h2>

        <div v-if="generalError" class="form-error" role="alert">
          {{ generalError }}
        </div>

        <form @submit.prevent="handleCreateConference" class="conference-form">
          <div class="form-grid">
            <div class="form-group">
              <label for="conf-name" class="form-label">Nome da conferência</label>
              <input
                id="conf-name"
                v-model="name"
                type="text"
                class="input-text"
                placeholder="Ex.: Outubro 2026 - Semana 1"
                required
                :disabled="isCreating"
              />
              <span v-if="formErrors.name" class="form-error">{{ formErrors.name }}</span>
            </div>

            <div class="form-group">
              <label for="conf-start" class="form-label">Data inicial (inclusive)</label>
              <input
                id="conf-start"
                v-model="startDate"
                type="date"
                class="input-text"
                required
                :disabled="isCreating"
              />
              <span v-if="formErrors.startDate" class="form-error">{{ formErrors.startDate }}</span>
            </div>

            <div class="form-group">
              <label for="conf-end" class="form-label">Data final (inclusive)</label>
              <input
                id="conf-end"
                v-model="endDate"
                type="date"
                class="input-text"
                required
                :disabled="isCreating"
              />
              <span v-if="formErrors.endDate" class="form-error">{{ formErrors.endDate }}</span>
            </div>
          </div>

          <div class="form-actions">
            <button
              type="button"
              class="btn btn-secondary"
              :disabled="isCreating"
              @click="showNewConferenceForm = false"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="isCreating"
            >
              {{ isCreating ? 'Criando…' : 'Criar conferência' }}
            </button>
          </div>
        </form>
      </section>

      <!-- Tabela Editorial de Conferências -->
      <section class="conferences-list-section">
        <div v-if="conferences.length === 0" class="empty-state">
          <p class="empty-title">Nenhuma conferência registrada</p>
          <p class="empty-desc">Crie sua primeira conferência para iniciar a importação de CSVs e conferência.</p>
        </div>

        <div v-else class="table-container">
          <table class="editorial-table">
            <thead>
              <tr>
                <th scope="col">Código</th>
                <th scope="col">Nome</th>
                <th scope="col">Período</th>
                <th scope="col">Fontes</th>
                <th scope="col">Conciliação</th>
                <th scope="col" class="num-col">Ações</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="conf in conferences" :key="conf.id">
                <td class="tabular-nums font-semibold">{{ conf.code }}</td>
                <td>
                  <Link :href="`/conferencias/${conf.id}`" class="conf-title-link">
                    {{ conf.name }}
                  </Link>
                </td>
                <td class="tabular-nums">
                  {{ formatDate(conf.startDate) }} a {{ formatDate(conf.endDate) }}
                </td>
                <td>
                  <div class="sources-status">
                    <span
                      class="source-indicator"
                      :class="conf.sources.orders ? 'has-source' : 'no-source'"
                      :title="conf.sources.orders ? 'Fonte de pedidos confirmada' : 'Pedidos pendente'"
                    >
                      Pedidos: {{ conf.sources.orders ? 'OK' : 'Pendente' }}
                    </span>
                    <span class="source-sep">·</span>
                    <span
                      class="source-indicator"
                      :class="conf.sources.payouts ? 'has-source' : 'no-source'"
                      :title="conf.sources.payouts ? 'Fonte de repasses confirmada' : 'Repasses pendente'"
                    >
                      Repasses: {{ conf.sources.payouts ? 'OK' : 'Pendente' }}
                    </span>
                  </div>
                </td>
                <td>
                  <div v-if="conf.run" class="run-status">
                    <span class="badge badge-neutral">Executada</span>
                    <span v-if="conf.progress" class="progress-info tabular-nums">
                      {{ conf.progress.divergences }} div. ({{ conf.progress.reviewed }} rev.)
                    </span>
                  </div>
                  <div v-else class="run-pending">
                    <span v-if="conf.sources.orders && conf.sources.payouts" class="text-muted">
                      Pronta para conciliar
                    </span>
                    <span v-else class="text-muted">
                      Aguardando fontes
                    </span>
                  </div>
                </td>
                <td class="num-col">
                  <Link
                    :href="`/conferencias/${conf.id}`"
                    class="btn btn-secondary btn-table-action"
                  >
                    Abrir
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AppLayout>
</template>

<style scoped>
.conferences-index {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.page-header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--color-border);
}

.page-title {
  font-size: 1.5rem;
  margin: 0;
}

.page-subtitle {
  font-size: 0.875rem;
  color: var(--color-muted);
  margin: 0.25rem 0 0 0;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.quota-badge {
  font-size: 0.8125rem;
  color: var(--color-muted);
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  padding: 0.25rem 0.5rem;
  border-radius: var(--radius);
}

.new-conference-box {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.box-title {
  font-size: 1.0625rem;
  margin: 0;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
  gap: 1rem;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 1rem;
}

.conf-title-link {
  color: var(--color-ink);
  font-weight: 600;
  text-decoration: none;
}

.conf-title-link:hover {
  color: var(--color-copper);
  text-decoration: underline;
}

.sources-status {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.8125rem;
}

.source-indicator.has-source {
  color: var(--color-match);
  font-weight: 600;
}

.source-indicator.no-source {
  color: var(--color-muted);
}

.source-sep {
  color: var(--color-border);
}

.run-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.progress-info {
  font-size: 0.75rem;
  color: var(--color-muted);
}

.text-muted {
  color: var(--color-muted);
  font-size: 0.8125rem;
}

.btn-table-action {
  padding: 0.25rem 0.625rem;
  font-size: 0.8125rem;
}

.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  background-color: var(--color-surface);
  border: 1px dashed var(--color-border);
  border-radius: var(--radius);
}

.empty-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-ink);
  margin: 0 0 0.25rem 0;
}

.empty-desc {
  font-size: 0.875rem;
  color: var(--color-muted);
  margin: 0;
}

.font-semibold {
  font-weight: 600;
}
</style>
